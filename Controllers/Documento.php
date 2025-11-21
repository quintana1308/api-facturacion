<?php 

// Incluir controladores y librerías necesarias.
// Nota: Ya no necesitamos los controladores individuales como Clientes.php, Movimientos.php, etc.
require_once('Controllers/DocumentoDataCollector.php'); // El recolector de datos.
require_once('Libraries/Core/DocumentoValidators.php');     // El nuevo validador.
require_once('Models/LogJsonModel.php');
require_once('Libraries/Core/Mysql.php');
require_once('Libraries/Core/EmailNotifier.php');
require_once('Libraries/Core/Logger.php');
require_once('Libraries/Core/DocumentQueue.php');

class Documento extends Controllers {

    public $resultado = []; 

    // Propiedades de configuración
    private $token;
    private $empresa;
    private $moneda_base;
    private $factura;

    private $logId;
	private $jsonRequest; 

    public function __construct() {
        try {
            // 1. Obtiene y valida el JSON inicial
            $this->initializeRequest();
            
            // 2. Añadir documento a la cola y procesar
            $this->processWithQueue();
			
        } catch (Exception $e) {
                    // Captura cualquier error (de validación o de BD) y lo formatea
                    //returnError("500", "Error en el procesamiento del lote: " . $e->getMessage());
                    // Si ya se había seteado un código HTTP válido, se usa ese.
            // Si no, se asume 500.
            $code = http_response_code();
            if ($code === 200) {
                $code = 500; // Valor por defecto si no se asignó nada antes
                http_response_code($code);
            }

            $errorResponse = json_encode([
                'status' => false,
                'error' => "Error en el procesamiento del documento: " . $e->getMessage()
            ]);
            
            // Enviar notificación por correo si está configurado
            $this->sendErrorNotification($errorResponse, 'critical');
            
            echo $errorResponse;
            exit;
        }
    }

    // Método que genera la respuesta final en JSON.
    public function documento() {
		
        // 1. Construimos el array de la respuesta
        $data = $this->resultado;
		
        // 2. Convertimos la respuesta a formato JSON
        $jsonResponse = json_encode($data, JSON_PRETTY_PRINT);
		
		// 3. Enviar notificación por correo si hay error (status = false)
        if (isset($data['status']) && $data['status'] === false) {
            $this->sendErrorNotification($jsonResponse, 'processing');
        }
		
        // 4. ACTUALIZAMOS EL LOG con la respuesta
        // Verificamos que tengamos un ID de log para actualizar
        if (!empty($this->logId)) {
            // Creamos una instancia del LogJsonModel para usar su método de actualización
            $logJsonModel = new LogJsonModel();
            $logJsonModel->updateLogResponse($this->logId, $jsonResponse);
        }

        // 4. Imprimimos la respuesta final al cliente
        header('Content-Type: application/json');
        echo $jsonResponse;
    }
    
    // Extrae los datos del POST y valida el token
    private function initializeRequest() {
        
        $postdata = file_get_contents("php://input");
		
        if (!$postdata) {
            $this->sendError(400, "No se recibió un cuerpo de petición válido.");
        }

        $data = json_decode($postdata);
        if (!$data) {
            $this->sendError(400, "No se recibió un JSON válido.");
        }

        if (empty($data->empresa) || empty($data->moneda_base)) {
            $this->sendError(400, "El JSON debe contener 'empresa' y 'moneda base'.");
        }

        $bearerToken = $this->getBearerToken();
        if (!$bearerToken) {
            $this->sendError(401, "No se recibió un token Bearer en el header de autorización.");
        }

		
        $this->validateEmpresa($data->empresa);
        $this->validateToken($bearerToken, $data->empresa);
		
        if($data->empresa !== 'ENV_TEST'){
            $this->validateTasa($data->valor_cambiario_dolar, $data->fecha);
        }
		
        $this->token = $bearerToken;
		$this->empresa = (isset($data->empresa) && $data->empresa !== "") ? $data->empresa : NULL;
		$this->moneda_base = (isset($data->moneda_base) && $data->moneda_base !== "") ? $data->moneda_base : NULL;
		
		$this->validateSucural($data->sucursal->codigo);
		$this->validateDate($data->fecha, $data->tipo_documento);
		$this->validateClient($data->cliente);
        $this->factura = $data;
		$this->jsonRequest = $postdata;
		
        $logJsonModel = new LogJsonModel();
        $this->logId = $logJsonModel->insertLogRequest($data->numero, $postdata, $this->empresa, $this->factura->tipo_documento);

        //new LogJson($data, $this->empresa);
    }

    // El corazón de la nueva arquitectura.
    private function processInvoiceBatch($factura) {
        
        $dataCollector = new DocumentoDataCollector();

        try {
            // =============================================================
            // FASE 1: VALIDACIÓN Y RECOLECCIÓN (Súper rápido, solo memoria)
            // =============================================================
            $numFactura = $factura->numero ?? 'SIN_NUMERO';

            // 1. Validar la estructura completa de la factura. Si falla, lanza excepción.
            DocumentoValidators::validateFacturaCompleta($factura);

            // 2. Recolectar datos maestros únicos.
            $dataCollector->addCliente($factura->cliente);
            $dataCollector->addVendedor($factura->vendedor);
            $dataCollector->addCentroCosto($factura->sucursal);
            
            if (isset($factura->vehiculo)) {
                $factura->vehiculo->documento = $factura->numero;
                $dataCollector->addVehiculo($factura->vehiculo);
            }

            if (isset($factura->cliente->paciente)) {
                $factura->cliente->paciente->codeCliente = $factura->cliente->codigo;
                $dataCollector->addPaciente($factura->cliente->paciente);
            }

            $dataCollector->addSerieFiscal($factura->serie_fiscal);
            if (!empty($factura->movimientos)) {
                foreach ($factura->movimientos as $mov) {
                    $dataCollector->addAlmacen($mov->almacen);
                    $dataCollector->addUnidad($mov->unidad);
                    $dataCollector->addProducto($mov->producto); // Esto recolecta el producto y sus hijos (marca, depto, etc)
                    $dataCollector->addUnidadAgru($mov); 
                }
            }

            if(!empty($factura->recibo->movimientos)){
                foreach ($factura->recibo->movimientos as $mov) {
                    $dataCollector->addBanco($mov->banco, $factura->sucursal);
                }
            }
            
            // =============================================================
            // FASE 2: PERSISTENCIA MASIVA DE DATOS MAESTROS
            // =============================================================
            // Esto se hace una sola vez para todo el lote, para máxima eficiencia.
            // Se envuelve en su propia transacción.
            $this->model->procesarMaestros($dataCollector);

            // =================================================================
            // FASE 3: PROCESAMIENTO INDIVIDUAL DE FACTURAS
            // =================================================================
            try {

                // Cada factura se procesa en su propia transacción.
                $this->model->procesarFactura($factura, $this->moneda_base);
				
                // Si no hay excepción, la factura fue exitosa.
                $this->resultado = [
                    "status" => true,
                    "documento" => $factura->numero,
                    "message" => "Documento procesado correctamente."
                ];
            } catch (Exception $e) {
                // Si procesarFacturaIndividual falla, solo esta factura se marca como error.
                 $this->resultado = [
                    "status" => false,
                    "documento" => $factura->numero,
                    "error" => $e->getMessage() // Mensaje de error específico de la factura
                ];
            }
            

        } catch (Exception $e) {
            // Este catch ahora solo captura errores catastróficos,
            // como un fallo en la inserción de maestros o un error de conexión.
            $this->resultado = ["status" => false, "documento" => $numFactura, "error" => "Error crítico en el factura: " . $e->getMessage()];
        }
    }

    private function validateToken($token, $empresa) {

        $mysql2 = new Mysql2();
        $sqltoken = "SELECT * FROM api_enterprise WHERE ETR_IDENTIF = '$empresa' AND ETR_TOKEN = '$token'";
        $requesttoken = $mysql2->select($sqltoken);

        if (!$requesttoken) {
            http_response_code(403);
            throw new Exception("Acceso denegado: Token inválido.");
        }
    }

    private function validateEmpresa($empresa) {
        
        $mysql2 = new Mysql2();
        $sqlEnterprise = "SELECT * FROM api_enterprise WHERE ETR_IDENTIF = '$empresa'";
        $requestEnterprise = $mysql2->select($sqlEnterprise);

        if (!$requestEnterprise) {
            http_response_code(400);
            // Si la inserción falla, es mejor lanzar un error.
            throw new Exception("La empresa no existe o el identificativo es incorrecto.");
        }
    }
	
	private function validateSucural($code) {

        $mysql = new Mysql($this->empresa);
        $sqlSucursal = "SELECT * FROM adn_centrocostos WHERE CCT_CODIGO = '$code'";
        $requestSucursal = $mysql->select($sqlSucursal);

        if (!$requestSucursal) {
            http_response_code(400);
            // Si la inserción falla, es mejor lanzar un error.
            throw new Exception("La sucursal no existe o el codigo es incorrecto.");
        }
    }
	
	private function validateDate($dateFac, $TipoDoc) {

        $mysql = new Mysql($this->empresa);
        $sqlDate = "SELECT DCL_FECHA AS fecha
                    FROM ADN_DOCCLI
                    WHERE DCL_TDT_CODIGO = '$TipoDoc'
                    ORDER BY DCL_FECHA DESC
                    LIMIT 1;";
        $requestDate = $mysql->select($sqlDate);

        if (!$requestDate) {
            // No hay documentos previos de este tipo (empresa nueva o sin FAV/NEN)
            // Aceptamos la fecha enviada sin validar contra nada
            return;
        }

        if ($dateFac < $requestDate['fecha']) {
            http_response_code(400);
            throw new Exception("La fecha del documento es menor al ultimo documento registrado.");
        }
    }

    private function validateClient($client) {

        $mysql = new Mysql($this->empresa);
        $sqlDate = "SELECT CLT_RIF FROM adn_clientes WHERE CLT_CODIGO = '$client->codigo';";
        $requestDate = $mysql->select($sqlDate);

        if($requestDate){
            if($client->rif != $requestDate['CLT_RIF']){
                http_response_code(400);
                throw new Exception("El rif del cliente no coincide.");
            }
        }
    }

    private function validateTasa($tasa, $fecha) {

        if (!$tasa) {
            http_response_code(500);
            throw new Exception("La tasa de cambio es obligatoria.");
        }else{
			
            $mysql2 = new Mysql2();
            $sql = "SELECT * FROM api_tasas WHERE fecha = '$fecha' AND moneda = 'USD'";
            $request = $mysql2->select($sql);
			
            if (!$request) {
                http_response_code(500);
                throw new Exception("La tasa de cambio no existe.");
            }

            if ($request['tasa'] != $tasa) {
                http_response_code(500);
                throw new Exception("La tasa de cambio no coincide.");
            }
        }
    } 

    private function getBearerToken() {
		$headers = null;
			
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
		
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function sendError($code, $message) {
        http_response_code($code);
        $errorResponse = json_encode([
            'status' => false,
            'error' => $message
        ]);
		
		// Enviar notificación por correo si está configurado
        $this->sendErrorNotification($errorResponse, 'validation');
        
        echo $errorResponse;
		
        exit;
    }
	
	/**
     * Envía notificación por correo cuando hay un error con status = false
     * @param string $jsonResponse La respuesta JSON de error
     * @param string $errorType Tipo de error (validation, processing, critical)
     */
    private function sendErrorNotification($jsonResponse, $errorType = 'processing') {
        try {
            Logger::info("Documento: sendErrorNotification llamado con errorType: $errorType");
            Logger::debug("Documento: Empresa: " . ($this->empresa ?? 'NULL'));
            Logger::debug("Documento: JsonRequest disponible: " . (!empty($this->jsonRequest) ? 'SÍ' : 'NO'));
            
            if (!empty($this->empresa) && !empty($this->jsonRequest)) {
                Logger::info("Documento: Creando EmailNotifier para empresa: " . $this->empresa);
                $emailNotifier = new EmailNotifier();
                $result = $emailNotifier->sendErrorNotification(
                    $this->empresa,
                    $this->jsonRequest,
                    $jsonResponse,
                    $errorType
                );
                Logger::log("Documento: Resultado EmailNotifier: " . ($result ? 'ÉXITO' : 'FALLÓ'), $result ? 'SUCCESS' : 'ERROR');
            } else {
                Logger::info("Documento: No se puede enviar notificación - faltan datos");
            }
        } catch (Exception $e) {
            // Si hay error en el envío de correo, no interrumpir el flujo principal
            Logger::error("Documento: Error enviando notificación por correo: " . $e->getMessage());
        }
    }
    
    /**
     * Procesa el documento usando el sistema de cola profesional
     */
    private function processWithQueue() {
        $queue = new DocumentQueue();
        
        // Determinar prioridad
        $prioridad = ($this->factura->tipo_documento === 'FAV') ? 2 : 1;
        
        // Añadir documento a la cola
        $queueResult = $queue->enqueue($this->factura, $this->empresa, $prioridad);
        
        if (!$queueResult) {
            $this->sendError(500, "Error al añadir documento a la cola de procesamiento");
        }
        
        // Intentar procesar inmediatamente (solo si no hay otros procesándose)
        $this->tryProcessQueue($queue);
    }
    
    /**
     * Intenta procesar documentos de la cola
     */
    private function tryProcessQueue($queue) {
        $maxWaitTime = 60; // Máximo 60 segundos
        $startTime = time();
        $processed = false;
        
        while ((time() - $startTime) < $maxWaitTime && !$processed) {
            // Intentar obtener el siguiente documento (solo si no hay otros procesándose)
            $document = $queue->dequeueNext($this->empresa);
            
            if ($document) {
                try {
                    // Procesar el documento
                    $facturaData = json_decode($document['DQ_JSON_DATA']);
                    
                    // Verificar si es nuestro documento
                    $isOurDocument = ($facturaData->numero === $this->factura->numero);
                    
                    // Llamar al constructor padre para tener acceso al modelo
                    parent::__construct($this->empresa);
                    
                    // Procesar la factura
                    $this->processInvoiceBatch($facturaData);
                    
                    // Marcar como completado
                    $queue->markCompleted($document['DQ_ID'], $this->resultado);
                    
                    if ($isOurDocument) {
                        $processed = true; // Nuestro documento fue procesado
                    }
                    
                } catch (Exception $e) {
                    // Marcar como fallido
                    $queue->markFailed($document['DQ_ID'], $e->getMessage());
                    
                    // Si es nuestro documento, lanzar el error
                    if ($facturaData->numero === $this->factura->numero) {
                        throw $e;
                    }
                }
            } else {
                // No hay documentos disponibles para procesar, esperar un poco
                sleep(2);
            }
        }
        
        if (!$processed) {
            // Si no se procesó en el tiempo límite, informar que está en cola
            $pending = $queue->countPending($this->empresa);
            $this->resultado = [
                "status" => true,
                "documento" => $this->factura->numero,
                "message" => "Documento añadido a la cola. Posición aproximada: $pending. Será procesado en breve."
            ];
        }
    }
}
?>
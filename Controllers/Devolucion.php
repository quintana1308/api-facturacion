<?php 

// Incluir controladores y librerías necesarias.
require_once('Controllers/DocumentoDataCollector.php'); // El recolector de datos.
require_once('Libraries/Core/DocumentoDevolucionValidators.php');     // El validador de documentos.
require_once('Models/LogJsonModel.php');
require_once('Libraries/Core/Mysql.php');
require_once('Libraries/Core/EmailNotifier.php');
require_once('Libraries/Core/Logger.php');
require_once('Libraries/Core/DocumentQueue.php');

class Devolucion extends Controllers {

    public $resultado = []; 

    // Propiedades de configuración
    private $token;
    private $empresa;
    private $moneda_base;
    private $devolucion;
    private $numero_factura_origen;

    private $logId;
    private $jsonRequest;

    public function __construct() {
        try {
            // 1. Obtiene y valida el JSON inicial
            $this->initializeRequest();
            
            // 2. Añadir devolución a la cola y procesar
            $this->processWithQueue();
            
        } catch (Exception $e) {
            // Captura cualquier error (de validación o de BD) y lo formatea
            $code = http_response_code();
            if ($code === 200) {
                $code = 500; // Valor por defecto si no se asignó nada antes
                http_response_code($code);
            }

            $errorResponse = json_encode([
                'status' => false,
                'error' => "Error en el procesamiento de la devolución: " . $e->getMessage()
            ]);
            
            // Enviar notificación por correo si está configurado
            $this->sendErrorNotification($errorResponse, 'critical');
            
            echo $errorResponse;
            exit;
        }
    }

    // Método que genera la respuesta final en JSON.
    public function devolucion() {

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

        // 5. Imprimimos la respuesta final al cliente
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

        if (empty($data->numero_factura_origen)) {
            $this->sendError(400, "El JSON debe contener 'numero_factura_origen' para la devolución.");
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
        $this->numero_factura_origen = (isset($data->numero_factura_origen) && $data->numero_factura_origen !== "") ? $data->numero_factura_origen : NULL;

        $this->validateSucural($data->sucursal->codigo);
        $this->validateClient($data->cliente);

        $this->devolucion = $data;
        $this->jsonRequest = $postdata;
		
        $logJsonModel = new LogJsonModel();
        $this->logId = $logJsonModel->insertLogRequest($data->numero, $postdata, $this->empresa, $this->devolucion->tipo_documento);

        //new LogJson($data, $this->empresa);
    }

    // El corazón del procesamiento de devoluciones.
    private function processDevolucion($devolucion, $empresa) {
        
        $dataCollector = new DocumentoDataCollector();

        try {
            // =============================================================
            // FASE 1: VALIDACIÓN Y RECOLECCIÓN (Súper rápido, solo memoria)
            // =============================================================
            $numDevolucion = $devolucion->numero ?? 'SIN_NUMERO';

            // 1. Validar la estructura completa de la devolución. Si falla, lanza excepción.
            DocumentoDevolucionValidators   ::validateFacturaCompleta($devolucion);

            // 2. Validar que la factura origen existe y obtener sus datos
            $facturaOrigen = $this->model->validarFacturaOrigen($this->numero_factura_origen);
            if (!$facturaOrigen) {
                throw new Exception("La factura origen FAV N° {$this->numero_factura_origen} no existe en el sistema.");
            }

            // 3. Validar que los montos de la devolución coincidan con la factura origen
            $this->model->validarMontosDevolucion($devolucion, $facturaOrigen);

            // 4. Validar que los movimientos de la devolución coincidan con la factura origen
            $this->model->validarMovimientosDevolucion($devolucion, $this->numero_factura_origen);
            
            // 5. Recolectar datos maestros únicos.
            $dataCollector->addCliente($devolucion->cliente);
            $dataCollector->addVendedor($devolucion->vendedor);
            $dataCollector->addCentroCosto($devolucion->sucursal);
            
            if (isset($devolucion->vehiculo)) {
                $devolucion->vehiculo->documento = $devolucion->numero;
                $dataCollector->addVehiculo($devolucion->vehiculo);
            }

            if (isset($devolucion->cliente->paciente)) {
                $devolucion->cliente->paciente->codeCliente = $devolucion->cliente->codigo;
                $dataCollector->addPaciente($devolucion->cliente->paciente);
            }

            $dataCollector->addSerieFiscal($devolucion->serie_fiscal);
            if (!empty($devolucion->movimientos)) {
                foreach ($devolucion->movimientos as $mov) {
                    $dataCollector->addAlmacen($mov->almacen);
                    $dataCollector->addUnidad($mov->unidad);
                    $dataCollector->addProducto($mov->producto); // Esto recolecta el producto y sus hijos (marca, depto, etc)
                    $dataCollector->addUnidadAgru($mov); 
                }
            }
            
            // =============================================================
            // FASE 2: PERSISTENCIA MASIVA DE DATOS MAESTROS
            // =============================================================
            // Esto se hace una sola vez para todo el lote, para máxima eficiencia.
            // Se envuelve en su propia transacción.
            $this->model->procesarMaestros($dataCollector);

            // =================================================================
            // FASE 3: PROCESAMIENTO DE LA DEVOLUCIÓN
            // =================================================================
            try {

                // Cada devolución se procesa en su propia transacción.
                $this->model->procesarDevolucion($devolucion, $this->moneda_base, $this->numero_factura_origen);
				
                // Si no hay excepción, la devolución fue exitosa.
                $this->resultado = [
                    "status" => true,
                    "documento" => $devolucion->numero,
                    "tipo" => "DEV",
                    "factura_origen" => $this->numero_factura_origen,
                    "message" => "Devolución procesada correctamente."
                ];
            } catch (Exception $e) {
                // Si procesarDevolucion falla, se marca como error.
                 $this->resultado = [
                    "status" => false,
                    "documento" => $devolucion->numero,
                    "tipo" => "DEV",
                    "factura_origen" => $this->numero_factura_origen,
                    "error" => $e->getMessage() // Mensaje de error específico de la devolución
                ];
            }
            

        } catch (Exception $e) {
            // Este catch ahora solo captura errores catastróficos,
            // como un fallo en la inserción de maestros o un error de conexión.
            $this->resultado = ["status" => false, "documento" => $numDevolucion, "tipo" => "DEV", "error" => "Error crítico en la devolución: " . $e->getMessage()];
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
            Logger::info("Devolucion: sendErrorNotification llamado con errorType: $errorType");
            Logger::debug("Devolucion: Empresa: " . ($this->empresa ?? 'NULL'));
            Logger::debug("Devolucion: JsonRequest disponible: " . (!empty($this->jsonRequest) ? 'SÍ' : 'NO'));
            
            if (!empty($this->empresa) && !empty($this->jsonRequest)) {
                Logger::info("Devolucion: Creando EmailNotifier para empresa: " . $this->empresa);
                $emailNotifier = new EmailNotifier();
                $result = $emailNotifier->sendErrorNotification(
                    $this->empresa,
                    $this->jsonRequest,
                    $jsonResponse,
                    $errorType
                );
                Logger::log("Devolucion: Resultado EmailNotifier: " . ($result ? 'ÉXITO' : 'FALLÓ'), $result ? 'SUCCESS' : 'ERROR');
            } else {
                Logger::info("Devolucion: No se puede enviar notificación - faltan datos");
            }
        } catch (Exception $e) {
            // Si hay error en el envío de correo, no interrumpir el flujo principal
            Logger::error("Devolucion: Error enviando notificación por correo: " . $e->getMessage());
        }
    }
    
    /**
     * Procesa la devolución usando el sistema de cola profesional
     */
    private function processWithQueue() {
        $queue = new DocumentQueue();
        
        // Determinar prioridad (devoluciones tienen prioridad alta)
        $prioridad = ($this->devolucion->tipo_documento === 'DEV') ? 2 : 1;
        
        // Añadir devolución a la cola
        $queueResult = $queue->enqueue($this->devolucion, $this->empresa, $prioridad);
        
        if (!$queueResult) {
            $this->sendError(500, "Error al añadir devolución a la cola de procesamiento");
        }
        
        // Intentar procesar inmediatamente (solo si no hay otros procesándose)
        $this->tryProcessQueue($queue);
    }
    
    /**
     * Intenta procesar devoluciones de la cola
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
                    $devolucionData = json_decode($document['DQ_JSON_DATA']);
                    
                    // Verificar si es nuestra devolución
                    $isOurDocument = ($devolucionData->numero === $this->devolucion->numero);
                    
                    // Llamar al constructor padre para tener acceso al modelo
                    parent::__construct($this->empresa);
                    
                    // Procesar la devolución
                    $this->processDevolucion($devolucionData, $this->empresa);
                    
                    // Marcar como completado
                    $queue->markCompleted($document['DQ_ID'], $this->resultado);
                    
                    if ($isOurDocument) {
                        $processed = true; // Nuestra devolución fue procesada
                    }
                    
                } catch (Exception $e) {
                    // Marcar como fallido
                    $queue->markFailed($document['DQ_ID'], $e->getMessage());
                    
                    // Si es nuestra devolución, lanzar el error
                    if ($devolucionData->numero === $this->devolucion->numero) {
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
                "documento" => $this->devolucion->numero,
                "tipo" => "DEV",
                "message" => "Devolución añadida a la cola. Posición aproximada: $pending. Será procesada en breve."
            ];
        }
    }
}
?>

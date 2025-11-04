<?php 

// Incluir controladores y librerías necesarias.
// Nota: Ya no necesitamos los controladores individuales como Clientes.php, Movimientos.php, etc.
require_once('Controllers/LoteDataCollector.php'); // El recolector de datos.
require_once('Libraries/Core/DocumentoValidators.php');     // El validador de documentos.
require_once('Models/LogJsonLoteModel.php');
require_once('Libraries/Core/Mysql.php');

class Lote extends Controllers {

    public $total = 0;
    public $procesadas = 0;  
    public $errores = 0; 
    public $resultados = []; 

    // Propiedades de configuración
    private $token;
    private $empresa;
    private $moneda_base;
    private $facturas;

    private $logId;

    public function __construct() {
        try {
            // 1. Obtiene y valida el JSON inicial
            $this->initializeRequest();

            // 2. Llama al constructor padre para tener acceso al modelo
            parent::__construct($this->empresa);
            
            // 3. Inicia el proceso principal de validación y persistencia
            $this->processInvoiceBatch($this->facturas);

        } catch (Exception $e) {
            // Captura cualquier error (de validación o de BD) y lo formatea
            $code = http_response_code();
            if ($code === 200) {
                $code = 500; // Valor por defecto si no se asignó nada antes
                http_response_code($code);
            }

            echo json_encode([
                'status' => false,
                'error' => "Error en el procesamiento del lote: " . $e->getMessage()
            ]);
            exit;
        }
    }

    // Método que genera la respuesta final en JSON.
    public function lote() {

        // 1. Construimos el array de la respuesta
        $data = [
            "status" => $this->errores === 0,
            "resumen" => [
                "total" => $this->total,
                "procesadas" => $this->procesadas,
                "errores" => $this->errores
            ],
            "documentos" => $this->resultados
        ];

        // 2. Convertimos la respuesta a formato JSON
        $jsonResponse = json_encode($data, JSON_PRETTY_PRINT);

        // 3. ACTUALIZAMOS EL LOG con la respuesta
        // Verificamos que tengamos un ID de log para actualizar
        if (!empty($this->logId)) {
            // Creamos una instancia del LogJsonModel para usar su método de actualización
            $logJsonModel = new LogJsonLoteModel();
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

        // Obtener token desde header Authorization Bearer
        $token = $this->getBearerToken();
        if (!$token) {
            $this->sendError(401, "Token de autorización Bearer requerido en headers.");
        }

        $this->validateEmpresa($data->empresa);
        $this->validateToken($token, $data->empresa);

        $this->token = $token;
		$this->empresa = (isset($data->empresa) && $data->empresa !== "") ? $data->empresa : NULL;
		$this->moneda_base = (isset($data->moneda_base) && $data->moneda_base !== "") ? $data->moneda_base : NULL;
		$this->facturas = (isset($data->facturas) && is_array($data->facturas)) ? $data->facturas : NULL;
		
        if (empty($this->facturas)) {
            $this->sendError(400, "El JSON debe contener un array de 'facturas'.");
        }

        // --- NUEVA LÓGICA DE LOG ---
        // 1. Preparamos el string con los números de factura
        $numerosFactura = array_map(function($factura) {
            return ($factura->numero ?? 'SN') . '-' . ($factura->tipo_documento ?? 'SD');
        }, $this->facturas);
        $stringNumeros = implode(', ', $numerosFactura);

        $logJsonModel = new LogJsonLoteModel();
        $this->logId = $logJsonModel->insertLogRequest($stringNumeros, $postdata,  $this->empresa);

    }

    // El corazón de la nueva arquitectura.
    private function processInvoiceBatch(array $facturas) {
        
        $dataCollector = new LoteDataCollector();
        $this->total = count($facturas);

        try {
            // =============================================================
            // FASE 1: VALIDACIÓN Y RECOLECCIÓN (Súper rápido, solo memoria)
            // =============================================================
             foreach ($facturas as $factura) {

                $this->validateTasa($factura->valor_cambiario_dolar, $factura->fecha);
                $this->validateSucural($factura->sucursal->codigo);
                $this->validateDate($factura->fecha, $factura->tipo_documento);
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
                        $dataCollector->addBanco($mov->banco);
                    }
                }
            }

            // =============================================================
            // FASE 2: PERSISTENCIA MASIVA DE DATOS MAESTROS
            // =============================================================
            // Esto se hace una sola vez para todo el lote, para máxima eficiencia.
            // Se envuelve en su propia transacción.
            $this->model->procesarMaestrosEnLote($dataCollector);


            // =================================================================
            // FASE 3: PROCESAMIENTO INDIVIDUAL DE FACTURAS
            // =================================================================
            foreach ($facturas as $factura) {
                try {

                    // Cada factura se procesa en su propia transacción.
                    $this->model->procesarFacturaIndividual($factura, $this->moneda_base, $this->empresa);

                    // Si no hay excepción, la factura fue exitosa.
                    $this->procesadas++;
                    $this->resultados[] = [
                        "documento" => $factura->numero,
                        "status" => true,
                        "message" => "Documento procesado correctamente."
                    ];
                } catch (Exception $e) {
                    // Si procesarFacturaIndividual falla, solo esta factura se marca como error.
                    $this->errores++;
                    $this->resultados[] = [
                        "documento" => $factura->numero,
                        "status" => false,
                        "error" => $e->getMessage() // Mensaje de error específico de la factura
                    ];
                }
            }

        } catch (Exception $e) {
            // Este catch ahora solo captura errores catastróficos,
            // como un fallo en la inserción de maestros o un error de conexión.
            $this->errores = $this->total;
            $this->procesadas = 0;
            $this->resultados[] = ["documento" => "EL LOTE COMPLETO", "status" => false, "error" => "Error crítico en el lote: " . $e->getMessage()];
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
            http_response_code(400);
            throw new Exception("La fecha de la factura no existe.");
        }

        if ($dateFac < $requestDate['fecha']) {
            http_response_code(400);
            throw new Exception("La fecha del documento es menor al ultimo documento registrado.");
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
        echo json_encode([
            'status' => false,
            'error' => $message
        ]);
        exit;
    }
}
?>
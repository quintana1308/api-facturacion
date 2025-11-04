<?php 

// Incluir controladores y librerías necesarias.
require_once('Libraries/Core/NotaCreditoValidators.php');     // El validador de documentos.
require_once('Models/LogJsonModel.php');

class NotaCredito extends Controllers {

    public $resultado = []; 

    // Propiedades de configuración
    private $token;
    private $empresa;
    private $moneda_base;
    private $notacredito;
    private $numero_factura_origen;

    private $logId;

    public function __construct() {
        try {
            // 1. Obtiene y valida el JSON inicial
            $this->initializeRequest();

            // 2. Llama al constructor padre para tener acceso al modelo
            parent::__construct($this->empresa);
            
            // 3. Inicia el proceso principal de validación y persistencia
            $this->processNotaCredito($this->notacredito);

        } catch (Exception $e) {
            // Captura cualquier error (de validación o de BD) y lo formatea
            $code = http_response_code();
            if ($code === 200) {
                $code = 500; // Valor por defecto si no se asignó nada antes
                http_response_code($code);
            }

            echo json_encode([
                'status' => false,
                'error' => "Error en el procesamiento de la nota de crédito: " . $e->getMessage()
            ]);
            exit;
        }
    }

    // Método que genera la respuesta final en JSON.
    public function notacredito() {

        // 1. Construimos el array de la respuesta
        $data = $this->resultado;

        // 2. Convertimos la respuesta a formato JSON
        $jsonResponse = json_encode($data, JSON_PRETTY_PRINT);

        // 3. ACTUALIZAMOS EL LOG con la respuesta
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

        if (empty($data->numero_factura_origen)) {
            $this->sendError(400, "El JSON debe contener 'numero_factura_origen' para la nota de crédito.");
        }

        $bearerToken = $this->getBearerToken();
        if (!$bearerToken) {
            $this->sendError(401, "No se recibió un token Bearer en el header de autorización.");
        }

        $this->validateEmpresa($data->empresa);
        $this->validateToken($bearerToken, $data->empresa);

        $this->token = $bearerToken;
		$this->empresa = (isset($data->empresa) && $data->empresa !== "") ? $data->empresa : NULL;
		$this->moneda_base = (isset($data->moneda_base) && $data->moneda_base !== "") ? $data->moneda_base : NULL;
        $this->numero_factura_origen = (isset($data->numero_factura_origen) && $data->numero_factura_origen !== "") ? $data->numero_factura_origen : NULL;

        $this->notacredito = $data;

		
        $logJsonModel = new LogJsonModel();
        $this->logId = $logJsonModel->insertLogRequest($data->numero, $postdata, $this->empresa, $this->notacredito->tipo_documento);

        //new LogJson($data, $this->empresa);
    }

    // El corazón del procesamiento de devoluciones.
    private function processNotaCredito($notacredito) {

        try {
            // =============================================================
            // FASE 1: VALIDACIÓN Y RECOLECCIÓN (Súper rápido, solo memoria)
            // =============================================================
            $numNotaCredito = $notacredito->numero ?? 'SIN_NUMERO';

            // 1. Validar la estructura completa de la devolución. Si falla, lanza excepción.
            NotaCreditoValidators::validateNotaCreditoCompleta($notacredito);

            // 2. Validar que la factura origen existe y obtener sus datos
            $facturaOrigen = $this->model->validarFacturaOrigen($this->numero_factura_origen);
            if (!$facturaOrigen) {
                throw new Exception("La factura origen FAV N° {$this->numero_factura_origen} no existe en el sistema.");
            }

            // 3. Validar que los montos de la devolución coincidan con la factura origen
            $this->model->validarMontosNotaCredito($notacredito, $facturaOrigen);

            // =================================================================
            // FASE 3: PROCESAMIENTO DE LA DEVOLUCIÓN
            // =================================================================
            try {

                // Cada devolución se procesa en su propia transacción.
                $this->model->procesarNotaCredito($notacredito, $this->moneda_base, $this->numero_factura_origen);
				
                // Si no hay excepción, la devolución fue exitosa.
                $this->resultado = [
                    "status" => true,
                    "documento" => $notacredito->numero,
                    "tipo" => "CREV",
                    "factura_origen" => $this->numero_factura_origen,
                    "message" => "Nota de crédito procesada correctamente."
                ];
            } catch (Exception $e) {
                // Si procesarDevolucion falla, se marca como error.
                 $this->resultado = [
                    "status" => false,
                    "documento" => $notacredito->numero,
                    "tipo" => "CREV",
                    "factura_origen" => $this->numero_factura_origen,
                    "error" => $e->getMessage() // Mensaje de error específico de la devolución
                ];
            }
            

        } catch (Exception $e) {
            // Este catch ahora solo captura errores catastróficos,
            // como un fallo en la inserción de maestros o un error de conexión.
            $this->resultado = ["status" => false, "documento" => $numNotaCredito, "tipo" => "CREV", "error" => "Error crítico en la nota de crédito: " . $e->getMessage()];
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

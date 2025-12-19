<?php 

require_once('Models/LogJsonModel.php');

// Definición de la clase Documento que hereda de Controllers
class UpdateBankAccount extends Controllers {

    public $resultado = []; 

    // Propiedades de configuración
    private $token;
    private $empresa;
    private $moneda_base;
    private $factura;

    private $logId;
	
    // Constructor de la clase
    public function __construct() {

        try {

            // 1. Obtiene y valida el JSON inicial
            $this->initializeRequest();

            // 2. Llama al constructor padre para tener acceso al modelo
            parent::__construct($this->empresa);
            
            // 3. Inicia el proceso principal de validación y persistencia
            $this->processInvoiceBatch($this->factura);

        } catch (Exception $e) {
            // Maneja errores y devuelve un mensaje de error 500
            $code = http_response_code();
            if ($code === 200) {
                $code = 500; // Valor por defecto si no se asignó nada antes
                http_response_code($code);
            }

            echo json_encode([
                'status' => false,
                'error' => "Error en el procesamiento de la factura: " . $e->getMessage()
            ]);
            exit;
        }
    }

    // Método para devolver la factura en formato JSON
    public function updateBankAccount() {
		
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

        if (empty($data->empresa)) {
            $this->sendError(400, "El JSON debe contener 'empresa'.");
        }

        $bearerToken = $this->getBearerToken();
        if (!$bearerToken) {
            $this->sendError(401, "No se recibió un token Bearer en el header de autorización.");
        }

        $this->validateEmpresa($data->empresa);
        $this->validateToken($bearerToken, $data->empresa);

        $this->token = $bearerToken;
		$this->empresa = (isset($data->empresa) && $data->empresa !== "") ? $data->empresa : NULL;

        $this->factura = $data;

		
        $logJsonModel = new LogJsonModel();
        $this->logId = $logJsonModel->insertLogRequest($data->movimiento->referencia_antigua, $postdata, $this->empresa, 'UPDATEBANKACCOUNT');

    }

    // El corazón de la nueva arquitectura.
    private function processInvoiceBatch($factura) {
        
        try {

            $numFactura = $factura->numero ?? 'SIN_NUMERO';

            try {
                // Cada factura se procesa en su propia transacción.
                $this->model->procesarBankAccount($factura);
				
                // Si no hay excepción, la factura fue exitosa.
                $this->resultado = [
                    "status" => true,
                    "N° Transacción" => $factura->movimiento->referencia_nueva,
                    "message" => "Datos bancarios actualizados correctamente."
                ];
            } catch (Exception $e) {
                // Si procesarFacturaIndividual falla, solo esta factura se marca como error.
                 $this->resultado = [
                    "status" => false,
                    "N° Transacción" => $factura->movimiento->referencia_nueva,
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
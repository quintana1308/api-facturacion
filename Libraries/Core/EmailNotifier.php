<?php 

require_once('Libraries/Core/Conexion2.php');
require_once('Libraries/Core/Mysql2.php');
require_once('Libraries/Core/Logger.php');

class EmailNotifier {
    
    private $mysql2;
    
    public function __construct() {
        $this->mysql2 = new Mysql2();
    }
    
    /**
     * Envía notificación de error por correo si está habilitado para la empresa
     * @param string $empresa Código de la empresa
     * @param string $jsonRequest JSON de la petición original
     * @param string $jsonResponse JSON de la respuesta de error
     * @param string $errorType Tipo de error (validation, processing, critical)
     * @return bool
     */
    public function sendErrorNotification($empresa, $jsonRequest, $jsonResponse, $errorType = 'processing') {
        
        try {
            Logger::info("EmailNotifier: Iniciando envío de notificación para empresa: $empresa");
            
            // 1. Verificar si la empresa tiene habilitadas las notificaciones
            $enterpriseData = $this->getEnterpriseNotificationConfig($empresa);
            Logger::debug("EmailNotifier: Datos de empresa obtenidos: " . json_encode($enterpriseData));
            
            if (!$enterpriseData || $enterpriseData['ETR_NOTIFICATION_EMAIL'] != '1') {
                Logger::info("EmailNotifier: Notificaciones no habilitadas para empresa $empresa");
                return false;
            }
            
            // 2. Obtener los correos destinatarios
            $emails = $this->parseEmails($enterpriseData['ETR_EMAIL']);
            Logger::debug("EmailNotifier: Correos parseados: " . json_encode($emails));
            
            if (empty($emails)) {
                Logger::info("EmailNotifier: No hay correos configurados para empresa $empresa");
                return false;
            }
            
            // 3. Preparar el contenido del correo
            $subject = $this->buildSubject($empresa, $errorType);
            $htmlBody = $this->buildHtmlBody($empresa, $jsonRequest, $jsonResponse, $errorType);
            Logger::debug("EmailNotifier: Asunto preparado: $subject");
            
            // 4. Enviar el correo
            $result = $this->sendMail($emails, $subject, $htmlBody);
            Logger::log("EmailNotifier: Resultado del envío: " . ($result ? 'ÉXITO' : 'FALLÓ'), $result ? 'SUCCESS' : 'ERROR');
            
            return $result;
            
        } catch (Exception $e) {
            // Si hay error en el envío de correo, no interrumpir el flujo principal
            Logger::error("EmailNotifier: Error enviando notificación por correo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene la configuración de notificaciones de la empresa
     * @param string $empresa
     * @return array|false
     */
    private function getEnterpriseNotificationConfig($empresa) {
        
        $sql = "SELECT ETR_NOTIFICATION_EMAIL, ETR_EMAIL 
                FROM api_enterprise 
                WHERE ETR_IDENTIF = '$empresa'";
        
        $result = $this->mysql2->select($sql);
        
        return $result ? $result : false;
    }
    
    /**
     * Parsea la cadena de correos separados por coma
     * @param string $emailString
     * @return array
     */
    private function parseEmails($emailString) {
        
        Logger::debug("EmailNotifier: parseEmails - Input string: '$emailString'");
        
        if (empty($emailString)) {
            Logger::debug("EmailNotifier: parseEmails - String vacío, retornando array vacío");
            return [];
        }
        
        $emails = explode(',', $emailString);
        Logger::debug("EmailNotifier: parseEmails - Después de explode: " . json_encode($emails));
        
        $validEmails = [];
        
        foreach ($emails as $index => $email) {
            $originalEmail = $email;
            $email = trim($email);
            Logger::debug("EmailNotifier: parseEmails - Procesando [$index]: '$originalEmail' -> '$email'");
            
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
                Logger::debug("EmailNotifier: parseEmails - Email válido añadido: '$email'");
            } else {
                Logger::error("EmailNotifier: parseEmails - Email inválido rechazado: '$email'");
            }
        }
        
        Logger::debug("EmailNotifier: parseEmails - Resultado final: " . json_encode($validEmails));
        return $validEmails;
    }
    
    /**
     * Construye el asunto del correo
     * @param string $empresa
     * @param string $errorType
     * @return string
     */
    private function buildSubject($empresa, $errorType) {
        
        $typeLabels = [
            'validation' => 'Error de Validación',
            'processing' => 'Error de Procesamiento',
            'critical' => 'Error Crítico'
        ];
        
        $label = $typeLabels[$errorType] ?? 'Error';
        
        return "[$label] API Facturación - Empresa: $empresa - " . date('Y-m-d H:i:s');
    }
    
    /**
     * Construye el cuerpo HTML del correo
     * @param string $empresa
     * @param string $jsonRequest
     * @param string $jsonResponse
     * @param string $errorType
     * @return string
     */
    private function buildHtmlBody($empresa, $jsonRequest, $jsonResponse, $errorType) {
        
        $fecha = date('Y-m-d H:i:s');
        $requestFormatted = $this->formatJson($jsonRequest);
        $responseFormatted = $this->formatJson($jsonResponse);
        
        $typeLabels = [
            'validation' => 'Error de Validación',
            'processing' => 'Error de Procesamiento', 
            'critical' => 'Error Crítico'
        ];
        
        $errorLabel = $typeLabels[$errorType] ?? 'Error';
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Notificación de Error - API Facturación</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 800px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background-color: #dc3545; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .info-section { margin-bottom: 20px; }
                .info-label { font-weight: bold; color: #333; }
                .json-container { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 10px 0; }
                .json-content { font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow-y: auto; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🚨 $errorLabel - API Facturación</h2>
                </div>
                
                <div class='info-section'>
                    <p><span class='info-label'>Empresa:</span> $empresa</p>
                    <p><span class='info-label'>Fecha y Hora:</span> $fecha</p>
                    <p><span class='info-label'>Tipo de Error:</span> $errorLabel</p>
                </div>
                
                <div class='info-section'>
                    <h3>📥 JSON de Petición Recibido:</h3>
                    <div class='json-container'>
                        <div class='json-content'>$requestFormatted</div>
                    </div>
                </div>
                
                <div class='info-section'>
                    <h3>📤 Respuesta de Error Generada:</h3>
                    <div class='json-container'>
                        <div class='json-content'>$responseFormatted</div>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Este es un mensaje automático generado por el sistema de API de Facturación.</p>
                    <p>Por favor, no responder a este correo.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Formatea JSON para mostrar en HTML
     * @param string $json
     * @return string
     */
    private function formatJson($json) {
        
        if (empty($json)) {
            return 'No disponible';
        }
        
        // Intentar decodificar y recodificar para formatear
        $decoded = json_decode($json);
        if ($decoded !== null) {
            return htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        }
        
        // Si no se puede decodificar, mostrar tal como está
        return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Envía el correo usando la API de Resend
     * @param array $emails
     * @param string $subject
     * @param string $htmlBody
     * @return bool
     */
    private function sendMail($emails, $subject, $htmlBody) {
        
        try {
            $allSuccess = true;
            $sentCount = 0;
            $totalEmails = count($emails);
            
            Logger::info("EmailNotifier: sendMail - Iniciando envío a $totalEmails destinatarios");
            Logger::debug("EmailNotifier: sendMail - Lista de destinatarios: " . json_encode($emails));
            
            // Enviar correo individual a cada destinatario
            foreach ($emails as $index => $email) {
                Logger::info("EmailNotifier: Enviando correo " . ($index + 1) . "/$totalEmails a: $email");
                
                // Preparar los datos para la API de Resend
                $data = [
                    'from' => MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
                    'to' => [$email], // Array con un solo correo
                    'subject' => $subject,
                    'html' => $htmlBody
                ];
                
                // Realizar la petición a la API de Resend
                $response = $this->makeResendApiCall($data);
                
                if ($response && isset($response['id'])) {
                    Logger::success("EmailNotifier: Correo enviado exitosamente a $email (ID: " . $response['id'] . ")");
                    $sentCount++;
                } else {
                    Logger::error("EmailNotifier: Error enviando a $email - Respuesta: " . json_encode($response));
                    $allSuccess = false;
                }
                
                // Delay inteligente basado en el tipo de correo
                if (count($emails) > 1 && $index < count($emails) - 1) {
                    $nextEmail = $emails[$index + 1];
                    $delay = $this->calculateDelay($email, $nextEmail);
                    
                    Logger::debug("EmailNotifier: Esperando $delay segundos antes del siguiente envío...");
                    usleep($delay * 1000000); // Convertir a microsegundos
                }
            }
            
            Logger::info("EmailNotifier: Resumen de envío - Enviados: $sentCount/" . count($emails));
            
            return $allSuccess && $sentCount > 0;
            
        } catch (Exception $e) {
            Logger::error("Error enviando correo con Resend: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Realiza la llamada a la API de Resend
     * @param array $data
     * @return array|false
     */
    private function makeResendApiCall($data) {
        
        $url = 'https://api.resend.com/emails';
        
        $headers = [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            Logger::error("cURL Error: " . $error);
            return false;
        }
        
        if ($httpCode !== 200) {
            Logger::error("Resend API HTTP Error: " . $httpCode . " - Response: " . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Calcula el delay apropiado entre envíos basado en el tipo de correo
     * @param string $currentEmail
     * @param string $nextEmail
     * @return float Delay en segundos
     */
    private function calculateDelay($currentEmail, $nextEmail) {
        
        // Dominios corporativos comunes que requieren más delay
        $corporateDomains = [
            'sistemasadn.com',
            'outlook.com',
            'hotmail.com',
            'live.com',
            'yahoo.com',
            'aol.com',
            'icloud.com'
        ];
        
        $currentDomain = substr(strrchr($currentEmail, "@"), 1);
        $nextDomain = substr(strrchr($nextEmail, "@"), 1);
        
        $currentIsCorporate = in_array($currentDomain, $corporateDomains) || $this->isLikelyCorporate($currentDomain);
        $nextIsCorporate = in_array($nextDomain, $corporateDomains) || $this->isLikelyCorporate($nextDomain);
        
        Logger::debug("EmailNotifier: Análisis de dominios - Actual: $currentDomain (Corp: " . ($currentIsCorporate ? 'Sí' : 'No') . "), Siguiente: $nextDomain (Corp: " . ($nextIsCorporate ? 'Sí' : 'No') . ")");
        
        // Determinar delay basado en los tipos
        if ($currentIsCorporate && $nextIsCorporate) {
            return 2.0; // 2 segundos entre correos corporativos
        } elseif ($currentIsCorporate || $nextIsCorporate) {
            return 1.5; // 1.5 segundos si uno es corporativo
        } else {
            return 0.5; // 0.5 segundos para correos personales
        }
    }
    
    /**
     * Determina si un dominio es probablemente corporativo
     * @param string $domain
     * @return bool
     */
    private function isLikelyCorporate($domain) {
        
        // Patrones que sugieren correo corporativo
        $corporatePatterns = [
            '/\.com\.ve$/',     // Dominios venezolanos
            '/\.org\.ve$/',
            '/\.net\.ve$/',
            '/\.edu\.ve$/',
            '/\.gob\.ve$/',
            '/\.mil\.ve$/',
            '/\.co\.[a-z]{2}$/', // Dominios .co.xx
            '/\.edu$/',          // Educativos
            '/\.gov$/',          // Gubernamentales
            '/\.mil$/',          // Militares
            '/\.org$/'           // Organizaciones
        ];
        
        foreach ($corporatePatterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }
        
        // Si no es gmail, yahoo, hotmail, etc., probablemente es corporativo
        $personalDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'aol.com'];
        
        return !in_array($domain, $personalDomains);
    }
}

?>

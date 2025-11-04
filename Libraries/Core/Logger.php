<?php

class Logger {
    
    private static $logFile = null;
    
    /**
     * Inicializa el archivo de log
     */
    private static function initLogFile() {
        if (self::$logFile === null) {
            $logDir = dirname(__DIR__, 2) . '/logs';
            
            // Crear directorio logs si no existe
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            self::$logFile = $logDir . '/email_notifications.log';
        }
    }
    
    /**
     * Escribe un mensaje en el archivo de log
     * @param string $message
     * @param string $level
     */
    public static function log($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log de información
     * @param string $message
     */
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    /**
     * Log de error
     * @param string $message
     */
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    /**
     * Log de debug
     * @param string $message
     */
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }
    
    /**
     * Log de éxito
     * @param string $message
     */
    public static function success($message) {
        self::log($message, 'SUCCESS');
    }
    
    /**
     * Obtiene la ruta del archivo de log
     * @return string
     */
    public static function getLogFile() {
        self::initLogFile();
        return self::$logFile;
    }
    
    /**
     * Limpia el archivo de log
     */
    public static function clear() {
        self::initLogFile();
        file_put_contents(self::$logFile, '');
    }
}

?>

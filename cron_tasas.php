<?php
/**
 * Tarea programada para obtener tasas de cambio
 * Ejecutar diariamente a las 7:00 PM
 * Obtiene las tasas del día actual y las almacena en la tabla api_tasas
 */

// Incluir archivos necesarios
require_once(__DIR__ . "/Config/Config.php");
require_once(__DIR__ . "/Libraries/Core/Conexion2.php");
require_once(__DIR__ . "/Libraries/Core/Mysql2.php");

class TasasCron {
    private $mysql;
    private $apiUrl = 'https://banking.apps-adn.com/tasas/getTasa/all';
    
    public function __construct() {
        $this->mysql = new Mysql2();
    }
    
    /**
     * Ejecuta la tarea de obtención y almacenamiento de tasas
     */
    public function ejecutar() {
        try {
            echo "[" . date('Y-m-d H:i:s') . "] Iniciando tarea de actualización de tasas...\n";
            
            // Obtener datos de la API
            $datos = $this->obtenerTasasAPI();
            
            if (!$datos) {
                throw new Exception("No se pudieron obtener los datos de la API");
            }
            
            // Procesar y guardar las tasas
            $resultado = $this->procesarYGuardarTasas($datos);
            
            if ($resultado) {
                echo "[" . date('Y-m-d H:i:s') . "] Tasas actualizadas correctamente\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Error al actualizar las tasas\n";
            }
            
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Obtiene las tasas de la API externa
     */
    private function obtenerTasasAPI() {
        // Configurar contexto para la petición HTTP
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; TasasCron/1.0)',
                    'Accept: application/json'
                ]
            ]
        ]);
        
        // Realizar petición
        $response = @file_get_contents($this->apiUrl, false, $context);
        
        if ($response === false) {
            echo "[" . date('Y-m-d H:i:s') . "] Error al realizar petición HTTP a la API\n";
            return false;
        }
        
        // Decodificar JSON
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "[" . date('Y-m-d H:i:s') . "] Error al decodificar JSON: " . json_last_error_msg() . "\n";
            return false;
        }
        
        // Validar estructura de respuesta
        if (!isset($data['status']) || !$data['status'] || !isset($data['tasas'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Respuesta de API inválida\n";
            return false;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Datos obtenidos de la API correctamente\n";
        return $data['tasas'];
    }
    
    /**
     * Procesa las tasas y las guarda en la base de datos
     */
    private function procesarYGuardarTasas($tasas) {
        // Fecha del día actual
        $fechaActual = date('Y-m-d');
        
        $exito = true;
        
        foreach ($tasas as $moneda => $tasa) {
            // Convertir coma a punto en la tasa
            $tasaFormateada = str_replace(',', '.', $tasa);
            
            // Validar que la tasa sea numérica
            if (!is_numeric($tasaFormateada)) {
                echo "[" . date('Y-m-d H:i:s') . "] Tasa inválida para $moneda: $tasa\n";
                continue;
            }
            
            // Preparar datos para inserción
            $datos = [
                'fecha' => $fechaActual,
                'tasa' => $tasaFormateada,
                'moneda' => strtoupper($moneda),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Usar REPLACE para evitar duplicados (actualiza si existe, inserta si no existe)
            $query = "REPLACE INTO api_tasas (fecha, tasa, moneda, created_at) VALUES (?, ?, ?, ?)";
            
            $resultado = $this->mysql->replace($query, [
                $datos['fecha'],
                $datos['tasa'],
                $datos['moneda'],
                $datos['created_at']
            ]);
            
            if ($resultado) {
                echo "[" . date('Y-m-d H:i:s') . "] Tasa guardada - Moneda: {$datos['moneda']}, Fecha: {$datos['fecha']}, Tasa: {$datos['tasa']}\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Error al guardar tasa para {$datos['moneda']}\n";
                $exito = false;
            }
        }
        
        return $exito;
    }
    
    /**
     * Verifica si ya existen tasas para una fecha y moneda específica
     */
    private function existeTasa($fecha, $moneda) {
        $query = "SELECT COUNT(*) as total FROM api_tasas WHERE fecha = ? AND moneda = ?";
        $stmt = $this->mysql->select("SELECT COUNT(*) as total FROM api_tasas WHERE fecha = '$fecha' AND moneda = '$moneda'");
        
        return isset($stmt['total']) && $stmt['total'] > 0;
    }
}

// Ejecutar la tarea si el archivo se ejecuta directamente
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $cron = new TasasCron();
    $cron->ejecutar();
} else {
    // Si se accede desde navegador, mostrar información
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Cron Tasas</title></head><body>";
    echo "<h1>Tarea Programada - Actualización de Tasas</h1>";
    echo "<p>Este archivo debe ejecutarse como tarea programada.</p>";
    echo "<p>Comando para cron: <code>php " . __FILE__ . "</code></p>";
    echo "<p>Programar para ejecutar diariamente a las 19:00 (7:00 PM)</p>";
    echo "<hr>";
    echo "<h2>Ejecutar manualmente (solo para pruebas):</h2>";
    
    if (isset($_GET['ejecutar']) && $_GET['ejecutar'] === '1') {
        echo "<pre>";
        $cron = new TasasCron();
        $cron->ejecutar();
        echo "</pre>";
    } else {
        echo "<a href='?ejecutar=1'>Ejecutar ahora</a>";
    }
    
    echo "</body></html>";
}
?>

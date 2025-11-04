<?php
/**
 * Procesador Profesional de Cola de Documentos
 * Garantiza procesamiento estrictamente secuencial
 */

require_once('Config/Config.php');
require_once('Libraries/Core/Mysql2.php');
require_once('Libraries/Core/DocumentQueue.php');
require_once('Controllers/Controllers.php');
require_once('Controllers/DocumentoDataCollector.php');
require_once('Libraries/Core/DocumentoValidators.php');
require_once('Models/LogJsonModel.php');
require_once('Libraries/Core/Mysql.php');
require_once('Libraries/Core/EmailNotifier.php');
require_once('Libraries/Core/Logger.php');

class ProfessionalQueueProcessor {
    private $queue;
    private $running = true;
    
    public function __construct() {
        $this->queue = new DocumentQueue();
    }
    
    /**
     * Ejecuta el procesador de cola
     */
    public function run() {
        echo "🚀 Iniciando procesador profesional de cola...\n";
        
        while ($this->running) {
            try {
                $this->processNextBatch();
                sleep(3); // Esperar 3 segundos entre iteraciones
            } catch (Exception $e) {
                echo "❌ Error en procesador: " . $e->getMessage() . "\n";
                sleep(10); // Esperar más tiempo si hay error
            }
        }
    }
    
    /**
     * Procesa el siguiente lote de documentos (uno por empresa)
     */
    private function processNextBatch() {
        $empresas = $this->getActiveEmpresas();
        
        foreach ($empresas as $empresa) {
            $this->processNextForEmpresa($empresa);
        }
    }
    
    /**
     * Procesa el siguiente documento para una empresa específica
     */
    private function processNextForEmpresa($empresa) {
        // Intentar obtener el siguiente documento (solo si no hay otros procesándose)
        $document = $this->queue->dequeueNext($empresa);
        
        if ($document) {
            echo "📄 Procesando documento ID: {$document['DQ_ID']} - Empresa: {$empresa} - Número: ";
            
            try {
                $facturaData = json_decode($document['DQ_JSON_DATA']);
                echo "{$facturaData->numero}\n";
                
                // Crear instancia del procesador
                $processor = new DocumentProcessor($empresa);
                $result = $processor->processDocument($facturaData);
                
                // Marcar como completado
                $this->queue->markCompleted($document['DQ_ID'], $result);
                echo "✅ Documento {$document['DQ_ID']} completado exitosamente\n";
                
            } catch (Exception $e) {
                // Marcar como fallido
                $this->queue->markFailed($document['DQ_ID'], $e->getMessage());
                echo "❌ Error procesando documento {$document['DQ_ID']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Obtiene empresas con documentos pendientes
     */
    private function getActiveEmpresas() {
        $mysql = new Mysql2();
        $sql = "SELECT DISTINCT DQ_EMPRESA FROM document_queue WHERE DQ_STATUS = 'pending'";
        $result = $mysql->select($sql);
        
        $empresas = [];
        if ($result) {
            // Si solo hay un resultado, convertirlo en array
            if (isset($result['DQ_EMPRESA'])) {
                $empresas[] = $result['DQ_EMPRESA'];
            } else {
                // Si hay múltiples resultados
                foreach ($result as $row) {
                    $empresas[] = $row['DQ_EMPRESA'];
                }
            }
        }
        
        return $empresas;
    }
    
    /**
     * Detiene el procesador
     */
    public function stop() {
        $this->running = false;
        echo "🛑 Deteniendo procesador profesional...\n";
    }
}

/**
 * Procesador de documentos individual
 */
class DocumentProcessor {
    private $empresa;
    private $model;
    
    public function __construct($empresa) {
        $this->empresa = $empresa;
        $this->model = new DocumentoModel(new Mysql($empresa));
    }
    
    public function processDocument($factura) {
        $dataCollector = new DocumentoDataCollector();
        
        // Validar estructura
        DocumentoValidators::validateFacturaCompleta($factura);
        
        // Recolectar datos maestros
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
                $dataCollector->addProducto($mov->producto);
                $dataCollector->addUnidadAgru($mov);
            }
        }
        
        if (!empty($factura->recibo->movimientos)) {
            foreach ($factura->recibo->movimientos as $mov) {
                $dataCollector->addBanco($mov->banco, $factura->sucursal);
            }
        }
        
        // Procesar maestros
        $this->model->procesarMaestros($dataCollector);
        
        // Procesar factura
        $this->model->procesarFactura($factura, $factura->moneda_base ?? 'BS');
        
        return [
            "status" => true,
            "documento" => $factura->numero,
            "message" => "Documento procesado correctamente desde cola profesional."
        ];
    }
}

// Ejecutar procesador si se llama directamente
if (php_sapi_name() === 'cli') {
    $processor = new ProfessionalQueueProcessor();
    
    // Manejar señales para detener gracefully
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($processor) {
            $processor->stop();
        });
        pcntl_signal(SIGINT, function() use ($processor) {
            $processor->stop();
        });
    }
    
    $processor->run();
} else {
    echo "Este script debe ejecutarse desde línea de comandos (CLI)";
}
?>

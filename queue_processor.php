<?php
/**
 * Procesador de cola de documentos
 * Este script debe ejecutarse en background para procesar documentos en cola
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

class QueueProcessor {
    private $queue;
    private $running = true;
    
    public function __construct() {
        $this->queue = new DocumentQueue();
    }
    
    /**
     * Ejecuta el procesador de cola
     */
    public function run() {
        echo "🚀 Iniciando procesador de cola de documentos...\n";
        
        while ($this->running) {
            try {
                $this->processNextDocument();
                sleep(2); // Esperar 2 segundos entre iteraciones
            } catch (Exception $e) {
                echo "❌ Error en procesador: " . $e->getMessage() . "\n";
                sleep(5); // Esperar más tiempo si hay error
            }
        }
    }
    
    /**
     * Procesa el siguiente documento en cola
     */
    private function processNextDocument() {
        // Obtener lista de empresas activas
        $empresas = $this->getActiveEmpresas();
        
        foreach ($empresas as $empresa) {
            $document = $this->queue->dequeue($empresa);
            
            if ($document) {
                echo "📄 Procesando documento ID: {$document['DQ_ID']} - Empresa: {$empresa}\n";
                
                try {
                    $facturaData = json_decode($document['DQ_JSON_DATA']);
                    
                    // Crear instancia del modelo
                    $documentoController = new DocumentoController($empresa);
                    $result = $documentoController->processDocument($facturaData);
                    
                    // Marcar como completado
                    $this->queue->markCompleted($document['DQ_ID'], $result);
                    echo "✅ Documento {$document['DQ_ID']} procesado exitosamente\n";
                    
                } catch (Exception $e) {
                    // Marcar como fallido
                    $this->queue->markFailed($document['DQ_ID'], $e->getMessage());
                    echo "❌ Error procesando documento {$document['DQ_ID']}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    /**
     * Obtiene lista de empresas activas
     */
    private function getActiveEmpresas() {
        $mysql = new Mysql2();
        $sql = "SELECT DISTINCT DQ_EMPRESA FROM document_queue WHERE DQ_STATUS = 'pending'";
        $result = $mysql->selectAll($sql);
        
        $empresas = [];
        if ($result) {
            foreach ($result as $row) {
                $empresas[] = $row['DQ_EMPRESA'];
            }
        }
        
        return $empresas;
    }
    
    /**
     * Detiene el procesador
     */
    public function stop() {
        $this->running = false;
        echo "🛑 Deteniendo procesador de cola...\n";
    }
}

/**
 * Controlador simplificado para procesar documentos
 */
class DocumentoController {
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
            "message" => "Documento procesado correctamente desde cola."
        ];
    }
}

// Ejecutar procesador si se llama directamente
if (php_sapi_name() === 'cli') {
    $processor = new QueueProcessor();
    
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

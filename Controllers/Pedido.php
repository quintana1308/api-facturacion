<?php 

require_once('Models/LogJsonModel.php');
require_once('Libraries/Core/Mysql2.php');

class Pedido extends Controllers{

    private $views;

    public function __construct()
    {
        parent::__construct('default'); // Pasar un valor por defecto para conectEnterprise
        $this->views = new Views();
    }

    /**
     * Muestra la información de un pedido específico
     * URL: minominio.com/pedido/ver/1555666
     * O con parámetro GET: minominio.com/pedido/ver?pedido=1555666
     */
    public function ver($numeroPedido = null){
        
        // Obtener el número de pedido desde parámetro GET si no viene en la URL
        if (empty($numeroPedido)) {
            $numeroPedido = $_GET['pedido'] ?? null;
        }
        
        // Validar que se proporcionó un número de pedido
        if (empty($numeroPedido)) {
            $data['error'] = "No se proporcionó un número de pedido válido.";
            $data['pedido'] = null;
        } else {
            try {
                // Cargar el modelo
                $logJsonModel = new LogJsonModel();
                
                // Buscar el pedido
                $pedidoData = $logJsonModel->getPedidoByNumero($numeroPedido);
                
                if ($pedidoData) {
                    $data['pedido'] = $pedidoData;
                    $data['error'] = null;
                    
                    // Formatear el JSON para mejor visualización
                    if (!empty($pedidoData['JSON_VALUE'])) {
                        $data['json_formatted'] = json_encode(json_decode($pedidoData['JSON_VALUE']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }
                    
                    if (!empty($pedidoData['JSON_RESPONSE'])) {
                        $data['response_formatted'] = json_encode(json_decode($pedidoData['JSON_RESPONSE']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $data['error'] = "No se encontró información para el pedido: " . htmlspecialchars($numeroPedido);
                    $data['pedido'] = null;
                }
            } catch (Exception $e) {
                $data['error'] = "Error al procesar la consulta: " . $e->getMessage();
                $data['pedido'] = null;
            }
        }
        
        // Datos para la vista
        $data['page_id'] = 2;
        $data['page_tag'] = "Consulta de Pedido";
        $data['page_title'] = "Información del Pedido";
        $data['page_name'] = "pedido";
        $data['numero_pedido'] = $numeroPedido;
        
        // Cargar la vista
        dep($data);
        exit;
        $this->views->getView($this, "pedido", $data);
    }
}
?>

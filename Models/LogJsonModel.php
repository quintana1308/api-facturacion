<?php 
// ARCHIVO: Models/LogJsonModel.php (REESTRUCTURADO)

class LogJsonModel extends Mysql2 {	
    
    public function __construct() {
        parent::__construct();
    }

    /**
     * Inserta el log inicial con la petición JSON y devuelve el ID del nuevo registro.
     * @param string $numeros Un string con los números de factura (ej: "243602-FAV, 243603-FAV").
     * @param string $jsonRequest El JSON de la petición original.
     * @return int El ID del registro recién insertado.
     */
    public function insertLogRequest(string $numeros, string $jsonRequest, string $empresa, string $type): int {

        $sql = "INSERT INTO api_logjson (JSON_DCL_NUMERO, JSON_VALUE, JSON_TYPE, JSON_ENTERPRISE)
                VALUES (?, ?, ?, ?)";

        // Asumimos un tipo genérico 'LOTE' para el registro principal.
        $arrayValues = array($numeros, $jsonRequest, $type, $empresa);
        
        // Usamos el método `insert` de la clase Mysql que devuelve el lastInsertId
        // Necesitas asegurarte de que tu método `insert` en Mysql.php devuelve el ID.
        // Si no lo hace, puedes cambiarlo por $this->insertMovRecibo que sí parece devolver el ID.
        $lastId = $this->insertMovRecibo($sql, $arrayValues);

        if (!$lastId) {
            // Si la inserción falla, es mejor lanzar un error.
            throw new Exception("No se pudo insertar el registro de log inicial.");
        }
        
        return $lastId;
    }

    /**
     * Actualiza un registro de log existente para añadir la respuesta JSON.
     * @param int $logId El ID del registro de log a actualizar.
     * @param string $jsonResponse El JSON de la respuesta final.
     * @return bool
     */
    public function updateLogResponse(int $logId, string $jsonResponse): bool {
        
        $sql = "UPDATE api_logjson SET JSON_RESPONSE = ? WHERE JSON_ID = ?";
        $arrayValues = array($jsonResponse, $logId);
        
        // Usamos el método `update` de la clase Mysql.
        $request = $this->update($sql, $arrayValues);
        return $request;
    }

    /**
     * Obtiene un registro de log por número de pedido.
     * @param string $numeroPedido El número de pedido a buscar.
     * @return array|false Los datos del registro o false si no se encuentra.
     */
    public function getPedidoByNumero(string $numeroPedido) {
        
        $sql = "SELECT JSON_ID, JSON_ENTERPRISE, JSON_TYPE, JSON_DCL_NUMERO, JSON_VALUE, JSON_RESPONSE, JSON_FECHAHORA 
                FROM api_logjson 
                WHERE JSON_DCL_NUMERO = '$numeroPedido'
                ORDER BY JSON_FECHAHORA DESC 
                LIMIT 1";
        
        $request = $this->select($sql);
        
        if (!empty($request)) {
            return $request;
        }
        
        return false;
    }
}
?>
<?php

class DocumentQueue {
    private $mysql;
    
    public function __construct() {
        $this->mysql = new Mysql2();
    }
    
    /**
     * Añade un documento a la cola
     */
    public function enqueue($documentData, $empresa, $prioridad = 1) {
        $jsonData = json_encode($documentData);
        $timestamp = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO document_queue (
                    DQ_JSON_DATA, 
                    DQ_EMPRESA, 
                    DQ_STATUS, 
                    DQ_PRIORIDAD, 
                    DQ_CREATED_AT,
                    DQ_ATTEMPTS
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $values = [
            $jsonData,
            $empresa,
            'pending',
            $prioridad,
            $timestamp,
            0
        ];
        
        return $this->mysql->insert($sql, $values);
    }
    
    /**
     * Verifica si hay documentos en procesamiento para una empresa
     */
    public function hasProcessingDocuments($empresa) {
        $sql = "SELECT COUNT(*) as count FROM document_queue 
                WHERE DQ_EMPRESA = '$empresa' AND DQ_STATUS = 'processing'";
        
        $result = $this->mysql->select($sql);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Obtiene el siguiente documento SOLO si no hay ninguno procesándose
     */
    public function dequeueNext($empresa) {
        // Usar bloqueo exclusivo para toda la operación
        $lockName = "queue_process_$empresa";
        
        $sqlLock = "SELECT GET_LOCK('$lockName', 30) as lock_acquired";
        $lockResult = $this->mysql->select($sqlLock);
        
        if (!$lockResult || $lockResult['lock_acquired'] != 1) {
            return null; // No se pudo obtener el bloqueo
        }
        
        try {
            // Verificar si hay documentos en procesamiento
            if ($this->hasProcessingDocuments($empresa)) {
                return null; // Hay documentos procesándose, no tomar ninguno más
            }
            
            // Buscar el siguiente documento pendiente
            $sql = "SELECT * FROM document_queue 
                    WHERE DQ_EMPRESA = '$empresa' 
                    AND DQ_STATUS = 'pending'
                    AND DQ_ATTEMPTS < 3
                    ORDER BY DQ_PRIORIDAD DESC, DQ_CREATED_AT ASC
                    LIMIT 1";
            
            $document = $this->mysql->select($sql);
            
            if ($document) {
                // Marcar como procesando INMEDIATAMENTE
                $updateSql = "UPDATE document_queue 
                             SET DQ_STATUS = ?, 
                                 DQ_STARTED_AT = NOW(),
                                 DQ_ATTEMPTS = DQ_ATTEMPTS + 1
                             WHERE DQ_ID = ?";
                $this->mysql->update($updateSql, ['processing', $document['DQ_ID']]);
            }
            
            return $document;
            
        } finally {
            // Liberar el bloqueo siempre
            $sqlRelease = "SELECT RELEASE_LOCK('$lockName')";
            $this->mysql->select($sqlRelease);
        }
    }
    
    /**
     * Marca documento como completado
     */
    public function markCompleted($documentId, $result = null) {
        $resultJson = $result ? json_encode($result) : '';
        
        $sql = "UPDATE document_queue 
                SET DQ_STATUS = ?,
                    DQ_COMPLETED_AT = NOW(),
                    DQ_RESULT = ?
                WHERE DQ_ID = ?";
        
        return $this->mysql->update($sql, ['completed', $resultJson, intval($documentId)]);
    }
    
    /**
     * Marca documento como fallido
     */
    public function markFailed($documentId, $error) {
        $sql = "UPDATE document_queue 
                SET DQ_STATUS = ?,
                    DQ_COMPLETED_AT = NOW(),
                    DQ_ERROR = ?
                WHERE DQ_ID = ?";
        
        return $this->mysql->update($sql, ['failed', $error, intval($documentId)]);
    }
    
    /**
     * Cuenta documentos pendientes
     */
    public function countPending($empresa) {
        $sql = "SELECT COUNT(*) as total FROM document_queue 
                WHERE DQ_EMPRESA = '$empresa' AND DQ_STATUS = 'pending'";
        
        $result = $this->mysql->select($sql);
        return $result['total'] ?? 0;
    }
    
    /**
     * Obtiene estadísticas de la cola
     */
    public function getStats($empresa) {
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        // Obtener cada estadística por separado ya que no tenemos selectAll
        foreach (['pending', 'processing', 'completed', 'failed'] as $status) {
            $sql = "SELECT COUNT(*) as count FROM document_queue 
                    WHERE DQ_EMPRESA = '$empresa' AND DQ_STATUS = '$status'";
            
            $result = $this->mysql->select($sql);
            $stats[$status] = $result['count'] ?? 0;
        }
        
        return $stats;
    }
}
?>

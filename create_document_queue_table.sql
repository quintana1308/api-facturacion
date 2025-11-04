-- Tabla para el sistema de cola de documentos
CREATE TABLE IF NOT EXISTS document_queue (
    DQ_ID INT AUTO_INCREMENT PRIMARY KEY,
    DQ_JSON_DATA LONGTEXT NOT NULL,
    DQ_EMPRESA VARCHAR(50) NOT NULL,
    DQ_STATUS ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    DQ_PRIORIDAD INT DEFAULT 1,
    DQ_CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    DQ_STARTED_AT TIMESTAMP NULL,
    DQ_COMPLETED_AT TIMESTAMP NULL,
    DQ_ATTEMPTS INT DEFAULT 0,
    DQ_RESULT LONGTEXT NULL,
    DQ_ERROR TEXT NULL,
    
    INDEX idx_empresa_status (DQ_EMPRESA, DQ_STATUS),
    INDEX idx_prioridad_created (DQ_PRIORIDAD DESC, DQ_CREATED_AT ASC),
    INDEX idx_status (DQ_STATUS),
    INDEX idx_created (DQ_CREATED_AT)
);

-- Limpiar documentos completados/fallidos después de 24 horas
CREATE EVENT IF NOT EXISTS cleanup_document_queue
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM document_queue 
  WHERE DQ_STATUS IN ('completed', 'failed') 
  AND DQ_COMPLETED_AT <= DATE_SUB(NOW(), INTERVAL 24 HOUR);

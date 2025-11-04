<?php
/**
 * Monitor de Cola de Documentos
 * Permite ver el estado de la cola en tiempo real
 */

require_once('Config/Config.php');
require_once('Libraries/Core/Mysql2.php');
require_once('Libraries/Core/DocumentQueue.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Monitor de Cola - API Facturación</title>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="5">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-card { 
            background: #f5f5f5; 
            padding: 15px; 
            border-radius: 8px; 
            min-width: 120px;
            text-align: center;
        }
        .stat-card.pending { background: #fff3cd; }
        .stat-card.processing { background: #cce5ff; }
        .stat-card.completed { background: #d4edda; }
        .stat-card.failed { background: #f8d7da; }
        .stat-number { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .status-pending { color: #856404; }
        .status-processing { color: #004085; }
        .status-completed { color: #155724; }
        .status-failed { color: #721c24; }
    </style>
</head>
<body>
    <h1>📊 Monitor de Cola - API Facturación</h1>
    <p><em>Actualización automática cada 5 segundos</em></p>

    <?php
    try {
        $queue = new DocumentQueue();
        $mysql = new Mysql2();
        
        // Obtener empresas activas
        $sqlEmpresas = "SELECT DISTINCT DQ_EMPRESA FROM document_queue ORDER BY DQ_EMPRESA";
        $empresasResult = $mysql->select($sqlEmpresas);
        
        $empresas = [];
        if ($empresasResult) {
            if (isset($empresasResult['DQ_EMPRESA'])) {
                $empresas[] = $empresasResult['DQ_EMPRESA'];
            } else {
                foreach ($empresasResult as $row) {
                    $empresas[] = $row['DQ_EMPRESA'];
                }
            }
        }
        
        foreach ($empresas as $empresa) {
            echo "<h2>🏢 Empresa: $empresa</h2>";
            
            // Obtener estadísticas
            $stats = $queue->getStats($empresa);
            
            echo '<div class="stats">';
            echo '<div class="stat-card pending">';
            echo '<div class="stat-number">' . $stats['pending'] . '</div>';
            echo '<div class="stat-label">Pendientes</div>';
            echo '</div>';
            
            echo '<div class="stat-card processing">';
            echo '<div class="stat-number">' . $stats['processing'] . '</div>';
            echo '<div class="stat-label">Procesando</div>';
            echo '</div>';
            
            echo '<div class="stat-card completed">';
            echo '<div class="stat-number">' . $stats['completed'] . '</div>';
            echo '<div class="stat-label">Completados</div>';
            echo '</div>';
            
            echo '<div class="stat-card failed">';
            echo '<div class="stat-number">' . $stats['failed'] . '</div>';
            echo '<div class="stat-label">Fallidos</div>';
            echo '</div>';
            echo '</div>';
            
            // Mostrar documentos recientes
            $sqlRecientes = "SELECT DQ_ID, DQ_STATUS, DQ_CREATED_AT, DQ_STARTED_AT, DQ_COMPLETED_AT, DQ_ATTEMPTS, DQ_ERROR,
                                    JSON_EXTRACT(DQ_JSON_DATA, '$.numero') as numero_doc
                             FROM document_queue 
                             WHERE DQ_EMPRESA = '$empresa'
                             ORDER BY DQ_CREATED_AT DESC 
                             LIMIT 20";
            
            $recientes = $mysql->select($sqlRecientes);
            
            if ($recientes) {
                echo '<h3>📋 Documentos Recientes</h3>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Documento</th><th>Estado</th><th>Creado</th><th>Iniciado</th><th>Completado</th><th>Intentos</th><th>Error</th></tr>';
                
                // Si es un solo resultado, convertir a array
                if (isset($recientes['DQ_ID'])) {
                    $recientes = [$recientes];
                }
                
                foreach ($recientes as $doc) {
                    $statusClass = 'status-' . $doc['DQ_STATUS'];
                    $numeroDoc = trim($doc['numero_doc'], '"'); // Quitar comillas del JSON
                    
                    echo '<tr>';
                    echo '<td>' . $doc['DQ_ID'] . '</td>';
                    echo '<td>' . $numeroDoc . '</td>';
                    echo '<td class="' . $statusClass . '">' . strtoupper($doc['DQ_STATUS']) . '</td>';
                    echo '<td>' . $doc['DQ_CREATED_AT'] . '</td>';
                    echo '<td>' . ($doc['DQ_STARTED_AT'] ?? '-') . '</td>';
                    echo '<td>' . ($doc['DQ_COMPLETED_AT'] ?? '-') . '</td>';
                    echo '<td>' . $doc['DQ_ATTEMPTS'] . '</td>';
                    echo '<td>' . ($doc['DQ_ERROR'] ? substr($doc['DQ_ERROR'], 0, 50) . '...' : '-') . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            }
            
            echo '<hr style="margin: 30px 0;">';
        }
        
        if (empty($empresas)) {
            echo '<p>No hay documentos en cola.</p>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">Error: ' . $e->getMessage() . '</p>';
    }
    ?>

    <h3>🔧 Comandos Útiles</h3>
    <ul>
        <li><strong>Limpiar completados:</strong> <code>DELETE FROM document_queue WHERE DQ_STATUS = 'completed'</code></li>
        <li><strong>Reintentar fallidos:</strong> <code>UPDATE document_queue SET DQ_STATUS = 'pending', DQ_ATTEMPTS = 0 WHERE DQ_STATUS = 'failed'</code></li>
        <li><strong>Ver solo pendientes:</strong> <code>SELECT * FROM document_queue WHERE DQ_STATUS = 'pending' ORDER BY DQ_CREATED_AT</code></li>
    </ul>
</body>
</html>

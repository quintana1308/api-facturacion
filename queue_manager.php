<?php
/**
 * GESTOR DE COLA DE DOCUMENTOS
 * 
 * Script para reiniciar, limpiar y gestionar procesos en cola
 */

require_once('Libraries/Core/Mysql2.php');
require_once('Libraries/Core/DocumentQueue.php');

class QueueManager {
    
    private $mysql2;
    private $queue;
    
    public function __construct() {
        $this->mysql2 = new Mysql2();
        $this->queue = new DocumentQueue();
    }
    
    /**
     * Reiniciar todos los procesos en cola
     */
    public function reiniciarProcesos() {
        echo "<h2>🔄 Reiniciando Procesos en Cola</h2>";
        
        try {
            // 1. Cambiar documentos 'processing' a 'pending'
            $sql = "UPDATE document_queue 
                    SET DQ_STATUS = 'pending', 
                        DQ_STARTED_AT = NULL,
                        DQ_ATTEMPTS = GREATEST(DQ_ATTEMPTS - 1, 0)
                    WHERE DQ_STATUS = 'processing'";
            
            $result = $this->mysql2->update_massive($sql);
            
            // 2. Obtener estadísticas
            $stats = $this->getEstadisticasCompletas();
            
            echo "<div class='success'>";
            echo "<p>✅ <strong>Procesos reiniciados exitosamente</strong></p>";
            echo "<p>📊 Documentos cambiados de 'processing' a 'pending'</p>";
            echo "<p>📈 <strong>Estado actual:</strong></p>";
            echo "<ul>";
            echo "<li>Pendientes: <strong>{$stats['pending']}</strong></li>";
            echo "<li>Procesando: <strong>{$stats['processing']}</strong></li>";
            echo "<li>Completados: <strong>{$stats['completed']}</strong></li>";
            echo "<li>Fallidos: <strong>{$stats['failed']}</strong></li>";
            echo "</ul>";
            echo "</div>";
            
            return true;
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error al reiniciar procesos: " . $e->getMessage() . "</div>";
            return false;
        }
    }
    
    /**
     * Limpiar documentos fallidos o muy antiguos
     */
    public function limpiarCola($diasAntiguos = 7) {
        echo "<h2>🧹 Limpiando Cola de Documentos</h2>";
        
        try {
            $fechaLimite = date('Y-m-d H:i:s', strtotime("-$diasAntiguos days"));
            
            // 1. Contar documentos a eliminar
            $sqlCount = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN DQ_STATUS = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN DQ_STATUS = 'failed' THEN 1 ELSE 0 END) as failed
                         FROM document_queue 
                         WHERE DQ_CREATED_AT < '$fechaLimite' 
                         AND DQ_STATUS IN ('completed', 'failed')";
            
            $count = $this->mysql2->select($sqlCount);
            
            if ($count['total'] > 0) {
                // 2. Eliminar documentos antiguos
                $sqlDelete = "DELETE FROM document_queue 
                             WHERE DQ_CREATED_AT < '$fechaLimite' 
                             AND DQ_STATUS IN ('completed', 'failed')";
                
                $this->mysql2->delete($sqlDelete);
                
                echo "<div class='success'>";
                echo "<p>✅ <strong>Cola limpiada exitosamente</strong></p>";
                echo "<p>🗑️ Eliminados <strong>{$count['total']}</strong> documentos antiguos:</p>";
                echo "<ul>";
                echo "<li>Completados: <strong>{$count['completed']}</strong></li>";
                echo "<li>Fallidos: <strong>{$count['failed']}</strong></li>";
                echo "</ul>";
                echo "<p>📅 Documentos anteriores a: <strong>$fechaLimite</strong></p>";
                echo "</div>";
            } else {
                echo "<div class='info'>ℹ️ No hay documentos antiguos para limpiar</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error al limpiar cola: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Reintentar documentos fallidos
     */
    public function reintentarFallidos($maxIntentos = 3) {
        echo "<h2>🔁 Reintentando Documentos Fallidos</h2>";
        
        try {
            // Contar documentos fallidos que pueden reintentarse
            $sqlCount = "SELECT COUNT(*) as total FROM document_queue 
                        WHERE DQ_STATUS = 'failed' AND DQ_ATTEMPTS < $maxIntentos";
            
            $count = $this->mysql2->select($sqlCount);
            
            if ($count['total'] > 0) {
                // Cambiar status a pending para reintentar
                $sql = "UPDATE document_queue 
                        SET DQ_STATUS = 'pending',
                            DQ_STARTED_AT = NULL,
                            DQ_COMPLETED_AT = NULL,
                            DQ_ERROR = NULL
                        WHERE DQ_STATUS = 'failed' AND DQ_ATTEMPTS < $maxIntentos";
                
                $this->mysql2->update_massive($sql);
                
                echo "<div class='success'>";
                echo "<p>✅ <strong>Documentos marcados para reintento</strong></p>";
                echo "<p>🔁 <strong>{$count['total']}</strong> documentos fallidos serán reintentados</p>";
                echo "</div>";
            } else {
                echo "<div class='info'>ℹ️ No hay documentos fallidos disponibles para reintentar</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error al reintentar fallidos: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Ver documentos problemáticos
     */
    public function verProblematicos() {
        echo "<h2>⚠️ Documentos Problemáticos</h2>";
        
        try {
            // Documentos con muchos intentos
            $sqlMuchosIntentos = "SELECT DQ_ID, DQ_EMPRESA, DQ_ATTEMPTS, DQ_ERROR, DQ_CREATED_AT 
                                 FROM document_queue 
                                 WHERE DQ_ATTEMPTS >= 3 
                                 ORDER BY DQ_ATTEMPTS DESC, DQ_CREATED_AT DESC 
                                 LIMIT 10";
            
            $problematicos = $this->mysql2->select_all($sqlMuchosIntentos);
            
            if (!empty($problematicos)) {
                echo "<h3>🔴 Documentos con Múltiples Intentos Fallidos:</h3>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>ID</th><th>Empresa</th><th>Intentos</th><th>Error</th><th>Creado</th><th>Acción</th></tr>";
                
                foreach ($problematicos as $doc) {
                    echo "<tr>";
                    echo "<td>{$doc['DQ_ID']}</td>";
                    echo "<td>{$doc['DQ_EMPRESA']}</td>";
                    echo "<td style='color: red;'><strong>{$doc['DQ_ATTEMPTS']}</strong></td>";
                    echo "<td>" . substr($doc['DQ_ERROR'] ?? 'Sin error', 0, 50) . "...</td>";
                    echo "<td>{$doc['DQ_CREATED_AT']}</td>";
                    echo "<td><button onclick='eliminarDocumento({$doc['DQ_ID']})'>Eliminar</button></td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='success'>✅ No hay documentos problemáticos</div>";
            }
            
            // Documentos procesando por mucho tiempo
            $sqlProcesandoMucho = "SELECT DQ_ID, DQ_EMPRESA, DQ_STARTED_AT, 
                                         TIMESTAMPDIFF(MINUTE, DQ_STARTED_AT, NOW()) as minutos_procesando
                                  FROM document_queue 
                                  WHERE DQ_STATUS = 'processing' 
                                  AND TIMESTAMPDIFF(MINUTE, DQ_STARTED_AT, NOW()) > 10
                                  ORDER BY DQ_STARTED_AT ASC";
            
            $procesandoMucho = $this->mysql2->select_all($sqlProcesandoMucho);
            
            if (!empty($procesandoMucho)) {
                echo "<h3>⏰ Documentos Procesando por Mucho Tiempo:</h3>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>ID</th><th>Empresa</th><th>Iniciado</th><th>Minutos</th><th>Acción</th></tr>";
                
                foreach ($procesandoMucho as $doc) {
                    $color = $doc['minutos_procesando'] > 60 ? 'red' : 'orange';
                    echo "<tr>";
                    echo "<td>{$doc['DQ_ID']}</td>";
                    echo "<td>{$doc['DQ_EMPRESA']}</td>";
                    echo "<td>{$doc['DQ_STARTED_AT']}</td>";
                    echo "<td style='color: $color;'><strong>{$doc['minutos_procesando']} min</strong></td>";
                    echo "<td><button onclick='reiniciarDocumento({$doc['DQ_ID']})'>Reiniciar</button></td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error al obtener problemáticos: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Obtener estadísticas completas
     */
    public function getEstadisticasCompletas() {
        $sql = "SELECT 
                    DQ_STATUS,
                    COUNT(*) as cantidad,
                    MIN(DQ_CREATED_AT) as mas_antiguo,
                    MAX(DQ_CREATED_AT) as mas_reciente
                FROM document_queue 
                GROUP BY DQ_STATUS";
        
        $resultados = $this->mysql2->select_all($sql);
        
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        foreach ($resultados as $row) {
            $stats[$row['DQ_STATUS']] = $row['cantidad'];
        }
        
        return $stats;
    }
    
    /**
     * Mostrar estadísticas detalladas
     */
    public function mostrarEstadisticas() {
        echo "<h2>📊 Estadísticas de Cola</h2>";
        
        try {
            $sql = "SELECT 
                        DQ_STATUS,
                        DQ_EMPRESA,
                        COUNT(*) as cantidad,
                        MIN(DQ_CREATED_AT) as mas_antiguo,
                        MAX(DQ_CREATED_AT) as mas_reciente,
                        AVG(DQ_ATTEMPTS) as promedio_intentos
                    FROM document_queue 
                    GROUP BY DQ_STATUS, DQ_EMPRESA
                    ORDER BY DQ_EMPRESA, DQ_STATUS";
            
            $stats = $this->mysql2->select_all($sql);
            
            if (!empty($stats)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Empresa</th><th>Estado</th><th>Cantidad</th><th>Más Antiguo</th><th>Más Reciente</th><th>Prom. Intentos</th></tr>";
                
                foreach ($stats as $stat) {
                    $statusClass = "status-" . $stat['DQ_STATUS'];
                    echo "<tr>";
                    echo "<td><strong>{$stat['DQ_EMPRESA']}</strong></td>";
                    echo "<td class='$statusClass'>{$stat['DQ_STATUS']}</td>";
                    echo "<td><strong>{$stat['cantidad']}</strong></td>";
                    echo "<td>{$stat['mas_antiguo']}</td>";
                    echo "<td>{$stat['mas_reciente']}</td>";
                    echo "<td>" . number_format($stat['promedio_intentos'], 1) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='info'>ℹ️ No hay documentos en cola</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error al obtener estadísticas: " . $e->getMessage() . "</div>";
        }
    }
}

// Procesar acciones AJAX
if (isset($_POST['action'])) {
    $manager = new QueueManager();
    
    switch ($_POST['action']) {
        case 'reiniciar':
            $manager->reiniciarProcesos();
            break;
        case 'limpiar':
            $dias = $_POST['dias'] ?? 7;
            $manager->limpiarCola($dias);
            break;
        case 'reintentar':
            $manager->reintentarFallidos();
            break;
    }
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestor de Cola - API Facturación</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .actions { margin: 20px 0; }
        .btn { 
            background: #007cba; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
            margin: 5px;
            border-radius: 4px;
        }
        .btn:hover { background: #005a8b; }
        .btn.danger { background: #dc3545; }
        .btn.danger:hover { background: #c82333; }
        .btn.warning { background: #ffc107; color: #212529; }
        .btn.warning:hover { background: #e0a800; }
        
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #cce5ff; border: 1px solid #b3d7ff; padding: 15px; margin: 10px 0; border-radius: 4px; }
        
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        
        .status-pending { color: #856404; }
        .status-processing { color: #004085; }
        .status-completed { color: #155724; }
        .status-failed { color: #721c24; }
        
        #resultado { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>🔧 Gestor de Cola de Documentos - API Facturación</h1>
    
    <div class="actions">
        <h3>🛠️ Acciones Disponibles:</h3>
        <button class="btn" onclick="ejecutarAccion('reiniciar')">🔄 Reiniciar Procesos</button>
        <button class="btn warning" onclick="ejecutarAccion('reintentar')">🔁 Reintentar Fallidos</button>
        <button class="btn danger" onclick="limpiarCola()">🧹 Limpiar Cola</button>
        <button class="btn" onclick="location.reload()">📊 Actualizar</button>
    </div>
    
    <div id="resultado"></div>
    
    <?php
    try {
        $manager = new QueueManager();
        $manager->mostrarEstadisticas();
        echo "<hr>";
        $manager->verProblematicos();
        
    } catch (Exception $e) {
        echo "<div class='error'>Error general: " . $e->getMessage() . "</div>";
    }
    ?>
    
    <script>
    function ejecutarAccion(accion) {
        const resultado = document.getElementById('resultado');
        resultado.innerHTML = '<p>⏳ Ejecutando acción...</p>';
        
        const formData = new FormData();
        formData.append('action', accion);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            resultado.innerHTML = data;
            setTimeout(() => location.reload(), 3000);
        })
        .catch(error => {
            resultado.innerHTML = '<div class="error">Error: ' + error + '</div>';
        });
    }
    
    function limpiarCola() {
        const dias = prompt('¿Cuántos días de antigüedad para limpiar?', '7');
        if (dias) {
            const resultado = document.getElementById('resultado');
            resultado.innerHTML = '<p>⏳ Limpiando cola...</p>';
            
            const formData = new FormData();
            formData.append('action', 'limpiar');
            formData.append('dias', dias);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                resultado.innerHTML = data;
                setTimeout(() => location.reload(), 3000);
            })
            .catch(error => {
                resultado.innerHTML = '<div class="error">Error: ' + error + '</div>';
            });
        }
    }
    
    function eliminarDocumento(id) {
        if (confirm('¿Eliminar documento ' + id + '?')) {
            // Implementar si es necesario
            alert('Funcionalidad de eliminar no implementada por seguridad');
        }
    }
    
    function reiniciarDocumento(id) {
        if (confirm('¿Reiniciar documento ' + id + '?')) {
            // Implementar si es necesario
            alert('Funcionalidad de reiniciar individual no implementada');
        }
    }
    </script>
</body>
</html>

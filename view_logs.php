<?php
// Visor de logs de notificaciones por correo

require_once('Libraries/Core/Logger.php');

$logFile = Logger::getLogFile();
$action = $_GET['action'] ?? '';

// Procesar acciones
if ($action === 'clear') {
    Logger::clear();
    $message = "✅ Log limpiado correctamente";
} elseif ($action === 'test') {
    Logger::info("Test de log desde view_logs.php");
    Logger::debug("Esto es un mensaje de debug");
    Logger::error("Esto es un mensaje de error de prueba");
    Logger::success("Esto es un mensaje de éxito");
    $message = "✅ Mensajes de prueba añadidos al log";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Visor de Logs - Notificaciones Email</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #007bff; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .actions { margin-bottom: 20px; }
        .btn { padding: 8px 15px; margin-right: 10px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .log-container { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; max-height: 600px; overflow-y: auto; }
        .log-content { font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; line-height: 1.4; }
        .log-line { margin-bottom: 2px; }
        .log-info { color: #007bff; }
        .log-debug { color: #6c757d; }
        .log-error { color: #dc3545; font-weight: bold; }
        .log-success { color: #28a745; font-weight: bold; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .file-info { background: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Visor de Logs - Notificaciones Email</h1>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="file-info">
            <strong>Archivo de Log:</strong> <?php echo $logFile; ?><br>
            <strong>Tamaño:</strong> <?php echo file_exists($logFile) ? number_format(filesize($logFile)) . ' bytes' : 'No existe'; ?><br>
            <strong>Última modificación:</strong> <?php echo file_exists($logFile) ? date('Y-m-d H:i:s', filemtime($logFile)) : 'N/A'; ?>
        </div>
        
        <div class="actions">
            <a href="?action=refresh" class="btn btn-primary">🔄 Actualizar</a>
            <a href="?action=test" class="btn btn-success">🧪 Añadir Test</a>
            <a href="?action=clear" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres limpiar el log?')">🗑️ Limpiar Log</a>
            <a href="simulate_error.php" class="btn btn-primary" target="_blank">🚨 Simular Error</a>
        </div>
        
        <div class="log-container">
            <div class="log-content">
<?php
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (empty($content)) {
        echo "📝 El archivo de log está vacío.\n";
        echo "💡 Ejecuta una prueba o simula un error para ver los logs aquí.";
    } else {
        // Colorear las líneas según el nivel
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $class = 'log-line';
            if (strpos($line, '[INFO]') !== false) $class .= ' log-info';
            elseif (strpos($line, '[DEBUG]') !== false) $class .= ' log-debug';
            elseif (strpos($line, '[ERROR]') !== false) $class .= ' log-error';
            elseif (strpos($line, '[SUCCESS]') !== false) $class .= ' log-success';
            
            echo "<div class='$class'>" . htmlspecialchars($line) . "</div>";
        }
    }
} else {
    echo "❌ El archivo de log no existe aún.\n";
    echo "💡 Se creará automáticamente cuando ocurra el primer evento.";
}
?>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 4px;">
            <h4>📖 Instrucciones:</h4>
            <ul>
                <li><strong>Actualizar:</strong> Recarga la página para ver nuevos logs</li>
                <li><strong>Añadir Test:</strong> Escribe mensajes de prueba en el log</li>
                <li><strong>Limpiar Log:</strong> Borra todo el contenido del archivo</li>
                <li><strong>Simular Error:</strong> Ejecuta una prueba completa del sistema</li>
            </ul>
            <p><strong>Colores:</strong> 
                <span class="log-info">🔵 INFO</span> | 
                <span class="log-debug">⚪ DEBUG</span> | 
                <span class="log-error">🔴 ERROR</span> | 
                <span class="log-success">🟢 SUCCESS</span>
            </p>
        </div>
    </div>
</body>
</html>

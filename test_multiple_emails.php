<?php
// Test específico para envío múltiple de correos

require_once('Config/Config.php');
require_once('Libraries/Core/Conexion2.php');
require_once('Libraries/Core/Mysql2.php');
require_once('Libraries/Core/EmailNotifier.php');
require_once('Libraries/Core/Logger.php');

echo "<h2>🧪 Test de Envío Múltiple de Correos</h2>";

// Limpiar log para esta prueba
Logger::clear();
Logger::info("=== INICIO TEST ENVÍO MÚLTIPLE ===");

// Datos de prueba
$empresaTest = 'TEST';
$jsonRequest = json_encode([
    "empresa" => $empresaTest,
    "numero" => "MULTI-TEST-001",
    "fecha" => date('Y-m-d H:i:s'),
    "tipo_documento" => "FAC",
    "cliente" => [
        "codigo" => "CLI001",
        "nombre" => "Cliente Test Múltiple"
    ]
], JSON_PRETTY_PRINT);

$jsonResponse = json_encode([
    "status" => false,
    "error" => "Error de prueba para envío múltiple",
    "documento" => "MULTI-TEST-001"
], JSON_PRETTY_PRINT);

echo "<h3>📋 Información de la Prueba:</h3>";
echo "<strong>Empresa:</strong> $empresaTest<br>";

// Verificar configuración de la empresa
try {
    $mysql2 = new Mysql2();
    $sql = "SELECT ETR_IDENTIF, ETR_NOTIFICATION_EMAIL, ETR_EMAIL FROM api_enterprise WHERE ETR_IDENTIF = '$empresaTest'";
    $empresa = $mysql2->select($sql);
    
    if ($empresa) {
        echo "<strong>Notificaciones habilitadas:</strong> " . ($empresa['ETR_NOTIFICATION_EMAIL'] ? 'SÍ' : 'NO') . "<br>";
        echo "<strong>Correos configurados:</strong> " . $empresa['ETR_EMAIL'] . "<br>";
        
        $emails = explode(',', $empresa['ETR_EMAIL']);
        $emails = array_map('trim', $emails);
        echo "<strong>Correos parseados:</strong> " . implode(', ', $emails) . "<br>";
        echo "<strong>Total de destinatarios:</strong> " . count($emails) . "<br>";
    } else {
        echo "<span style='color:red'>❌ Empresa no encontrada</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red'>❌ Error consultando BD: " . $e->getMessage() . "</span><br>";
}

echo "<h3>🚀 Ejecutando Envío...</h3>";

try {
    $emailNotifier = new EmailNotifier();
    
    $result = $emailNotifier->sendErrorNotification(
        $empresaTest,
        $jsonRequest,
        $jsonResponse,
        'testing'
    );
    
    if ($result) {
        echo "<div style='color:green; font-weight:bold'>✅ ENVÍO COMPLETADO EXITOSAMENTE</div>";
    } else {
        echo "<div style='color:red; font-weight:bold'>❌ ENVÍO FALLÓ</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color:red'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "<h3>📄 Logs Detallados:</h3>";
echo "<div style='background:#f8f9fa; padding:15px; border:1px solid #dee2e6; border-radius:5px; font-family:monospace; font-size:12px; max-height:400px; overflow-y:auto;'>";

$logFile = Logger::getLogFile();
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    // Mostrar solo las últimas líneas (de esta prueba)
    $showLines = array_slice($lines, -20);
    
    foreach ($showLines as $line) {
        if (empty(trim($line))) continue;
        
        $color = '#000';
        if (strpos($line, '[ERROR]') !== false) $color = '#dc3545';
        elseif (strpos($line, '[SUCCESS]') !== false) $color = '#28a745';
        elseif (strpos($line, '[INFO]') !== false) $color = '#007bff';
        elseif (strpos($line, '[DEBUG]') !== false) $color = '#6c757d';
        
        echo "<div style='color:$color; margin-bottom:2px;'>" . htmlspecialchars($line) . "</div>";
    }
} else {
    echo "No hay logs disponibles";
}

echo "</div>";

echo "<h3>🔍 Verificaciones Adicionales:</h3>";
echo "<ul>";
echo "<li><strong>API Key Resend:</strong> " . (defined('RESEND_API_KEY') ? 'Configurada (' . substr(RESEND_API_KEY, 0, 10) . '...)' : 'NO CONFIGURADA') . "</li>";
echo "<li><strong>Dominio From:</strong> " . (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'NO CONFIGURADO') . "</li>";
echo "<li><strong>Archivo de logs:</strong> <code>" . Logger::getLogFile() . "</code></li>";
echo "</ul>";

echo "<h3>📧 Próximos Pasos:</h3>";
echo "<ol>";
echo "<li>Revisa tu bandeja de entrada en ambos correos</li>";
echo "<li>Verifica la carpeta de spam/correo no deseado</li>";
echo "<li>Revisa los logs detallados arriba para ver si hay errores específicos</li>";
echo "<li>Si ves IDs de Resend en los logs, significa que se enviaron correctamente</li>";
echo "</ol>";

echo "<div style='margin-top:20px;'>";
echo "<a href='view_logs.php' style='padding:8px 15px; background:#007bff; color:white; text-decoration:none; border-radius:4px;'>📋 Ver Logs Completos</a> ";
echo "<a href='test_multiple_emails.php' style='padding:8px 15px; background:#28a745; color:white; text-decoration:none; border-radius:4px;'>🔄 Ejecutar de Nuevo</a>";
echo "</div>";

?>

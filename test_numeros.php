<?php
// Test para verificar la generación de números

require_once('Config/Config.php');
require_once('Libraries/Core/Conexion2.php');
require_once('Libraries/Core/Mysql2.php');
require_once('Models/DocumentoModel.php');

echo "<h2>🧪 Test de Generación de Números</h2>";

try {
    $mysql2 = new Mysql2();
    $documentoModel = new DocumentoModel($mysql2);
    
    echo "<h3>📊 Números actuales en BD:</h3>";
    
    // Verificar números FAV actuales
    $sqlFAV = "SELECT MAX(CAST(DCL_NUMERO AS UNSIGNED)) as maxFAV FROM ADN_DOCCLI WHERE DCL_TDT_CODIGO = 'FAV'";
    $resultFAV = $mysql2->select($sqlFAV);
    echo "<strong>FAV máximo actual:</strong> " . ($resultFAV['maxFAV'] ?? 'NULL') . "<br>";
    
    // Verificar números PED actuales
    $sqlPED = "SELECT MAX(CAST(DCL_NUMERO AS UNSIGNED)) as maxPED FROM ADN_DOCCLI WHERE DCL_TDT_CODIGO = 'PED'";
    $resultPED = $mysql2->select($sqlPED);
    echo "<strong>PED máximo actual:</strong> " . ($resultPED['maxPED'] ?? 'NULL') . "<br>";
    
    // Verificar números de recibo actuales
    $sqlREC = "SELECT MAX(CAST(RIGHT(REC_NUMERO, 17) AS UNSIGNED)) as maxREC FROM ADN_RECIBOS";
    $resultREC = $mysql2->select($sqlREC);
    echo "<strong>Recibo máximo actual:</strong> " . ($resultREC['maxREC'] ?? 'NULL') . "<br>";
    
    echo "<h3>🔢 Números que generará idsDocumentos():</h3>";
    
    // Usar reflexión para acceder al método protegido
    $reflection = new ReflectionClass($documentoModel);
    $idsMethod = $reflection->getMethod('idsDocumentos');
    $idsMethod->setAccessible(true);
    
    $numeros = $idsMethod->invoke($documentoModel);
    
    echo "<strong>nDoc (FAV):</strong> " . $numeros['nDoc'] . "<br>";
    echo "<strong>nPed (PED):</strong> " . $numeros['nPed'] . "<br>";
    echo "<strong>nRecibo:</strong> " . $numeros['nRecibo'] . "<br>";
    
    echo "<h3>🔍 Verificar si ya existen:</h3>";
    
    // Verificar si el número FAV ya existe
    $sqlCheckFAV = "SELECT COUNT(*) as count FROM ADN_DOCCLI WHERE DCL_NUMERO = '{$numeros['nDoc']}' AND DCL_TDT_CODIGO = 'FAV'";
    $checkFAV = $mysql2->select($sqlCheckFAV);
    echo "<strong>FAV {$numeros['nDoc']} existe:</strong> " . ($checkFAV['count'] > 0 ? 'SÍ ❌' : 'NO ✅') . "<br>";
    
    // Verificar si el número PED ya existe
    $sqlCheckPED = "SELECT COUNT(*) as count FROM ADN_DOCCLI WHERE DCL_NUMERO = '{$numeros['nPed']}' AND DCL_TDT_CODIGO = 'PED'";
    $checkPED = $mysql2->select($sqlCheckPED);
    echo "<strong>PED {$numeros['nPed']} existe:</strong> " . ($checkPED['count'] > 0 ? 'SÍ ❌' : 'NO ✅') . "<br>";
    
    // Verificar si el número de recibo ya existe
    $sqlCheckREC = "SELECT COUNT(*) as count FROM ADN_RECIBOS WHERE REC_NUMERO = '{$numeros['nRecibo']}'";
    $checkREC = $mysql2->select($sqlCheckREC);
    echo "<strong>Recibo {$numeros['nRecibo']} existe:</strong> " . ($checkREC['count'] > 0 ? 'SÍ ❌' : 'NO ✅') . "<br>";
    
    echo "<h3>📋 Análisis:</h3>";
    
    if ($checkFAV['count'] == 0 && $checkPED['count'] == 0 && $checkREC['count'] == 0) {
        echo "<div style='background:#d4edda; padding:15px; border-radius:5px; color:#155724;'>";
        echo "<strong>✅ CORRECTO:</strong> Todos los números generados son únicos y pueden usarse.";
        echo "</div>";
    } else {
        echo "<div style='background:#f8d7da; padding:15px; border-radius:5px; color:#721c24;'>";
        echo "<strong>❌ PROBLEMA:</strong> Algunos números ya existen en la base de datos.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<span style='color:red'>❌ Error durante el test: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

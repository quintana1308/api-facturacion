<?php
/**
 * SCRIPT RÁPIDO PARA REINICIAR COLA
 * Ejecuta desde línea de comandos o navegador
 */

require_once('Libraries/Core/Mysql2.php');

echo "🔄 Reiniciando procesos en cola...\n";

try {
    $mysql2 = new Mysql2();
    
    // Cambiar todos los 'processing' a 'pending'
    $sql = "UPDATE document_queue 
            SET DQ_STATUS = 'pending', 
                DQ_STARTED_AT = NULL,
                DQ_ATTEMPTS = GREATEST(DQ_ATTEMPTS - 1, 0)
            WHERE DQ_STATUS = 'processing'";
    
    $mysql2->update_massive($sql);
    
    // Obtener estadísticas
    $sqlStats = "SELECT DQ_STATUS, COUNT(*) as cantidad FROM document_queue GROUP BY DQ_STATUS";
    $stats = $mysql2->select_all($sqlStats);
    
    echo "✅ Procesos reiniciados exitosamente\n";
    echo "📊 Estado actual:\n";
    
    foreach ($stats as $stat) {
        echo "   - {$stat['DQ_STATUS']}: {$stat['cantidad']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Usa queue_manager.php para más opciones\n";
?>

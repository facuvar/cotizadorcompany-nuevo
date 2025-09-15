<?php
// Force deploy timestamp: 2025-01-07 11:30
header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔍 Debug Info Railway</h2>";
echo "<strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "<strong>__DIR__:</strong> " . __DIR__ . "<br>";
echo "<strong>getcwd():</strong> " . getcwd() . "<br>";
echo "<strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "<br>";

echo "<h3>📁 Archivos en raíz:</h3>";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    echo ($file === 'config.php' ? '✅' : '📄') . " $file<br>";
}

echo "<h3>📁 Archivos en admin:</h3>";
if (is_dir(__DIR__ . '/admin')) {
    $adminFiles = scandir(__DIR__ . '/admin');
    foreach ($adminFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        echo ($file === 'api_gestionar_datos.php' ? '✅' : '📄') . " admin/$file<br>";
    }
}

echo "<h3>🔧 Verificaciones:</h3>";
echo "config.php: " . (file_exists(__DIR__ . '/config.php') ? '✅' : '❌') . "<br>";
echo "includes/db.php: " . (file_exists(__DIR__ . '/includes/db.php') ? '✅' : '❌') . "<br>";
echo "admin/api_gestionar_datos.php: " . (file_exists(__DIR__ . '/admin/api_gestionar_datos.php') ? '✅' : '❌') . "<br>";

if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
        echo "<h3>✅ Config cargado:</h3>";
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'No definido') . "<br>";
        echo "ENVIRONMENT: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'No definido') . "<br>";
    } catch (Exception $e) {
        echo "<h3>❌ Error config:</h3>" . $e->getMessage() . "<br>";
    }
}
?> 
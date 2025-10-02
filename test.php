<?php
echo "🚀 Railway Deploy Test - " . date('Y-m-d H:i:s');
echo "<br>";
echo "✅ PHP está funcionando";
echo "<br>";
echo "📂 Directorio actual: " . __DIR__;
echo "<br>";
echo "🌐 Server: " . ($_SERVER['HTTP_HOST'] ?? 'No definido');
echo "<br>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "✅ config.php encontrado";
} else {
    echo "❌ config.php NO encontrado";
}
echo "<br>";
if (is_dir(__DIR__ . '/admin')) {
    echo "✅ Directorio admin existe";
} else {
    echo "❌ Directorio admin NO existe";
}
echo "<br>";

// Nueva información de debug
echo "<hr>";
echo "<h3>🔍 Debug Adicional:</h3>";
echo "admin/api_gestionar_datos.php: " . (file_exists(__DIR__ . '/admin/api_gestionar_datos.php') ? '✅' : '❌') . "<br>";
echo "includes/db.php: " . (file_exists(__DIR__ . '/includes/db.php') ? '✅' : '❌') . "<br>";

if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'No definido') . "<br>";
        echo "ENVIRONMENT: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'No definido') . "<br>";
    } catch (Exception $e) {
        echo "Error cargando config: " . $e->getMessage() . "<br>";
    }
}

echo "<br>🕐 Last updated: " . date('Y-m-d H:i:s');
?> 
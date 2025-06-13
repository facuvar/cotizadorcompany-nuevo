<?php
// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Información básica
echo "<h1>Test Railway</h1>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</p>";

// Test de conexión a la base de datos
try {
    require_once 'config.php';
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✅ Conexión a la base de datos exitosa</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Mostrar variables de entorno relevantes
echo "<h2>Variables de Entorno</h2>";
echo "<pre>";
echo "RAILWAY_ENVIRONMENT: " . ($_ENV['RAILWAY_ENVIRONMENT'] ?? 'No definido') . "\n";
echo "MYSQLHOST: " . ($_ENV['MYSQLHOST'] ?? 'No definido') . "\n";
echo "MYSQLDATABASE: " . ($_ENV['MYSQLDATABASE'] ?? 'No definido') . "\n";
echo "MYSQLPORT: " . ($_ENV['MYSQLPORT'] ?? 'No definido') . "\n";
echo "</pre>";

// Mostrar información del servidor
echo "<h2>Información del Servidor</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";
?> 
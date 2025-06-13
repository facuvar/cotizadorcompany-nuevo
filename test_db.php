<?php
// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Base de Datos Railway</h1>";

try {
    require_once 'config.php';
    
    echo "<h2>Configuración de Base de Datos</h2>";
    echo "<pre>";
    echo "Host: " . DB_HOST . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Port: " . DB_PORT . "\n";
    echo "User: " . DB_USER . "\n";
    echo "</pre>";
    
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✅ Conexión exitosa</p>";
    
    // Intentar listar las tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Tablas en la Base de Datos</h2>";
    if (empty($tables)) {
        echo "<p style='color: orange;'>⚠️ No hay tablas en la base de datos</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Mostrar información de depuración
    echo "<h2>Información de Depuración</h2>";
    echo "<pre>";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
    echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
    echo "</pre>";
}
?> 
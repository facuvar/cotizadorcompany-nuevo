<?php
// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test MySQL Railway</h1>";

// Intentar conexión directa con MySQL
try {
    $host = 'mysql.railway.internal';
    $user = 'root';
    $pass = 'CdEEWsKUcSueZldgmiaypVCCdnKMjgcD';
    $db = 'railway';
    $port = 3306;

    echo "<h2>Intentando conexión directa a MySQL</h2>";
    echo "<pre>";
    echo "Host: $host\n";
    echo "Database: $db\n";
    echo "Port: $port\n";
    echo "User: $user\n";
    echo "</pre>";

    $mysqli = new mysqli($host, $user, $pass, $db, $port);
    
    if ($mysqli->connect_error) {
        throw new Exception("Error de conexión: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✅ Conexión exitosa</p>";
    echo "<p>Versión del servidor: " . $mysqli->server_info . "</p>";
    
    // Listar tablas
    $result = $mysqli->query("SHOW TABLES");
    if ($result) {
        echo "<h2>Tablas en la base de datos:</h2>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Mostrar información de depuración
    echo "<h2>Información de Depuración</h2>";
    echo "<pre>";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
    echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
    echo "Variables de entorno:\n";
    echo "MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'No definido') . "\n";
    echo "MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: 'No definido') . "\n";
    echo "MYSQLPORT: " . (getenv('MYSQLPORT') ?: 'No definido') . "\n";
    echo "</pre>";
}
?> 
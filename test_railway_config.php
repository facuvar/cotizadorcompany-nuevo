<?php
/**
 * 🚂 TEST CONFIGURACIÓN RAILWAY
 * Archivo para probar la conexión con los nuevos datos de Railway
 */

// Forzar detección de Railway para pruebas
$_ENV['RAILWAY_ENVIRONMENT'] = 'true';

// Configurar los datos de conexión directamente (para pruebas)
$_ENV['MYSQLHOST'] = 'mysql.railway.internal';
$_ENV['MYSQLUSER'] = 'root';
$_ENV['MYSQLPASSWORD'] = 'CdEEWsKUcSueZldgmiaypVCCdnKMjgcD';
$_ENV['MYSQLDATABASE'] = 'railway';
$_ENV['MYSQLPORT'] = '3306';

// Cargar configuración
require_once 'config.php';

echo "<h1>🚂 Test Railway Config</h1>";
echo "<p><strong>Entorno:</strong> " . ENVIRONMENT . "</p>";
echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
echo "<p><strong>Base de datos:</strong> " . DB_NAME . "</p>";
echo "<p><strong>Puerto:</strong> " . DB_PORT . "</p>";

// Test conexión
try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✅ Conexión exitosa!</p>";
    
    $result = $pdo->query("SELECT 1 as test")->fetch();
    echo "<p>Test query: " . $result['test'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚂 Test Configuración Railway</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .info { color: #3498db; }
        .config-item {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .test-result {
            margin: 15px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .test-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚂 Test Configuración Railway</h1>
        
        <h2>📋 Configuración Detectada</h2>
        <?php
        $envInfo = getEnvironmentInfo();
        ?>
        
        <div class="config-item">
            <strong>Entorno:</strong> <?= $envInfo['environment'] ?> 
            <?= $envInfo['is_railway'] ? '🚂' : '💻' ?>
        </div>
        
        <div class="config-item">
            <strong>Host:</strong> <?= $envInfo['host'] ?>
        </div>
        
        <div class="config-item">
            <strong>Base de datos:</strong> <?= $envInfo['database'] ?>
        </div>
        
        <div class="config-item">
            <strong>Puerto:</strong> <?= $envInfo['port'] ?>
        </div>
        
        <div class="config-item">
            <strong>Usuario:</strong> <?= DB_USER ?>
        </div>
        
        <div class="config-item">
            <strong>Contraseña:</strong> <?= str_repeat('*', strlen(DB_PASS)) ?> 
            (<?= strlen(DB_PASS) ?> caracteres)
        </div>
        
        <div class="config-item">
            <strong>URL Base:</strong> <?= $envInfo['base_url'] ?>
        </div>
        
        <h2>🔍 Test de Conexión</h2>
        
        <?php
        // Test de conexión PDO
        echo "<h3>Test PDO:</h3>";
        try {
            $pdo = getDBConnection();
            echo '<div class="test-result test-success">';
            echo '<strong>✅ Conexión PDO exitosa!</strong><br>';
            
            // Test de consulta básica
            $result = $pdo->query("SELECT 1 as test, NOW() as timestamp")->fetch();
            echo "Test query: " . $result['test'] . "<br>";
            echo "Timestamp: " . $result['timestamp'] . "<br>";
            
            // Test de versión MySQL
            $version = $pdo->query("SELECT VERSION() as version")->fetch();
            echo "MySQL Version: " . $version['version'] . "<br>";
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="test-result test-error">';
            echo '<strong>❌ Error en conexión PDO:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        
        // Test de conexión MySQLi
        echo "<h3>Test MySQLi:</h3>";
        try {
            $mysqli = getMySQLiConnection();
            echo '<div class="test-result test-success">';
            echo '<strong>✅ Conexión MySQLi exitosa!</strong><br>';
            echo "Host info: " . $mysqli->host_info . "<br>";
            echo "Server info: " . $mysqli->server_info . "<br>";
            $mysqli->close();
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="test-result test-error">';
            echo '<strong>❌ Error en conexión MySQLi:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        
        // Test de tablas existentes
        echo "<h3>Test de Tablas:</h3>";
        try {
            $pdo = getDBConnection();
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<div class="test-result test-success">';
            echo '<strong>✅ Tablas encontradas (' . count($tables) . '):</strong><br>';
            if (empty($tables)) {
                echo "⚠️ No hay tablas en la base de datos<br>";
            } else {
                foreach ($tables as $table) {
                    echo "📋 " . $table . "<br>";
                }
            }
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="test-result test-error">';
            echo '<strong>❌ Error obteniendo tablas:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <h2>🎯 Información Adicional</h2>
        
        <div class="config-item">
            <strong>URL Pública Railway:</strong> 
            <a href="https://cotizadorcompany-production-6d22.up.railway.app" target="_blank">
                cotizadorcompany-production-6d22.up.railway.app
            </a>
        </div>
        
        <div class="config-item">
            <strong>PHP Version:</strong> <?= PHP_VERSION ?>
        </div>
        
        <div class="config-item">
            <strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?>
        </div>
        
        <h2>🚀 Siguiente Paso</h2>
        <p>Si la conexión es exitosa pero no hay tablas, necesitarás:</p>
        <ol>
            <li>📤 Exportar tu base de datos local</li>
            <li>🚂 Subir el SQL a Railway</li>
            <li>✅ Verificar que todo funcione</li>
        </ol>
        
        <p><a href="cotizador.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">🎯 Probar Cotizador</a></p>
    </div>
</body>
</html> 
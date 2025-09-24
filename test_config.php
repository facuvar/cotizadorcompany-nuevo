<?php
require_once 'sistema/config.php';

echo "<h1>🔧 Test de Configuración Automática</h1>";

echo "<div style='background: " . (IS_RAILWAY ? '#4CAF50' : '#2196F3') . "; color: white; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h2>" . (IS_RAILWAY ? "🚂 Ejecutándose en Railway" : "🏠 Ejecutándose en Local (XAMPP)") . "</h2>";
echo "</div>";

echo "<h3>📊 Configuración Detectada:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th style='padding: 10px; background: #f5f5f5;'>Variable</th><th style='padding: 10px; background: #f5f5f5;'>Valor</th></tr>";
echo "<tr><td style='padding: 8px;'>IS_RAILWAY</td><td style='padding: 8px;'>" . (IS_RAILWAY ? 'true' : 'false') . "</td></tr>";
echo "<tr><td style='padding: 8px;'>DB_HOST</td><td style='padding: 8px;'>" . DB_HOST . "</td></tr>";
echo "<tr><td style='padding: 8px;'>DB_USER</td><td style='padding: 8px;'>" . DB_USER . "</td></tr>";
echo "<tr><td style='padding: 8px;'>DB_NAME</td><td style='padding: 8px;'>" . DB_NAME . "</td></tr>";
echo "<tr><td style='padding: 8px;'>DB_PORT</td><td style='padding: 8px;'>" . DB_PORT . "</td></tr>";
echo "<tr><td style='padding: 8px;'>SITE_URL</td><td style='padding: 8px;'>" . SITE_URL . "</td></tr>";
echo "</table>";

if (IS_RAILWAY) {
    echo "<h3>🌍 Variables de Entorno Railway:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th style='padding: 10px; background: #f5f5f5;'>Variable</th><th style='padding: 10px; background: #f5f5f5;'>Valor</th></tr>";
    
    $railwayVars = ['RAILWAY_ENVIRONMENT', 'PORT', 'MYSQLHOST', 'MYSQLUSER', 'MYSQLDATABASE', 'MYSQLPORT'];
    foreach ($railwayVars as $var) {
        $value = $_ENV[$var] ?? 'No definida';
        echo "<tr><td style='padding: 8px;'>$var</td><td style='padding: 8px;'>$value</td></tr>";
    }
    echo "</table>";
}

echo "<h3>🔗 Test de Conexión a Base de Datos:</h3>";
try {
    require_once 'sistema/includes/db.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if ($conn->connect_error) {
        echo "<div style='background: #f44336; color: white; padding: 10px; border-radius: 5px;'>❌ Error de conexión: " . $conn->connect_error . "</div>";
    } else {
        echo "<div style='background: #4CAF50; color: white; padding: 10px; border-radius: 5px;'>✅ Conexión exitosa a la base de datos</div>";
        
        // Test de consulta simple
        $result = $conn->query("SELECT COUNT(*) as total FROM categorias");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<div style='background: #2196F3; color: white; padding: 10px; border-radius: 5px; margin-top: 10px;'>📊 Categorías encontradas: " . $row['total'] . "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='background: #f44336; color: white; padding: 10px; border-radius: 5px;'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "<br><a href='admin/' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Ir al Admin</a>";
echo " <a href='sistema/cotizador.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>💰 Ir al Cotizador</a>";
?> 
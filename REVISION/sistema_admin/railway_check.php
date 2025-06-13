<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si estamos en Railway
if (!defined('IS_RAILWAY') || !IS_RAILWAY) {
    die("Este script solo debe ejecutarse en Railway");
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Estado de la Base de Datos en Railway</h2>";
    
    // Verificar conexión
    echo "<h3>1. Conexión a la Base de Datos</h3>";
    if ($conn && !$conn->connect_error) {
        echo "✅ Conexión exitosa<br>";
        echo "Host: " . DB_HOST . "<br>";
        echo "Base de datos: " . DB_NAME . "<br>";
    } else {
        echo "❌ Error de conexión: " . ($conn->connect_error ?? "Desconocido") . "<br>";
    }
    
    // Verificar tabla plazos_entrega
    echo "<h3>2. Tabla plazos_entrega</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'plazos_entrega'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla existe<br>";
        
        // Verificar estructura
        $result = $conn->query("DESCRIBE plazos_entrega");
        echo "<pre>Estructura actual:\n";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "</pre>";
        
        // Verificar datos
        $result = $conn->query("SELECT * FROM plazos_entrega ORDER BY orden ASC");
        echo "<pre>Datos actuales:\n";
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " | Nombre: " . $row['nombre'] . " | Días: " . $row['dias'] . "\n";
        }
        echo "</pre>";
    } else {
        echo "❌ Tabla no existe<br>";
    }
    
    // Verificar tabla configuracion
    echo "<h3>3. Tabla configuracion</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'configuracion'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla existe<br>";
        
        // Verificar estructura
        $result = $conn->query("DESCRIBE configuracion");
        echo "<pre>Estructura actual:\n";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "</pre>";
        
        // Verificar datos
        $result = $conn->query("SELECT * FROM configuracion ORDER BY id ASC");
        echo "<pre>Datos actuales:\n";
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " | Nombre: " . $row['nombre'] . " | Valor: " . $row['valor'] . "\n";
        }
        echo "</pre>";
    } else {
        echo "❌ Tabla no existe<br>";
    }
    
    // Enlaces de acción
    echo "<h3>Acciones</h3>";
    echo "<a href='railway_init.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Inicializar Base de Datos</a>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
    
    // Registrar error
    railway_log("Error verificando base de datos en Railway: " . $e->getMessage());
} 
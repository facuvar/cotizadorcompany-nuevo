<?php
/**
 * Script simple para probar la conexión a la base de datos
 */

echo "=== PRUEBA DE CONEXIÓN ===\n";

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
echo "1. Verificando archivo de configuración: $configPath\n";

if (!file_exists($configPath)) {
    die("❌ Error: Archivo de configuración no encontrado\n");
}
echo "✓ Archivo de configuración encontrado\n";

require_once $configPath;
echo "✓ Configuración cargada\n";

// Mostrar configuración (sin contraseñas)
if (defined('DB_HOST')) {
    echo "   - Host: " . DB_HOST . "\n";
    echo "   - Usuario: " . DB_USER . "\n";
    echo "   - Base de datos: " . DB_NAME . "\n";
} else {
    echo "❌ Constantes de base de datos no definidas\n";
}

// Cargar DB
$dbPath = __DIR__ . '/../includes/db.php';
echo "\n2. Verificando archivo de base de datos: $dbPath\n";

if (!file_exists($dbPath)) {
    die("❌ Error: Archivo de base de datos no encontrado\n");
}
echo "✓ Archivo de base de datos encontrado\n";

require_once $dbPath;
echo "✓ Clase Database cargada\n";

try {
    echo "\n3. Intentando conectar a la base de datos...\n";
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Conexión NULL');
    }
    
    echo "✓ Conexión exitosa\n";
    
    // Probar una consulta simple
    echo "\n4. Probando consulta simple...\n";
    $result = $conn->query("SELECT 1 as test");
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ Consulta exitosa: " . $row['test'] . "\n";
    } else {
        echo "❌ Error en consulta: " . $conn->error . "\n";
    }
    
    // Verificar tablas
    echo "\n5. Verificando tablas...\n";
    $tables = ['categorias', 'opciones', 'presupuestos'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ Tabla '$table' existe\n";
            
            // Contar registros
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            if ($count_result) {
                $count = $count_result->fetch_assoc()['count'];
                echo "   - Registros: $count\n";
            }
        } else {
            echo "❌ Tabla '$table' no existe\n";
        }
    }
    
    echo "\n✅ Todas las pruebas completadas\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Detalles: " . $e->getTraceAsString() . "\n";
}
?> 
<?php
// Verificar que estamos en Railway
if (!isset($_ENV['RAILWAY_ENVIRONMENT'])) {
    die("Este script solo puede ejecutarse en el entorno de Railway");
}

// Definir la ruta base del proyecto
define('BASE_PATH', '/app');

// Incluir archivos necesarios
require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/sistema/includes/db.php';
require_once BASE_PATH . '/sistema/includes/functions.php';

// Verificar la conexión a la base de datos
try {
    $conn = getDBConnection();
    echo "✅ Conexión a la base de datos establecida correctamente\n";
    
    // Verificar la estructura de las tablas
    $tables = ['plazos_entrega', 'configuracion'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Tabla '$table' existe\n";
            
            // Verificar la estructura de la tabla
            $columns = $conn->query("SHOW COLUMNS FROM $table");
            echo "Estructura de la tabla '$table':\n";
            while ($column = $columns->fetch_assoc()) {
                echo "  - {$column['Field']} ({$column['Type']})\n";
            }
        } else {
            echo "❌ Tabla '$table' no existe\n";
        }
    }
    
    // Enlaces de acción
    echo "<h3>Acciones</h3>";
    echo "<a href='railway_init.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Inicializar Base de Datos</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    // Registrar error
    railway_log("Error verificando base de datos en Railway: " . $e->getMessage());
} 
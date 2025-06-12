<?php
/**
 * Script para agregar la columna observaciones a la tabla presupuestos
 * Ejecutar una sola vez para actualizar bases de datos existentes
 */

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    die("Error: Archivo de configuración no encontrado");
}
require_once $configPath;

// Cargar DB
$dbPath = __DIR__ . '/../includes/db.php';
if (!file_exists($dbPath)) {
    die("Error: Archivo de base de datos no encontrado");
}
require_once $dbPath;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    echo "Conectado a la base de datos...\n";
    
    // Verificar si la tabla presupuestos existe
    $table_check = $conn->query("SHOW TABLES LIKE 'presupuestos'");
    
    if ($table_check->num_rows == 0) {
        echo "La tabla 'presupuestos' no existe. No es necesario hacer nada.\n";
        exit;
    }
    
    echo "Tabla 'presupuestos' encontrada.\n";
    
    // Verificar si la columna observaciones ya existe
    $column_check = $conn->query("SHOW COLUMNS FROM presupuestos LIKE 'observaciones'");
    
    if ($column_check->num_rows > 0) {
        echo "La columna 'observaciones' ya existe. No es necesario hacer nada.\n";
        exit;
    }
    
    echo "Agregando columna 'observaciones'...\n";
    
    // Agregar la columna observaciones
    $alter_table = "ALTER TABLE presupuestos ADD COLUMN observaciones TEXT AFTER cliente_empresa";
    
    if ($conn->query($alter_table)) {
        echo "✅ Columna 'observaciones' agregada exitosamente.\n";
        
        // Verificar que se agregó correctamente
        $verify_check = $conn->query("SHOW COLUMNS FROM presupuestos LIKE 'observaciones'");
        if ($verify_check->num_rows > 0) {
            echo "✅ Verificación exitosa: La columna existe y está disponible.\n";
        } else {
            echo "⚠️  Advertencia: No se pudo verificar la columna.\n";
        }
    } else {
        throw new Exception('Error al agregar la columna observaciones: ' . $conn->error);
    }
    
    echo "\n🎉 Migración completada exitosamente.\n";
    echo "Ahora los presupuestos pueden incluir observaciones del cliente.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
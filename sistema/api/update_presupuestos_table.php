<?php
/**
 * Script para actualizar la tabla presupuestos con las columnas necesarias
 * Ejecutar una sola vez para migrar la estructura
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
    
    echo "Actualizando estructura de la tabla presupuestos...\n";
    
    // Verificar qué columnas existen
    $result = $conn->query("DESCRIBE presupuestos");
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Lista de columnas necesarias
    $required_columns = [
        'opciones_json' => "ADD COLUMN `opciones_json` TEXT NULL AFTER `telefono_cliente`",
        'plazo_entrega' => "ADD COLUMN `plazo_entrega` INT DEFAULT 90 AFTER `opciones_json`",
        'subtotal' => "ADD COLUMN `subtotal` DECIMAL(10,2) DEFAULT 0.00 AFTER `plazo_entrega`",
        'descuento_porcentaje' => "ADD COLUMN `descuento_porcentaje` DECIMAL(5,2) DEFAULT 0.00 AFTER `subtotal`",
        'descuento_monto' => "ADD COLUMN `descuento_monto` DECIMAL(10,2) DEFAULT 0.00 AFTER `descuento_porcentaje`",
        'empresa_cliente' => "ADD COLUMN `empresa_cliente` VARCHAR(255) NULL AFTER `telefono_cliente`"
    ];
    
    $changes_made = 0;
    
    foreach ($required_columns as $column => $sql) {
        if (!in_array($column, $existing_columns)) {
            $full_sql = "ALTER TABLE presupuestos $sql";
            if ($conn->query($full_sql)) {
                echo "✓ Columna '$column' agregada exitosamente\n";
                $changes_made++;
            } else {
                echo "✗ Error agregando columna '$column': " . $conn->error . "\n";
            }
        } else {
            echo "• Columna '$column' ya existe\n";
        }
    }
    
    // Verificar si necesitamos agregar las columnas de precios por plazo a la tabla opciones
    $result = $conn->query("DESCRIBE opciones");
    $existing_opciones_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_opciones_columns[] = $row['Field'];
    }
    
    $precio_columns = [
        'precio_90_dias' => "ADD COLUMN `precio_90_dias` DECIMAL(10,2) DEFAULT 0.00 AFTER `precio`",
        'precio_160_dias' => "ADD COLUMN `precio_160_dias` DECIMAL(10,2) DEFAULT 0.00 AFTER `precio_90_dias`",
        'precio_270_dias' => "ADD COLUMN `precio_270_dias` DECIMAL(10,2) DEFAULT 0.00 AFTER `precio_160_dias`",
        'descuento' => "ADD COLUMN `descuento` INT DEFAULT 0 AFTER `precio_270_dias`"
    ];
    
    foreach ($precio_columns as $column => $sql) {
        if (!in_array($column, $existing_opciones_columns)) {
            $full_sql = "ALTER TABLE opciones $sql";
            if ($conn->query($full_sql)) {
                echo "✓ Columna '$column' agregada a tabla opciones\n";
                $changes_made++;
            } else {
                echo "✗ Error agregando columna '$column' a opciones: " . $conn->error . "\n";
            }
        } else {
            echo "• Columna '$column' ya existe en opciones\n";
        }
    }
    
    if ($changes_made > 0) {
        echo "\n✅ Migración completada. $changes_made cambios realizados.\n";
    } else {
        echo "\n✅ La estructura ya está actualizada.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 
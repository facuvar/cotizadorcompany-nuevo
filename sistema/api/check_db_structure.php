<?php
/**
 * Script para verificar la estructura actual de las tablas
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
    
    echo "=== ESTRUCTURA ACTUAL DE LAS TABLAS ===\n\n";
    
    // Verificar estructura de presupuestos
    echo "TABLA: presupuestos\n";
    echo "===================\n";
    $result = $conn->query("DESCRIBE presupuestos");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo sprintf("%-20s %-15s %-10s %-10s %-15s %-15s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Null'], 
                $row['Key'], 
                $row['Default'], 
                $row['Extra']
            );
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    
    echo "\n";
    
    // Verificar estructura de opciones
    echo "TABLA: opciones\n";
    echo "===============\n";
    $result = $conn->query("DESCRIBE opciones");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo sprintf("%-20s %-15s %-10s %-10s %-15s %-15s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Null'], 
                $row['Key'], 
                $row['Default'], 
                $row['Extra']
            );
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    
    echo "\n";
    
    // Verificar estructura de categorias
    echo "TABLA: categorias\n";
    echo "=================\n";
    $result = $conn->query("DESCRIBE categorias");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo sprintf("%-20s %-15s %-10s %-10s %-15s %-15s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Null'], 
                $row['Key'], 
                $row['Default'], 
                $row['Extra']
            );
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    
    echo "\n";
    
    // Contar registros
    echo "=== CONTEO DE REGISTROS ===\n";
    
    $tables = ['categorias', 'opciones', 'presupuestos'];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "$table: " . $row['count'] . " registros\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 
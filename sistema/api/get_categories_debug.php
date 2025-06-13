<?php
/**
 * Versión de debug del API de categorías
 */

echo "=== DEBUG GET CATEGORIES ===\n";

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
echo "1. Cargando configuración...\n";

if (!file_exists($configPath)) {
    die("❌ Error: Archivo de configuración no encontrado\n");
}

require_once $configPath;
echo "✓ Configuración cargada\n";

// Cargar DB
$dbPath = __DIR__ . '/../includes/db.php';
echo "2. Cargando Database...\n";

if (!file_exists($dbPath)) {
    die("❌ Error: Archivo de base de datos no encontrado\n");
}

require_once $dbPath;
echo "✓ Database cargada\n";

try {
    echo "3. Conectando a la base de datos...\n";
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    echo "✓ Conexión exitosa\n";
    
    // Obtener categorías
    echo "4. Obteniendo categorías...\n";
    $categorias = [];
    $query = "SELECT * FROM categorias ORDER BY nombre";
    $result = $conn->query($query);
    
    if ($result) {
        echo "✓ Consulta de categorías exitosa\n";
        while ($row = $result->fetch_assoc()) {
            $categorias[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre']
            ];
            echo "   - Categoría: {$row['id']} - {$row['nombre']}\n";
        }
    } else {
        throw new Exception('Error al obtener categorías: ' . $conn->error);
    }
    
    echo "\n✅ Debug completado!\n";
    echo "Total categorías: " . count($categorias) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 
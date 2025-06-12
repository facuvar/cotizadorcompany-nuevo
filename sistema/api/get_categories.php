<?php
/**
 * API para obtener categorías y opciones
 * Usado por el cotizador moderno
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de configuración no encontrado'
    ]);
    exit;
}

require_once $configPath;

// Cargar DB
$dbPath = __DIR__ . '/../includes/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de base de datos no encontrado'
    ]);
    exit;
}

require_once $dbPath;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Obtener categorías
    $categorias = [];
    $query = "SELECT * FROM categorias ORDER BY nombre";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre']
            ];
        }
    } else {
        throw new Exception('Error al obtener categorías: ' . $conn->error);
    }
    
    // Obtener opciones con categorías
    $opciones = [];
    $query = "SELECT o.*, c.nombre as categoria_nombre 
              FROM opciones o 
              LEFT JOIN categorias c ON o.categoria_id = c.id 
              ORDER BY c.nombre, o.nombre";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $opciones[] = [
                'id' => (int)$row['id'],
                'categoria_id' => (int)$row['categoria_id'],
                'categoria_nombre' => $row['categoria_nombre'],
                'nombre' => $row['nombre'],
                'precio_90_dias' => (float)($row['precio_90_dias'] ?? 0),
                'precio_160_dias' => (float)($row['precio_160_dias'] ?? 0),
                'precio_270_dias' => (float)($row['precio_270_dias'] ?? 0),
                'descuento' => (int)($row['descuento'] ?? 0)
            ];
        }
    } else {
        throw new Exception('Error al obtener opciones: ' . $conn->error);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'categorias' => $categorias,
        'opciones' => $opciones,
        'total_categorias' => count($categorias),
        'total_opciones' => count($opciones)
    ]);
    
} catch (Exception $e) {
    error_log('Error en get_categories.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ]);
}
?> 
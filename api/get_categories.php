<?php
/**
 * API para obtener categorías y opciones
 * Usado por el cotizador moderno
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar configuración - buscar en múltiples ubicaciones
$configPaths = [
    __DIR__ . '/../config.php',           // Railway (raíz del proyecto)
    __DIR__ . '/../sistema/config.php',   // Local (dentro de sistema)
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de configuración no encontrado en ninguna ubicación'
    ]);
    exit;
}

// Cargar DB - buscar en múltiples ubicaciones
$dbPaths = [
    __DIR__ . '/../sistema/includes/db.php',   // Local
    __DIR__ . '/../includes/db.php',           // Railway alternativo
];

$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de base de datos no encontrado en ninguna ubicación'
    ]);
    exit;
}

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
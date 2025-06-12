<?php
/**
 * API para obtener categorías y opciones ordenadas
 * Usado por el cotizador ordenado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar configuración
$configPath = __DIR__ . '/../sistema/config.php';
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
$dbPath = __DIR__ . '/../sistema/includes/db.php';
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
    
    // Obtener categorías ordenadas por campo orden
    $categorias = [];
    $query = "SELECT * FROM categorias ORDER BY orden ASC, nombre ASC";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'orden' => (int)($row['orden'] ?? 0)
            ];
        }
    } else {
        throw new Exception('Error al obtener categorías: ' . $conn->error);
    }
    
    // Obtener opciones con categorías ordenadas por campo orden
    $opciones = [];
    $query = "SELECT o.*, c.nombre as categoria_nombre, c.orden as categoria_orden
              FROM opciones o 
              LEFT JOIN categorias c ON o.categoria_id = c.id 
              ORDER BY c.orden ASC, o.orden ASC, o.nombre ASC";
    
    $result = $conn->query($query);
    
    if ($result) {
        $ascensores_count = 0;
        while ($row = $result->fetch_assoc()) {
            $opciones[] = [
                'id' => (int)$row['id'],
                'categoria_id' => (int)$row['categoria_id'],
                'categoria_nombre' => $row['categoria_nombre'],
                'categoria_orden' => (int)($row['categoria_orden'] ?? 0),
                'nombre' => $row['nombre'],
                'precio_90_dias' => (float)($row['precio_90_dias'] ?? 0),
                'precio_160_dias' => (float)($row['precio_160_dias'] ?? 0),
                'precio_270_dias' => (float)($row['precio_270_dias'] ?? 0),
                'descuento' => (int)($row['descuento'] ?? 0),
                'orden' => (int)($row['orden'] ?? 0)
            ];
            
            // Contar ascensores
            if ((int)$row['categoria_id'] === 1) {
                $ascensores_count++;
            }
        }
        
        // Log de depuración
        error_log("API: Total opciones devueltas: " . count($opciones));
        error_log("API: Opciones de ascensores (categoria_id=1): " . $ascensores_count);
        
    } else {
        throw new Exception('Error al obtener opciones: ' . $conn->error);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'categorias' => $categorias,
        'opciones' => $opciones,
        'total_categorias' => count($categorias),
        'total_opciones' => count($opciones),
        'ordenado' => true
    ]);
    
} catch (Exception $e) {
    error_log('Error en get_categories_ordered.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ]);
}
?> 
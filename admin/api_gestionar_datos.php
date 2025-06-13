<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Cargar configuración
$configPath = __DIR__ . '/../sistema/config.php';
if (!file_exists($configPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Archivo de configuración no encontrado']);
    exit;
}
require_once $configPath;

// Cargar DB
$dbPath = __DIR__ . '/../sistema/includes/db.php';
if (!file_exists($dbPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Archivo de base de datos no encontrado']);
    exit;
}
require_once $dbPath;

// Configurar cabeceras para JSON
header('Content-Type: application/json');

// Procesar acciones
$action = $_GET['action'] ?? '';
$response = ['success' => false];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    switch ($action) {
        case 'get_opcion':
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                $response = ['success' => false, 'error' => 'ID no proporcionado'];
                break;
            }
            
            $stmt = $conn->prepare("SELECT * FROM opciones WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($opcion = $result->fetch_assoc()) {
                $response = [
                    'success' => true,
                    'opcion' => $opcion
                ];
            } else {
                $response = ['success' => false, 'error' => 'Opción no encontrada'];
            }
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Acción no válida'];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ];
}

// Devolver respuesta
echo json_encode($response); 
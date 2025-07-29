<?php
session_start();

// Log de depuración inicial
error_log("=== API GESTIONAR DATOS DEBUG ===");
error_log("Action: " . ($_GET['action'] ?? 'No action'));
error_log("ID: " . ($_GET['id'] ?? 'No ID'));
error_log("__DIR__: " . __DIR__);

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("API: Usuario no autorizado");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Cargar configuración centralizada
require_once dirname(__DIR__) . '/config.php';

// Cargar la conexión a la base de datos
require_once dirname(__DIR__) . '/includes/db.php';


// Obtener la conexión a la base de datos
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    if (!$conn) {
        throw new Exception("No se pudo obtener la conexión a la base de datos.");
    }
} catch (Exception $e) {
    error_log("Error fatal al conectar a la DB: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error de conexión en la base de datos.']);
    exit;
}
// Configurar cabeceras para JSON
header('Content-Type: application/json');

// Procesar acciones
$action = $_GET['action'] ?? '';
$response = ['success' => false];

try {
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
                error_log("✅ Opción encontrada ID: " . $id);
            } else {
                $response = ['success' => false, 'error' => 'Opción no encontrada'];
                error_log("❌ Opción no encontrada ID: " . $id);
            }
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Acción no válida: ' . $action];
            error_log("❌ Acción no válida: " . $action);
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ];
    error_log("ERROR en switch: " . $e->getMessage());
}

// Log de respuesta
error_log("Respuesta final: " . json_encode($response));

// Devolver respuesta
echo json_encode($response); 
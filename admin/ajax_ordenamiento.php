<?php
/**
 * Manejo AJAX para las acciones de ordenamiento
 */

header('Content-Type: application/json');

// Verificar autenticación
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Cargar configuración
$configPath = __DIR__ . '/../sistema/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Archivo de configuración no encontrado']);
    exit;
}
require_once $configPath;

// Cargar DB
$dbPath = __DIR__ . '/../sistema/includes/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$action || !$id) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    switch ($action) {
        case 'move_categoria_up':
            // Obtener el orden actual
            $stmt = $conn->prepare("SELECT orden FROM categorias WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $categoria_actual = $result->fetch_assoc();
            
            if (!$categoria_actual) {
                echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
                exit;
            }
            
            $orden_actual = $categoria_actual['orden'] ?? 0;
            
            // Buscar la categoría anterior
            $stmt = $conn->prepare("SELECT id, orden FROM categorias WHERE orden < ? ORDER BY orden DESC LIMIT 1");
            $stmt->bind_param("i", $orden_actual);
            $stmt->execute();
            $result = $stmt->get_result();
            $categoria_anterior = $result->fetch_assoc();
            
            if (!$categoria_anterior) {
                echo json_encode(['success' => false, 'message' => 'La categoría ya está en la primera posición']);
                exit;
            }
            
            // Intercambiar órdenes
            $orden_anterior = $categoria_anterior['orden'];
            $id_anterior = $categoria_anterior['id'];
            
            $conn->begin_transaction();
            
            $stmt1 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
            $stmt1->bind_param("ii", $orden_anterior, $id);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
            $stmt2->bind_param("ii", $orden_actual, $id_anterior);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Categoría movida hacia arriba']);
            break;
            
        case 'move_categoria_down':
            // Obtener el orden actual
            $stmt = $conn->prepare("SELECT orden FROM categorias WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $categoria_actual = $result->fetch_assoc();
            
            if (!$categoria_actual) {
                echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
                exit;
            }
            
            $orden_actual = $categoria_actual['orden'] ?? 0;
            
            // Buscar la categoría siguiente
            $stmt = $conn->prepare("SELECT id, orden FROM categorias WHERE orden > ? ORDER BY orden ASC LIMIT 1");
            $stmt->bind_param("i", $orden_actual);
            $stmt->execute();
            $result = $stmt->get_result();
            $categoria_siguiente = $result->fetch_assoc();
            
            if (!$categoria_siguiente) {
                echo json_encode(['success' => false, 'message' => 'La categoría ya está en la última posición']);
                exit;
            }
            
            // Intercambiar órdenes
            $orden_siguiente = $categoria_siguiente['orden'];
            $id_siguiente = $categoria_siguiente['id'];
            
            $conn->begin_transaction();
            
            $stmt1 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
            $stmt1->bind_param("ii", $orden_siguiente, $id);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
            $stmt2->bind_param("ii", $orden_actual, $id_siguiente);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Categoría movida hacia abajo']);
            break;
            
        case 'move_opcion_up':
            // Obtener la opción actual
            $stmt = $conn->prepare("SELECT categoria_id, orden FROM opciones WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $opcion_actual = $result->fetch_assoc();
            
            if (!$opcion_actual) {
                echo json_encode(['success' => false, 'message' => 'Opción no encontrada']);
                exit;
            }
            
            $categoria_id = $opcion_actual['categoria_id'];
            $orden_actual = $opcion_actual['orden'] ?? 0;
            
            // Buscar la opción anterior en la misma categoría
            $stmt = $conn->prepare("SELECT id, orden FROM opciones WHERE categoria_id = ? AND orden < ? ORDER BY orden DESC LIMIT 1");
            $stmt->bind_param("ii", $categoria_id, $orden_actual);
            $stmt->execute();
            $result = $stmt->get_result();
            $opcion_anterior = $result->fetch_assoc();
            
            if (!$opcion_anterior) {
                echo json_encode(['success' => false, 'message' => 'La opción ya está en la primera posición de su categoría']);
                exit;
            }
            
            // Intercambiar órdenes
            $orden_anterior = $opcion_anterior['orden'];
            $id_anterior = $opcion_anterior['id'];
            
            $conn->begin_transaction();
            
            $stmt1 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
            $stmt1->bind_param("ii", $orden_anterior, $id);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
            $stmt2->bind_param("ii", $orden_actual, $id_anterior);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Opción movida hacia arriba']);
            break;
            
        case 'move_opcion_down':
            // Obtener la opción actual
            $stmt = $conn->prepare("SELECT categoria_id, orden FROM opciones WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $opcion_actual = $result->fetch_assoc();
            
            if (!$opcion_actual) {
                echo json_encode(['success' => false, 'message' => 'Opción no encontrada']);
                exit;
            }
            
            $categoria_id = $opcion_actual['categoria_id'];
            $orden_actual = $opcion_actual['orden'] ?? 0;
            
            // Buscar la opción siguiente en la misma categoría
            $stmt = $conn->prepare("SELECT id, orden FROM opciones WHERE categoria_id = ? AND orden > ? ORDER BY orden ASC LIMIT 1");
            $stmt->bind_param("ii", $categoria_id, $orden_actual);
            $stmt->execute();
            $result = $stmt->get_result();
            $opcion_siguiente = $result->fetch_assoc();
            
            if (!$opcion_siguiente) {
                echo json_encode(['success' => false, 'message' => 'La opción ya está en la última posición de su categoría']);
                exit;
            }
            
            // Intercambiar órdenes
            $orden_siguiente = $opcion_siguiente['orden'];
            $id_siguiente = $opcion_siguiente['id'];
            
            $conn->begin_transaction();
            
            $stmt1 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
            $stmt1->bind_param("ii", $orden_siguiente, $id);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
            $stmt2->bind_param("ii", $orden_actual, $id_siguiente);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Opción movida hacia abajo']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    // Para mysqli no existe inTransaction(), así que simplemente intentamos rollback
    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackException) {
            // Si falla el rollback, lo registramos pero continuamos
            error_log("Error en rollback: " . $rollbackException->getMessage());
        }
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 
<?php
// Script para eliminar un presupuesto
session_start();

// Determinar si es una solicitud AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
    } else {
        header('Location: login.php');
    }
    exit;
}

// Incluir archivos de configuración y base de datos
require_once '../config.php';
require_once '../includes/db.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    } else {
        header('Location: presupuestos.php');
    }
    exit;
}

// Verificar que se recibió un ID válido
if (!isset($_POST['presupuesto_id']) || !is_numeric($_POST['presupuesto_id'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de presupuesto no válido']);
    } else {
        $_SESSION['error_message'] = 'ID de presupuesto no válido.';
        header('Location: presupuestos.php');
    }
    exit;
}

$presupuestoId = (int)$_POST['presupuesto_id'];

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Primero eliminar registros relacionados en presupuestos_historial si existe la tabla
    $tableExists = $conn->query("SHOW TABLES LIKE 'presupuestos_historial'")->num_rows > 0;
    if ($tableExists) {
        $sqlHistorial = "DELETE FROM presupuestos_historial WHERE presupuesto_id = ?";
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->bind_param("i", $presupuestoId);
        $stmtHistorial->execute();
    }
    
    // Eliminar el presupuesto
    $sql = "DELETE FROM presupuestos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $presupuestoId);
    
    if ($stmt->execute()) {
        // Preparar respuesta
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Presupuesto eliminado correctamente.',
                'presupuestoId' => $presupuestoId
            ]);
            exit;
        } else {
            // Preparar mensaje de éxito para redirección normal
            $_SESSION['success_message'] = 'Presupuesto eliminado correctamente.';
            header('Location: presupuestos.php');
            exit;
        }
    } else {
        $errorMsg = 'Error al eliminar el presupuesto: ' . $stmt->error;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        } else {
            $_SESSION['error_message'] = $errorMsg;
            header('Location: presupuestos.php');
            exit;
        }
    }
    
} catch (Exception $e) {
    $errorMsg = 'Error: ' . $e->getMessage();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    } else {
        $_SESSION['error_message'] = $errorMsg;
        header('Location: presupuestos.php');
    }
    exit;
}
?>

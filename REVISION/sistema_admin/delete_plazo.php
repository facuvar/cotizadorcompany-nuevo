<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Verificar el método de la solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    header('Location: view_plazos_entrega.php');
    exit;
}

// Obtener y validar el ID del plazo
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    setFlashMessage('error', 'ID de plazo no válido');
    header('Location: view_plazos_entrega.php');
    exit;
}

try {
    // Iniciar la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar si el plazo existe
    $checkStmt = $conn->prepare("SELECT id FROM plazos_entrega WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("El plazo de entrega no existe");
    }
    
    // Verificar si el plazo está en uso en algún presupuesto
    $usageStmt = $conn->prepare("SELECT id FROM presupuestos WHERE plazo_id = ? LIMIT 1");
    $usageStmt->bind_param('i', $id);
    $usageStmt->execute();
    $usageResult = $usageStmt->get_result();
    
    if ($usageResult->num_rows > 0) {
        throw new Exception("No se puede eliminar el plazo porque está siendo utilizado en presupuestos existentes");
    }
    
    // Eliminar el plazo
    $deleteStmt = $conn->prepare("DELETE FROM plazos_entrega WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    
    if ($deleteStmt->execute()) {
        $conn->commit();
        setFlashMessage('success', 'Plazo de entrega eliminado correctamente');
    } else {
        throw new Exception("Error al eliminar el plazo: " . $conn->error);
    }
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    setFlashMessage('error', 'Error: ' . $e->getMessage());
}

// Redireccionar de vuelta a la página de plazos
header('Location: view_plazos_entrega.php');
exit;
?> 
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Verificar si se recibieron datos por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    header('Location: view_plazos_entrega.php');
    exit;
}

// Verificar que se recibieron todos los datos necesarios
if (!isset($_POST['id']) || !isset($_POST['nombre']) || !isset($_POST['dias'])) {
    setFlashMessage('error', 'Faltan parámetros requeridos');
    header('Location: view_plazos_entrega.php');
    exit;
}

$id = intval($_POST['id']);
$nombre = trim($_POST['nombre']);
$dias = intval($_POST['dias']);

// Validar datos
if (empty($nombre)) {
    setFlashMessage('error', 'El nombre del plazo no puede estar vacío');
    header('Location: view_plazos_entrega.php');
    exit;
}

if ($dias < 0) {
    setFlashMessage('error', 'El número de días debe ser un valor positivo');
    header('Location: view_plazos_entrega.php');
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar si el plazo existe
    $checkQuery = "SELECT id FROM plazos_entrega WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception('El plazo de entrega no existe');
    }
    
    // Verificar si ya existe otro plazo con el mismo nombre (que no sea el actual)
    $duplicateQuery = "SELECT id FROM plazos_entrega WHERE nombre = ? AND id != ?";
    $duplicateStmt = $conn->prepare($duplicateQuery);
    $duplicateStmt->bind_param('si', $nombre, $id);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    
    if ($duplicateResult->num_rows > 0) {
        throw new Exception('Ya existe un plazo de entrega con ese nombre');
    }
    
    // Actualizar el plazo
    $updateQuery = "UPDATE plazos_entrega SET nombre = ?, dias = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param('sii', $nombre, $dias, $id);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Error al actualizar el plazo de entrega: ' . $conn->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    setFlashMessage('success', 'Plazo de entrega actualizado correctamente');
} catch (Exception $e) {
    // Revertir cambios en caso de error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    setFlashMessage('error', $e->getMessage());
} finally {
    // Cerrar conexiones
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($duplicateStmt)) $duplicateStmt->close();
    if (isset($updateStmt)) $updateStmt->close();
}

// Redireccionar a la página de gestión
header('Location: view_plazos_entrega.php');
exit;
?> 
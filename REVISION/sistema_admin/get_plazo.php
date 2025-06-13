<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Verificar que se recibió el ID del plazo
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el ID del plazo']);
    exit;
}

$id = intval($_GET['id']);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Consultar la información del plazo
    $query = "SELECT id, nombre, dias FROM plazos_entrega WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Plazo de entrega no encontrado']);
        exit;
    }
    
    $plazo = $result->fetch_assoc();
    
    // Devolver la información del plazo en formato JSON
    echo json_encode([
        'success' => true,
        'plazo' => $plazo
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener el plazo: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
}
?> 
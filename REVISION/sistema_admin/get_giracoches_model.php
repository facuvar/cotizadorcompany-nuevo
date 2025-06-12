<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

try {
    // Validar ID del modelo
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("ID de modelo no válido");
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $modelId = intval($_GET['id']);
    
    // Obtener información del modelo
    $query = "SELECT * FROM opciones WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $modelId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Modelo no encontrado");
    }
    
    $modelo = $result->fetch_assoc();
    
    // Obtener precios por plazo
    $query = "SELECT op.*, pe.nombre as plazo_nombre 
              FROM opcion_precios op
              JOIN plazos_entrega pe ON op.plazo_id = pe.id
              WHERE op.opcion_id = ?
              ORDER BY pe.nombre";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $modelId);
    $stmt->execute();
    $resultPrecios = $stmt->get_result();
    
    $precios = [];
    
    while ($precio = $resultPrecios->fetch_assoc()) {
        // Usar el nombre del plazo como clave
        $precios[$precio['plazo_entrega']] = $precio['precio'];
    }
    
    // Preparar respuesta JSON
    $response = [
        'id' => $modelo['id'],
        'nombre' => $modelo['nombre'],
        'precios' => $precios
    ];
    
    // Enviar respuesta
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Enviar error
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
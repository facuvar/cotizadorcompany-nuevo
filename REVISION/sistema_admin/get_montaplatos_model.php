<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar que el usuario sea administrador
requireAdmin();

header('Content-Type: application/json');

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de modelo no válido'
    ]);
    exit;
}

$modelId = (int)$_GET['id'];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar que el modelo exista y pertenezca a la categoría MONTAPLATOS
    $queryModel = "SELECT o.id, o.nombre 
                   FROM opciones o 
                   JOIN categorias c ON o.categoria_id = c.id 
                   WHERE o.id = ? AND c.nombre = 'MONTAPLATOS'";
    
    $stmt = $conn->prepare($queryModel);
    $stmt->bind_param('i', $modelId);
    $stmt->execute();
    $resultModel = $stmt->get_result();
    
    if ($resultModel->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Modelo no encontrado o no pertenece a la categoría MONTAPLATOS'
        ]);
        exit;
    }
    
    $model = $resultModel->fetch_assoc();
    
    // Obtener todos los plazos de entrega
    $queryPlazos = "SELECT id, nombre, dias FROM plazos_entrega ORDER BY dias";
    $resultPlazos = $conn->query($queryPlazos);
    
    $plazos = [];
    while ($plazo = $resultPlazos->fetch_assoc()) {
        $plazos[] = $plazo;
    }
    
    // Obtener precios para este modelo
    $queryPrecios = "SELECT plazo_id, precio 
                     FROM opcion_precios 
                     WHERE opcion_id = ?";
    
    $stmt = $conn->prepare($queryPrecios);
    $stmt->bind_param('i', $modelId);
    $stmt->execute();
    $resultPrecios = $stmt->get_result();
    
    $prices = [];
    while ($precio = $resultPrecios->fetch_assoc()) {
        $prices[$precio['plazo_id']] = $precio['precio'];
    }
    
    echo json_encode([
        'success' => true,
        'model' => $model,
        'plazos' => $plazos,
        'prices' => $prices
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 
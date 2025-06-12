<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/flash_messages.php';

// Verificar que el usuario sea administrador
requireAdmin();

// Verificar el método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    header('Location: index.php');
    exit;
}

// Validar datos del formulario
if (!isset($_POST['model_id']) || !is_numeric($_POST['model_id']) || 
    !isset($_POST['model_name']) || empty(trim($_POST['model_name']))) {
    setFlashMessage('error', 'Datos del formulario incompletos o inválidos');
    header('Location: validate_montaplatos.php');
    exit;
}

$modelId = (int)$_POST['model_id'];
$modelName = trim($_POST['model_name']);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar que el modelo exista y pertenezca a la categoría MONTAPLATOS
    $queryModel = "SELECT o.id 
                   FROM opciones o 
                   JOIN categorias c ON o.categoria_id = c.id 
                   WHERE o.id = ? AND c.nombre = 'MONTAPLATOS'";
    
    $stmt = $conn->prepare($queryModel);
    $stmt->bind_param('i', $modelId);
    $stmt->execute();
    $resultModel = $stmt->get_result();
    
    if ($resultModel->num_rows === 0) {
        throw new Exception('Modelo no encontrado o no pertenece a la categoría MONTAPLATOS');
    }
    
    // Actualizar el nombre del modelo
    $updateModel = "UPDATE opciones SET nombre = ? WHERE id = ?";
    $stmt = $conn->prepare($updateModel);
    $stmt->bind_param('si', $modelName, $modelId);
    $stmt->execute();
    
    // Procesar precios para cada plazo de entrega
    $queryPlazos = "SELECT id FROM plazos_entrega";
    $resultPlazos = $conn->query($queryPlazos);
    
    // Preparar consultas para insertar o actualizar precios
    $checkPrice = "SELECT id FROM opcion_precios WHERE opcion_id = ? AND plazo_id = ?";
    $updatePrice = "UPDATE opcion_precios SET precio = ? WHERE opcion_id = ? AND plazo_id = ?";
    $insertPrice = "INSERT INTO opcion_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)";
    
    $stmtCheck = $conn->prepare($checkPrice);
    $stmtUpdate = $conn->prepare($updatePrice);
    $stmtInsert = $conn->prepare($insertPrice);
    
    while ($plazo = $resultPlazos->fetch_assoc()) {
        $plazoId = $plazo['id'];
        $priceKey = 'price_' . $plazoId;
        
        // Verificar si el precio existe y es numérico
        if (isset($_POST[$priceKey]) && !empty($_POST[$priceKey])) {
            $price = str_replace(',', '.', $_POST[$priceKey]);
            if (!is_numeric($price)) {
                throw new Exception('Precio no válido para el plazo ID: ' . $plazoId);
            }
            
            // Verificar si el precio ya existe para este modelo y plazo
            $stmtCheck->bind_param('ii', $modelId, $plazoId);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            
            if ($resultCheck->num_rows > 0) {
                // Actualizar precio existente
                $stmtUpdate->bind_param('dii', $price, $modelId, $plazoId);
                $stmtUpdate->execute();
            } else {
                // Insertar nuevo precio
                $stmtInsert->bind_param('iid', $modelId, $plazoId, $price);
                $stmtInsert->execute();
            }
        } else {
            // Verificar si el precio ya existe para este modelo y plazo
            $stmtCheck->bind_param('ii', $modelId, $plazoId);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            
            if ($resultCheck->num_rows > 0) {
                // Establecer el precio como NULL si no se proporcionó un valor
                $price = null;
                $stmtUpdate->bind_param('dii', $price, $modelId, $plazoId);
                $stmtUpdate->execute();
            }
        }
    }
    
    // Confirmar los cambios
    $conn->commit();
    
    setFlashMessage('success', 'Modelo actualizado correctamente');
    header('Location: validate_montaplatos.php');
    exit;
    
} catch (Exception $e) {
    // Revertir los cambios en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    setFlashMessage('error', 'Error al actualizar el modelo: ' . $e->getMessage());
    header('Location: validate_montaplatos.php');
    exit;
}
?> 
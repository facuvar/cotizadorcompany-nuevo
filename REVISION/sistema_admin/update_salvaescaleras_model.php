<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

try {
    // Verificar si es una solicitud POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }
    
    // Validar ID del modelo
    if (!isset($_POST['model_id']) || !is_numeric($_POST['model_id'])) {
        throw new Exception("ID de modelo no válido");
    }
    
    // Validar nombre del modelo
    if (!isset($_POST['model_name']) || empty(trim($_POST['model_name']))) {
        throw new Exception("El nombre del modelo no puede estar vacío");
    }
    
    // Validar precios
    if (!isset($_POST['precios']) || !is_array($_POST['precios']) || empty($_POST['precios'])) {
        throw new Exception("No se han proporcionado precios para el modelo");
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    $modelId = intval($_POST['model_id']);
    $modelName = trim($_POST['model_name']);
    $precios = $_POST['precios'];
    
    // Verificar que el modelo exista
    $query = "SELECT * FROM opciones WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $modelId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("El modelo con ID $modelId no existe");
    }
    
    // Actualizar nombre del modelo
    $query = "UPDATE opciones SET nombre = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $modelName, $modelId);
    $stmt->execute();
    
    // Actualizar precios
    foreach ($precios as $plazoDias => $precio) {
        // Validar que el precio es un número válido
        if (!is_numeric($precio) || floatval($precio) < 0) {
            throw new Exception("El precio para el plazo de $plazoDias días no es válido");
        }
        
        $plazoDias = intval($plazoDias);
        $precio = floatval($precio);
        
        // Verificar si ya existe el precio para este plazo
        $query = "SELECT id FROM precios WHERE opcion_id = ? AND plazo_dias = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $modelId, $plazoDias);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Actualizar precio existente
            $query = "UPDATE precios SET precio = ? WHERE opcion_id = ? AND plazo_dias = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("dii", $precio, $modelId, $plazoDias);
            $stmt->execute();
        } else {
            // Insertar nuevo precio
            $query = "INSERT INTO precios (opcion_id, plazo_dias, precio) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iid", $modelId, $plazoDias, $precio);
            $stmt->execute();
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Establecer mensaje de éxito y redireccionar
    setFlashMessage("El modelo '$modelName' ha sido actualizado correctamente");
    header("Location: validate_salvaescaleras.php");
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->connect_error === false) {
        $conn->rollback();
    }
    
    // Establecer mensaje de error y redireccionar
    setFlashMessage("Error al actualizar el modelo: " . $e->getMessage(), "danger");
    header("Location: validate_salvaescaleras.php");
    exit;
} 
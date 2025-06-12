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
    
    // Mapa de IDs de plazos conocidos
    $plazosIdMap = [
        '90 dias' => 60,
        '160/180 dias' => 62,
        '270 dias' => 61
    ];
    
    // Actualizar precios
    $conn->query("DELETE FROM opcion_precios WHERE opcion_id = " . $modelId);
    foreach ($precios as $plazo => $precio) {
        $plazoEntrega = $plazo;
        
        // Usar el ID directamente si conocemos el mapeo
        if (isset($plazosIdMap[$plazoEntrega])) {
            $plazoId = $plazosIdMap[$plazoEntrega];
        } else {
            // Verificar si ya existe un plazo con ese nombre
            $queryPlazo = "SELECT id FROM plazos_entrega WHERE nombre = ?";
            $stmtPlazo = $conn->prepare($queryPlazo);
            $stmtPlazo->bind_param('s', $plazoEntrega);
            $stmtPlazo->execute();
            $resultPlazo = $stmtPlazo->get_result();
            
            if ($resultPlazo->num_rows > 0) {
                $plazoId = $resultPlazo->fetch_assoc()['id'];
            } else {
                // Crear el plazo si no existe
                $insertPlazo = "INSERT INTO plazos_entrega (nombre) VALUES (?)";
                $stmtInsertPlazo = $conn->prepare($insertPlazo);
                $stmtInsertPlazo->bind_param('s', $plazoEntrega);
                $stmtInsertPlazo->execute();
                $plazoId = $conn->insert_id;
            }
        }
        
        $query = "INSERT INTO opcion_precios (opcion_id, plazo_id, plazo_entrega, precio) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iisd', $modelId, $plazoId, $plazoEntrega, $precio);
        $stmt->execute();
        $stmt->close();
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Establecer mensaje de éxito y redireccionar
    setFlashMessage("success", "El modelo '$modelName' ha sido actualizado correctamente");
    header("Location: validate_giracoches.php");
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->connect_error === false) {
        $conn->rollback();
    }
    
    // Establecer mensaje de error y redireccionar
    setFlashMessage("error", "Error al actualizar el modelo: " . $e->getMessage());
    header("Location: validate_giracoches.php");
    exit;
} 
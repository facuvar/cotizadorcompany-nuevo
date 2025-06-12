<?php
// Script para cambiar el estado de un presupuesto
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

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['presupuesto_id']) || !isset($_POST['nuevo_estado'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Datos incompletos para cambiar el estado del presupuesto.']);
    } else {
        $_SESSION['error_message'] = 'Datos incompletos para cambiar el estado del presupuesto.';
        header('Location: presupuestos.php');
    }
    exit;
}

// Obtener datos del formulario
$presupuestoId = (int)$_POST['presupuesto_id'];
$nuevoEstado = $_POST['nuevo_estado'];
$comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

// Validar estado
$estadosValidos = ['pendiente', 'enviado', 'aprobado', 'rechazado'];
if (!in_array($nuevoEstado, $estadosValidos)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Estado no válido.']);
    } else {
        $_SESSION['error_message'] = 'Estado no válido.';
        header('Location: presupuestos.php');
    }
    exit;
}

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Actualizar estado del presupuesto
    $sql = "UPDATE presupuestos SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nuevoEstado, $presupuestoId);
    
    if ($stmt->execute()) {
        // Registrar el cambio de estado en el historial si existe la tabla
        $tableExists = $conn->query("SHOW TABLES LIKE 'presupuestos_historial'")->num_rows > 0;
        
        if (!$tableExists) {
            // Crear la tabla de historial si no existe
            $createTableSQL = "CREATE TABLE presupuestos_historial (
                id INT AUTO_INCREMENT PRIMARY KEY,
                presupuesto_id INT NOT NULL,
                estado_anterior VARCHAR(50),
                estado_nuevo VARCHAR(50) NOT NULL,
                comentario TEXT,
                usuario VARCHAR(100),
                fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id) ON DELETE CASCADE
            )";
            
            $conn->query($createTableSQL);
        }
        
        // Obtener el estado anterior
        $sqlEstadoAnterior = "SELECT estado FROM presupuestos WHERE id = ?";
        $stmtEstadoAnterior = $conn->prepare($sqlEstadoAnterior);
        $stmtEstadoAnterior->bind_param("i", $presupuestoId);
        $stmtEstadoAnterior->execute();
        $resultEstadoAnterior = $stmtEstadoAnterior->get_result();
        $estadoAnterior = $resultEstadoAnterior->fetch_assoc()['estado'];
        
        // Registrar en el historial
        $sqlHistorial = "INSERT INTO presupuestos_historial (presupuesto_id, estado_anterior, estado_nuevo, comentario, usuario) 
                         VALUES (?, ?, ?, ?, ?)";
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $usuario = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'admin';
        $stmtHistorial->bind_param("issss", $presupuestoId, $estadoAnterior, $nuevoEstado, $comentario, $usuario);
        $stmtHistorial->execute();
        
        // Preparar respuesta
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Estado del presupuesto actualizado correctamente.',
                'nuevoEstado' => $nuevoEstado,
                'presupuestoId' => $presupuestoId
            ]);
            exit;
        } else {
            // Preparar mensaje de éxito para redirección normal
            $_SESSION['success_message'] = 'Estado del presupuesto actualizado correctamente.';
            
            // Redirigir de vuelta a la página anterior
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            // Si venimos de la página de detalles, volver a ella
            if (strpos($referer, 'ver_presupuesto.php') !== false) {
                header('Location: ' . $referer);
            } else {
                // De lo contrario, volver a la lista de presupuestos
                header('Location: presupuestos.php');
            }
            exit;
        }
    } else {
        $errorMsg = 'Error al actualizar el estado del presupuesto: ' . $stmt->error;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        } else {
            $_SESSION['error_message'] = $errorMsg;
        }
    }
    
} catch (Exception $e) {
    $errorMsg = 'Error: ' . $e->getMessage();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    } else {
        $_SESSION['error_message'] = $errorMsg;
        // Redireccionar de vuelta a la página de presupuestos
        header('Location: presupuestos.php');
    }
    exit;
}
?>

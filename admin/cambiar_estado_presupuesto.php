<?php
session_start();
require_once '../sistema/config.php';
require_once '../sistema/includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del formulario
$presupuestoId = isset($_POST['presupuesto_id']) ? (int)$_POST['presupuesto_id'] : 0;
$nuevoEstado = isset($_POST['estado']) ? $_POST['estado'] : '';
$comentario = isset($_POST['comentario']) ? $_POST['comentario'] : '';

// Validar datos
if ($presupuestoId <= 0 || empty($nuevoEstado)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Estados válidos
$estadosValidos = ['pendiente', 'enviado', 'aprobado', 'rechazado'];
if (!in_array($nuevoEstado, $estadosValidos)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit;
}

// Conectar a la base de datos
$db = Database::getInstance();
$conn = $db->getConnection();

// Actualizar el estado del presupuesto
$sql = "UPDATE presupuestos SET estado = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $nuevoEstado, $presupuestoId);

if ($stmt->execute()) {
    // Verificar si existe la tabla de historial
    $tableExists = $conn->query("SHOW TABLES LIKE 'presupuestos_historial'")->num_rows > 0;
    
    if (!$tableExists) {
        // Crear la tabla de historial si no existe
        $createTableSQL = "CREATE TABLE presupuestos_historial (
            id INT AUTO_INCREMENT PRIMARY KEY,
            presupuesto_id INT NOT NULL,
            estado_anterior VARCHAR(50) NOT NULL,
            estado_nuevo VARCHAR(50) NOT NULL,
            comentario TEXT,
            usuario VARCHAR(100) NOT NULL,
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
    $row = $resultEstadoAnterior->fetch_assoc();
    $estadoAnterior = $row ? $row['estado'] : 'desconocido';
    
    // Registrar en el historial
    $sqlHistorial = "INSERT INTO presupuestos_historial (presupuesto_id, estado_anterior, estado_nuevo, comentario, usuario) VALUES (?, ?, ?, ?, ?)";
    $stmtHistorial = $conn->prepare($sqlHistorial);
    $usuario = $_SESSION['admin_username'] ?? 'admin';
    $stmtHistorial->bind_param("issss", $presupuestoId, $estadoAnterior, $nuevoEstado, $comentario, $usuario);
    $stmtHistorial->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

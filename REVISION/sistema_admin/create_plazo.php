<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    header('Location: view_plazos_entrega.php');
    exit;
}

// Validar datos de entrada
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$dias = isset($_POST['dias']) ? (int)$_POST['dias'] : 0;

if (empty($nombre) || $dias <= 0) {
    setFlashMessage('error', 'El nombre y los días son obligatorios');
    header('Location: view_plazos_entrega.php');
    exit;
}

try {
    // Iniciar la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Preparar la descripción basada en el nombre y días
    $descripcion = "Entrega en {$nombre} ({$dias} días)";
    
    // Obtener el último orden
    $result = $conn->query("SELECT MAX(orden) as max_orden FROM plazos_entrega");
    $row = $result->fetch_assoc();
    $orden = ($row['max_orden'] ?? 0) + 1;
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Preparar la consulta para insertar un nuevo plazo
    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, descripcion, dias, orden) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $nombre, $descripcion, $dias, $orden);
    
    // Ejecutar la consulta
    if ($stmt->execute()) {
        $conn->commit();
        setFlashMessage('success', 'Plazo de entrega creado correctamente');
    } else {
        throw new Exception("Error al crear el plazo de entrega: " . $conn->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    setFlashMessage('error', 'Error: ' . $e->getMessage());
}

// Redireccionar de vuelta a la página de administración
header('Location: view_plazos_entrega.php');
exit;
?> 
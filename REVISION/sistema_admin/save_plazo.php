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
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$orden = isset($_POST['orden']) ? (int)$_POST['orden'] : 0;

if (empty($nombre)) {
    setFlashMessage('error', 'El nombre del plazo es obligatorio');
    header('Location: view_plazos_entrega.php');
    exit;
}

try {
    // Iniciar la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Preparar la consulta para insertar un nuevo plazo
    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, descripcion, orden) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nombre, $descripcion, $orden);
    
    // Ejecutar la consulta
    if ($stmt->execute()) {
        setFlashMessage('success', 'Plazo de entrega añadido correctamente');
    } else {
        throw new Exception("Error al guardar el plazo de entrega: " . $conn->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    setFlashMessage('error', 'Error: ' . $e->getMessage());
}

// Redireccionar de vuelta a la página de administración
header('Location: view_plazos_entrega.php');
exit;
?> 
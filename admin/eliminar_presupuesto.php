<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Cargar configuración - buscar en múltiples ubicaciones
$configPaths = [
    __DIR__ . '/../config.php',           // Railway (raíz del proyecto)
    __DIR__ . '/../sistema/config.php',   // Local (dentro de sistema)
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    die("Error: No se pudo encontrar el archivo de configuración en ninguna ubicación");
}

// Cargar DB - buscar en múltiples ubicaciones
$dbPaths = [
    __DIR__ . '/../sistema/includes/db.php',   // Local
    __DIR__ . '/../includes/db.php',           // Railway alternativo
];

foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        break;
    }
}

// Verificar que se recibió un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No se especificó un ID de presupuesto válido";
    header('Location: presupuestos.php');
    exit;
}

$presupuesto_id = intval($_GET['id']);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si el presupuesto existe
    $check_query = "SELECT id FROM presupuestos WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('i', $presupuesto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "El presupuesto #$presupuesto_id no existe";
        header('Location: presupuestos.php');
        exit;
    }
    
    // Primero eliminamos los detalles asociados al presupuesto
    $delete_details = "DELETE FROM presupuesto_detalles WHERE presupuesto_id = ?";
    $stmt = $conn->prepare($delete_details);
    $stmt->bind_param('i', $presupuesto_id);
    $stmt->execute();
    
    // Luego eliminamos el presupuesto
    $delete_quote = "DELETE FROM presupuestos WHERE id = ?";
    $stmt = $conn->prepare($delete_quote);
    $stmt->bind_param('i', $presupuesto_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "Presupuesto #$presupuesto_id eliminado correctamente";
    } else {
        $_SESSION['error_message'] = "No se pudo eliminar el presupuesto #$presupuesto_id";
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Redirigir de vuelta a la lista de presupuestos
header('Location: presupuestos.php');
exit;
?> 
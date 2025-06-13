<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el administrador está logueado
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar que se recibió la URL
    if (!isset($_POST['sheetsUrl']) || empty($_POST['sheetsUrl'])) {
        throw new Exception('No se proporcionó la URL de Google Sheets');
    }
    
    $url = $_POST['sheetsUrl'];
    
    // Verificar que la URL sea válida
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('La URL proporcionada no es válida');
    }
    
    // Verificar que sea una URL de Google Sheets
    if (strpos($url, 'docs.google.com/spreadsheets') === false) {
        throw new Exception('La URL debe ser de Google Sheets');
    }
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    // Eliminar registros anteriores de fuente_datos
    $query = "DELETE FROM fuente_datos WHERE tipo = 'google_sheets'";
    $conn->query($query);
    
    // Registrar la nueva fuente de datos
    $query = "INSERT INTO fuente_datos (tipo, url, fecha_actualizacion) VALUES ('google_sheets', ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $url);
    $stmt->execute();
    
    // Importar los datos - usar el script actualizado
    require_once 'import_sheets_data.php';
    
    // Redirigir con mensaje de éxito
    setFlashMessage('Conexión con Google Sheets establecida correctamente. Categorías actualizadas con los nuevos nombres.', 'success');
    redirect(SITE_URL . '/admin/index.php');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Redirigir con mensaje de error
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . '/admin/index.php');
} 
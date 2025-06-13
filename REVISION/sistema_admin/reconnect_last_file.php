<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el administrador está logueado
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si se recibió el ID de la fuente (ya sea por POST o GET)
    if (!isset($_POST['source_id']) && !isset($_GET['source_id'])) {
        throw new Exception('No se recibió información del archivo a reconectar');
    }
    
    // Obtener el ID de la fuente (prioridad a POST)
    if (isset($_POST['source_id'])) {
        $sourceId = intval($_POST['source_id']);
    } else {
        $sourceId = intval($_GET['source_id']);
    }
    
    // Obtener los datos de la fuente
    $query = "SELECT * FROM fuente_datos WHERE id = ? AND tipo = 'google_sheets'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $sourceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception('No se encontró la fuente de datos especificada');
    }
    
    $dataSource = $result->fetch_assoc();
    $url = $dataSource['url'];
    
    // Verificar que la URL sea válida
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('La URL almacenada no es válida');
    }
    
    // Verificar que sea una URL de Google Sheets
    if (strpos($url, 'docs.google.com/spreadsheets') === false) {
        throw new Exception('La URL almacenada no es de Google Sheets');
    }
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    // Eliminar registros anteriores de fuente_datos (excepto el actual)
    $query = "DELETE FROM fuente_datos WHERE tipo = 'google_sheets' AND id != ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $sourceId);
    $stmt->execute();
    
    // Actualizar la fecha de actualización de la fuente existente
    $query = "UPDATE fuente_datos SET fecha_actualizacion = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $sourceId);
    $stmt->execute();
    
    // Importar los datos con las categorías actualizadas
    require_once 'import_sheets_data.php';
    
    // Redirigir con mensaje de éxito
    setFlashMessage('Datos actualizados correctamente con todas las categorías detalladas', 'success');
    
    // Redirigir según donde se originó la solicitud
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'test_sheets.php') !== false) {
        redirect('test_sheets.php');
    } else {
        redirect(SITE_URL . '/admin/index.php');
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Redirigir con mensaje de error
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    
    // Redirigir según donde se originó la solicitud
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'test_sheets.php') !== false) {
        redirect('test_sheets.php');
    } else {
        redirect(SITE_URL . '/admin/index.php');
    }
} 
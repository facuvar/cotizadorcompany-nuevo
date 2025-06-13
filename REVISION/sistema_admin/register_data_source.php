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
    
    // Validar URL de Google Sheets
    if (!isset($_POST['url']) || empty(trim($_POST['url']))) {
        throw new Exception("La URL de Google Sheets es obligatoria");
    }
    
    $url = trim($_POST['url']);
    
    // Verificar si es una URL válida
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception("La URL proporcionada no es válida");
    }
    
    // Verificar si es una URL de Google Sheets
    if (strpos($url, 'docs.google.com/spreadsheets') === false) {
        throw new Exception("La URL debe ser de Google Sheets (docs.google.com/spreadsheets)");
    }
    
    // Extraer ID del documento
    $pattern = '/spreadsheets\/d\/([a-zA-Z0-9-_]+)/';
    if (!preg_match($pattern, $url, $matches)) {
        throw new Exception("No se pudo extraer el ID del documento de Google Sheets desde la URL proporcionada.");
    }
    
    $documentId = $matches[1];
    
    // Construir URL completa si es necesario
    $fullUrl = "https://docs.google.com/spreadsheets/d/$documentId/edit";
    
    // Conexión a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si la URL ya existe
    $query = "SELECT * FROM fuente_datos WHERE url = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $fullUrl);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // La URL ya existe, actualizar la fecha
        $fuenteDatos = $result->fetch_assoc();
        
        // Buscar el nombre de la columna de fecha
        $dateColumnName = null;
        $result = $conn->query("SHOW COLUMNS FROM fuente_datos");
        while ($row = $result->fetch_assoc()) {
            if (strpos(strtolower($row['Field']), 'fecha') !== false) {
                $dateColumnName = $row['Field'];
                break;
            }
        }
        
        if ($dateColumnName) {
            $query = "UPDATE fuente_datos SET $dateColumnName = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $fuenteDatos['id']);
            $stmt->execute();
            
            setFlashMessage("La fuente de datos ya existía y se ha actualizado su fecha", "info");
        } else {
            setFlashMessage("La fuente de datos ya existe en la base de datos", "warning");
        }
    } else {
        // La URL no existe, insertarla
        $query = "INSERT INTO fuente_datos (url, tipo) VALUES (?, 'google_sheets')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $fullUrl);
        $stmt->execute();
        
        setFlashMessage("La fuente de datos se ha registrado correctamente", "success");
    }
    
    // Redirigir al panel de administración
    header("Location: index.php");
    exit;
    
} catch (Exception $e) {
    // Manejar error
    setFlashMessage("Error: " . $e->getMessage(), "danger");
    header("Location: create_db_structure.php");
    exit;
} 
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

try {
    // Iniciar la base de datos y conexión
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Obtener la última fuente de datos de Google Sheets
    $query = "SELECT * FROM fuente_datos ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        throw new Exception("No hay fuentes de datos registradas. Por favor, conecte primero un archivo de Google Sheets.");
    }
    
    $fuente = $result->fetch_assoc();
    $url = $fuente['url'];
    
    // Extraer el ID del documento de Google Sheets
    $pattern = '/spreadsheets\/d\/([a-zA-Z0-9-_]+)/';
    if (preg_match($pattern, $url, $matches)) {
        $documentId = $matches[1];
    } else {
        throw new Exception("No se pudo extraer el ID del documento de la URL proporcionada.");
    }
    
    // Construir URL de exportación (formato XLSX)
    $exportUrl = "https://docs.google.com/spreadsheets/d/{$documentId}/export?format=xlsx";
    
    // Crear directorio temporal si no existe
    $tempDir = '../temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // Nombre del archivo temporal
    $tempFile = $tempDir . '/plazos_entrega_' . uniqid() . '.xlsx';
    
    // Descargar el archivo
    $fileContent = file_get_contents($exportUrl);
    if ($fileContent === false) {
        throw new Exception("No se pudo descargar el archivo de Google Sheets.");
    }
    file_put_contents($tempFile, $fileContent);
    
    // Cargar el archivo con PhpSpreadsheet
    require_once '../../vendor/autoload.php';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
    
    // Buscar plazos de entrega en la primera hoja (asumimos que está ahí)
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    
    // Crear la tabla de plazos_entrega si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS plazos_entrega (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL,
        descripcion TEXT,
        orden INT DEFAULT 0
    )");
    
    // Limpiar plazos existentes
    $conn->query("DELETE FROM plazos_entrega");
    
    // Buscar sección "PLAZOS DE ENTREGA" en la hoja
    $plazosStartRow = null;
    $plazosEndRow = null;
    
    for ($row = 1; $row <= $highestRow; $row++) {
        $cellValue = $sheet->getCell('A' . $row)->getValue();
        
        if ($cellValue !== null) {
            $cellValue = trim((string)$cellValue);
            
            if (strtoupper($cellValue) === 'PLAZOS DE ENTREGA') {
                $plazosStartRow = $row + 1; // Empieza en la siguiente fila
            } elseif ($plazosStartRow !== null && empty($cellValue)) {
                $plazosEndRow = $row - 1;
                break;
            }
        }
    }
    
    if ($plazosStartRow === null) {
        // Si no se encuentra la sección específica, usaremos plazos predeterminados
        $plazos = [
            ['nombre' => '160-180 días', 'descripcion' => 'Entrega en 160-180 días', 'orden' => 1],
            ['nombre' => '90 días', 'descripcion' => 'Entrega en 90 días', 'orden' => 2],
            ['nombre' => '270 días', 'descripcion' => 'Entrega en 270 días', 'orden' => 3]
        ];
    } else {
        // Procesar los plazos encontrados en la hoja
        $plazos = [];
        $orden = 1;
        
        for ($row = $plazosStartRow; $row <= $plazosEndRow; $row++) {
            $nombre = trim((string)$sheet->getCell('A' . $row)->getValue());
            $descripcion = trim((string)$sheet->getCell('B' . $row)->getValue());
            
            if (!empty($nombre)) {
                $plazos[] = [
                    'nombre' => $nombre,
                    'descripcion' => !empty($descripcion) ? $descripcion : "Entrega en {$nombre}",
                    'orden' => $orden++
                ];
            }
        }
        
        // Si no se encontraron plazos en la hoja, usar los predeterminados
        if (empty($plazos)) {
            $plazos = [
                ['nombre' => '160-180 días', 'descripcion' => 'Entrega en 160-180 días', 'orden' => 1],
                ['nombre' => '90 días', 'descripcion' => 'Entrega en 90 días', 'orden' => 2],
                ['nombre' => '270 días', 'descripcion' => 'Entrega en 270 días', 'orden' => 3]
            ];
        }
    }
    
    // Insertar plazos de entrega
    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, descripcion, orden) VALUES (?, ?, ?)");
    $plazosImportados = 0;
    
    foreach ($plazos as $plazo) {
        $stmt->bind_param('ssi', $plazo['nombre'], $plazo['descripcion'], $plazo['orden']);
        $stmt->execute();
        $plazosImportados++;
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Limpiar archivo temporal
    unlink($tempFile);
    
    // Establecer mensaje de éxito
    setFlashMessage('success', "Se importaron exitosamente {$plazosImportados} plazos de entrega.");
    
    // Redirigir a la página de administración
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Establecer mensaje de error
    setFlashMessage('error', "Error al importar plazos de entrega: " . $e->getMessage());
    
    // Redirigir a la página de administración
    header('Location: index.php');
    exit;
} 
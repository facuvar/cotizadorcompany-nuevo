<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Prueba de Importación de Google Sheets</h2>";
    
    // Obtener la URL de Google Sheets
    $query = "SELECT * FROM fuente_datos WHERE tipo = 'google_sheets' ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception('No se encontró la configuración de Google Sheets');
    }
    
    $dataSource = $result->fetch_assoc();
    $url = $dataSource['url'];
    
    echo "<p>URL encontrada: " . htmlspecialchars($url) . "</p>";
    
    // Extraer el ID del documento
    if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
        $docId = $matches[1];
        echo "<p>ID del documento: " . htmlspecialchars($docId) . "</p>";
    } else {
        throw new Exception('No se pudo extraer el ID del documento de la URL');
    }
    
    // Construir la URL de exportación
    $exportUrl = "https://docs.google.com/spreadsheets/d/{$docId}/export?format=xlsx";
    echo "<p>URL de exportación: " . htmlspecialchars($exportUrl) . "</p>";
    
    // Configurar el contexto HTTP
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 30,
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]
    ]);
    
    // Intentar descargar el archivo
    echo "<p>Intentando descargar el archivo...</p>";
    $tempFile = tempnam(sys_get_temp_dir(), 'sheets_');
    $fileContent = @file_get_contents($exportUrl, false, $context);
    
    if ($fileContent === false) {
        $error = error_get_last();
        throw new Exception('Error al descargar el archivo: ' . ($error['message'] ?? 'Error desconocido'));
    }
    
    file_put_contents($tempFile, $fileContent);
    echo "<p>Archivo descargado correctamente en: " . htmlspecialchars($tempFile) . "</p>";
    
    // Intentar cargar el archivo con PhpSpreadsheet
    echo "<p>Intentando cargar el archivo con PhpSpreadsheet...</p>";
    $spreadsheet = IOFactory::load($tempFile);
    
    // Mostrar información de las hojas
    echo "<h3>Hojas encontradas:</h3>";
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        echo "<p>- " . htmlspecialchars($sheetName) . "</p>";
    }
    
    // Limpiar archivo temporal
    unlink($tempFile);
    echo "<p>Archivo temporal eliminado.</p>";
    
    echo "<p style='color: green;'>¡Prueba completada con éxito!</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
        echo "<p>Archivo temporal eliminado.</p>";
    }
} 
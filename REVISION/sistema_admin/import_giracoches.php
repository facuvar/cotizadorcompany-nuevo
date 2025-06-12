<?php
session_start();
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../../vendor/autoload.php';

// Verificar si el usuario es administrador
requireAdmin();

// Función para extraer días de un texto de plazo
function extractDaysFromPlazo($plazo) {
    $dias = 0;
    
    // Buscar números en el string
    if (preg_match('/(\d+)/', $plazo, $matches)) {
        $dias = (int)$matches[1];
    }
    
    return $dias;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Obtener la fuente de datos más reciente
    $query = "SELECT * FROM fuente_datos ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception("No se ha encontrado ninguna fuente de datos registrada.");
    }
    
    $fuenteDatos = $result->fetch_assoc();
    $urlGoogleSheets = $fuenteDatos['url'];
    
    // Extraer ID del documento de Google Sheets
    $pattern = '/spreadsheets\/d\/([a-zA-Z0-9-_]+)/';
    if (!preg_match($pattern, $urlGoogleSheets, $matches)) {
        throw new Exception("No se pudo extraer el ID del documento de Google Sheets desde la URL proporcionada.");
    }
    
    $documentId = $matches[1];
    
    // Construir URL para exportar como XLSX
    $exportUrl = "https://docs.google.com/spreadsheets/d/{$documentId}/export?format=xlsx";
    
    // Registrar la URL que estamos intentando acceder
    error_log("Intentando descargar: " . $exportUrl);
    
    // Directorio para archivos temporales
    $tempDir = '../temp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Archivo temporal para guardar el XLSX
    $tempFile = $tempDir . '/' . uniqid('import_giracoches_') . '.xlsx';
    
    // Usar cURL en lugar de file_get_contents para mejor control y depuración
    $ch = curl_init($exportUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Deshabilitar verificación SSL si es necesario
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Deshabilitar verificación SSL si es necesario
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Aumentar tiempo de espera a 60 segundos
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36');
    
    $fileData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    if ($fileData === false || $httpCode !== 200) {
        throw new Exception("Error al descargar el archivo: " . ($error ? $error : "HTTP Code: $httpCode") . " - URL: $exportUrl");
    }
    
    curl_close($ch);
    
    // Guardar el archivo
    file_put_contents($tempFile, $fileData);
    
    if (!file_exists($tempFile) || filesize($tempFile) == 0) {
        throw new Exception("Error: El archivo descargado está vacío o no se pudo guardar correctamente.");
    }
    
    error_log("Archivo descargado correctamente: " . $tempFile . " (Tamaño: " . filesize($tempFile) . " bytes)");
    
    // Cargar el archivo usando PhpSpreadsheet
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true); // Leer valores calculados, no fórmulas
    $spreadsheet = $reader->load($tempFile);
    
    // Obtener la primera hoja
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    
    // Buscar la sección de GIRACOCHES
    $foundGiracoches = false;
    $giracochesStartRow = 0;
    $giracochesEndRow = 0;
    $columnasPrecios = [];
    
    for ($row = 1; $row <= $highestRow; $row++) {
        $cellValue = $sheet->getCell('A' . $row)->getValue();
        
        if (is_string($cellValue) && strpos(strtoupper($cellValue), 'GIRACOCHES') !== false) {
            $foundGiracoches = true;
            $giracochesStartRow = $row;
            
            // Buscar columnas de precios en esta fila
            $maxCol = $sheet->getHighestColumn();
            $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);
            
            for ($colIndex = 1; $colIndex <= $maxColIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $headerValue = trim((string)$sheet->getCell($colLetter . $row)->getValue());
                
                if (strpos($headerValue, '160/180') !== false) {
                    $columnasPrecios['160/180 dias'] = $colLetter;
                } elseif (strpos($headerValue, '90') !== false && strpos($headerValue, 'dias') !== false) {
                    $columnasPrecios['90 dias'] = $colLetter;
                } elseif (strpos($headerValue, '270') !== false) {
                    $columnasPrecios['270 dias'] = $colLetter;
                }
            }
            
            // Buscar el fin de la sección
            for ($endRow = $giracochesStartRow + 1; $endRow <= $highestRow; $endRow++) {
                $endCellValue = trim((string)$sheet->getCell('A' . $endRow)->getValue());
                
                // Si encontramos una celda vacía seguida de otra celda vacía o una nueva categoría
                if ((empty($endCellValue) && empty($sheet->getCell('A' . ($endRow + 1))->getValue())) || 
                    (is_string($endCellValue) && 
                     strtoupper($endCellValue) === $endCellValue && 
                     !empty($endCellValue) &&
                     strpos($endCellValue, 'ESTRUCTURA') === false &&
                     $endCellValue !== 'ESTRUCTURA' &&  // Ignorar esta fila específica
                     strpos($endCellValue, 'GIRACOCHES') === false)) {
                    $giracochesEndRow = $endRow - 1;
                    break;
                }
            }
            
            if ($giracochesEndRow === 0) {
                $giracochesEndRow = $highestRow;
            }
            
            break;
        }
    }
    
    if (!$foundGiracoches) {
        throw new Exception("No se encontró la sección de GIRACOCHES en la hoja de cálculo.");
    }
    
    // Si no se encontraron todas las columnas de precios, usar valores predeterminados
    if (!isset($columnasPrecios['160/180 dias'])) {
        $columnasPrecios['160/180 dias'] = 'G'; // Ajustar según los datos reales
    }
    if (!isset($columnasPrecios['90 dias'])) {
        $columnasPrecios['90 dias'] = 'H'; // Ajustar según los datos reales
    }
    if (!isset($columnasPrecios['270 dias'])) {
        $columnasPrecios['270 dias'] = 'I'; // Ajustar según los datos reales
    }
    
    // Verificar si existe la categoría GIRACOCHES
    $categoriaQuery = "SELECT id FROM categorias WHERE nombre = 'GIRACOCHES' LIMIT 1";
    $categoriaResult = $conn->query($categoriaQuery);
    
    if ($categoriaResult && $categoriaResult->num_rows > 0) {
        $categoria = $categoriaResult->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        // Eliminar opciones existentes para esta categoría
        $deleteOpcionesQuery = "DELETE FROM opciones WHERE categoria_id = ?";
        $stmt = $conn->prepare($deleteOpcionesQuery);
        $stmt->bind_param('i', $categoriaId);
        $stmt->execute();
        $stmt->close();
    } else {
        // Crear la categoría GIRACOCHES si no existe
        $insertCategoriaQuery = "INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertCategoriaQuery);
        $descripcion = "Plataformas giratorias para vehículos";
        $orden = 10; // Ajustar según sea necesario
        $stmt->bind_param('ssi', $nombreCategoria, $descripcion, $orden);
        $nombreCategoria = 'GIRACOCHES';
        $stmt->execute();
        $categoriaId = $conn->insert_id;
        $stmt->close();
    }
    
    // Recopilar modelos y precios
    $modelos = [];
    $contadorModelos = 0;
    
    for ($row = $giracochesStartRow + 1; $row <= $giracochesEndRow; $row++) {
        $nombre = trim((string)$sheet->getCell('A' . $row)->getValue());
        
        // Ignorar filas vacías o que coincidan con la palabra "ESTRUCTURA" sola
        if (empty($nombre) || $nombre === 'ESTRUCTURA') {
            continue;
        }
        
        $precios = [];
        foreach ($columnasPrecios as $plazo => $colLetter) {
            $valor = $sheet->getCell($colLetter . $row)->getCalculatedValue();
            
            // Asegurarse de que sea un número válido
            if (is_numeric($valor)) {
                $precios[$plazo] = (float)$valor;
            } else {
                $precios[$plazo] = 0;
            }
        }
        
        if (!empty($nombre) && count($precios) > 0) {
            $modelos[] = [
                'nombre' => $nombre,
                'precios' => $precios
            ];
            $contadorModelos++;
        }
    }
    
    // Insertar las opciones y sus precios
    foreach ($modelos as $index => $modelo) {
        // Insertar la opción
        $insertOpcionQuery = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertOpcionQuery);
        $precio = 0; // El precio base siempre será 0, ya que usamos opcion_precios
        $descripcion = '';
        $esObligatorio = 1;
        $orden = $index + 1;
        $stmt->bind_param('issdii', $categoriaId, $modelo['nombre'], $descripcion, $precio, $esObligatorio, $orden);
        $stmt->execute();
        $opcionId = $conn->insert_id;
        $stmt->close();
        
        // Insertar los precios para esta opción
        foreach ($modelo['precios'] as $plazo => $precio) {
            // Asegurarse de que $plazo sea una cadena de texto (nombre del plazo)
            $plazoEntrega = $plazo; // Ya viene como string (ej: "160/180 dias")
            
            // Mapa de IDs de plazos conocidos
            $plazosIdMap = [
                '90 dias' => 60,
                '160/180 dias' => 62,
                '270 dias' => 61
            ];
            
            // Usar el ID directamente si conocemos el mapeo
            if (isset($plazosIdMap[$plazoEntrega])) {
                $plazoId = $plazosIdMap[$plazoEntrega];
            } else {
            // Verificar si ya existe un plazo con ese nombre
            $queryPlazo = "SELECT id FROM plazos_entrega WHERE nombre = ?";
            $stmtPlazo = $conn->prepare($queryPlazo);
            $stmtPlazo->bind_param('s', $plazoEntrega);
            $stmtPlazo->execute();
            $resultPlazo = $stmtPlazo->get_result();
            
            if ($resultPlazo->num_rows > 0) {
                $plazoId = $resultPlazo->fetch_assoc()['id'];
            } else {
                // Crear el plazo si no existe
                $insertPlazo = "INSERT INTO plazos_entrega (nombre) VALUES (?)";
                $stmtInsertPlazo = $conn->prepare($insertPlazo);
                $stmtInsertPlazo->bind_param('s', $plazoEntrega);
                $stmtInsertPlazo->execute();
                $plazoId = $conn->insert_id;
                }
            }
            
            // Insertar el precio
            $insertPrecioQuery = "INSERT INTO opcion_precios (opcion_id, plazo_id, plazo_entrega, precio) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertPrecioQuery);
            $stmt->bind_param('iisd', $opcionId, $plazoId, $plazoEntrega, $precio);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Limpiar archivo temporal
    unlink($tempFile);
    
    // Establecer mensaje de éxito
    setFlashMessage('success', "Se importaron exitosamente {$contadorModelos} modelos de GIRACOCHES.");
    
    // Redirigir a la página de validación
    header('Location: validate_giracoches.php');
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Guardar detalles del error en un archivo de registro
    $errorLog = '../temp/error_giracoches_' . date('Y-m-d_H-i-s') . '.log';
    file_put_contents($errorLog, "Error al importar GIRACOCHES: " . $e->getMessage() . "\n\n" . $e->getTraceAsString());
    
    // Establecer mensaje de error
    setFlashMessage('error', "Error al importar GIRACOCHES: " . $e->getMessage());
    
    // Mostrar error detallado
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Importación</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
            .container { max-width: 1200px; margin: 0 auto; }
            .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; }
            .trace { background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow: auto; margin-top: 20px; }
            .btn { display: inline-block; padding: 8px 16px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            pre { white-space: pre-wrap; word-wrap: break-word; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Error al Importar GIRACOCHES</h1>
            
            <div class="error">
                <h2>Mensaje de Error:</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
            </div>
            
            <div class="trace">
                <h2>Detalles del Error:</h2>
                <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
            </div>
            
            <p>Se ha guardado un registro detallado en: ' . htmlspecialchars($errorLog) . '</p>
            
            <a href="debug_giracoches_import.php" class="btn">Ir a la página de depuración</a>
            <a href="index.php" class="btn" style="background-color: #6c757d;">Volver al Panel de Administración</a>
        </div>
    </body>
    </html>';
    exit;
} 
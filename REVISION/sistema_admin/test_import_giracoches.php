<?php
session_start();
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../../vendor/autoload.php';

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
    
    // Directorio para archivos temporales
    $tempDir = '../temp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Archivo temporal para guardar el XLSX
    $tempFile = $tempDir . '/' . uniqid('import_giracoches_') . '.xlsx';
    
    echo "<p>Descargando documento desde Google Sheets...</p>";
    
    // Descargar el archivo
    $fileData = file_get_contents($exportUrl);
    if ($fileData === false) {
        throw new Exception("No se pudo descargar el archivo desde Google Sheets.");
    }
    
    file_put_contents($tempFile, $fileData);
    
    echo "<p>Documento descargado correctamente.</p>";
    
    // Cargar el archivo usando PhpSpreadsheet
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true); // Leer valores calculados, no fórmulas
    $spreadsheet = $reader->load($tempFile);
    
    // Obtener la primera hoja
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    
    echo "<p>Buscando sección GIRACOCHES en la hoja de cálculo...</p>";
    
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
            
            echo "<p>Sección GIRACOCHES encontrada en la fila {$row}.</p>";
            
            // Buscar columnas de precios en esta fila
            $maxCol = $sheet->getHighestColumn();
            $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);
            
            for ($colIndex = 1; $colIndex <= $maxColIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $headerValue = trim((string)$sheet->getCell($colLetter . $row)->getValue());
                
                if (strpos($headerValue, '160/180') !== false) {
                    $columnasPrecios['160/180 dias'] = $colLetter;
                    echo "<p>Columna para 160/180 días encontrada: {$colLetter}</p>";
                } elseif (strpos($headerValue, '90') !== false && strpos($headerValue, 'dias') !== false) {
                    $columnasPrecios['90 dias'] = $colLetter;
                    echo "<p>Columna para 90 días encontrada: {$colLetter}</p>";
                } elseif (strpos($headerValue, '270') !== false) {
                    $columnasPrecios['270 dias'] = $colLetter;
                    echo "<p>Columna para 270 días encontrada: {$colLetter}</p>";
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
            
            echo "<p>Fin de sección GIRACOCHES encontrado en la fila {$giracochesEndRow}.</p>";
            
            break;
        }
    }
    
    if (!$foundGiracoches) {
        throw new Exception("No se encontró la sección de GIRACOCHES en la hoja de cálculo.");
    }
    
    // Si no se encontraron todas las columnas de precios, usar valores predeterminados
    if (!isset($columnasPrecios['160/180 dias'])) {
        $columnasPrecios['160/180 dias'] = 'G'; // Ajustar según los datos reales
        echo "<p>Usando columna predeterminada G para 160/180 días.</p>";
    }
    if (!isset($columnasPrecios['90 dias'])) {
        $columnasPrecios['90 dias'] = 'H'; // Ajustar según los datos reales
        echo "<p>Usando columna predeterminada H para 90 días.</p>";
    }
    if (!isset($columnasPrecios['270 dias'])) {
        $columnasPrecios['270 dias'] = 'I'; // Ajustar según los datos reales
        echo "<p>Usando columna predeterminada I para 270 días.</p>";
    }
    
    echo "<p>Verificando categoría GIRACOCHES en la base de datos...</p>";
    
    // Verificar si existe la categoría GIRACOCHES
    $categoriaQuery = "SELECT id FROM categorias WHERE nombre = 'GIRACOCHES' LIMIT 1";
    $categoriaResult = $conn->query($categoriaQuery);
    
    if ($categoriaResult && $categoriaResult->num_rows > 0) {
        $categoria = $categoriaResult->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        echo "<p>Categoría GIRACOCHES encontrada con ID {$categoriaId}.</p>";
        
        // Eliminar opciones existentes para esta categoría
        $deleteOpcionesQuery = "DELETE FROM opciones WHERE categoria_id = ?";
        $stmt = $conn->prepare($deleteOpcionesQuery);
        $stmt->bind_param('i', $categoriaId);
        $stmt->execute();
        $stmt->close();
        
        echo "<p>Opciones existentes eliminadas para la categoría.</p>";
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
        
        echo "<p>Categoría GIRACOCHES creada con ID {$categoriaId}.</p>";
    }
    
    echo "<p>Recopilando modelos y precios...</p>";
    
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
    
    echo "<p>Se encontraron {$contadorModelos} modelos de GIRACOCHES.</p>";
    
    echo "<h3>Listado de modelos:</h3>";
    echo "<ul>";
    foreach ($modelos as $modelo) {
        echo "<li>{$modelo['nombre']} - Precios: ";
        foreach ($modelo['precios'] as $plazo => $precio) {
            echo "{$plazo}: {$precio} | ";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    echo "<p>Insertando opciones y precios en la base de datos...</p>";
    
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
        
        echo "<p>Opción '{$modelo['nombre']}' insertada con ID {$opcionId}.</p>";
        
        // Insertar los precios para esta opción
        foreach ($modelo['precios'] as $plazo => $precio) {
            // Asegurarse de que $plazo sea una cadena de texto (nombre del plazo)
            $plazoEntrega = $plazo; // Ya viene como string (ej: "160/180 dias")
            
            // Verificar si ya existe un plazo con ese nombre
            $queryPlazo = "SELECT id FROM plazos_entrega WHERE nombre = ?";
            $stmtPlazo = $conn->prepare($queryPlazo);
            $stmtPlazo->bind_param('s', $plazoEntrega);
            $stmtPlazo->execute();
            $resultPlazo = $stmtPlazo->get_result();
            
            if ($resultPlazo->num_rows > 0) {
                $plazoId = $resultPlazo->fetch_assoc()['id'];
                echo "<p>Plazo '{$plazoEntrega}' encontrado con ID {$plazoId}.</p>";
            } else {
                // Crear el plazo si no existe
                $insertPlazo = "INSERT INTO plazos_entrega (nombre) VALUES (?)";
                $stmtInsertPlazo = $conn->prepare($insertPlazo);
                $stmtInsertPlazo->bind_param('s', $plazoEntrega);
                $stmtInsertPlazo->execute();
                $plazoId = $conn->insert_id;
                echo "<p>Plazo '{$plazoEntrega}' creado con ID {$plazoId}.</p>";
            }
            
            // Insertar el precio
            $insertPrecioQuery = "INSERT INTO opcion_precios (opcion_id, plazo_id, plazo_entrega, precio) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertPrecioQuery);
            $stmt->bind_param('iisd', $opcionId, $plazoId, $plazoEntrega, $precio);
            $stmt->execute();
            echo "<p>Precio para '{$modelo['nombre']}' con plazo '{$plazoEntrega}' insertado: {$precio}.</p>";
            $stmt->close();
        }
    }
    
    // Finalizar
    echo "<p><strong>TRANSACCIÓN COMPLETADA EXITOSAMENTE.</strong></p>";
    
    // Confirmar la transacción
    $conn->commit();
    
    // Limpiar archivo temporal
    if (file_exists($tempFile)) {
        unlink($tempFile);
        echo "<p>Archivo temporal eliminado.</p>";
    }
    
    echo "<p><a href='validate_giracoches.php'>Ir a la página de validación</a></p>";
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Mostrar detalles del error
    echo "<h1>Error al importar GIRACOCHES</h1>";
    echo "<p>Mensaje: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    
    // Guardar detalles del error en un archivo de registro
    $errorLog = '../temp/error_giracoches_' . date('Y-m-d_H-i-s') . '.log';
    file_put_contents($errorLog, "Error al importar GIRACOCHES: " . $e->getMessage() . "\n\n" . $e->getTraceAsString());
    echo "<p>Se ha guardado un registro detallado en: " . $errorLog . "</p>";
    
    echo "<p><a href='debug_giracoches_import.php'>Ir a la página de depuración</a></p>";
} 
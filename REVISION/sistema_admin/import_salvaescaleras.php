<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../../vendor/autoload.php';

// Verificar si el usuario es administrador
requireAdmin();

// Función para extraer los días de un plazo de entrega
function extractDaysFromPlazo($plazoText) {
    // Normalizar formato
    $plazoText = strtolower(trim($plazoText));
    
    // Buscar números en el texto
    preg_match('/(\d+)/', $plazoText, $matches);
    
    if (isset($matches[1])) {
        return (int)$matches[1];
    }
    
    // Valores por defecto si no se puede extraer
    if (strpos($plazoText, 'inmediato') !== false || strpos($plazoText, 'inmediata') !== false) {
        return 30; // Inmediato = 30 días
    } elseif (strpos($plazoText, 'normal') !== false) {
        return 120; // Normal = 120 días
    } elseif (strpos($plazoText, 'urgente') !== false) {
        return 60; // Urgente = 60 días
    }
    
    // Si no se puede determinar, devolver 180 días como valor predeterminado
    return 180;
}

try {
    // Iniciar una transacción para asegurar la integridad de los datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $conn->begin_transaction();
    
    // Primero verificamos la estructura de la tabla para encontrar el nombre correcto de la columna de fecha
    $query = "SHOW COLUMNS FROM fuente_datos";
    $result = $conn->query($query);
    
    $fechaColumnName = 'fecha_creacion'; // Valor predeterminado
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Buscar columnas que puedan representar la fecha (fecha_actualizacion, fecha_creacion, etc.)
            if (strpos(strtolower($row['Field']), 'fecha') !== false) {
                $fechaColumnName = $row['Field'];
                break;
            }
        }
    }
    
    // Obtener la URL de Google Sheets más reciente
    $query = "SELECT * FROM fuente_datos ORDER BY {$fechaColumnName} DESC LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta SQL: " . $conn->error);
    }
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontró ninguna fuente de datos registrada en la base de datos.");
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
    $tempFile = $tempDir . '/' . uniqid('sheets_') . '.xlsx';
    
    // Descargar el archivo
    $fileData = file_get_contents($exportUrl);
    if ($fileData === false) {
        throw new Exception("No se pudo descargar el archivo desde Google Sheets.");
    }
    
    $bytesWritten = file_put_contents($tempFile, $fileData);
    
    if ($bytesWritten === false) {
        throw new Exception("No se pudo guardar el archivo descargado.");
    }
    
    // Cargar el archivo usando PhpSpreadsheet
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $spreadsheet = $reader->load($tempFile);
    
    // Obtener la primera hoja (asumimos que los datos están ahí)
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    
    // Buscar la sección de SALVAESCALERAS
    $foundSalvaescaleras = false;
    $salvaescalerasStartRow = 0;
    $salvaescalerasEndRow = 0;
    
    for ($row = 1; $row <= $highestRow; $row++) {
        $cellValue = $sheet->getCell('A' . $row)->getValue();
        
        if (is_string($cellValue) && strpos(strtoupper($cellValue), 'SALVAESCALERAS') !== false) {
            $foundSalvaescaleras = true;
            $salvaescalerasStartRow = $row;
            
            // Buscar el fin de la sección
            for ($endRow = $salvaescalerasStartRow + 1; $endRow <= $highestRow; $endRow++) {
                $endCellValue = $sheet->getCell('A' . $endRow)->getValue();
                
                // Si encontramos una celda vacía o otra categoría (mayúsculas y no representa un modelo)
                if (empty($endCellValue) || 
                   (is_string($endCellValue) && 
                    strtoupper($endCellValue) === $endCellValue && 
                    strpos($endCellValue, 'SALVA') === false && 
                    !empty($endCellValue))) {
                    $salvaescalerasEndRow = $endRow - 1;
                    break;
                }
            }
            
            if ($salvaescalerasEndRow === 0) {
                $salvaescalerasEndRow = $highestRow;
            }
            
            break;
        }
    }
    
    if (!$foundSalvaescaleras) {
        throw new Exception("No se encontró la sección de SALVAESCALERAS en la hoja de cálculo.");
    }
    
    // Analizar las columnas para encontrar los plazos
    $plazoColumns = [];
    $modelColumn = null;
    
    // Encontrar la columna de modelos y plazos
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $headerValue = $sheet->getCell($col . $salvaescalerasStartRow)->getValue();
        
        if (!empty($headerValue)) {
            if (strtoupper($headerValue) === 'SALVAESCALERAS' || 
                strtoupper($headerValue) === 'MODELO' || 
                strtoupper($headerValue) === 'SALVAESCALERA') {
                $modelColumn = $col;
            } elseif (strpos(strtolower($headerValue), 'entreg') !== false || 
                      strpos(strtolower($headerValue), 'plazo') !== false) {
                // Esta columna contiene información sobre plazos de entrega
                $plazoColumns[] = [
                    'column' => $col,
                    'dias' => extractDaysFromPlazo($headerValue)
                ];
            }
        }
    }
    
    if (empty($plazoColumns)) {
        throw new Exception("No se pudieron encontrar columnas de plazos de entrega en la hoja de cálculo.");
    }
    
    if ($modelColumn === null) {
        // Si no se encontró una columna específica para modelos, asumir que es la columna A
        $modelColumn = 'A';
    }
    
    // Verificar si la categoría SALVAESCALERAS existe en la base de datos
    $query = "SELECT * FROM categorias WHERE nombre = 'SALVAESCALERAS'";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        // Crear la categoría si no existe
        $query = "INSERT INTO categorias (nombre, descripcion) VALUES ('SALVAESCALERAS', 'Modelos de salvaescaleras')";
        $conn->query($query);
        $categoriaId = $conn->insert_id;
    } else {
        $categoria = $result->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        // Eliminar opciones existentes para esta categoría
        $query = "SELECT id FROM opciones WHERE categoria_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $categoriaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $opcionIds = [];
        while ($row = $result->fetch_assoc()) {
            $opcionIds[] = $row['id'];
        }
        
        if (!empty($opcionIds)) {
            // Eliminar precios para estas opciones
            $placeholders = implode(',', array_fill(0, count($opcionIds), '?'));
            $query = "DELETE FROM precios WHERE opcion_id IN ($placeholders)";
            $stmt = $conn->prepare($query);
            
            $types = str_repeat('i', count($opcionIds));
            $stmt->bind_param($types, ...$opcionIds);
            $stmt->execute();
            
            // Ahora eliminar las opciones
            $query = "DELETE FROM opciones WHERE categoria_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $categoriaId);
            $stmt->execute();
        }
    }
    
    // Recolectar modelos y sus precios
    $modelos = [];
    
    for ($row = $salvaescalerasStartRow + 1; $row <= $salvaescalerasEndRow; $row++) {
        $modelName = $sheet->getCell($modelColumn . $row)->getValue();
        
        // Verificar si el nombre del modelo es válido (no vacío y no es otro encabezado)
        if (!empty($modelName) && 
            is_string($modelName) && 
            strtoupper($modelName) !== $modelName) {
            
            $modelPrices = [];
            $validPrices = false;
            
            foreach ($plazoColumns as $plazoInfo) {
                $precio = $sheet->getCell($plazoInfo['column'] . $row)->getValue();
                
                // Intentar convertir a número
                if (!is_numeric($precio)) {
                    // Intentar extraer un número de una cadena como "€12,345.67"
                    $precio = preg_replace('/[^0-9,.]/', '', $precio);
                    $precio = str_replace(',', '.', $precio);
                }
                
                if (is_numeric($precio) && $precio > 0) {
                    $modelPrices[] = [
                        'plazo_dias' => $plazoInfo['dias'],
                        'precio' => (float)$precio
                    ];
                    $validPrices = true;
                }
            }
            
            if ($validPrices) {
                $modelos[$modelName] = $modelPrices;
            }
        }
    }
    
    if (empty($modelos)) {
        throw new Exception("No se pudieron extraer modelos y precios válidos de la hoja de cálculo.");
    }
    
    // Insertar opciones y precios en la base de datos
    foreach ($modelos as $modelName => $prices) {
        // Insertar opción
        $query = "INSERT INTO opciones (categoria_id, nombre) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $categoriaId, $modelName);
        $stmt->execute();
        
        $opcionId = $conn->insert_id;
        
        // Insertar precios para esta opción
        foreach ($prices as $priceInfo) {
            $query = "INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isd", $opcionId, $priceInfo['plazo_dias'], $priceInfo['precio']);
            $stmt->execute();
        }
    }
    
    // Limpiar archivo temporal
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Confirmar la transacción
    $conn->commit();
    
    // Establecer mensaje de éxito
    setFlashMessage('success', 'Se importaron ' . count($modelos) . ' modelos de SALVAESCALERAS con éxito.');
    
    // Redirigir a la página de validación
    header('Location: validate_salvaescaleras.php');
    exit;
    
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Establecer mensaje de error
    setFlashMessage('error', 'Error al importar datos de SALVAESCALERAS: ' . $e->getMessage());
    
    // Redirigir a la página de administración
    header('Location: index.php');
    exit;
} 
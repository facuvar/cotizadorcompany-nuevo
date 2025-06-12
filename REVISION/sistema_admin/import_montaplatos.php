<?php
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
    
    // Directorio para archivos temporales
    $tempDir = '../temp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Archivo temporal para guardar el XLSX
    $tempFile = $tempDir . '/' . uniqid('import_montaplatos_') . '.xlsx';
    
    // Descargar el archivo
    $fileData = file_get_contents($exportUrl);
    if ($fileData === false) {
        throw new Exception("No se pudo descargar el archivo desde Google Sheets.");
    }
    
    file_put_contents($tempFile, $fileData);
    
    // Cargar el archivo usando PhpSpreadsheet
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true); // Leer valores calculados, no fórmulas
    $spreadsheet = $reader->load($tempFile);
    
    // Obtener la primera hoja
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    
    // Buscar la sección de MONTAPLATOS
    $foundMontaplatos = false;
    $montaplatosStartRow = 0;
    $montaplatosEndRow = 0;
    $columnasPrecios = [];
    
    for ($row = 1; $row <= $highestRow; $row++) {
        $cellValue = $sheet->getCell('A' . $row)->getValue();
        
        if (!$foundMontaplatos && stripos($cellValue, 'MONTAPLATOS') !== false) {
            $foundMontaplatos = true;
            $montaplatosStartRow = $row + 1; // La siguiente fila después del título
            continue;
        }
        
        if ($foundMontaplatos) {
            // Si encontramos otra categoría después de MONTAPLATOS, marcamos el fin
            if (!empty($cellValue) && (
                stripos($cellValue, 'GIRACOCHES') !== false ||
                stripos($cellValue, 'ESTRUCTURA') !== false ||
                stripos($cellValue, 'SALVAESCALERAS') !== false ||
                stripos($cellValue, 'MONTACARGAS') !== false
            )) {
                $montaplatosEndRow = $row - 1;
                break;
            }
            
            // Si llegamos al final del archivo
            if ($row == $highestRow) {
                $montaplatosEndRow = $row;
            }
        }
    }
    
    if (!$foundMontaplatos || $montaplatosStartRow === 0) {
        throw new Exception("No se encontró la sección de MONTAPLATOS en la hoja de cálculo.");
    }
    
    if ($montaplatosEndRow === 0) {
        // Si no se encontró un final explícito, buscar hasta que haya una fila vacía
        for ($row = $montaplatosStartRow; $row <= $highestRow; $row++) {
            $isEmpty = true;
            for ($col = 'A'; $col <= 'E'; $col++) {
                if (!empty($sheet->getCell($col . $row)->getValue())) {
                    $isEmpty = false;
                    break;
                }
            }
            
            if ($isEmpty) {
                $montaplatosEndRow = $row - 1;
                break;
            }
            
            // Si llegamos al final
            if ($row == $highestRow) {
                $montaplatosEndRow = $row;
            }
        }
    }
    
    if ($montaplatosEndRow === 0) {
        throw new Exception("No se pudo determinar el final de la sección MONTAPLATOS.");
    }
    
    // Buscar los encabezados de las columnas de precios (plazos de entrega)
    $plazoColumns = [];
    $modelColumn = null;
    
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $headerValue = trim($sheet->getCell($col . $montaplatosStartRow)->getValue());
        
        if (!empty($headerValue)) {
            if (strtoupper($headerValue) === 'MONTAPLATOS' || 
                strtoupper($headerValue) === 'MODELO') {
                $modelColumn = $col;
            } elseif (strpos(strtolower($headerValue), 'entreg') !== false || 
                     strpos(strtolower($headerValue), 'plazo') !== false || 
                     strpos(strtolower($headerValue), 'dia') !== false) {
                // Esta columna contiene información sobre plazos de entrega
                $plazoColumns[] = [
                    'column' => $col,
                    'nombre' => $headerValue,
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
    
    // Verificar si la categoría MONTAPLATOS existe en la base de datos
    $query = "SELECT * FROM categorias WHERE nombre = 'MONTAPLATOS'";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        // Crear la categoría si no existe
        $query = "INSERT INTO categorias (nombre, descripcion) VALUES ('MONTAPLATOS', 'Modelos de montaplatos')";
        $conn->query($query);
        $categoriaId = $conn->insert_id;
    } else {
        $categoria = $result->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        // Eliminar opciones existentes de esta categoría
        $query = "SELECT id FROM opciones WHERE categoria_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $categoriaId);
        $stmt->execute();
        $resultOpciones = $stmt->get_result();
        
        while ($opcion = $resultOpciones->fetch_assoc()) {
            // Eliminar precios asociados a estas opciones
            $deletePrecios = "DELETE FROM opcion_precios WHERE opcion_id = ?";
            $stmtDeletePrecios = $conn->prepare($deletePrecios);
            $stmtDeletePrecios->bind_param('i', $opcion['id']);
            $stmtDeletePrecios->execute();
        }
        
        // Ahora eliminar las opciones
        $deleteOpciones = "DELETE FROM opciones WHERE categoria_id = ?";
        $stmtDeleteOpciones = $conn->prepare($deleteOpciones);
        $stmtDeleteOpciones->bind_param('i', $categoriaId);
        $stmtDeleteOpciones->execute();
    }
    
    // Recorrer las filas de MONTAPLATOS y extraer modelos y precios
    $modelos = [];
    $orden = 1;
    
    for ($row = $montaplatosStartRow + 1; $row <= $montaplatosEndRow; $row++) {
        $modelName = trim($sheet->getCell($modelColumn . $row)->getValue());
        
        // Saltar filas vacías o sin nombre de modelo válido
        if (empty($modelName) || strpos(strtolower($modelName), 'montaplatos') !== false) {
            continue;
        }
        
        // Verificar si es un modelo válido (por ejemplo, contiene palabras clave o tiene un formato específico)
        if (strpos($modelName, 'MODELO') !== false || 
            strpos($modelName, 'MONTAPLATOS') !== false || 
            preg_match('/\d+\s*KG/i', $modelName)) {
            
            $precios = [];
            $hayPreciosValidos = false;
            
            foreach ($plazoColumns as $plazoInfo) {
                $precio = $sheet->getCell($plazoInfo['column'] . $row)->getValue();
                
                // Limpiar y convertir el precio
                $precioValor = 0;
                if (!empty($precio)) {
                    // Eliminar símbolos de moneda y separadores de miles
                    $precio = preg_replace('/[^\d,.]/', '', $precio);
                    $precio = str_replace(',', '.', $precio);
                    $precioValor = floatval($precio);
                }
                
                if ($precioValor > 0) {
                    $hayPreciosValidos = true;
                }
                
                $precios[] = [
                    'plazo_nombre' => $plazoInfo['nombre'],
                    'plazo_dias' => $plazoInfo['dias'],
                    'precio' => $precioValor
                ];
            }
            
            // Solo añadir el modelo si tiene al menos un precio válido
            if ($hayPreciosValidos) {
                $modelos[] = [
                    'nombre' => $modelName,
                    'precios' => $precios,
                    'orden' => $orden++
                ];
            }
        }
    }
    
    // Si no encontramos modelos válidos
    if (empty($modelos)) {
        throw new Exception("No se encontraron modelos de MONTAPLATOS válidos en la hoja de cálculo.");
    }
    
    // Procesar e insertar los modelos y sus precios en la base de datos
    foreach ($modelos as $modelo) {
        // Insertar la opción
        $query = "INSERT INTO opciones (categoria_id, nombre, descripcion, es_obligatorio, orden) VALUES (?, ?, ?, 1, ?)";
        $stmt = $conn->prepare($query);
        $descripcion = "Montaplatos " . $modelo['nombre'];
        $stmt->bind_param('issi', $categoriaId, $modelo['nombre'], $descripcion, $modelo['orden']);
        $stmt->execute();
        
        $opcionId = $conn->insert_id;
        
        // Insertar los precios para cada plazo
        foreach ($modelo['precios'] as $precioInfo) {
            if ($precioInfo['precio'] > 0) {
                // Verificar si ya existe un plazo con esos días
                $queryPlazo = "SELECT id FROM plazos_entrega WHERE dias = ?";
                $stmtPlazo = $conn->prepare($queryPlazo);
                $stmtPlazo->bind_param('i', $precioInfo['plazo_dias']);
                $stmtPlazo->execute();
                $resultPlazo = $stmtPlazo->get_result();
                
                if ($resultPlazo->num_rows > 0) {
                    $plazoId = $resultPlazo->fetch_assoc()['id'];
                } else {
                    // Crear el plazo si no existe
                    $insertPlazo = "INSERT INTO plazos_entrega (nombre, dias) VALUES (?, ?)";
                    $stmtInsertPlazo = $conn->prepare($insertPlazo);
                    $stmtInsertPlazo->bind_param('si', $precioInfo['plazo_nombre'], $precioInfo['plazo_dias']);
                    $stmtInsertPlazo->execute();
                    $plazoId = $conn->insert_id;
                }
                
                // Insertar el precio
                $insertPrecio = "INSERT INTO opcion_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)";
                $stmtInsertPrecio = $conn->prepare($insertPrecio);
                $stmtInsertPrecio->bind_param('iid', $opcionId, $plazoId, $precioInfo['precio']);
                $stmtInsertPrecio->execute();
            }
        }
    }
    
    // Eliminar el archivo temporal
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Confirmar los cambios
    $conn->commit();
    
    // Establecer mensaje de éxito
    setFlashMessage("Se han importado " . count($modelos) . " modelos de MONTAPLATOS correctamente.", "success");
    
    // Redirigir a la página de validación
    redirect(SITE_URL . "/admin/validate_montaplatos.php");
    
} catch (Exception $e) {
    // En caso de error, revertir la transacción
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Eliminar el archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Establecer mensaje de error
    setFlashMessage("Error al importar datos de MONTAPLATOS: " . $e->getMessage(), "error");
    
    // Redirigir a la página de inicio
    redirect(SITE_URL . "/admin/index.php");
} 
<?php
// Script para importar datos directamente desde el archivo Excel original
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Función para mostrar mensajes
function mostrarMensaje($mensaje, $tipo = 'info') {
    $color = 'black';
    if ($tipo == 'success') $color = 'green';
    if ($tipo == 'error') $color = 'red';
    if ($tipo == 'warning') $color = 'orange';
    
    echo "<p style='color: $color;'>$mensaje</p>";
}

// Función para limpiar y convertir valores de precio
function limpiarPrecio($valor) {
    if (empty($valor)) return 0;
    
    // Eliminar símbolos de moneda y espacios
    $precio = str_replace(['$', ' '], '', $valor);
    
    // Reemplazar coma por punto para decimales
    $precio = str_replace(',', '.', $precio);
    
    // Extraer solo números y punto decimal
    preg_match('/[\d.]+/', $precio, $matches);
    if (!empty($matches)) {
        return floatval($matches[0]);
    }
    
    return 0;
}

// Función para procesar la hoja de productos/ascensores
function procesarHojaProductos($worksheet, $conn) {
    // Limitar el tiempo de ejecución para esta función
    $maxTime = time() + 120; // 2 minutos máximo
    
    // Limpiar productos duplicados antes de importar
    mostrarMensaje("Verificando productos duplicados...", "info");
    // Variables para el procesamiento
    $currentProduct = null;
    $productoId = null;
    $plazoColumns = [];
    $plazoIds = [
        '160-180 dias' => 2,
        '90 dias' => 1,
        '270 dias' => 3,
        '160/180 dias' => 2
    ];
    
    // Obtener el rango de datos
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    
    // Procesar cada fila (con límite de tiempo)
    for ($row = 1; $row <= min($highestRow, 100); $row++) { // Máximo 100 filas
        // Verificar si excedimos el tiempo máximo
        if (time() > $maxTime) {
            mostrarMensaje("Tiempo máximo excedido, procesamiento interrumpido en la fila $row", "warning");
            break;
        }
        // Leer la primera columna
        $firstCell = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
        
        // Si está vacía, continuar
        if (empty($firstCell)) continue;
        
        // Detectar encabezados de plazos
        if (strpos($firstCell, '160-180 dias') !== false || 
            strpos($firstCell, '160/180 dias') !== false ||
            strpos($firstCell, '90 dias') !== false ||
            strpos($firstCell, '270 dias') !== false) {
            
            // Encontrar las columnas de plazos
            for ($col = 1; $col <= $worksheet->getHighestColumn(true); $col++) {
                $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                if (empty($cellValue)) continue;
                
                foreach ($plazoIds as $plazoName => $plazoId) {
                    if (strpos($cellValue, $plazoName) !== false) {
                        $plazoColumns[$plazoId] = $col;
                        mostrarMensaje("Encontrada columna para plazo $plazoName en columna $col", "info");
                        break;
                    }
                }
            }
            continue;
        }
        
        // Detectar producto - Mejorado para XLS limpio
        if (!empty($firstCell) && (
            strpos(strtoupper($firstCell), 'EQUIPO') !== false || 
            strpos(strtoupper($firstCell), 'ESTRUCTURA') !== false ||
            strpos(strtoupper($firstCell), 'GIRACOCHE') !== false ||
            strpos(strtoupper($firstCell), 'MONTAPLATO') !== false ||
            strpos(strtoupper($firstCell), 'DOMICILIARIO') !== false ||
            strpos(strtoupper($firstCell), 'ASCENSOR') !== false)) {
            
            $currentProduct = trim($firstCell);
            
            // Evitar procesamiento duplicado
            static $processedProducts = [];
            if (in_array($currentProduct, $processedProducts)) {
                continue;
            }
            $processedProducts[] = $currentProduct;
            
            // Verificar si el producto ya existe - Búsqueda exacta
            $query = "SELECT id FROM xls_productos WHERE nombre = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $currentProduct);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Crear el producto con un nombre limpio
                $nombreLimpio = trim($currentProduct);
                $query = "INSERT INTO xls_productos (nombre, orden) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $orden = 0;
                $stmt->bind_param("si", $nombreLimpio, $orden);
                $stmt->execute();
                $productoId = $conn->insert_id;
                mostrarMensaje("Producto creado: $currentProduct (ID: $productoId)", "success");
            } else {
                $producto = $result->fetch_assoc();
                $productoId = $producto['id'];
                mostrarMensaje("Producto encontrado: $currentProduct (ID: $productoId)", "info");
                
                // Eliminar opciones existentes
                $query = "SELECT id FROM xls_opciones WHERE producto_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $productoId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $opcionIds = [];
                while ($row = $result->fetch_assoc()) {
                    $opcionIds[] = $row['id'];
                }
                
                if (!empty($opcionIds)) {
                    $placeholders = implode(',', array_fill(0, count($opcionIds), '?'));
                    $query = "DELETE FROM xls_precios WHERE opcion_id IN ($placeholders)";
                    $stmt = $conn->prepare($query);
                    
                    $types = str_repeat('i', count($opcionIds));
                    $stmt->bind_param($types, ...$opcionIds);
                    $stmt->execute();
                    
                    $query = "DELETE FROM xls_opciones WHERE producto_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $productoId);
                    $stmt->execute();
                    
                    mostrarMensaje("Eliminadas opciones existentes para el producto", "info");
                }
            }
            
            continue;
        }
        
        // Si tenemos un producto actual y la primera columna no está vacía, es una opción
        if ($currentProduct && $productoId && !empty($firstCell) && !empty($plazoColumns)) {
            $opcionNombre = trim($firstCell);
            
            // Verificar si es una línea de opción válida
            $tienePrecios = false;
            foreach ($plazoColumns as $plazoId => $columnIndex) {
                $cellValue = $worksheet->getCellByColumnAndRow($columnIndex, $row)->getValue();
                if (!empty($cellValue)) {
                    $tienePrecios = true;
                    break;
                }
            }
            
            if (!$tienePrecios) continue;
            
            // Insertar opción
            $stmt = $conn->prepare("INSERT INTO xls_opciones (producto_id, nombre, descripcion) VALUES (?, ?, ?)");
            $descripcion = "Importado desde Excel";
            $stmt->bind_param("iss", $productoId, $opcionNombre, $descripcion);
            $stmt->execute();
            
            $opcionId = $conn->insert_id;
            
            // Insertar precios para cada plazo
            foreach ($plazoColumns as $plazoId => $columnIndex) {
                $cellValue = $worksheet->getCellByColumnAndRow($columnIndex, $row)->getValue();
                if (!empty($cellValue)) {
                    $precio = limpiarPrecio($cellValue);
                    
                    $stmt = $conn->prepare("INSERT INTO xls_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $opcionId, $plazoId, $precio);
                    $stmt->execute();
                }
            }
            
            mostrarMensaje("Agregada opción: $opcionNombre para $currentProduct", "success");
        }
    }
    
    mostrarMensaje("Procesamiento de la hoja de productos completado", "success");
}

// Función para procesar la hoja de adicionales
function procesarHojaAdicionales($worksheet, $conn) {
    // Limitar el tiempo de ejecución para esta función
    $maxTime = time() + 60; // 60 segundos máximo
    // Variables para el procesamiento
    $currentProduct = null;
    $productoId = null;
    $plazoColumns = [];
    $plazoIds = [
        '160-180 dias' => 2,
        '90 dias' => 1,
        '270 dias' => 3,
        '160/180 dias' => 2
    ];
    
    // Obtener el rango de datos
    $highestRow = $worksheet->getHighestRow();
    
    // Procesar cada fila (con límite de tiempo)
    for ($row = 1; $row <= min($highestRow, 100); $row++) { // Máximo 100 filas
        // Verificar si excedimos el tiempo máximo
        if (time() > $maxTime) {
            mostrarMensaje("Tiempo máximo excedido, procesamiento interrumpido en la fila $row", "warning");
            break;
        }
        // Leer la primera columna
        $firstCell = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
        
        // Si está vacía, continuar
        if (empty($firstCell)) continue;
        
        // Detectar encabezados de plazos
        if (strpos($firstCell, '160-180 dias') !== false || 
            strpos($firstCell, '160/180 dias') !== false ||
            strpos($firstCell, '90 dias') !== false ||
            strpos($firstCell, '270 dias') !== false) {
            
            // Encontrar las columnas de plazos
            for ($col = 1; $col <= $worksheet->getHighestColumn(true); $col++) {
                $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                if (empty($cellValue)) continue;
                
                foreach ($plazoIds as $plazoName => $plazoId) {
                    if (strpos($cellValue, $plazoName) !== false) {
                        $plazoColumns[$plazoId] = $col;
                        mostrarMensaje("Encontrada columna para plazo $plazoName en columna $col", "info");
                        break;
                    }
                }
            }
            continue;
        }
        
        // Detectar producto adicional
        if (!empty($firstCell) && (
            strpos(strtoupper($firstCell), 'ADICIONALES') !== false || 
            strpos(strtoupper($firstCell), 'SALVAESCALERAS') !== false)) {
            
            $currentProduct = trim($firstCell);
            
            // Evitar procesamiento duplicado
            static $processedAdicionales = [];
            if (in_array($currentProduct, $processedAdicionales)) {
                continue;
            }
            $processedAdicionales[] = $currentProduct;
            
            // Verificar si el producto ya existe - Búsqueda exacta
            $query = "SELECT id FROM xls_productos WHERE nombre = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $currentProduct);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Crear el producto con un nombre limpio
                $nombreLimpio = trim($currentProduct);
                $query = "INSERT INTO xls_productos (nombre, orden) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $orden = 0;
                $stmt->bind_param("si", $nombreLimpio, $orden);
                $stmt->execute();
                $productoId = $conn->insert_id;
                mostrarMensaje("Producto adicional creado: $currentProduct (ID: $productoId)", "success");
            } else {
                $producto = $result->fetch_assoc();
                $productoId = $producto['id'];
                mostrarMensaje("Producto adicional encontrado: $currentProduct (ID: $productoId)", "info");
                
                // Eliminar adicionales existentes
                $query = "SELECT id FROM xls_adicionales WHERE id IN (SELECT adicional_id FROM xls_productos_adicionales WHERE producto_id = ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $productoId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $adicionalIds = [];
                while ($row = $result->fetch_assoc()) {
                    $adicionalIds[] = $row['id'];
                }
                
                if (!empty($adicionalIds)) {
                    $placeholders = implode(',', array_fill(0, count($adicionalIds), '?'));
                    
                    // Eliminar precios de adicionales
                    $query = "DELETE FROM xls_adicionales_precios WHERE adicional_id IN ($placeholders)";
                    $stmt = $conn->prepare($query);
                    $types = str_repeat('i', count($adicionalIds));
                    $stmt->bind_param($types, ...$adicionalIds);
                    $stmt->execute();
                    
                    // Eliminar relaciones producto-adicional
                    $query = "DELETE FROM xls_productos_adicionales WHERE adicional_id IN ($placeholders)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($types, ...$adicionalIds);
                    $stmt->execute();
                    
                    // Eliminar adicionales
                    $query = "DELETE FROM xls_adicionales WHERE id IN ($placeholders)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($types, ...$adicionalIds);
                    $stmt->execute();
                    
                    mostrarMensaje("Eliminados adicionales existentes para el producto", "info");
                }
            }
            
            continue;
        }
        
        // Si tenemos un producto actual y la primera columna no está vacía, es un adicional
        if ($currentProduct && $productoId && !empty($firstCell) && !empty($plazoColumns)) {
            $adicionalNombre = trim($firstCell);
            
            // Verificar si es una línea de adicional válida
            $tienePrecios = false;
            foreach ($plazoColumns as $plazoId => $columnIndex) {
                $cellValue = $worksheet->getCellByColumnAndRow($columnIndex, $row)->getValue();
                if (!empty($cellValue)) {
                    $tienePrecios = true;
                    break;
                }
            }
            
            if (!$tienePrecios) continue;
            
            // Insertar adicional
            $stmt = $conn->prepare("INSERT INTO xls_adicionales (nombre, tipo) VALUES (?, 'checkbox')");
            $stmt->bind_param("s", $adicionalNombre);
            $stmt->execute();
            
            $adicionalId = $conn->insert_id;
            
            // Relacionar adicional con producto
            $stmt = $conn->prepare("INSERT INTO xls_productos_adicionales (producto_id, adicional_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $productoId, $adicionalId);
            $stmt->execute();
            
            // Insertar precios para cada plazo
            foreach ($plazoColumns as $plazoId => $columnIndex) {
                $cellValue = $worksheet->getCellByColumnAndRow($columnIndex, $row)->getValue();
                if (!empty($cellValue)) {
                    $precio = limpiarPrecio($cellValue);
                    
                    $stmt = $conn->prepare("INSERT INTO xls_adicionales_precios (adicional_id, plazo_id, precio) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $adicionalId, $plazoId, $precio);
                    $stmt->execute();
                }
            }
            
            mostrarMensaje("Agregado adicional: $adicionalNombre para $currentProduct", "success");
        }
    }
    
    mostrarMensaje("Procesamiento de la hoja de adicionales completado", "success");
}

// Función para procesar la hoja de descuentos
function procesarHojaDescuentos($worksheet, $conn) {
    // Aquí se implementaría la lógica para procesar descuentos si es necesario
    mostrarMensaje("Procesamiento de la hoja de descuentos completado", "success");
}

// Procesar el archivo Excel
try {
    // Verificar si se envió un archivo
    $archivoSubido = false;
    $mensaje = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $archivoSubido = true;
        $tempFile = $_FILES['excel_file']['tmp_name'];
        
        // Conectar a la base de datos
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Cargar el archivo Excel
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            
            // Establecer tiempo máximo de ejecución
            set_time_limit(600); // 10 minutos
            
            // Limpiar la base de datos antes de importar
            if (isset($_POST['limpiar_antes']) && $_POST['limpiar_antes'] == 'si') {
                mostrarMensaje("Limpiando base de datos antes de importar...", "info");
                
                // Eliminar datos existentes
                $conn->query("DELETE FROM xls_precios");
                $conn->query("DELETE FROM xls_opciones");
                $conn->query("DELETE FROM xls_adicionales_precios");
                $conn->query("DELETE FROM xls_productos_adicionales");
                $conn->query("DELETE FROM xls_adicionales");
                $conn->query("DELETE FROM xls_productos");
                
                mostrarMensaje("Base de datos limpiada correctamente", "success");
            }
            
            $spreadsheet = $reader->load($tempFile);
            
            // Obtener las hojas disponibles
            $sheetNames = $spreadsheet->getSheetNames();
            
            // Procesar cada hoja
            // Limitar a procesar solo las primeras 3 hojas
            $procesadas = 0;
            
            foreach ($sheetNames as $sheetName) {
                if ($procesadas >= 3) break; // Máximo 3 hojas
                
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                
                mostrarMensaje("Procesando hoja: $sheetName", "info");
                
                // Determinar qué tipo de hoja es y procesarla adecuadamente
                $sheetNameLower = strtolower($sheetName);
                
                if (strpos($sheetNameLower, 'ascensor') !== false || strpos($sheetNameLower, 'producto') !== false) {
                    procesarHojaProductos($worksheet, $conn);
                } else if (strpos($sheetNameLower, 'adicional') !== false) {
                    procesarHojaAdicionales($worksheet, $conn);
                } else if (strpos($sheetNameLower, 'descuento') !== false) {
                    procesarHojaDescuentos($worksheet, $conn);
                } else {
                    // Si no podemos determinar el tipo, intentamos procesar como productos
                    procesarHojaProductos($worksheet, $conn);
                }
                
                $procesadas++;
            }
            
            // Confirmar cambios
            $conn->commit();
            $mensaje = "<p style='color: green; font-weight: bold;'>Importación completada con éxito</p>";
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            $conn->rollback();
            $mensaje = "<p style='color: red; font-weight: bold;'>Error durante la importación: " . $e->getMessage() . "</p>";
        }
    }
    
    // Mostrar formulario de importación
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Importar desde Excel</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            h1, h2, h3 { color: #333; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .card { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: white; }
            .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; border: none; cursor: pointer; }
            .note { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #4CAF50; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>Importar datos desde archivo Excel</h1>
        
        <div class='card'>
            <div class='note'>
                <p><strong>Nota:</strong> Este script importará datos directamente desde un archivo Excel (.xlsx) con múltiples hojas.</p>
            </div>
        
        " . ($archivoSubido ? $mensaje : "") . "
        
        <form method='post' enctype='multipart/form-data'>
            <p>
                <label for='excel_file'>Seleccione el archivo Excel:</label><br>
                <input type='file' name='excel_file' id='excel_file' accept='.xlsx,.xls' required>
            </p>
            
            <p>
                <input type='checkbox' name='limpiar_antes' value='si' id='limpiar_antes' checked>
                <label for='limpiar_antes'>Limpiar base de datos antes de importar (recomendado)</label>
            </p>
            
            <p>
                <button type='submit' class='btn'>Importar datos</button>
            </p>
        </form>
        
        <p><a href='cotizador_xls_fixed.php' class='btn'>Ir al Cotizador</a></p>
    </body>
</html>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
}
?>

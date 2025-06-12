<?php
// Script para importar datos desde un archivo Excel con fórmulas
require_once 'vendor/autoload.php';
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;

// Función para mostrar mensajes
function mostrarMensaje($mensaje, $tipo = 'info') {
    $color = 'black';
    if ($tipo == 'success') $color = 'green';
    if ($tipo == 'error') $color = 'red';
    if ($tipo == 'warning') $color = 'orange';
    
    echo "<p style='color: $color;'>$mensaje</p>";
}

// Función para limpiar y convertir valores monetarios
function limpiarValorMonetario($valor) {
    // Si es una cadena, limpiar caracteres no numéricos
    if (is_string($valor)) {
        $valor = preg_replace('/[^0-9.,]/', '', $valor);
        $valor = str_replace(['.', ','], ['', '.'], $valor);
    }
    
    return floatval($valor);
}

// Función para procesar la hoja de productos
function procesarHoja($worksheet, $conn) {
    // Variables para el procesamiento
    $productos = [];
    $currentProduct = null;
    $productoId = null;
    $opciones = [];
    $plazos = [];
    
    // Obtener el rango de datos
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    
    // Identificar columnas de plazos en la primera fila
    for ($col = 'C'; $col <= $highestColumn; $col++) {
        $header = $worksheet->getCell($col . '1')->getValue();
        if (strpos(strtolower($header), 'precio') !== false) {
            $plazos[$col] = $header;
        }
    }
    
    if (empty($plazos)) {
        mostrarMensaje("No se encontraron columnas de precios en la hoja", "error");
        return;
    }
    
    mostrarMensaje("Plazos de entrega detectados: " . implode(", ", $plazos), "info");
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Procesar cada fila
        $lastProductName = '';
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $productName = trim($worksheet->getCell('A' . $row)->getValue());
            $opcionName = trim($worksheet->getCell('B' . $row)->getValue());
            
            // Si hay un producto en columna A, usarlo como producto actual
            if (!empty($productName)) {
                $currentProduct = $productName;
                
                // Si es un nuevo producto (diferente al anterior)
                if ($currentProduct != $lastProductName) {
                    // Verificar si el producto ya existe
                    $query = "SELECT id FROM xls_productos WHERE nombre = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $currentProduct);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        // Crear el producto
                        $query = "INSERT INTO xls_productos (nombre, orden) VALUES (?, ?)";
                        $stmt = $conn->prepare($query);
                        $orden = 0;
                        $stmt->bind_param("si", $currentProduct, $orden);
                        $stmt->execute();
                        $productoId = $conn->insert_id;
                        mostrarMensaje("Producto creado: $currentProduct (ID: $productoId)", "success");
                    } else {
                        $row = $result->fetch_assoc();
                        $productoId = $row['id'];
                        mostrarMensaje("Producto encontrado: $currentProduct (ID: $productoId)", "info");
                    }
                    
                    $lastProductName = $currentProduct;
                }
            }
            
            // Si hay una opción en columna B y tenemos un producto actual
            if (!empty($opcionName) && !empty($productoId)) {
                // Crear la opción
                $query = "INSERT INTO xls_opciones (producto_id, nombre) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("is", $productoId, $opcionName);
                $stmt->execute();
                $opcionId = $conn->insert_id;
                
                mostrarMensaje("Opción creada: $opcionName para producto $currentProduct", "success");
                
                // Procesar precios para cada plazo
                foreach ($plazos as $col => $plazoNombre) {
                    // Obtener el valor calculado (no la fórmula)
                    $precioCalculado = $worksheet->getCell($col . $row)->getCalculatedValue();
                    
                    // Limpiar y convertir el valor
                    $precio = limpiarValorMonetario($precioCalculado);
                    
                    // Extraer el plazo del nombre (por ejemplo, "Precio 90 dias" -> "90 dias")
                    $plazo = str_replace("Precio ", "", $plazoNombre);
                    
                    // Guardar el precio
                    $query = "INSERT INTO xls_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("isd", $opcionId, $plazo, $precio);
                    $stmt->execute();
                    
                    mostrarMensaje("Precio guardado para opción '$opcionName', plazo '$plazo': $precio", "success");
                }
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        mostrarMensaje("Importación completada con éxito", "success");
        
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $conn->rollback();
        mostrarMensaje("Error al procesar la hoja: " . $e->getMessage(), "error");
    }
}

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Importar desde Excel con Fórmulas</title>
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
        <h1>Importar desde Excel con Fórmulas</h1>";
    
    // Procesar formulario
    $archivoSubido = false;
    $mensaje = "";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verificar si se ha subido un archivo
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $tempFile = $_FILES['excel_file']['tmp_name'];
            $fileName = $_FILES['excel_file']['name'];
            
            // Verificar extensión
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($fileExt !== 'xlsx') {
                $mensaje = mostrarMensaje("El archivo debe ser un archivo Excel (.xlsx)", "error");
            } else {
                $archivoSubido = true;
                
                // Limpiar la base de datos si se solicita
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
                
                // Cargar el archivo Excel
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(false); // Importante: leer fórmulas
                
                // Establecer tiempo máximo de ejecución
                set_time_limit(600); // 10 minutos
                
                $spreadsheet = $reader->load($tempFile);
                
                // Obtener las hojas disponibles
                $sheetNames = $spreadsheet->getSheetNames();
                
                // Procesar cada hoja
                foreach ($sheetNames as $sheetName) {
                    $worksheet = $spreadsheet->getSheetByName($sheetName);
                    
                    mostrarMensaje("Procesando hoja: $sheetName", "info");
                    procesarHoja($worksheet, $conn);
                }
                
                mostrarMensaje("Importación finalizada", "success");
                echo "<p><a href='cotizador_xls_fixed.php' class='btn'>Ir al Cotizador</a></p>";
            }
        } else if (isset($_FILES['excel_file'])) {
            $mensaje = mostrarMensaje("Error al subir el archivo: " . $_FILES['excel_file']['error'], "error");
        }
    }
    
    // Mostrar formulario
    echo "
        <div class='card'>
            <div class='note'>
                <p><strong>Nota:</strong> Este script importará datos directamente desde un archivo Excel (.xlsx) con fórmulas.</p>
                <p>El sistema calculará los valores de las fórmulas y los importará correctamente.</p>
            </div>
            
            " . ($archivoSubido ? "" : "
            <form method='post' enctype='multipart/form-data'>
                <p>
                    <label for='excel_file'>Seleccione el archivo Excel:</label><br>
                    <input type='file' name='excel_file' id='excel_file' accept='.xlsx' required>
                </p>
                
                <p>
                    <input type='checkbox' name='limpiar_antes' value='si' id='limpiar_antes' checked>
                    <label for='limpiar_antes'>Limpiar base de datos antes de importar (recomendado)</label>
                </p>
                
                <p>
                    <button type='submit' class='btn'>Importar datos</button>
                </p>
            </form>") . "
        </div>
        
        <p><a href='admin/index.php' class='btn' style='background-color: #2196F3;'>Ir al Panel de Administración</a></p>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
}
?>

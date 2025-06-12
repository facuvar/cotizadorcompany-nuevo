<?php
// Script para mostrar el contenido del archivo Excel cotizador-xls.xlsx
require_once 'vendor/autoload.php';
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Función para mostrar mensajes
function mostrarMensaje($mensaje, $tipo = 'info') {
    $color = 'black';
    if ($tipo == 'success') $color = 'green';
    if ($tipo == 'error') $color = 'red';
    if ($tipo == 'warning') $color = 'orange';
    
    echo "<p style='color: $color;'>$mensaje</p>";
}

// Función para formatear valores de celda
function formatearValor($valor) {
    if (is_numeric($valor)) {
        return number_format($valor, 2, ',', '.');
    }
    return $valor;
}

// Ruta al archivo Excel
$excelFile = 'xls/cotizador-xls.xlsx';

// Verificar si el archivo existe
if (!file_exists($excelFile)) {
    die("El archivo $excelFile no existe.");
}

// Cargar el archivo Excel
try {
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($excelFile);
    
    // Obtener las hojas disponibles
    $sheetNames = $spreadsheet->getSheetNames();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Contenido del archivo Excel</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            h1, h2, h3 { color: #333; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .sheet-nav { margin-bottom: 20px; }
            .sheet-nav a { 
                display: inline-block; 
                padding: 5px 10px; 
                margin-right: 5px; 
                background-color: #f2f2f2; 
                text-decoration: none;
                color: #333;
                border-radius: 3px;
            }
            .sheet-nav a.active {
                background-color: #4CAF50;
                color: white;
            }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
        </style>
    </head>
    <body>
        <h1>Contenido del archivo Excel: " . basename($excelFile) . "</h1>";
    
    // Mostrar navegación de hojas
    echo "<div class='sheet-nav'>";
    foreach ($sheetNames as $index => $sheetName) {
        $activeClass = ($index === 0) ? 'active' : '';
        echo "<a href='#sheet-$index' class='$activeClass' onclick='showSheet($index)'>$sheetName</a>";
    }
    echo "</div>";
    
    // Mostrar contenido de cada hoja
    foreach ($sheetNames as $index => $sheetName) {
        $display = ($index === 0) ? 'block' : 'none';
        echo "<div id='sheet-$index' style='display: $display;'>";
        echo "<h2>Hoja: $sheetName</h2>";
        
        $worksheet = $spreadsheet->getSheetByName($sheetName);
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Análisis de la estructura
        echo "<h3>Análisis de la estructura:</h3>";
        
        // Detectar encabezados y plazos
        $headers = [];
        $plazos = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellValue = $worksheet->getCell($col . '1')->getValue();
            $headers[$col] = $cellValue;
            
            // Detectar si es un plazo de entrega
            if (strpos(strtolower($cellValue), 'día') !== false || 
                strpos(strtolower($cellValue), 'dias') !== false) {
                $plazos[$col] = $cellValue;
            }
        }
        
        // Mostrar información de plazos detectados
        if (!empty($plazos)) {
            echo "<p class='success'>✓ Plazos de entrega detectados: " . count($plazos) . "</p>";
            echo "<ul>";
            foreach ($plazos as $col => $plazo) {
                echo "<li>Columna $col: $plazo</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='error'>✗ No se detectaron plazos de entrega en esta hoja</p>";
        }
        
        // Detectar productos y opciones
        $productos = [];
        $currentProduct = null;
        
        for ($row = 1; $row <= min($highestRow, 100); $row++) {
            $firstCell = $worksheet->getCell('A' . $row)->getValue();
            $secondCell = $worksheet->getCell('B' . $row)->getValue();
            
            // Detectar producto
            if (!empty($firstCell) && empty($secondCell) && (
                strpos(strtoupper($firstCell), 'EQUIPO') !== false || 
                strpos(strtoupper($firstCell), 'ESTRUCTURA') !== false ||
                strpos(strtoupper($firstCell), 'GIRACOCHE') !== false ||
                strpos(strtoupper($firstCell), 'MONTAPLATO') !== false ||
                strpos(strtoupper($firstCell), 'DOMICILIARIO') !== false ||
                strpos(strtoupper($firstCell), 'ASCENSOR') !== false ||
                strpos(strtoupper($firstCell), 'ADICIONALES') !== false)) {
                
                $currentProduct = $firstCell;
                $productos[$currentProduct] = [];
            }
            
            // Detectar opción
            if ($currentProduct && !empty($secondCell)) {
                $productos[$currentProduct][] = $secondCell;
            }
        }
        
        // Mostrar información de productos detectados
        if (!empty($productos)) {
            echo "<p class='success'>✓ Productos detectados: " . count($productos) . "</p>";
            echo "<ul>";
            foreach ($productos as $producto => $opciones) {
                echo "<li><strong>$producto</strong> - " . count($opciones) . " opciones</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='error'>✗ No se detectaron productos en esta hoja</p>";
        }
        
        // Mostrar tabla con los datos
        echo "<h3>Contenido de la hoja:</h3>";
        echo "<div style='overflow-x: auto;'>";
        echo "<table>";
        
        // Encabezados de la tabla
        echo "<tr>";
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            echo "<th>" . $col . ": " . htmlspecialchars($headers[$col] ?? '') . "</th>";
        }
        echo "</tr>";
        
        // Datos de la tabla
        for ($row = 2; $row <= min($highestRow, 100); $row++) {
            echo "<tr>";
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $worksheet->getCell($col . $row)->getValue();
                $formattedValue = formatearValor($cellValue);
                
                // Resaltar productos
                $style = '';
                if ($col === 'A' && empty($worksheet->getCell('B' . $row)->getValue()) && !empty($cellValue)) {
                    $style = 'font-weight: bold; background-color: #e6f7ff;';
                }
                // Resaltar opciones
                else if ($col === 'B' && !empty($cellValue)) {
                    $style = 'font-weight: bold; color: #4CAF50;';
                }
                // Resaltar precios
                else if (in_array($col, array_keys($plazos)) && is_numeric($cellValue)) {
                    $style = 'text-align: right; color: #0066cc;';
                }
                
                echo "<td style='$style'>" . htmlspecialchars($formattedValue) . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
        
        // Mostrar mensaje si hay más filas
        if ($highestRow > 100) {
            echo "<p class='warning'>Nota: Solo se muestran las primeras 100 filas de " . $highestRow . " filas totales.</p>";
        }
        
        echo "</div>";
    }
    
    echo "<script>
    function showSheet(index) {
        // Ocultar todas las hojas
        var sheets = document.querySelectorAll('[id^=\"sheet-\"]');
        for (var i = 0; i < sheets.length; i++) {
            sheets[i].style.display = 'none';
        }
        
        // Mostrar la hoja seleccionada
        document.getElementById('sheet-' + index).style.display = 'block';
        
        // Actualizar la navegación
        var links = document.querySelectorAll('.sheet-nav a');
        for (var i = 0; i < links.length; i++) {
            links[i].classList.remove('active');
        }
        links[index].classList.add('active');
    }
    </script>";
    
    echo "<p><a href='importar_desde_excel.php' style='display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Ir a Importar Datos</a></p>";
    
    echo "</body></html>";
    
} catch (Exception $e) {
    die("Error al leer el archivo Excel: " . $e->getMessage());
}
?>

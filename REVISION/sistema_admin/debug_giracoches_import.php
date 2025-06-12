<?php
session_start();
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Verificar si el usuario es administrador
requireAdmin();

// Configuración de cabeceras para mostrar la salida en tiempo real
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Debug Importación GIRACOCHES</title>
        <link rel="stylesheet" href="../../assets/css/admin.css">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
            .container { max-width: 1200px; margin: 0 auto; }
            .debug-info { background-color: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
            .debug-info h3 { margin-top: 0; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .info { color: blue; }
            .debug-step { margin-bottom: 20px; border-left: 3px solid #007bff; padding-left: 15px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            table, th, td { border: 1px solid #ddd; }
            th, td { padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .btn { display: inline-block; padding: 6px 12px; margin-bottom: 0; font-size: 14px; font-weight: 400; 
                   line-height: 1.42857143; text-align: center; white-space: nowrap; vertical-align: middle; 
                   cursor: pointer; border: 1px solid transparent; border-radius: 4px; text-decoration: none; }
            .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; }
            .btn-success { color: #fff; background-color: #28a745; border-color: #28a745; }
            .btn-warning { color: #fff; background-color: #ffc107; border-color: #ffc107; }
            pre { background-color: #f8f9fa; padding: 10px; border-radius: 5px; overflow: auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Debug de Importación de GIRACOCHES</h1>';

    echo '<div class="debug-step">
        <h2>1. Verificando conexión a la base de datos</h2>';
    
    if ($conn) {
        echo '<p class="success">✅ Conexión a la base de datos establecida correctamente.</p>';
    } else {
        echo '<p class="error">❌ Error: No se pudo establecer conexión con la base de datos.</p>';
        exit;
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>2. Buscando fuente de datos más reciente</h2>';
    
    // Buscar la fuente de datos más reciente
    $query = "SELECT * FROM fuente_datos WHERE tipo = 'google_sheets' ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        echo '<p class="error">❌ Error: No se encontró ninguna fuente de datos de Google Sheets.</p>';
        exit;
    }
    
    $fuenteDatos = $result->fetch_assoc();
    echo '<p class="success">✅ Fuente de datos encontrada: ID=' . $fuenteDatos['id'] . '</p>';
    echo '<p class="info">📅 Última actualización: ' . $fuenteDatos['fecha_actualizacion'] . '</p>';
    echo '<p class="info">🔗 URL: ' . htmlspecialchars($fuenteDatos['url']) . '</p>';
    echo '</div>';

    echo '<div class="debug-step">
        <h2>3. Determinando columna de fecha</h2>';
    
    // Buscar la columna que contiene "fecha" en su nombre
    $query = "SHOW COLUMNS FROM fuente_datos";
    $result = $conn->query($query);
    $columnaFecha = null;
    
    if ($result) {
        while ($columna = $result->fetch_assoc()) {
            if (stripos($columna['Field'], 'fecha') !== false) {
                $columnaFecha = $columna['Field'];
                echo '<p class="success">✅ Columna de fecha encontrada: ' . $columnaFecha . '</p>';
                break;
            }
        }
    }
    
    if (!$columnaFecha) {
        $columnaFecha = 'fecha_actualizacion';
        echo '<p class="warning">⚠️ No se encontró columna con "fecha" en su nombre, usando: ' . $columnaFecha . '</p>';
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>4. Procesando URL del documento Google Sheets</h2>';
    
    // Extraer ID del documento de Google Sheets
    $url = $fuenteDatos['url'];
    preg_match('/\/d\/(.*?)\//', $url . '/', $matches);
    
    if (empty($matches[1])) {
        echo '<p class="error">❌ Error: No se pudo extraer el ID del documento de Google Sheets.</p>';
        exit;
    }
    
    $documentId = $matches[1];
    echo '<p class="success">✅ ID del documento encontrado: ' . $documentId . '</p>';
    
    // Construir URL para exportación
    $exportUrl = "https://docs.google.com/spreadsheets/d/{$documentId}/export?format=xlsx";
    echo '<p class="info">🔗 URL de exportación: ' . htmlspecialchars($exportUrl) . '</p>';
    echo '</div>';

    echo '<div class="debug-step">
        <h2>5. Descargando archivo XLSX</h2>';
    
    // Crear directorio temporal si no existe
    if (!file_exists('../temp')) {
        mkdir('../temp', 0755, true);
        echo '<p class="info">📁 Directorio temporal creado: ../temp</p>';
    }
    
    $tempFile = '../temp/giracoches_import_' . time() . '.xlsx';
    
    // Intentar descargar el archivo
    $ch = curl_init($exportUrl);
    $fp = fopen($tempFile, 'w+');
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    fclose($fp);
    
    if ($success && $httpCode == 200) {
        echo '<p class="success">✅ Archivo descargado correctamente: ' . $tempFile . '</p>';
        echo '<p class="info">📊 Tamaño del archivo: ' . filesize($tempFile) . ' bytes</p>';
    } else {
        echo '<p class="error">❌ Error al descargar el archivo: ' . $error . ' (HTTP Code: ' . $httpCode . ')</p>';
        exit;
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>6. Cargando archivo con PhpSpreadsheet</h2>';
    
    try {
        $spreadsheet = IOFactory::load($tempFile);
        echo '<p class="success">✅ Archivo cargado correctamente con PhpSpreadsheet</p>';
        
        // Información sobre las hojas
        $sheetCount = $spreadsheet->getSheetCount();
        echo '<p class="info">📊 Número de hojas en el documento: ' . $sheetCount . '</p>';
        
        echo '<p class="info">📋 Nombres de las hojas:</p>';
        echo '<ul>';
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            echo '<li>' . htmlspecialchars($sheetName) . '</li>';
        }
        echo '</ul>';
        
        // Usar la primera hoja
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        
        echo '<p class="info">📊 Dimensiones de la hoja principal: ' . $highestColumn . $highestRow . '</p>';
        echo '<p class="info">📊 Número de filas: ' . $highestRow . '</p>';
    } catch (Exception $e) {
        echo '<p class="error">❌ Error al cargar el archivo con PhpSpreadsheet: ' . $e->getMessage() . '</p>';
        exit;
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>7. Buscando sección GIRACOCHES</h2>';
    
    // Buscar la sección GIRACOCHES
    $inicioGiracoches = null;
    $finGiracoches = null;
    $encabezadosGiracoches = [];
    
    for ($row = 1; $row <= $highestRow; $row++) {
        $value = $sheet->getCell('A' . $row)->getValue();
        
        if ($value === 'GIRACOCHES' && $inicioGiracoches === null) {
            $inicioGiracoches = $row;
            echo '<p class="success">✅ Sección GIRACOCHES encontrada en la fila ' . $row . '</p>';
            
            // Buscar encabezados (asumiendo que están en la siguiente fila)
            $headerRow = $row + 1;
            $col = 'A';
            
            while ($sheet->getCell($col . $headerRow)->getValue() !== null && $col <= $highestColumn) {
                $encabezadosGiracoches[$col] = $sheet->getCell($col . $headerRow)->getValue();
                $col++;
            }
            
            echo '<p class="info">📋 Encabezados encontrados:</p>';
            echo '<pre>' . print_r($encabezadosGiracoches, true) . '</pre>';
            
            // Buscar el final de la sección (o hasta que encontremos otra sección)
            for ($endRow = $headerRow + 1; $endRow <= $highestRow; $endRow++) {
                $endValue = $sheet->getCell('A' . $endRow)->getValue();
                
                // Si encontramos una celda vacía o una nueva sección (mayúsculas), terminamos
                if ($endValue === null || (is_string($endValue) && strtoupper($endValue) === $endValue && strtoupper($endValue) !== $endValue)) {
                    $finGiracoches = $endRow - 1;
                    echo '<p class="success">✅ Fin de sección GIRACOCHES en la fila ' . $finGiracoches . '</p>';
                    break;
                }
            }
            
            // Si no encontramos el final, asumimos que llega hasta el final de la hoja
            if ($finGiracoches === null) {
                $finGiracoches = $highestRow;
                echo '<p class="warning">⚠️ No se encontró un final claro para la sección GIRACOCHES, asumiendo hasta el final: ' . $finGiracoches . '</p>';
            }
            
            break;
        }
    }
    
    if ($inicioGiracoches === null) {
        echo '<p class="error">❌ No se encontró la sección GIRACOCHES en el documento</p>';
        exit;
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>8. Extrayendo datos de modelos GIRACOCHES</h2>';
    
    // Extraer datos de los modelos (desde la fila después de los encabezados)
    $modelosGiracoches = [];
    $dataStartRow = $inicioGiracoches + 2; // Fila después de los encabezados
    
    echo '<p class="info">📊 Leyendo datos desde la fila ' . $dataStartRow . ' hasta la ' . $finGiracoches . '</p>';
    
    for ($row = $dataStartRow; $row <= $finGiracoches; $row++) {
        $modelo = [];
        $isEmpty = true;
        
        foreach ($encabezadosGiracoches as $col => $header) {
            $value = $sheet->getCell($col . $row)->getValue();
            
            // Si al menos una celda tiene valor, no está vacía
            if ($value !== null && $value !== '') {
                $isEmpty = false;
            }
            
            $modelo[$header] = $value;
        }
        
        // Solo agregar si la fila no está vacía
        if (!$isEmpty) {
            $modelosGiracoches[] = $modelo;
        }
    }
    
    $numModelos = count($modelosGiracoches);
    
    if ($numModelos > 0) {
        echo '<p class="success">✅ Se encontraron ' . $numModelos . ' modelos de GIRACOCHES</p>';
        
        echo '<h3>Modelos encontrados:</h3>';
        echo '<table>';
        echo '<tr>';
        foreach (array_keys($modelosGiracoches[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        
        foreach ($modelosGiracoches as $modelo) {
            echo '<tr>';
            foreach ($modelo as $valor) {
                echo '<td>' . htmlspecialchars($valor ?? '') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="error">❌ No se encontraron modelos de GIRACOCHES</p>';
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>9. Buscando categoría GIRACOCHES en la base de datos</h2>';
    
    // Buscar la categoría GIRACOCHES
    $query = "SELECT * FROM categorias WHERE nombre = 'GIRACOCHES'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $categoriaGiracoches = $result->fetch_assoc();
        echo '<p class="success">✅ Categoría GIRACOCHES encontrada en base de datos: ID=' . $categoriaGiracoches['id'] . '</p>';
    } else {
        echo '<p class="warning">⚠️ No se encontró la categoría GIRACOCHES en la base de datos. Se creará al importar.</p>';
    }
    
    // Obtener los plazos de entrega disponibles
    $plazos = [];
    $queryPlazos = "SELECT id, nombre FROM plazos_entrega ORDER BY id ASC";
    $resultPlazos = $conn->query($queryPlazos);
    
    if ($resultPlazos && $resultPlazos->num_rows > 0) {
        while ($plazo = $resultPlazos->fetch_assoc()) {
            $plazos[] = $plazo;
        }
        echo '<p class="success">✅ Se encontraron ' . count($plazos) . ' plazos de entrega en la base de datos</p>';
    } else {
        echo '<p class="warning">⚠️ No se encontraron plazos de entrega en la base de datos</p>';
    }
    echo '</div>';

    echo '<div class="debug-step">
        <h2>10. Resumen y acciones</h2>';
    
    echo '<p>Se encontraron <strong>' . $numModelos . '</strong> modelos de GIRACOCHES en el archivo. Al importar, estos modelos se agregarán o actualizarán en la base de datos.</p>';
    
    echo '<p>Ejemplos de algunos modelos encontrados:</p>';
    
    // Mostrar hasta 3 modelos de ejemplo
    $ejemplos = array_slice($modelosGiracoches, 0, min(3, count($modelosGiracoches)));
    
    foreach ($ejemplos as $index => $modelo) {
        echo '<div class="debug-info">';
        echo '<h3>Modelo #' . ($index + 1) . ': ' . htmlspecialchars($modelo['MODELO'] ?? 'Sin nombre') . '</h3>';
        
        // Mostrar los plazos y precios que se importarían
        echo '<p>Plazos y precios que se importarían:</p>';
        echo '<ul>';
        
        foreach ($plazos as $plazo) {
            $nombrePlazo = $plazo['nombre'];
            $precioColumna = 'PRECIO ' . $nombrePlazo;
            
            if (isset($modelo[$precioColumna])) {
                echo '<li>' . htmlspecialchars($nombrePlazo) . ': $' . number_format($modelo[$precioColumna], 2, ',', '.') . '</li>';
            } else {
                echo '<li>' . htmlspecialchars($nombrePlazo) . ': No disponible</li>';
            }
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    // Botones para continuar
    echo '<div style="margin-top: 30px;">';
    echo '<a href="import_giracoches.php" class="btn btn-success">Proceder con la importación</a> ';
    echo '<a href="view_import_logs.php" class="btn btn-primary">Ver registros de importaciones anteriores</a> ';
    echo '<a href="index.php" class="btn btn-warning">Volver al panel de administración</a>';
    echo '</div>';
    
    echo '</div>';
    
    // Limpiar el archivo temporal
    unlink($tempFile);
    echo '<p class="info">🧹 Archivo temporal eliminado.</p>';

    echo '</div>
    </body>
    </html>';
    
} catch (Exception $e) {
    echo '<div class="error">';
    echo '<h3>Error crítico</h3>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '</div>';
    
    echo '<a href="index.php" class="btn btn-warning">Volver al panel de administración</a>';
    
    echo '</div>
    </body>
    </html>';
}
?> 
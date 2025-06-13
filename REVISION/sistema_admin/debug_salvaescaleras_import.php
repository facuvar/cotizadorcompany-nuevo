<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Este script es solo para depuración y muestra los datos de la hoja de cálculo
// No requiere login para pruebas
// requireAdmin();

// Función para mostrar información de depuración
function debug_info($title, $content, $type = 'info') {
    $bgColor = '#e3f2fd'; // Azul claro por defecto
    
    if ($type === 'error') {
        $bgColor = '#ffebee'; // Rojo claro
    } elseif ($type === 'success') {
        $bgColor = '#e8f5e9'; // Verde claro
    } elseif ($type === 'warning') {
        $bgColor = '#fff9c4'; // Amarillo claro
    }
    
    echo "<div style='background-color: {$bgColor}; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>";
    echo "<h3 style='margin-top: 0;'>{$title}</h3>";
    
    if (is_array($content) || is_object($content)) {
        echo "<pre>" . htmlspecialchars(print_r($content, true)) . "</pre>";
    } else {
        echo "<p>" . htmlspecialchars($content) . "</p>";
    }
    
    echo "</div>";
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h1>Depuración de Importación de SALVAESCALERAS</h1>";
    
    // Paso 1: Conexión a la base de datos
    debug_info("1. Conexión a la base de datos", "Conexión establecida correctamente", "success");
    
    // Paso 2: Obtener la URL de Google Sheets más reciente
    $query = "SELECT * FROM fuente_datos ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontró ninguna fuente de datos registrada en la base de datos.");
    }
    
    $fuenteDatos = $result->fetch_assoc();
    debug_info("2. Fuente de datos encontrada", $fuenteDatos, "success");
    
    // Paso 3: Extraer ID del documento
    $urlGoogleSheets = $fuenteDatos['url'];
    
    $pattern = '/spreadsheets\/d\/([a-zA-Z0-9-_]+)/';
    if (!preg_match($pattern, $urlGoogleSheets, $matches)) {
        throw new Exception("No se pudo extraer el ID del documento de Google Sheets desde la URL proporcionada.");
    }
    
    $documentId = $matches[1];
    debug_info("3. ID del documento extraído", "ID: " . $documentId, "success");
    
    // Paso 4: Construir URL de exportación
    $exportUrl = "https://docs.google.com/spreadsheets/d/{$documentId}/export?format=xlsx";
    debug_info("4. URL de exportación", $exportUrl, "info");
    
    // Paso 5: Información sobre la descarga (simulada)
    debug_info("5. Descarga del archivo", "Simulando descarga del archivo XLSX...", "info");
    
    // Paso 6: Búsqueda de la sección SALVAESCALERAS
    debug_info("6. Búsqueda de sección SALVAESCALERAS", "Simulando búsqueda en las hojas...", "info");
    
    // Simular el resultado de la búsqueda
    $salvaescalerasInfo = [
        'sheet_name' => 'Hoja1',
        'start_row' => 10,
        'salvaescaleras_row' => 12,
        'model_rows' => [13, 14, 15],
        'plazo_columns' => [
            [
                'column' => 'D',
                'dias' => 160
            ],
            [
                'column' => 'E',
                'dias' => 90
            ],
            [
                'column' => 'F',
                'dias' => 270
            ]
        ]
    ];
    
    debug_info("7. Sección SALVAESCALERAS encontrada", $salvaescalerasInfo, "success");
    
    // Paso 8: Verificar categoría en la base de datos
    $query = "SELECT * FROM categorias WHERE nombre = 'SALVAESCALERAS'";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        debug_info("8. Categoría en la base de datos", "No se encontró la categoría. Se debe crear.", "warning");
        $categoriaExiste = false;
    } else {
        $categoria = $result->fetch_assoc();
        debug_info("8. Categoría en la base de datos", "Categoría encontrada: ID " . $categoria['id'], "success");
        $categoriaExiste = true;
        $categoriaId = $categoria['id'];
    }
    
    // Paso 9: Verificar opciones existentes
    if ($categoriaExiste) {
        $query = "SELECT * FROM opciones WHERE categoria_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $categoriaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $opcionesExistentes = [];
        while ($opcion = $result->fetch_assoc()) {
            $opcionesExistentes[] = $opcion;
        }
        
        debug_info("9. Opciones existentes", "Se encontraron " . count($opcionesExistentes) . " opciones para la categoría", "info");
        
        if (count($opcionesExistentes) > 0) {
            debug_info("Lista de opciones existentes", $opcionesExistentes, "info");
        }
    } else {
        debug_info("9. Opciones existentes", "No aplica - La categoría no existe", "info");
    }
    
    // Paso 10: Modelos esperados en la hoja
    $modelosEsperados = [
        'MODELO SIMPLE H/ 1.80 M',
        'MODELO COMPLETO H/1.80 M',
        'MODELO COMPLETO H/3 M'
    ];
    
    debug_info("10. Modelos esperados", $modelosEsperados, "info");
    
    // Paso 11: Simulación de precios extraídos
    $preciosSimulados = [
        'MODELO SIMPLE H/ 1.80 M' => [
            ['plazo_dias' => 160, 'precio' => 13234809.00],
            ['plazo_dias' => 90, 'precio' => 17205251.70],
            ['plazo_dias' => 270, 'precio' => 11911328.10],
        ],
        'MODELO COMPLETO H/1.80 M' => [
            ['plazo_dias' => 160, 'precio' => 17350074.00],
            ['plazo_dias' => 90, 'precio' => 22555096.20],
            ['plazo_dias' => 270, 'precio' => 15615066.60],
        ],
        'MODELO COMPLETO H/3 M' => [
            ['plazo_dias' => 160, 'precio' => 21829196.00],
            ['plazo_dias' => 90, 'precio' => 28377954.80],
            ['plazo_dias' => 270, 'precio' => 19646276.40],
        ]
    ];
    
    debug_info("11. Precios extraídos (simulación)", $preciosSimulados, "success");
    
    // Paso 12: Proceso de importación
    debug_info("12. Proceso de importación", "Simular transacción para importar datos", "info");
    
    // Crear tabla para mostrar el paso de importación
    echo "<table border='1' cellpadding='5' cellspacing='0' style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<thead style='background-color: #f5f5f5;'>";
    echo "<tr>";
    echo "<th>Paso</th><th>Descripción</th><th>Estado</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    echo "<tr><td>12.1</td><td>Iniciar transacción</td><td style='color: green;'>✓ Simulado correctamente</td></tr>";
    
    if (!$categoriaExiste) {
        echo "<tr><td>12.2</td><td>Crear categoría 'SALVAESCALERAS'</td><td style='color: green;'>✓ Simulado correctamente</td></tr>";
        echo "<tr><td>12.3</td><td>Obtener ID de la nueva categoría</td><td style='color: green;'>✓ Simulado correctamente (ID: 999)</td></tr>";
    } else {
        echo "<tr><td>12.2</td><td>Eliminar opciones existentes para la categoría</td><td style='color: green;'>✓ Simulado correctamente</td></tr>";
        echo "<tr><td>12.3</td><td>Eliminar precios existentes para las opciones de la categoría</td><td style='color: green;'>✓ Simulado correctamente</td></tr>";
    }
    
    echo "<tr><td>12.4</td><td>Insertar nuevas opciones y precios</td><td style='color: green;'>✓ Simulado correctamente</td></tr>";
    echo "<tr><td>12.5</td><td>Confirmar transacción</td><td style='color: green;'>✓ Simulado correctamente</td></tr>";
    
    echo "</tbody>";
    echo "</table>";
    
    // Paso 13: Recomendaciones y próximos pasos
    echo "<h2>Recomendaciones y próximos pasos</h2>";
    echo "<ol>";
    echo "<li>Ejecutar el script de importación real: <a href='import_salvaescaleras.php' style='color: #2196F3;'>import_salvaescaleras.php</a></li>";
    echo "<li>Validar los datos importados: <a href='validate_salvaescaleras.php' style='color: #2196F3;'>validate_salvaescaleras.php</a></li>";
    echo "<li>Verificar la visualización en el cotizador: <a href='../cotizador.php' style='color: #2196F3;' target='_blank'>Cotizador</a></li>";
    echo "</ol>";
    
    // Botones de acción
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='import_salvaescaleras.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>Proceder a la importación real</a>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Volver al panel de administración</a>";
    echo "</div>";
    
} catch (Exception $e) {
    debug_info("Error en la depuración", $e->getMessage(), "error");
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Volver al panel de administración</a>";
    echo "</div>";
} 
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Esta es una herramienta de prueba para la importación específica de datos de MONTACARGAS
// No requiere login para pruebas
// requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Prueba de Importación para la Categoría MONTACARGAS</h2>";
    
    // Consultar la fuente de datos
    $sourceQuery = "SELECT * FROM fuente_datos WHERE tipo = 'google_sheets' ORDER BY fecha_actualizacion DESC LIMIT 1";
    $sourceResult = $conn->query($sourceQuery);
    
    if (!$sourceResult || $sourceResult->num_rows === 0) {
        throw new Exception('No se encontró ninguna fuente de datos de Google Sheets configurada');
    }
    
    $source = $sourceResult->fetch_assoc();
    echo "<div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
    echo "<h3 style='color: #2e7d32; margin-top: 0;'>Fuente de datos encontrada:</h3>";
    echo "<p><strong>URL:</strong> " . htmlspecialchars($source['url']) . "</p>";
    echo "<p><strong>Fecha de actualización:</strong> " . date('d/m/Y H:i:s', strtotime($source['fecha_actualizacion'])) . "</p>";
    echo "</div>";
    
    // Comprobar si existe la categoría MONTACARGAS
    $query = "SELECT * FROM categorias WHERE nombre LIKE '%MONTACARGAS%' LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        echo "<p style='color: red;'>No se encontró la categoría MONTACARGAS en la base de datos.</p>";
    } else {
        $categoria = $result->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        echo "<p>Categoría MONTACARGAS encontrada con ID: $categoriaId</p>";
        
        // Ver cuántas opciones existen actualmente
        $opcionesCount = "SELECT COUNT(*) as total FROM opciones WHERE categoria_id = $categoriaId";
        $countResult = $conn->query($opcionesCount);
        $countRow = $countResult->fetch_assoc();
        $totalOpciones = $countRow['total'];
        
        echo "<p>Opciones actuales para MONTACARGAS: $totalOpciones</p>";
        
        if ($totalOpciones > 0) {
            echo "<p>Opciones existentes:</p>";
            $opcionesQuery = "SELECT * FROM opciones WHERE categoria_id = $categoriaId ORDER BY orden";
            $opcionesResult = $conn->query($opcionesQuery);
            
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background-color: #4CAF50; color: white;'>";
            echo "<th>ID</th><th>Nombre</th><th>Precio</th></tr>";
            
            while ($opcion = $opcionesResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $opcion['id'] . "</td>";
                echo "<td>" . htmlspecialchars($opcion['nombre']) . "</td>";
                echo "<td>$" . number_format($opcion['precio'], 2, ',', '.') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // Mostrar los datos de ejemplo proporcionados
    echo "<h3>Datos de ejemplo de la hoja de cálculo:</h3>";
    echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "MONTACARGAS - MAQUINA TAMBOR                      160/180 dias    90 dias     270 dias\n";
    echo "HASTA 400 KG PUERTA MANUAL                        $  27,882,924.00    $ 36,247,801.20    $  25,094,631.60\n";
    echo "HASTA 1000 KG PUERTA MANUAL                       $  34,818,985.00    $ 45,264,680.50    $  31,337,086.50";
    echo "</pre>";
    
    // Mostrar botones de acción
    echo "<div style='margin: 20px 0;'>";
    echo "<form method='post' action='reconnect_last_file.php'>";
    echo "<button type='submit' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;'>Reconectar y actualizar datos</button>";
    echo "</form>";
    
    echo "<a href='test_sheets.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>Verificar todas las categorías</a>";
    
    echo "<a href='fix_montacargas.php' style='display: inline-block; padding: 10px 20px; background-color: #FF9800; color: white; text-decoration: none; border-radius: 4px;'>Reparar categoría MONTACARGAS</a>";
    echo "</div>";
    
    // Explicación del procesamiento de datos
    echo "<div style='background-color: #f0f4c3; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #33691e; margin-top: 0;'>Instrucciones para corregir el problema:</h3>";
    echo "<ol>";
    echo "<li>Hemos modificado el script de importación para reconocer correctamente el formato específico de MONTACARGAS.</li>";
    echo "<li>El script ahora busca líneas que contengan 'HASTA XXX KG PUERTA MANUAL' en lugar de 'N PARADAS'.</li>";
    echo "<li>También hemos mejorado la detección de los encabezados de precios para ser más flexible con el formato '160/180 dias'.</li>";
    echo "<li>Click en 'Reconectar y actualizar datos' para reimportar los datos del Google Sheets con estos cambios.</li>";
    echo "<li>Después, verifica que las opciones para MONTACARGAS aparezcan correctamente.</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background-color: #ffebee; padding: 15px; border-radius: 5px; color: #b71c1c;'>";
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} 
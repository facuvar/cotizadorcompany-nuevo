<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Esta es una herramienta de prueba para la importación específica de datos de SALVAESCALERAS
// No requiere login para pruebas
// requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Prueba de Importación para la Categoría SALVAESCALERAS</h2>";
    
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
    
    // Comprobar si existe la categoría SALVAESCALERAS
    $query = "SELECT * FROM categorias WHERE nombre LIKE '%SALVAESCALERAS%' LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        echo "<p style='color: red;'>No se encontró la categoría SALVAESCALERAS en la base de datos.</p>";
    } else {
        $categoria = $result->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        echo "<p>Categoría SALVAESCALERAS encontrada con ID: $categoriaId</p>";
        
        // Ver cuántas opciones existen actualmente
        $opcionesCount = "SELECT COUNT(*) as total FROM opciones WHERE categoria_id = $categoriaId";
        $countResult = $conn->query($opcionesCount);
        $countRow = $countResult->fetch_assoc();
        $totalOpciones = $countRow['total'];
        
        echo "<p>Opciones actuales para SALVAESCALERAS: $totalOpciones</p>";
        
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
            
            // Mostrar precios por plazo para la primera opción
            if ($opcionesResult->num_rows > 0) {
                $opcionesResult->data_seek(0);
                $primeraOpcion = $opcionesResult->fetch_assoc();
                
                echo "<h3>Precios por plazo para la opción '" . htmlspecialchars($primeraOpcion['nombre']) . "':</h3>";
                $preciosQuery = "SELECT * FROM opcion_precios WHERE opcion_id = " . $primeraOpcion['id'];
                $preciosResult = $conn->query($preciosQuery);
                
                if ($preciosResult && $preciosResult->num_rows > 0) {
                    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                    echo "<tr style='background-color: #4CAF50; color: white;'>";
                    echo "<th>Plazo</th><th>Precio</th></tr>";
                    
                    $rowNum = 0;
                    while ($precio = $preciosResult->fetch_assoc()) {
                        $bgcolor = $rowNum % 2 === 0 ? '#f2f2f2' : 'white';
                        echo "<tr style='background-color: {$bgcolor};'>";
                        echo "<td>" . htmlspecialchars($precio['plazo_entrega']) . "</td>";
                        echo "<td>$" . number_format($precio['precio'], 2, ',', '.') . "</td>";
                        echo "</tr>";
                        $rowNum++;
                    }
                    echo "</table>";
                }
            }
        }
    }
    
    // Mostrar ejemplo de datos que se espera procesar
    echo "<h3>Formato esperado en la hoja de cálculo para SALVAESCALERAS:</h3>";
    echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "SALVAESCALERAS                                    160/180 dias    90 dias     270 dias\n";
    echo "MODELO SIMPLE H/ 1.80 M                           $  13,234,809.00    $ 17,205,251.70    $  11,911,328.10\n";
    echo "MODELO COMPLETO H/1.80 M                          $  17,350,074.00    $ 22,555,096.20    $  15,615,066.60\n";
    echo "MODELO COMPLETO H/3 M                             $  21,829,196.00    $ 28,377,954.80    $  19,646,276.40";
    echo "</pre>";
    
    // Mostrar botones de acción
    echo "<div style='margin: 20px 0;'>";
    echo "<form method='post' action='reconnect_last_file.php'>";
    echo "<button type='submit' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;'>Reconectar y actualizar datos</button>";
    echo "</form>";
    
    echo "<a href='test_sheets.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>Verificar todas las categorías</a>";
    
    echo "<a href='fix_salvaescaleras.php' style='display: inline-block; padding: 10px 20px; background-color: #FF9800; color: white; text-decoration: none; border-radius: 4px;'>Reparar categoría SALVAESCALERAS</a>";
    echo "</div>";
    
    // Explicación del procesamiento de datos
    echo "<div style='background-color: #f0f4c3; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #33691e; margin-top: 0;'>Instrucciones para corregir el problema:</h3>";
    echo "<ol>";
    echo "<li>Hemos modificado el script de importación para reconocer correctamente los modelos de SALVAESCALERAS.</li>";
    echo "<li>El script ahora busca líneas que contengan 'MODELO', 'TIPO', 'RECTO', 'CURVO' o combinaciones de 'SALVA' y 'ESCALERA'.</li>";
    echo "<li>También hemos mejorado la detección de los encabezados de precios para ser más flexible con diferentes formatos.</li>";
    echo "<li>Click en 'Reconectar y actualizar datos' para reimportar los datos del Google Sheets con estos cambios.</li>";
    echo "<li>Después, verifica que las opciones para SALVAESCALERAS aparezcan correctamente.</li>";
    echo "</ol>";
    echo "</div>";
    
    // Crear script para reparar manualmente
    echo "<h3>¿Necesitas crear opciones manualmente?</h3>";
    echo "<p>Si la importación automática no funciona correctamente, puedes crear un script 'fix_salvaescaleras.php' similar al que creamos para MONTACARGAS.</p>";
    echo "<div style='background-color: #e3f2fd; padding: 15px; border-radius: 5px;'>";
    echo "<pre style='margin: 0; overflow-x: auto;'>";
    echo "&lt;?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    \$db = Database::getInstance();
    \$conn = \$db->getConnection();
    
    echo \"&lt;h2>Verificación y Reparación de Categoría SALVAESCALERAS&lt;/h2>\";
    
    // 1. Verificar si existe la categoría
    \$query = \"SELECT * FROM categorias WHERE nombre LIKE '%SALVAESCALERAS%' LIMIT 1\";
    \$result = \$conn->query(\$query);
    
    if (!\$result || \$result->num_rows === 0) {
        echo \"&lt;p style='color: red;'>No se encontró la categoría. Vamos a crearla.&lt;/p>\";
        
        // Crear la categoría
        \$nombre = \"SALVAESCALERAS\";
        \$descripcion = \"Elevador para personas con movilidad reducida\";
        \$orden = 9;
        
        \$query = \"INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, ?)\";
        \$stmt = \$conn->prepare(\$query);
        \$stmt->bind_param('ssi', \$nombre, \$descripcion, \$orden);
        
        if (\$stmt->execute()) {
            \$categoriaId = \$conn->insert_id;
            echo \"&lt;p style='color: green;'>Categoría creada exitosamente con ID: \$categoriaId&lt;/p>\";
        } else {
            throw new Exception(\"Error al crear la categoría: \" . \$stmt->error);
        }
    } else {
        \$categoria = \$result->fetch_assoc();
        \$categoriaId = \$categoria['id'];
        
        echo \"&lt;div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>\";
        echo \"&lt;h3 style='color: #2e7d32; margin-top: 0;'>Categoría encontrada:&lt;/h3>\";
        echo \"&lt;p>&lt;strong>ID:&lt;/strong> \" . \$categoriaId . \"&lt;/p>\";
        echo \"&lt;p>&lt;strong>Nombre:&lt;/strong> \" . htmlspecialchars(\$categoria['nombre']) . \"&lt;/p>\";
        echo \"&lt;/div>\";
    }
    
    // 2. Verificar si existen opciones
    \$opcionesQuery = \"SELECT COUNT(*) as total FROM opciones WHERE categoria_id = ?\";
    \$stmt = \$conn->prepare(\$opcionesQuery);
    \$stmt->bind_param('i', \$categoriaId);
    \$stmt->execute();
    \$result = \$stmt->get_result();
    \$row = \$result->fetch_assoc();
    \$totalOpciones = \$row['total'];
    
    if (\$totalOpciones === 0) {
        echo \"&lt;p style='color: orange;'>No hay opciones. Vamos a crear algunas.&lt;/p>\";
        
        // 3. Crear opciones de ejemplo
        \$opciones = [
            [
                'nombre' => 'MODELO RECTO',
                'descripcion' => 'Salvaescaleras con recorrido recto',
                'precio' => 25000000,
                'es_obligatorio' => 1,
                'orden' => 1
            ],
            [
                'nombre' => 'MODELO CURVO',
                'descripcion' => 'Salvaescaleras con recorrido curvo',
                'precio' => 35000000,
                'es_obligatorio' => 1,
                'orden' => 2
            ]
        ];
        
        // Código para insertar las opciones...
    }
}
catch (Exception \$e) {
    echo \"&lt;div style='background-color: #ffebee; padding: 15px; border-radius: 5px;'>\";
    echo \"&lt;h3>Error&lt;/h3>\";
    echo \"&lt;p>\" . \$e->getMessage() . \"&lt;/p>\";
    echo \"&lt;/div>\";
}
?>";
    echo "</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background-color: #ffebee; padding: 15px; border-radius: 5px; color: #b71c1c;'>";
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} 
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Desactivar el requerimiento de login para pruebas
// requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Verificación de Categorías y Opciones</h2>";
    
    // Mostrar todas las categorías para diagnóstico
    echo "<h3>Todas las categorías disponibles:</h3>";
    $allCategoriesQuery = "SELECT * FROM categorias ORDER BY orden";
    $allCategoriesResult = $conn->query($allCategoriesQuery);
    
    if ($allCategoriesResult && $allCategoriesResult->num_rows > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #4CAF50; color: white;'>";
        echo "<th>ID</th><th>Nombre</th><th>Descripción</th><th>Orden</th></tr>";
        
        while ($cat = $allCategoriesResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $cat['id'] . "</td>";
            echo "<td>" . htmlspecialchars($cat['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($cat['descripcion']) . "</td>";
            echo "<td>" . $cat['orden'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No se encontraron categorías en la base de datos.</p>";
    }
    
    // Buscar específicamente la categoría MONTACARGAS
    $query = "SELECT * FROM categorias WHERE nombre LIKE '%MONTACARGAS%' LIMIT 1";
    echo "<p>Ejecutando consulta para MONTACARGAS: " . $query . "</p>";
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $categoria = $result->fetch_assoc();
        echo "<div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<h3 style='color: #2e7d32; margin-top: 0;'>Categoría MONTACARGAS encontrada:</h3>";
        echo "<p><strong>ID:</strong> " . $categoria['id'] . "</p>";
        echo "<p><strong>Nombre:</strong> " . htmlspecialchars($categoria['nombre']) . "</p>";
        echo "<p><strong>Descripción:</strong> " . htmlspecialchars($categoria['descripcion']) . "</p>";
        echo "<p><strong>Orden:</strong> " . $categoria['orden'] . "</p>";
        echo "</div>";
        
        // Buscar opciones para esta categoría
        $opcionesQuery = "SELECT * FROM opciones WHERE categoria_id = " . $categoria['id'] . " ORDER BY orden ASC";
        echo "<p>Buscando opciones con la consulta: " . $opcionesQuery . "</p>";
        
        $opcionesResult = $conn->query($opcionesQuery);
        
        if ($opcionesResult && $opcionesResult->num_rows > 0) {
            echo "<h3>Opciones para " . htmlspecialchars($categoria['nombre']) . ":</h3>";
            echo "<p style='color: green; font-weight: bold;'>Se encontraron " . $opcionesResult->num_rows . " opciones para esta categoría</p>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background-color: #4CAF50; color: white;'>";
            echo "<th>ID</th><th>Nombre</th><th>Descripción</th><th>Precio</th><th>Obligatorio</th></tr>";
            
            $rowNum = 0;
            while ($opcion = $opcionesResult->fetch_assoc()) {
                $bgcolor = $rowNum % 2 === 0 ? '#f2f2f2' : 'white';
                echo "<tr style='background-color: {$bgcolor};'>";
                echo "<td>" . $opcion['id'] . "</td>";
                echo "<td>" . htmlspecialchars($opcion['nombre']) . "</td>";
                echo "<td>" . htmlspecialchars($opcion['descripcion']) . "</td>";
                echo "<td>$" . number_format($opcion['precio'], 2, ',', '.') . "</td>";
                echo "<td>" . ($opcion['es_obligatorio'] ? 'Sí' : 'No') . "</td>";
                echo "</tr>";
                $rowNum++;
            }
            echo "</table>";
            
            // Verificar los precios por plazo para la primera opción
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
            } else {
                echo "<p style='color: red;'>No se encontraron precios por plazo para esta opción.</p>";
            }
        } else {
            echo "<p style='color: red; font-weight: bold;'>¡No se encontraron opciones para la categoría MONTACARGAS!</p>";
            
            // Comprobar si hay filas de PARADAS en el archivo de Google Sheets
            echo "<h3>Revisando el procesamiento de la hoja de MONTACARGAS</h3>";
            echo "<p>La categoría existe en la base de datos pero no tiene opciones asociadas. Vamos a revisar la estructura del archivo Google Sheets.</p>";
            
            // Consultar la fuente de datos
            $sourceQuery = "SELECT * FROM fuente_datos WHERE tipo = 'google_sheets' ORDER BY fecha_actualizacion DESC LIMIT 1";
            $sourceResult = $conn->query($sourceQuery);
            
            if ($sourceResult && $sourceResult->num_rows > 0) {
                $source = $sourceResult->fetch_assoc();
                echo "<p>Última fuente de datos Google Sheets: " . htmlspecialchars($source['url']) . "</p>";
                echo "<p>Fecha de actualización: " . date('d/m/Y H:i:s', strtotime($source['fecha_actualizacion'])) . "</p>";
                
                echo "<div style='background-color: #fff3e0; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3 style='color: #e65100; margin-top: 0;'>Posibles causas:</h3>";
                echo "<ul>";
                echo "<li>No hay filas con formato 'N PARADAS' en la sección MONTACARGAS de la hoja de cálculo</li>";
                echo "<li>Los precios para MONTACARGAS están en formato incorrecto o son cero</li>";
                echo "<li>La hoja de cálculo ha cambiado su estructura y el programa no lo reconoce correctamente</li>";
                echo "</ul>";
                echo "</div>";
            }
            
            // Mostrar enlace para reconectar
            echo "<div style='margin: 20px 0;'>";
            echo "<a href='reconnect_last_file.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Reconectar última fuente de datos</a>";
            echo "</div>";
        }
    } else {
        echo "<p style='color: red; font-weight: bold;'>No se encontró la categoría MONTACARGAS en la base de datos.</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='background-color: #ffebee; padding: 15px; border-radius: 5px; color: #b71c1c;'>";
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} 
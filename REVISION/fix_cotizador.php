<?php
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Reparación del Cotizador</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            h1, h2, h3 { color: #333; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
            .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
            .btn-blue { background-color: #2196F3; }
        </style>
    </head>
    <body>
    <h1>Reparación del Cotizador</h1>";
    
    // Crear tabla plazos_entrega si no existe
    echo "<div class='section'>";
    echo "<h2>Paso 1: Configurar tabla plazos_entrega</h2>";
    
    $sql = "CREATE TABLE IF NOT EXISTS `plazos_entrega` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nombre` varchar(100) NOT NULL,
      `descripcion` text,
      `orden` int(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ Tabla plazos_entrega creada o verificada correctamente.</p>";
    } else {
        echo "<p class='error'>❌ Error al crear tabla plazos_entrega: " . $conn->error . "</p>";
    }
    
    // Limpiar datos existentes en plazos_entrega
    $sql = "DELETE FROM `plazos_entrega`";
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ Datos de plazos_entrega limpiados correctamente.</p>";
    } else {
        echo "<p class='error'>❌ Error al limpiar datos de plazos_entrega: " . $conn->error . "</p>";
    }
    
    // Insertar plazos predeterminados
    $plazos = [
        ['nombre' => '30-60 días', 'descripcion' => 'Entrega rápida (30-60 días)', 'orden' => 1],
        ['nombre' => '60-90 días', 'descripcion' => 'Entrega estándar (60-90 días)', 'orden' => 2],
        ['nombre' => '90-120 días', 'descripcion' => 'Entrega normal (90-120 días)', 'orden' => 3],
        ['nombre' => '120-150 días', 'descripcion' => 'Entrega programada (120-150 días)', 'orden' => 4],
        ['nombre' => '150-180 días', 'descripcion' => 'Entrega extendida (150-180 días)', 'orden' => 5],
        ['nombre' => '180-210 días', 'descripcion' => 'Entrega económica (180-210 días)', 'orden' => 6]
    ];
    
    $plazosInsertados = 0;
    foreach ($plazos as $plazo) {
        $sql = "INSERT INTO `plazos_entrega` (`nombre`, `descripcion`, `orden`) VALUES (
            '" . $conn->real_escape_string($plazo['nombre']) . "', 
            '" . $conn->real_escape_string($plazo['descripcion']) . "', 
            " . intval($plazo['orden']) . "
        )";
        
        if ($conn->query($sql)) {
            $plazosInsertados++;
        } else {
            echo "<p class='error'>❌ Error al insertar plazo '{$plazo['nombre']}': " . $conn->error . "</p>";
        }
    }
    
    echo "<p class='success'>✅ Se insertaron $plazosInsertados plazos de entrega.</p>";
    echo "</div>";
    
    // Crear tabla opcion_precios si no existe
    echo "<div class='section'>";
    echo "<h2>Paso 2: Configurar tabla opcion_precios</h2>";
    
    $sql = "CREATE TABLE IF NOT EXISTS `opcion_precios` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `opcion_id` int(11) NOT NULL,
      `plazo_entrega` varchar(100) NOT NULL,
      `precio` decimal(15,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ Tabla opcion_precios creada o verificada correctamente.</p>";
    } else {
        echo "<p class='error'>❌ Error al crear tabla opcion_precios: " . $conn->error . "</p>";
    }
    
    // Limpiar datos existentes en opcion_precios
    $sql = "DELETE FROM `opcion_precios`";
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ Datos de opcion_precios limpiados correctamente.</p>";
    } else {
        echo "<p class='error'>❌ Error al limpiar datos de opcion_precios: " . $conn->error . "</p>";
    }
    
    // Generar precios para todas las opciones y plazos
    echo "<div class='section'>";
    echo "<h2>Paso 3: Generar precios para opciones</h2>";
    
    // Obtener todas las opciones
    $result = $conn->query("SELECT id, precio FROM opciones");
    
    if ($result->num_rows === 0) {
        echo "<p class='error'>❌ No hay opciones en la base de datos.</p>";
    } else {
        echo "<p>Se encontraron {$result->num_rows} opciones en la base de datos.</p>";
        
        // Obtener todos los plazos
        $plazosResult = $conn->query("SELECT nombre FROM plazos_entrega ORDER BY orden");
        
        if ($plazosResult->num_rows === 0) {
            echo "<p class='error'>❌ No hay plazos de entrega en la base de datos.</p>";
        } else {
            echo "<p>Se encontraron {$plazosResult->num_rows} plazos de entrega.</p>";
            
            // Generar precios para cada opción y plazo
            $totalInsertados = 0;
            
            while ($opcion = $result->fetch_assoc()) {
                $plazosResult->data_seek(0); // Reiniciar el puntero
                
                while ($plazo = $plazosResult->fetch_assoc()) {
                    $plazoNombre = $plazo['nombre'];
                    $precio = $opcion['precio'];
                    
                    // Aplicar multiplicador según el plazo
                    if (strpos($plazoNombre, '30-60') !== false) {
                        $precio = $precio * 1.15; // 15% más caro para entrega rápida
                    } elseif (strpos($plazoNombre, '60-90') !== false) {
                        $precio = $precio * 1.10; // 10% más caro
                    } elseif (strpos($plazoNombre, '90-120') !== false) {
                        $precio = $precio * 1.05; // 5% más caro
                    } elseif (strpos($plazoNombre, '150-180') !== false) {
                        $precio = $precio * 0.95; // 5% más barato
                    } elseif (strpos($plazoNombre, '180-210') !== false) {
                        $precio = $precio * 0.90; // 10% más barato
                    }
                    
                    $sql = "INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (
                        " . intval($opcion['id']) . ", 
                        '" . $conn->real_escape_string($plazoNombre) . "', 
                        " . floatval($precio) . "
                    )";
                    
                    if ($conn->query($sql)) {
                        $totalInsertados++;
                    } else {
                        echo "<p class='error'>❌ Error al insertar precio para opción ID {$opcion['id']} y plazo {$plazoNombre}: " . $conn->error . "</p>";
                    }
                }
            }
            
            echo "<p class='success'>✅ Se insertaron {$totalInsertados} registros de precios.</p>";
        }
    }
    echo "</div>";
    
    // Finalización
    echo "<div class='section'>";
    echo "<h2>Reparación completada</h2>";
    echo "<p>Se han realizado las correcciones necesarias para que el cotizador funcione correctamente.</p>";
    echo "<p>Ahora puedes acceder al cotizador y verificar que muestra correctamente las opciones, precios y plazos.</p>";
    echo "<p><a href='sistema/cotizador.php' class='btn'>Ir al Cotizador</a> <a href='verificar_estado.php' class='btn btn-blue'>Verificar Estado</a></p>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

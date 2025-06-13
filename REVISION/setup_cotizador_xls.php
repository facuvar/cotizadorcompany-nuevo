<?php
// Script para configurar la base de datos para el cotizador basado en XLS
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';

// Función para mostrar mensajes
function mostrarMensaje($mensaje, $tipo = 'info') {
    $color = 'black';
    if ($tipo == 'success') $color = 'green';
    if ($tipo == 'error') $color = 'red';
    if ($tipo == 'warning') $color = 'orange';
    
    echo "<p style='color: $color;'>$mensaje</p>";
}

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Configuración del Cotizador XLS</title>
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
    <h1>Configuración del Cotizador XLS</h1>";
    
    // 1. Crear tabla de productos
    echo "<div class='section'>";
    echo "<h2>Creando tabla de productos</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_productos";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_productos (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT,
        orden INT(11) DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_productos' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_productos': " . $conn->error, "error");
    }
    
    // Insertar productos predeterminados
    $productos = [
        ["nombre" => "EQUIPO ELECTROMECANICO 450KG CARGA UTIL", "orden" => 1],
        ["nombre" => "OPCION GEARLESS", "orden" => 2],
        ["nombre" => "HIDRAULICO 450KG CENTRAL 13HP PISTON 1 TRAMO", "orden" => 3],
        ["nombre" => "HIDRAULICO 450KG CENTRAL 25LTS 4HP", "orden" => 4],
        ["nombre" => "MISMA CARACT PERO DIRECTO", "orden" => 5],
        ["nombre" => "DOMICILIARIO PUERTA PLEGADIZA CABINA EXT SIN PUERTAS", "orden" => 6],
        ["nombre" => "MONTAVEHICULOS", "orden" => 7],
        ["nombre" => "MONTACARGAS - MAQUINA TAMBOR", "orden" => 8],
        ["nombre" => "SALVAESCALERAS", "orden" => 9],
        ["nombre" => "ESCALERAS MECANICAS - VIDRIADO - FALDON ACERO", "orden" => 10],
        ["nombre" => "MONTAPLATOS", "orden" => 11],
        ["nombre" => "GIRACOCHES", "orden" => 12],
        ["nombre" => "ESTRUCTURA", "orden" => 13],
        ["nombre" => "PEFIL DIVISORIO", "orden" => 14]
    ];
    
    foreach ($productos as $producto) {
        $sql = "INSERT INTO xls_productos (nombre, orden) 
                VALUES ('{$producto['nombre']}', {$producto['orden']})";
        
        if ($conn->query($sql) === TRUE) {
            mostrarMensaje("Producto '{$producto['nombre']}' agregado correctamente.", "success");
        } else {
            mostrarMensaje("Error al agregar el producto '{$producto['nombre']}': " . $conn->error, "error");
        }
    }
    
    echo "</div>";
    
    // 2. Crear tabla de opciones
    echo "<div class='section'>";
    echo "<h2>Creando tabla de opciones</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_opciones";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_opciones (
        id INT(11) NOT NULL AUTO_INCREMENT,
        producto_id INT(11) NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT,
        precio_base DECIMAL(10,2) DEFAULT 0,
        orden INT(11) DEFAULT 0,
        PRIMARY KEY (id),
        FOREIGN KEY (producto_id) REFERENCES xls_productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_opciones' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_opciones': " . $conn->error, "error");
    }
    
    // Insertar opciones para EQUIPO ELECTROMECANICO
    $result = $conn->query("SELECT id FROM xls_productos WHERE nombre = 'EQUIPO ELECTROMECANICO 450KG CARGA UTIL'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $productoId = $row['id'];
        
        $opciones = [
            ["nombre" => "4 PARADAS", "precio_base" => 6541500.00, "orden" => 1],
            ["nombre" => "5 PARADAS", "precio_base" => 6786500.00, "orden" => 2],
            ["nombre" => "6 PARADAS", "precio_base" => 7031500.00, "orden" => 3],
            ["nombre" => "7 PARADAS", "precio_base" => 7252000.00, "orden" => 4],
            ["nombre" => "8 PARADAS", "precio_base" => 7497000.00, "orden" => 5],
            ["nombre" => "9 PARADAS", "precio_base" => 7742000.00, "orden" => 6],
            ["nombre" => "10 PARADAS", "precio_base" => 7987000.00, "orden" => 7],
            ["nombre" => "11 PARADAS", "precio_base" => 8207500.00, "orden" => 8],
            ["nombre" => "12 PARADAS", "precio_base" => 8452500.00, "orden" => 9],
            ["nombre" => "13 PARADAS", "precio_base" => 8697500.00, "orden" => 10],
            ["nombre" => "14 PARADAS", "precio_base" => 8918000.00, "orden" => 11],
            ["nombre" => "15 PARADAS", "precio_base" => 9163000.00, "orden" => 12]
        ];
        
        foreach ($opciones as $opcion) {
            $sql = "INSERT INTO xls_opciones (producto_id, nombre, precio_base, orden) 
                    VALUES ($productoId, '{$opcion['nombre']}', {$opcion['precio_base']}, {$opcion['orden']})";
            
            if ($conn->query($sql) === TRUE) {
                mostrarMensaje("Opción '{$opcion['nombre']}' agregada correctamente.", "success");
            } else {
                mostrarMensaje("Error al agregar la opción '{$opcion['nombre']}': " . $conn->error, "error");
            }
        }
    }
    
    echo "</div>";
    
    // 3. Crear tabla de plazos de entrega
    echo "<div class='section'>";
    echo "<h2>Creando tabla de plazos de entrega</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_plazos";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_plazos (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT,
        factor DECIMAL(10,2) DEFAULT 1.00,
        orden INT(11) DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_plazos' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_plazos': " . $conn->error, "error");
    }
    
    // Insertar plazos predeterminados
    $plazos = [
        ["nombre" => "90 días", "descripcion" => "Entrega rápida (90 días)", "factor" => 1.30, "orden" => 1],
        ["nombre" => "160-180 días", "descripcion" => "Entrega estándar (160-180 días)", "factor" => 1.00, "orden" => 2],
        ["nombre" => "270 días", "descripcion" => "Entrega económica (270 días)", "factor" => 0.90, "orden" => 3]
    ];
    
    foreach ($plazos as $plazo) {
        $sql = "INSERT INTO xls_plazos (nombre, descripcion, factor, orden) 
                VALUES ('{$plazo['nombre']}', '{$plazo['descripcion']}', {$plazo['factor']}, {$plazo['orden']})";
        
        if ($conn->query($sql) === TRUE) {
            mostrarMensaje("Plazo '{$plazo['nombre']}' agregado correctamente.", "success");
        } else {
            mostrarMensaje("Error al agregar el plazo '{$plazo['nombre']}': " . $conn->error, "error");
        }
    }
    
    echo "</div>";
    
    // 4. Crear tabla de precios
    echo "<div class='section'>";
    echo "<h2>Creando tabla de precios</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_precios";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_precios (
        id INT(11) NOT NULL AUTO_INCREMENT,
        opcion_id INT(11) NOT NULL,
        plazo_id INT(11) NOT NULL,
        precio DECIMAL(15,2) DEFAULT 0,
        PRIMARY KEY (id),
        FOREIGN KEY (opcion_id) REFERENCES xls_opciones(id) ON DELETE CASCADE,
        FOREIGN KEY (plazo_id) REFERENCES xls_plazos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_precios' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_precios': " . $conn->error, "error");
    }
    
    // Insertar precios para EQUIPO ELECTROMECANICO según plazos
    $result = $conn->query("SELECT id FROM xls_productos WHERE nombre = 'EQUIPO ELECTROMECANICO 450KG CARGA UTIL'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $productoId = $row['id'];
        
        // Obtener opciones
        $opcionesResult = $conn->query("SELECT id, nombre, precio_base FROM xls_opciones WHERE producto_id = $productoId ORDER BY orden");
        
        // Obtener plazos
        $plazosResult = $conn->query("SELECT id, nombre, factor FROM xls_plazos ORDER BY orden");
        
        if ($opcionesResult && $opcionesResult->num_rows > 0 && $plazosResult && $plazosResult->num_rows > 0) {
            // Precios específicos del XLS para EQUIPO ELECTROMECANICO
            $preciosXLS = [
                // [opcion_nombre, precio_90dias, precio_160_180dias, precio_270dias]
                ["4 PARADAS", 44865873.00, 34512210.00, 31060989.00],
                ["5 PARADAS", 46198531.60, 35537332.00, 31983598.80],
                ["6 PARADAS", 47553727.00, 36579790.00, 32921811.00],
                ["7 PARADAS", 50335821.90, 38719863.00, 34847876.70],
                ["8 PARADAS", 50163733.10, 38587487.00, 34728738.30],
                ["9 PARADAS", 51483077.10, 39602367.00, 35642130.30],
                ["10 PARADAS", 52848857.10, 40652967.00, 36587670.30],
                ["11 PARADAS", 54064401.30, 41588001.00, 37429200.90],
                ["12 PARADAS", 55412425.90, 42624943.00, 38362448.70],
                ["13 PARADAS", 56768645.70, 43668189.00, 39301370.10],
                ["14 PARADAS", 57988286.20, 44606374.00, 40145736.60],
                ["15 PARADAS", 59343141.00, 45648570.00, 41083713.00]
            ];
            
            // Crear un mapa de nombres de opciones a IDs
            $opcionesMap = [];
            while ($opcion = $opcionesResult->fetch_assoc()) {
                $opcionesMap[$opcion['nombre']] = $opcion['id'];
            }
            
            // Crear un mapa de nombres de plazos a IDs
            $plazosMap = [];
            while ($plazo = $plazosResult->fetch_assoc()) {
                $plazosMap[$plazo['nombre']] = $plazo['id'];
            }
            
            // Insertar precios específicos
            foreach ($preciosXLS as $precio) {
                $opcionNombre = $precio[0];
                $precio90dias = $precio[1];
                $precio160_180dias = $precio[2];
                $precio270dias = $precio[3];
                
                if (isset($opcionesMap[$opcionNombre])) {
                    $opcionId = $opcionesMap[$opcionNombre];
                    
                    // Precio para 90 días
                    if (isset($plazosMap["90 días"])) {
                        $plazoId = $plazosMap["90 días"];
                        $sql = "INSERT INTO xls_precios (opcion_id, plazo_id, precio) 
                                VALUES ($opcionId, $plazoId, $precio90dias)";
                        
                        if ($conn->query($sql) === TRUE) {
                            mostrarMensaje("Precio para '{$opcionNombre}' con plazo '90 días' agregado correctamente.", "success");
                        } else {
                            mostrarMensaje("Error al agregar el precio: " . $conn->error, "error");
                        }
                    }
                    
                    // Precio para 160-180 días
                    if (isset($plazosMap["160-180 días"])) {
                        $plazoId = $plazosMap["160-180 días"];
                        $sql = "INSERT INTO xls_precios (opcion_id, plazo_id, precio) 
                                VALUES ($opcionId, $plazoId, $precio160_180dias)";
                        
                        if ($conn->query($sql) === TRUE) {
                            mostrarMensaje("Precio para '{$opcionNombre}' con plazo '160-180 días' agregado correctamente.", "success");
                        } else {
                            mostrarMensaje("Error al agregar el precio: " . $conn->error, "error");
                        }
                    }
                    
                    // Precio para 270 días
                    if (isset($plazosMap["270 días"])) {
                        $plazoId = $plazosMap["270 días"];
                        $sql = "INSERT INTO xls_precios (opcion_id, plazo_id, precio) 
                                VALUES ($opcionId, $plazoId, $precio270dias)";
                        
                        if ($conn->query($sql) === TRUE) {
                            mostrarMensaje("Precio para '{$opcionNombre}' con plazo '270 días' agregado correctamente.", "success");
                        } else {
                            mostrarMensaje("Error al agregar el precio: " . $conn->error, "error");
                        }
                    }
                }
            }
        }
    }
    
    echo "</div>";
    
    // 5. Crear tabla de adicionales
    echo "<div class='section'>";
    echo "<h2>Creando tabla de adicionales</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_adicionales";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_adicionales (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        precio_base DECIMAL(15,2) DEFAULT 0,
        orden INT(11) DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_adicionales' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_adicionales': " . $conn->error, "error");
    }
    
    // Insertar adicionales para ELECTROMECANICOS
    $adicionales = [
        ["nombre" => "ADICIONAL 750KG MAQUINA", "tipo" => "ELECTROMECANICOS", "precio_base" => 637000.00, "orden" => 1],
        ["nombre" => "ADICIONAL CABINA 2,25M3", "tipo" => "ELECTROMECANICOS", "precio_base" => 73500.00, "orden" => 2],
        ["nombre" => "ADICIONAL 1000KG MAQUINA", "tipo" => "ELECTROMECANICOS", "precio_base" => 796250.00, "orden" => 3],
        ["nombre" => "ADICIONAL CABINA 2,66", "tipo" => "ELECTROMECANICOS", "precio_base" => 129850.00, "orden" => 4],
        ["nombre" => "ADICIONAL ACCESO CABINA EN ACERO", "tipo" => "ELECTROMECANICOS", "precio_base" => 343000.00, "orden" => 5],
        ["nombre" => "ADICIONAL ACERO PISOS", "tipo" => "ELECTROMECANICOS", "precio_base" => 75950.00, "orden" => 6],
        ["nombre" => "ADICIONAL LATERAL PANORAMICO", "tipo" => "ELECTROMECANICOS", "precio_base" => 110250.00, "orden" => 7]
    ];
    
    foreach ($adicionales as $adicional) {
        $sql = "INSERT INTO xls_adicionales (nombre, tipo, precio_base, orden) 
                VALUES ('{$adicional['nombre']}', '{$adicional['tipo']}', {$adicional['precio_base']}, {$adicional['orden']})";
        
        if ($conn->query($sql) === TRUE) {
            mostrarMensaje("Adicional '{$adicional['nombre']}' agregado correctamente.", "success");
        } else {
            mostrarMensaje("Error al agregar el adicional '{$adicional['nombre']}': " . $conn->error, "error");
        }
    }
    
    echo "</div>";
    
    // 6. Crear tabla de relación entre productos y adicionales
    echo "<div class='section'>";
    echo "<h2>Creando tabla de relación entre productos y adicionales</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_productos_adicionales";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_productos_adicionales (
        id INT(11) NOT NULL AUTO_INCREMENT,
        producto_id INT(11) NOT NULL,
        adicional_id INT(11) NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (producto_id) REFERENCES xls_productos(id) ON DELETE CASCADE,
        FOREIGN KEY (adicional_id) REFERENCES xls_adicionales(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_productos_adicionales' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_productos_adicionales': " . $conn->error, "error");
    }
    
    // Relacionar adicionales con EQUIPO ELECTROMECANICO
    $result = $conn->query("SELECT id FROM xls_productos WHERE nombre = 'EQUIPO ELECTROMECANICO 450KG CARGA UTIL'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $productoId = $row['id'];
        
        $adicionalesResult = $conn->query("SELECT id FROM xls_adicionales WHERE tipo = 'ELECTROMECANICOS'");
        
        if ($adicionalesResult && $adicionalesResult->num_rows > 0) {
            while ($adicional = $adicionalesResult->fetch_assoc()) {
                $adicionalId = $adicional['id'];
                
                $sql = "INSERT INTO xls_productos_adicionales (producto_id, adicional_id) 
                        VALUES ($productoId, $adicionalId)";
                
                if ($conn->query($sql) === TRUE) {
                    mostrarMensaje("Relación entre producto y adicional creada correctamente.", "success");
                } else {
                    mostrarMensaje("Error al crear la relación: " . $conn->error, "error");
                }
            }
        }
    }
    
    echo "</div>";
    
    // 7. Crear tabla de precios para adicionales
    echo "<div class='section'>";
    echo "<h2>Creando tabla de precios para adicionales</h2>";
    
    $sql = "DROP TABLE IF EXISTS xls_adicionales_precios";
    $conn->query($sql);
    
    $sql = "CREATE TABLE xls_adicionales_precios (
        id INT(11) NOT NULL AUTO_INCREMENT,
        adicional_id INT(11) NOT NULL,
        plazo_id INT(11) NOT NULL,
        precio DECIMAL(15,2) DEFAULT 0,
        PRIMARY KEY (id),
        FOREIGN KEY (adicional_id) REFERENCES xls_adicionales(id) ON DELETE CASCADE,
        FOREIGN KEY (plazo_id) REFERENCES xls_plazos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql) === TRUE) {
        mostrarMensaje("Tabla 'xls_adicionales_precios' creada correctamente.", "success");
    } else {
        mostrarMensaje("Error al crear la tabla 'xls_adicionales_precios': " . $conn->error, "error");
    }
    
    // Insertar precios para adicionales según plazos
    $adicionalesResult = $conn->query("SELECT id, nombre, precio_base FROM xls_adicionales WHERE tipo = 'ELECTROMECANICOS'");
    $plazosResult = $conn->query("SELECT id, nombre, factor FROM xls_plazos ORDER BY orden");
    
    if ($adicionalesResult && $adicionalesResult->num_rows > 0 && $plazosResult && $plazosResult->num_rows > 0) {
        // Precios específicos del XLS para adicionales
        $preciosXLS = [
            // [adicional_nombre, precio_90dias, precio_160_180dias, precio_270dias]
            ["ADICIONAL 750KG MAQUINA", 3194919.00, 2457630.00, 2211867.00],
            ["ADICIONAL CABINA 2,25M3", 363490.40, 279608.00, 251647.20],
            ["ADICIONAL 1000KG MAQUINA", 3991286.00, 3070220.00, 2763198.00],
            ["ADICIONAL CABINA 2,66", 641234.10, 493257.00, 443931.30],
            ["ADICIONAL ACCESO CABINA EN ACERO", 1696292.00, 1304840.00, 1174356.00],
            ["ADICIONAL ACERO PISOS", 374674.30, 288211.00, 259389.90],
            ["ADICIONAL LATERAL PANORAMICO", 544302.20, 418694.00, 376824.60]
        ];
        
        // Crear un mapa de nombres de adicionales a IDs
        $adicionalesMap = [];
        while ($adicional = $adicionalesResult->fetch_assoc()) {
            $adicionalesMap[$adicional['nombre']] = $adicional['id'];
        }
        
        // Crear un mapa de nombres de plazos a IDs
        $plazosMap = [];
        while ($plazo = $plazosResult->fetch_assoc()) {
            $plazosMap[$plazo['nombre']] = $plazo['id'];
        }
        
        // Insertar precios específicos
        foreach ($preciosXLS as $precio) {
            $adicionalNombre = $precio[0];
            $precio90dias = $precio[1];
            $precio160_180dias = $precio[2];
            $precio270dias = $precio[3];
            
            if (isset($adicionalesMap[$adicionalNombre])) {
                $adicionalId = $adicionalesMap[$adicionalNombre];
                
                // Precio para 90 días
                if (isset($plazosMap["90 días"])) {
                    $plazoId = $plazosMap["90 días"];
                    $sql = "INSERT INTO xls_adicionales_precios (adicional_id, plazo_id, precio) 
                            VALUES ($adicionalId, $plazoId, $precio90dias)";
                    
                    if ($conn->query($sql) === TRUE) {
                        mostrarMensaje("Precio para '{$adicionalNombre}' con plazo '90 días' agregado correctamente.", "success");
                    } else {
                        mostrarMensaje("Error al agregar el precio: " . $conn->error, "error");
                    }
                }
                
                // Precio para 160-180 días
                if (isset($plazosMap["160-180 días"])) {
                    $plazoId = $plazosMap["160-180 días"];
                    $sql = "INSERT INTO xls_adicionales_precios (adicional_id, plazo_id, precio) 
                            VALUES ($adicionalId, $plazoId, $precio160_180dias)";
                    
                    if ($conn->query($sql) === TRUE) {
                        mostrarMensaje("Precio para '{$adicionalNombre}' con plazo '160-180 días' agregado correctamente.", "success");
                    } else {
                        mostrarMensaje("Error al agregar el precio: " . $conn->error, "error");
                    }
                }
                
                // Precio para 270 días
                if (isset($plazosMap["270 días"])) {
                    $plazoId = $plazosMap["270 días"];
                    $sql = "INSERT INTO xls_adicionales_precios (adicional_id, plazo_id, precio) 
                            VALUES ($adicionalId, $plazoId, $precio270dias)";
                    
                    if ($conn->query($sql) === TRUE) {
                        mostrarMensaje("Precio para '{$adicionalNombre}' con plazo '270 días' agregado correctamente.", "success");
                    } else {
                        mostrarMensaje("Error al agregar el precio: " . $conn->error, "error");
                    }
                }
            }
        }
    }
    
    echo "</div>";
    
    // Enlace al cotizador
    echo "<div class='section'>";
    echo "<h2>Configuración completada</h2>";
    echo "<p>La configuración del cotizador XLS ha sido realizada correctamente.</p>";
    echo "<p>Ahora puede acceder al cotizador XLS:</p>";
    echo "<a href='cotizador_xls.php' class='btn'>Ir al Cotizador XLS</a>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

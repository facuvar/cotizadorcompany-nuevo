<?php
// Script para sincronizar las tablas del cotizador
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
        <title>Sincronizar Cotizador</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            h1, h2, h3 { color: #333; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .card { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: white; }
            .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; border: none; cursor: pointer; }
        </style>
    </head>
    <body>
        <h1>Sincronizar Cotizador</h1>";
    
    // Verificar si se solicitó la sincronización
    if (isset($_POST['sincronizar'])) {
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // 1. Verificar tablas necesarias para el cotizador
            $tablas = ['productos', 'opciones', 'plazos_entrega', 'opcion_precios'];
            foreach ($tablas as $tabla) {
                $result = $conn->query("SHOW TABLES LIKE '$tabla'");
                if ($result->num_rows == 0) {
                    // Crear la tabla si no existe
                    if ($tabla == 'productos') {
                        $conn->query("CREATE TABLE productos (
                            id INT(11) NOT NULL AUTO_INCREMENT,
                            nombre VARCHAR(255) NOT NULL,
                            PRIMARY KEY (id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        mostrarMensaje("Tabla productos creada", "success");
                    } else if ($tabla == 'opciones') {
                        $conn->query("CREATE TABLE opciones (
                            id INT(11) NOT NULL AUTO_INCREMENT,
                            producto_id INT(11) NOT NULL,
                            nombre VARCHAR(255) NOT NULL,
                            PRIMARY KEY (id),
                            KEY producto_id (producto_id),
                            CONSTRAINT fk_opciones_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        mostrarMensaje("Tabla opciones creada", "success");
                    } else if ($tabla == 'plazos_entrega') {
                        $conn->query("CREATE TABLE plazos_entrega (
                            id INT(11) NOT NULL AUTO_INCREMENT,
                            nombre VARCHAR(100) NOT NULL,
                            multiplicador DECIMAL(5,2) DEFAULT 1.00,
                            PRIMARY KEY (id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        
                        // Insertar plazos predeterminados
                        $conn->query("INSERT INTO plazos_entrega (nombre, multiplicador) VALUES 
                            ('160-180 dias', 1.00),
                            ('90 dias', 1.30),
                            ('270 dias', 0.90)");
                        
                        mostrarMensaje("Tabla plazos_entrega creada con plazos predeterminados", "success");
                    } else if ($tabla == 'opcion_precios') {
                        $conn->query("CREATE TABLE opcion_precios (
                            id INT(11) NOT NULL AUTO_INCREMENT,
                            opcion_id INT(11) NOT NULL,
                            plazo_entrega VARCHAR(100) NOT NULL,
                            precio DECIMAL(15,2) NOT NULL,
                            PRIMARY KEY (id),
                            KEY opcion_id (opcion_id),
                            CONSTRAINT fk_opcion_precios_opcion FOREIGN KEY (opcion_id) REFERENCES opciones (id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        mostrarMensaje("Tabla opcion_precios creada", "success");
                    }
                } else {
                    mostrarMensaje("La tabla $tabla ya existe", "info");
                }
            }
            
            // Desactivar restricciones de clave foránea
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            // 2. Sincronizar productos
            mostrarMensaje("Sincronizando productos...", "info");
            $conn->query("TRUNCATE TABLE productos");
            $conn->query("INSERT INTO productos (id, nombre) SELECT id, nombre FROM xls_productos");
            $productosCount = $conn->affected_rows;
            mostrarMensaje("$productosCount productos sincronizados", "success");
            
            // 3. Sincronizar opciones
            mostrarMensaje("Sincronizando opciones...", "info");
            $conn->query("TRUNCATE TABLE opciones");
            $conn->query("INSERT INTO opciones (id, producto_id, nombre) SELECT id, producto_id, nombre FROM xls_opciones");
            $opcionesCount = $conn->affected_rows;
            mostrarMensaje("$opcionesCount opciones sincronizadas", "success");
            
            // 4. Sincronizar plazos_entrega
            mostrarMensaje("Sincronizando plazos de entrega...", "info");
            $conn->query("TRUNCATE TABLE plazos_entrega");
            
            // Obtener plazos de xls_plazos
            $result = $conn->query("SELECT * FROM xls_plazos");
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $nombre = $row['nombre'];
                    $multiplicador = 1.0; // Valor predeterminado
                    
                    // Determinar multiplicador según el nombre
                    if (strpos($nombre, "90") !== false) {
                        $multiplicador = 1.3; // 30% adicional
                    } else if (strpos($nombre, "270") !== false) {
                        $multiplicador = 0.9; // 10% descuento
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, multiplicador) VALUES (?, ?)");
                    $stmt->bind_param("sd", $nombre, $multiplicador);
                    $stmt->execute();
                }
                mostrarMensaje("Plazos de entrega sincronizados", "success");
            } else {
                // Insertar plazos predeterminados
                $conn->query("INSERT INTO plazos_entrega (nombre, multiplicador) VALUES 
                    ('160-180 dias', 1.00),
                    ('90 dias', 1.30),
                    ('270 dias', 0.90)");
                mostrarMensaje("Plazos predeterminados insertados", "success");
            }
            
            // 5. Sincronizar opcion_precios
            mostrarMensaje("Sincronizando precios de opciones...", "info");
            $conn->query("TRUNCATE TABLE opcion_precios");
            
            // Obtener precios de xls_precios
            $result = $conn->query("
                SELECT xp.opcion_id, xpl.nombre AS plazo_entrega, xp.precio
                FROM xls_precios xp
                JOIN xls_plazos xpl ON xp.plazo_id = xpl.id
            ");
            
            if ($result->num_rows > 0) {
                $preciosCount = 0;
                while ($row = $result->fetch_assoc()) {
                    $opcionId = $row['opcion_id'];
                    $plazoEntrega = $row['plazo_entrega'];
                    $precio = $row['precio'];
                    
                    $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)");
                    $stmt->bind_param("isd", $opcionId, $plazoEntrega, $precio);
                    $stmt->execute();
                    $preciosCount++;
                }
                mostrarMensaje("$preciosCount precios sincronizados", "success");
            } else {
                mostrarMensaje("No se encontraron precios para sincronizar", "warning");
            }
            
            // Reactivar restricciones de clave foránea
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            
            // Confirmar transacción
            $conn->commit();
            
            mostrarMensaje("Sincronización completada correctamente", "success");
            echo "<p><a href='cotizador_xls_fixed.php' class='btn'>Ir al Cotizador</a></p>";
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            $conn->rollback();
            mostrarMensaje("Error al sincronizar: " . $e->getMessage(), "error");
        }
    } else {
        // Mostrar formulario de sincronización
        echo "
        <div class='card'>
            <p>Este script sincronizará las tablas necesarias para que el cotizador funcione correctamente.</p>
            <p>Se realizarán las siguientes acciones:</p>
            <ul>
                <li>Verificar y crear las tablas necesarias si no existen</li>
                <li>Sincronizar productos desde xls_productos a productos</li>
                <li>Sincronizar opciones desde xls_opciones a opciones</li>
                <li>Sincronizar plazos de entrega desde xls_plazos a plazos_entrega</li>
                <li>Sincronizar precios desde xls_precios a opcion_precios</li>
            </ul>
            
            <form method='post'>
                <button type='submit' name='sincronizar' class='btn'>Sincronizar Cotizador</button>
            </form>
        </div>";
    }
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
}
?>

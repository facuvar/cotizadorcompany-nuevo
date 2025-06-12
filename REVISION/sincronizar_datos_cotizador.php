<?php
// Script para sincronizar directamente los datos del Excel con las tablas del cotizador
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
        <title>Sincronizar Datos del Cotizador</title>
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
        <h1>Sincronizar Datos del Cotizador</h1>";
    
    // Verificar si se solicitó la sincronización
    if (isset($_POST['sincronizar'])) {
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Desactivar restricciones de clave foránea
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            // 1. Sincronizar categorías
            mostrarMensaje("Sincronizando categorías...", "info");
            
            // Verificar si la tabla categorias existe
            $result = $conn->query("SHOW TABLES LIKE 'categorias'");
            if ($result->num_rows == 0) {
                // Crear la tabla categorias
                $conn->query("CREATE TABLE categorias (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    nombre VARCHAR(255) NOT NULL,
                    descripcion TEXT,
                    orden INT(11) DEFAULT 0,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                mostrarMensaje("Tabla categorias creada", "success");
            }
            
            // Insertar categorías predeterminadas
            $conn->query("TRUNCATE TABLE categorias");
            $conn->query("INSERT INTO categorias (id, nombre, descripcion, orden) VALUES 
                (1, 'ASCENSORES', 'Equipos electromecanicos', 1),
                (2, 'ADICIONALES', 'Características adicionales', 2),
                (3, 'DESCUENTOS', 'Descuentos aplicables', 3)");
            mostrarMensaje("Categorías sincronizadas", "success");
            
            // 2. Sincronizar productos directamente desde xls_productos a opciones
            mostrarMensaje("Sincronizando productos y opciones...", "info");
            
            // Verificar si la tabla opciones existe
            $result = $conn->query("SHOW TABLES LIKE 'opciones'");
            if ($result->num_rows == 0) {
                // Crear la tabla opciones
                $conn->query("CREATE TABLE opciones (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    categoria_id INT(11) NOT NULL,
                    nombre VARCHAR(255) NOT NULL,
                    descripcion TEXT,
                    precio DECIMAL(15,2) DEFAULT 0.00,
                    orden INT(11) DEFAULT 0,
                    PRIMARY KEY (id),
                    KEY categoria_id (categoria_id),
                    CONSTRAINT fk_opciones_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                mostrarMensaje("Tabla opciones creada", "success");
            }
            
            // Limpiar tabla opciones
            $conn->query("TRUNCATE TABLE opciones");
            
            // Obtener productos de xls_productos
            $result = $conn->query("SELECT * FROM xls_productos");
            $productosCount = 0;
            
            if ($result && $result->num_rows > 0) {
                while ($producto = $result->fetch_assoc()) {
                    $productoId = $producto['id'];
                    $productoNombre = $producto['nombre'];
                    $categoriaId = 1; // Por defecto, categoría ASCENSORES
                    
                    // Determinar la categoría según el nombre del producto
                    if (strpos(strtoupper($productoNombre), 'ADICIONAL') !== false) {
                        $categoriaId = 2; // Categoría ADICIONALES
                    } else if (strpos(strtoupper($productoNombre), 'DESCUENTO') !== false) {
                        $categoriaId = 3; // Categoría DESCUENTOS
                    }
                    
                    // Obtener opciones para este producto
                    $opcionesResult = $conn->query("SELECT * FROM xls_opciones WHERE producto_id = $productoId");
                    
                    if ($opcionesResult && $opcionesResult->num_rows > 0) {
                        while ($opcion = $opcionesResult->fetch_assoc()) {
                            $opcionId = $opcion['id'];
                            $opcionNombre = $opcion['nombre'];
                            
                            // Obtener el precio base (usamos el primer precio que encontremos)
                            $precioBase = 0;
                            $precioResult = $conn->query("SELECT precio FROM xls_precios WHERE opcion_id = $opcionId LIMIT 1");
                            
                            if ($precioResult && $precioResult->num_rows > 0) {
                                $precioRow = $precioResult->fetch_assoc();
                                $precioBase = $precioRow['precio'];
                            }
                            
                            // Insertar en la tabla opciones
                            $stmt = $conn->prepare("INSERT INTO opciones (id, categoria_id, nombre, descripcion, precio, orden) VALUES (?, ?, ?, ?, ?, ?)");
                            $descripcion = "Producto: $productoNombre";
                            $orden = $opcionId;
                            $stmt->bind_param("iissdi", $opcionId, $categoriaId, $opcionNombre, $descripcion, $precioBase, $orden);
                            $stmt->execute();
                            $productosCount++;
                        }
                    } else {
                        // Si no hay opciones, crear una opción con el nombre del producto
                        $stmt = $conn->prepare("INSERT INTO opciones (categoria_id, nombre, descripcion, precio, orden) VALUES (?, ?, ?, ?, ?)");
                        $descripcion = "";
                        $precioBase = 0;
                        $orden = $productoId;
                        $stmt->bind_param("issdi", $categoriaId, $productoNombre, $descripcion, $precioBase, $orden);
                        $stmt->execute();
                        $productosCount++;
                    }
                }
                mostrarMensaje("$productosCount opciones sincronizadas", "success");
            } else {
                mostrarMensaje("No se encontraron productos para sincronizar", "warning");
            }
            
            // 3. Sincronizar plazos de entrega
            mostrarMensaje("Sincronizando plazos de entrega...", "info");
            
            // Verificar si la tabla plazos_entrega existe
            $result = $conn->query("SHOW TABLES LIKE 'plazos_entrega'");
            if ($result->num_rows == 0) {
                // Crear la tabla plazos_entrega
                $conn->query("CREATE TABLE plazos_entrega (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    nombre VARCHAR(100) NOT NULL,
                    descripcion VARCHAR(255),
                    multiplicador DECIMAL(5,2) DEFAULT 1.00,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                mostrarMensaje("Tabla plazos_entrega creada", "success");
            }
            
            // Limpiar tabla plazos_entrega
            $conn->query("TRUNCATE TABLE plazos_entrega");
            
            // Obtener plazos de xls_plazos
            $result = $conn->query("SELECT * FROM xls_plazos");
            $plazosCount = 0;
            
            if ($result && $result->num_rows > 0) {
                while ($plazo = $result->fetch_assoc()) {
                    $plazoNombre = $plazo['nombre'];
                    $multiplicador = 1.0;
                    
                    // Determinar multiplicador según el nombre
                    if (strpos($plazoNombre, "90") !== false) {
                        $multiplicador = 1.3; // 30% adicional
                    } else if (strpos($plazoNombre, "270") !== false) {
                        $multiplicador = 0.9; // 10% descuento
                    }
                    
                    // Insertar en la tabla plazos_entrega
                    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, descripcion, multiplicador) VALUES (?, ?, ?)");
                    $descripcion = "Plazo de entrega: $plazoNombre";
                    $stmt->bind_param("ssd", $plazoNombre, $descripcion, $multiplicador);
                    $stmt->execute();
                    $plazosCount++;
                }
                mostrarMensaje("$plazosCount plazos de entrega sincronizados", "success");
            } else {
                // Insertar plazos predeterminados
                $conn->query("INSERT INTO plazos_entrega (nombre, descripcion, multiplicador) VALUES 
                    ('160-180 dias', 'Plazo estándar (160-180 días)', 1.00),
                    ('90 dias', 'Plazo rápido (90 días)', 1.30),
                    ('270 dias', 'Plazo económico (270 días)', 0.90)");
                mostrarMensaje("Plazos predeterminados insertados", "success");
            }
            
            // Reactivar restricciones de clave foránea
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            
            // Confirmar transacción
            $conn->commit();
            
            mostrarMensaje("Sincronización completada correctamente", "success");
            echo "<p><a href='sistema/cotizador.php' class='btn'>Ir al Cotizador</a></p>";
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            $conn->rollback();
            mostrarMensaje("Error al sincronizar: " . $e->getMessage(), "error");
        }
    } else {
        // Mostrar formulario de sincronización
        echo "
        <div class='card'>
            <p>Este script sincronizará los datos importados desde Excel con las tablas necesarias para el cotizador.</p>
            <p>Se realizarán las siguientes acciones:</p>
            <ul>
                <li>Crear o actualizar las categorías (ASCENSORES, ADICIONALES, DESCUENTOS)</li>
                <li>Sincronizar productos y opciones desde xls_productos y xls_opciones a la tabla opciones</li>
                <li>Sincronizar plazos de entrega desde xls_plazos a plazos_entrega</li>
            </ul>
            
            <form method='post'>
                <button type='submit' name='sincronizar' class='btn'>Sincronizar Datos</button>
            </form>
        </div>";
    }
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
}
?>

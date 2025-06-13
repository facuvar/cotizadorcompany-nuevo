<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Estructura de la base de datos
$tables = [
    'categorias' => "
        CREATE TABLE IF NOT EXISTS `categorias` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(100) NOT NULL,
            `descripcion` text,
            `activo` tinyint(1) NOT NULL DEFAULT '1',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'opciones' => "
        CREATE TABLE IF NOT EXISTS `opciones` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `categoria_id` int(11) NOT NULL,
            `nombre` varchar(255) NOT NULL,
            `descripcion` text,
            `activo` tinyint(1) NOT NULL DEFAULT '1',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `categoria_id` (`categoria_id`),
            CONSTRAINT `opciones_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'precios' => "
        CREATE TABLE IF NOT EXISTS `precios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `opcion_id` int(11) NOT NULL,
            `plazo_dias` int(11) NOT NULL,
            `precio` decimal(10,2) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `opcion_id` (`opcion_id`),
            CONSTRAINT `precios_ibfk_1` FOREIGN KEY (`opcion_id`) REFERENCES `opciones` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'fuente_datos' => "
        CREATE TABLE IF NOT EXISTS `fuente_datos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `url` varchar(255) NOT NULL,
            `tipo` varchar(50) NOT NULL DEFAULT 'google_sheets',
            `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    echo "<h1>Verificación de la estructura de la base de datos</h1>";
    echo "<div style='margin-bottom: 20px;'>";
    echo "<p>Este script verificará y creará las tablas necesarias para el sistema de presupuestos.</p>";
    echo "</div>";
    
    echo "<table border='1' cellpadding='5' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
    echo "<thead style='background-color: #f5f5f5;'>";
    echo "<tr><th>Tabla</th><th>Estado</th></tr>";
    echo "</thead><tbody>";
    
    // Crear tablas si no existen
    foreach ($tables as $tableName => $createStatement) {
        // Verificar si la tabla existe
        $tableExists = false;
        $result = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($result && $result->num_rows > 0) {
            $tableExists = true;
        }
        
        if (!$tableExists) {
            // Crear la tabla
            $result = $conn->query($createStatement);
            
            if ($result) {
                echo "<tr><td>$tableName</td><td style='color: green;'>Creada correctamente</td></tr>";
            } else {
                throw new Exception("Error al crear la tabla $tableName: " . $conn->error);
            }
        } else {
            echo "<tr><td>$tableName</td><td style='color: blue;'>Ya existe</td></tr>";
        }
    }
    
    echo "</tbody></table>";
    
    // Verificar si hay registros en la tabla fuente_datos
    $result = $conn->query("SELECT COUNT(*) as count FROM fuente_datos");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo "<h2>No hay fuentes de datos registradas</h2>";
        echo "<div style='margin-top: 20px; margin-bottom: 20px;'>";
        echo "<p>Para continuar, necesitarás agregar al menos una fuente de datos (URL de Google Sheets).</p>";
        echo "<form method='post' action='register_data_source.php'>";
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='url'>URL de Google Sheets:</label><br>";
        echo "<input type='text' name='url' id='url' style='width: 100%; padding: 8px; margin-top: 5px;' placeholder='https://docs.google.com/spreadsheets/d/...' required>";
        echo "</div>";
        echo "<button type='submit' style='padding: 8px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer;'>Registrar Fuente de Datos</button>";
        echo "</form>";
        echo "</div>";
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Volver al Panel de Administración</a>";
    echo "</div>";
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->connect_error === false) {
        $conn->rollback();
    }
    
    echo "<div style='color: red; padding: 15px; border: 1px solid red; margin-bottom: 20px;'>";
    echo "<h2>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Volver al Panel de Administración</a>";
    echo "</div>";
}
?> 
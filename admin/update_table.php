<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../sistema/config.php';
require_once '../sistema/includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "<h2>Actualizando estructura de la tabla opciones</h2>";

    // Array de alteraciones necesarias
    $alteraciones = [
        "ALTER TABLE opciones ADD COLUMN precio_90_dias decimal(12,2) NOT NULL DEFAULT 0.00 AFTER descripcion",
        "ALTER TABLE opciones ADD COLUMN precio_160_dias decimal(12,2) NOT NULL DEFAULT 0.00 AFTER precio_90_dias",
        "ALTER TABLE opciones ADD COLUMN precio_270_dias decimal(12,2) NOT NULL DEFAULT 0.00 AFTER precio_160_dias",
        "ALTER TABLE opciones ADD COLUMN descuento decimal(5,2) NOT NULL DEFAULT 0.00 AFTER precio_270_dias"
    ];

    // Ejecutar cada alteración
    foreach ($alteraciones as $sql) {
        if ($conn->query($sql)) {
            echo "✅ Ejecutado correctamente: " . $sql . "<br>";
        } else {
            echo "❌ Error al ejecutar: " . $sql . "<br>";
            echo "Error: " . $conn->error . "<br>";
        }
    }

    // Insertar datos de ejemplo
    echo "<h2>Insertando datos de ejemplo...</h2>";
    
    $queries = [
        "INSERT INTO opciones (categoria_id, nombre, descripcion, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
        (1, '2 Paradas', 'Ascensor para 2 paradas', 15000000, 14000000, 16000000, 0, 1)",
        
        "INSERT INTO opciones (categoria_id, nombre, descripcion, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
        (1, '3 Paradas', 'Ascensor para 3 paradas', 18000000, 17000000, 19000000, 0, 2)",
        
        "INSERT INTO opciones (categoria_id, nombre, descripcion, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
        (2, 'Puertas Automáticas', 'Sistema de puertas automáticas', 2000000, 1900000, 2100000, 0, 1)",
        
        "INSERT INTO opciones (categoria_id, nombre, descripcion, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
        (3, 'Pago de Contado', 'Descuento por pago de contado', 0, 0, 0, 10, 1)"
    ];

    foreach ($queries as $query) {
        if ($conn->query($query)) {
            echo "✅ Opción insertada correctamente<br>";
        } else {
            echo "❌ Error al insertar opción: " . $conn->error . "<br>";
        }
    }

    echo "<h2>¡Actualización completada!</h2>";
    echo "<p>Ahora puedes volver a <a href='gestionar_datos.php'>gestionar datos</a></p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
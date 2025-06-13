<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../sistema/config.php';
require_once '../sistema/includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Verificar estructura de la tabla
    echo "<h2>Estructura de la tabla opciones</h2>";
    $result = $conn->query("SHOW CREATE TABLE opciones");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<pre>" . $row['Create Table'] . "</pre>";
    } else {
        echo "Error al obtener estructura: " . $conn->error;
    }

    // Insertar datos de ejemplo si la tabla está vacía
    $result = $conn->query("SELECT COUNT(*) as total FROM opciones");
    $count = $result->fetch_assoc()['total'];

    if ($count == 0) {
        echo "<h2>Insertando datos de ejemplo...</h2>";
        
        // Insertar algunas opciones de ejemplo
        $queries = [
            "INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
            (1, '2 Paradas', 15000000, 14000000, 16000000, 0, 1)",
            
            "INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
            (1, '3 Paradas', 18000000, 17000000, 19000000, 0, 2)",
            
            "INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
            (2, 'Puertas Automáticas', 2000000, 1900000, 2100000, 0, 1)",
            
            "INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES 
            (3, 'Pago de Contado', 0, 0, 0, 10, 1)"
        ];

        foreach ($queries as $query) {
            if ($conn->query($query)) {
                echo "Opción insertada correctamente<br>";
            } else {
                echo "Error al insertar opción: " . $conn->error . "<br>";
            }
        }
    } else {
        echo "<h2>La tabla ya tiene datos ($count registros)</h2>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
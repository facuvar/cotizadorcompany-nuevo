<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Conexión sin seleccionar base de datos
    $conn = new mysqli('localhost', 'root', '');
    
    echo "<h2>Bases de datos disponibles:</h2>";
    $result = $conn->query("SHOW DATABASES");
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Database'] . "<br>";
    }
    
    // Conectar a la base de datos actual
    $conn->select_db('presupuestos_ascensores');
    
    echo "<h2>Tablas en presupuestos_ascensores:</h2>";
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "<br>";
    }
    
    echo "<h2>Estructura de la tabla opciones:</h2>";
    $result = $conn->query("DESCRIBE opciones");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<h2>Datos actuales en categorias:</h2>";
    $result = $conn->query("SELECT * FROM categorias");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<h2>Datos actuales en opciones:</h2>";
    $result = $conn->query("SELECT * FROM opciones");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<p>¿Quieres que use otra base de datos? Por favor, indícame cuál usar y los valores correctos para las opciones.</p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
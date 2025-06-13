<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar que el usuario sea administrador
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si la tabla opcion_precios existe
    $result = $conn->query("SHOW TABLES LIKE 'opcion_precios'");
    
    if ($result->num_rows === 0) {
        echo "<p>La tabla opcion_precios no existe.</p>";
        exit;
    }
    
    // Mostrar la estructura de la tabla
    echo "<h2>Estructura de la tabla opcion_precios</h2>";
    
    $result = $conn->query("SHOW COLUMNS FROM opcion_precios");
    
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
    
    while ($column = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . (isset($column['Default']) ? $column['Default'] : 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Mostrar algunos datos de ejemplo
    echo "<h2>Datos de ejemplo (primeros 5 registros)</h2>";
    
    $result = $conn->query("SELECT * FROM opcion_precios LIMIT 5");
    
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        
        // Cabecera de la tabla
        $firstRow = $result->fetch_assoc();
        $result->data_seek(0);
        
        echo "<tr>";
        foreach (array_keys($firstRow) as $fieldName) {
            echo "<th>" . $fieldName . "</th>";
        }
        echo "</tr>";
        
        // Datos
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . (is_null($value) ? "NULL" : $value) . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No hay datos en la tabla.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 
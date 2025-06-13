<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $conn = new mysqli('localhost', 'root', '', 'company_presupuestos');
    
    echo "<h2>Estructura actual de la tabla opciones:</h2>";
    $result = $conn->query("DESCRIBE opciones");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<h2>Datos actuales en categorias:</h2>";
    $result = $conn->query("SELECT * FROM categorias ORDER BY orden ASC");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Orden</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['nombre'] . "</td>";
        echo "<td>" . $row['descripcion'] . "</td>";
        echo "<td>" . $row['orden'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Datos actuales en opciones:</h2>";
    $result = $conn->query("SELECT o.*, c.nombre as categoria_nombre 
                           FROM opciones o 
                           LEFT JOIN categorias c ON o.categoria_id = c.id 
                           ORDER BY c.orden ASC, o.orden ASC");
    
    if (!$result) {
        echo "Error en la consulta: " . $conn->error;
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>ID</th>
                <th>Categoría</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Precio 90 días</th>
                <th>Precio 160 días</th>
                <th>Precio 270 días</th>
                <th>Descuento</th>
                <th>Orden</th>
              </tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . ($row['id'] ?? '') . "</td>";
            echo "<td>" . ($row['categoria_nombre'] ?? '') . "</td>";
            echo "<td>" . ($row['nombre'] ?? '') . "</td>";
            echo "<td>" . ($row['descripcion'] ?? '') . "</td>";
            echo "<td>" . (isset($row['precio_90_dias']) ? number_format($row['precio_90_dias'], 2) : '') . "</td>";
            echo "<td>" . (isset($row['precio_160_dias']) ? number_format($row['precio_160_dias'], 2) : '') . "</td>";
            echo "<td>" . (isset($row['precio_270_dias']) ? number_format($row['precio_270_dias'], 2) : '') . "</td>";
            echo "<td>" . ($row['descuento'] ?? '') . "%</td>";
            echo "<td>" . ($row['orden'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
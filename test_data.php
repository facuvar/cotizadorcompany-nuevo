<?php
// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de Datos Railway</h1>";

try {
    $mysqli = new mysqli('mysql.railway.internal', 'root', 'CdEEWsKUcSueZldgmiaypVCCdnKMjgcD', 'railway', 3306);
    
    if ($mysqli->connect_error) {
        throw new Exception("Error de conexión: " . $mysqli->connect_error);
    }
    
    // Verificar categorías
    echo "<h2>Categorías</h2>";
    $result = $mysqli->query("SELECT * FROM categorias");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar opciones
    echo "<h2>Opciones</h2>";
    $result = $mysqli->query("SELECT * FROM opciones LIMIT 5");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Categoría</th><th>Nombre</th><th>Precio</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['categoria_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($row['precio']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar plazos de entrega
    echo "<h2>Plazos de Entrega</h2>";
    $result = $mysqli->query("SELECT * FROM plazos_entrega");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Días</th><th>Precio</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($row['dias']) . "</td>";
            echo "<td>" . htmlspecialchars($row['precio']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar configuración
    echo "<h2>Configuración</h2>";
    $result = $mysqli->query("SELECT * FROM configuracion");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Clave</th><th>Valor</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['clave']) . "</td>";
            echo "<td>" . htmlspecialchars($row['valor']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 
<?php
// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de Estructura de Tablas</h1>";

try {
    $mysqli = new mysqli('mysql.railway.internal', 'root', 'CdEEWsKUcSueZldgmiaypVCCdnKMjgcD', 'railway', 3306);
    
    if ($mysqli->connect_error) {
        throw new Exception("Error de conexión: " . $mysqli->connect_error);
    }
    
    // Verificar estructura de plazos_entrega
    echo "<h2>Estructura de plazos_entrega</h2>";
    $result = $mysqli->query("DESCRIBE plazos_entrega");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Llave</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar estructura de configuracion
    echo "<h2>Estructura de configuracion</h2>";
    $result = $mysqli->query("DESCRIBE configuracion");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Llave</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar datos actuales de plazos_entrega
    echo "<h2>Datos actuales de plazos_entrega</h2>";
    $result = $mysqli->query("SELECT * FROM plazos_entrega");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Días</th><th>Precio</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($row['dias']) . "</td>";
            echo "<td>" . htmlspecialchars($row['precio'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar datos actuales de configuracion
    echo "<h2>Datos actuales de configuracion</h2>";
    $result = $mysqli->query("SELECT * FROM configuracion");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Clave</th><th>Valor</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['clave'] ?? 'NULL') . "</td>";
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
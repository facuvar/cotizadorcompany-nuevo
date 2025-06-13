<?php
// Conexión directa a la base de datos sin includes para evitar advertencias de headers
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "presupuestos_ascensores";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Estructura de tabla</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Estructura de la tabla opcion_precios</h1>";

// Verificar si la tabla existe
$tableExists = $conn->query("SHOW TABLES LIKE 'opcion_precios'")->num_rows > 0;

if (!$tableExists) {
    echo "<p class='error'>La tabla 'opcion_precios' no existe.</p>";
} else {
    // Mostrar estructura de la tabla
    $result = $conn->query("SHOW COLUMNS FROM opcion_precios");
    
    echo "<h2>Columnas de la tabla:</h2>";
    echo "<table>
        <tr>
            <th>Campo</th>
            <th>Tipo</th>
            <th>Nulo</th>
            <th>Clave</th>
            <th>Default</th>
            <th>Extra</th>
        </tr>";
    
    while ($column = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . $column['Field'] . "</td>
            <td>" . $column['Type'] . "</td>
            <td>" . $column['Null'] . "</td>
            <td>" . $column['Key'] . "</td>
            <td>" . (isset($column['Default']) ? $column['Default'] : 'NULL') . "</td>
            <td>" . $column['Extra'] . "</td>
        </tr>";
    }
    
    echo "</table>";
    
    // Mostrar claves foráneas
    $result = $conn->query("SHOW CREATE TABLE opcion_precios");
    $row = $result->fetch_assoc();
    $createTable = $row['Create Table'];
    
    echo "<h2>Definición completa de la tabla:</h2>";
    echo "<pre>" . htmlspecialchars($createTable) . "</pre>";
    
    // Verificar si existe la columna plazo_id
    $hasPlazosId = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_id'")->num_rows > 0;
    
    if ($hasPlazosId) {
        echo "<p class='success'>✓ La tabla tiene la columna 'plazo_id'</p>";
    } else {
        echo "<p class='error'>✗ La tabla NO tiene la columna 'plazo_id'</p>";
    }
    
    // Verificar si existe la columna plazo_entrega
    $hasPlazosEntrega = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_entrega'")->num_rows > 0;
    
    if ($hasPlazosEntrega) {
        echo "<p class='error'>✗ La tabla tiene la columna 'plazo_entrega' (debería usar 'plazo_id')</p>";
    } else {
        echo "<p class='success'>✓ La tabla NO tiene la columna 'plazo_entrega' (correcto)</p>";
    }
    
    // Verificar si existe la columna plazo_entrega_id
    $hasPlazosEntregaId = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_entrega_id'")->num_rows > 0;
    
    if ($hasPlazosEntregaId) {
        echo "<p class='error'>✗ La tabla tiene la columna 'plazo_entrega_id' (debería usar 'plazo_id')</p>";
    } else {
        echo "<p class='success'>✓ La tabla NO tiene la columna 'plazo_entrega_id' (correcto)</p>";
    }
}

// Verificar la tabla plazos_entrega
$plazosExists = $conn->query("SHOW TABLES LIKE 'plazos_entrega'")->num_rows > 0;

if (!$plazosExists) {
    echo "<p class='error'>La tabla 'plazos_entrega' no existe.</p>";
} else {
    echo "<h2>Plazos de entrega existentes:</h2>";
    $result = $conn->query("SELECT * FROM plazos_entrega");
    
    if ($result->num_rows === 0) {
        echo "<p class='error'>No hay plazos de entrega definidos.</p>";
    } else {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Días</th>
            </tr>";
        
        while ($plazo = $result->fetch_assoc()) {
            echo "<tr>
                <td>" . $plazo['id'] . "</td>
                <td>" . $plazo['nombre'] . "</td>
                <td>" . (isset($plazo['dias']) ? $plazo['dias'] : 'N/A') . "</td>
            </tr>";
        }
        
        echo "</table>";
    }
}

echo "<h2>Solución propuesta:</h2>";
echo "<p>Ejecutar el siguiente SQL para recrear la tabla con la estructura correcta:</p>";
echo "<pre>
DROP TABLE IF EXISTS opcion_precios;
CREATE TABLE opcion_precios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opcion_id INT NOT NULL,
    plazo_id INT NOT NULL,
    precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE,
    FOREIGN KEY (plazo_id) REFERENCES plazos_entrega(id) ON DELETE CASCADE
);
</pre>";

echo "<p><a href='create_opcion_precios.php'>Ejecutar script de corrección</a></p>";
echo "<p><a href='index.php'>Volver al panel de administración</a></p>";

echo "</body>
</html>";

$conn->close();
?> 
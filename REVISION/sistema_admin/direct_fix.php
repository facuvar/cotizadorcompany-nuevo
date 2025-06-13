<?php
// Conexión directa a la base de datos sin includes para evitar advertencias
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

// Configurar respuesta como HTML
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Corregir Tabla</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Corrección Directa de la Tabla opcion_precios</h1>";

// Verificamos si se puede ejecutar el script
$conn->begin_transaction();

try {
    // 1. Verificar si la tabla tiene la columna plazo_entrega
    $hasPlazosEntrega = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_entrega'")->num_rows > 0;
    $hasPlazosId = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_id'")->num_rows > 0;
    
    if ($hasPlazosEntrega && !$hasPlazosId) {
        echo "<p class='success'>✓ La tabla tiene la columna 'plazo_entrega' pero no 'plazo_id'. Corrigiendo...</p>";
        
        // Asegurarse de que exista la tabla plazos_entrega
        $plazosExists = $conn->query("SHOW TABLES LIKE 'plazos_entrega'")->num_rows > 0;
        
        if (!$plazosExists) {
            echo "<p>Creando tabla plazos_entrega...</p>";
            
            $createPlazos = "CREATE TABLE plazos_entrega (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) NOT NULL,
                dias INT NOT NULL DEFAULT 0,
                descripcion TEXT NULL,
                orden INT DEFAULT 0
            )";
            
            $conn->query($createPlazos);
            
            // Obtener todos los nombres de plazo únicos de la tabla opcion_precios
            $uniquePlazosQuery = "SELECT DISTINCT plazo_entrega FROM opcion_precios";
            $uniquePlazosResult = $conn->query($uniquePlazosQuery);
            
            while ($plazo = $uniquePlazosResult->fetch_assoc()) {
                $nombre = $plazo['plazo_entrega'];
                $dias = 0;
                
                // Extraer días del nombre (si existen)
                if (preg_match('/(\d+)/', $nombre, $matches)) {
                    $dias = (int)$matches[1];
                }
                
                $insertPlazo = $conn->prepare("INSERT INTO plazos_entrega (nombre, dias) VALUES (?, ?)");
                $insertPlazo->bind_param("si", $nombre, $dias);
                $insertPlazo->execute();
                $insertPlazo->close();
            }
            
            echo "<p class='success'>✓ Tabla plazos_entrega creada con " . $uniquePlazosResult->num_rows . " plazos</p>";
        }
        
        // Añadir la columna plazo_id a la tabla opcion_precios
        $conn->query("ALTER TABLE opcion_precios ADD COLUMN plazo_id INT NULL AFTER opcion_id");
        
        // Actualizar la columna plazo_id con los IDs correspondientes
        $plazosResult = $conn->query("SELECT id, nombre FROM plazos_entrega");
        
        while ($plazo = $plazosResult->fetch_assoc()) {
            $updateQuery = "UPDATE opcion_precios SET plazo_id = ? WHERE plazo_entrega = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("is", $plazo['id'], $plazo['nombre']);
            $updateStmt->execute();
            $rowsAffected = $updateStmt->affected_rows;
            $updateStmt->close();
            
            echo "<p>Actualizado plazo '" . htmlspecialchars($plazo['nombre']) . "' (ID: " . $plazo['id'] . ") - " . $rowsAffected . " registros</p>";
        }
        
        // Hacer que la columna plazo_id sea NOT NULL
        $conn->query("ALTER TABLE opcion_precios MODIFY COLUMN plazo_id INT NOT NULL");
        
        // Agregar la restricción de clave foránea
        $conn->query("ALTER TABLE opcion_precios ADD CONSTRAINT fk_plazo_id FOREIGN KEY (plazo_id) REFERENCES plazos_entrega(id) ON DELETE CASCADE");
        
        echo "<p class='success'>✓ Se ha agregado la columna plazo_id con sus referencias</p>";
        
        $conn->commit();
        echo "<p class='success'>✓ Cambios confirmados exitosamente</p>";
        
        // Mostrar la nueva estructura de la tabla
        echo "<h2>Estructura actual de la tabla:</h2>";
        $columns = $conn->query("SHOW COLUMNS FROM opcion_precios");
        
        echo "<table border='1'>
            <tr>
                <th>Campo</th>
                <th>Tipo</th>
                <th>Nulo</th>
                <th>Clave</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
        
        while ($column = $columns->fetch_assoc()) {
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
        
        // Mostrar mensaje con el último paso opcional
        echo "<div style='margin-top: 20px; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9;'>
            <h3>Paso Opcional:</h3>
            <p>La columna 'plazo_entrega' ahora es redundante y podría eliminarse. Se recomienda mantenerla durante un tiempo para verificar que todo funciona correctamente, y luego eliminarla con el siguiente comando:</p>
            <pre>ALTER TABLE opcion_precios DROP COLUMN plazo_entrega;</pre>
            <p><strong>Nota:</strong> Antes de ejecutar este comando, asegúrate de haber actualizado todos los scripts que hacen referencia a esta columna.</p>
        </div>";
        
    } else if ($hasPlazosId) {
        echo "<p class='success'>✓ La tabla ya tiene la columna 'plazo_id'. No se requieren cambios.</p>";
        
        // Hacer un conteo para verificar que todos los registros tienen un plazo_id válido
        $validCount = $conn->query("SELECT COUNT(*) as total FROM opcion_precios WHERE plazo_id IS NOT NULL AND plazo_id > 0")->fetch_assoc()['total'];
        $totalCount = $conn->query("SELECT COUNT(*) as total FROM opcion_precios")->fetch_assoc()['total'];
        
        echo "<p>Registros con plazo_id válido: " . $validCount . " de " . $totalCount . " (" . round(($validCount/$totalCount)*100, 2) . "%)</p>";
        
        $conn->commit();
    } else {
        echo "<p class='error'>✗ La tabla no tiene ni la columna 'plazo_entrega' ni 'plazo_id'.</p>";
        $conn->rollback();
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='debug_giracoches_import.php'>Ir a la página de depuración de GIRACOCHES</a></p>";
echo "<p><a href='index.php'>Volver al panel de administración</a></p>";
echo "</body></html>";

$conn->close();
?> 
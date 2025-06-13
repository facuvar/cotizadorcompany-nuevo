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
    <title>Ejecutar SQL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
        .button { background-color: #4CAF50; color: white; padding: 10px 15px; 
                 border: none; border-radius: 5px; cursor: pointer; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Corrección de la tabla opcion_precios</h1>";

// Verificar si la tabla existe
$tableExists = $conn->query("SHOW TABLES LIKE 'opcion_precios'")->num_rows > 0;

if (!$tableExists) {
    echo "<p class='error'>La tabla 'opcion_precios' no existe.</p>";
} else {
    // Verificar si existe la columna plazo_entrega_id
    $hasPlazosEntregaId = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_entrega_id'")->num_rows > 0;
    
    if ($hasPlazosEntregaId) {
        // Crear tabla de respaldo
        if ($conn->query("CREATE TABLE opcion_precios_backup LIKE opcion_precios")) {
            echo "<p class='success'>✓ Tabla de respaldo creada: opcion_precios_backup</p>";
            
            if ($conn->query("INSERT INTO opcion_precios_backup SELECT * FROM opcion_precios")) {
                echo "<p class='success'>✓ Datos copiados a la tabla de respaldo</p>";
                
                // Verificar la tabla plazos_entrega existe
                $plazosExists = $conn->query("SHOW TABLES LIKE 'plazos_entrega'")->num_rows > 0;
                
                if (!$plazosExists) {
                    echo "<p class='error'>✗ La tabla plazos_entrega no existe. Creando...</p>";
                    
                    $createPlazos = "CREATE TABLE plazos_entrega (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        nombre VARCHAR(50) NOT NULL,
                        dias INT NOT NULL DEFAULT 0,
                        descripcion TEXT NULL,
                        orden INT DEFAULT 0
                    )";
                    
                    if ($conn->query($createPlazos)) {
                        echo "<p class='success'>✓ Tabla plazos_entrega creada</p>";
                        
                        // Insertar plazos predeterminados
                        $plazos = [
                            ['nombre' => '160-180 días', 'dias' => 180],
                            ['nombre' => '90 días', 'dias' => 90],
                            ['nombre' => '270 días', 'dias' => 270]
                        ];
                        
                        foreach ($plazos as $plazo) {
                            $insertPlazo = $conn->prepare("INSERT INTO plazos_entrega (nombre, dias) VALUES (?, ?)");
                            $insertPlazo->bind_param("si", $plazo['nombre'], $plazo['dias']);
                            $insertPlazo->execute();
                        }
                        
                        echo "<p class='success'>✓ Plazos predeterminados insertados</p>";
                    } else {
                        echo "<p class='error'>✗ Error al crear tabla plazos_entrega: " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p class='success'>✓ La tabla plazos_entrega ya existe</p>";
                }
                
                // Recrear la tabla opcion_precios con la estructura correcta
                if ($conn->query("DROP TABLE opcion_precios")) {
                    echo "<p class='success'>✓ Tabla opcion_precios eliminada para recrear</p>";
                    
                    $createOpcionPrecios = "CREATE TABLE opcion_precios (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        opcion_id INT NOT NULL,
                        plazo_id INT NOT NULL,
                        precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                        FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE,
                        FOREIGN KEY (plazo_id) REFERENCES plazos_entrega(id) ON DELETE CASCADE
                    )";
                    
                    if ($conn->query($createOpcionPrecios)) {
                        echo "<p class='success'>✓ Tabla opcion_precios recreada con estructura correcta</p>";
                        
                        // Ahora vamos a migrar los datos de la tabla de respaldo
                        $plazosResult = $conn->query("SELECT id, nombre FROM plazos_entrega");
                        $plazos = [];
                        
                        while ($plazo = $plazosResult->fetch_assoc()) {
                            $plazos[$plazo['nombre']] = $plazo['id'];
                        }
                        
                        // Verificar si la tabla de backup tiene la columna plazo_entrega
                        $hasPlazosEntrega = $conn->query("SHOW COLUMNS FROM opcion_precios_backup LIKE 'plazo_entrega'")->num_rows > 0;
                        
                        $success = false;
                        $error = '';
                        
                        if ($hasPlazosEntrega) {
                            // Obtener datos donde plazo_entrega no es nulo
                            $oldData = $conn->query("SELECT opcion_id, plazo_entrega, precio FROM opcion_precios_backup WHERE plazo_entrega IS NOT NULL");
                            
                            if ($oldData && $oldData->num_rows > 0) {
                                $migratedCount = 0;
                                $errorCount = 0;
                                
                                while ($row = $oldData->fetch_assoc()) {
                                    $plazoEntrega = $row['plazo_entrega'];
                                    $opcionId = $row['opcion_id'];
                                    $precio = $row['precio'];
                                    
                                    // Encontrar el ID del plazo correspondiente
                                    $plazoId = isset($plazos[$plazoEntrega]) ? $plazos[$plazoEntrega] : null;
                                    
                                    if (!$plazoId) {
                                        // Si no existe el plazo, crearlo
                                        $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre) VALUES (?)");
                                        $stmt->bind_param('s', $plazoEntrega);
                                        $stmt->execute();
                                        $plazoId = $conn->insert_id;
                                        $plazos[$plazoEntrega] = $plazoId;
                                    }
                                    
                                    // Insertar el registro en la nueva tabla
                                    $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)");
                                    $stmt->bind_param('iid', $opcionId, $plazoId, $precio);
                                    
                                    if ($stmt->execute()) {
                                        $migratedCount++;
                                    } else {
                                        $errorCount++;
                                    }
                                }
                                
                                echo "<p class='success'>✓ Migración completada. Registros migrados: $migratedCount. Errores: $errorCount</p>";
                                $success = true;
                            }
                        } else if ($hasPlazosEntregaId) {
                            // Obtener datos de la vieja columna plazo_entrega_id
                            $oldData = $conn->query("SELECT opcion_id, plazo_entrega_id, precio FROM opcion_precios_backup WHERE plazo_entrega_id IS NOT NULL");
                            
                            if ($oldData && $oldData->num_rows > 0) {
                                $migratedCount = 0;
                                $errorCount = 0;
                                
                                while ($row = $oldData->fetch_assoc()) {
                                    $plazoEntregaId = $row['plazo_entrega_id'];
                                    $opcionId = $row['opcion_id'];
                                    $precio = $row['precio'];
                                    
                                    // Si no hay un plazo con ese ID, buscar si hay plazos que podamos usar
                                    $plazoExists = $conn->query("SELECT id FROM plazos_entrega WHERE id = $plazoEntregaId")->num_rows > 0;
                                    
                                    if (!$plazoExists) {
                                        // Usar el primer plazo disponible
                                        $firstPlazo = $conn->query("SELECT id FROM plazos_entrega ORDER BY id LIMIT 1");
                                        
                                        if ($firstPlazo && $firstPlazo->num_rows > 0) {
                                            $plazoEntregaId = $firstPlazo->fetch_assoc()['id'];
                                        } else {
                                            // No hay plazos, crear uno por defecto
                                            $defaultPlazo = "Normal";
                                            $insertDefault = $conn->prepare("INSERT INTO plazos_entrega (nombre) VALUES (?)");
                                            $insertDefault->bind_param("s", $defaultPlazo);
                                            $insertDefault->execute();
                                            $plazoEntregaId = $conn->insert_id;
                                        }
                                    }
                                    
                                    // Insertar el registro en la nueva tabla
                                    $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)");
                                    $stmt->bind_param('iid', $opcionId, $plazoEntregaId, $precio);
                                    
                                    if ($stmt->execute()) {
                                        $migratedCount++;
                                    } else {
                                        $errorCount++;
                                    }
                                }
                                
                                echo "<p class='success'>✓ Migración completada. Registros migrados: $migratedCount. Errores: $errorCount</p>";
                                $success = true;
                            }
                        }
                        
                        if (!$success) {
                            echo "<p class='error'>✗ No se pudieron migrar datos. No se encontraron columnas compatibles.</p>";
                        }
                    } else {
                        echo "<p class='error'>✗ Error al recrear la tabla opcion_precios: " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p class='error'>✗ Error al eliminar la tabla opcion_precios: " . $conn->error . "</p>";
                }
            } else {
                echo "<p class='error'>✗ Error al copiar datos a la tabla de respaldo: " . $conn->error . "</p>";
            }
        } else {
            echo "<p class='error'>✗ Error al crear tabla de respaldo: " . $conn->error . "</p>";
        }
    } else {
        // Verificar si existe la columna plazo_id
        $hasPlazosId = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_id'")->num_rows > 0;
        
        if ($hasPlazosId) {
            echo "<p class='success'>✓ La tabla opcion_precios ya tiene la estructura correcta (plazo_id)</p>";
        } else {
            echo "<p class='error'>✗ La tabla opcion_precios no tiene columna plazo_id ni plazo_entrega_id</p>";
        }
    }
}

echo "<h2>Estructura final de la tabla opcion_precios:</h2>";
$tableExists = $conn->query("SHOW TABLES LIKE 'opcion_precios'")->num_rows > 0;

if ($tableExists) {
    $result = $conn->query("SHOW COLUMNS FROM opcion_precios");
    
    echo "<table border='1'>
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
    
    echo "<h3>Definición completa de la tabla:</h3>";
    echo "<pre>" . htmlspecialchars($createTable) . "</pre>";
} else {
    echo "<p class='error'>La tabla opcion_precios no existe</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Volver al panel de administración</a></p>";

echo "</body>
</html>";

$conn->close();
?> 
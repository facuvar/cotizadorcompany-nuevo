<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar que el usuario sea administrador
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si la tabla existe
    $tableExists = $conn->query("SHOW TABLES LIKE 'opcion_precios'")->num_rows > 0;
    
    // Si la tabla ya existe, hacer un backup primero
    if ($tableExists) {
        $conn->query("CREATE TABLE IF NOT EXISTS opcion_precios_backup LIKE opcion_precios");
        $conn->query("INSERT INTO opcion_precios_backup SELECT * FROM opcion_precios");
        echo "<p>Se ha creado una copia de seguridad de la tabla original en 'opcion_precios_backup'</p>";
        
        // Eliminar la tabla existente para recrearla
        $conn->query("DROP TABLE opcion_precios");
        echo "<p>Se ha eliminado la tabla 'opcion_precios' existente</p>";
    }
    
    // Crear la tabla con la estructura correcta
    $createTable = "CREATE TABLE opcion_precios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        opcion_id INT NOT NULL,
        plazo_id INT NOT NULL,
        precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE,
        FOREIGN KEY (plazo_id) REFERENCES plazos_entrega(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($createTable)) {
        echo "<p>Se ha creado la tabla 'opcion_precios' con la estructura correcta</p>";
        
        // Si había una tabla anterior, intentar migrar los datos
        if ($tableExists) {
            // Verificar si la tabla de backup tiene la columna plazo_entrega
            $hasPlazosEntrega = $conn->query("SHOW COLUMNS FROM opcion_precios_backup LIKE 'plazo_entrega'")->num_rows > 0;
            
            if ($hasPlazosEntrega) {
                echo "<p>Migrando datos desde la tabla de backup (columna plazo_entrega)...</p>";
                
                // Primero, obtener los plazos de entrega existentes
                $plazosResult = $conn->query("SELECT id, nombre FROM plazos_entrega");
                $plazos = [];
                
                while ($plazo = $plazosResult->fetch_assoc()) {
                    $plazos[$plazo['nombre']] = $plazo['id'];
                }
                
                // Verificar si hay plazos definidos
                if (empty($plazos)) {
                    echo "<p><strong>Error:</strong> No hay plazos de entrega definidos en la tabla plazos_entrega</p>";
                    echo "<p>Crea algunos plazos de entrega primero y luego ejecuta este script nuevamente.</p>";
                } else {
                    // Obtener los datos antiguos
                    $oldData = $conn->query("SELECT id, opcion_id, plazo_entrega, precio FROM opcion_precios_backup");
                    
                    // Contador para registros migrados correctamente
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
                            echo "<p>Error al migrar registro: opcion_id=$opcionId, plazo=$plazoEntrega (ID=$plazoId), precio=$precio</p>";
                        }
                    }
                    
                    echo "<p>Migración completa. Registros migrados: $migratedCount. Errores: $errorCount</p>";
                }
            } else {
                echo "<p>La tabla de backup no tiene la columna 'plazo_entrega'. Verificando si tiene 'plazo_id'...</p>";
                
                $hasPlazosId = $conn->query("SHOW COLUMNS FROM opcion_precios_backup LIKE 'plazo_id'")->num_rows > 0;
                
                if ($hasPlazosId) {
                    // Si la estructura ya era correcta, simplemente copiar los datos
                    $insertResult = $conn->query("INSERT INTO opcion_precios (opcion_id, plazo_id, precio) SELECT opcion_id, plazo_id, precio FROM opcion_precios_backup");
                    
                    if ($insertResult) {
                        $rowCount = $conn->affected_rows;
                        echo "<p>Se han copiado $rowCount registros desde la tabla de backup</p>";
                    } else {
                        echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p><strong>Error:</strong> La tabla de backup no tiene ni 'plazo_entrega' ni 'plazo_id'</p>";
                }
            }
        }
        
        echo "<h2>Estructura final de la tabla opcion_precios:</h2>";
        $columns = $conn->query("SHOW COLUMNS FROM opcion_precios");
        
        echo "<table border='1'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
        
        while ($column = $columns->fetch_assoc()) {
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
        
    } else {
        echo "<p><strong>Error al crear la tabla:</strong> " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<p><a href='index.php'>Volver al panel de administración</a></p>";
?> 
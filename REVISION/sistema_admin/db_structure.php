<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar si la tabla plazos_entrega existe
    $result = $conn->query("SHOW TABLES LIKE 'plazos_entrega'");
    
    if ($result->num_rows === 0) {
        // La tabla no existe, crearla
        $conn->query("CREATE TABLE plazos_entrega (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL,
            dias INT NOT NULL DEFAULT 0,
            descripcion TEXT NULL,
            orden INT DEFAULT 0
        )");
        
        // Insertar algunos plazos por defecto
        $plazos = [
            ['nombre' => '160-180 días', 'dias' => 180, 'descripcion' => 'Entrega en 160-180 días', 'orden' => 1],
            ['nombre' => '90 días', 'dias' => 90, 'descripcion' => 'Entrega en 90 días', 'orden' => 2],
            ['nombre' => '270 días', 'dias' => 270, 'descripcion' => 'Entrega en 270 días', 'orden' => 3]
        ];
        
        foreach ($plazos as $plazo) {
            $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, dias, descripcion, orden) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sisi', $plazo['nombre'], $plazo['dias'], $plazo['descripcion'], $plazo['orden']);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // La tabla existe, verificar si tiene la columna dias
        $result = $conn->query("SHOW COLUMNS FROM plazos_entrega LIKE 'dias'");
        
        if ($result->num_rows === 0) {
            // La columna dias no existe, agregarla
            $conn->query("ALTER TABLE plazos_entrega ADD COLUMN dias INT NOT NULL DEFAULT 0 AFTER nombre");
            
            // Actualizar los días basados en los nombres de los plazos existentes
            $stmt = $conn->prepare("SELECT id, nombre FROM plazos_entrega");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dias = 0;
                
                // Extraer los días del nombre (asumiendo formatos como "90 días", "160-180 días", etc.)
                if (preg_match('/(\d+)-?(\d+)?/', $row['nombre'], $matches)) {
                    // Si hay un rango (ej. 160-180), usar el valor más alto
                    $dias = isset($matches[2]) && !empty($matches[2]) ? intval($matches[2]) : intval($matches[1]);
                }
                
                if ($dias > 0) {
                    $updateStmt = $conn->prepare("UPDATE plazos_entrega SET dias = ? WHERE id = ?");
                    $updateStmt->bind_param('ii', $dias, $row['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
            
            $stmt->close();
        }
    }
    
    // Verificar si existe la tabla opcion_precios
    $result = $conn->query("SHOW TABLES LIKE 'opcion_precios'");
    
    if ($result->num_rows === 0) {
        // La tabla no existe, crearla
        $conn->query("CREATE TABLE opcion_precios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            opcion_id INT NOT NULL,
            plazo_id INT NOT NULL,
            precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE,
            FOREIGN KEY (plazo_id) REFERENCES plazos_entrega(id) ON DELETE CASCADE
        )");
    } else {
        // Verificar si tiene la columna plazo_id en lugar de plazo_entrega
        $result = $conn->query("SHOW COLUMNS FROM opcion_precios LIKE 'plazo_entrega'");
        
        if ($result->num_rows > 0) {
            // La columna plazo_entrega existe, necesitamos cambiarla a plazo_id
            
            // Primero crear la nueva columna
            $conn->query("ALTER TABLE opcion_precios ADD COLUMN plazo_id INT NULL AFTER opcion_id");
            
            // Actualizar los valores basados en los nombres de los plazos
            $result = $conn->query("SELECT id, nombre FROM plazos_entrega");
            $plazos = [];
            
            while ($row = $result->fetch_assoc()) {
                $plazos[$row['nombre']] = $row['id'];
            }
            
            foreach ($plazos as $nombre => $id) {
                $stmt = $conn->prepare("UPDATE opcion_precios SET plazo_id = ? WHERE plazo_entrega = ?");
                $stmt->bind_param('is', $id, $nombre);
                $stmt->execute();
                $stmt->close();
            }
            
            // Eliminar la columna anterior y agregar la restricción de clave foránea
            $conn->query("ALTER TABLE opcion_precios DROP COLUMN plazo_entrega");
            $conn->query("ALTER TABLE opcion_precios ADD CONSTRAINT fk_plazo_id FOREIGN KEY (plazo_id) REFERENCES plazos_entrega(id) ON DELETE CASCADE");
            
            // Hacer que la columna sea NOT NULL
            $conn->query("ALTER TABLE opcion_precios MODIFY COLUMN plazo_id INT NOT NULL");
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    setFlashMessage('success', 'Estructura de base de datos actualizada correctamente');
} catch (Exception $e) {
    // Revertir cambios en caso de error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    setFlashMessage('error', 'Error al actualizar la estructura de la base de datos: ' . $e->getMessage());
} finally {
    // Cerrar conexiones si es necesario
    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($updateStmt) && $updateStmt) $updateStmt->close();
}

// Redireccionar a la página de administración
header('Location: index.php');
exit;
?> 
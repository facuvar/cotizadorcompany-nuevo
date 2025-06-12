<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si ya existen plazos de entrega
    $query = "SELECT COUNT(*) as total FROM plazos_entrega";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    
    $plazosExistentes = $row['total'];
    
    if ($plazosExistentes > 0) {
        echo "<div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin: 15px; color: #2e7d32;'>";
        echo "<h2>Los plazos de entrega ya están configurados</h2>";
        echo "<p>Se encontraron {$plazosExistentes} plazos de entrega en la base de datos.</p>";
        echo "<p>Plazos actuales:</p>";
        
        $query = "SELECT * FROM plazos_entrega ORDER BY orden ASC";
        $result = $conn->query($query);
        
        echo "<ul>";
        while ($plazo = $result->fetch_assoc()) {
            echo "<li><strong>{$plazo['nombre']}</strong> (Orden: {$plazo['orden']})</li>";
        }
        echo "</ul>";
        
        echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Volver al panel</a></p>";
        echo "</div>";
    } else {
        // Iniciar transacción
        $conn->begin_transaction();
        
        // Insertar plazos de entrega estándar
        $plazos = [
            ['160/180 dias', 1],
            ['90 dias', 2],
            ['270 dias', 3]
        ];
        
        $insertQuery = "INSERT INTO plazos_entrega (nombre, orden) VALUES (?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
        foreach ($plazos as $plazo) {
            $stmt->bind_param('si', $plazo[0], $plazo[1]);
            $stmt->execute();
        }
        
        $stmt->close();
        
        // Confirmar transacción
        $conn->commit();
        
        echo "<div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin: 15px; color: #2e7d32;'>";
        echo "<h2>Plazos de entrega configurados correctamente</h2>";
        echo "<p>Se han configurado los siguientes plazos de entrega:</p>";
        
        echo "<ul>";
        foreach ($plazos as $plazo) {
            echo "<li><strong>{$plazo[0]}</strong> (Orden: {$plazo[1]})</li>";
        }
        echo "</ul>";
        
        echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Volver al panel</a></p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    echo "<div style='background-color: #ffebee; padding: 15px; border-radius: 5px; margin: 15px; color: #b71c1c;'>";
    echo "<h2>Error al configurar los plazos de entrega</h2>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Volver al panel</a></p>";
    echo "</div>";
} 
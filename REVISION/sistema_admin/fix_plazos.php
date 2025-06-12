<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si la tabla plazos_entrega tiene la columna orden
    $result = $conn->query("SHOW COLUMNS FROM plazos_entrega LIKE 'orden'");
    
    if ($result->num_rows === 0) {
        // Añadir la columna orden
        $conn->query("ALTER TABLE plazos_entrega ADD COLUMN orden INT DEFAULT 0");
        echo "<p>Columna 'orden' añadida a la tabla plazos_entrega.</p>";
    }
    
    // Obtener todos los plazos
    $plazos = $conn->query("SELECT * FROM plazos_entrega ORDER BY nombre");
    $plazosData = [];
    
    if ($plazos && $plazos->num_rows > 0) {
        echo "<h2>Plazos de entrega actuales:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Orden Actual</th></tr>";
        
        while ($plazo = $plazos->fetch_assoc()) {
            $plazosData[] = $plazo;
            echo "<tr>";
            echo "<td>" . $plazo['id'] . "</td>";
            echo "<td>" . $plazo['nombre'] . "</td>";
            echo "<td>" . $plazo['orden'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Establecer órdenes especiales para plazos comunes
        $ordenEspecial = [
            '90 dias' => 1,
            '160/180 dias' => 2,
            '270 dias' => 3
        ];
        
        // Actualizar órdenes
        $orden = 4; // Comenzar desde 4 para otros plazos
        $ordenActualizados = 0;
        
        foreach ($plazosData as $plazo) {
            $nuevaOrden = isset($ordenEspecial[$plazo['nombre']]) ? $ordenEspecial[$plazo['nombre']] : $orden++;
            
            if ($plazo['orden'] != $nuevaOrden) {
                $stmt = $conn->prepare("UPDATE plazos_entrega SET orden = ? WHERE id = ?");
                $stmt->bind_param("ii", $nuevaOrden, $plazo['id']);
                $stmt->execute();
                $ordenActualizados++;
            }
        }
        
        echo "<p>Se actualizaron los órdenes de $ordenActualizados plazos.</p>";
        
        // Mostrar plazos actualizados
        $plazosActualizados = $conn->query("SELECT * FROM plazos_entrega ORDER BY orden ASC, nombre ASC");
        
        if ($plazosActualizados && $plazosActualizados->num_rows > 0) {
            echo "<h2>Plazos de entrega actualizados:</h2>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Orden Nuevo</th></tr>";
            
            while ($plazo = $plazosActualizados->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $plazo['id'] . "</td>";
                echo "<td>" . $plazo['nombre'] . "</td>";
                echo "<td>" . $plazo['orden'] . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    } else {
        echo "<p>No se encontraron plazos de entrega en la base de datos.</p>";
    }
    
    echo "<h3>Actualizar el cotizador para mostrar todos los plazos</h3>";
    echo "<p>Para que se muestren todos los plazos en el cotizador, debes verificar las siguientes cosas:</p>";
    echo "<ol>";
    echo "<li>Asegúrate de que la tabla opcion_precios tiene datos para todos los plazos y modelos.</li>";
    echo "<li>Verifica que en la función importadora de GIRACOCHES se están guardando todos los plazos.</li>";
    echo "<li>Revisa el código del cotizador para asegurarte de que se muestren todos los plazos disponibles.</li>";
    echo "</ol>";
    
    echo "<p><a href='validate_giracoches.php'>Volver a la página de validación de GIRACOCHES</a></p>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
} 
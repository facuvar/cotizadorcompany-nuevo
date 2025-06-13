<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../sistema/config.php';
require_once '../sistema/includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "<h2>Datos actuales en la tabla opciones</h2>";
    
    // Mostrar la consulta que se está ejecutando
    $query = "SELECT o.*, c.nombre as categoria_nombre 
              FROM opciones o 
              LEFT JOIN categorias c ON o.categoria_id = c.id 
              ORDER BY c.orden ASC, o.orden ASC, o.nombre ASC";
    
    echo "<pre>Ejecutando consulta:\n" . $query . "</pre>";
    
    $result = $conn->query($query);
    
    if ($result) {
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
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['categoria_nombre'] . "</td>";
            echo "<td>" . $row['nombre'] . "</td>";
            echo "<td>" . $row['descripcion'] . "</td>";
            echo "<td>" . number_format($row['precio_90_dias'], 2) . "</td>";
            echo "<td>" . number_format($row['precio_160_dias'], 2) . "</td>";
            echo "<td>" . number_format($row['precio_270_dias'], 2) . "</td>";
            echo "<td>" . $row['descuento'] . "%</td>";
            echo "<td>" . $row['orden'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p>Total de registros: " . $result->num_rows . "</p>";
    } else {
        echo "Error en la consulta: " . $conn->error;
    }

    // Verificar si hay errores en la inserción previa
    echo "<h2>Intentando insertar datos faltantes</h2>";
    
    // Array para verificar qué opciones ya existen
    $result = $conn->query("SELECT nombre FROM opciones");
    $opciones_existentes = [];
    while ($row = $result->fetch_assoc()) {
        $opciones_existentes[] = $row['nombre'];
    }
    
    // Datos a insertar si no existen
    $datos = [
        [1, '2 Paradas', 'Ascensor para 2 paradas', 15000000, 14000000, 16000000, 0, 1],
        [1, '3 Paradas', 'Ascensor para 3 paradas', 18000000, 17000000, 19000000, 0, 2],
        [1, '4 Paradas', 'Ascensor para 4 paradas', 21000000, 20000000, 22000000, 0, 3],
        [1, '5 Paradas', 'Ascensor para 5 paradas', 24000000, 23000000, 25000000, 0, 4],
        [2, 'Puertas Automáticas', 'Sistema de puertas automáticas', 2000000, 1900000, 2100000, 0, 1],
        [2, 'Cabina de Lujo', 'Acabados premium para la cabina', 3000000, 2900000, 3100000, 0, 2],
        [3, 'Pago de Contado', 'Descuento por pago de contado', 0, 0, 0, 10, 1],
        [3, 'Pago 30-60', 'Plan de pagos 30-60 días', 0, 0, 0, 5, 2]
    ];
    
    foreach ($datos as $dato) {
        if (!in_array($dato[1], $opciones_existentes)) {
            $sql = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issddddi", ...$dato);
            
            if ($stmt->execute()) {
                echo "✅ Insertada opción: " . $dato[1] . "<br>";
            } else {
                echo "❌ Error al insertar " . $dato[1] . ": " . $stmt->error . "<br>";
            }
        } else {
            echo "⏭️ La opción '" . $dato[1] . "' ya existe<br>";
        }
    }

    echo "<p><a href='gestionar_datos.php'>Volver a Gestionar Datos</a></p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
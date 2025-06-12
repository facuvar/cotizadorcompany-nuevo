<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Esta herramienta es para verificar y reparar la categoría SALVAESCALERAS
// Desactivar el requerimiento de login para pruebas
// requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Verificación y Reparación de Categoría SALVAESCALERAS</h2>";
    
    // 1. Verificar si existe la categoría SALVAESCALERAS
    $query = "SELECT * FROM categorias WHERE nombre LIKE '%SALVAESCALERAS%' LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        echo "<p style='color: red;'>No se encontró la categoría SALVAESCALERAS en la base de datos. Vamos a crearla.</p>";
        
        // Crear la categoría SALVAESCALERAS
        $salvaescalerasNombre = "SALVAESCALERAS";
        $salvaescalerasDesc = "Elevador para personas con movilidad reducida";
        $salvaescalerasOrden = 9;
        
        $query = "INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $salvaescalerasNombre, $salvaescalerasDesc, $salvaescalerasOrden);
        
        if ($stmt->execute()) {
            $categoriaId = $conn->insert_id;
            echo "<p style='color: green;'>Categoría SALVAESCALERAS creada exitosamente con ID: $categoriaId</p>";
        } else {
            throw new Exception("Error al crear la categoría SALVAESCALERAS: " . $stmt->error);
        }
    } else {
        $categoria = $result->fetch_assoc();
        $categoriaId = $categoria['id'];
        
        echo "<div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<h3 style='color: #2e7d32; margin-top: 0;'>Categoría SALVAESCALERAS encontrada:</h3>";
        echo "<p><strong>ID:</strong> " . $categoriaId . "</p>";
        echo "<p><strong>Nombre:</strong> " . htmlspecialchars($categoria['nombre']) . "</p>";
        echo "<p><strong>Descripción:</strong> " . htmlspecialchars($categoria['descripcion']) . "</p>";
        echo "<p><strong>Orden:</strong> " . $categoria['orden'] . "</p>";
        echo "</div>";
    }
    
    // 2. Verificar si existen opciones para esta categoría
    $opcionesQuery = "SELECT COUNT(*) as total FROM opciones WHERE categoria_id = ?";
    $stmt = $conn->prepare($opcionesQuery);
    $stmt->bind_param('i', $categoriaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalOpciones = $row['total'];
    
    echo "<p>Opciones encontradas para SALVAESCALERAS: $totalOpciones</p>";
    
    if ($totalOpciones === 0) {
        echo "<p style='color: orange;'>No hay opciones para la categoría SALVAESCALERAS. Vamos a crear algunas opciones de ejemplo.</p>";
        
        // 3. Crear opciones de ejemplo
        $opciones = [
            [
                'nombre' => 'MODELO SIMPLE H/ 1.80 M',
                'descripcion' => 'Salvaescaleras modelo simple hasta 1.80 metros',
                'precio' => 13234809,
                'es_obligatorio' => 1,
                'orden' => 1
            ],
            [
                'nombre' => 'MODELO COMPLETO H/1.80 M',
                'descripcion' => 'Salvaescaleras modelo completo hasta 1.80 metros',
                'precio' => 17350074,
                'es_obligatorio' => 1,
                'orden' => 2
            ],
            [
                'nombre' => 'MODELO COMPLETO H/3 M',
                'descripcion' => 'Salvaescaleras modelo completo hasta 3 metros',
                'precio' => 21829196,
                'es_obligatorio' => 1,
                'orden' => 3
            ]
        ];
        
        // Insertar opciones
        $insertQuery = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
        $opcionesCreadas = 0;
        $conn->begin_transaction();
        
        foreach ($opciones as $opcion) {
            $stmt->bind_param('issdii', 
                $categoriaId, 
                $opcion['nombre'], 
                $opcion['descripcion'], 
                $opcion['precio'], 
                $opcion['es_obligatorio'], 
                $opcion['orden']
            );
            
            if ($stmt->execute()) {
                $opcionId = $conn->insert_id;
                $opcionesCreadas++;
                
                echo "<p>Opción creada: " . htmlspecialchars($opcion['nombre']) . " con ID: $opcionId</p>";
                
                // Insertar precios por plazo
                $preciosPlazoQuery = "INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)";
                $preciosStmt = $conn->prepare($preciosPlazoQuery);
                
                // Precio 160-180 días (base)
                $plazo = '160-180 días';
                $precioBase = $opcion['precio'];
                $preciosStmt->bind_param('isd', $opcionId, $plazo, $precioBase);
                $preciosStmt->execute();
                
                // Precio 90 días (+10%)
                $plazo = '90 días';
                $precio90 = $precioBase * 1.10; // 10% más
                $preciosStmt->bind_param('isd', $opcionId, $plazo, $precio90);
                $preciosStmt->execute();
                
                // Precio 270 días (-5%)
                $plazo = '270 días';
                $precio270 = $precioBase * 0.95; // 5% menos
                $preciosStmt->bind_param('isd', $opcionId, $plazo, $precio270);
                $preciosStmt->execute();
                
                echo "<p style='margin-left: 20px;'>Precios por plazo registrados para esta opción</p>";
            } else {
                throw new Exception("Error al crear la opción: " . $stmt->error);
            }
        }
        
        $conn->commit();
        echo "<p style='color: green; font-weight: bold;'>Se crearon $opcionesCreadas opciones para la categoría SALVAESCALERAS</p>";
        
    } else {
        // Mostrar las opciones existentes
        $opcionesQuery = "SELECT * FROM opciones WHERE categoria_id = ? ORDER BY orden ASC";
        $stmt = $conn->prepare($opcionesQuery);
        $stmt->bind_param('i', $categoriaId);
        $stmt->execute();
        $opcionesResult = $stmt->get_result();
        
        echo "<h3>Opciones existentes para SALVAESCALERAS:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #4CAF50; color: white;'>";
        echo "<th>ID</th><th>Nombre</th><th>Descripción</th><th>Precio</th><th>Obligatorio</th></tr>";
        
        $rowNum = 0;
        while ($opcion = $opcionesResult->fetch_assoc()) {
            $bgcolor = $rowNum % 2 === 0 ? '#f2f2f2' : 'white';
            echo "<tr style='background-color: {$bgcolor};'>";
            echo "<td>" . $opcion['id'] . "</td>";
            echo "<td>" . htmlspecialchars($opcion['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($opcion['descripcion']) . "</td>";
            echo "<td>$" . number_format($opcion['precio'], 2, ',', '.') . "</td>";
            echo "<td>" . ($opcion['es_obligatorio'] ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
            $rowNum++;
        }
        echo "</table>";
        
        // Mostrar precios por plazo para la primera opción
        if ($opcionesResult->num_rows > 0) {
            $opcionesResult->data_seek(0);
            $primeraOpcion = $opcionesResult->fetch_assoc();
            
            echo "<h3>Precios por plazo para la opción '" . htmlspecialchars($primeraOpcion['nombre']) . "':</h3>";
            $preciosQuery = "SELECT * FROM opcion_precios WHERE opcion_id = " . $primeraOpcion['id'];
            $preciosResult = $conn->query($preciosQuery);
            
            if ($preciosResult && $preciosResult->num_rows > 0) {
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr style='background-color: #4CAF50; color: white;'>";
                echo "<th>Plazo</th><th>Precio</th></tr>";
                
                $rowNum = 0;
                while ($precio = $preciosResult->fetch_assoc()) {
                    $bgcolor = $rowNum % 2 === 0 ? '#f2f2f2' : 'white';
                    echo "<tr style='background-color: {$bgcolor};'>";
                    echo "<td>" . htmlspecialchars($precio['plazo_entrega']) . "</td>";
                    echo "<td>$" . number_format($precio['precio'], 2, ',', '.') . "</td>";
                    echo "</tr>";
                    $rowNum++;
                }
                echo "</table>";
            } else {
                echo "<p style='color: red;'>No se encontraron precios por plazo para esta opción.</p>";
            }
        }
    }
    
    // 4. Mostrar enlace para reconectar a Google Sheets
    echo "<div style='margin: 20px 0;'>";
    echo "<p>Si deseas actualizar los datos desde Google Sheets:</p>";
    echo "<a href='reconnect_last_file.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>Reconectar última fuente de datos</a>";
    echo "<a href='test_sheets.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Verificar categorías y opciones</a>";
    echo "</div>";
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo "<div style='background-color: #ffebee; padding: 15px; border-radius: 5px; color: #b71c1c;'>";
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} 
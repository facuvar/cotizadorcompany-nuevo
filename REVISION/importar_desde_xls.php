<?php
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

// Función para mostrar mensajes
function mostrarMensaje($mensaje, $tipo = 'info') {
    $color = 'black';
    if ($tipo == 'success') $color = 'green';
    if ($tipo == 'error') $color = 'red';
    if ($tipo == 'warning') $color = 'orange';
    
    echo "<p style='color: $color;'>$mensaje</p>";
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Importación desde XLS de Referencia</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            h1, h2 { color: #333; }
            table { border-collapse: collapse; margin-bottom: 20px; width: 100%; }
            th { background-color: #f2f2f2; }
            td, th { padding: 8px; text-align: left; border: 1px solid #ddd; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
            .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
            .btn-blue { background-color: #2196F3; }
        </style>
    </head>
    <body>
    <h1>Importación desde XLS de Referencia</h1>";
    
    // Ruta al archivo XLS de referencia
    $xlsFile = __DIR__ . '/xls/xls-referencia.xlsx';
    
    if (!file_exists($xlsFile)) {
        mostrarMensaje("El archivo de referencia no existe: $xlsFile", "error");
        exit;
    }
    
    mostrarMensaje("Archivo de referencia encontrado: $xlsFile", "success");
    
    // Cargar el archivo XLS
    $reader = new Xlsx();
    $spreadsheet = $reader->load($xlsFile);
    $sheet = $spreadsheet->getActiveSheet();
    
    // Obtener dimensiones
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    
    mostrarMensaje("Archivo cargado correctamente. Dimensiones: " . $highestColumn . $highestRow . " (filas: " . $highestRow . ")", "success");
    
    // Configurar plazos de entrega
    echo "<div class='section'>";
    echo "<h2>Configuración de Plazos de Entrega</h2>";
    
    // Verificar si existe la tabla plazos_entrega
    $result = $conn->query("SHOW TABLES LIKE 'plazos_entrega'");
    
    if ($result->num_rows === 0) {
        mostrarMensaje("La tabla plazos_entrega no existe. Creando tabla...", "warning");
        
        // Crear la tabla plazos_entrega
        $sql = "CREATE TABLE plazos_entrega (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL,
            descripcion TEXT,
            orden INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if ($conn->query($sql)) {
            mostrarMensaje("Tabla plazos_entrega creada correctamente.", "success");
        } else {
            mostrarMensaje("Error al crear la tabla: " . $conn->error, "error");
            exit;
        }
    } else {
        mostrarMensaje("La tabla plazos_entrega ya existe.", "info");
    }
    
    // Limpiar plazos existentes para evitar duplicados
    $conn->query("TRUNCATE TABLE plazos_entrega");
    mostrarMensaje("Tabla plazos_entrega limpiada para evitar duplicados.", "info");
    
    // Plazos predeterminados basados en el archivo XLS
    $plazos = [
        ['nombre' => '30-60 días', 'descripcion' => 'Entrega rápida (30-60 días)', 'orden' => 1],
        ['nombre' => '60-90 días', 'descripcion' => 'Entrega estándar (60-90 días)', 'orden' => 2],
        ['nombre' => '90-120 días', 'descripcion' => 'Entrega normal (90-120 días)', 'orden' => 3],
        ['nombre' => '120-150 días', 'descripcion' => 'Entrega programada (120-150 días)', 'orden' => 4],
        ['nombre' => '150-180 días', 'descripcion' => 'Entrega extendida (150-180 días)', 'orden' => 5],
        ['nombre' => '180-210 días', 'descripcion' => 'Entrega económica (180-210 días)', 'orden' => 6]
    ];
    
    // Insertar plazos
    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, descripcion, orden) VALUES (?, ?, ?)");
    
    foreach ($plazos as $plazo) {
        $stmt->bind_param("ssi", $plazo['nombre'], $plazo['descripcion'], $plazo['orden']);
        
        if ($stmt->execute()) {
            mostrarMensaje("Plazo '{$plazo['nombre']}' agregado correctamente.", "success");
        } else {
            mostrarMensaje("Error al agregar plazo '{$plazo['nombre']}': " . $stmt->error, "error");
        }
    }
    echo "</div>";
    
    // Configurar tabla opcion_precios
    echo "<div class='section'>";
    echo "<h2>Configuración de Tabla opcion_precios</h2>";
    
    // Verificar si existe la tabla opcion_precios
    $result = $conn->query("SHOW TABLES LIKE 'opcion_precios'");
    
    if ($result->num_rows === 0) {
        mostrarMensaje("La tabla opcion_precios no existe. Creando tabla...", "warning");
        
        // Crear la tabla opcion_precios
        $sql = "CREATE TABLE opcion_precios (
            id INT(11) NOT NULL AUTO_INCREMENT,
            opcion_id INT(11) NOT NULL,
            plazo_entrega VARCHAR(100) NOT NULL,
            precio DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if ($conn->query($sql)) {
            mostrarMensaje("Tabla opcion_precios creada correctamente.", "success");
        } else {
            mostrarMensaje("Error al crear la tabla: " . $conn->error, "error");
            exit;
        }
    } else {
        mostrarMensaje("La tabla opcion_precios ya existe.", "info");
        
        // Limpiar precios existentes para evitar duplicados
        $conn->query("TRUNCATE TABLE opcion_precios");
        mostrarMensaje("Tabla opcion_precios limpiada para evitar duplicados.", "info");
    }
    
    // Procesar el archivo XLS para importar datos
    echo "<div class='section'>";
    echo "<h2>Importación de Datos desde XLS</h2>";
    
    // Variables para el seguimiento
    $categoriaActual = null;
    $categoriaId = null;
    $totalCategorias = 0;
    $totalOpciones = 0;
    $totalPrecios = 0;
    
    // Recorrer filas del archivo
    for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
        $nombre = trim($sheet->getCell('A' . $rowIndex)->getValue());
        
        // Saltar filas vacías
        if (empty($nombre)) {
            continue;
        }
        
        $descripcion = trim($sheet->getCell('B' . $rowIndex)->getFormattedValue());
        $precio = $sheet->getCell('C' . $rowIndex)->getValue();
        
        // Verificar si es una categoría (mayúsculas y sin precio)
        if (strtoupper($nombre) === $nombre && (empty($precio) || !is_numeric($precio))) {
            // Es una categoría
            $categoriaActual = $nombre;
            
            // Verificar si la categoría ya existe
            $stmt = $conn->prepare("SELECT id FROM categorias WHERE nombre = ?");
            $stmt->bind_param("s", $categoriaActual);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // La categoría ya existe
                $categoria = $result->fetch_assoc();
                $categoriaId = $categoria['id'];
                mostrarMensaje("Categoría existente: " . $categoriaActual . " (ID: " . $categoriaId . ")", "info");
            } else {
                // Crear nueva categoría
                $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, ?)");
                $orden = $totalCategorias + 1;
                $stmt->bind_param("ssi", $categoriaActual, $descripcion, $orden);
                $stmt->execute();
                $categoriaId = $conn->insert_id;
                $totalCategorias++;
                mostrarMensaje("Nueva categoría creada: " . $categoriaActual . " (ID: " . $categoriaId . ")", "success");
            }
        } elseif ($categoriaId !== null && is_numeric($precio)) {
            // Es una opción de la categoría actual
            $esObligatorio = 1; // Por defecto todas son obligatorias
            
            // Verificar si la opción ya existe
            $stmt = $conn->prepare("SELECT id FROM opciones WHERE categoria_id = ? AND nombre = ?");
            $stmt->bind_param("is", $categoriaId, $nombre);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $opcionId = null;
            
            if ($result->num_rows > 0) {
                // La opción ya existe, actualizarla
                $opcion = $result->fetch_assoc();
                $opcionId = $opcion['id'];
                $stmt = $conn->prepare("UPDATE opciones SET descripcion = ?, precio = ?, es_obligatorio = ? WHERE id = ?");
                $stmt->bind_param("sdii", $descripcion, $precio, $esObligatorio, $opcionId);
                $stmt->execute();
                mostrarMensaje("Opción actualizada: " . $nombre . " - Precio: " . $precio, "info");
            } else {
                // Crear nueva opción
                $stmt = $conn->prepare("INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) VALUES (?, ?, ?, ?, ?, ?)");
                $orden = $totalOpciones + 1;
                $stmt->bind_param("issdii", $categoriaId, $nombre, $descripcion, $precio, $esObligatorio, $orden);
                $stmt->execute();
                $opcionId = $conn->insert_id;
                $totalOpciones++;
                mostrarMensaje("Nueva opción creada: " . $nombre . " - Precio: " . $precio, "success");
            }
            
            // Agregar precios para cada plazo
            if ($opcionId) {
                // Obtener todos los plazos
                $plazosResult = $conn->query("SELECT nombre FROM plazos_entrega ORDER BY orden");
                
                if ($plazosResult && $plazosResult->num_rows > 0) {
                    while ($plazo = $plazosResult->fetch_assoc()) {
                        $plazoNombre = $plazo['nombre'];
                        
                        // Insertar precio para este plazo
                        $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)");
                        $stmt->bind_param("isd", $opcionId, $plazoNombre, $precio);
                        
                        if ($stmt->execute()) {
                            $totalPrecios++;
                        } else {
                            mostrarMensaje("Error al insertar precio para opción ID " . $opcionId . " y plazo " . $plazoNombre . ": " . $stmt->error, "error");
                        }
                    }
                }
            }
        }
    }
    
    echo "<h3>Resumen de la importación</h3>";
    echo "<ul>";
    echo "<li>Total de categorías procesadas: " . $totalCategorias . "</li>";
    echo "<li>Total de opciones procesadas: " . $totalOpciones . "</li>";
    echo "<li>Total de precios por plazo generados: " . $totalPrecios . "</li>";
    echo "</ul>";
    
    mostrarMensaje("Importación completada correctamente.", "success");
    echo "</div>";
    
    // Enlaces de navegación
    echo "<div class='section'>";
    echo "<h2>Próximos pasos</h2>";
    echo "<p>La importación desde el archivo XLS de referencia se ha completado. Ahora puedes acceder al cotizador para verificar que los datos se muestran correctamente.</p>";
    echo "<p><a href='sistema/cotizador.php' class='btn'>Ir al Cotizador</a> <a href='verificar_estado.php' class='btn btn-blue'>Verificar Estado</a></p>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

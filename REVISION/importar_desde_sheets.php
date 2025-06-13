<?php
// Script para importar datos desde Google Sheets o CSV
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';

// Verificar si se envió el formulario
$mensaje = '';
$tipo_mensaje = '';

// Función para procesar CSV
function procesarCSV($file, $conn) {
    $handle = fopen($file, 'r');
    if (!$handle) {
        return ['error', 'No se pudo abrir el archivo CSV'];
    }
    
    // Leer encabezados
    $headers = fgetcsv($handle, 1000, ',');
    if (!$headers) {
        fclose($handle);
        return ['error', 'El archivo CSV está vacío o tiene un formato incorrecto'];
    }
    
    // Buscar columnas de plazos
    $plazoColumns = [];
    $nombreColumn = -1;
    $productoColumn = -1;
    
    foreach ($headers as $index => $header) {
        if (stripos($header, '90 dias') !== false || stripos($header, '90 días') !== false) {
            $plazoColumns[1] = $index;
        } else if (stripos($header, '160/180 dias') !== false || stripos($header, '160/180 días') !== false) {
            $plazoColumns[2] = $index;
        } else if (stripos($header, '270 dias') !== false || stripos($header, '270 días') !== false) {
            $plazoColumns[3] = $index;
        } else if (stripos($header, 'nombre') !== false || stripos($header, 'opcion') !== false || stripos($header, 'opción') !== false) {
            $nombreColumn = $index;
        } else if (stripos($header, 'producto') !== false) {
            $productoColumn = $index;
        }
    }
    
    if (empty($plazoColumns) || $nombreColumn === -1) {
        fclose($handle);
        return ['error', 'No se encontraron las columnas necesarias en el CSV (plazos y nombre de opción)'];
    }
    
    // Procesar datos
    $productos = [];
    $currentProducto = null;
    
    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        // Si hay una columna de producto, usarla para agrupar
        if ($productoColumn !== -1 && !empty($data[$productoColumn])) {
            $currentProducto = $data[$productoColumn];
            if (!isset($productos[$currentProducto])) {
                $productos[$currentProducto] = [];
            }
        }
        
        // Si no hay nombre de opción, saltar
        if (empty($data[$nombreColumn])) {
            continue;
        }
        
        $opcion = [
            'nombre' => $data[$nombreColumn],
            'descripcion' => 'Importado desde CSV',
            'precios' => []
        ];
        
        // Agregar precios para cada plazo
        foreach ($plazoColumns as $plazoId => $columnIndex) {
            if (isset($data[$columnIndex])) {
                // Limpiar y convertir a número
                $precio = str_replace(['$', '.', ','], ['', '', '.'], $data[$columnIndex]);
                if (is_numeric($precio)) {
                    $opcion['precios'][$plazoId] = $precio;
                } else {
                    $opcion['precios'][$plazoId] = 0;
                }
            } else {
                $opcion['precios'][$plazoId] = 0;
            }
        }
        
        if ($currentProducto) {
            $productos[$currentProducto][] = $opcion;
        }
    }
    
    fclose($handle);
    
    if (empty($productos)) {
        return ['error', 'No se encontraron datos válidos en el CSV'];
    }
    
    // Guardar en la base de datos
    $conn->begin_transaction();
    
    try {
        foreach ($productos as $productoNombre => $opciones) {
            // Buscar el ID del producto
            $query = "SELECT id FROM xls_productos WHERE nombre LIKE ?";
            $stmt = $conn->prepare($query);
            $search_term = "%$productoNombre%";
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Si no existe, crear el producto
                $stmt = $conn->prepare("INSERT INTO xls_productos (nombre) VALUES (?)");
                $stmt->bind_param("s", $productoNombre);
                $stmt->execute();
                $producto_id = $conn->insert_id;
            } else {
                $producto = $result->fetch_assoc();
                $producto_id = $producto['id'];
                
                // Eliminar opciones existentes
                $query = "SELECT id FROM xls_opciones WHERE producto_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $producto_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $opcion_ids = [];
                while ($row = $result->fetch_assoc()) {
                    $opcion_ids[] = $row['id'];
                }
                
                if (!empty($opcion_ids)) {
                    $placeholders = implode(',', array_fill(0, count($opcion_ids), '?'));
                    $query = "DELETE FROM xls_precios WHERE opcion_id IN ($placeholders)";
                    $stmt = $conn->prepare($query);
                    
                    $types = str_repeat('i', count($opcion_ids));
                    $stmt->bind_param($types, ...$opcion_ids);
                    $stmt->execute();
                    
                    $query = "DELETE FROM xls_opciones WHERE producto_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $producto_id);
                    $stmt->execute();
                }
            }
            
            // Insertar opciones
            foreach ($opciones as $opcion) {
                $stmt = $conn->prepare("INSERT INTO xls_opciones (producto_id, nombre, descripcion) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $producto_id, $opcion['nombre'], $opcion['descripcion']);
                $stmt->execute();
                
                $opcion_id = $conn->insert_id;
                
                // Insertar precios
                foreach ($opcion['precios'] as $plazo_id => $precio) {
                    $stmt = $conn->prepare("INSERT INTO xls_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $opcion_id, $plazo_id, $precio);
                    $stmt->execute();
                }
            }
        }
        
        $conn->commit();
        return ['success', 'Datos importados correctamente'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['error', 'Error al guardar en la base de datos: ' . $e->getMessage()];
    }
}

// Función para importar desde Google Sheets
function importarDesdeGoogleSheets($spreadsheetId, $conn) {
    // Verificar si existe la librería de Google API
    if (!file_exists('vendor/google/apiclient/src/Client.php')) {
        return ['error', 'La librería de Google API no está instalada. Por favor, instale composer require google/apiclient:^2.0'];
    }
    
    try {
        require_once 'vendor/autoload.php';
        
        // Configurar cliente de Google
        $client = new Google_Client();
        $client->setApplicationName('Cotizador Importador');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        
        // Usar credenciales de servicio si existen
        if (file_exists('credentials.json')) {
            $client->setAuthConfig('credentials.json');
        } else {
            return ['error', 'No se encontró el archivo credentials.json para autenticar con Google Sheets'];
        }
        
        $service = new Google_Service_Sheets($client);
        
        // Obtener datos de la hoja
        $range = 'A1:Z100'; // Ajustar según necesidad
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();
        
        if (empty($values)) {
            return ['error', 'No se encontraron datos en la hoja de Google Sheets'];
        }
        
        // Convertir a formato CSV en memoria
        $tempFile = tempnam(sys_get_temp_dir(), 'gsheet');
        $handle = fopen($tempFile, 'w');
        
        foreach ($values as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        // Procesar como CSV
        $result = procesarCSV($tempFile, $conn);
        unlink($tempFile);
        
        return $result;
        
    } catch (Exception $e) {
        return ['error', 'Error al importar desde Google Sheets: ' . $e->getMessage()];
    }
}

// Función para procesar los archivos específicos
function procesarArchivosEspecificos($files, $conn) {
    $resultados = [];
    
    // Procesar ascensores.csv
    if (isset($files['ascensores_csv']) && $files['ascensores_csv']['error'] === UPLOAD_ERR_OK) {
        $resultados[] = 'Procesando ascensores.csv...';
        list($tipo, $msg) = procesarCSV($files['ascensores_csv']['tmp_name'], $conn);
        $resultados[] = "Resultado: $msg";
    }
    
    // Procesar adicionales.csv
    if (isset($files['adicionales_csv']) && $files['adicionales_csv']['error'] === UPLOAD_ERR_OK) {
        $resultados[] = 'Procesando adicionales.csv...';
        
        // Aquí podríamos añadir lógica específica para procesar adicionales
        // Por ahora, usamos el mismo procesador genérico
        list($tipo, $msg) = procesarCSV($files['adicionales_csv']['tmp_name'], $conn);
        $resultados[] = "Resultado: $msg";
    }
    
    // Procesar descuentos.csv
    if (isset($files['descuentos_csv']) && $files['descuentos_csv']['error'] === UPLOAD_ERR_OK) {
        $resultados[] = 'Procesando descuentos.csv...';
        
        // Aquí podríamos añadir lógica específica para procesar descuentos
        // Por ahora, usamos el mismo procesador genérico
        list($tipo, $msg) = procesarCSV($files['descuentos_csv']['tmp_name'], $conn);
        $resultados[] = "Resultado: $msg";
    }
    
    if (empty($resultados)) {
        return ['error', 'No se seleccionó ningún archivo para importar'];
    }
    
    return ['success', implode('<br>', $resultados)];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        if (isset($_POST['importar_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            list($tipo_mensaje, $mensaje) = procesarCSV($_FILES['csv_file']['tmp_name'], $conn);
        } else if (isset($_POST['importar_sheets']) && !empty($_POST['spreadsheet_id'])) {
            list($tipo_mensaje, $mensaje) = importarDesdeGoogleSheets($_POST['spreadsheet_id'], $conn);
        } else if (isset($_POST['importar_especificos'])) {
            list($tipo_mensaje, $mensaje) = procesarArchivosEspecificos($_FILES, $conn);
        } else {
            $tipo_mensaje = 'error';
            $mensaje = 'No se recibieron los datos necesarios';
        }
    } catch (Exception $e) {
        $tipo_mensaje = 'error';
        $mensaje = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar desde Google Sheets o CSV</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        h1, h2 { margin-top: 0; }
        .card { background-color: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-secondary { background-color: #f5f5f5; color: #333; border: 1px solid #ddd; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 15px; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab.active { border-bottom-color: #4CAF50; font-weight: 500; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .note { background-color: #fff3cd; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Importar desde Google Sheets o CSV</h1>
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab active" data-tab="csv">Importar desde CSV</div>
                <div class="tab" data-tab="sheets">Importar desde Google Sheets</div>
                <div class="tab" data-tab="specific">Importar Archivos Específicos</div>
            </div>
            
            <div class="tab-content active" id="tab-csv">
                <div class="note">
                    <p><strong>Nota:</strong> El archivo CSV debe tener las siguientes columnas:</p>
                    <ul>
                        <li>Una columna con el nombre de la opción</li>
                        <li>Columnas para los precios de cada plazo (90 dias, 160/180 dias, 270 dias)</li>
                        <li>Opcionalmente, una columna con el nombre del producto</li>
                    </ul>
                </div>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Archivo CSV:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </div>
                    
                    <button type="submit" name="importar_csv">Importar desde CSV</button>
                </form>
            </div>
            
            <div class="tab-content" id="tab-sheets">
                <div class="note">
                    <p><strong>Nota:</strong> La hoja de Google Sheets debe tener el mismo formato que el CSV descrito arriba.</p>
                    <p>Para usar esta opción, necesita tener configuradas las credenciales de Google API en el archivo credentials.json.</p>
                </div>
                
                <form method="post">
                    <div class="form-group">
                        <label for="spreadsheet_id">ID de la hoja de Google Sheets:</label>
                        <input type="text" name="spreadsheet_id" id="spreadsheet_id" placeholder="Ej: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" required>
                        <small>El ID se encuentra en la URL de la hoja: https://docs.google.com/spreadsheets/d/[ID]/edit</small>
                    </div>
                    
                    <button type="submit" name="importar_sheets">Importar desde Google Sheets</button>
                </form>
            </div>
            
            <div class="tab-content" id="tab-specific">
                <div class="note">
                    <p><strong>Nota:</strong> Esta opción está diseñada específicamente para importar los archivos:</p>
                    <ul>
                        <li><strong>ascensores.csv</strong> - Contiene los productos y opciones principales</li>
                        <li><strong>adicionales.csv</strong> - Contiene los adicionales para cada producto</li>
                        <li><strong>descuentos.csv</strong> - Contiene los descuentos aplicables</li>
                    </ul>
                </div>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="ascensores_csv">Archivo ascensores.csv:</label>
                        <input type="file" name="ascensores_csv" id="ascensores_csv" accept=".csv">
                    </div>
                    
                    <div class="form-group">
                        <label for="adicionales_csv">Archivo adicionales.csv:</label>
                        <input type="file" name="adicionales_csv" id="adicionales_csv" accept=".csv">
                    </div>
                    
                    <div class="form-group">
                        <label for="descuentos_csv">Archivo descuentos.csv:</label>
                        <input type="file" name="descuentos_csv" id="descuentos_csv" accept=".csv">
                    </div>
                    
                    <button type="submit" name="importar_especificos">Importar Archivos</button>
                </form>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="cotizador_xls_fixed.php" style="padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Ir al Cotizador</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Activar tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Mostrar contenido
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === `tab-${tabId}`) {
                            content.classList.add('active');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

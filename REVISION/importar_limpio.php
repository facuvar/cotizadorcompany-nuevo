<?php
// Script para importar datos desde un archivo Excel con fórmulas (versión limpia)
require_once 'vendor/autoload.php';
require_once 'sistema/config.php';
require_once 'sistema/includes/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;

// Variables globales
$logMensajes = [];
$resumen = [
    'productos' => 0,
    'opciones' => 0,
    'precios' => 0,
    'adicionales' => 0,
    'descuentos' => 0
];
$importacionExitosa = false;

// Función para registrar mensajes en el log
function registrarLog($mensaje) {
    global $logMensajes;
    $logMensajes[] = $mensaje;
}

// Función para limpiar y convertir valores monetarios
function limpiarValorMonetario($valor) {
    // Si es una cadena, limpiar caracteres no numéricos
    if (is_string($valor)) {
        $valor = preg_replace('/[^0-9.,]/', '', $valor);
        $valor = str_replace(['.', ','], ['', '.'], $valor);
    }
    
    return floatval($valor);
}

// Función para obtener el ID del plazo según su nombre
function obtenerPlazoId($conn, $nombrePlazo) {
    // Extraer el plazo del nombre (por ejemplo, "Precio 90 dias" -> "90 dias")
    $plazo = str_replace("Precio ", "", $nombrePlazo);
    
    // Buscar el plazo en la base de datos
    $stmt = $conn->prepare("SELECT id FROM xls_plazos WHERE nombre LIKE ?");
    $busqueda = "%$plazo%";
    $stmt->bind_param("s", $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    }
    
    // Si no se encuentra, crear el plazo
    $multiplicador = 1.0;
    if (strpos($plazo, "90") !== false) {
        $multiplicador = 1.3; // 30% adicional
    } else if (strpos($plazo, "270") !== false) {
        $multiplicador = 0.9; // 10% descuento
    }
    
    $stmt = $conn->prepare("INSERT INTO xls_plazos (nombre, multiplicador) VALUES (?, ?)");
    $stmt->bind_param("sd", $plazo, $multiplicador);
    $stmt->execute();
    
    return $conn->insert_id;
}

// Función para procesar la hoja de productos
function procesarHoja($worksheet, $conn) {
    global $resumen;
    
    // Variables para el procesamiento
    $productos = [];
    $currentProduct = null;
    $productoId = null;
    $opciones = [];
    $plazos = [];
    
    // Obtener el rango de datos
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    
    // Identificar columnas de plazos en la primera fila
    for ($col = 'C'; $col <= $highestColumn; $col++) {
        $header = $worksheet->getCell($col . '1')->getValue();
        if (strpos(strtolower($header), 'precio') !== false) {
            $plazos[$col] = $header;
        }
    }
    
    if (empty($plazos)) {
        registrarLog("No se encontraron columnas de precios en la hoja");
        return false;
    }
    
    registrarLog("Plazos de entrega detectados: " . implode(", ", $plazos));
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Procesar cada fila
        $lastProductName = '';
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $productName = trim($worksheet->getCell('A' . $row)->getValue());
            $opcionName = trim($worksheet->getCell('B' . $row)->getValue());
            
            // Si hay un producto en columna A, usarlo como producto actual
            if (!empty($productName)) {
                $currentProduct = $productName;
                
                // Si es un nuevo producto (diferente al anterior)
                if ($currentProduct != $lastProductName) {
                    // Verificar si el producto ya existe
                    $query = "SELECT id FROM xls_productos WHERE nombre = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $currentProduct);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        // Crear el producto
                        $query = "INSERT INTO xls_productos (nombre) VALUES (?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("s", $currentProduct);
                        $stmt->execute();
                        $productoId = $conn->insert_id;
                        $resumen['productos']++;
                        registrarLog("Producto creado: $currentProduct (ID: $productoId)");
                    } else {
                        $row_product = $result->fetch_assoc();
                        $productoId = $row_product['id'];
                        registrarLog("Producto encontrado: $currentProduct (ID: $productoId)");
                    }
                    
                    $lastProductName = $currentProduct;
                }
            }
            
            // Si hay una opción en columna B y un producto actual, procesar la opción
            if (!empty($opcionName) && !empty($currentProduct) && !empty($productoId)) {
                // Verificar si la opción ya existe para este producto
                $query = "SELECT id FROM xls_opciones WHERE producto_id = ? AND nombre = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("is", $productoId, $opcionName);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    // Crear la opción
                    $query = "INSERT INTO xls_opciones (producto_id, nombre) VALUES (?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("is", $productoId, $opcionName);
                    $stmt->execute();
                    $opcionId = $conn->insert_id;
                    $resumen['opciones']++;
                    registrarLog("Opción creada: $opcionName para producto $currentProduct");
                } else {
                    $row_opcion = $result->fetch_assoc();
                    $opcionId = $row_opcion['id'];
                }
                
                // Procesar precios para cada plazo
                foreach ($plazos as $col => $plazoNombre) {
                    // Obtener el valor calculado (no la fórmula)
                    $precioCalculado = $worksheet->getCell($col . $row)->getCalculatedValue();
                    
                    // Limpiar y convertir el valor
                    $precio = limpiarValorMonetario($precioCalculado);
                    
                    // Obtener el ID del plazo
                    $plazoId = obtenerPlazoId($conn, $plazoNombre);
                    
                    // Guardar el precio
                    $query = "INSERT INTO xls_precios (opcion_id, plazo_id, precio) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iid", $opcionId, $plazoId, $precio);
                    $stmt->execute();
                    $resumen['precios']++;
                    
                    registrarLog("Precio guardado para opción '$opcionName', plazo ID '$plazoId': $precio");
                }
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        registrarLog("Importación de la hoja completada con éxito");
        return true;
        
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $conn->rollback();
        registrarLog("Error al procesar la hoja: " . $e->getMessage());
        return false;
    }
}

// Iniciar el procesamiento
try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si se ha enviado un formulario
    $archivoSubido = false;
    
    if (isset($_POST['submit'])) {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
            $tempFile = $_FILES['excel_file']['tmp_name'];
            $fileName = $_FILES['excel_file']['name'];
            
            // Verificar extensión
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($fileExt !== 'xlsx') {
                $mensaje = "<div class='alert alert-danger'>El archivo debe ser un archivo Excel (.xlsx)</div>";
            } else {
                $archivoSubido = true;
                
                // Limpiar la base de datos si se solicita
                if (isset($_POST['limpiar_antes']) && $_POST['limpiar_antes'] == 'si') {
                    registrarLog("Limpiando base de datos antes de importar...");
                    
                    // Eliminar datos existentes
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                    $conn->query("TRUNCATE TABLE xls_precios");
                    $conn->query("TRUNCATE TABLE xls_opciones");
                    $conn->query("TRUNCATE TABLE xls_adicionales_precios");
                    $conn->query("TRUNCATE TABLE xls_productos_adicionales");
                    $conn->query("TRUNCATE TABLE xls_adicionales");
                    $conn->query("TRUNCATE TABLE xls_productos");
                    $conn->query("TRUNCATE TABLE xls_plazos");
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    
                    registrarLog("Base de datos limpiada correctamente");
                }
                
                // Cargar el archivo Excel
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(false); // Importante: leer fórmulas
                
                // Establecer tiempo máximo de ejecución
                set_time_limit(600); // 10 minutos
                
                $spreadsheet = $reader->load($tempFile);
                
                // Obtener las hojas disponibles
                $sheetNames = $spreadsheet->getSheetNames();
                
                // Procesar cada hoja
                $hojasExitosas = 0;
                foreach ($sheetNames as $sheetName) {
                    $worksheet = $spreadsheet->getSheetByName($sheetName);
                    
                    registrarLog("Procesando hoja: $sheetName");
                    if (procesarHoja($worksheet, $conn)) {
                        $hojasExitosas++;
                    }
                }
                
                $importacionExitosa = true;
                registrarLog("Importación finalizada");
            }
        } else if (isset($_FILES['excel_file'])) {
            $mensaje = "<div class='alert alert-danger'>Error al subir el archivo: " . $_FILES['excel_file']['error'] . "</div>";
        }
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar desde Excel con Fórmulas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        .card {
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        .note {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        .btn {
            display: inline-block;
            background-color: #0d6efd;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .log-container {
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.85rem;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
        }
        .log-entry {
            margin-bottom: 3px;
            border-bottom: 1px solid #eee;
            padding-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Importar desde Excel con Fórmulas</h1>
        
        <?php if ($importacionExitosa): ?>
            <div class="alert alert-success">
                <h4 class="alert-heading">¡Importación completada con éxito!</h4>
                <p>Se han importado:</p>
                <ul>
                    <li><strong><?php echo $resumen['productos']; ?></strong> productos</li>
                    <li><strong><?php echo $resumen['opciones']; ?></strong> opciones</li>
                    <li><strong><?php echo $resumen['precios']; ?></strong> precios</li>
                </ul>
                <hr>
                <p>Ahora puede utilizar el cotizador con los nuevos datos.</p>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="cotizador_xls_fixed.php" class="btn btn-success">
                    <i class="bi bi-calculator me-2"></i> Ir al Cotizador
                </a>
                
                <a href="admin/index.php" class="btn btn-primary">
                    <i class="bi bi-gear me-2"></i> Ir al Panel de Administración
                </a>
                
                <a href="importar_limpio.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-repeat me-2"></i> Realizar otra importación
                </a>
            </div>
            
            <div class="mt-4">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#logDetails">
                    <i class="bi bi-terminal me-1"></i> Mostrar detalles técnicos
                </button>
                
                <div class="collapse mt-2" id="logDetails">
                    <div class="card">
                        <div class="card-header">
                            Detalles de la importación
                        </div>
                        <div class="card-body">
                            <div class="log-container">
                                <?php foreach ($logMensajes as $log): ?>
                                <div class="log-entry"><?php echo htmlspecialchars($log); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="note">
                        <p><strong>Nota:</strong> Este script importará datos directamente desde un archivo Excel (.xlsx) con fórmulas.</p>
                        <p>El sistema calculará los valores de las fórmulas y los importará correctamente.</p>
                        <p>Esta versión utiliza la nueva estructura de la base de datos con la tabla xls_plazos.</p>
                    </div>
                    
                    <?php if (isset($mensaje)) echo $mensaje; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="excel_file">Seleccione el archivo Excel:</label>
                            <input type="file" name="excel_file" id="excel_file" accept=".xlsx" required class="form-control">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" name="limpiar_antes" value="si" id="limpiar_antes" checked class="form-check-input">
                            <label for="limpiar_antes" class="form-check-label">Limpiar base de datos antes de importar (recomendado)</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="submit" class="btn btn-primary">
                                <i class="bi bi-file-earmark-excel me-2"></i> Importar datos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="admin/index.php" class="btn btn-outline-primary">
                    <i class="bi bi-gear me-2"></i> Ir al Panel de Administración
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

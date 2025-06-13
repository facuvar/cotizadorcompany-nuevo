<?php
// Activar visualización de errores para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Importar clases de PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Verificar si el administrador está logueado
requireAdmin();

// Crear directorios de uploads si no existen
if (!file_exists(XLS_DIR)) {
    mkdir(XLS_DIR, 0777, true);
}

// Registrar los datos que recibimos para depuración
$debugData = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'isAjax' => isAjax(),
    'files' => isset($_FILES) ? count($_FILES) : 0,
    'post' => isset($_POST) ? count($_POST) : 0
];

if (isAjax()) {
    // Para solicitudes AJAX, guardar la información de depuración en un archivo
    file_put_contents(XLS_DIR . '/debug_log.txt', date('Y-m-d H:i:s') . ' - ' . json_encode($debugData) . "\n", FILE_APPEND);
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Método no permitido', 'debug' => $debugData]);
        exit;
    } else {
        redirect(SITE_URL . '/admin/index.php');
    }
}

// Verificar que se haya subido un archivo
if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    $error = 'Error al subir el archivo';
    $errorDetails = isset($_FILES['excelFile']) ? $_FILES['excelFile']['error'] : 'No se recibió el archivo';
    
    switch ($errorDetails) {
        case UPLOAD_ERR_INI_SIZE:
            $error = 'El archivo excede el tamaño máximo permitido (php.ini)';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $error = 'El archivo excede el tamaño máximo permitido (formulario)';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error = 'El archivo se subió parcialmente';
            break;
        case UPLOAD_ERR_NO_FILE:
            $error = 'No se seleccionó ningún archivo';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $error = 'No existe directorio temporal';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $error = 'Error al escribir el archivo en disco';
            break;
        case UPLOAD_ERR_EXTENSION:
            $error = 'Carga detenida por extensión PHP';
            break;
    }
    
    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $error, 
            'debug' => [
                'error_code' => $errorDetails,
                'post_data' => $_POST,
                'files_data' => isset($_FILES['excelFile']) ? $_FILES['excelFile'] : 'No hay datos'
            ]
        ]);
        exit;
    } else {
        setFlashMessage($error, 'error');
        redirect(SITE_URL . '/admin/index.php');
    }
}

// Verificar que el archivo sea un Excel
$fileType = $_FILES['excelFile']['type'];
$allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/octet-stream'];
if (!in_array($fileType, $allowedTypes)) {
    $error = 'El archivo debe ser un Excel (.xls o .xlsx). Tipo detectado: ' . $fileType;
    
    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    } else {
        setFlashMessage($error, 'error');
        redirect(SITE_URL . '/admin/index.php');
    }
}

// Generar un nombre único para el archivo
$fileName = uniqid('excel_') . '.xlsx';
$filePath = XLS_DIR . '/' . $fileName;

// Mover el archivo subido
if (!move_uploaded_file($_FILES['excelFile']['tmp_name'], $filePath)) {
    $moveError = error_get_last();
    $error = 'Error al guardar el archivo: ' . ($moveError ? $moveError['message'] : 'Desconocido');
    
    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $error,
            'debug' => [
                'file_tmp' => $_FILES['excelFile']['tmp_name'],
                'dest_path' => $filePath,
                'dir_exists' => file_exists(XLS_DIR) ? 'Sí' : 'No',
                'dir_writable' => is_writable(XLS_DIR) ? 'Sí' : 'No',
                'user' => get_current_user()
            ]
        ]);
        exit;
    } else {
        setFlashMessage($error, 'error');
        redirect(SITE_URL . '/admin/index.php');
    }
}

try {
    // Comenzar una transacción con la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $conn->begin_transaction();
    
    // Limpiar las tablas existentes
    $conn->query("DELETE FROM presupuesto_detalle");
    $conn->query("DELETE FROM presupuestos");
    $conn->query("DELETE FROM opciones");
    $conn->query("DELETE FROM categorias");
    $conn->query("DELETE FROM fuente_datos");
    
    // Registrar la fuente de datos
    $query = "INSERT INTO fuente_datos (tipo, archivo) VALUES ('excel', ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $fileName);
    $stmt->execute();
    
    // Cargar el archivo Excel con PhpSpreadsheet
    $spreadsheet = IOFactory::load($filePath);
    
    // ===== PROCESAR HOJA 1: CATEGORÍAS =====
    $worksheet = $spreadsheet->getSheet(0); // Primera hoja (índice 0)
    $highestRow = $worksheet->getHighestRow();
    
    // Verificar que la hoja tenga el formato esperado
    $headers = [
        $worksheet->getCell('A1')->getValue(),
        $worksheet->getCell('B1')->getValue(),
        $worksheet->getCell('C1')->getValue()
    ];
    
    $expectedHeaders = ['Nombre', 'Descripción', 'Orden'];
    if ($headers != $expectedHeaders) {
        throw new Exception('La primera hoja (Categorías) no tiene el formato esperado. Encabezados esperados: ' . implode(', ', $expectedHeaders));
    }
    
    // Procesar las categorías (desde la fila 2 hasta el final)
    $categorias = [];
    for ($row = 2; $row <= $highestRow; $row++) {
        $nombre = $worksheet->getCell('A' . $row)->getValue();
        $descripcion = $worksheet->getCell('B' . $row)->getValue();
        $orden = (int)$worksheet->getCell('C' . $row)->getValue();
        
        if (!empty($nombre)) {
            // Insertar categoría en la base de datos
            $query = "INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $nombre, $descripcion, $orden);
            $stmt->execute();
            
            // Guardar el ID de la categoría para usarlo con las opciones
            $categorias[$nombre] = [
                'id' => $conn->insert_id,
                'nombre' => $nombre
            ];
        }
    }
    
    // ===== PROCESAR HOJA 2: OPCIONES =====
    if ($spreadsheet->getSheetCount() < 2) {
        throw new Exception('El archivo debe tener al menos 2 hojas (Categorías y Opciones).');
    }
    
    $worksheet = $spreadsheet->getSheet(1); // Segunda hoja (índice 1)
    $highestRow = $worksheet->getHighestRow();
    
    // Verificar que la hoja tenga el formato esperado
    $headers = [
        $worksheet->getCell('A1')->getValue(),
        $worksheet->getCell('B1')->getValue(),
        $worksheet->getCell('C1')->getValue(),
        $worksheet->getCell('D1')->getValue(),
        $worksheet->getCell('E1')->getValue(),
        $worksheet->getCell('F1')->getValue()
    ];
    
    $expectedHeaders = ['Categoría', 'Nombre', 'Descripción', 'Precio', 'Es Obligatorio', 'Orden'];
    if ($headers != $expectedHeaders) {
        throw new Exception('La segunda hoja (Opciones) no tiene el formato esperado. Encabezados esperados: ' . implode(', ', $expectedHeaders));
    }
    
    // Procesar las opciones (desde la fila 2 hasta el final)
    for ($row = 2; $row <= $highestRow; $row++) {
        $categoriaNombre = $worksheet->getCell('A' . $row)->getValue();
        $nombre = $worksheet->getCell('B' . $row)->getValue();
        $descripcion = $worksheet->getCell('C' . $row)->getValue();
        $precio = (float)$worksheet->getCell('D' . $row)->getValue();
        $esObligatorio = $worksheet->getCell('E' . $row)->getValue() == 'Sí' ? 1 : 0;
        $orden = (int)$worksheet->getCell('F' . $row)->getValue();
        
        if (!empty($categoriaNombre) && !empty($nombre)) {
            // Buscar el ID de la categoría
            if (!isset($categorias[$categoriaNombre])) {
                // Si la categoría no existe, la creamos
                $query = "INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, 999)";
                $stmt = $conn->prepare($query);
                $descripcionDefault = 'Categoría para ' . $categoriaNombre;
                $stmt->bind_param('ss', $categoriaNombre, $descripcionDefault);
                $stmt->execute();
                
                $categorias[$categoriaNombre] = [
                    'id' => $conn->insert_id,
                    'nombre' => $categoriaNombre
                ];
            }
            
            $categoriaId = $categorias[$categoriaNombre]['id'];
            
            // Insertar opción en la base de datos
            $query = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('issdii', $categoriaId, $nombre, $descripcion, $precio, $esObligatorio, $orden);
            $stmt->execute();
        }
    }
    
    // ===== PROCESAR HOJA 3: CONFIGURACIONES (si existe) =====
    if ($spreadsheet->getSheetCount() >= 3) {
        $worksheet = $spreadsheet->getSheet(2); // Tercera hoja (índice 2)
        $highestRow = $worksheet->getHighestRow();
        
        // Verificar que la hoja tenga el formato esperado
        $headers = [
            $worksheet->getCell('A1')->getValue(),
            $worksheet->getCell('B1')->getValue(),
            $worksheet->getCell('C1')->getValue()
        ];
        
        $expectedHeaders = ['Nombre', 'Valor', 'Descripción'];
        if ($headers == $expectedHeaders) {
            // Procesar las configuraciones (desde la fila 2 hasta el final)
            for ($row = 2; $row <= $highestRow; $row++) {
                $nombre = $worksheet->getCell('A' . $row)->getValue();
                $valor = $worksheet->getCell('B' . $row)->getValue();
                $descripcion = $worksheet->getCell('C' . $row)->getValue();
                
                if (!empty($nombre) && !empty($valor)) {
                    // Verificar si la configuración ya existe
                    $query = "SELECT id FROM configuraciones WHERE nombre = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('s', $nombre);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Actualizar configuración existente
                        $query = "UPDATE configuraciones SET valor = ?, descripcion = ? WHERE nombre = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('sss', $valor, $descripcion, $nombre);
                    } else {
                        // Insertar nueva configuración
                        $query = "INSERT INTO configuraciones (nombre, valor, descripcion) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('sss', $nombre, $valor, $descripcion);
                    }
                    
                    $stmt->execute();
                }
            }
        }
    }
    
    // Confirmar la transacción
    $conn->commit();
    
    // Generar resumen de datos procesados
    $resumen = [
        'categorias' => count($categorias),
        'opciones' => 0
    ];
    
    // Contar opciones
    $result = $conn->query("SELECT COUNT(*) as total FROM opciones");
    if ($result) {
        $row = $result->fetch_assoc();
        $resumen['opciones'] = $row['total'];
    }
    
    // Éxito - SIEMPRE devolver JSON para solicitudes POST (asumimos que son desde JavaScript)
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Archivo procesado correctamente: ' . $resumen['categorias'] . ' categorías y ' . $resumen['opciones'] . ' opciones importadas.',
        'filename' => $fileName,
        'filepath' => $filePath,
        'resumen' => $resumen
    ]);
    exit;
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    $error = 'Error al procesar el archivo: ' . $e->getMessage();
    
    if (isAjax()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $error,
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        exit;
    } else {
        setFlashMessage($error, 'error');
        redirect(SITE_URL . '/admin/index.php');
    }
} 
<?php
/**
 * API para generar presupuestos
 * Usado por el cotizador moderno
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de configuración no encontrado'
    ]);
    exit;
}

require_once $configPath;

// Cargar DB
$dbPath = __DIR__ . '/../includes/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de base de datos no encontrado'
    ]);
    exit;
}

require_once $dbPath;

// Cargar el manejador de correos
$emailPaths = [
    __DIR__ . '/../includes/email_handler.php',   // Ubicación dentro de sistema
    __DIR__ . '/../../includes/email_handler.php',   // Ubicación en raíz
];

$emailLoaded = false;
foreach ($emailPaths as $emailPath) {
    if (file_exists($emailPath)) {
        require_once $emailPath;
        $emailLoaded = true;
        break;
    }
}

if (!$emailLoaded) {
    error_log('Advertencia: No se pudo cargar el manejador de correos');
}

try {
    // Validar datos requeridos
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $opciones_json = $_POST['opciones'] ?? '';
    $plazo = $_POST['plazo'] ?? '90';
    
    if (empty($nombre) || empty($email)) {
        throw new Exception('Nombre y email son requeridos');
    }
    
    if (empty($opciones_json)) {
        throw new Exception('Debe seleccionar al menos una opción');
    }
    
    $opciones_ids = json_decode($opciones_json, true);
    if (!is_array($opciones_ids) || empty($opciones_ids)) {
        throw new Exception('Opciones inválidas');
    }
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Obtener detalles de las opciones seleccionadas
    $placeholders = str_repeat('?,', count($opciones_ids) - 1) . '?';
    $query = "SELECT o.*, c.nombre as categoria_nombre 
              FROM opciones o 
              LEFT JOIN categorias c ON o.categoria_id = c.id 
              WHERE o.id IN ($placeholders)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    
    $types = str_repeat('i', count($opciones_ids));
    $stmt->bind_param($types, ...$opciones_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $opciones_detalle = [];
    while ($row = $result->fetch_assoc()) {
        $opciones_detalle[] = $row;
    }
    
    if (empty($opciones_detalle)) {
        throw new Exception('No se encontraron las opciones seleccionadas');
    }
    
    // Calcular totales
    $subtotal = 0;
    $descuento_porcentaje = 0;
    
    foreach ($opciones_detalle as $opcion) {
        if ($opcion['categoria_id'] == 3 && isset($opcion['descuento']) && $opcion['descuento'] > 0) {
            // Es un descuento
            $descuento_porcentaje = max($descuento_porcentaje, $opcion['descuento']);
        } else {
            // Es un producto con precio
            $precio_campo = "precio_{$plazo}_dias";
            $precio = $opcion[$precio_campo] ?? 0;
            $subtotal += (float)$precio;
        }
    }
    
    $descuento = $subtotal * ($descuento_porcentaje / 100);
    $total = $subtotal - $descuento;
    
    // Generar número de presupuesto único
    $numero_presupuesto = 'PRES-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Verificar que el número no exista
    $check_query = "SELECT id FROM presupuestos WHERE numero_presupuesto = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('s', $numero_presupuesto);
    $stmt->execute();
    
    // Si existe, generar otro
    while ($stmt->get_result()->num_rows > 0) {
        $numero_presupuesto = 'PRES-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt->bind_param('s', $numero_presupuesto);
        $stmt->execute();
    }
    
    // Crear el JSON con las opciones y datos adicionales
    $presupuesto_data = [
        'opciones_ids' => $opciones_ids,
        'plazo_entrega' => $plazo,
        'opciones_detalle' => $opciones_detalle
    ];
    $opciones_json_final = json_encode($presupuesto_data);
    
    // Guardar presupuesto en la base de datos usando los nombres correctos de columnas
    $insert_query = "INSERT INTO presupuestos (
        numero_presupuesto,
        cliente_nombre, 
        cliente_email, 
        cliente_telefono, 
        cliente_empresa,
        subtotal,
        descuento_porcentaje,
        descuento_monto,
        total,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        throw new Exception('Error preparando inserción: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'sssssdddd',
        $numero_presupuesto,
        $nombre,
        $email,
        $telefono,
        $empresa,
        $subtotal,
        $descuento_porcentaje,
        $descuento,
        $total
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Error al guardar presupuesto: ' . $stmt->error);
    }
    
    $presupuesto_id = $conn->insert_id;
    
    // Ahora guardar el detalle de las opciones en una tabla separada si existe
    // O actualizar el presupuesto con el JSON de opciones
    $update_query = "UPDATE presupuestos SET updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param('i', $presupuesto_id);
        $stmt->execute();
    }
    
    // Preparar datos para el correo de notificación
    $presupuesto_data_email = [
        'quote_id' => $presupuesto_id,
        'numero_presupuesto' => $numero_presupuesto,
        'totals' => [
            'subtotal' => $subtotal,
            'descuento_porcentaje' => $descuento_porcentaje,
            'descuento_monto' => $descuento,
            'total' => $total
        ],
        'customer' => [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'empresa' => $empresa
        ],
        'plazo_entrega' => $plazo,
        'opciones_count' => count($opciones_detalle)
    ];
    
    // Enviar correo de notificación si el manejador está disponible
    $email_enviado = false;
    if ($emailLoaded && class_exists('EmailHandler')) {
        try {
            $emailHandler = new EmailHandler();
            $email_enviado = $emailHandler->enviarNotificacionPresupuesto($presupuesto_data_email);
            
            if ($email_enviado) {
                error_log("Correo de notificación enviado exitosamente para presupuesto: " . $numero_presupuesto);
            } else {
                error_log("Error al enviar correo de notificación para presupuesto: " . $numero_presupuesto);
            }
        } catch (Exception $e) {
            error_log("Error enviando correo para presupuesto {$numero_presupuesto}: " . $e->getMessage());
        }
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Presupuesto generado exitosamente',
        'quote_id' => $presupuesto_id,
        'numero_presupuesto' => $numero_presupuesto,
        'email_enviado' => $email_enviado,
        'totals' => [
            'subtotal' => $subtotal,
            'descuento_porcentaje' => $descuento_porcentaje,
            'descuento_monto' => $descuento,
            'total' => $total
        ],
        'customer' => [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'empresa' => $empresa
        ],
        'opciones_count' => count($opciones_detalle)
    ]);
    
} catch (Exception $e) {
    error_log('Error en generate_quote.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 
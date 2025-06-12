<?php
// Script para generar un PDF más detallado
// Requiere que se pase el ID del presupuesto

// Configurar cabeceras para forzar la descarga de un PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="presupuesto_detallado.pdf"');

// Verificar que se recibió un parámetro de ID
if (!isset($_GET['id'])) {
    die("Error: No se especificó el ID del presupuesto");
}

$presupuestoId = intval($_GET['id']);
if ($presupuestoId <= 0) {
    // Registrar el error para depuración
    $logFile = __DIR__ . '/../pdf_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] Error: ID de presupuesto no válido: {$_GET['id']}\n", FILE_APPEND);
    
    die("Error: ID de presupuesto no válido: {$_GET['id']}");
}

// Conectar a la base de datos
require_once __DIR__ . '/../sistema/config.php';
require_once __DIR__ . '/../sistema/includes/db.php';

try {
    // Obtener datos del presupuesto
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM presupuestos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $presupuestoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("Error: Presupuesto no encontrado");
    }
    
    $presupuesto = $result->fetch_assoc();
    
    // Crear un PDF más detallado
    $pdf = '%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>
endobj
4 0 obj
<< /Length 1000 >>
stream
BT
/F2 24 Tf
200 750 Td
(PRESUPUESTO) Tj
/F1 12 Tf
-150 -40 Td
(Fecha: ' . date('d/m/Y', strtotime($presupuesto['fecha_creacion'])) . ') Tj
0 -20 Td
(Código: ' . $presupuesto['codigo'] . ') Tj
0 -40 Td
/F2 16 Tf
(DATOS DEL CLIENTE) Tj
/F1 12 Tf
0 -25 Td
(Nombre: ' . $presupuesto['nombre_cliente'] . ') Tj
0 -20 Td
(Email: ' . $presupuesto['email_cliente'] . ') Tj
0 -20 Td
(Teléfono: ' . $presupuesto['telefono_cliente'] . ') Tj
0 -40 Td
/F2 16 Tf
(DETALLE DEL PRESUPUESTO) Tj
/F1 12 Tf
0 -25 Td
(Producto: ' . $presupuesto['producto_nombre'] . ') Tj
0 -20 Td
(Opción: ' . $presupuesto['opcion_nombre'] . ') Tj
0 -20 Td
(Plazo de entrega: ' . $presupuesto['plazo_nombre'] . ') Tj
0 -20 Td
(Forma de pago: ' . $presupuesto['forma_pago'] . ') Tj
0 -40 Td
/F2 14 Tf
(RESUMEN) Tj
/F1 12 Tf
0 -25 Td
(Subtotal: $' . number_format($presupuesto['subtotal'], 2, ',', '.') . ') Tj
0 -20 Td
(Descuento: $' . number_format($presupuesto['descuento'], 2, ',', '.') . ') Tj
0 -20 Td
/F2 14 Tf
(TOTAL: $' . number_format($presupuesto['total'], 2, ',', '.') . ') Tj
/F1 10 Tf
0 -50 Td
(Este presupuesto tiene una validez de 15 días a partir de la fecha de emisión.) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
6 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>
endobj
xref
0 7
0000000000 65535 f
0000000010 00000 n
0000000060 00000 n
0000000115 00000 n
0000000230 00000 n
0000001280 00000 n
0000001350 00000 n
trailer
<< /Size 7 /Root 1 0 R >>
startxref
1420
%%EOF';
    
    echo $pdf;
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

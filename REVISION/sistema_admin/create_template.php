<?php
require_once '../../vendor/autoload.php';
require_once '../config.php';
require_once '../includes/db.php';

// Limpiar cualquier salida previa
ob_clean();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Crear un nuevo objeto Spreadsheet
$spreadsheet = new Spreadsheet();

// ===== HOJA 1: CATEGORÍAS =====
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Categorías');

// Establecer encabezados
$sheet->setCellValue('A1', 'Nombre');
$sheet->setCellValue('B1', 'Descripción');
$sheet->setCellValue('C1', 'Orden');

// Aplicar estilo a los encabezados
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['bottom' => ['style' => Border::BORDER_MEDIUM]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E0E0E0']]
];
$sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

// Añadir datos de ejemplo
$categorias = [
    ['Tipo de Ascensor', 'Selecciona el tipo de ascensor que necesitas', 1],
    ['Capacidad', 'Selecciona la capacidad del ascensor', 2],
    ['Número de Paradas', 'Selecciona el número de pisos', 3],
    ['Acabados', 'Selecciona los acabados del ascensor', 4],
    ['Opciones Adicionales', 'Selecciona características adicionales', 5]
];

$row = 2;
foreach ($categorias as $categoria) {
    $sheet->setCellValue('A' . $row, $categoria[0]);
    $sheet->setCellValue('B' . $row, $categoria[1]);
    $sheet->setCellValue('C' . $row, $categoria[2]);
    $row++;
}

// Ajustar anchos de columna
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(50);
$sheet->getColumnDimension('C')->setWidth(10);

// ===== HOJA 2: OPCIONES =====
$sheet = $spreadsheet->createSheet();
$sheet->setTitle('Opciones');

// Establecer encabezados
$sheet->setCellValue('A1', 'Categoría');
$sheet->setCellValue('B1', 'Nombre');
$sheet->setCellValue('C1', 'Descripción');
$sheet->setCellValue('D1', 'Precio');
$sheet->setCellValue('E1', 'Es Obligatorio');
$sheet->setCellValue('F1', 'Orden');

// Aplicar estilo a los encabezados
$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

// Añadir datos de ejemplo
$opciones = [
    ['Tipo de Ascensor', 'Ascensor Eléctrico', 'Ascensor con motor eléctrico, ideal para edificios de media y alta altura', 15000.00, 'Sí', 1],
    ['Tipo de Ascensor', 'Ascensor Hidráulico', 'Ascensor con sistema hidráulico, ideal para edificios de baja altura', 12000.00, 'Sí', 2],
    ['Capacidad', '4 Personas (320 kg)', 'Capacidad para 4 personas o 320 kg', 0.00, 'Sí', 1],
    ['Capacidad', '6 Personas (480 kg)', 'Capacidad para 6 personas o 480 kg', 1500.00, 'Sí', 2],
    ['Número de Paradas', '2 Paradas', 'Ascensor con 2 paradas', 0.00, 'Sí', 1],
    ['Número de Paradas', '3 Paradas', 'Ascensor con 3 paradas', 2000.00, 'Sí', 2],
    ['Acabados', 'Acabado Estándar', 'Acabado básico con materiales estándar', 0.00, 'Sí', 1],
    ['Acabados', 'Acabado Premium', 'Acabado con materiales de alta calidad', 2500.00, 'Sí', 2]
];

$row = 2;
foreach ($opciones as $opcion) {
    $sheet->setCellValue('A' . $row, $opcion[0]);
    $sheet->setCellValue('B' . $row, $opcion[1]);
    $sheet->setCellValue('C' . $row, $opcion[2]);
    $sheet->setCellValue('D' . $row, $opcion[3]);
    $sheet->setCellValue('E' . $row, $opcion[4]);
    $sheet->setCellValue('F' . $row, $opcion[5]);
    $row++;
}

// Ajustar anchos de columna
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(50);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(10);

// ===== HOJA 3: CONFIGURACIONES =====
$sheet = $spreadsheet->createSheet();
$sheet->setTitle('Configuraciones');

// Establecer encabezados
$sheet->setCellValue('A1', 'Nombre');
$sheet->setCellValue('B1', 'Valor');
$sheet->setCellValue('C1', 'Descripción');

// Aplicar estilo a los encabezados
$sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

// Añadir datos de ejemplo
$configuraciones = [
    ['titulo_sistema', 'Sistema de Presupuestos de Ascensores', 'Título principal del sistema'],
    ['moneda', '€', 'Símbolo de moneda a usar'],
    ['iva', '21', 'Porcentaje de IVA a aplicar'],
    ['email_notificaciones', 'notificaciones@example.com', 'Email para recibir notificaciones']
];

$row = 2;
foreach ($configuraciones as $config) {
    $sheet->setCellValue('A' . $row, $config[0]);
    $sheet->setCellValue('B' . $row, $config[1]);
    $sheet->setCellValue('C' . $row, $config[2]);
    $row++;
}

// Ajustar anchos de columna
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(50);

// Seleccionar la primera hoja como activa
$spreadsheet->setActiveSheetIndex(0);

try {
    // Limpiar el buffer de salida
    if (ob_get_length()) ob_end_clean();
    
    // Configurar headers para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plantilla_importacion.xlsx"');
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');
    
    // Crear el writer y guardar directamente a la salida
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    // En caso de error, devolver JSON
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la plantilla: ' . $e->getMessage()
    ]);
} 
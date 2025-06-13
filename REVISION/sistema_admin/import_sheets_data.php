<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Verificar si el administrador está logueado
requireAdmin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener la URL de Google Sheets
    $query = "SELECT * FROM fuente_datos WHERE tipo = 'google_sheets' ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception('No se encontró la configuración de Google Sheets');
    }
    
    $dataSource = $result->fetch_assoc();
    $url = $dataSource['url'];
    
    // Extraer el ID del documento
    if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
        $docId = $matches[1];
    } else {
        throw new Exception('No se pudo extraer el ID del documento de la URL');
    }
    
    // Construir la URL de exportación
    $exportUrl = "https://docs.google.com/spreadsheets/d/{$docId}/export?format=xlsx";
    
    // Configurar el contexto HTTP
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 30,
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]
    ]);
    
    // Descargar el archivo
    $tempFile = tempnam(sys_get_temp_dir(), 'sheets_');
    $fileContent = @file_get_contents($exportUrl, false, $context);
    
    if ($fileContent === false) {
        $error = error_get_last();
        throw new Exception('Error al descargar el archivo: ' . ($error['message'] ?? 'Error desconocido'));
    }
    
    file_put_contents($tempFile, $fileContent);
    
    // Cargar el archivo con PhpSpreadsheet
    $spreadsheet = IOFactory::load($tempFile);
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    // Limpiar tablas existentes
    $conn->query("DELETE FROM presupuesto_detalle");
    $conn->query("DELETE FROM presupuestos");
    $conn->query("DELETE FROM opciones");
    $conn->query("DELETE FROM categorias");
    
    // Crear tabla de plazos si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS plazos_entrega (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL,
        descripcion TEXT,
        orden INT DEFAULT 0
    )");
    
    // Limpiar plazos existentes
    $conn->query("DELETE FROM plazos_entrega");
    
    // Insertar plazos de entrega
    $plazos = [
        ['nombre' => '160-180 días', 'descripcion' => 'Entrega en 160-180 días', 'orden' => 1],
        ['nombre' => '90 días', 'descripcion' => 'Entrega en 90 días', 'orden' => 2],
        ['nombre' => '270 días', 'descripcion' => 'Entrega en 270 días', 'orden' => 3]
    ];
    
    foreach ($plazos as $plazo) {
        $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, descripcion, orden) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $plazo['nombre'], $plazo['descripcion'], $plazo['orden']);
        $stmt->execute();
    }
    
    // Crear categorías principales
    $categorias = [
        'electro' => [
            'nombre' => 'EQUIPO ELECTROMECANICO 450KG CARGA UTIL',
            'descripcion' => 'Ascensor electromecánico con capacidad de 450KG',
            'orden' => 1
        ],
        'gearless' => [
            'nombre' => 'OPCION GEARLESS',
            'descripcion' => 'Ascensor con tecnología Gearless',
            'orden' => 2
        ],
        'hidraulico1' => [
            'nombre' => 'HIDRAULICO 450KG CENTRAL 13HP PISTON 1 TRAMO',
            'descripcion' => 'Ascensor hidráulico con pistón de 1 tramo',
            'orden' => 3
        ],
        'hidraulico2' => [
            'nombre' => 'HIDRAULICO 450KG CENTRAL 25LTS 4HP',
            'descripcion' => 'Ascensor hidráulico con central de 25LTS y 4HP',
            'orden' => 4
        ],
        'hidraulico3' => [
            'nombre' => 'MISMA CARACT QUE ANTERIOR PERO DIRECTO',
            'descripcion' => 'Ascensor hidráulico directo',
            'orden' => 5
        ],
        'domiciliario' => [
            'nombre' => 'DOMICILIARIO PUERTA PLEGADIZA CABINA EXT SIN PUERTAS',
            'descripcion' => 'Ascensor para uso residencial con puerta plegadiza',
            'orden' => 6
        ],
        'montavehiculos' => [
            'nombre' => 'MONTAVEHICULOS',
            'descripcion' => 'Elevador para vehículos',
            'orden' => 7
        ],
        'montacargas' => [
            'nombre' => 'MONTACARGAS - MAQUINA TAMBOR',
            'descripcion' => 'Montacargas con máquina de tambor',
            'orden' => 8
        ],
        'salvaescaleras' => [
            'nombre' => 'SALVAESCALERAS',
            'descripcion' => 'Elevador para personas con movilidad reducida',
            'orden' => 9
        ],
        'montaplatos' => [
            'nombre' => 'MONTAPLATOS',
            'descripcion' => 'Elevador para comidas y pequeñas cargas',
            'orden' => 10
        ],
        'giracoches' => [
            'nombre' => 'GIRACOCHES',
            'descripcion' => 'Sistema para girar vehículos',
            'orden' => 11
        ],
        'estructura' => [
            'nombre' => 'ESTRUCTURA',
            'descripcion' => 'Estructura metálica para ascensores',
            'orden' => 12
        ],
        'perfil' => [
            'nombre' => 'PEFIL DIVISORIO',
            'descripcion' => 'Perfil divisorio para instalaciones',
            'orden' => 13
        ],
        'adicionales' => [
            'nombre' => 'Opciones Adicionales',
            'descripcion' => 'Accesorios y opciones adicionales para tu ascensor',
            'orden' => 14
        ],
        'descuentos' => [
            'nombre' => 'Formas de Pago',
            'descripcion' => 'Descuentos disponibles según forma de pago',
            'orden' => 15
        ]
    ];
    
    // Insertar categorías
    foreach ($categorias as $key => $cat) {
        $query = "INSERT INTO categorias (nombre, descripcion, orden) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $cat['nombre'], $cat['descripcion'], $cat['orden']);
        $stmt->execute();
        $categorias[$key]['id'] = $conn->insert_id;
    }
    
    // Crear tabla para precios por plazo si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS opcion_precios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        opcion_id INT NOT NULL,
        plazo_entrega VARCHAR(50) NOT NULL,
        precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE
    )");
    
    // Limpiar precios existentes
    $conn->query("DELETE FROM opcion_precios");
    
    // =====================================================================
    // PROCESAR HOJA DE ASCENSORES
    // =====================================================================
    $worksheet = $spreadsheet->getSheetByName('ASCENSORES');
    if ($worksheet) {
        // Obtener datos de la hoja
        $data = $worksheet->toArray();
        $currentCategory = null;
        $orden = 1;
        
        // Índices de columnas para los precios según plazos
        $precio160_180 = 6; // Columna G
        $precio90 = 7;      // Columna H  
        $precio270 = 8;     // Columna I
        
        foreach ($data as $rowIndex => $row) {
            // Saltar filas vacías
            if (empty($row[0])) continue;
            
            $firstCell = trim($row[0]);
            
            // Detectar sección actual
            if (strpos($firstCell, 'EQUIPO ELECTROMECANICO') !== false) {
                $currentCategory = 'electro';
                // Verificar que las columnas tengan los encabezados correctos
                if (isset($data[$rowIndex+1])) {
                    $headerRow = $data[$rowIndex+1];
                    if (trim($headerRow[$precio160_180]) !== '160-180 dias' && 
                        trim($headerRow[$precio90]) !== '90 dias' && 
                        trim($headerRow[$precio270]) !== '270 dias') {
                        // Intentar encontrar los índices correctos
                        for ($i = 0; $i < count($headerRow); $i++) {
                            if (strpos(trim($headerRow[$i]), '160-180') !== false) $precio160_180 = $i;
                            if (strpos(trim($headerRow[$i]), '90 dias') !== false) $precio90 = $i;
                            if (strpos(trim($headerRow[$i]), '270 dias') !== false) $precio270 = $i;
                        }
                    }
                }
                continue;
            } elseif (strpos($firstCell, 'OPCION GEARLESS') !== false) {
                $currentCategory = 'gearless';
                continue;
            } elseif (strpos($firstCell, 'HIDRAULICO 450KG CENTRAL 13HP') !== false) {
                $currentCategory = 'hidraulico1';
                continue;
            } elseif (strpos($firstCell, 'HIDRAULICO 450KG CENTRAL 25LTS 4HP') !== false) {
                $currentCategory = 'hidraulico2';
                continue;
            } elseif (strpos($firstCell, 'MISMA CARACT QUE ANTERIOR PERO DIRECTO') !== false) {
                $currentCategory = 'hidraulico3';
                continue;
            } elseif (strpos($firstCell, 'DOMICILIARIO') !== false) {
                $currentCategory = 'domiciliario';
                continue;
            } elseif (strpos($firstCell, 'MONTAVEHICULOS') !== false) {
                $currentCategory = 'montavehiculos';
                continue;
            } elseif (strpos($firstCell, 'MONTACARGAS') !== false) {
                // Mensaje de depuración para identificar que encontró la categoría
                error_log("Encontrada categoría MONTACARGAS en la fila: " . ($rowIndex + 1));
                error_log("Contenido de la celda: " . $firstCell);
                $currentCategory = 'montacargas';
                
                // Verificar que las columnas tengan los encabezados correctos para esta sección
                if (isset($data[$rowIndex+1])) {
                    $headerRow = $data[$rowIndex+1];
                    error_log("Fila de encabezado siguiente a MONTACARGAS: " . json_encode($headerRow));
                    
                    // Imprimir todos los encabezados para diagnóstico
                    for ($i = 0; $i < count($headerRow); $i++) {
                        $headerCell = trim($headerRow[$i]);
                        if (!empty($headerCell)) {
                            error_log("MONTACARGAS - Encabezado en columna $i: '$headerCell'");
                        }
                    }
                    
                    // Buscar específicamente en la fila del encabezado de MONTACARGAS
                    for ($i = 0; $i < count($headerRow); $i++) {
                        $headerCell = trim($headerRow[$i]);
                        if (!empty($headerCell)) {
                            // Más flexible para capturar los encabezados específicos de MONTACARGAS
                            if (strpos($headerCell, '160') !== false && (strpos($headerCell, '180') !== false || strpos($headerCell, '/180') !== false)) {
                                $precio160_180 = $i;
                                error_log("MONTACARGAS - Índice para precio 160-180 días: $i");
                            }
                            if (strpos($headerCell, '90') !== false && strpos($headerCell, 'dia') !== false) {
                                $precio90 = $i;
                                error_log("MONTACARGAS - Índice para precio 90 días: $i");
                            }
                            if (strpos($headerCell, '270') !== false && strpos($headerCell, 'dia') !== false) {
                                $precio270 = $i;
                                error_log("MONTACARGAS - Índice para precio 270 días: $i");
                            }
                        }
                    }
                    
                    // Si aún no se han encontrado todos los encabezados, buscar en la fila siguiente
                    if (!isset($precio160_180) || !isset($precio90) || !isset($precio270)) {
                        if (isset($data[$rowIndex+2])) {
                            $nextHeaderRow = $data[$rowIndex+2];
                            error_log("MONTACARGAS - Buscando en la segunda fila después de MONTACARGAS: " . json_encode($nextHeaderRow));
                            
                            for ($i = 0; $i < count($nextHeaderRow); $i++) {
                                $headerCell = trim($nextHeaderRow[$i]);
                                if (!empty($headerCell)) {
                                    error_log("MONTACARGAS - Segunda fila, encabezado en columna $i: '$headerCell'");
                                    if (strpos($headerCell, '160') !== false && (strpos($headerCell, '180') !== false || strpos($headerCell, '/180') !== false)) {
                                        $precio160_180 = $i;
                                        error_log("MONTACARGAS - Índice para precio 160-180 días (segunda fila): $i");
                                    }
                                    if (strpos($headerCell, '90') !== false && strpos($headerCell, 'dia') !== false) {
                                        $precio90 = $i;
                                        error_log("MONTACARGAS - Índice para precio 90 días (segunda fila): $i");
                                    }
                                    if (strpos($headerCell, '270') !== false && strpos($headerCell, 'dia') !== false) {
                                        $precio270 = $i;
                                        error_log("MONTACARGAS - Índice para precio 270 días (segunda fila): $i");
                                    }
                                }
                            }
                        }
                    }
                }
                continue;
            } elseif (strpos($firstCell, 'SALVAESCALERAS') !== false) {
                // Mensaje de depuración para identificar que encontró la categoría
                error_log("Encontrada categoría SALVAESCALERAS en la fila: " . ($rowIndex + 1));
                error_log("Contenido de la celda: " . $firstCell);
                $currentCategory = 'salvaescaleras';
                
                // Verificar que las columnas tengan los encabezados correctos para esta sección
                if (isset($data[$rowIndex+1])) {
                    $headerRow = $data[$rowIndex+1];
                    error_log("Fila de encabezado siguiente a SALVAESCALERAS: " . json_encode($headerRow));
                    
                    // Imprimir todos los encabezados para diagnóstico
                    for ($i = 0; $i < count($headerRow); $i++) {
                        $headerCell = trim($headerRow[$i]);
                        if (!empty($headerCell)) {
                            error_log("SALVAESCALERAS - Encabezado en columna $i: '$headerCell'");
                        }
                    }
                    
                    // Buscar específicamente en la fila del encabezado de SALVAESCALERAS
                    for ($i = 0; $i < count($headerRow); $i++) {
                        $headerCell = trim($headerRow[$i]);
                        if (!empty($headerCell)) {
                            // Más flexible para capturar los encabezados específicos
                            if (strpos($headerCell, '160') !== false && (strpos($headerCell, '180') !== false || strpos($headerCell, '/180') !== false)) {
                                $precio160_180 = $i;
                                error_log("SALVAESCALERAS - Índice para precio 160-180 días: $i");
                            }
                            if (strpos($headerCell, '90') !== false && strpos($headerCell, 'dia') !== false) {
                                $precio90 = $i;
                                error_log("SALVAESCALERAS - Índice para precio 90 días: $i");
                            }
                            if (strpos($headerCell, '270') !== false && strpos($headerCell, 'dia') !== false) {
                                $precio270 = $i;
                                error_log("SALVAESCALERAS - Índice para precio 270 días: $i");
                            }
                        }
                    }
                    
                    // Si aún no se han encontrado todos los encabezados, buscar en la fila siguiente
                    if (!isset($precio160_180) || !isset($precio90) || !isset($precio270)) {
                        if (isset($data[$rowIndex+2])) {
                            $nextHeaderRow = $data[$rowIndex+2];
                            error_log("SALVAESCALERAS - Buscando en la segunda fila después de SALVAESCALERAS: " . json_encode($nextHeaderRow));
                            
                            for ($i = 0; $i < count($nextHeaderRow); $i++) {
                                $headerCell = trim($nextHeaderRow[$i]);
                                if (!empty($headerCell)) {
                                    error_log("SALVAESCALERAS - Segunda fila, encabezado en columna $i: '$headerCell'");
                                    if (strpos($headerCell, '160') !== false && (strpos($headerCell, '180') !== false || strpos($headerCell, '/180') !== false)) {
                                        $precio160_180 = $i;
                                        error_log("SALVAESCALERAS - Índice para precio 160-180 días (segunda fila): $i");
                                    }
                                    if (strpos($headerCell, '90') !== false && strpos($headerCell, 'dia') !== false) {
                                        $precio90 = $i;
                                        error_log("SALVAESCALERAS - Índice para precio 90 días (segunda fila): $i");
                                    }
                                    if (strpos($headerCell, '270') !== false && strpos($headerCell, 'dia') !== false) {
                                        $precio270 = $i;
                                        error_log("SALVAESCALERAS - Índice para precio 270 días (segunda fila): $i");
                                    }
                                }
                            }
                        }
                    }
                }
                continue;
            } elseif (strpos($firstCell, 'MONTAPLATOS') !== false) {
                $currentCategory = 'montaplatos';
                continue;
            } elseif (strpos($firstCell, 'GIRACOCHES') !== false) {
                $currentCategory = 'giracoches';
                continue;
            } elseif (strpos($firstCell, 'ESTRUCTURA') !== false) {
                $currentCategory = 'estructura';
                continue;
            } elseif (strpos($firstCell, 'PEFIL DIVISORIO') !== false) {
                $currentCategory = 'perfil';
                continue;
            }
            
            // Si no estamos en ninguna categoría, continuar
            if (!$currentCategory) continue;
            
            // Para la categoría MONTACARGAS, detectar filas específicas
            if ($currentCategory === 'montacargas') {
                // Verificar si la línea tiene el formato de MONTACARGAS (HASTA XXX KG PUERTA MANUAL)
                if (strpos($firstCell, 'HASTA') !== false && strpos($firstCell, 'KG') !== false && strpos($firstCell, 'PUERTA') !== false) {
                    $nombre = trim($firstCell);
                    $descripcion = "Montacargas {$nombre}";
                    
                    error_log("Procesando opción de MONTACARGAS: " . $nombre);
                    error_log("Valores de precios encontrados: 160-180=" . 
                        (isset($row[$precio160_180]) ? $row[$precio160_180] : 'no definido') . 
                        ", 90=" . (isset($row[$precio90]) ? $row[$precio90] : 'no definido') . 
                        ", 270=" . (isset($row[$precio270]) ? $row[$precio270] : 'no definido'));
                    
                    // Verificar si tenemos los precios para los diferentes plazos
                    $precio160_180_val = isset($row[$precio160_180]) ? floatval(str_replace(['$', ',', ' '], '', $row[$precio160_180])) : 0;
                    $precio90_val = isset($row[$precio90]) ? floatval(str_replace(['$', ',', ' '], '', $row[$precio90])) : 0;
                    $precio270_val = isset($row[$precio270]) ? floatval(str_replace(['$', ',', ' '], '', $row[$precio270])) : 0;
                    
                    error_log("Valores convertidos: 160-180=$precio160_180_val, 90=$precio90_val, 270=$precio270_val");
                    
                    // Al menos uno de los precios debe ser válido
                    if ($precio160_180_val > 0 || $precio90_val > 0 || $precio270_val > 0) {
                        // Insertar opción base (usamos el precio de 160-180 días como referencia)
                        $categoriaId = $categorias[$currentCategory]['id'];
                        $query = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) 
                                 VALUES (?, ?, ?, ?, 1, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('issdi', $categoriaId, $nombre, $descripcion, $precio160_180_val, $orden);
                        $result = $stmt->execute();
                        
                        error_log("Resultado de la inserción de MONTACARGAS: " . ($result ? "Éxito" : "Error - " . $stmt->error));
                        
                        $opcionId = $conn->insert_id;
                        
                        // Insertar precios por plazo
                        $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)");
                        
                        // Precio 160-180 días
                        $plazo = '160-180 días';
                        $stmt->bind_param('isd', $opcionId, $plazo, $precio160_180_val);
                        $stmt->execute();
                        
                        // Precio 90 días
                        $plazo = '90 días';
                        $stmt->bind_param('isd', $opcionId, $plazo, $precio90_val);
                        $stmt->execute();
                        
                        // Precio 270 días
                        $plazo = '270 días';
                        $stmt->bind_param('isd', $opcionId, $plazo, $precio270_val);
                        $stmt->execute();
                        
                        $orden++;
                    }
                    
                    continue; // Continuar con la siguiente fila después de procesar esta
                }
            }
            
            // Para la categoría SALVAESCALERAS, detectar filas específicas
            if ($currentCategory === 'salvaescaleras') {
                // Verificar si la línea tiene el formato de SALVAESCALERAS (diferentes formatos posibles)
                // Actualizado para reconocer los formatos exactos que aparecen en la hoja
                if (strpos($firstCell, 'MODELO SIMPLE H/') !== false || 
                    strpos($firstCell, 'MODELO COMPLETO H/') !== false || 
                    strpos($firstCell, 'MODELO RECTO') !== false || 
                    strpos($firstCell, 'MODELO CURVO') !== false ||
                    (strpos($firstCell, 'SALVA') !== false && strpos($firstCell, 'ESCALERA') !== false)) {
                    
                    $nombre = trim($firstCell);
                    $descripcion = "Salvaescaleras {$nombre}";
                    
                    error_log("Procesando opción de SALVAESCALERAS: " . $nombre);
                    error_log("Valores de precios encontrados: 160-180=" . 
                        (isset($row[$precio160_180]) ? $row[$precio160_180] : 'no definido') . 
                        ", 90=" . (isset($row[$precio90]) ? $row[$precio90] : 'no definido') . 
                        ", 270=" . (isset($row[$precio270]) ? $row[$precio270] : 'no definido'));
                    
                    // Verificar si tenemos los precios para los diferentes plazos
                    // Mejorar la limpieza de caracteres para convertir los precios correctamente
                    $precio160_180_val = isset($row[$precio160_180]) ? floatval(str_replace(['$', ',', ' '], '', $row[$precio160_180])) : 0;
                    $precio90_val = isset($row[$precio90]) ? floatval(str_replace(['$', ',', ' '], '', $row[$precio90])) : 0;
                    $precio270_val = isset($row[$precio270]) ? floatval(str_replace(['$', ',', ' '], '', $row[$precio270])) : 0;
                    
                    error_log("SALVAESCALERAS - Valores convertidos: 160-180=$precio160_180_val, 90=$precio90_val, 270=$precio270_val");
                    
                    // Al menos uno de los precios debe ser válido
                    if ($precio160_180_val > 0 || $precio90_val > 0 || $precio270_val > 0) {
                        // Insertar opción base (usamos el precio de 160-180 días como referencia)
                        $categoriaId = $categorias[$currentCategory]['id'];
                        $query = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) 
                                 VALUES (?, ?, ?, ?, 1, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('issdi', $categoriaId, $nombre, $descripcion, $precio160_180_val, $orden);
                        $result = $stmt->execute();
                        
                        error_log("Resultado de la inserción de SALVAESCALERAS: " . ($result ? "Éxito" : "Error - " . $stmt->error));
                        
                        $opcionId = $conn->insert_id;
                        
                        // Insertar precios por plazo
                        $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)");
                        
                        // Precio 160-180 días
                        $plazo = '160-180 días';
                        $stmt->bind_param('isd', $opcionId, $plazo, $precio160_180_val);
                        $stmt->execute();
                        
                        // Precio 90 días
                        $plazo = '90 días';
                        $stmt->bind_param('isd', $opcionId, $plazo, $precio90_val);
                        $stmt->execute();
                        
                        // Precio 270 días
                        $plazo = '270 días';
                        $stmt->bind_param('isd', $opcionId, $plazo, $precio270_val);
                        $stmt->execute();
                        
                        $orden++;
                    }
                    
                    continue; // Continuar con la siguiente fila después de procesar esta
                }
            }
            
            // Si es una fila de paradas (comienza con número y contiene "PARADAS")
            if (preg_match('/^\d+\s+PARADAS/i', $firstCell)) {
                $nombre = trim($firstCell);
                $descripcion = "Ascensor de {$nombre}";
                
                // Si estamos en la categoría MONTACARGAS, añadir información de depuración
                if ($currentCategory === 'montacargas') {
                    error_log("Procesando opción de MONTACARGAS: " . $nombre);
                    error_log("Valores de precios encontrados: 160-180=" . 
                        (isset($row[$precio160_180]) ? $row[$precio160_180] : 'no definido') . 
                        ", 90=" . (isset($row[$precio90]) ? $row[$precio90] : 'no definido') . 
                        ", 270=" . (isset($row[$precio270]) ? $row[$precio270] : 'no definido'));
                }
                
                // Verificar si tenemos los precios para los diferentes plazos
                $precio160_180_val = isset($row[$precio160_180]) ? floatval(str_replace(['$', ','], '', $row[$precio160_180])) : 0;
                $precio90_val = isset($row[$precio90]) ? floatval(str_replace(['$', ','], '', $row[$precio90])) : 0;
                $precio270_val = isset($row[$precio270]) ? floatval(str_replace(['$', ','], '', $row[$precio270])) : 0;
                
                // Si estamos en MONTACARGAS, registrar los valores convertidos
                if ($currentCategory === 'montacargas') {
                    error_log("Valores convertidos: 160-180=$precio160_180_val, 90=$precio90_val, 270=$precio270_val");
                }
                
                // Al menos uno de los precios debe ser válido
                if ($precio160_180_val > 0 || $precio90_val > 0 || $precio270_val > 0) {
                    // Insertar opción base (usamos el precio de 160-180 días como referencia)
                    $categoriaId = $categorias[$currentCategory]['id'];
                    $query = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) 
                             VALUES (?, ?, ?, ?, 1, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('issdi', $categoriaId, $nombre, $descripcion, $precio160_180_val, $orden);
                    $result = $stmt->execute();
                    
                    if ($currentCategory === 'montacargas') {
                        error_log("Resultado de la inserción de MONTACARGAS: " . ($result ? "Éxito" : "Error - " . $stmt->error));
                    }
                    
                    $opcionId = $conn->insert_id;
                    
                    // Insertar precios por plazo
                    $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)");
                    
                    // Precio 160-180 días
                    $plazo = '160-180 días';
                    $stmt->bind_param('isd', $opcionId, $plazo, $precio160_180_val);
                    $stmt->execute();
                    
                    // Precio 90 días
                    $plazo = '90 días';
                    $stmt->bind_param('isd', $opcionId, $plazo, $precio90_val);
                    $stmt->execute();
                    
                    // Precio 270 días
                    $plazo = '270 días';
                    $stmt->bind_param('isd', $opcionId, $plazo, $precio270_val);
                    $stmt->execute();
                    
                    $orden++;
                }
            }
        }
    }
    
    // =====================================================================
    // PROCESAR HOJA DE ADICIONALES
    // =====================================================================
    $worksheet = $spreadsheet->getSheetByName('ADICIONALES');
    if ($worksheet) {
        // Obtener datos de la hoja
        $data = $worksheet->toArray();
        $orden = 1;
        $categoriaId = $categorias['adicionales']['id'];
        
        // Índices para identificar las columnas relevantes
        $nombreIdx = 0;       // Primera columna (A) - Nombre del adicional
        $descripcionIdx = 1;  // Segunda columna (B) - Descripción (si existe)
        $precioIdx = 2;       // Tercera columna (C) - Precio del adicional
        
        foreach ($data as $rowIndex => $row) {
            // Saltar la primera fila (encabezados) y filas vacías
            if ($rowIndex === 0 || empty($row[$nombreIdx])) continue;
            
            $nombre = trim($row[$nombreIdx]);
            $descripcion = isset($row[$descripcionIdx]) ? trim($row[$descripcionIdx]) : '';
            
            // Si la descripción está vacía, usar el nombre como descripción
            if (empty($descripcion)) {
                $descripcion = "Adicional: {$nombre}";
            }
            
            // Procesar el precio (podría estar en diferentes formatos)
            $precio = 0;
            if (isset($row[$precioIdx])) {
                $precioStr = trim($row[$precioIdx]);
                // Eliminar símbolos de moneda y separadores
                $precio = floatval(str_replace(['$', ',', '.'], ['', '', '.'], $precioStr));
            }
            
            // Solo insertar si tiene un precio válido
            if ($precio > 0) {
                // Insertar la opción adicional
                $query = "INSERT INTO opciones (categoria_id, nombre, descripcion, precio, es_obligatorio, orden) 
                         VALUES (?, ?, ?, ?, 0, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('issdi', $categoriaId, $nombre, $descripcion, $precio, $orden);
                $stmt->execute();
                $opcionId = $conn->insert_id;
                
                // Para los adicionales, el precio es el mismo para todos los plazos
                $stmt = $conn->prepare("INSERT INTO opcion_precios (opcion_id, plazo_entrega, precio) VALUES (?, ?, ?)");
                
                foreach ($plazos as $plazo) {
                    $plazoNombre = $plazo['nombre'];
                    $stmt->bind_param('isd', $opcionId, $plazoNombre, $precio);
                    $stmt->execute();
                }
                
                $orden++;
            }
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Limpiar archivo temporal
    unlink($tempFile);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    throw $e;
} 
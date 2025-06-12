<?php
// Iniciar sesión antes de cualquier output
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Cargar configuración con manejo de errores
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("Error: Archivo de configuración no encontrado. Contacte al administrador.");
}

try {
    require_once $configPath;
} catch (Exception $e) {
    die("Error cargando configuración: " . $e->getMessage());
}

// Cargar archivos necesarios con manejo de errores
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/functions.php';
} catch (Exception $e) {
    if (defined('IS_RAILWAY') && IS_RAILWAY) {
        railway_log("Error loading includes: " . $e->getMessage());
        die("Error del sistema. Por favor, contacte al administrador.");
    } else {
        die("Error cargando archivos: " . $e->getMessage());
    }
}

// Intentar conectar a la base de datos
$dbConnected = false;
$db = null;
$conn = null;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if ($conn && !$conn->connect_error) {
        $dbConnected = true;
        
        // Verificar si hay datos cargados
        $query = "SELECT * FROM fuente_datos ORDER BY fecha_actualizacion DESC LIMIT 1";
        $result = $conn->query($query);
        
        if (!$result || $result->num_rows === 0) {
            // Si no hay datos, mostrar mensaje pero no fallar
            $sinDatos = true;
        } else {
            $sinDatos = false;
        }
    }
} catch (Exception $e) {
    if (defined('IS_RAILWAY') && IS_RAILWAY) {
        railway_log("Database connection error in cotizador: " . $e->getMessage());
    }
    $dbConnected = false;
}

// Inicializar variables por defecto
$plazos = [];
$categorias = null;
$plazoSeleccionado = '160-180 días';

// Si la DB está conectada, obtener datos
if ($dbConnected && $conn) {
    try {
        // Obtener plazos de entrega
        $query = "SELECT * FROM plazos_entrega ORDER BY orden ASC";
        $plazosResult = $conn->query($query);
        
        if ($plazosResult && $plazosResult->num_rows > 0) {
            while ($plazo = $plazosResult->fetch_assoc()) {
                $plazos[] = $plazo;
            }
        }
        
        // Plazo por defecto
        $plazoSeleccionado = isset($plazos[0]) ? $plazos[0]['nombre'] : '160-180 días';
        
        // Obtener categorías
        $query = "SELECT * FROM categorias ORDER BY orden ASC";
        $categorias = $conn->query($query);
        
    } catch (Exception $e) {
        if (defined('IS_RAILWAY') && IS_RAILWAY) {
            railway_log("Error getting data in cotizador: " . $e->getMessage());
        }
        // No fallar, solo usar valores por defecto
    }
}

// Si no hay plazos, usar valores por defecto
if (empty($plazos)) {
    $plazos = [
        ['id' => 1, 'nombre' => '90 días', 'multiplicador' => 1.15],
        ['id' => 2, 'nombre' => '160-180 días', 'multiplicador' => 1.0],
        ['id' => 3, 'nombre' => '270 días', 'multiplicador' => 0.85]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizador de Ascensores</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Estilos para el acordeón */
        .accordion {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .accordion-header {
            padding: 18px 20px;
            background-color: #f8f8f8;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s;
        }
        .accordion-header:hover {
            background-color: #f0f0f0;
        }
        .accordion-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
            flex: 1;
            /* Para nombres de categorías largos */
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
        .accordion-icon {
            transition: transform 0.3s;
            color: #4CAF50;
            margin-left: 15px;
        }
        .accordion-header.active {
            background-color: #e8f5e9;  /* Fondo verde claro cuando está activo */
        }
        .accordion-header.active .accordion-title {
            color: #2e7d32;  /* Verde más oscuro para el texto */
        }
        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
            color: #2e7d32;
        }
        .accordion-content {
            display: none;
            padding: 20px;
            border-top: 1px solid #ddd;
            background-color: white;
        }
        .accordion-content.active {
            display: block;
        }
        
        /* Estilos para las opciones */
        .options-list {
            margin-top: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        .option-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 4px;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .option-item:hover {
            background-color: #f9f9f9;
            border-color: #ddd;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .option-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .option-title label {
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            cursor: pointer;
        }
        .option-title input {
            margin-right: 8px;
            margin-top: 3px;
        }
        .option-description {
            margin-top: 8px;
            font-size: 14px;
            color: #666;
            flex-grow: 1;
        }
        .option-price {
            font-weight: bold;
            color: #e74c3c;
            white-space: nowrap;
            margin-left: 10px;
        }
        
        /* Estilos para el modelo seleccionado */
        .selected-model-summary {
            background-color: #f1f8e9;
            border: 1px solid #c5e1a5;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .selected-model-summary h4 {
            color: #33691e;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        /* Estilos para los plazos por opción */
        .option-plazos {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .option-plazo {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option-plazo.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        /* Estilos para el resumen */
        .summary-section {
            margin-top: 30px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .summary-total {
            margin-top: 15px;
            font-weight: bold;
            font-size: 18px;
            text-align: right;
        }
        
        /* Estilos para el flujo de pasos */
        .steps-section {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 15px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
            color: #999;
            font-weight: 500;
        }
        .step.active {
            color: #4CAF50;
            font-weight: bold;
        }
        .step.completed {
            color: #666;
        }
        .step:not(:last-child):after {
            content: "";
            position: absolute;
            top: 50%;
            right: 0;
            width: 20px;
            height: 2px;
            background-color: #ddd;
            transform: translateY(-50%);
        }
        
        /* Estilos para los pasos del cotizador */
        .cotizador-step {
            display: none;
        }
        .cotizador-step.active {
            display: block;
        }
        
        /* Estilos para los botones de navegación */
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .btn-secondary {
            background-color: #f1f1f1;
            color: #333;
        }
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        .btn-disabled {
            background-color: #cccccc;
            color: #666;
            cursor: not-allowed;
        }
        
        /* Estilos específicos para botones de plazos */
        .btn[data-plazo] {
            position: relative;
            overflow: hidden;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-primary[data-plazo] {
            background-color: #4CAF50;
            color: white;
            font-weight: 600;
        }
        .btn-secondary[data-plazo] {
            background-color: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
        }
        
        /* Estilos adicionales para las tablas */
        .table-responsive {
            margin-top: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .text-center {
            text-align: center !important;
        }
        
        .text-end, .text-right {
            text-align: right !important;
        }
        
        .form-check {
            display: inline-block;
            min-height: 1.5rem;
            padding-left: 1.5em;
            margin-bottom: 0.125rem;
        }
        
        .form-check-input {
            width: 1em;
            height: 1em;
            margin-top: 0.25em;
            vertical-align: top;
            border: 1px solid #dee2e6;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
        }
        
        .card-header {
            padding: 0.75rem 1.25rem;
            margin-bottom: 0;
            background-color: rgba(0,0,0,.03);
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        
        .card-body {
            flex: 1 1 auto;
            padding: 1.25rem;
        }
        
        .bg-primary {
            background-color: #007bff !important;
        }
        
        .text-white {
            color: #fff !important;
        }
        
        .mb-0 {
            margin-bottom: 0 !important;
        }
        
        .mt-3 {
            margin-top: 1rem !important;
        }
        
        .mt-4 {
            margin-top: 1.5rem !important;
        }
        
        .mb-4 {
            margin-bottom: 1.5rem !important;
        }
        
        /* Estilos para filas de tabla seleccionables */
        .table-striped tbody tr:hover {
            background-color: #f1f8e9 !important;
        }
        .table-striped tbody tr.selected {
            background-color: #e8f5e9 !important;
            border-left: 3px solid #4CAF50;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1>Cotizador de Ascensores</h1>
            </div>
        </div>
    </header>

    <?php if (defined('IS_RAILWAY') && IS_RAILWAY): ?>
    <!-- Debug info para Railway -->
    <div style="background: #e3f2fd; border: 1px solid #1976d2; padding: 10px; margin: 10px; border-radius: 5px; font-size: 14px;">
        <strong>🚂 Ejecutándose en Railway</strong><br>
        • Base de datos: <?php echo $dbConnected ? '✅ Conectada' : '❌ Desconectada'; ?><br>
        • Categorías cargadas: <?php echo ($categorias && $categorias->num_rows > 0) ? '✅ ' . $categorias->num_rows : '❌ 0'; ?><br>
        • Plazos disponibles: <?php echo count($plazos); ?><br>
        <?php if (isset($sinDatos) && $sinDatos): ?>
        • ⚠️ Sin datos en fuente_datos - usando valores por defecto<br>
        <?php endif; ?>
        <?php if (!$dbConnected): ?>
        • 🔧 <a href="../admin/" style="color: #1976d2;">Ir al panel admin</a> para verificar configuración<br>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$dbConnected): ?>
    <!-- Mensaje de error para usuarios -->
    <div style="background: #ffebee; border: 1px solid #f44336; padding: 15px; margin: 10px; border-radius: 5px; color: #c62828;">
        <h3>⚠️ Servicio temporalmente limitado</h3>
        <p>Estamos experimentando problemas técnicos con la base de datos. El cotizador funcionará con datos limitados.</p>
        <p>Por favor, contacte al administrador o intente nuevamente más tarde.</p>
    </div>
    <?php endif; ?>

    <main>
        <div class="container">
            <div class="cotizador-container">
                <form id="cotizadorForm" method="post" action="generar_presupuesto.php">
                    <div class="steps-section">
                        <div class="step active" data-step="1">1. Elegir Ascensor</div>
                        <div class="step" data-step="2">2. Opciones Adicionales</div>
                        <div class="step" data-step="3">3. Forma de Pago</div>
                        <div class="step" data-step="4">4. Datos de Contacto</div>
                    </div>
                    
                    <input type="hidden" name="plazo_entrega" id="plazoEntrega" value="<?php echo htmlspecialchars($plazoSeleccionado); ?>">
                    
                    <!-- Paso 1: Selección de Ascensor -->
                    <div class="cotizador-step active" id="step-1">
                        <h3>Seleccione el tipo y modelo de ascensor</h3>
                        <p>Elija el modelo de ascensor que mejor se adapte a sus necesidades y el plazo de entrega deseado.</p>
                        
                        <?php
                        // Filtrar solo las categorías de ascensores (no adicionales ni descuentos)
                        $categoriasAscensores = $categorias;
                        if ($categorias && $categorias->num_rows > 0):
                            $categorias->data_seek(0); // Resetear el puntero
                            $categoriasExcluir = ['Opciones Adicionales', 'Formas de Pago'];
                            $categoriasOrdenadas = array();
                            
                            // Primera pasada: recolectar categorías y ordenarlas por el campo "orden"
                            while ($cat = $categorias->fetch_assoc()) {
                                if (!in_array($cat['nombre'], $categoriasExcluir)) {
                                    $categoriasOrdenadas[$cat['orden']] = $cat;
                                }
                            }
                            
                            // Ordenar por la clave (orden)
                            ksort($categoriasOrdenadas);
                            
                            // Segunda pasada: mostrar las categorías ordenadas
                            foreach ($categoriasOrdenadas as $categoria):
                                // Tratamiento especial para GIRACOCHES
                                if ($categoria['nombre'] === 'GIRACOCHES'):
                        ?>
                            <div class="accordion" id="accordion-<?php echo $categoria['id']; ?>" style="border: 1px solid #e0f2e1; border-radius: 5px; overflow:hidden;">
                                <div class="accordion-header" onclick="toggleAccordion(this)" style="background-color: #e8f5e9;">
                                    <div class="accordion-title" style="color: #2e7d32; text-transform: uppercase; font-weight: 700;"><?php echo htmlspecialchars($categoria['nombre']); ?></div>
                                    <div class="accordion-icon" style="color: #2e7d32;"><i class="fas fa-chevron-down"></i></div>
                                </div>
                                <div class="accordion-content">
                                    <?php if (!empty($categoria['descripcion'])): ?>
                                        <p><?php echo htmlspecialchars($categoria['descripcion']); ?></p>
                                    <?php endif; ?>
                                    
                                    <p>Sistema para girar vehículos</p>
                                    <p>Sistema para girar vehículos</p>
                                    
                                    <div style="display: flex; flex-wrap: wrap; margin: -10px;">
                                    <?php
                                    // Obtener opciones para GIRACOCHES
                                    $query = "SELECT o.* FROM opciones o WHERE o.categoria_id = " . $categoria['id'] . " ORDER BY o.orden ASC";
                                    $opciones = $conn->query($query);
                                    
                                    if ($opciones && $opciones->num_rows > 0):
                                        while ($opcion = $opciones->fetch_assoc()):
                                            // Generar precios para todos los plazos basados en el precio base
                                            $precios = [];
                                            
                                            // Plazos predefinidos y sus multiplicadores
                                            $plazosMultiplicadores = [
                                                '30-60 días' => 1.15,     // 15% más caro
                                                '60-90 días' => 1.10,     // 10% más caro
                                                '90-120 días' => 1.05,    // 5% más caro
                                                '120-150 días' => 1.00,   // precio base
                                                '150-180 días' => 0.95,   // 5% más barato
                                                '180-210 días' => 0.90    // 10% más barato
                                            ];
                                            
                                            // Generar precios para cada plazo
                                            foreach ($plazosMultiplicadores as $plazo => $multiplicador) {
                                                $precios[$plazo] = $opcion['precio'] * $multiplicador;
                                            }
                                            
                                            // Si no hay plazos definidos, usar solo el precio base
                                            if (empty($precios)) {
                                                $precios[$plazoSeleccionado] = $opcion['precio'];
                                            }
                                            
                                            // Precio inicial a mostrar (del plazo por defecto)
                                            $precioMostrar = isset($precios[$plazoSeleccionado]) ? $precios[$plazoSeleccionado] : $opcion['precio'];
                                            
                                            // Convertir a JSON para uso en JavaScript
                                            $preciosJson = json_encode($precios);
                                    ?>
                                        <div style="flex: 0 0 calc(50% - 20px); margin: 10px;">
                                            <div style="border: 1px solid #eee; border-radius: 4px; overflow: hidden;">
                                                <div style="padding: 15px; background-color: #fff;">
                                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                                        <input type="radio" name="opcion_<?php echo $categoria['id']; ?>" 
                                                               value="<?php echo $opcion['id']; ?>" 
                                                               data-precio="<?php echo $precioMostrar; ?>"
                                                               data-precios='<?php echo htmlspecialchars($preciosJson); ?>'
                                                               data-plazo-seleccionado="<?php echo htmlspecialchars($plazoSeleccionado); ?>"
                                                               data-categoria-id="<?php echo $categoria['id']; ?>"
                                                               data-categoria-nombre="<?php echo htmlspecialchars($categoria['nombre']); ?>"
                                                               data-opcion-nombre="<?php echo htmlspecialchars($opcion['nombre']); ?>"
                                                               <?php echo $opcion['es_obligatorio'] ? 'required' : ''; ?>>
                                                        <span style="margin-left: 10px; font-weight: 500;"><?php echo htmlspecialchars($opcion['nombre']); ?></span>
                                                    </label>
                                                    <span style="float: right; font-weight: bold; color: #e74c3c;">$<?php echo number_format($precioMostrar, 2, ',', '.'); ?></span>
                                                    
                                                    <?php if (!empty($opcion['descripcion'])): ?>
                                                        <div style="margin-top: 10px; font-size: 14px; color: #666;">
                                                            <?php echo htmlspecialchars($opcion['descripcion']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Plazos de entrega por opción -->
                                                <div class="plazos-buttons" style="width: 100%;">
                                                <?php 
                                                // Asegurarse de mostrar todos los plazos disponibles para GIRACOCHES
                                                if (!empty($precios)):
                                                    // Ordenar plazos por la columna orden
                                                    $plazosOrdenados = [];
                                                    foreach ($plazos as $plazo) {
                                                        if (isset($precios[$plazo['nombre']])) {
                                                            $plazosOrdenados[] = $plazo;
                                                        }
                                                    }
                                                    
                                                    // Mostrar todos los plazos con precios
                                                    foreach ($plazosOrdenados as $plazo):
                                                        $precio = isset($precios[$plazo['nombre']]) ? $precios[$plazo['nombre']] : '';
                                                        if (empty($precio)) continue;
                                                        $isActive = ($plazo['nombre'] === $plazoSeleccionado);
                                                ?>
                                                    <div class="<?php echo $isActive ? 'btn btn-primary' : 'btn btn-secondary'; ?>" 
                                                         style="margin-bottom:0; border-radius:0; display:block; text-align:center; font-size: 16px; font-weight: 500; padding: 12px 15px; width: 100%;"
                                                         data-plazo="<?php echo htmlspecialchars($plazo['nombre']); ?>"
                                                         data-precio="<?php echo $precio; ?>"
                                                         data-opcion-id="<?php echo $opcion['id']; ?>"
                                                         onclick="selectPlazo(this)">
                                                        <?php echo htmlspecialchars($plazo['nombre']); ?>: $<?php echo number_format($precio, 2, ',', '.'); ?>
                                                    </div>
                                                <?php 
                                                    endforeach;
                                                endif; 
                                                ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php 
                                        endwhile;
                                    else: 
                                    ?>
                                        <div style="width: 100%; padding: 20px; text-align: center; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; margin-top: 15px;">
                                            <p><strong>No hay opciones disponibles para esta categoría.</strong></p>
                                            <p>Por favor, contacte con el administrador para importar modelos desde la hoja de cálculo.</p>
                                            <p><a href="admin/debug_giracoches_import.php" style="color: #721c24; text-decoration: underline;">Ir a la página de importación</a></p>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php 
                                else:
                        ?>
                            <div class="accordion" id="accordion-<?php echo $categoria['id']; ?>">
                                <div class="accordion-header" onclick="toggleAccordion(this)">
                                    <div class="accordion-title"><?php echo htmlspecialchars($categoria['nombre']); ?></div>
                                    <div class="accordion-icon"><i class="fas fa-chevron-down"></i></div>
                                </div>
                                <div class="accordion-content">
                                    <?php if (!empty($categoria['descripcion'])): ?>
                                        <p><?php echo htmlspecialchars($categoria['descripcion']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Obtener opciones para esta categoría
                                    $query = "SELECT o.* FROM opciones o WHERE o.categoria_id = " . $categoria['id'] . " ORDER BY o.orden ASC";
                                    $opciones = $conn->query($query);
                                    
                                    if ($opciones && $opciones->num_rows > 0):
                                    ?>
                                        <div class="options-list">
                                            <?php while ($opcion = $opciones->fetch_assoc()):
                                                // Obtener precios para todos los plazos
                                                $preciosQuery = "SELECT plazo_entrega, precio FROM opcion_precios WHERE opcion_id = " . $opcion['id'];
                                                $preciosResult = $conn->query($preciosQuery);
                                                $precios = [];
                                                
                                                if ($preciosResult && $preciosResult->num_rows > 0) {
                                                    while ($precioRow = $preciosResult->fetch_assoc()) {
                                                        $precios[$precioRow['plazo_entrega']] = $precioRow['precio'];
                                                    }
                                                }
                                                
                                                // Si no hay precios, usar el precio base
                                                if (empty($precios)) {
                                                    $precios[$plazoSeleccionado] = $opcion['precio'];
                                                }
                                                
                                                // Precio inicial a mostrar (del plazo por defecto)
                                                $precioMostrar = isset($precios[$plazoSeleccionado]) ? $precios[$plazoSeleccionado] : $opcion['precio'];
                                                
                                                // Convertir a JSON para uso en JavaScript
                                                $preciosJson = json_encode($precios);
                                            ?>
                                                <div class="option-item" data-id="<?php echo $opcion['id']; ?>">
                                                    <div class="option-title">
                                                        <label>
                                                            <input type="radio" name="opcion_<?php echo $categoria['id']; ?>" 
                                                                   value="<?php echo $opcion['id']; ?>" 
                                                                   data-precio="<?php echo $precioMostrar; ?>"
                                                                   data-precios='<?php echo htmlspecialchars($preciosJson); ?>'
                                                                   data-plazo-seleccionado="<?php echo htmlspecialchars($plazoSeleccionado); ?>"
                                                                   data-categoria-id="<?php echo $categoria['id']; ?>"
                                                                   data-categoria-nombre="<?php echo htmlspecialchars($categoria['nombre']); ?>"
                                                                   data-opcion-nombre="<?php echo htmlspecialchars($opcion['nombre']); ?>"
                                                                   <?php echo $opcion['es_obligatorio'] ? 'required' : ''; ?>>
                                                            <?php echo htmlspecialchars($opcion['nombre']); ?>
                                                        </label>
                                                        <span class="option-price">$<?php echo number_format($precioMostrar, 2, ',', '.'); ?></span>
                                                    </div>
                                                    
                                                    <?php if (!empty($opcion['descripcion'])): ?>
                                                        <div class="option-description">
                                                            <?php echo htmlspecialchars($opcion['descripcion']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Plazos de entrega por opción -->
                                                    <?php if (!empty($precios)): ?>
                                                        <div style="margin-top: 15px;">
                                                            <?php foreach ($plazos as $index => $plazo): 
                                                                $precio = isset($precios[$plazo['nombre']]) ? $precios[$plazo['nombre']] : '';
                                                                if (empty($precio)) continue;
                                                                $isActive = ($plazo['nombre'] === $plazoSeleccionado);
                                                            ?>
                                                                <div class="<?php echo $isActive ? 'btn btn-primary' : 'btn btn-secondary'; ?>" 
                                                                     style="margin-bottom:10px; display:block; text-align:center; font-size: 16px; font-weight: 500; padding: 12px 15px; width: 100%; border-radius: 4px;"
                                                                     data-plazo="<?php echo htmlspecialchars($plazo['nombre']); ?>"
                                                                     data-precio="<?php echo $precio; ?>"
                                                                     data-opcion-id="<?php echo $opcion['id']; ?>"
                                                                     onclick="selectPlazo(this)">
                                                                    <?php echo htmlspecialchars($plazo['nombre']); ?>: $<?php echo number_format($precio, 2, ',', '.'); ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>No hay opciones disponibles para esta categoría.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                                endif;
                            endforeach; 
                        endif; 
                        ?>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-next" data-next="2" id="btn-to-step-2" disabled>Continuar</button>
                        </div>
                    </div>
                    
                    <!-- Paso 2: Opciones Adicionales -->
                    <div class="cotizador-step" id="step-2">
                        <h3>Opciones Adicionales</h3>
                        <p>Seleccione accesorios y opciones adicionales para su ascensor</p>
                        
                        <div class="selected-model-summary">
                            <h4>Modelo seleccionado:</h4>
                            <div id="selected-model-info" class="summary-item">
                                <!-- Aquí se mostrará el modelo seleccionado -->
                            </div>
                        </div>
                        
                        <?php
                        // Buscar la categoría GIRACOCHES para un tratamiento especial
                        $categorias->data_seek(0); // Resetear el puntero
                        $categoriaGiracoches = null;
                        while ($categoria = $categorias->fetch_assoc()) {
                            if ($categoria['nombre'] === 'GIRACOCHES') {
                                $categoriaGiracoches = $categoria;
                                break;
                            }
                        }
                        
                        // Si estamos mostrando GIRACOCHES, mostrar sus opciones con precios específicos
                        if ($categoriaGiracoches && isset($_GET['categoria']) && $_GET['categoria'] == 'GIRACOCHES'):
                            // Obtener opciones de GIRACOCHES
                            $query = "SELECT o.* FROM opciones o WHERE o.categoria_id = " . $categoriaGiracoches['id'] . " ORDER BY o.orden ASC";
                            $opcionesGiracoches = $conn->query($query);
                            
                            if ($opcionesGiracoches && $opcionesGiracoches->num_rows > 0):
                        ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Modelos GIRACOCHES Disponibles</h5>
                                </div>
                                <div class="card-body">
                                    <div class="options-list">
                                        <?php while ($opcion = $opcionesGiracoches->fetch_assoc()): 
                                            // Generar precios para todos los plazos basados en el precio base
                                            $precios = [];
                                            
                                            // Plazos predefinidos y sus multiplicadores
                                            $plazosMultiplicadores = [
                                                '30-60 días' => 1.15,     // 15% más caro
                                                '60-90 días' => 1.10,     // 10% más caro
                                                '90-120 días' => 1.05,    // 5% más caro
                                                '120-150 días' => 1.00,   // precio base
                                                '150-180 días' => 0.95,   // 5% más barato
                                                '180-210 días' => 0.90    // 10% más barato
                                            ];
                                            
                                            // Generar precios para cada plazo
                                            foreach ($plazosMultiplicadores as $plazo => $multiplicador) {
                                                $precios[$plazo] = $opcion['precio'] * $multiplicador;
                                            }
                                            
                                            // Si no hay plazos definidos, usar solo el precio base
                                            if (empty($precios)) {
                                                $precios[$plazoSeleccionado] = $opcion['precio'];
                                            }
                                            
                                            // Precio inicial a mostrar (del plazo por defecto)
                                            $precioMostrar = isset($precios[$plazoSeleccionado]) ? $precios[$plazoSeleccionado] : $opcion['precio'];
                                            
                                            // Convertir a JSON para uso en JavaScript
                                            $preciosJson = json_encode($precios);
                                        ?>
                                            <div class="option-item" data-id="<?php echo $opcion['id']; ?>">
                                                <div class="option-title">
                                                    <label>
                                                        <input type="radio" name="opcion_<?php echo $categoriaGiracoches['id']; ?>" 
                                                               value="<?php echo $opcion['id']; ?>" 
                                                               data-precio="<?php echo $precioMostrar; ?>"
                                                               data-precios='<?php echo htmlspecialchars($preciosJson); ?>'
                                                               data-plazo-seleccionado="<?php echo htmlspecialchars($plazoSeleccionado); ?>"
                                                               data-categoria-id="<?php echo $categoriaGiracoches['id']; ?>"
                                                               data-categoria-nombre="<?php echo htmlspecialchars($categoriaGiracoches['nombre']); ?>"
                                                               data-opcion-nombre="<?php echo htmlspecialchars($opcion['nombre']); ?>"
                                                               <?php echo $opcion['es_obligatorio'] ? 'required' : ''; ?>>
                                                        <?php echo htmlspecialchars($opcion['nombre']); ?>
                                                    </label>
                                                    <span class="option-price">$<?php echo number_format($precioMostrar, 2, ',', '.'); ?></span>
                                                </div>
                                                
                                                <?php if (!empty($opcion['descripcion'])): ?>
                                                    <div class="option-description">
                                                        <?php echo htmlspecialchars($opcion['descripcion']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Plazos de entrega por opción -->
                                                <?php if (!empty($precios)): ?>
                                                    <div style="margin-top: 15px;">
                                                        <?php foreach ($plazos as $index => $plazo): 
                                                            $precio = isset($precios[$plazo['nombre']]) ? $precios[$plazo['nombre']] : '';
                                                            if (empty($precio)) continue;
                                                            $isActive = ($plazo['nombre'] === $plazoSeleccionado);
                                                        ?>
                                                            <div class="<?php echo $isActive ? 'btn btn-primary' : 'btn btn-secondary'; ?>" 
                                                                 style="margin-bottom:10px; display:block; text-align:center; font-size: 16px; font-weight: 500; padding: 12px 15px; width: 100%; border-radius: 4px;"
                                                                 data-plazo="<?php echo htmlspecialchars($plazo['nombre']); ?>"
                                                                 data-precio="<?php echo $precio; ?>"
                                                                 data-opcion-id="<?php echo $opcion['id']; ?>"
                                                                 onclick="selectPlazo(this)">
                                                                <?php echo htmlspecialchars($plazo['nombre']); ?>: $<?php echo number_format($precio, 2, ',', '.'); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endif;
                        ?>
                        
                        <?php
                        // Buscar la categoría de adicionales
                        $categorias->data_seek(0); // Resetear el puntero
                        $categoriaAdicionales = null;
                        while ($categoria = $categorias->fetch_assoc()) {
                            if ($categoria['nombre'] === 'Opciones Adicionales') {
                                $categoriaAdicionales = $categoria;
                                break;
                            }
                        }
                        
                        if ($categoriaAdicionales):
                            // Obtener opciones adicionales
                            $query = "SELECT o.* FROM opciones o WHERE o.categoria_id = " . $categoriaAdicionales['id'] . " ORDER BY o.orden ASC";
                            $opcionesAdicionales = $conn->query($query);
                            
                            if ($opcionesAdicionales && $opcionesAdicionales->num_rows > 0):
                        ?>
                            <div class="options-list">
                                <?php while ($opcion = $opcionesAdicionales->fetch_assoc()): ?>
                                    <div class="option-item" data-id="<?php echo $opcion['id']; ?>">
                                        <div class="option-title">
                                            <label>
                                                <input type="checkbox" name="adicional_<?php echo $opcion['id']; ?>" 
                                                       value="1" 
                                                       data-precio="<?php echo $opcion['precio']; ?>"
                                                       data-id="<?php echo $opcion['id']; ?>"
                                                       data-nombre="<?php echo htmlspecialchars($opcion['nombre']); ?>">
                                                <?php echo htmlspecialchars($opcion['nombre']); ?>
                                            </label>
                                            <span class="option-price">$<?php echo number_format($opcion['precio'], 2, ',', '.'); ?></span>
                                        </div>
                                        
                                        <?php if (!empty($opcion['descripcion'])): ?>
                                            <div class="option-description">
                                                <?php echo htmlspecialchars($opcion['descripcion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p>No hay opciones adicionales disponibles.</p>
                        <?php 
                            endif;
                        endif;
                        ?>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-prev" data-prev="1">Volver</button>
                            <button type="button" class="btn btn-primary btn-next" data-next="3">Continuar</button>
                        </div>
                    </div>
                    
                    <!-- Paso 3: Forma de Pago -->
                    <div class="cotizador-step" id="step-3">
                        <h3>Forma de Pago</h3>
                        <p>Seleccione la forma de pago que mejor se adapte a sus necesidades</p>
                        
                        <?php
                        // Buscar la categoría de descuentos/formas de pago
                        $categorias->data_seek(0); // Resetear el puntero
                        $categoriaDescuentos = null;
                        while ($categoria = $categorias->fetch_assoc()) {
                            if ($categoria['nombre'] === 'Formas de Pago') {
                                $categoriaDescuentos = $categoria;
                                break;
                            }
                        }
                        
                        if ($categoriaDescuentos):
                            // Obtener opciones de pago
                            $query = "SELECT o.* FROM opciones o WHERE o.categoria_id = " . $categoriaDescuentos['id'] . " ORDER BY o.orden ASC";
                            $opcionesPago = $conn->query($query);
                            
                            if ($opcionesPago && $opcionesPago->num_rows > 0):
                        ?>
                            <div class="options-list">
                                <?php while ($opcion = $opcionesPago->fetch_assoc()): ?>
                                    <div class="option-item" data-id="<?php echo $opcion['id']; ?>">
                                        <div class="option-title">
                                            <label>
                                                <input type="radio" name="forma_pago" 
                                                       value="<?php echo $opcion['id']; ?>" 
                                                       data-precio="<?php echo $opcion['precio']; ?>"
                                                       data-nombre="<?php echo htmlspecialchars($opcion['nombre']); ?>">
                                                <?php echo htmlspecialchars($opcion['nombre']); ?>
                                            </label>
                                            <span class="option-price">
                                                <?php 
                                                    // Si el precio es negativo, es un descuento
                                                    if ($opcion['precio'] < 0) {
                                                        echo '-' . number_format(abs($opcion['precio']), 2, ',', '.') . '%';
                                                    } else {
                                                        echo '$' . number_format($opcion['precio'], 2, ',', '.');
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($opcion['descripcion'])): ?>
                                            <div class="option-description">
                                                <?php echo htmlspecialchars($opcion['descripcion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p>No hay formas de pago disponibles.</p>
                        <?php 
                            endif;
                        endif;
                        ?>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-prev" data-prev="2">Volver</button>
                            <button type="button" class="btn btn-primary btn-next" data-next="4">Continuar</button>
                        </div>
                    </div>
                    
                    <!-- Paso 4: Datos de Contacto y Resumen -->
                    <div class="cotizador-step" id="step-4">
                        <div class="summary-section">
                            <h3>Resumen del Presupuesto</h3>
                            <div id="resumenItems">
                                <!-- Aquí se llenará dinámicamente con JavaScript -->
                            </div>
                            <div class="summary-total">
                                Total: $<span id="totalPresupuesto">0,00</span>
                            </div>
                        </div>
                        
                        <div class="customer-info-section">
                            <h3>Datos de Contacto</h3>
                            <div class="form-group">
                                <label for="nombreCliente">Nombre completo</label>
                                <input type="text" id="nombreCliente" name="nombreCliente" required>
                            </div>
                            <div class="form-group">
                                <label for="emailCliente">Email</label>
                                <input type="email" id="emailCliente" name="emailCliente" required>
                            </div>
                            <div class="form-group">
                                <label for="telefonoCliente">Teléfono</label>
                                <input type="text" id="telefonoCliente" name="telefonoCliente">
                            </div>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-prev" data-prev="3">Volver</button>
                            <button type="submit" class="btn btn-primary">Generar Presupuesto</button>
                        </div>
                    </div>
                    
                    <?php if (!$categorias || $categorias->num_rows === 0): ?>
                        <p>No hay categorías disponibles. Por favor, contacte al administrador.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Presupuestos de Ascensores. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        const optionInputs = document.querySelectorAll('input[type="radio"]');
        const additionalInputs = document.querySelectorAll('input[type="checkbox"]');
        const resumenItems = document.getElementById('resumenItems');
        const totalPresupuesto = document.getElementById('totalPresupuesto');
        const plazoInput = document.getElementById('plazoEntrega');
        const stepButtons = document.querySelectorAll('.btn-next, .btn-prev');
        const steps = document.querySelectorAll('.step');
        const cotizadorSteps = document.querySelectorAll('.cotizador-step');
        const continueToStep2Button = document.getElementById('btn-to-step-2');
        const selectedModelInfo = document.getElementById('selected-model-info');
        
        // Variables para mantener el estado del presupuesto
        let selectedAscensor = null;
        let selectedAdicionales = [];
        let selectedFormaPago = null;
        let subtotalAscensor = 0;
        let subtotalAdicionales = 0;
        let descuentoFormaPago = 0;
        
        // Verificar si hay parámetros de categoría en la URL
        const urlParams = new URLSearchParams(window.location.search);
        const categoriaParam = urlParams.get('categoria');
        if (categoriaParam === 'GIRACOCHES') {
            // Si estamos en otro paso diferente al 2, forzar la carga de la sección de GIRACOCHES
            if (!document.getElementById('step-2').classList.contains('active')) {
                // Cargar opciones GIRACOCHES aunque estemos en otro paso
                setTimeout(() => {
                    // Este timeout asegura que los otros elementos ya están inicializados
                    const giracochesRadios = document.querySelectorAll('input[data-categoria-nombre="GIRACOCHES"]');
                    if (giracochesRadios.length > 0) {
                        // Seleccionar el primer modelo si no hay ninguno seleccionado
                        const isAnySelected = Array.from(giracochesRadios).some(radio => radio.checked);
                        if (!isAnySelected) {
                            giracochesRadios[0].checked = true;
                            giracochesRadios[0].dispatchEvent(new Event('change'));
                        }
                    }
                }, 500);
            }
        }
        
        // Acordeón
        const accordionHeaders = document.querySelectorAll('.accordion-header');
        
        // Inicializar acordeón
        accordionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                toggleAccordion(this);
            });
        });
        
        // Evento para manejar clics en los option-item
        document.querySelectorAll('.option-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Si el clic fue directamente en el input o la label, no hacer nada adicional
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL' || e.target.closest('label')) {
                    return;
                }
                
                // Encontrar el input dentro de este option-item
                const input = this.querySelector('input');
                if (input) {
                    if (input.type === 'checkbox') {
                        // Toggle el estado del checkbox
                        input.checked = !input.checked;
                    } else if (input.type === 'radio') {
                        // Seleccionar el radio
                        input.checked = true;
                    }
                    
                    // Disparar manualmente el evento change para que se ejecuten los handlers
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
        
        // Hacer clickable las filas de la tabla GIRACOCHES
        document.querySelectorAll('.table-striped tbody tr').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                // Si el clic fue directamente en el input o la label, no hacer nada adicional
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL' || e.target.closest('label')) {
                    return;
                }
                
                // Encontrar el radio dentro de esta fila
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    // Desmarcar todas las filas primero
                    this.closest('tbody').querySelectorAll('tr').forEach(tr => {
                        tr.classList.remove('selected');
                    });
                    
                    // Seleccionar el radio
                    radio.checked = true;
                    
                    // Marcar esta fila como seleccionada
                    this.classList.add('selected');
                    
                    // Disparar evento change manualmente
                    radio.dispatchEvent(new Event('change'));
                }
            });
        });
        
        // Añadir evento change a los radios para marcar la fila como seleccionada
        document.querySelectorAll('.table-striped tbody tr input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    // Desmarcar todas las filas primero
                    this.closest('tbody').querySelectorAll('tr').forEach(tr => {
                        tr.classList.remove('selected');
                    });
                    
                    // Marcar esta fila como seleccionada
                    this.closest('tr').classList.add('selected');
                }
            });
        });
        
        // Función para alternar acordeones
        function toggleAccordion(header) {
            // Toggle clase active en el header
            header.classList.toggle('active');
            
            // Toggle contenido
            const content = header.nextElementSibling;
            if (content.classList.contains('active')) {
                content.classList.remove('active');
            } else {
                // Opcional: cerrar otros acordeones abiertos
                document.querySelectorAll('.accordion-content.active').forEach(item => {
                    if (item !== content) {
                        item.classList.remove('active');
                        item.previousElementSibling.classList.remove('active');
                    }
                });
                content.classList.add('active');
            }
        }
        
        // Función para seleccionar plazo (nueva implementación para botones de plazo)
        window.selectPlazo = function(element) {
            const opcionId = element.dataset.opcionId;
            const selectedPlazo = element.dataset.plazo;
            const precio = element.dataset.precio;
            const optionItem = element.closest('.option-item');
            const radio = optionItem.querySelector('input[type="radio"]');
            
            // Actualizar estilos de botones
            const plazoBtns = optionItem.querySelectorAll('[data-plazo]');
            plazoBtns.forEach(btn => {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-secondary');
                btn.style.transform = 'scale(1)';
                btn.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            });
            
            // Aplicar estilos al botón seleccionado
            element.classList.remove('btn-secondary');
            element.classList.add('btn-primary');
            element.style.transform = 'scale(1.02)';
            element.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
            
            // Actualizar el precio mostrado con animación
            const priceElement = optionItem.querySelector('.option-price');
            priceElement.style.transition = 'all 0.3s';
            priceElement.style.transform = 'scale(1.1)';
            priceElement.textContent = '$' + formatNumber(precio);
            
            setTimeout(() => {
                priceElement.style.transform = 'scale(1)';
            }, 300);
            
            // Actualizar el input
            radio.dataset.precio = precio;
            radio.dataset.plazoSeleccionado = selectedPlazo;
            
            // Si esta opción está seleccionada, actualizar el resumen
            if (radio.checked) {
                updateSelectedModel();
                updateResumen();
                
                // Actualizar el plazo seleccionado global
                plazoInput.value = selectedPlazo;
            } else {
                // Marcar esta opción como seleccionada
                radio.checked = true;
                // Actualizar el modelo y resumen
                selectedAscensor = {
                    id: radio.value,
                    nombre: radio.dataset.opcionNombre,
                    categoria: radio.dataset.categoriaNombre,
                    plazo: selectedPlazo,
                    precio: parseFloat(precio)
                };
                updateSelectedModel();
                updateResumen();
                plazoInput.value = selectedPlazo;
            }
        };
        
        // Plazos de entrega por opción
        const optionPlazos = document.querySelectorAll('.option-plazo');
        
        optionPlazos.forEach(plazo => {
            plazo.addEventListener('click', function() {
                const opcionId = this.dataset.opcionId;
                const selectedPlazo = this.dataset.plazo;
                const precio = this.dataset.precio;
                const optionItem = this.closest('.option-item');
                const radio = optionItem.querySelector('input[type="radio"]');
                
                // Actualizar plazos activos solo para esta opción
                optionItem.querySelectorAll('.option-plazo').forEach(p => {
                    p.classList.remove('active');
                });
                this.classList.add('active');
                
                // Actualizar el precio mostrado
                optionItem.querySelector('.option-price').textContent = '$' + formatNumber(precio);
                
                // Actualizar el input
                radio.dataset.precio = precio;
                radio.dataset.plazoSeleccionado = selectedPlazo;
                
                // Si esta opción está seleccionada, actualizar el resumen
                if (radio.checked) {
                    updateSelectedModel();
                    updateResumen();
                    
                    // Actualizar el plazo seleccionado global
                    plazoInput.value = selectedPlazo;
                }
            });
        });
        
        // Agregar eventos a todas las opciones de ascensor
        optionInputs.forEach(input => {
            input.addEventListener('change', function() {
                // Abrir el acordeón cuando se selecciona una opción
                const accordionContent = this.closest('.accordion-content');
                if (accordionContent && !accordionContent.classList.contains('active')) {
                    accordionContent.classList.add('active');
                    accordionContent.previousElementSibling.classList.add('active');
                }
                
                // Actualizar el plazo seleccionado global con el de esta opción
                if (this.dataset.plazoSeleccionado) {
                    plazoInput.value = this.dataset.plazoSeleccionado;
                }
                
                // Si es una opción de ascensor (paso 1)
                if (this.name.startsWith('opcion_')) {
                    selectedAscensor = {
                        id: this.value,
                        nombre: this.dataset.opcionNombre,
                        categoria: this.dataset.categoriaNombre,
                        plazo: this.dataset.plazoSeleccionado,
                        precio: parseFloat(this.dataset.precio)
                    };
                    
                    // Habilitar botón para continuar al paso 2
                    continueToStep2Button.disabled = false;
                    updateSelectedModel();
                } else if (this.name === 'forma_pago') {
                    // Si es una forma de pago (paso 3)
                    const precio = parseFloat(this.dataset.precio);
                    selectedFormaPago = {
                        id: this.value,
                        nombre: this.dataset.nombre,
                        precio: precio
                    };
                    
                    // Si el precio es negativo, es un porcentaje de descuento
                    if (precio < 0) {
                        descuentoFormaPago = Math.abs(precio) / 100; // Convertir a porcentaje decimal
                    } else {
                        descuentoFormaPago = 0;
                    }
                }
                
                updateResumen();
            });
        });
        
        // Agregar eventos a las opciones adicionales
        additionalInputs.forEach(input => {
            input.addEventListener('change', function() {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre;
                const precio = parseFloat(this.dataset.precio);
                
                if (this.checked) {
                    // Agregar a la lista de adicionales seleccionados
                    selectedAdicionales.push({
                        id: id,
                        nombre: nombre,
                        precio: precio
                    });
                } else {
                    // Quitar de la lista de adicionales seleccionados
                    selectedAdicionales = selectedAdicionales.filter(adicional => adicional.id !== id);
                }
                
                updateResumen();
            });
        });
        
        // Actualizar el modelo seleccionado en el paso 2
        function updateSelectedModel() {
            if (selectedAscensor) {
                let nombreCategoria = selectedAscensor.categoria;
                
                // Limitar la longitud del nombre de la categoría si es muy largo
                if (nombreCategoria.length > 40) {
                    nombreCategoria = nombreCategoria.substring(0, 37) + '...';
                }
                
                selectedModelInfo.innerHTML = `
                    <div class="item-name">
                        <strong>${nombreCategoria}</strong><br>
                        ${selectedAscensor.nombre}
                        <span style="font-size: 12px; color: #666;">(${selectedAscensor.plazo})</span>
                    </div>
                    <div class="item-price">$${formatNumber(selectedAscensor.precio)}</div>
                `;
            }
        }
        
        // Actualizar el resumen del presupuesto
        function updateResumen() {
            resumenItems.innerHTML = '';
            let total = 0;
            subtotalAscensor = 0;
            subtotalAdicionales = 0;
            
            // Agregar ascensor seleccionado
            if (selectedAscensor) {
                // Limitar la longitud del nombre de la categoría si es muy largo
                let nombreCategoria = selectedAscensor.categoria;
                if (nombreCategoria.length > 40) {
                    nombreCategoria = nombreCategoria.substring(0, 37) + '...';
                }
                
                const item = document.createElement('div');
                item.className = 'summary-item';
                item.innerHTML = `
                    <div class="item-name">
                        <strong>${nombreCategoria}</strong><br>
                        ${selectedAscensor.nombre}
                        <span style="font-size: 12px; color: #666;">(${selectedAscensor.plazo})</span>
                    </div>
                    <div class="item-price">$${formatNumber(selectedAscensor.precio)}</div>
                `;
                resumenItems.appendChild(item);
                
                subtotalAscensor = selectedAscensor.precio;
                total += subtotalAscensor;
            }
            
            // Agregar divisor si hay ascensor y adicionales
            if (selectedAscensor && selectedAdicionales.length > 0) {
                const divisor = document.createElement('div');
                divisor.style.borderTop = '1px dashed #ccc';
                divisor.style.margin = '10px 0';
                divisor.style.paddingTop = '10px';
                divisor.innerHTML = '<div style="font-weight: 500; color: #666;">Adicionales:</div>';
                resumenItems.appendChild(divisor);
            }
            
            // Agregar adicionales seleccionados
            selectedAdicionales.forEach(adicional => {
                const item = document.createElement('div');
                item.className = 'summary-item';
                item.innerHTML = `
                    <div class="item-name">${adicional.nombre}</div>
                    <div class="item-price">$${formatNumber(adicional.precio)}</div>
                `;
                resumenItems.appendChild(item);
                
                subtotalAdicionales += adicional.precio;
                total += adicional.precio;
            });
            
            // Aplicar descuento si hay forma de pago seleccionada
            if (selectedFormaPago && descuentoFormaPago > 0) {
                const subtotal = subtotalAscensor + subtotalAdicionales;
                const descuentoValor = subtotal * descuentoFormaPago;
                
                const divisor = document.createElement('div');
                divisor.style.borderTop = '1px dashed #ccc';
                divisor.style.margin = '10px 0';
                divisor.style.paddingTop = '10px';
                divisor.innerHTML = '<div style="font-weight: 500; color: #666;">Forma de pago:</div>';
                resumenItems.appendChild(divisor);
                
                const item = document.createElement('div');
                item.className = 'summary-item';
                item.innerHTML = `
                    <div class="item-name">${selectedFormaPago.nombre} 
                        <span style="font-size: 12px; color: #666;">(${Math.abs(selectedFormaPago.precio)}% de descuento)</span>
                    </div>
                    <div class="item-price" style="color: #e74c3c;">-$${formatNumber(descuentoValor)}</div>
                `;
                resumenItems.appendChild(item);
                
                total -= descuentoValor;
            }
            
            // Actualizar el total
            totalPresupuesto.textContent = formatNumber(total);
        }
        
        // Navegación entre pasos
        stepButtons.forEach(button => {
            button.addEventListener('click', function() {
                const currentStep = this.closest('.cotizador-step');
                let targetStep;
                
                if (this.classList.contains('btn-next')) {
                    const nextStepNum = this.dataset.next;
                    targetStep = document.getElementById('step-' + nextStepNum);
                    
                    // Si vamos al paso 2 y tenemos seleccionado un modelo GIRACOCHES
                    if (nextStepNum === '2' && selectedAscensor && selectedAscensor.categoria === 'GIRACOCHES') {
                        // Añadir el parámetro a la URL sin recargar la página
                        const url = new URL(window.location.href);
                        url.searchParams.set('categoria', 'GIRACOCHES');
                        window.history.pushState({}, '', url);
                    }
                } else {
                    const prevStepNum = this.dataset.prev;
                    targetStep = document.getElementById('step-' + prevStepNum);
                }
                
                if (targetStep) {
                    // Ocultar paso actual
                    currentStep.classList.remove('active');
                    
                    // Mostrar paso objetivo
                    targetStep.classList.add('active');
                    
                    // Actualizar indicadores de pasos
                    updateStepsIndicator(parseInt(targetStep.id.split('-')[1]));
                }
            });
        });
        
        // Actualizar los indicadores de pasos
        function updateStepsIndicator(activeStepNum) {
            steps.forEach(step => {
                const stepNum = parseInt(step.dataset.step);
                step.classList.remove('active', 'completed');
                
                if (stepNum === activeStepNum) {
                    step.classList.add('active');
                } else if (stepNum < activeStepNum) {
                    step.classList.add('completed');
                }
            });
        }
        
        // Formato de números
        function formatNumber(number) {
            // Convertir el número a un formato con puntos como separadores de miles y coma para decimales
            return parseFloat(number).toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Abrir el primer acordeón por defecto
        if (accordionHeaders.length > 0) {
            accordionHeaders[0].click();
        }
        });
    </script>
</body>
</html> 
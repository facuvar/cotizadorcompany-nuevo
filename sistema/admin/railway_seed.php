<?php
// Verificar que estamos en Railway
if (!isset($_ENV['RAILWAY_ENVIRONMENT'])) {
    die("Este script solo puede ejecutarse en el entorno de Railway");
}

// Definir la ruta base del proyecto
define('BASE_PATH', '/app');

// Incluir archivos necesarios
require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/sistema/includes/db.php';
require_once BASE_PATH . '/sistema/includes/functions.php';

// Verificar la conexión a la base de datos
try {
    $conn = getDBConnection();
    echo "✅ Conexión a la base de datos establecida correctamente\n";
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Datos iniciales para plazos_entrega
    $plazos = [
        ['nombre' => 'Inmediato', 'dias' => 0, 'orden' => 1, 'activo' => 1],
        ['nombre' => '1-2 días', 'dias' => 2, 'orden' => 2, 'activo' => 1],
        ['nombre' => '3-5 días', 'dias' => 5, 'orden' => 3, 'activo' => 1],
        ['nombre' => '1 semana', 'dias' => 7, 'orden' => 4, 'activo' => 1],
        ['nombre' => '2 semanas', 'dias' => 14, 'orden' => 5, 'activo' => 1],
        ['nombre' => '1 mes', 'dias' => 30, 'orden' => 6, 'activo' => 1]
    ];
    
    // Insertar plazos de entrega
    $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, dias, orden, activo) VALUES (:nombre, :dias, :orden, :activo)");
    foreach ($plazos as $plazo) {
        $stmt->execute($plazo);
    }
    echo "✅ Plazos de entrega inicializados\n";
    
    // Datos iniciales para configuracion
    $configuraciones = [
        [
            'nombre' => 'empresa_nombre',
            'valor' => 'Company Ascensores',
            'descripcion' => 'Nombre de la empresa',
            'tipo' => 'text'
        ],
        [
            'nombre' => 'empresa_direccion',
            'valor' => 'Av. Rivadavia 1234',
            'descripcion' => 'Dirección de la empresa',
            'tipo' => 'text'
        ],
        [
            'nombre' => 'empresa_telefono',
            'valor' => '011-1234-5678',
            'descripcion' => 'Teléfono de contacto',
            'tipo' => 'text'
        ],
        [
            'nombre' => 'empresa_email',
            'valor' => 'info@company.com',
            'descripcion' => 'Email de contacto',
            'tipo' => 'text'
        ],
        [
            'nombre' => 'moneda_simbolo',
            'valor' => 'AR$',
            'descripcion' => 'Símbolo de la moneda',
            'tipo' => 'text'
        ],
        [
            'nombre' => 'iva_porcentaje',
            'valor' => '21',
            'descripcion' => 'Porcentaje de IVA',
            'tipo' => 'number'
        ]
    ];
    
    // Insertar configuraciones
    $stmt = $conn->prepare("INSERT INTO configuracion (nombre, valor, descripcion, tipo) VALUES (:nombre, :valor, :descripcion, :tipo)");
    foreach ($configuraciones as $config) {
        $stmt->execute($config);
    }
    echo "✅ Configuraciones inicializadas\n";
    
    // Confirmar transacción
    $conn->commit();
    echo "✅ Base de datos inicializada correctamente\n";
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
} 
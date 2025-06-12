<?php
/**
 * Script para agregar datos de ejemplo a la base de datos
 * Ejecutar una sola vez para tener datos de prueba
 */

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    die("Error: Archivo de configuración no encontrado");
}
require_once $configPath;

// Cargar DB
$dbPath = __DIR__ . '/../includes/db.php';
if (!file_exists($dbPath)) {
    die("Error: Archivo de base de datos no encontrado");
}
require_once $dbPath;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    echo "Agregando datos de ejemplo...\n";
    
    // Categorías de ejemplo
    $categorias = [
        ['id' => 1, 'nombre' => 'Características Básicas'],
        ['id' => 2, 'nombre' => 'Características Opcionales'],
        ['id' => 3, 'nombre' => 'Descuentos']
    ];
    
    // Insertar categorías
    foreach ($categorias as $categoria) {
        $check = $conn->prepare("SELECT id FROM categorias WHERE id = ?");
        $check->bind_param('i', $categoria['id']);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO categorias (id, nombre) VALUES (?, ?)");
            $stmt->bind_param('is', $categoria['id'], $categoria['nombre']);
            
            if ($stmt->execute()) {
                echo "✓ Categoría '{$categoria['nombre']}' agregada\n";
            } else {
                echo "✗ Error agregando categoría: " . $stmt->error . "\n";
            }
        } else {
            echo "• Categoría '{$categoria['nombre']}' ya existe\n";
        }
    }
    
    // Opciones de ejemplo
    $opciones = [
        // Características Básicas
        [
            'categoria_id' => 1,
            'nombre' => 'Ascensor estándar 6 personas',
            'precio_90_dias' => 15000,
            'precio_160_dias' => 14000,
            'precio_270_dias' => 13000
        ],
        [
            'categoria_id' => 1,
            'nombre' => 'Ascensor estándar 8 personas',
            'precio_90_dias' => 18000,
            'precio_160_dias' => 17000,
            'precio_270_dias' => 16000
        ],
        [
            'categoria_id' => 1,
            'nombre' => 'Ascensor panorámico 6 personas',
            'precio_90_dias' => 22000,
            'precio_160_dias' => 21000,
            'precio_270_dias' => 20000
        ],
        
        // Características Opcionales
        [
            'categoria_id' => 2,
            'nombre' => 'Puertas automáticas de acero inoxidable',
            'precio_90_dias' => 2500,
            'precio_160_dias' => 2300,
            'precio_270_dias' => 2100
        ],
        [
            'categoria_id' => 2,
            'nombre' => 'Sistema de emergencia con alarma',
            'precio_90_dias' => 800,
            'precio_160_dias' => 750,
            'precio_270_dias' => 700
        ],
        [
            'categoria_id' => 2,
            'nombre' => 'Iluminación LED interior',
            'precio_90_dias' => 500,
            'precio_160_dias' => 450,
            'precio_270_dias' => 400
        ],
        [
            'categoria_id' => 2,
            'nombre' => 'Espejo en pared posterior',
            'precio_90_dias' => 300,
            'precio_160_dias' => 280,
            'precio_270_dias' => 250
        ],
        [
            'categoria_id' => 2,
            'nombre' => 'Sistema de música ambiental',
            'precio_90_dias' => 600,
            'precio_160_dias' => 550,
            'precio_270_dias' => 500
        ],
        
        // Descuentos
        [
            'categoria_id' => 3,
            'nombre' => 'Descuento por pronto pago',
            'descuento' => 5,
            'precio_90_dias' => 0,
            'precio_160_dias' => 0,
            'precio_270_dias' => 0
        ],
        [
            'categoria_id' => 3,
            'nombre' => 'Descuento cliente frecuente',
            'descuento' => 10,
            'precio_90_dias' => 0,
            'precio_160_dias' => 0,
            'precio_270_dias' => 0
        ]
    ];
    
    // Insertar opciones
    $opciones_agregadas = 0;
    foreach ($opciones as $opcion) {
        // Verificar si ya existe una opción similar
        $check = $conn->prepare("SELECT id FROM opciones WHERE categoria_id = ? AND nombre = ?");
        $check->bind_param('is', $opcion['categoria_id'], $opcion['nombre']);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $descuento = $opcion['descuento'] ?? 0;
            
            $stmt = $conn->prepare("INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isdddi', 
                $opcion['categoria_id'],
                $opcion['nombre'],
                $opcion['precio_90_dias'],
                $opcion['precio_160_dias'],
                $opcion['precio_270_dias'],
                $descuento
            );
            
            if ($stmt->execute()) {
                echo "✓ Opción '{$opcion['nombre']}' agregada\n";
                $opciones_agregadas++;
            } else {
                echo "✗ Error agregando opción: " . $stmt->error . "\n";
            }
        } else {
            echo "• Opción '{$opcion['nombre']}' ya existe\n";
        }
    }
    
    echo "\n✅ Datos de ejemplo agregados exitosamente!\n";
    echo "- Categorías: " . count($categorias) . "\n";
    echo "- Opciones agregadas: $opciones_agregadas\n";
    
    // Mostrar resumen
    echo "\n=== RESUMEN DE DATOS ===\n";
    
    foreach ($categorias as $categoria) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM opciones WHERE categoria_id = ?");
        $stmt->bind_param('i', $categoria['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        echo "• {$categoria['nombre']}: $count opciones\n";
    }
    
    echo "\n🎉 ¡Listo! Ahora puedes probar el cotizador.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 
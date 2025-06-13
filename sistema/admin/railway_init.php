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
    
    // Crear tabla plazos_entrega si no existe
    $sql = "CREATE TABLE IF NOT EXISTS plazos_entrega (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        dias INT NOT NULL,
        orden INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->exec($sql) !== false) {
        echo "✅ Tabla plazos_entrega creada o verificada correctamente\n";
    } else {
        echo "❌ Error al crear tabla plazos_entrega\n";
    }
    
    // Crear tabla configuracion si no existe
    $sql = "CREATE TABLE IF NOT EXISTS configuracion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        valor TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->exec($sql) !== false) {
        echo "✅ Tabla configuracion creada o verificada correctamente\n";
    } else {
        echo "❌ Error al crear tabla configuracion\n";
    }
    
    // Verificar si hay datos en plazos_entrega
    $result = $conn->query("SELECT COUNT(*) as count FROM plazos_entrega");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insertar plazos por defecto
        $plazos = [
            ['nombre' => '90 dias', 'dias' => 90, 'orden' => 1],
            ['nombre' => '160-180 dias', 'dias' => 170, 'orden' => 2],
            ['nombre' => '270 dias', 'dias' => 270, 'orden' => 3]
        ];
        
        foreach ($plazos as $plazo) {
            $stmt = $conn->prepare("INSERT INTO plazos_entrega (nombre, dias, orden) VALUES (?, ?, ?)");
            $stmt->bind_param('sii', $plazo['nombre'], $plazo['dias'], $plazo['orden']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Verificar si hay datos en configuracion
    $result = $conn->query("SELECT COUNT(*) as count FROM configuracion");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insertar configuración por defecto
        $configuraciones = [
            ['nombre' => 'nombre_sistema', 'valor' => 'Sistema de Presupuestos de Ascensores', 'descripcion' => 'Nombre del sistema', 'tipo' => 'text'],
            ['nombre' => 'nombre_empresa', 'valor' => 'Tu Empresa', 'descripcion' => 'Nombre de la empresa', 'tipo' => 'text'],
            ['nombre' => 'telefono', 'valor' => '+54 11 1234-5678', 'descripcion' => 'Teléfono de contacto', 'tipo' => 'text'],
            ['nombre' => 'email', 'valor' => 'info@tuempresa.com', 'descripcion' => 'Email de contacto', 'tipo' => 'text'],
            ['nombre' => 'direccion', 'valor' => 'Tu Dirección, Ciudad', 'descripcion' => 'Dirección de la empresa', 'tipo' => 'text'],
            ['nombre' => 'moneda', 'valor' => 'ARS', 'descripcion' => 'Moneda por defecto', 'tipo' => 'text'],
            ['nombre' => 'iva', 'valor' => '21', 'descripcion' => 'Porcentaje de IVA', 'tipo' => 'number'],
            ['nombre' => 'dias_entrega_default', 'valor' => '30', 'descripcion' => 'Días de entrega por defecto', 'tipo' => 'number']
        ];
        
        foreach ($configuraciones as $config) {
            $stmt = $conn->prepare("INSERT INTO configuracion (nombre, valor, descripcion, tipo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $config['nombre'], $config['valor'], $config['descripcion'], $config['tipo']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo "✅ Base de datos inicializada correctamente en Railway";
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Registrar error
    railway_log("Error inicializando base de datos en Railway: " . $e->getMessage());
    
    echo "❌ Error inicializando base de datos: " . $e->getMessage();
} 
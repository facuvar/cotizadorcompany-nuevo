<?php
// Archivo de diagnóstico para Railway - EN LA RAÍZ - SIN autenticación para debugging
// NOTA: Eliminar este archivo después de resolver el problema

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Diagnóstico Railway - Estructura de Archivos (RAÍZ)</h2>";

echo "<h3>📍 Información del Servidor:</h3>";
echo "<strong>Server Name:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'No definido') . "<br>";
echo "<strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No definido') . "<br>";
echo "<strong>Script Filename:</strong> " . ($_SERVER['SCRIPT_FILENAME'] ?? 'No definido') . "<br>";
echo "<strong>__DIR__:</strong> " . __DIR__ . "<br>";
echo "<strong>__FILE__:</strong> " . __FILE__ . "<br>";
echo "<strong>getcwd():</strong> " . getcwd() . "<br>";

echo "<h3>🌍 Variables de Entorno Railway:</h3>";
$railwayVars = [];
foreach ($_ENV as $key => $value) {
    if (strpos($key, 'MYSQL') !== false || strpos($key, 'RAILWAY') !== false) {
        $railwayVars[$key] = $value;
    }
}

if (empty($railwayVars)) {
    echo "❌ No se encontraron variables de entorno de Railway<br>";
    echo "<em>Esto podría indicar que estás en un entorno local</em><br>";
} else {
    foreach ($railwayVars as $key => $value) {
        echo "<strong>$key:</strong> " . htmlspecialchars($value) . "<br>";
    }
}

echo "<h3>📁 Contenido del Directorio Actual (RAÍZ):</h3>";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    if (is_dir(__DIR__ . '/' . $file)) {
        echo "📁 <strong>$file/</strong><br>";
    } else {
        $size = filesize(__DIR__ . '/' . $file);
        $sizeFormatted = $size ? "(" . round($size/1024, 1) . "KB)" : "";
        echo "📄 $file $sizeFormatted<br>";
    }
}

echo "<h3>📁 Contenido del Directorio Admin:</h3>";
$adminDir = __DIR__ . '/admin';
if (is_dir($adminDir)) {
    $adminFiles = scandir($adminDir);
    foreach ($adminFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        
        if (is_dir($adminDir . '/' . $file)) {
            echo "📁 admin/<strong>$file/</strong><br>";
        } else {
            $size = filesize($adminDir . '/' . $file);
            $sizeFormatted = $size ? "(" . round($size/1024, 1) . "KB)" : "";
            echo "📄 admin/$file $sizeFormatted<br>";
        }
    }
} else {
    echo "❌ Directorio admin no existe<br>";
}

echo "<h3>🔍 Verificación de Archivos Críticos:</h3>";

$archivosImportantes = [
    'config.php en raíz' => __DIR__ . '/config.php',
    'sistema/config.php' => __DIR__ . '/sistema/config.php',
    'includes/db.php' => __DIR__ . '/includes/db.php',
    'sistema/includes/db.php' => __DIR__ . '/sistema/includes/db.php',
    'admin/api_gestionar_datos.php' => __DIR__ . '/admin/api_gestionar_datos.php',
    'admin/railway_diagnostic.php' => __DIR__ . '/admin/railway_diagnostic.php',
    'index.php' => __DIR__ . '/index.php',
    'cotizador.php' => __DIR__ . '/cotizador.php',
];

foreach ($archivosImportantes as $descripcion => $ruta) {
    $existe = file_exists($ruta);
    $icono = $existe ? "✅" : "❌";
    echo "$icono <strong>$descripcion:</strong> $ruta<br>";
}

echo "<h3>🔧 Prueba de Configuración:</h3>";
$configPaths = [
    __DIR__ . '/config.php',
    __DIR__ . '/sistema/config.php',
];

$configCargado = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        echo "Intentando cargar: $configPath<br>";
        try {
            ob_start();
            require_once $configPath;
            ob_end_clean();
            echo "✅ Configuración cargada<br>";
            $configCargado = true;
            
            if (defined('DB_HOST')) {
                echo "<strong>DB_HOST:</strong> " . DB_HOST . "<br>";
            }
            if (defined('ENVIRONMENT')) {
                echo "<strong>ENVIRONMENT:</strong> " . ENVIRONMENT . "<br>";
            }
            break;
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "<br>";
        }
    }
}

if (!$configCargado) {
    echo "❌ No se pudo cargar configuración<br>";
}

echo "<h3>🌐 Información de REQUEST:</h3>";
echo "<strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "<br>";
echo "<strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "<br>";
echo "<strong>SERVER_SOFTWARE:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'No definido') . "<br>";

echo "<hr>";
echo "<p><em>🚨 IMPORTANTE: Eliminar este archivo después de resolver el problema</em></p>";
echo "<p>Accedido desde: " . $_SERVER['REQUEST_URI'] . " a las " . date('Y-m-d H:i:s') . "</p>";
?> 
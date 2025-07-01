<?php
// Archivo de diagnóstico para Railway - SIN autenticación para debugging
// NOTA: Eliminar este archivo después de resolver el problema

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Diagnóstico Railway - Estructura de Archivos</h2>";

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

echo "<h3>📁 Estructura de Directorios:</h3>";

// Función para explorar directorios
function explorarDirectorio($dir, $nivel = 0, $maxNivel = 2) {
    if ($nivel > $maxNivel) return;
    
    $indent = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $nivel);
    
    if (!is_dir($dir)) {
        echo $indent . "❌ $dir (no es un directorio)<br>";
        return;
    }
    
    $files = @scandir($dir);
    if ($files === false) {
        echo $indent . "❌ No se puede leer $dir<br>";
        return;
    }
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $dir . '/' . $file;
        
        if (is_dir($fullPath)) {
            echo $indent . "📁 <strong>$file/</strong><br>";
            if ($nivel < $maxNivel) {
                explorarDirectorio($fullPath, $nivel + 1, $maxNivel);
            }
        } else {
            $size = filesize($fullPath);
            $sizeFormatted = $size ? "(" . round($size/1024, 1) . "KB)" : "";
            echo $indent . "📄 $file $sizeFormatted<br>";
        }
    }
}

// Explorar desde el directorio actual hacia arriba
$currentDir = __DIR__;
echo "<h4>Desde directorio actual (admin): $currentDir</h4>";
explorarDirectorio($currentDir, 0, 1);

$parentDir = dirname($currentDir);
echo "<h4>Directorio padre: $parentDir</h4>";
explorarDirectorio($parentDir, 0, 1);

// Verificar rutas específicas que buscamos
echo "<h3>🔍 Verificación de Archivos Específicos:</h3>";

$archivosImportantes = [
    'config.php en raíz' => dirname(__DIR__) . '/config.php',
    'config.php en sistema' => dirname(__DIR__) . '/sistema/config.php',
    'db.php en includes' => dirname(__DIR__) . '/includes/db.php',
    'db.php en sistema/includes' => dirname(__DIR__) . '/sistema/includes/db.php',
    'index.php en raíz' => dirname(__DIR__) . '/index.php',
    'cotizador.php en raíz' => dirname(__DIR__) . '/cotizador.php',
];

foreach ($archivosImportantes as $descripcion => $ruta) {
    $existe = file_exists($ruta);
    $icono = $existe ? "✅" : "❌";
    $info = "";
    
    if ($existe) {
        $size = filesize($ruta);
        $info = " (" . round($size/1024, 1) . "KB)";
        
        // Para archivos de configuración, intentar cargar y verificar constantes
        if (strpos($ruta, 'config.php') !== false) {
            try {
                // Capturar output para evitar problemas
                ob_start();
                include_once $ruta;
                ob_end_clean();
                
                $info .= " - Cargado OK";
                if (defined('DB_HOST')) {
                    $info .= " - DB_HOST: " . DB_HOST;
                }
            } catch (Exception $e) {
                $info .= " - Error al cargar: " . $e->getMessage();
            }
        }
    }
    
    echo "$icono <strong>$descripcion:</strong> $ruta$info<br>";
}

echo "<h3>🔧 Prueba de Conexión (si hay config):</h3>";

// Intentar cargar configuración y probar conexión
$configCargado = false;
$configPaths = [
    dirname(__DIR__) . '/config.php',
    dirname(__DIR__) . '/sistema/config.php',
];

foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        echo "Intentando cargar: $configPath<br>";
        try {
            require_once $configPath;
            echo "✅ Configuración cargada exitosamente<br>";
            $configCargado = true;
            
            // Mostrar algunas constantes importantes
            $constantes = ['DB_HOST', 'DB_USER', 'DB_NAME', 'DB_PORT', 'ENVIRONMENT'];
            foreach ($constantes as $const) {
                if (defined($const)) {
                    echo "<strong>$const:</strong> " . constant($const) . "<br>";
                }
            }
            
            // Intentar conexión si existe la función
            if (function_exists('getDBConnection')) {
                try {
                    $pdo = getDBConnection();
                    echo "✅ Conexión PDO exitosa<br>";
                    
                    // Probar consulta simple
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM opciones");
                    $result = $stmt->fetch();
                    echo "✅ Opciones en BD: " . $result['count'] . "<br>";
                    
                } catch (Exception $e) {
                    echo "❌ Error de conexión PDO: " . $e->getMessage() . "<br>";
                }
            }
            
            break;
        } catch (Exception $e) {
            echo "❌ Error cargando configuración: " . $e->getMessage() . "<br>";
        }
    }
}

if (!$configCargado) {
    echo "❌ No se pudo cargar ningún archivo de configuración<br>";
}

echo "<hr>";
echo "<p><em>🚨 IMPORTANTE: Eliminar este archivo después de resolver el problema por seguridad</em></p>";
?> 
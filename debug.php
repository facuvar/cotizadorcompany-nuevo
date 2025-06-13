<?php
// Archivo de diagnóstico para Railway
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Diagnóstico de Railway</h1>";

// 1. Verificar archivos
echo "<h2>📁 Archivos en el directorio:</h2>";
echo "<pre>";
print_r(scandir('.'));
echo "</pre>";

// 2. Verificar contenido de index.php
echo "<h2>📄 Contenido de index.php:</h2>";
echo "<pre>";
$index_content = file_get_contents('index.php');
echo htmlspecialchars($index_content);
echo "</pre>";

// 3. Verificar include_path
echo "<h2>🔧 PHP include_path:</h2>";
echo "<pre>";
echo get_include_path();
echo "</pre>";

// 4. Verificar variables de entorno
echo "<h2>🌍 Variables de entorno:</h2>";
echo "<pre>";
print_r($_ENV);
echo "</pre>";

// 5. Verificar configuración PHP
echo "<h2>⚙️ Configuración PHP:</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
echo "include_path: " . get_include_path() . "\n";
echo "</pre>";

// 6. Intentar incluir index.html
echo "<h2>🔄 Intentando incluir index.html:</h2>";
echo "<pre>";
try {
    include 'index.html';
    echo "✅ Inclusión exitosa";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
echo "</pre>"; 
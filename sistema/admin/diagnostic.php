<?php
// Mostrar información del sistema
echo "<h2>Información del Sistema</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "</pre>";

// Mostrar estructura de directorios
echo "<h2>Estructura de Directorios</h2>";
echo "<pre>";
function listDir($dir, $indent = '') {
    if (is_dir($dir)) {
        echo $indent . "📁 " . basename($dir) . "/\n";
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    listDir($path, $indent . '  ');
                } else {
                    echo $indent . '  📄 ' . $file . "\n";
                }
            }
        }
    }
}

// Listar desde la raíz del proyecto
listDir('/app');
echo "</pre>";

// Mostrar variables de entorno
echo "<h2>Variables de Entorno</h2>";
echo "<pre>";
print_r($_ENV);
echo "</pre>"; 
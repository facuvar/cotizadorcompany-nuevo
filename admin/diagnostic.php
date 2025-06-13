<?php
// Mostrar información del sistema
echo "<h2>Información del Sistema</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "Current Directory: " . getcwd() . "<br>";

// Función para listar directorios recursivamente
function listDir($dir, $indent = '') {
    if (!is_dir($dir)) {
        echo "$indent [No es un directorio: $dir]<br>";
        return;
    }
    
    echo "$indent 📁 $dir<br>";
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            listDir($path, $indent . '&nbsp;&nbsp;&nbsp;&nbsp;');
        } else {
            echo "$indent&nbsp;&nbsp;&nbsp;&nbsp;📄 $file<br>";
        }
    }
}

// Listar directorios desde la raíz
echo "<h2>Estructura de Directorios</h2>";
echo "<pre>";
listDir('/app');
echo "</pre>";

// Mostrar variables de entorno
echo "<h2>Variables de Entorno</h2>";
echo "<pre>";
print_r($_ENV);
echo "</pre>"; 
<?php
// ULTRA SIMPLE DEBUG - Línea por línea
echo "1. PHP FUNCIONA<br>";

echo "2. Versión PHP: " . PHP_VERSION . "<br>";

echo "3. Servidor: " . ($_SERVER['HTTP_HOST'] ?? 'NO HOST') . "<br>";

echo "4. Railway check: " . (isset($_ENV['PORT']) ? 'SÍ' : 'NO') . "<br>";

echo "5. Intentando config...<br>";

try {
    require_once 'config.php';
    echo "6. Config OK<br>";
} catch (Exception $e) {
    echo "6. Config ERROR: " . $e->getMessage() . "<br>";
    exit;
}

echo "7. Entorno: " . ENVIRONMENT . "<br>";

echo "8. Intentando BD...<br>";

try {
    $pdo = getDBConnection();
    echo "9. BD OK<br>";
} catch (Exception $e) {
    echo "9. BD ERROR: " . $e->getMessage() . "<br>";
}

echo "10. FIN DEL TEST<br>";
?> 
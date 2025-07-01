<?php
echo "🚀 Railway Deploy Test - " . date('Y-m-d H:i:s');
echo "<br>";
echo "✅ PHP está funcionando";
echo "<br>";
echo "📂 Directorio actual: " . __DIR__;
echo "<br>";
echo "🌐 Server: " . ($_SERVER['HTTP_HOST'] ?? 'No definido');
echo "<br>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "✅ config.php encontrado";
} else {
    echo "❌ config.php NO encontrado";
}
echo "<br>";
if (is_dir(__DIR__ . '/admin')) {
    echo "✅ Directorio admin existe";
} else {
    echo "❌ Directorio admin NO existe";
}
?> 
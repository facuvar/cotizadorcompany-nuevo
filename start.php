<?php
/**
 * 🚂 SCRIPT DE INICIO PARA RAILWAY
 * Este archivo se asegura de que la aplicación inicie correctamente
 */

// Configurar el puerto
$port = $_ENV['PORT'] ?? 8080;

// Verificar que el puerto sea válido
if (!is_numeric($port) || $port < 1000 || $port > 65535) {
    $port = 8080;
}

echo "🚂 Iniciando Railway Server en puerto: $port\n";
echo "📍 Host: 0.0.0.0:$port\n";
echo "📁 Document Root: " . __DIR__ . "\n";
echo "🐘 PHP Version: " . PHP_VERSION . "\n";
echo "⏰ Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Crear el comando de inicio
$command = "php -S 0.0.0.0:$port -t " . __DIR__;

echo "🔧 Ejecutando: $command\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Ejecutar el servidor
passthru($command);
?> 
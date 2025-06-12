<?php
/**
 * 🔍 HEALTH CHECK SIMPLE PARA RAILWAY
 */

// Headers para JSON
header('Content-Type: application/json');

echo json_encode([
    'status' => 'OK',
    'timestamp' => date('c'),
    'php' => PHP_VERSION,
    'port' => $_ENV['PORT'] ?? 'not set'
]);
?> 
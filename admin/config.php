<?php
// Permitir acceso tanto en Railway como en localhost
if (!getenv('RAILWAY_ENVIRONMENT') && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
    die("Este script solo puede ejecutarse en el entorno de Railway o en localhost");
}

// Asegurarse de que no haya salida antes de los headers
ob_start();

// Configuración de sesión (debe ir antes de iniciar la sesión)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Definir la ruta base del proyecto
if (getenv('RAILWAY_ENVIRONMENT')) {
    define('ADMIN_BASE_PATH', '/app');
    define('INCLUDES_PATH', '/app/includes');
} else {
    define('ADMIN_BASE_PATH', dirname(dirname(__FILE__)));
    define('INCLUDES_PATH', dirname(dirname(__FILE__)) . '/includes');
}

// Incluir el archivo de configuración principal
require_once dirname(dirname(__FILE__)) . '/config.php';

// Incluir archivos necesarios (usando rutas relativas al archivo actual)
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/functions.php';

// Credenciales de administrador
if (!defined('ADMIN_USER')) define('ADMIN_USER', 'admin');
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', password_hash('admin123', PASSWORD_DEFAULT));

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de caracteres
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', ADMIN_BASE_PATH . '/logs/error.log');

// Log de diagnóstico
error_log("=== DIAGNÓSTICO DE RUTAS ADMIN ===");
error_log("ADMIN_BASE_PATH: " . ADMIN_BASE_PATH);
error_log("INCLUDES_PATH: " . INCLUDES_PATH);
error_log("__FILE__: " . __FILE__);
error_log("dirname(__FILE__): " . dirname(__FILE__));
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido'));
?> 
<?php
// Configuración de sesión (debe ir antes de iniciar la sesión)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Verificar que estamos en el entorno de Railway
if (!getenv('RAILWAY_ENVIRONMENT')) {
    die("Este script solo puede ejecutarse en el entorno de Railway");
}

// Definir la ruta base del proyecto
define('BASE_PATH', '/app');

// Incluir archivos necesarios
require_once BASE_PATH . '/config.php';  // El archivo config.php está en la raíz
require_once BASE_PATH . '/sistema/includes/db.php';
require_once BASE_PATH . '/sistema/includes/functions.php';

// Credenciales de administrador
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', password_hash('admin123', PASSWORD_DEFAULT));

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de caracteres
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/sistema/logs/error.log'); 
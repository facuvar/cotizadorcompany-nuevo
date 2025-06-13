<?php
/**
 * Configuración Universal - Railway y Local
 * (Forzando deploy para actualizar variables)
 * 
 * Este archivo maneja la configuración de la base de datos y otras
 * variables de entorno, adaptándose automáticamente si se ejecuta
 * en Railway o en un entorno local.
 */

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---

// Detectar Railway de manera más robusta
$isRailway = (getenv('RAILWAY_ENVIRONMENT') === 'production' || 
             getenv('RAILWAY_ENVIRONMENT') === 'true' || 
             isset($_ENV['RAILWAY_ENVIRONMENT']) ||
             strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false);

// Log detallado del entorno
error_log("=== Diagnóstico de Entorno ===");
error_log("RAILWAY_ENVIRONMENT: " . (getenv('RAILWAY_ENVIRONMENT') ?: 'No definido'));
error_log("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido'));
error_log("Detección final - isRailway: " . ($isRailway ? 'true' : 'false'));

if ($isRailway) {
    // Entorno de producción en Railway
    $db_host = getenv('MYSQLHOST');
    $db_user = getenv('MYSQLUSER');
    $db_pass = getenv('MYSQLPASSWORD');
    $db_name = getenv('MYSQLDATABASE');
    $db_port = getenv('MYSQLPORT');

    // Validar que todas las variables de entorno de la base de datos existan en Railway
    if (empty($db_host) || empty($db_user) || empty($db_pass) || empty($db_name) || empty($db_port)) {
        die('Error Crítico de Configuración: Faltan una o más variables de entorno de la base de datos en Railway (MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT). Por favor, ve a tu dashboard de Railway, selecciona el servicio de tu aplicación, y en la sección "Variables", asegúrate de que el servicio de la base de datos MySQL esté correctamente vinculado.');
    }

    define('DB_HOST', $db_host);
    define('DB_USER', $db_user);
    define('DB_PASS', $db_pass);
    define('DB_NAME', $db_name);
    define('DB_PORT', $db_port);
} else {
    // Entorno de desarrollo local (XAMPP, etc.)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'company_presupuestos');
    define('DB_PORT', 3306);
}

// --- 2. CONFIGURACIÓN DE LA APLICACIÓN ---

// URL base de la aplicación (útil para generar enlaces absolutos)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
define('BASE_URL', $protocol . $host);

// Ruta base en el sistema de archivos
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__FILE__));
}

// --- 3. OTRAS CONFIGURACIONES ---

// Habilitar o deshabilitar el modo de depuración
define('DEBUG_MODE', !getenv('RAILWAY_ENVIRONMENT'));

// Configuración de la zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de errores
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Evitar salida antes de las sesiones
ob_start();

// Iniciar sesión al principio del archivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Asegurar que las rutas base estén correctamente definidas
if ($isRailway) {
    // En Railway, usar rutas absolutas desde la raíz del proyecto
    define('PROJECT_ROOT', __DIR__);
} else {
    // En local, mantener la configuración actual
    define('PROJECT_ROOT', __DIR__);
}

// Log de rutas
error_log("=== Rutas del Sistema ===");
error_log("PROJECT_ROOT: " . PROJECT_ROOT);
error_log("__DIR__: " . __DIR__);

if ($isRailway) {
    // ========================================
    // CONFIGURACIÓN RAILWAY (PRODUCCIÓN)
    // ========================================
    // Configuración de entorno
    define('ENVIRONMENT', 'railway');
    
    // Log de configuración
    error_log("Configuración Railway:");
    error_log("DB_HOST: " . DB_HOST);
    error_log("DB_NAME: " . DB_NAME);
    error_log("DB_PORT: " . DB_PORT);
    error_log("DB_USER: " . DB_USER);
    
} else {
    // ========================================
    // CONFIGURACIÓN LOCAL (DESARROLLO)
    // ========================================
    // Configuración de entorno
    define('ENVIRONMENT', 'local');
    
    // Log de inicialización (solo en debug)
    if (DEBUG_MODE) {
        error_log("Config cargada - Entorno: " . ENVIRONMENT . " - Host: " . DB_HOST);
    }
}

// ========================================
// CONFIGURACIÓN COMÚN
// ========================================

// Configuración de charset
ini_set('default_charset', 'UTF-8');

// Definir constantes solo si no están definidas
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'AR$');
}

// ========================================
// FUNCIÓN DE CONEXIÓN PDO
// ========================================

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Log de conexión exitosa (solo en debug)
        if (DEBUG_MODE) {
            error_log("Conexión exitosa a " . ENVIRONMENT . " - Host: " . DB_HOST . " - DB: " . DB_NAME);
        }
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log del error
        error_log("Error de conexión DB (" . ENVIRONMENT . "): " . $e->getMessage());
        
        if (DEBUG_MODE) {
            die("Error de conexión: " . $e->getMessage());
        } else {
            die("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
        }
    }
}

// ========================================
// FUNCIÓN DE CONEXIÓN MYSQLI (ALTERNATIVA)
// ========================================

function getMySQLiConnection() {
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        $mysqli->set_charset("utf8mb4");
        
        return $mysqli;
        
    } catch (Exception $e) {
        error_log("Error de conexión MySQLi (" . ENVIRONMENT . "): " . $e->getMessage());
        
        if (DEBUG_MODE) {
            die("Error de conexión MySQLi: " . $e->getMessage());
        } else {
            die("Error de conexión a la base de datos.");
        }
    }
}

// ========================================
// FUNCIONES DE UTILIDAD
// ========================================

/**
 * Obtener información del entorno actual
 */
function getEnvironmentInfo() {
    return [
        'environment' => ENVIRONMENT,
        'host' => DB_HOST,
        'database' => DB_NAME,
        'port' => DB_PORT,
        'debug' => DEBUG_MODE,
        'base_url' => BASE_URL,
        'is_railway' => ENVIRONMENT === 'railway'
    ];
}

/**
 * Verificar si la conexión está funcionando
 */
function testConnection() {
    try {
        $pdo = getDBConnection();
        $result = $pdo->query("SELECT 1 as test")->fetch();
        return $result['test'] === 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener estadísticas de la base de datos
 */
function getDatabaseStats() {
    try {
        $pdo = getDBConnection();
        
        $stats = [];
        
        // Contar categorías
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM categorias");
        $stats['categorias'] = $stmt->fetch()['count'];
        
        // Contar opciones
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM opciones");
        $stats['opciones'] = $stmt->fetch()['count'];
        
        // Contar presupuestos
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM presupuestos");
        $stats['presupuestos'] = $stmt->fetch()['count'];
        
        return $stats;
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// ========================================
// CONFIGURACIÓN ESPECÍFICA POR ENTORNO
// ========================================

if (ENVIRONMENT === 'railway') {
    // Configuración específica de Railway
    define('UPLOAD_MAX_SIZE', '10M');
    define('SESSION_LIFETIME', 3600); // 1 hora
    
} else {
    // Configuración específica de Local
    define('UPLOAD_MAX_SIZE', '50M');
    define('SESSION_LIFETIME', 7200); // 2 horas
}

// ========================================
// CONSTANTES ADICIONALES
// ========================================

define('APP_NAME', 'Cotizador Company');
define('APP_VERSION', '2.0.0');
define('CURRENCY', 'ARS');

// Rutas de archivos
define('UPLOAD_DIR', PROJECT_ROOT . '/uploads/');
define('PDF_DIR', PROJECT_ROOT . '/presupuestos/');
define('LOG_DIR', PROJECT_ROOT . '/logs/');

// Log de rutas completas
error_log("=== Rutas de Directorios ===");
error_log("UPLOAD_DIR: " . UPLOAD_DIR);
error_log("PDF_DIR: " . PDF_DIR);
error_log("LOG_DIR: " . LOG_DIR);

// Crear directorios si no existen
$dirs = [UPLOAD_DIR, PDF_DIR, LOG_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

?> 
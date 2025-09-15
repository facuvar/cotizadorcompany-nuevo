<?php
/**
 * Configuración Universal - Railway y Local
 * (Forzando deploy para actualizar variables)
 * 
 * Este archivo maneja la configuración de la base de datos y otras
 * variables de entorno, adaptándose automáticamente si se ejecuta
 * en Railway o en un entorno local.
 */

// Cargar variables de entorno desde el archivo .env en el entorno local
if (file_exists(__DIR__ . '/.env')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Detectar si estamos en Railway
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']);
define('ENVIRONMENT', $isRailway ? 'railway' : 'local');

// Definir rutas base
define('PROJECT_ROOT', __DIR__);

// Configuración para Railway
if ($isRailway) {
    define('DB_HOST', $_ENV['MYSQLHOST']);
    define('DB_USER', $_ENV['MYSQLUSER']);
    define('DB_PASS', $_ENV['MYSQLPASSWORD']);
    define('DB_NAME', $_ENV['MYSQLDATABASE']);
    define('DB_PORT', $_ENV['MYSQLPORT']);
} 
// Configuración para el entorno local
else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'company_presupuestos');
    define('DB_PORT', 3306);
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

define('DEBUG_MODE', !$isRailway);

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

// --- 2. CONFIGURACIÓN DE LA APLICACIÓN ---

// URL base de la aplicación (útil para generar enlaces absolutos)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
define('BASE_URL', $isRailway ? 'https://' . ($_SERVER['HTTP_HOST'] ?? '') : 'http://localhost/company-presupuestos-online-2');
error_log("BASE_URL definida como: " . BASE_URL);

// Configuración de errores y depuración
if (DEBUG_MODE) {
    error_log("Modo DEBUG activado");
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_log("Modo DEBUG desactivado");
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Configuración de la zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de sesión (solo si no hay sesión activa)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if ($isRailway) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Configuración de directorios
$directories = [
    'uploads' => PROJECT_ROOT . '/uploads',
    'presupuestos' => PROJECT_ROOT . '/presupuestos',
    'logs' => PROJECT_ROOT . '/logs',
    'temp' => PROJECT_ROOT . '/temp',
    'includes' => PROJECT_ROOT . '/includes',
    'assets' => PROJECT_ROOT . '/assets'
];

// Crear y verificar directorios necesarios
foreach ($directories as $name => $path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            error_log("ERROR: No se pudo crear el directorio: " . $path);
        } else {
            error_log("Directorio creado: " . $path);
        }
    }
    $const_name = strtoupper($name) . '_DIR';
    if (!defined($const_name)) {
        define($const_name, $path);
    }
}

// Verificar permisos de directorios críticos
foreach ($directories as $name => $path) {
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        error_log("Permisos de $name: $perms");
        
        // Intentar establecer permisos si es necesario
        if (!is_writable($path)) {
            @chmod($path, 0755);
            error_log("Intentando establecer permisos 0755 en: " . $path);
        }
    }
}

// Buffer de salida
ob_start();

?> 
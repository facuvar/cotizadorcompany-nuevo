<?php
/**
 * Conexión a base de datos
 * Requiere que config.php esté cargado primero
 */

// Cargar configuración si no está cargada
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

try {
    // Crear conexión PDO
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
} catch (PDOException $e) {
    // En Railway, no mostrar detalles del error en producción
    if (defined('IS_RAILWAY') && IS_RAILWAY) {
        die("Error de conexión a la base de datos");
    } else {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}

/**
 * Clase Database para compatibilidad con código existente
 */
class Database {
    private $connection;
    private static $instance;

    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($this->connection->connect_error) {
            die("Error de conexión: " . $this->connection->connect_error);
        }
        
        $this->connection->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }

    public function getLastId() {
        return $this->connection->insert_id;
    }

    public function numRows($result) {
        return $result->num_rows;
    }

    public function fetchArray($result) {
        return $result->fetch_assoc();
    }
}

/**
 * Función helper para obtener conexión mysqli
 * @return mysqli
 */
function getMysqliConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
        
    } catch (Exception $e) {
        if (defined('IS_RAILWAY') && IS_RAILWAY) {
            die("Error de conexión a la base de datos");
        } else {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
}

/**
 * Función para ejecutar consultas preparadas de forma segura
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function executeQuery($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if (defined('IS_RAILWAY') && IS_RAILWAY) {
            error_log("Database error: " . $e->getMessage());
            throw new Exception("Error en la consulta a la base de datos");
        } else {
            throw new Exception("Error en la consulta: " . $e->getMessage());
        }
    }
}

/**
 * Función para obtener un solo registro
 * @param string $sql
 * @param array $params
 * @return array|false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Función para obtener múltiples registros
 * @param string $sql
 * @param array $params
 * @return array
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}
?> 
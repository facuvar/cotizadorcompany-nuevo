<?php
/**
 * 🔄 EXPORTAR DATOS DESDE RAILWAY
 * Script que se ejecuta EN Railway para generar SQL de la BD Railway y permitir importación desde local
 */

set_time_limit(300);
ini_set('memory_limit', '512M');

// Cargar configuración
require_once 'config.php';

$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['PORT']);
$action = $_GET['action'] ?? 'form';

if ($action === 'export_railway_sql') {
    if (!$isRailway) {
        die("Este script debe ejecutarse desde Railway");
    }
    
    try {
        $pdo = getDBConnection();
        
        // Configurar headers para descarga
        $filename = "railway_backup_" . date('Y-m-d_H-i-s') . ".sql";
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Generar SQL dump
        echo "-- ============================================\n";
        echo "-- BACKUP BASE DE DATOS RAILWAY\n";
        echo "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Para importar en local\n";
        echo "-- ============================================\n\n";
        
        echo "SET NAMES utf8mb4;\n";
        echo "SET time_zone = '+00:00';\n";
        echo "SET foreign_key_checks = 0;\n";
        echo "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n\n";
        
        // Obtener todas las tablas
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            echo "-- ============================================\n";
            echo "-- Tabla: $table\n";
            echo "-- ============================================\n\n";
            
            // Estructura de la tabla
            $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            echo "DROP TABLE IF EXISTS `$table`;\n";
            echo $create_table['Create Table'] . ";\n\n";
            
            // Datos de la tabla
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $column_list = '`' . implode('`, `', $columns) . '`';
                
                echo "-- Datos para la tabla `$table`\n";
                echo "INSERT INTO `$table` ($column_list) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escaped_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $escaped_values[] = 'NULL';
                        } else {
                            $escaped_values[] = $pdo->quote($value);
                        }
                    }
                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                }
                
                echo implode(",\n", $values) . ";\n\n";
            } else {
                echo "-- Sin datos en la tabla `$table`\n\n";
            }
        }
        
        echo "SET foreign_key_checks = 1;\n";
        echo "-- Fin del backup\n";
        
        exit;
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

if ($action === 'copy_to_local') {
    // Script para copiar datos de Railway a local usando credenciales públicas
    $public_host = $_GET['host'] ?? '';
    $public_user = $_GET['user'] ?? '';
    $public_pass = $_GET['pass'] ?? '';
    $public_db = $_GET['db'] ?? '';
    $public_port = $_GET['port'] ?? 3306;
    
    if (empty($public_host) || empty($public_user) || empty($public_pass) || empty($public_db)) {
        die("Faltan credenciales públicas");
    }
    
    try {
        // Conectar a Railway con credenciales públicas
        $railway_pdo = new PDO(
            "mysql:host=$public_host;port=$public_port;dbname=$public_db;charset=utf8mb4",
            $public_user,
            $public_pass
        );
        
        // Conectar a local
        $local_pdo = new PDO(
            "mysql:host=localhost;dbname=company_presupuestos;charset=utf8mb4",
            "root",
            ""
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Conexiones establecidas']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 Exportar desde Railway</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .btn { display: inline-block; padding: 12px 20px; margin: 10px 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .credential { background: #2c3e50; color: #ecf0f1; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Exportar Datos desde Railway</h1>
        
        <?php if (!$isRailway): ?>
            
            <div class="warning">
                <h3>⚠️ Ejecutar en Railway</h3>
                <p>Este script debe ejecutarse <strong>desde Railway</strong>.</p>
                <p>Accede a: <strong>https://cotizadorcompany-nuevo-production.up.railway.app/export_from_railway.php</strong></p>
            </div>
            
        <?php else: ?>
            
            <div class="success">
                <h3>✅ Ejecutándose en Railway</h3>
                <p>Listo para exportar datos de Railway</p>
            </div>
            
            <?php
            try {
                $pdo = getDBConnection();
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                echo '<div class="step">';
                echo '<h3>📊 Estado de la Base de Datos Railway</h3>';
                echo '<p><strong>Tablas encontradas:</strong> ' . count($tables) . '</p>';
                
                $total_records = 0;
                foreach ($tables as $table) {
                    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    $total_records += $count;
                    echo "<p>📋 <strong>$table:</strong> $count registros</p>";
                }
                
                echo "<p><strong>Total de registros:</strong> $total_records</p>";
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h3>❌ Error</h3>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
            
            <div class="step">
                <h3>📄 Opción 1: Generar SQL Dump</h3>
                <p>Genera un archivo SQL que puedes descargar e importar en tu XAMPP local:</p>
                <a href="?action=export_railway_sql" class="btn btn-success">📄 Descargar SQL de Railway</a>
            </div>
            
            <div class="step">
                <h3>🔍 Opción 2: Obtener Credenciales Públicas</h3>
                <p>Para poder conectarte desde local, necesitas las credenciales públicas:</p>
                <a href="railway_db_info.php" class="btn btn-warning">🔍 Ver Credenciales</a>
            </div>
            
        <?php endif; ?>
        
        <div class="info">
            <h3>📋 Proceso Recomendado</h3>
            <ol>
                <li><strong>Desde Railway:</strong> Descargar SQL dump</li>
                <li><strong>En Local:</strong> Importar el SQL en phpMyAdmin</li>
                <li><strong>Verificar:</strong> Que todos los datos estén correctos</li>
                <li><strong>Sincronizar:</strong> Subir datos de local a Railway usando el método inverso</li>
            </ol>
        </div>
        
        <div class="warning">
            <h3>⚠️ Alternativa: Método Manual</h3>
            <p>Si tienes acceso al dashboard de Railway:</p>
            <ol>
                <li>Ve a tu base de datos MySQL en Railway</li>
                <li>Busca las credenciales públicas</li>
                <li>Usa phpMyAdmin o MySQL Workbench para conectarte directamente</li>
                <li>Exporta/Importa usando estas herramientas</li>
            </ol>
        </div>
        
        <p>
            <a href="cotizador.php" class="btn">🎯 Cotizador</a>
            <a href="railway_db_info.php" class="btn">🔍 Info DB</a>
        </p>
    </div>
</body>
</html> 
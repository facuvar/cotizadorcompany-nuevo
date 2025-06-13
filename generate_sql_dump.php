<?php
/**
 * 📄 GENERAR DUMP SQL DE BASE DE DATOS LOCAL
 * Genera un archivo SQL para importar manualmente en Railway
 */

set_time_limit(300);
ini_set('memory_limit', '512M');

// Configuración de base de datos local
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'company_presupuestos';

$action = $_GET['action'] ?? 'form';

if ($action === 'generate') {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Configurar headers para descarga
        $filename = "company_presupuestos_backup_" . date('Y-m-d_H-i-s') . ".sql";
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Generar SQL dump
        echo "-- ============================================\n";
        echo "-- BACKUP BASE DE DATOS: $db_name\n";
        echo "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Generado para migración a Railway\n";
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📄 Generar SQL Dump</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .btn { display: inline-block; padding: 12px 20px; margin: 10px 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; text-decoration: none; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .code-block { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Generar SQL Dump para Railway</h1>
        
        <div class="step">
            <h2>📋 Información</h2>
            <p>Este script generará un archivo SQL con todos los datos de tu base de datos local para que puedas importarlo manualmente en Railway.</p>
            
            <ul>
                <li><strong>Base de datos:</strong> <?= htmlspecialchars($db_name) ?></li>
                <li><strong>Host:</strong> <?= htmlspecialchars($db_host) ?></li>
                <li><strong>Usuario:</strong> <?= htmlspecialchars($db_user) ?></li>
            </ul>
        </div>
        
        <?php
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<div class="success">';
            echo '<h3>✅ Conexión exitosa</h3>';
            echo '<p>Tablas encontradas: ' . count($tables) . '</p>';
            echo '<ul>';
            
            $total_records = 0;
            foreach ($tables as $table) {
                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $total_records += $count;
                echo "<li><strong>$table:</strong> $count registros</li>";
            }
            
            echo '</ul>';
            echo "<p><strong>Total de registros:</strong> $total_records</p>";
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Error de conexión</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>Verifica que XAMPP esté ejecutándose y que la base de datos exista.</p>';
            echo '</div>';
        }
        ?>
        
        <div class="step">
            <h3>🚀 Generar y Descargar SQL</h3>
            <p>Haz clic en el botón para generar y descargar el archivo SQL:</p>
            <a href="?action=generate" class="btn btn-success">📄 Generar SQL Dump</a>
        </div>
        
        <div class="warning">
            <h3>📋 Instrucciones para importar en Railway</h3>
            <ol>
                <li>Descarga el archivo SQL generado</li>
                <li>Ve a tu proyecto en Railway</li>
                <li>Abre la base de datos MySQL</li>
                <li>Usa una herramienta como phpMyAdmin o importa directamente</li>
                <li>Ejecuta el archivo SQL descargado</li>
            </ol>
        </div>
        
        <div class="step">
            <h3>🔧 Herramientas alternativas</h3>
            <p>También puedes usar estas herramientas:</p>
            <ul>
                <li><strong>phpMyAdmin:</strong> Importar → Seleccionar archivo → Ejecutar</li>
                <li><strong>MySQL Workbench:</strong> Data Import/Restore</li>
                <li><strong>Línea de comandos:</strong> <code>mysql -h host -u user -p database < archivo.sql</code></li>
            </ul>
        </div>
        
        <p><a href="export_database.php" class="btn">🔄 Migración Automática</a></p>
        <p><a href="cotizador.php" class="btn">🎯 Ir al Cotizador</a></p>
    </div>
</body>
</html> 
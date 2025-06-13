<?php
/**
 * 🗄️ EXPORTAR BASE DE DATOS LOCAL A RAILWAY
 * Script para migrar datos de desarrollo local a producción
 */

set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');

// Configuración de base de datos local (XAMPP)
$local_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'company_presupuestos',
    'port' => 3306
];

// Configuración de Railway
$railway_config = [
    'host' => 'mysql.railway.internal',
    'user' => 'root',
    'pass' => 'CdEEWsKUcSueZldgmiaypVCCdnKMjgcD',
    'name' => 'railway',
    'port' => 3306
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🗄️ Migración de Base de Datos</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .btn { display: inline-block; padding: 12px 20px; margin: 10px 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .code-block { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; overflow-x: auto; }
        .table-info { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
        .progress { background: #e9ecef; border-radius: 4px; margin: 10px 0; }
        .progress-bar { background: #007bff; color: white; text-align: center; line-height: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Migración de Base de Datos Local → Railway</h1>
        
        <?php
        $action = $_GET['action'] ?? 'info';
        
        if ($action === 'info'):
        ?>
        
        <div class="step">
            <h2>📋 Información de la Migración</h2>
            <p><strong>Origen:</strong> Base de datos local XAMPP</p>
            <p><strong>Destino:</strong> Base de datos Railway</p>
            <p><strong>Proceso:</strong> Se exportarán todas las tablas y datos</p>
        </div>

        <div class="warning">
            <h3>⚠️ Advertencia Importante</h3>
            <ul>
                <li>Este proceso <strong>SOBRESCRIBIRÁ</strong> todos los datos en Railway</li>
                <li>Se recomienda hacer backup antes de proceder</li>
                <li>La migración puede tardar varios minutos</li>
                <li>No cierres esta ventana durante el proceso</li>
            </ul>
        </div>

        <div class="step">
            <h3>🔍 Verificar Conexiones</h3>
            <p><a href="?action=test_connections" class="btn">🔍 Probar Conexiones</a></p>
        </div>

        <div class="step">
            <h3>📊 Analizar Datos Locales</h3>
            <p><a href="?action=analyze_local" class="btn">📊 Analizar Base Local</a></p>
        </div>

        <div class="step">
            <h3>🚀 Migrar Datos</h3>
            <p><a href="?action=migrate" class="btn btn-success" onclick="return confirm('¿Estás seguro? Esto sobrescribirá todos los datos en Railway.')">🚀 Iniciar Migración</a></p>
        </div>

        <?php elseif ($action === 'test_connections'): ?>
        
        <h2>🔍 Prueba de Conexiones</h2>
        
        <div class="step">
            <h3>📍 Conexión Local (XAMPP)</h3>
            <?php
            try {
                $local_pdo = new PDO(
                    "mysql:host={$local_config['host']};port={$local_config['port']};dbname={$local_config['name']};charset=utf8mb4",
                    $local_config['user'],
                    $local_config['pass']
                );
                echo '<div class="success">✅ Conexión local exitosa</div>';
                
                $tables = $local_pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo '<p><strong>Tablas encontradas:</strong> ' . count($tables) . '</p>';
                
                foreach ($tables as $table) {
                    $count = $local_pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    echo "<div class='table-info'>📋 <strong>$table:</strong> $count registros</div>";
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Error en conexión local: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <div class="step">
            <h3>🚂 Conexión Railway</h3>
            <?php
            try {
                $railway_pdo = new PDO(
                    "mysql:host={$railway_config['host']};port={$railway_config['port']};dbname={$railway_config['name']};charset=utf8mb4",
                    $railway_config['user'],
                    $railway_config['pass']
                );
                echo '<div class="success">✅ Conexión Railway exitosa</div>';
                
                $tables = $railway_pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo '<p><strong>Tablas encontradas:</strong> ' . count($tables) . '</p>';
                
                if (empty($tables)) {
                    echo '<div class="warning">⚠️ La base de datos Railway está vacía</div>';
                } else {
                    foreach ($tables as $table) {
                        $count = $railway_pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                        echo "<div class='table-info'>📋 <strong>$table:</strong> $count registros</div>";
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Error en conexión Railway: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <p><a href="?" class="btn">🔙 Volver</a></p>

        <?php elseif ($action === 'analyze_local'): ?>
        
        <h2>📊 Análisis de Base de Datos Local</h2>
        
        <?php
        try {
            $local_pdo = new PDO(
                "mysql:host={$local_config['host']};port={$local_config['port']};dbname={$local_config['name']};charset=utf8mb4",
                $local_config['user'],
                $local_config['pass']
            );
            
            echo '<div class="success">✅ Conectado a base de datos local</div>';
            
            $tables = $local_pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<h3>📋 Resumen de Datos a Migrar</h3>';
            
            $total_records = 0;
            foreach ($tables as $table) {
                $count = $local_pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $total_records += $count;
                
                echo "<div class='table-info'>";
                echo "<strong>📋 $table:</strong> $count registros";
                
                // Mostrar algunos datos de ejemplo
                if ($count > 0) {
                    $sample = $local_pdo->query("SELECT * FROM `$table` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    if ($sample) {
                        echo "<br><small>Columnas: " . implode(', ', array_keys($sample)) . "</small>";
                    }
                }
                echo "</div>";
            }
            
            echo "<div class='step'>";
            echo "<h3>📊 Resumen Total</h3>";
            echo "<p><strong>Tablas:</strong> " . count($tables) . "</p>";
            echo "<p><strong>Registros totales:</strong> $total_records</p>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>

        <p><a href="?" class="btn">🔙 Volver</a></p>

        <?php elseif ($action === 'migrate'): ?>
        
        <h2>🚀 Migración en Proceso</h2>
        
        <?php
        try {
            // Conectar a ambas bases de datos
            $local_pdo = new PDO(
                "mysql:host={$local_config['host']};port={$local_config['port']};dbname={$local_config['name']};charset=utf8mb4",
                $local_config['user'],
                $local_config['pass']
            );
            
            $railway_pdo = new PDO(
                "mysql:host={$railway_config['host']};port={$railway_config['port']};dbname={$railway_config['name']};charset=utf8mb4",
                $railway_config['user'],
                $railway_config['pass']
            );
            
            echo '<div class="success">✅ Conexiones establecidas</div>';
            
            // Obtener todas las tablas
            $tables = $local_pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<h3>📋 Migrando " . count($tables) . " tablas...</h3>";
            
            foreach ($tables as $i => $table) {
                echo "<div class='step'>";
                echo "<h4>📋 Procesando tabla: $table</h4>";
                
                // Crear la tabla en Railway si no existe
                $create_table_sql = $local_pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                $create_sql = $create_table_sql['Create Table'];
                
                echo "<p>🔧 Creando estructura...</p>";
                $railway_pdo->exec("DROP TABLE IF EXISTS `$table`");
                $railway_pdo->exec($create_sql);
                
                // Copiar datos
                $count = $local_pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                echo "<p>📊 Copiando $count registros...</p>";
                
                if ($count > 0) {
                    $stmt = $local_pdo->query("SELECT * FROM `$table`");
                    $columns = array_keys($stmt->fetch(PDO::FETCH_ASSOC));
                    $stmt->execute(); // Re-ejecutar
                    
                    $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                    $insert_sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
                    $insert_stmt = $railway_pdo->prepare($insert_sql);
                    
                    $copied = 0;
                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                        $insert_stmt->execute($row);
                        $copied++;
                        
                        if ($copied % 100 == 0) {
                            echo "<p>📈 Copiados $copied/$count registros...</p>";
                            flush();
                        }
                    }
                    
                    echo "<div class='success'>✅ Tabla $table migrada: $copied registros</div>";
                } else {
                    echo "<div class='warning'>⚠️ Tabla $table está vacía</div>";
                }
                
                echo "</div>";
                flush();
            }
            
            echo '<div class="success">';
            echo '<h3>🎉 ¡Migración Completada!</h3>';
            echo '<p>Todas las tablas han sido migradas exitosamente a Railway.</p>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Error durante la migración: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>

        <p><a href="?" class="btn">🔙 Volver al Inicio</a></p>
        <p><a href="cotizador.php" class="btn btn-success">🎯 Ir al Cotizador</a></p>

        <?php endif; ?>
    </div>
</body>
</html> 
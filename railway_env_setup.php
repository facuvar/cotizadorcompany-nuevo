<?php
/**
 * 🚂 CONFIGURACIÓN DE VARIABLES DE ENTORNO - RAILWAY
 * Este archivo te ayuda a configurar correctamente las variables de entorno en Railway
 */

// Cargar configuración actual
require_once 'config.php';

// Detectar Railway
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || 
             isset($_SERVER['RAILWAY_ENVIRONMENT']) ||
             strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false ||
             strpos($_SERVER['HTTP_HOST'] ?? '', 'up.railway.app') !== false;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚂 Variables de Entorno Railway</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .env-var { background: #2c3e50; color: white; padding: 10px; border-radius: 5px; font-family: monospace; margin: 5px 0; }
        .status-good { color: #27ae60; font-weight: bold; }
        .status-warning { color: #f39c12; font-weight: bold; }
        .info-box { background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚂 Railway - Variables de Entorno</h1>
        
        <div class="info-box">
            <h2>📊 Estado Actual</h2>
            <p><strong>Sistema:</strong> <span class="status-good">✅ Funcionando correctamente</span></p>
            <p><strong>Entorno:</strong> <?= ENVIRONMENT ?></p>
            <p><strong>Base de datos:</strong> <?= DB_NAME ?> en <?= DB_HOST ?></p>
            
            <?php
            $envVars = ['MYSQLHOST', 'MYSQLUSER', 'MYSQLPASSWORD', 'MYSQLDATABASE', 'MYSQLPORT'];
            $configured = 0;
            foreach ($envVars as $var) {
                if (isset($_ENV[$var])) $configured++;
            }
            ?>
            <p><strong>Variables configuradas:</strong> <?= $configured ?>/5</p>
            
            <?php if ($configured === 5): ?>
                <p class="status-good">✅ Todas las variables están configuradas</p>
            <?php else: ?>
                <p class="status-warning">⚠️ Usando valores por defecto (el sistema funciona correctamente)</p>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <h2>🔧 Variables Recomendadas para Railway</h2>
            <p>Para una configuración más limpia, agrega estas variables en tu proyecto Railway:</p>
            
            <div class="env-var">MYSQLHOST=mysql.railway.internal</div>
            <div class="env-var">MYSQLUSER=root</div>
            <div class="env-var">MYSQLPASSWORD=CdEEWsKUcSueZldgmiaypVCCdnKMjgcD</div>
            <div class="env-var">MYSQLDATABASE=railway</div>
            <div class="env-var">MYSQLPORT=3306</div>
            
            <h3>📍 Cómo configurar:</h3>
            <ol>
                <li>Ve a tu proyecto en Railway</li>
                <li>Haz clic en la pestaña "Variables"</li>
                <li>Agrega cada variable con su valor</li>
                <li>Redespliega la aplicación</li>
            </ol>
        </div>

        <div class="info-box">
            <h3>🎯 Test de Conexión</h3>
            <?php
            try {
                $pdo = getDBConnection();
                echo '<p class="status-good">✅ Conexión exitosa a la base de datos</p>';
                
                $result = $pdo->query("SELECT COUNT(*) as tables FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch();
                echo '<p>📊 Tablas encontradas: ' . $result['tables'] . '</p>';
                
            } catch (Exception $e) {
                echo '<p style="color: #e74c3c;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <a href="cotizador.php" style="display: inline-block; padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 8px;">🎯 Ir al Cotizador</a>
        </p>
    </div>
</body>
</html> 
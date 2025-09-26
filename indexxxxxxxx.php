<?php
// Verificar si es una petición específica CANCELADO EL SISTEMA HASTA PAGO
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathInfo = parse_url($requestUri, PHP_URL_PATH);

// Si la petición es específica para archivos que existen, redirigir
if (strpos($pathInfo, '.php') !== false || strpos($pathInfo, 'admin') !== false || strpos($pathInfo, 'assets') !== false) {
    // No hacer nada, dejar que el servidor maneje la petición
    return;
}

// Si es la raíz, redirigir al cotizador
if ($pathInfo === '/' || $pathInfo === '' || $pathInfo === '/index.php') {
    header('Location: cotizador.php');
    exit;
}

// Si llegamos aquí, mostrar página de status para debug
header('Content-Type: text/html; charset=UTF-8');

// Verificar entorno
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['PORT']);
$port = $_ENV['PORT'] ?? '3000';

// Verificar conexión a la base de datos
$dbConnected = false;
$dbError = '';

if ($isRailway) {
    try {
        require_once 'config.php';
        $pdo = getDBConnection();
        $dbConnected = true;
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizador Company - Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f0f0f0; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .btn { display: inline-block; padding: 12px 20px; margin: 10px 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .status { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 Cotizador Company - Status</h1>
        
        <div class="status info">
            ℹ️ <strong>Página de Debug/Status</strong><br>
            Esta página se muestra solo para debugging. Normalmente se debería redirigir al cotizador.
        </div>
        
        <?php if ($isRailway): ?>
            <div class="status success">
                ✅ <strong>Railway Activo</strong><br>
                Puerto: <?= htmlspecialchars($port) ?><br>
                Host: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown') ?>
            </div>
            
            <?php if ($dbConnected): ?>
                <div class="status success">
                    ✅ <strong>Base de datos conectada</strong>
                </div>
            <?php else: ?>
                <div class="status error">
                    ❌ <strong>Error de base de datos:</strong><br>
                    <?= htmlspecialchars($dbError) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="status info">
                🔧 <strong>Desarrollo Local</strong>
            </div>
        <?php endif; ?>
        
        <p><strong>URI solicitada:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?></p>
        
        <div>
            <a href="cotizador.php" class="btn btn-success">📊 IR AL COTIZADOR</a>
            <a href="admin/" class="btn">👤 Admin</a>
            <a href="health.php" class="btn">🔍 Health Check</a>
            <a href="test_railway_config.php" class="btn">🚂 Test DB</a>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <h3>📊 Información del Sistema</h3>
        <ul>
            <li><strong>PHP:</strong> <?= PHP_VERSION ?></li>
            <li><strong>Servidor:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Railway' ?></li>
            <li><strong>Timestamp:</strong> <?= date('Y-m-d H:i:s') ?></li>
        </ul>
        
        <?php if ($isRailway): ?>
        <h3>🔧 Variables de Entorno</h3>
        <ul>
            <li><strong>PORT:</strong> <?= $_ENV['PORT'] ?? 'no definido' ?></li>
            <li><strong>RAILWAY_ENVIRONMENT:</strong> <?= $_ENV['RAILWAY_ENVIRONMENT'] ?? 'no definido' ?></li>
            <li><strong>MYSQLHOST:</strong> <?= isset($_ENV['MYSQLHOST']) ? 'configurado' : 'no configurado' ?></li>
            <li><strong>MYSQLUSER:</strong> <?= isset($_ENV['MYSQLUSER']) ? 'configurado' : 'no configurado' ?></li>
            <li><strong>MYSQLPASSWORD:</strong> <?= isset($_ENV['MYSQLPASSWORD']) ? 'configurado' : 'no configurado' ?></li>
            <li><strong>MYSQLDATABASE:</strong> <?= isset($_ENV['MYSQLDATABASE']) ? 'configurado' : 'no configurado' ?></li>
        </ul>
        <?php endif; ?>
    </div>
</body>
</html> 

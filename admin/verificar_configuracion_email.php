<?php
/**
 * Verificación de configuración de emails para Railway
 * Acceso: admin/verificar_configuracion_email.php
 */

// Verificar acceso (básico)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Acceso denegado. <a href='index.php'>Iniciar sesión</a>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Configuración Email - Admin</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #2d2d2d;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4CAF50;
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            background: #3a3a3a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #4CAF50;
        }
        .success { color: #4CAF50; }
        .warning { color: #FF9800; }
        .error { color: #f44336; }
        .info { color: #2196F3; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #555;
        }
        .email-list {
            background: #2a2a2a;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .nav-links {
            text-align: center;
            margin-top: 30px;
        }
        .nav-links a {
            color: #2196F3;
            text-decoration: none;
            margin: 0 15px;
            padding: 10px 20px;
            background: #3a3a3a;
            border-radius: 5px;
            display: inline-block;
        }
        .nav-links a:hover {
            background: #4a4a4a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Verificación Configuración Email - Railway</h1>
        
        <?php
        // Cargar configuración
        require_once '../config.php';
        
        $isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']);
        echo "<div class='section'>";
        echo "<h3>🌍 Información del Entorno</h3>";
        echo "<p><strong>Entorno:</strong> " . ($isRailway ? '<span class="success">RAILWAY ✅</span>' : '<span class="warning">LOCAL ⚠️</span>') . "</p>";
        echo "<p><strong>Base de datos:</strong> " . DB_NAME . " en " . DB_HOST . "</p>";
        echo "</div>";
        
        // Verificar variables de entorno
        echo "<div class='section'>";
        echo "<h3>📧 Variables de Entorno Railway</h3>";
        
        $env_vars = [
            'SENDGRID_API_KEY' => $_ENV['SENDGRID_API_KEY'] ?? null,
            'FROM_EMAIL' => $_ENV['FROM_EMAIL'] ?? null,
            'FROM_NAME' => $_ENV['FROM_NAME'] ?? null,
            'NOTIFICATION_EMAIL' => $_ENV['NOTIFICATION_EMAIL'] ?? null
        ];
        
        foreach ($env_vars as $var => $value) {
            echo "<p><strong>$var:</strong> ";
            if ($value === null) {
                echo "<span class='error'>❌ NO CONFIGURADA</span>";
            } else {
                if ($var === 'SENDGRID_API_KEY') {
                    $display_value = substr($value, 0, 10) . '...' . substr($value, -5);
                } else {
                    $display_value = $value;
                }
                echo "<span class='success'>✅ $display_value</span>";
            }
            echo "</p>";
        }
        echo "</div>";
        
        // Verificar configuración de archivos
        echo "<div class='section'>";
        echo "<h3>📋 Configuración email_config.php</h3>";
        
        $email_config = include '../includes/email_config.php';
        
        echo "<p><strong>SendGrid API Key:</strong> ";
        if (empty($email_config['sendgrid_api_key'])) {
            echo "<span class='error'>❌ VACÍA</span>";
        } else {
            echo "<span class='success'>✅ CONFIGURADA (" . substr($email_config['sendgrid_api_key'], 0, 10) . "...)</span>";
        }
        echo "</p>";
        
        echo "<p><strong>From Email:</strong> <span class='info'>" . $email_config['from_email'] . "</span></p>";
        echo "<p><strong>From Name:</strong> <span class='info'>" . $email_config['from_name'] . "</span></p>";
        echo "<p><strong>Notification Emails:</strong> <span class='info'>" . $email_config['notification_email'] . "</span></p>";
        echo "</div>";
        
        // Analizar emails
        echo "<div class='section'>";
        echo "<h3>📨 Análisis de Emails de Notificación</h3>";
        
        $notification_emails = $email_config['notification_email'];
        $emails = array_map('trim', explode(',', $notification_emails));
        
        echo "<p><strong>String original:</strong> <code>$notification_emails</code></p>";
        echo "<div class='email-list'>";
        echo "<strong>Emails separados:</strong><br>";
        foreach ($emails as $index => $email) {
            $valid = filter_var($email, FILTER_VALIDATE_EMAIL);
            echo "<p>" . ($index + 1) . ". <code>$email</code> - ";
            if ($valid) {
                echo "<span class='success'>✅ VÁLIDO</span>";
            } else {
                echo "<span class='error'>❌ INVÁLIDO</span>";
            }
            echo "</p>";
        }
        echo "</div>";
        echo "</div>";
        
        // Verificar EmailHandler
        echo "<div class='section'>";
        echo "<h3>🔧 Verificación EmailHandler</h3>";
        
        if (file_exists('../includes/email_handler.php')) {
            require_once '../includes/email_handler.php';
            if (class_exists('EmailHandler')) {
                echo "<p><span class='success'>✅ EmailHandler se puede cargar</span></p>";
                
                try {
                    $handler = new EmailHandler();
                    echo "<p><span class='success'>✅ EmailHandler se puede instanciar</span></p>";
                    
                    $valid_emails = array_filter($emails, function($email) { 
                        return filter_var($email, FILTER_VALIDATE_EMAIL); 
                    });
                    
                    echo "<p><strong>Emails que recibirían notificaciones:</strong></p>";
                    echo "<div class='email-list'>";
                    foreach ($valid_emails as $email) {
                        echo "<p><span class='success'>✅</span> $email</p>";
                    }
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<p><span class='error'>❌ Error instanciando EmailHandler: " . $e->getMessage() . "</span></p>";
                }
            } else {
                echo "<p><span class='error'>❌ EmailHandler class no encontrada</span></p>";
            }
        } else {
            echo "<p><span class='error'>❌ ../includes/email_handler.php no encontrado</span></p>";
        }
        echo "</div>";
        
        // Resumen
        echo "<div class='section'>";
        echo "<h3>🎯 Resumen y Conclusiones</h3>";
        
        $issues = [];
        if (empty($email_config['sendgrid_api_key'])) {
            $issues[] = "SendGrid API Key no configurada";
        }
        
        $valid_email_count = count(array_filter($emails, function($email) { 
            return filter_var($email, FILTER_VALIDATE_EMAIL); 
        }));
        
        if ($valid_email_count === 0) {
            $issues[] = "No hay emails válidos configurados";
        }
        
        if (empty($issues)) {
            echo "<p class='success'>✅ <strong>Configuración básica correcta</strong></p>";
            echo "<h4>Para debuggear problema con Victoria:</h4>";
            echo "<ol>";
            echo "<li>Generar presupuesto de prueba desde el cotizador</li>";
            echo "<li>Verificar carpeta de spam de victoria.tucci@ascensorescompany.com</li>";
            echo "<li>Revisar logs de Railway después del envío</li>";
            echo "<li>Confirmar límites de SendGrid</li>";
            echo "</ol>";
        } else {
            echo "<p class='error'>❌ <strong>Problemas encontrados:</strong></p>";
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li class='error'>$issue</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        ?>
        
        <div class="nav-links">
            <a href="index.php">← Volver al Dashboard</a>
            <a href="../cotizador.php" target="_blank">Ir al Cotizador</a>
        </div>
    </div>
</body>
</html>

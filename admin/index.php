<?php
// Incluir configuración antes de iniciar la sesión
require_once __DIR__ . '/config.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Manejar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Verificar login
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Si está logueado, redirigir al dashboard
if ($isLoggedIn) {
    header('Location: dashboard.php');
    exit;
}

// Procesar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (defined('ADMIN_USER') && defined('ADMIN_PASS')) {
        if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['login_time'] = time();
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } else {
        $error = 'Configuración de administrador no encontrada';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: var(--bg-primary);
        }

        .login-container {
            background: var(--bg-secondary);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .form-label {
            color: var(--text-secondary);
            font-size: 0.9em;
        }

        .form-input {
            padding: var(--spacing-sm);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        .form-button {
            background: var(--accent-primary);
            color: white;
            padding: var(--spacing-sm);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .form-button:hover {
            background: var(--accent-primary-dark);
        }

        .error-message {
            color: var(--accent-danger);
            text-align: center;
            margin-bottom: var(--spacing-md);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Panel de Administración</h1>
            <p>Ingrese sus credenciales para continuar</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST">
            <div class="form-group">
                <label class="form-label" for="username">Usuario</label>
                <input class="form-input" type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-input" type="password" id="password" name="password" required>
            </div>

            <button class="form-button" type="submit">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
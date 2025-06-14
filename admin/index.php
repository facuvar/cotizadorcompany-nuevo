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

// Cargar DB
$dbPath = __DIR__ . '/../sistema/includes/db.php';
$dbConnected = false;

if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
        $dbConnected = true;
    } catch (Exception $e) {
        error_log("Error loading db.php: " . $e->getMessage());
    }
}

// Verificar login
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Procesar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLoggedIn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (defined('ADMIN_USER') && defined('ADMIN_PASS')) {
        if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['login_time'] = time();
            $isLoggedIn = true;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}

// Si está logueado, mostrar dashboard
if ($isLoggedIn) {
    // Obtener estadísticas
    $stats = [
        'presupuestos' => ['total' => 0, 'mes' => 0, 'cambio' => 0],
        'clientes' => ['total' => 0, 'nuevos' => 0, 'cambio' => 0],
        'ingresos' => ['total' => 0, 'mes' => 0, 'cambio' => 0],
        'productos' => ['total' => 0, 'activos' => 0]
    ];
    
    $ultimosPresupuestos = [];
    $chartData = ['labels' => [], 'values' => []];
    
    if ($dbConnected) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Total presupuestos
            $result = $conn->query("SELECT COUNT(*) as total FROM presupuestos");
            if ($result) {
                $stats['presupuestos']['total'] = $result->fetch_assoc()['total'];
            }
            
            // Presupuestos del mes (usando ID como aproximación)
            $result = $conn->query("SELECT COUNT(*) as total FROM presupuestos WHERE id > 0");
            if ($result) {
                $stats['presupuestos']['mes'] = $result->fetch_assoc()['total'];
            }
            
            // Total ingresos
            $result = $conn->query("SELECT SUM(total) as total FROM presupuestos");
            if ($result) {
                $stats['ingresos']['total'] = $result->fetch_assoc()['total'] ?? 0;
            }
            
            // Ingresos del mes (usando todos los registros como aproximación)
            $result = $conn->query("SELECT SUM(total) as total FROM presupuestos WHERE id > 0");
            if ($result) {
                $stats['ingresos']['mes'] = $result->fetch_assoc()['total'] ?? 0;
            }
            
            // Total opciones
            $result = $conn->query("SELECT COUNT(*) as total FROM opciones");
            if ($result) {
                $stats['productos']['total'] = $result->fetch_assoc()['total'];
            }
            
            // Últimos presupuestos
            $result = $conn->query("SELECT * FROM presupuestos ORDER BY id DESC LIMIT 10");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $ultimosPresupuestos[] = $row;
                }
                // Debug: agregar al log para verificar
                error_log("Admin: Se encontraron " . count($ultimosPresupuestos) . " presupuestos");
            } else {
                error_log("Admin: Error en consulta presupuestos: " . $conn->error);
            }
            
            // Datos para gráfico (simulado por ahora - sin fecha_creacion)
            $chartData['labels'] = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
            $chartData['values'] = [25000, 35000, 18500, 30000, 45000, 28000, 40000];
            
        } catch (Exception $e) {
            error_log("Error getting stats: " . $e->getMessage());
        }
    }
    
    // Calcular cambios porcentuales (simulados por ahora)
    $stats['presupuestos']['cambio'] = 12.5;
    $stats['clientes']['cambio'] = -2.4;
    $stats['ingresos']['cambio'] = 18.2;
    
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        /* Layout principal */
        .dashboard-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Barra lateral */
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: var(--spacing-md);
        }

        .sidebar-header {
            padding: var(--spacing-lg) var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .sidebar-header h1 {
            font-size: 1.5em;
            color: var(--text-primary);
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .sidebar-item {
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
        }

        .sidebar-item:hover,
        .sidebar-item.active {
            background: var(--accent-primary);
            color: white;
        }
        
        .sidebar-footer {
            margin-top: auto;
        }

        /* Contenido principal */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header mejorado */
        .dashboard-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: var(--spacing-lg) var(--spacing-xl);
        }

        .header-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-grid h1 {
             color: var(--text-primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--accent-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Grid de contenido */
        .content-grid {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: var(--spacing-lg);
            align-content: start;
        }

        /* Cards de estadísticas */
        .stats-grid {
            grid-column: span 12;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .stat-title {
            color: var(--text-secondary);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 2em;
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.9em;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--error);
        }

        /* Gráfico */
        .chart-container {
            grid-column: span 8;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            position: relative;
            height: 400px; /* Alto fijo para el contenedor del gráfico */
        }

        /* Tabla de últimos presupuestos */
        .recent-presupuestos {
            grid-column: span 4;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
        }

        .presupuesto-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .presupuesto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            background: var(--bg-primary);
        }

        .presupuesto-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .presupuesto-title {
            font-weight: 500;
            color: var(--text-primary);
        }

        .presupuesto-date {
            font-size: 0.8em;
            color: var(--text-secondary);
        }

        .presupuesto-amount {
            font-weight: 600;
            color: var(--accent-primary);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .chart-container,
            .recent-presupuestos {
                grid-column: span 12;
            }
            .sidebar {
                display: none; /* Ocultar en pantallas pequeñas */
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Panel</h1>
            </div>
            <nav class="sidebar-menu">
                <a href="index.php" class="sidebar-item active">Dashboard</a>
                <a href="gestionar_datos.php" class="sidebar-item">Gestionar Datos</a>
                <a href="presupuestos.php" class="sidebar-item">Presupuestos</a>
                <a href="ajustar_precios.php" class="sidebar-item">Ajustar Precios</a>
            </nav>
            <div class="sidebar-footer">
                 <a href="../cotizador.php" class="sidebar-item" target="_blank">Ir al Cotizador</a>
            </div>
        </aside>
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-grid">
                    <h1>Dashboard</h1>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['admin_user'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_user']); ?></div>
                            <a href="?logout=1" class="logout-link">Cerrar sesión</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-grid">
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Presupuestos Totales</div>
                        <div class="stat-value"><?php echo number_format($stats['presupuestos']['total']); ?></div>
                        <div class="stat-change <?php echo $stats['presupuestos']['cambio'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['presupuestos']['cambio'] >= 0 ? '↑' : '↓'; ?>
                            <?php echo abs($stats['presupuestos']['cambio']); ?>%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Ingresos Totales</div>
                        <div class="stat-value">AR$ <?php echo number_format($stats['ingresos']['total'], 2); ?></div>
                        <div class="stat-change <?php echo $stats['ingresos']['cambio'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['ingresos']['cambio'] >= 0 ? '↑' : '↓'; ?>
                            <?php echo abs($stats['ingresos']['cambio']); ?>%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Productos Activos</div>
                        <div class="stat-value"><?php echo number_format($stats['productos']['total']); ?></div>
                    </div>
                </div>

                <!-- Gráfico -->
                <div class="chart-container">
                    <h2>Ingresos por Día</h2>
                    <canvas id="ingresosChart"></canvas>
                </div>

                <!-- Últimos Presupuestos -->
                <div class="recent-presupuestos">
                    <h2>Últimos Presupuestos</h2>
                    <div class="presupuesto-list">
                        <?php foreach ($ultimosPresupuestos as $presupuesto): ?>
                        <div class="presupuesto-item">
                            <div class="presupuesto-info">
                                <div class="presupuesto-title">Presupuesto #<?php echo $presupuesto['id']; ?></div>
                                <div class="presupuesto-date">
                                    <?php echo date('d/m/Y', strtotime($presupuesto['fecha_creacion'] ?? 'now')); ?>
                                </div>
                            </div>
                            <div class="presupuesto-amount">
                                AR$ <?php echo number_format($presupuesto['total'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Datos del gráfico
        const chartData = {
            labels: <?php echo json_encode($chartData['labels']); ?>,
            values: <?php echo json_encode($chartData['values']); ?>
        };

        // Crear gráfico
        const ctx = document.getElementById('ingresosChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Ingresos',
                    data: chartData.values,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Clave para que el gráfico se ajuste al contenedor
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'AR$ ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php
} else {
    // Mostrar formulario de login
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
            border-radius: var(--border-radius);
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
            border-radius: var(--border-radius-sm);
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
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .form-button:hover {
            background: var(--accent-primary-dark);
        }

        .error-message {
            color: var(--error);
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
<?php
}
?> 
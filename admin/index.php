<?php
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

// Verificar configuración
$configPath = __DIR__ . '/../sistema/config.php';
if (!file_exists($configPath)) {
    die("Error: Archivo de configuración no encontrado");
}

require_once $configPath;

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
            gap: var(--spacing-lg);
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent-primary);
        }

        .stat-card.success::before { background: var(--accent-success); }
        .stat-card.warning::before { background: var(--accent-warning); }
        .stat-card.info::before { background: var(--accent-info); }

        .stat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
        }

        .stat-icon.success { 
            background: rgba(16, 185, 129, 0.1); 
            color: var(--accent-success);
        }
        
        .stat-icon.warning { 
            background: rgba(245, 158, 11, 0.1); 
            color: var(--accent-warning);
        }
        
        .stat-icon.info { 
            background: rgba(6, 182, 212, 0.1); 
            color: var(--accent-info);
        }

        .stat-value {
            font-size: var(--text-3xl);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: var(--spacing-xs);
        }

        .stat-label {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-sm);
        }

        .stat-footer {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: var(--text-xs);
            color: var(--text-secondary);
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-weight: 600;
        }

        .stat-change.positive { color: var(--accent-success); }
        .stat-change.negative { color: var(--accent-danger); }

        /* Gráficos */
        .chart-section {
            grid-column: span 8;
        }

        .activity-section {
            grid-column: span 4;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: var(--spacing-lg);
        }

        /* Tabla de actividad */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-sm) 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            background: var(--bg-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-size: var(--text-sm);
            color: var(--text-primary);
            font-weight: 500;
        }

        .activity-time {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .activity-amount {
            font-weight: 600;
            color: var(--accent-success);
        }

        /* Tabla de presupuestos */
        .table-section {
            grid-column: span 12;
        }

        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .action-card {
            flex: 1;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-2px);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            background: var(--accent-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .chart-section { grid-column: span 12; }
            .activity-section { grid-column: span 12; }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                padding: var(--spacing-md);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 style="font-size: var(--text-xl); display: flex; align-items: center; gap: var(--spacing-sm);">
                    <span id="logo-icon"></span>
                    Panel Admin
                </h1>
            </div>
            
            <nav class="sidebar-menu">
                <a href="#" class="sidebar-item active">
                    <span id="nav-dashboard-icon"></span>
                    <span>Dashboard</span>
                </a>
                <a href="gestionar_datos.php" class="sidebar-item">
                    <span id="nav-data-icon"></span>
                    <span>Gestionar Datos</span>
                </a>
                <a href="presupuestos.php" class="sidebar-item">
                    <span id="nav-quotes-icon"></span>
                    <span>Presupuestos</span>
                </a>
                <a href="ajustar_precios.php" class="sidebar-item">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <div style="margin-top: auto; padding: var(--spacing-md);">
                    <a href="../cotizador.php" class="sidebar-item" target="_blank">
                        <span id="nav-calculator-icon"></span>
                        <span>Ir al Cotizador</span>
                    </a>
                    <a href="?logout=1" class="sidebar-item" style="color: var(--accent-danger);">
                        <span id="nav-logout-icon"></span>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-grid">
                    <div>
                        <h2 class="header-title">Dashboard</h2>
                        <p class="header-subtitle">Resumen general del sistema</p>
                    </div>
                    
                    <div class="user-info">
                        <div style="text-align: right;">
                            <div style="font-weight: 600; color: var(--text-primary);">
                                <?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'Admin'); ?>
                            </div>
                            <div style="font-size: var(--text-xs); color: var(--text-secondary);">
                                Administrador
                            </div>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['admin_user'] ?? 'A', 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Grid de Contenido -->
            <div class="content-grid">
                <!-- Quick Actions -->
                <div style="grid-column: span 12;">
                    <div class="quick-actions">
                        <div class="action-card" onclick="window.open('../cotizador.php', '_blank')">
                            <div class="action-icon">
                                <span id="action-new-icon"></span>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Nuevo Presupuesto</div>
                                <div style="font-size: var(--text-xs); color: var(--text-secondary);">Crear cotización</div>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="location.href='gestionar_datos.php'">
                            <div class="action-icon" style="background: var(--accent-success);">
                                <span id="action-data-icon"></span>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Gestionar Productos</div>
                                <div style="font-size: var(--text-xs); color: var(--text-secondary);">Editar catálogo</div>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="location.href='ajustar_precios.php'">
                            <div class="action-icon" style="background: var(--accent-info);">
                                <span id="action-prices-icon"></span>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Ajustar Precios</div>
                                <div style="font-size: var(--text-xs); color: var(--text-secondary);">Incrementar/Disminuir</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($stats['presupuestos']['total']); ?></div>
                                <div class="stat-label">Total Presupuestos</div>
                                <div class="stat-footer">
                                    <span class="stat-change positive">
                                        <span id="stat-up-icon"></span>
                                        <?php echo abs($stats['presupuestos']['cambio']); ?>%
                                    </span>
                                    <span>vs mes anterior</span>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <span id="stat-quotes-icon"></span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">$<?php echo number_format($stats['ingresos']['mes'], 0, ',', '.'); ?></div>
                                <div class="stat-label">Ingresos del Mes</div>
                                <div class="stat-footer">
                                    <span class="stat-change positive">
                                        <span id="stat-up2-icon"></span>
                                        <?php echo abs($stats['ingresos']['cambio']); ?>%
                                    </span>
                                    <span>vs mes anterior</span>
                                </div>
                            </div>
                            <div class="stat-icon success">
                                <span id="stat-money-icon"></span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo $stats['productos']['total']; ?></div>
                                <div class="stat-label">Productos Activos</div>
                                <div class="stat-footer">
                                    <span id="stat-info-icon"></span>
                                    <span>En el catálogo</span>
                                </div>
                            </div>
                            <div class="stat-icon warning">
                                <span id="stat-products-icon"></span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">$<?php echo round(($stats['ingresos']['total'] / max($stats['presupuestos']['total'], 1)), 0); ?></div>
                                <div class="stat-label">Ticket Medio</div>
                                <div class="stat-footer">
                                    <span class="stat-change negative">
                                        <span id="stat-down-icon"></span>
                                        2.4%
                                    </span>
                                    <span>vs mes anterior</span>
                                </div>
                            </div>
                            <div class="stat-icon info">
                                <span id="stat-avg-icon"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico -->
                <div class="chart-section">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Evolución de Ventas</h3>
                            <p class="card-subtitle">Últimos 7 días</p>
                        </div>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <div class="activity-section">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Actividad Reciente</h3>
                        </div>
                        <div class="activity-list">
                            <?php 
                            $iconos = ['cart', 'dollar', 'user', 'check'];
                            foreach (array_slice($ultimosPresupuestos, 0, 5) as $index => $presupuesto): 
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <span class="activity-icon-<?php echo $index; ?>"></span>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">
                                        Presupuesto #<?php echo $presupuesto['id']; ?>
                                    </div>
                                    <div class="activity-time">
                                        Cliente: <?php echo htmlspecialchars($presupuesto['cliente_nombre'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="activity-amount">
                                    $<?php echo number_format($presupuesto['total'], 0, ',', '.'); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Últimos Presupuestos -->
                <div class="table-section">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Últimos Presupuestos</h3>
                            <a href="presupuestos.php" class="btn btn-sm btn-primary">Ver todos</a>
                        </div>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Email</th>
                                        <th>Empresa</th>
                                        <th>Total</th>
                                        <th>Número</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimosPresupuestos)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
                                            No hay presupuestos para mostrar<br>
                                            <small>Debug: $dbConnected = <?php echo $dbConnected ? 'true' : 'false'; ?>, count = <?php echo count($ultimosPresupuestos); ?></small>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($ultimosPresupuestos as $presupuesto): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-primary">#<?php echo $presupuesto['id']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($presupuesto['cliente_nombre'] ?? 'Sin nombre'); ?></td>
                                        <td><?php echo htmlspecialchars($presupuesto['cliente_email'] ?? 'Sin email'); ?></td>
                                        <td>
                                            <?php 
                                            // Mostrar la empresa del cliente en lugar de items JSON
                                            echo htmlspecialchars($presupuesto['cliente_empresa'] ?? 'N/A');
                                            ?>
                                        </td>
                                        <td style="font-weight: 600; color: var(--accent-success);">
                                            $<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?>
                                        </td>
                                        <td><?php echo 'Presupuesto #' . $presupuesto['numero_presupuesto']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-secondary" onclick="verPresupuesto(<?php echo $presupuesto['id']; ?>)">
                                                <span id="btn-view-icon-<?php echo $presupuesto['id']; ?>"></span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Cargar iconos
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar icons
            document.getElementById('logo-icon').innerHTML = modernUI.getIcon('chart');
            document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
            document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
            document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');

            document.getElementById('nav-prices-icon').innerHTML = modernUI.getIcon('dollar');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
            
            // Action icons
            document.getElementById('action-new-icon').innerHTML = modernUI.getIcon('add', 'icon-lg');
            document.getElementById('action-data-icon').innerHTML = modernUI.getIcon('settings', 'icon-lg');

            document.getElementById('action-prices-icon').innerHTML = modernUI.getIcon('dollar', 'icon-lg');
            
            // Stat icons
            document.getElementById('stat-quotes-icon').innerHTML = modernUI.getIcon('document', 'icon-lg');
            document.getElementById('stat-money-icon').innerHTML = modernUI.getIcon('dollar', 'icon-lg');
            document.getElementById('stat-products-icon').innerHTML = modernUI.getIcon('package', 'icon-lg');
            document.getElementById('stat-avg-icon').innerHTML = modernUI.getIcon('chart', 'icon-lg');
            document.getElementById('stat-up-icon').innerHTML = modernUI.getIcon('arrowUp', 'icon-sm');
            document.getElementById('stat-up2-icon').innerHTML = modernUI.getIcon('arrowUp', 'icon-sm');
            document.getElementById('stat-down-icon').innerHTML = modernUI.getIcon('arrowDown', 'icon-sm');
            document.getElementById('stat-info-icon').innerHTML = modernUI.getIcon('info', 'icon-sm');
            
            // Activity icons
            <?php foreach (array_slice($ultimosPresupuestos, 0, 5) as $index => $presupuesto): ?>
            document.querySelector('.activity-icon-<?php echo $index; ?>').innerHTML = modernUI.getIcon('<?php echo $iconos[$index % 4]; ?>', 'icon-sm');
            <?php endforeach; ?>
            
            // Table icons
            <?php foreach ($ultimosPresupuestos as $presupuesto): ?>
            const viewIcon<?php echo $presupuesto['id']; ?> = document.getElementById('btn-view-icon-<?php echo $presupuesto['id']; ?>');
            if (viewIcon<?php echo $presupuesto['id']; ?>) {
                viewIcon<?php echo $presupuesto['id']; ?>.innerHTML = modernUI.getIcon('eye', 'icon-sm');
            }
            <?php endforeach; ?>
            
            // Crear gráfico
            const canvas = document.getElementById('salesChart');
            if (canvas) {
                const chartData = <?php echo json_encode($chartData['values']); ?>;
                modernUI.createSimpleChart(canvas, chartData.length > 0 ? chartData : [10, 25, 15, 30, 45, 35, 50], 'line');
            }
        });
        
        function verPresupuesto(id) {
            window.location.href = `ver_presupuesto.php?id=${id}`;
        }
    </script>
</body>
</html>
    <?php
    exit;
}

// Si no está logueado, mostrar formulario de login
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
            background: var(--bg-primary);
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: var(--spacing-lg);
        }
        
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: var(--accent-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-lg);
            color: white;
        }
        
        .login-title {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .login-subtitle {
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }
        
        .form-divider {
            border-top: 1px solid var(--border-color);
            margin: var(--spacing-lg) 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <span id="login-logo-icon"></span>
                </div>
                <h1 class="login-title">Panel de Control</h1>
                <p class="login-subtitle">Ingresa tus credenciales para continuar</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <span id="error-icon"></span>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="username">Usuario</label>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           required 
                           autofocus
                           placeholder="Ingresa tu usuario">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           required
                           placeholder="Ingresa tu contraseña">
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: var(--spacing-lg);">
                    <span id="login-btn-icon"></span>
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="form-divider"></div>
            
            <div style="text-align: center;">
                <a href="../cotizador.php" class="btn btn-secondary">
                    <span id="back-icon"></span>
                    Volver al Cotizador
                </a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/modern-icons.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('login-logo-icon').innerHTML = modernUI.getIcon('user', 'icon-lg');
            document.getElementById('login-btn-icon').innerHTML = modernUI.getIcon('arrowRight');
            document.getElementById('back-icon').innerHTML = modernUI.getIcon('arrowRight');
            
            const errorIcon = document.getElementById('error-icon');
            if (errorIcon) {
                errorIcon.innerHTML = modernUI.getIcon('error');
            }
        });
    </script>
</body>
</html> 
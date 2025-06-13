<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panel Admin</title>
    <link rel="stylesheet" href="<?php echo asset('css/modern-dark-theme.css'); ?>">
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
            height: 400px;
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
        }

        /* Mensaje de error */
        .error-message {
            background: var(--error-bg);
            color: var(--error);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            margin: var(--spacing-md);
        }
    </style>
</head>
<body>
    <?php if (isset($error) && $error): ?>
    <div class="error-message">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Panel Admin</h1>
            </div>
            <nav class="sidebar-menu">
                <a href="<?php echo ADMIN_URL; ?>" class="sidebar-item active">Dashboard</a>
                <a href="<?php echo ADMIN_URL; ?>/presupuestos.php" class="sidebar-item">Presupuestos</a>
                <a href="<?php echo ADMIN_URL; ?>/opciones.php" class="sidebar-item">Opciones</a>
                <a href="<?php echo ADMIN_URL; ?>/categorias.php" class="sidebar-item">Categorías</a>
                <a href="<?php echo ADMIN_URL; ?>/configuracion.php" class="sidebar-item">Configuración</a>
            </nav>
            <div class="sidebar-footer">
                <a href="<?php echo ADMIN_URL; ?>?logout=1" class="sidebar-item">Cerrar Sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-grid">
                    <h1>Dashboard</h1>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo substr($_SESSION['admin_user'] ?? 'A', 0, 1); ?>
                        </div>
                        <span><?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </header>

            <div class="content-grid">
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-title">Total Presupuestos</span>
                        <span class="stat-value"><?php echo number_format($stats['presupuestos']['total']); ?></span>
                        <div class="stat-change positive">
                            <span>+<?php echo number_format($stats['presupuestos']['mes']); ?></span>
                            <span>este mes</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <span class="stat-title">Clientes</span>
                        <span class="stat-value"><?php echo number_format($stats['clientes']['total']); ?></span>
                        <div class="stat-change positive">
                            <span>+<?php echo number_format($stats['clientes']['nuevos']); ?></span>
                            <span>nuevos</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <span class="stat-title">Ingresos</span>
                        <span class="stat-value">$<?php echo number_format($stats['ingresos']['total']); ?></span>
                        <div class="stat-change positive">
                            <span>+<?php echo number_format($stats['ingresos']['mes']); ?></span>
                            <span>este mes</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <span class="stat-title">Productos</span>
                        <span class="stat-value"><?php echo number_format($stats['productos']['total']); ?></span>
                        <div class="stat-change">
                            <span><?php echo number_format($stats['productos']['activos']); ?></span>
                            <span>activos</span>
                        </div>
                    </div>
                </div>

                <div class="chart-container">
                    <h2>Presupuestos por Mes</h2>
                    <!-- Aquí iría el gráfico -->
                </div>

                <div class="recent-presupuestos">
                    <h2>Últimos Presupuestos</h2>
                    <div class="presupuesto-list">
                        <?php foreach ($ultimosPresupuestos as $presupuesto): ?>
                        <div class="presupuesto-item">
                            <div class="presupuesto-info">
                                <span class="presupuesto-title">
                                    <?php echo htmlspecialchars($presupuesto['id']); ?>
                                </span>
                                <span class="presupuesto-date">
                                    <?php echo date('d/m/Y', strtotime($presupuesto['fecha_creacion'])); ?>
                                </span>
                            </div>
                            <span class="presupuesto-amount">
                                $<?php echo number_format($presupuesto['total'] ?? 0); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 
<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar si existe la fuente de datos
$hayDatos = false;
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $query = "SELECT * FROM fuente_datos ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $db->query($query);

    if ($result && $db->numRows($result) > 0) {
        $hayDatos = true;
    }
} catch (Exception $e) {
    // Probablemente la base de datos no existe
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuestos de Ascensores Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1>Presupuestos de Ascensores</h1>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if (!$hayDatos): ?>
                <div class="no-data-message">
                    <h2>No hay datos disponibles</h2>
                    <p>Antes de comenzar, el administrador debe cargar los datos iniciales.</p>
                    <p><a href="admin/index.php" class="btn btn-primary">Ir al panel de administración</a></p>
                </div>
            <?php else: ?>
                <div class="intro-section">
                    <h2>Crea tu presupuesto personalizado de ascensores</h2>
                    <p>Selecciona las opciones que necesitas para tu ascensor y obtén un presupuesto al instante.</p>
                    <a href="cotizador.php" class="btn btn-primary">Comenzar cotización</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Presupuestos de Ascensores. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html> 
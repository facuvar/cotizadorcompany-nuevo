<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar si se proporcionó un código de presupuesto
if (!isset($_GET['codigo'])) {
    redirect(SITE_URL);
}

$codigo = cleanString($_GET['codigo']);
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensaje Enviado - Presupuesto de Ascensor</title>
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
            <div class="cotizador-container">
                <div class="text-center" style="text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 64px; color: #28a745; margin-bottom: 20px;"></i>
                    <h2>¡Mensaje Enviado!</h2>
                    
                    <?php if ($flashMessage): ?>
                    <div class="flash-message flash-<?php echo $flashMessage['type']; ?>">
                        <?php echo $flashMessage['message']; ?>
                    </div>
                    <?php else: ?>
                    <p>Tu mensaje ha sido enviado correctamente. Nos pondremos en contacto contigo lo antes posible.</p>
                    <?php endif; ?>
                    
                    <p>Presupuesto de referencia: <strong>#<?php echo $codigo; ?></strong></p>
                    
                    <div class="actions-section" style="justify-content: center; margin-top: 30px;">
                        <a href="index.php" class="btn btn-primary">Volver al Inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Presupuestos de Ascensores. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html> 
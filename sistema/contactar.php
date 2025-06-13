<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar si se proporcionó un código de presupuesto
if (!isset($_GET['codigo'])) {
    redirect(SITE_URL);
}

$codigo = cleanString($_GET['codigo']);
$mensaje = '';
$presupuesto = null;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener el presupuesto
    $query = "SELECT * FROM presupuestos WHERE codigo = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Presupuesto no encontrado
        redirect(SITE_URL);
    }
    
    $presupuesto = $result->fetch_assoc();
    
    // Procesar el formulario si se ha enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = cleanString($_POST['nombre']);
        $email = cleanString($_POST['email']);
        $telefono = cleanString($_POST['telefono']);
        $mensaje = cleanString($_POST['mensaje']);
        
        // Aquí iría el código para enviar el email de contacto
        // Por ahora, simularemos que el email se envió correctamente
        
        setFlashMessage('Tu mensaje ha sido enviado correctamente. Nos pondremos en contacto contigo lo antes posible.', 'success');
        redirect(SITE_URL . '/contacto_enviado.php?codigo=' . $codigo);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $mensaje = "Ha ocurrido un error al procesar tu solicitud. Por favor, inténtalo nuevamente.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactar - Presupuesto de Ascensor</title>
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
                <h2>Contactar para Presupuesto #<?php echo $codigo; ?></h2>
                <p>Completa el siguiente formulario para ponerte en contacto con nosotros sobre este presupuesto.</p>
                
                <?php if (!empty($mensaje)): ?>
                <div class="flash-message flash-error">
                    <?php echo $mensaje; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="nombre">Nombre completo</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo $presupuesto['nombre_cliente']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $presupuesto['email_cliente']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" value="<?php echo $presupuesto['telefono_cliente']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="mensaje">Mensaje</label>
                        <textarea id="mensaje" name="mensaje" rows="5" required>Estoy interesado en el presupuesto #<?php echo $codigo; ?> por un total de <?php echo formatNumber($presupuesto['total']); ?> €. Me gustaría recibir más información.</textarea>
                    </div>
                    
                    <div class="actions-section">
                        <a href="generar_presupuesto.php?codigo=<?php echo $codigo; ?>" class="btn btn-secondary">Volver al Presupuesto</a>
                        <button type="submit" class="btn btn-primary">Enviar Mensaje</button>
                    </div>
                </form>
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
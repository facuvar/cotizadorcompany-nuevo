<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Verificar si se recibieron datos por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    header('Location: view_plazos_entrega.php');
    exit;
}

// Verificar que se recibieron todos los datos necesarios
if (!isset($_POST['nombre']) || !isset($_POST['dias'])) {
    setFlashMessage('error', 'Faltan parámetros requeridos');
    header('Location: view_plazos_entrega.php');
    exit;
}

$nombre = trim($_POST['nombre']);
$dias = intval($_POST['dias']);

// Validar datos
if (empty($nombre)) {
    setFlashMessage('error', 'El nombre del plazo no puede estar vacío');
    header('Location: view_plazos_entrega.php');
    exit;
}

if ($dias < 0) {
    setFlashMessage('error', 'El número de días debe ser un valor positivo');
    header('Location: view_plazos_entrega.php');
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar si ya existe un plazo con el mismo nombre
    $duplicateQuery = "SELECT id FROM plazos_entrega WHERE nombre = ?";
    $duplicateStmt = $conn->prepare($duplicateQuery);
    $duplicateStmt->bind_param('s', $nombre);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    
    if ($duplicateResult->num_rows > 0) {
        throw new Exception('Ya existe un plazo de entrega con ese nombre');
    }
    
    // Insertar el nuevo plazo
    $insertQuery = "INSERT INTO plazos_entrega (nombre, dias) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param('si', $nombre, $dias);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Error al agregar el plazo de entrega: ' . $conn->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    setFlashMessage('success', 'Plazo de entrega agregado correctamente');
} catch (Exception $e) {
    // Revertir cambios en caso de error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    setFlashMessage('error', $e->getMessage());
} finally {
    // Cerrar conexiones
    if (isset($duplicateStmt)) $duplicateStmt->close();
    if (isset($insertStmt)) $insertStmt->close();
}

// Redireccionar a la página de gestión
header('Location: view_plazos_entrega.php');
exit;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Plazo de Entrega - Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Añadir Plazo de Entrega</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Plazo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required>
                        <div class="form-text">Ejemplo: "Estándar", "Express", "Urgente"</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dias" class="form-label">Días de Entrega</label>
                        <input type="number" class="form-control" id="dias" name="dias" value="<?php echo htmlspecialchars($dias ?? ''); ?>" min="1" required>
                        <div class="form-text">Número de días necesarios para la entrega</div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view_plazos_entrega.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Plazo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Procesar solo solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    header('Location: view_plazos_entrega.php');
    exit;
}

// Validar datos de entrada
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$dias = isset($_POST['dias']) ? (int)$_POST['dias'] : 0;

if ($id <= 0) {
    setFlashMessage('error', 'ID de plazo de entrega no válido');
    header('Location: view_plazos_entrega.php');
    exit;
}

if (empty($nombre)) {
    setFlashMessage('error', 'El nombre del plazo de entrega es obligatorio');
    header('Location: view_plazos_entrega.php');
    exit;
}

if ($dias <= 0) {
    setFlashMessage('error', 'El número de días debe ser mayor que cero');
    header('Location: view_plazos_entrega.php');
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar si el plazo existe
    $stmt = $conn->prepare("SELECT id FROM plazos_entrega WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('El plazo de entrega no existe');
    }
    
    // Verificar si ya existe otro plazo con el mismo nombre
    $stmt = $conn->prepare("SELECT id FROM plazos_entrega WHERE nombre = ? AND id != ?");
    $stmt->bind_param('si', $nombre, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('Ya existe otro plazo de entrega con ese nombre');
    }
    
    // Actualizar el plazo
    $stmt = $conn->prepare("UPDATE plazos_entrega SET nombre = ?, dias = ? WHERE id = ?");
    $stmt->bind_param('sii', $nombre, $dias, $id);
    $stmt->execute();
    
    // Confirmar transacción
    $conn->commit();
    
    setFlashMessage('success', 'Plazo de entrega actualizado correctamente');
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    if (isset($conn) && $conn->connect_error === false) {
        $conn->rollback();
    }
    
    setFlashMessage('error', 'Error al actualizar el plazo de entrega: ' . $e->getMessage());
}

// Redireccionar a la página de gestión de plazos
header('Location: view_plazos_entrega.php');
exit;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Plazo de Entrega</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container mt-4">
        <h1>Editar Plazo de Entrega</h1>
        
        <?php
        // Mostrar mensajes flash
        displayFlashMessages();
        ?>
        
        <form action="edit_plazo.php" method="post">
            <div class="mb-3">
                <label for="id" class="form-label">ID</label>
                <input type="number" class="form-control" id="id" name="id" value="<?php echo $id; ?>" readonly>
            </div>
            
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="dias" class="form-label">Días</label>
                <input type="number" class="form-control" id="dias" name="dias" value="<?php echo $dias; ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="view_plazos_entrega.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Verificar si el usuario es administrador
requireAdmin();

// Obtener mensaje flash
$flashMessage = getFlashMessage();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar si la tabla plazos_entrega existe
    $result = $conn->query("SHOW TABLES LIKE 'plazos_entrega'");
    
    if ($result->num_rows === 0) {
        // La tabla no existe, crearla
        $conn->query("CREATE TABLE plazos_entrega (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL,
            dias INT NOT NULL DEFAULT 0,
            descripcion TEXT NULL,
            orden INT DEFAULT 0
        )");
    } else {
        // La tabla existe, verificar si tiene la columna dias
        $result = $conn->query("SHOW COLUMNS FROM plazos_entrega LIKE 'dias'");
        
        if ($result->num_rows === 0) {
            // La columna dias no existe, agregarla
            $conn->query("ALTER TABLE plazos_entrega ADD COLUMN dias INT NOT NULL DEFAULT 0 AFTER nombre");
            
            // Actualizar los días basados en los nombres de los plazos existentes
            $stmt = $conn->prepare("SELECT id, nombre FROM plazos_entrega");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $dias = 0;
                
                // Extraer los días del nombre (asumiendo formatos como "90 días", "160-180 días", etc.)
                if (preg_match('/(\d+)-?(\d+)?/', $row['nombre'], $matches)) {
                    // Si hay un rango (ej. 160-180), usar el valor más alto
                    $dias = isset($matches[2]) && !empty($matches[2]) ? intval($matches[2]) : intval($matches[1]);
                }
                
                if ($dias > 0) {
                    $updateStmt = $conn->prepare("UPDATE plazos_entrega SET dias = ? WHERE id = ?");
                    $updateStmt->bind_param('ii', $dias, $row['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
            
            $stmt->close();
        }
    }
    
    // Consultar los plazos de entrega
    $query = "SELECT id, nombre, dias FROM plazos_entrega ORDER BY dias ASC";
    $result = $conn->query($query);
    
    $plazos = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $plazos[] = $row;
        }
    }
} catch (Exception $e) {
    setFlashMessage('error', 'Error al obtener los plazos de entrega: ' . $e->getMessage());
}
?>

<div class="container">
    <h1>Gestión de Plazos de Entrega</h1>
    
    <?php if ($flashMessage): ?>
    <div class="alert alert-<?php echo $flashMessage['type']; ?>">
        <?php echo $flashMessage['message']; ?>
    </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Agregar Nuevo Plazo de Entrega</h5>
                </div>
                <div class="card-body">
                    <form action="add_plazo.php" method="post">
                        <div class="form-group">
                            <label for="nombre">Nombre del Plazo</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="dias">Días</label>
                            <input type="number" class="form-control" id="dias" name="dias" min="0" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Plazos de Entrega Existentes</h5>
        </div>
        <div class="card-body">
            <?php if (empty($plazos)): ?>
            <div class="alert alert-info">
                No hay plazos de entrega registrados.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Días</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plazos as $plazo): ?>
                        <tr>
                            <td><?php echo $plazo['id']; ?></td>
                            <td><?php echo htmlspecialchars($plazo['nombre']); ?></td>
                            <td><?php echo $plazo['dias']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-plazo" data-id="<?php echo $plazo['id']; ?>">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-sm btn-danger delete-plazo" data-id="<?php echo $plazo['id']; ?>" data-nombre="<?php echo htmlspecialchars($plazo['nombre']); ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para editar plazo -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Editar Plazo de Entrega</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editPlazoForm" action="update_plazo.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_nombre">Nombre del Plazo</label>
                        <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_dias">Días</label>
                        <input type="number" class="form-control" id="edit_dias" name="dias" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                ¿Está seguro de que desea eliminar el plazo de entrega "<span id="delete_plazo_nombre"></span>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <a href="#" id="confirm_delete" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar edición de plazos
        const editButtons = document.querySelectorAll('.edit-plazo');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                
                // Obtener datos del plazo mediante AJAX
                fetch('get_plazo.php?id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('edit_id').value = data.plazo.id;
                            document.getElementById('edit_nombre').value = data.plazo.nombre;
                            document.getElementById('edit_dias').value = data.plazo.dias;
                            
                            // Mostrar modal
                            $('#editModal').modal('show');
                        } else {
                            alert('Error: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al cargar los datos del plazo');
                    });
            });
        });
        
        // Manejar eliminación de plazos
        const deleteButtons = document.querySelectorAll('.delete-plazo');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre;
                
                document.getElementById('delete_plazo_nombre').textContent = nombre;
                document.getElementById('confirm_delete').href = 'delete_plazo.php?id=' + id;
                
                // Mostrar modal de confirmación
                $('#deleteModal').modal('show');
            });
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?> 
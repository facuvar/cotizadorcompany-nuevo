<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el usuario es administrador
requireAdmin();

// Función para formatear precios
function formatPrice($price) {
    return '$ ' . number_format($price, 2, ',', '.');
}

// Obtener lista de modelos y sus precios
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Buscar la categoría SALVAESCALERAS
    $query = "SELECT * FROM categorias WHERE nombre = 'SALVAESCALERAS'";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontró la categoría SALVAESCALERAS en la base de datos.");
    }
    
    $categoria = $result->fetch_assoc();
    $categoriaId = $categoria['id'];
    
    // Obtener todas las opciones (modelos) de la categoría
    $query = "SELECT * FROM opciones WHERE categoria_id = ? ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $categoriaId);
    $stmt->execute();
    $resultOpciones = $stmt->get_result();
    
    if ($resultOpciones->num_rows === 0) {
        throw new Exception("No se encontraron modelos para la categoría SALVAESCALERAS.");
    }
    
    // Obtener todos los plazos únicos
    $query = "SELECT DISTINCT plazo_entrega FROM opcion_precios 
              WHERE opcion_id IN (SELECT id FROM opciones WHERE categoria_id = ?)
              ORDER BY plazo_entrega";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $categoriaId);
    $stmt->execute();
    $resultPlazos = $stmt->get_result();
    
    $plazos = [];
    while ($plazo = $resultPlazos->fetch_assoc()) {
        $plazos[] = $plazo['plazo_entrega'];
    }
    
    // Para cada modelo, obtener sus precios por plazo
    $modelos = [];
    while ($opcion = $resultOpciones->fetch_assoc()) {
        $modelo = [
            'id' => $opcion['id'],
            'nombre' => $opcion['nombre'],
            'precios' => []
        ];
        
        // Obtener precios para este modelo
        $query = "SELECT * FROM opcion_precios WHERE opcion_id = ? ORDER BY plazo_entrega";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $opcion['id']);
        $stmt->execute();
        $resultPrecios = $stmt->get_result();
        
        while ($precio = $resultPrecios->fetch_assoc()) {
            $modelo['precios'][$precio['plazo_entrega']] = $precio['precio'];
        }
        
        $modelos[] = $modelo;
    }
    
    // Obtener la fecha de la última importación
    $query = "SELECT * FROM fuente_datos ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $conn->query($query);
    $ultimaImportacion = $result->fetch_assoc();
    
    // Template HTML
    include_once 'header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Validación de Datos - SALVAESCALERAS</h1>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['flash_message']; 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-1"></i>
                Modelos importados de SALVAESCALERAS
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-primary btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
                <a href="import_salvaescaleras.php" class="btn btn-warning btn-sm">
                    <i class="fas fa-sync"></i> Reimportar Datos
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($ultimaImportacion)): ?>
            <div class="mb-3">
                <strong>Última importación:</strong> <?php echo date('d/m/Y H:i:s', strtotime($ultimaImportacion['fecha_actualizacion'])); ?>
                <br>
                <strong>Fuente:</strong> <?php echo htmlspecialchars($ultimaImportacion['url']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($modelos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Modelo</th>
                            <?php foreach ($plazos as $plazo): ?>
                            <th class="text-center"><?php echo $plazo; ?> días</th>
                            <?php endforeach; ?>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modelos as $modelo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($modelo['nombre']); ?></td>
                            <?php foreach ($plazos as $plazo): ?>
                            <td class="text-end">
                                <?php 
                                if (isset($modelo['precios'][$plazo])) {
                                    echo formatPrice($modelo['precios'][$plazo]);
                                } else {
                                    echo '<span class="text-muted">N/A</span>';
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary edit-model" data-id="<?php echo $modelo['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                No se encontraron modelos de SALVAESCALERAS en la base de datos.
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>
            Información para el Cliente
        </div>
        <div class="card-body">
            <p>
                Los modelos de salvaescaleras mostrados arriba están disponibles para su cotización.
                Puede acceder a ellos desde el cotizador seleccionando la categoría SALVAESCALERAS.
            </p>
            <a href="../cotizador.php" class="btn btn-success" target="_blank">
                <i class="fas fa-external-link-alt"></i> Ir al Cotizador
            </a>
        </div>
    </div>
</div>

<!-- Modal para editar modelo -->
<div class="modal fade" id="editModelModal" tabindex="-1" aria-labelledby="editModelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModelModalLabel">Editar Modelo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editModelForm" action="update_salvaescaleras_model.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="model_id" id="model_id">
                    
                    <div class="mb-3">
                        <label for="model_name" class="form-label">Nombre del Modelo</label>
                        <input type="text" class="form-control" id="model_name" name="model_name" required>
                    </div>
                    
                    <div id="precios_container">
                        <!-- Los campos de precios se cargarán dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar clic en botón de editar
    const editButtons = document.querySelectorAll('.edit-model');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modelId = this.getAttribute('data-id');
            
            // Hacer una solicitud AJAX para obtener los datos del modelo
            fetch('get_salvaescaleras_model.php?id=' + modelId)
                .then(response => response.json())
                .then(data => {
                    // Llenar el formulario con los datos recibidos
                    document.getElementById('model_id').value = data.id;
                    document.getElementById('model_name').value = data.nombre;
                    
                    // Generar campos para los precios
                    const preciosContainer = document.getElementById('precios_container');
                    preciosContainer.innerHTML = '';
                    
                    Object.entries(data.precios).forEach(([plazo, precio]) => {
                        const div = document.createElement('div');
                        div.className = 'mb-3';
                        div.innerHTML = `
                            <label for="precio_${plazo}" class="form-label">Precio (${plazo} días)</label>
                            <input type="number" step="0.01" class="form-control" 
                                   id="precio_${plazo}" name="precios[${plazo}]" 
                                   value="${precio}" required>
                        `;
                        preciosContainer.appendChild(div);
                    });
                    
                    // Abrir el modal
                    const modal = new bootstrap.Modal(document.getElementById('editModelModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos del modelo.');
                });
        });
    });
});
</script>

<?php
    include_once 'footer.php';
    
} catch (Exception $e) {
    // Manejar error
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}
?> 
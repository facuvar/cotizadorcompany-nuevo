<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/flash.php';
require_once '../includes/header.php';

// Verificar que el usuario sea administrador
requireAdmin();

// Función para formatear precios
function formatPrice($price) {
    if ($price === null) {
        return '-';
    }
    return number_format($price, 2, ',', '.') . ' €';
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener la categoría MONTAPLATOS
    $queryCategoria = "SELECT id FROM categorias WHERE nombre = 'MONTAPLATOS'";
    $resultCategoria = $conn->query($queryCategoria);
    
    if ($resultCategoria->num_rows === 0) {
        throw new Exception('No se encontró la categoría MONTAPLATOS');
    }
    
    $categoriaId = $resultCategoria->fetch_assoc()['id'];
    
    // Obtener modelos y precios
    $queryModelos = "SELECT o.id, o.nombre 
                     FROM opciones o 
                     WHERE o.categoria_id = ?
                     ORDER BY o.nombre";
    
    $stmt = $conn->prepare($queryModelos);
    $stmt->bind_param('i', $categoriaId);
    $stmt->execute();
    $resultModelos = $stmt->get_result();
    
    if ($resultModelos->num_rows === 0) {
        setFlashMessage('info', 'No hay modelos de MONTAPLATOS importados.');
    }
    
    // Obtener todos los plazos de entrega
    $queryPlazos = "SELECT id, nombre, dias FROM plazos_entrega ORDER BY dias";
    $resultPlazos = $conn->query($queryPlazos);
    $plazos = [];
    
    while ($plazo = $resultPlazos->fetch_assoc()) {
        $plazos[] = $plazo;
    }
    
    // Obtener todos los precios de las opciones
    $queryPrecios = "SELECT op.opcion_id, op.plazo_id, op.precio
                     FROM opcion_precios op
                     WHERE op.opcion_id IN (SELECT id FROM opciones WHERE categoria_id = ?)";
    $stmtPrecios = $conn->prepare($queryPrecios);
    $stmtPrecios->bind_param('i', $categoriaId);
    $stmtPrecios->execute();
    $resultPrecios = $stmtPrecios->get_result();
    
    $precios = [];
    while ($precio = $resultPrecios->fetch_assoc()) {
        if (!isset($precios[$precio['opcion_id']])) {
            $precios[$precio['opcion_id']] = [];
        }
        $precios[$precio['opcion_id']][$precio['plazo_id']] = $precio['precio'];
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar Modelos MONTAPLATOS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        .table-responsive {
            max-height: 80vh;
            overflow-y: auto;
        }
        .sticky-header th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Modelos MONTAPLATOS Importados</h4>
                    </div>
                    <div class="card-body">
                        <?php if (hasFlashMessage('success')): ?>
                            <div class="alert alert-success">
                                <?php echo getFlashMessage('success'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (hasFlashMessage('error')): ?>
                            <div class="alert alert-danger">
                                <?php echo getFlashMessage('error'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (hasFlashMessage('info')): ?>
                            <div class="alert alert-info">
                                <?php echo getFlashMessage('info'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <a href="import_montaplatos.php" class="btn btn-success">
                                <i class="fas fa-sync"></i> Importar datos
                            </a>
                            <a href="view_plazos_entrega.php" class="btn btn-info">
                                <i class="fas fa-clock"></i> Gestionar plazos de entrega
                            </a>
                        </div>
                        
                        <?php if (isset($resultModelos) && $resultModelos->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="sticky-header">
                                        <tr class="bg-light">
                                            <th>Modelo</th>
                                            <?php foreach ($plazos as $plazo): ?>
                                                <th><?php echo htmlspecialchars($plazo['nombre']); ?></th>
                                            <?php endforeach; ?>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($modelo = $resultModelos->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($modelo['nombre']); ?></td>
                                                <?php foreach ($plazos as $plazo): ?>
                                                    <td>
                                                        <?php 
                                                        $precio = isset($precios[$modelo['id']][$plazo['id']]) 
                                                            ? $precios[$modelo['id']][$plazo['id']] 
                                                            : null;
                                                        echo formatPrice($precio);
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-primary edit-model" data-id="<?php echo $modelo['id']; ?>">
                                                        <i class="fas fa-edit"></i> Editar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No hay modelos MONTAPLATOS importados. 
                                <a href="import_montaplatos.php" class="alert-link">Importar ahora</a>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar modelo -->
    <div class="modal fade" id="editModelModal" tabindex="-1" role="dialog" aria-labelledby="editModelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editModelModalLabel">Editar Modelo MONTAPLATOS</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editModelForm" action="update_montaplatos_model.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="model_id" id="model_id">
                        
                        <div class="form-group">
                            <label for="model_name">Nombre del modelo:</label>
                            <input type="text" class="form-control" id="model_name" name="model_name" required>
                        </div>
                        
                        <h5 class="mt-4">Precios por plazo de entrega:</h5>
                        <div id="prices_container" class="row">
                            <!-- Los campos de precios se cargarán dinámicamente -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Manejar clic en el botón de editar
            $('.edit-model').click(function() {
                const modelId = $(this).data('id');
                
                // Limpiar contenedor de precios
                $('#prices_container').empty();
                
                // Cargar datos del modelo mediante AJAX
                $.ajax({
                    url: 'get_montaplatos_model.php',
                    method: 'GET',
                    data: { id: modelId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#model_id').val(response.model.id);
                            $('#model_name').val(response.model.nombre);
                            
                            // Crear campos para los precios
                            response.plazos.forEach(function(plazo) {
                                const precio = response.prices[plazo.id] !== undefined ? response.prices[plazo.id] : '';
                                
                                const priceField = `
                                    <div class="col-md-6 mb-3">
                                        <label for="price_${plazo.id}">${plazo.nombre} (${plazo.dias} días):</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="price_${plazo.id}" 
                                                name="prices[${plazo.id}]" value="${precio}"
                                                placeholder="Dejar vacío si no aplica">
                                            <div class="input-group-append">
                                                <span class="input-group-text">€</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                $('#prices_container').append(priceField);
                            });
                            
                            $('#editModelModal').modal('show');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error al cargar los datos del modelo');
                    }
                });
            });
        });
    </script>
</body>
</html> 
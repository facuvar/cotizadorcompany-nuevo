<?php
// Ver detalles de un presupuesto específico
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Incluir archivos de configuración y base de datos
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar que se recibió un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'ID de presupuesto no válido.';
    header('Location: presupuestos.php');
    exit;
}

$presupuestoId = (int)$_GET['id'];

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener datos del presupuesto
    $sql = "SELECT * FROM presupuestos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $presupuestoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = 'Presupuesto no encontrado.';
        header('Location: presupuestos.php');
        exit;
    }
    
    $presupuesto = $result->fetch_assoc();
    
    // Obtener historial de cambios si existe la tabla
    $historial = [];
    $tableExists = $conn->query("SHOW TABLES LIKE 'presupuestos_historial'")->num_rows > 0;
    
    if ($tableExists) {
        $sqlHistorial = "SELECT * FROM presupuestos_historial WHERE presupuesto_id = ? ORDER BY fecha_cambio DESC";
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->bind_param("i", $presupuestoId);
        $stmtHistorial->execute();
        $resultHistorial = $stmtHistorial->get_result();
        
        while ($row = $resultHistorial->fetch_assoc()) {
            $historial[] = $row;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: presupuestos.php');
    exit;
}

// Obtener mensaje flash
$flashMessage = getFlashMessage();
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Presupuestos de Ascensores</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Administración</h3>
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="presupuestos.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Presupuestos</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <div class="admin-header">
                <h2>Detalles del Presupuesto #<?php echo $presupuesto['id']; ?></h2>
                <div>
                    <a href="presupuestos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button type="button" class="btn btn-danger eliminar-presupuesto" data-id="<?php echo $presupuesto['id']; ?>" data-codigo="<?php echo htmlspecialchars($presupuesto['codigo']); ?>">
                        <i class="fas fa-trash"></i> Eliminar Presupuesto
                    </button>
                </div>
            </div>
            
            <?php if ($flashMessage): ?>
            <div class="flash-message flash-<?php echo $flashMessage['type']; ?>">
                <?php echo $flashMessage['message']; ?>
            </div>
            <?php endif; ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>Información del Presupuesto</h3>
                    <div>
                        <a href="../presupuestos/pdf_detallado.php?id=<?php echo $presupuesto['id']; ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-file-pdf"></i> Ver PDF
                        </a>
                        <button type="button" class="btn btn-warning cambiar-estado" 
                            data-id="<?php echo $presupuesto['id']; ?>" 
                            data-estado="<?php echo $presupuesto['estado']; ?>">
                            <i class="fas fa-exchange-alt"></i> Cambiar Estado
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Información del Cliente</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-striped">
                                        <tr>
                                            <th>Nombre:</th>
                                            <td><?php echo htmlspecialchars($presupuesto['nombre_cliente']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?php echo htmlspecialchars($presupuesto['email_cliente']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Teléfono:</th>
                                            <td><?php echo htmlspecialchars($presupuesto['telefono_cliente']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>IP:</th>
                                            <td><?php echo htmlspecialchars($presupuesto['ip_cliente']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Información del Presupuesto</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-striped">
                                        <tr>
                                            <th>Código:</th>
                                            <td><?php echo htmlspecialchars($presupuesto['codigo']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Fecha Creación:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($presupuesto['fecha_creacion'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Estado:</th>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $presupuesto['estado'] === 'pendiente' ? 'warning' : 
                                                        ($presupuesto['estado'] === 'enviado' ? 'info' : 
                                                        ($presupuesto['estado'] === 'aprobado' ? 'success' : 'danger')); 
                                                ?>">
                                                    <?php echo ucfirst($presupuesto['estado']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Total:</th>
                                            <td>$<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Detalles del Producto</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-striped">
                                                <tr>
                                                    <th>Producto:</th>
                                                    <td><?php echo htmlspecialchars($presupuesto['producto_nombre']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Opción:</th>
                                                    <td><?php echo htmlspecialchars($presupuesto['opcion_nombre']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Plazo de Entrega:</th>
                                                    <td><?php echo htmlspecialchars($presupuesto['plazo_nombre']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Forma de Pago:</th>
                                                    <td><?php echo htmlspecialchars($presupuesto['forma_pago']); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-striped">
                                                <tr>
                                                    <th>Subtotal:</th>
                                                    <td>$<?php echo number_format($presupuesto['subtotal'], 2, ',', '.'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Descuento:</th>
                                                    <td>$<?php echo number_format($presupuesto['descuento'], 2, ',', '.'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Total:</th>
                                                    <td>$<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($presupuesto['adicionales'])): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Adicionales</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Precio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $adicionales = json_decode($presupuesto['adicionales'], true);
                                            if (is_array($adicionales)):
                                                foreach ($adicionales as $adicional): 
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($adicional['nombre']); ?></td>
                                                    <td>$<?php echo number_format($adicional['precio'], 2, ',', '.'); ?></td>
                                                </tr>
                                            <?php 
                                                endforeach;
                                            endif;
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($historial)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Historial de Cambios</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Usuario</th>
                                                <th>Estado Anterior</th>
                                                <th>Nuevo Estado</th>
                                                <th>Comentario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($historial as $cambio): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($cambio['fecha_cambio'])); ?></td>
                                                    <td><?php echo htmlspecialchars($cambio['usuario']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            echo $cambio['estado_anterior'] === 'pendiente' ? 'warning' : 
                                                                ($cambio['estado_anterior'] === 'enviado' ? 'info' : 
                                                                ($cambio['estado_anterior'] === 'aprobado' ? 'success' : 'danger')); 
                                                        ?>">
                                                            <?php echo ucfirst($cambio['estado_anterior']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            echo $cambio['estado_nuevo'] === 'pendiente' ? 'warning' : 
                                                                ($cambio['estado_nuevo'] === 'enviado' ? 'info' : 
                                                                ($cambio['estado_nuevo'] === 'aprobado' ? 'success' : 'danger')); 
                                                        ?>">
                                                            <?php echo ucfirst($cambio['estado_nuevo']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($cambio['comentario']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Modal para eliminar presupuesto -->
<div class="modal fade" id="eliminarPresupuestoModal" tabindex="-1" aria-labelledby="eliminarPresupuestoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eliminarPresupuestoModalLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar el presupuesto <span id="codigo-presupuesto"></span>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <form id="formEliminarPresupuesto" action="eliminar_presupuesto.php" method="POST">
                    <input type="hidden" name="presupuesto_id" id="eliminar_presupuesto_id" value="">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambiar estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1" role="dialog" aria-labelledby="cambiarEstadoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cambiarEstadoModalLabel">Cambiar Estado del Presupuesto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCambiarEstado" action="cambiar_estado_presupuesto.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="presupuesto_id" id="presupuesto_id" value="<?php echo $presupuesto['id']; ?>">
                    <div class="form-group">
                        <label for="nuevo_estado">Nuevo Estado</label>
                        <select name="nuevo_estado" id="nuevo_estado" class="form-control">
                            <option value="pendiente" <?php echo $presupuesto['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="enviado" <?php echo $presupuesto['estado'] === 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                            <option value="aprobado" <?php echo $presupuesto['estado'] === 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                            <option value="rechazado" <?php echo $presupuesto['estado'] === 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comentario">Comentario (opcional)</label>
                        <textarea name="comentario" id="comentario" class="form-control" rows="3"></textarea>
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
    $(document).ready(function() {
        // Manejar botón de cambio de estado
        $('.cambiar-estado').click(function() {
            $('#cambiarEstadoModal').modal('show');
        });
        
        // Manejar el cierre del modal con los botones de Bootstrap 5
        $('.btn-close, .btn-secondary').on('click', function() {
            $('#cambiarEstadoModal').modal('hide');
            $('#eliminarPresupuestoModal').modal('hide');
        });
        
        // Manejar botón de eliminación de presupuesto
        $('.eliminar-presupuesto').click(function() {
            const presupuestoId = $(this).data('id');
            const codigoPresupuesto = $(this).data('codigo');
            
            $('#eliminar_presupuesto_id').val(presupuestoId);
            $('#codigo-presupuesto').text(codigoPresupuesto);
            $('#eliminarPresupuestoModal').modal('show');
        });
        
        // Enviar el formulario de cambio de estado mediante AJAX
        $('#formCambiarEstado').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                type: 'POST',
                url: 'cambiar_estado_presupuesto.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Cerrar el modal
                        $('#cambiarEstadoModal').modal('hide');
                        
                        // Mostrar mensaje de éxito
                        alert('Estado actualizado correctamente');
                        
                        // Recargar la página para mostrar los cambios
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar la solicitud');
                }
            });
        });
        
        // Enviar el formulario de eliminación mediante AJAX
        $('#formEliminarPresupuesto').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                type: 'POST',
                url: 'eliminar_presupuesto.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Cerrar el modal
                        $('#eliminarPresupuestoModal').modal('hide');
                        
                        // Mostrar mensaje de éxito
                        alert('Presupuesto eliminado correctamente');
                        
                        // Redirigir a la lista de presupuestos
                        window.location.href = 'presupuestos.php';
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar la solicitud');
                }
            });
        });
    });
</script>
</body>
</html>

<?php
// Administración de presupuestos
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

// Conectar a la base de datos
$db = Database::getInstance();
$conn = $db->getConnection();

// Verificar si la tabla existe
$tableExists = $conn->query("SHOW TABLES LIKE 'presupuestos'")->num_rows > 0;

// Paginación
$registrosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

// Filtros
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtroBusqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

// Construir consulta SQL
$sql = "SELECT * FROM presupuestos";
$countSql = "SELECT COUNT(*) as total FROM presupuestos";
$whereClauses = [];

if ($filtroEstado) {
    $whereClauses[] = "estado = '" . $conn->real_escape_string($filtroEstado) . "'";
}

if ($filtroBusqueda) {
    $whereClauses[] = "(
        nombre_cliente LIKE '%" . $conn->real_escape_string($filtroBusqueda) . "%' OR 
        email_cliente LIKE '%" . $conn->real_escape_string($filtroBusqueda) . "%' OR 
        codigo LIKE '%" . $conn->real_escape_string($filtroBusqueda) . "%' OR
        telefono_cliente LIKE '%" . $conn->real_escape_string($filtroBusqueda) . "%'
    )";
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
    $countSql .= " WHERE " . implode(' AND ', $whereClauses);
}

$sql .= " ORDER BY fecha_creacion DESC LIMIT $inicio, $registrosPorPagina";

// Obtener total de registros para paginación
$totalRegistros = 0;
if ($tableExists) {
    $countResult = $conn->query($countSql);
    if ($countResult && $countRow = $countResult->fetch_assoc()) {
        $totalRegistros = $countRow['total'];
    }
}

$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Obtener presupuestos
$presupuestos = [];
if ($tableExists) {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $presupuestos[] = $row;
        }
    }
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
                <h2>Administración de Presupuestos</h2>
            </div>
            
            <?php if ($flashMessage): ?>
            <div class="flash-message flash-<?php echo $flashMessage['type']; ?>">
                <?php echo $flashMessage['message']; ?>
            </div>
            <?php endif; ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>Listado de Presupuestos</h3>
                </div>
                <div>
                    <?php if (!$tableExists): ?>
                        <div class="alert alert-warning">
                            <p>La tabla de presupuestos no existe. Debe crear presupuestos primero.</p>
                        </div>
                    <?php else: ?>
                        <!-- Filtros -->
                        <form method="GET" action="presupuestos.php" class="mb-4">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="estado">Estado</label>
                                        <select name="estado" id="estado" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="pendiente" <?php echo $filtroEstado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                            <option value="enviado" <?php echo $filtroEstado === 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                            <option value="aprobado" <?php echo $filtroEstado === 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                                            <option value="rechazado" <?php echo $filtroEstado === 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="busqueda">Buscar</label>
                                        <input type="text" name="busqueda" id="busqueda" class="form-control" 
                                            placeholder="Nombre, email, teléfono o código" 
                                            value="<?php echo htmlspecialchars($filtroBusqueda); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">Filtrar</button>
                                    <a href="presupuestos.php" class="btn btn-secondary ml-2">Limpiar</a>
                                </div>
                            </div>
                        </form>

                        <?php if (empty($presupuestos)): ?>
                            <div class="alert alert-info">
                                <p>No se encontraron presupuestos con los filtros seleccionados.</p>
                            </div>
                        <?php else: ?>
                            <!-- Tabla de presupuestos -->
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Código</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Producto</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($presupuestos as $presupuesto): ?>
                                            <tr>
                                                <td><?php echo $presupuesto['id']; ?></td>
                                                <td><?php echo htmlspecialchars($presupuesto['codigo']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($presupuesto['fecha_creacion'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($presupuesto['nombre_cliente']); ?><br>
                                                    <small><?php echo htmlspecialchars($presupuesto['email_cliente']); ?></small><br>
                                                    <small><?php echo htmlspecialchars($presupuesto['telefono_cliente']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($presupuesto['producto_nombre']); ?></td>
                                                <td>$<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $presupuesto['estado'] === 'pendiente' ? 'warning' : 
                                                            ($presupuesto['estado'] === 'enviado' ? 'info' : 
                                                            ($presupuesto['estado'] === 'aprobado' ? 'success' : 'danger')); 
                                                    ?>">
                                                        <?php echo ucfirst($presupuesto['estado']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="ver_presupuesto.php?id=<?php echo $presupuesto['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> Ver
                                                        </a>
                                                        <a href="../../presupuestos/pdf_detallado.php?id=<?php echo $presupuesto['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-primary cambiar-estado" 
                                                            data-id="<?php echo $presupuesto['id']; ?>" 
                                                            data-estado="<?php echo $presupuesto['estado']; ?>">
                                                            <i class="fas fa-exchange-alt"></i> Estado
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger eliminar-presupuesto" 
                                                            data-id="<?php echo $presupuesto['id']; ?>" 
                                                            data-codigo="<?php echo htmlspecialchars($presupuesto['codigo']); ?>">
                                                            <i class="fas fa-trash"></i> Eliminar
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <?php if ($totalPaginas > 1): ?>
                                <nav aria-label="Paginación de presupuestos">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($paginaActual > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=1<?php echo $filtroEstado ? '&estado=' . urlencode($filtroEstado) : ''; ?><?php echo $filtroBusqueda ? '&busqueda=' . urlencode($filtroBusqueda) : ''; ?>">
                                                    Primera
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?><?php echo $filtroEstado ? '&estado=' . urlencode($filtroEstado) : ''; ?><?php echo $filtroBusqueda ? '&busqueda=' . urlencode($filtroBusqueda) : ''; ?>">
                                                    Anterior
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $inicio = max(1, $paginaActual - 2);
                                        $fin = min($totalPaginas, $paginaActual + 2);
                                        
                                        for ($i = $inicio; $i <= $fin; $i++): 
                                        ?>
                                            <li class="page-item <?php echo $i === $paginaActual ? 'active' : ''; ?>">
                                                <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo $filtroEstado ? '&estado=' . urlencode($filtroEstado) : ''; ?><?php echo $filtroBusqueda ? '&busqueda=' . urlencode($filtroBusqueda) : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($paginaActual < $totalPaginas): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?><?php echo $filtroEstado ? '&estado=' . urlencode($filtroEstado) : ''; ?><?php echo $filtroBusqueda ? '&busqueda=' . urlencode($filtroBusqueda) : ''; ?>">
                                                    Siguiente
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $totalPaginas; ?><?php echo $filtroEstado ? '&estado=' . urlencode($filtroEstado) : ''; ?><?php echo $filtroBusqueda ? '&busqueda=' . urlencode($filtroBusqueda) : ''; ?>">
                                                    Última
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
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
                    <input type="hidden" name="presupuesto_id" id="presupuesto_id" value="">
                    <div class="form-group">
                        <label for="nuevo_estado">Nuevo Estado</label>
                        <select name="nuevo_estado" id="nuevo_estado" class="form-control">
                            <option value="pendiente">Pendiente</option>
                            <option value="enviado">Enviado</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comentario">Comentario (opcional)</label>
                        <textarea name="comentario" id="comentario" class="form-control" rows="3"></textarea>
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

<script>
    $(document).ready(function() {
        // Manejar botón de cambio de estado
        $('.cambiar-estado').click(function() {
            const presupuestoId = $(this).data('id');
            const estadoActual = $(this).data('estado');
            
            $('#presupuesto_id').val(presupuestoId);
            $('#nuevo_estado').val(estadoActual);
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
    });
</script>
</body>
</html>

<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar si el formulario ha sido enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/cotizador.php');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener datos del cliente
    $nombreCliente = cleanString($_POST['nombreCliente']);
    $emailCliente = cleanString($_POST['emailCliente']);
    $telefonoCliente = isset($_POST['telefonoCliente']) ? cleanString($_POST['telefonoCliente']) : '';
    
    // Generar código único para el presupuesto
    $codigo = generateUniqueCode();
    $ipCliente = getClientIP();
    
    // Inicializar total
    $total = 0;
    
    // Obtener categorías
    $query = "SELECT * FROM categorias ORDER BY orden ASC";
    $categorias = $db->query($query);
    
    // Array para guardar las opciones seleccionadas
    $opcionesSeleccionadas = [];
    
    // Procesar selecciones del usuario
    if ($db->numRows($categorias) > 0) {
        while ($categoria = $db->fetchArray($categorias)) {
            $categoriaId = $categoria['id'];
            $opcionKey = 'opcion_' . $categoriaId;
            
            if (isset($_POST[$opcionKey])) {
                $opcionId = intval($_POST[$opcionKey]);
                
                // Obtener información de la opción seleccionada
                $query = "SELECT * FROM opciones WHERE id = $opcionId AND categoria_id = $categoriaId";
                $result = $db->query($query);
                
                if ($db->numRows($result) > 0) {
                    $opcion = $db->fetchArray($result);
                    $total += $opcion['precio'];
                    
                    // Guardar opción seleccionada
                    $opcionesSeleccionadas[] = [
                        'categoria_id' => $categoriaId,
                        'categoria_nombre' => $categoria['nombre'],
                        'opcion_id' => $opcionId,
                        'opcion_nombre' => $opcion['nombre'],
                        'precio' => $opcion['precio']
                    ];
                }
            }
        }
    }
    
    // Guardar el presupuesto en la base de datos
    $query = "INSERT INTO presupuestos (nombre_cliente, email_cliente, telefono_cliente, total, codigo, ip_cliente) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdsss', $nombreCliente, $emailCliente, $telefonoCliente, $total, $codigo, $ipCliente);
    $stmt->execute();
    
    $presupuestoId = $db->getLastId();
    
    // Guardar detalles del presupuesto
    foreach ($opcionesSeleccionadas as $opcion) {
        $query = "INSERT INTO presupuesto_detalle (presupuesto_id, categoria_id, opcion_id, nombre_opcion, precio) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iisd', $presupuestoId, $opcion['categoria_id'], $opcion['opcion_id'], $opcion['opcion_nombre'], $opcion['precio']);
        $stmt->execute();
    }
} catch (Exception $e) {
    // Registrar error
    error_log($e->getMessage());
    die("Ha ocurrido un error al procesar el presupuesto. Por favor, inténtelo nuevamente.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto Generado</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .presupuesto-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }
        
        .presupuesto-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e50009;
        }
        
        .presupuesto-header h2 {
            color: #e50009;
            margin-bottom: 10px;
        }
        
        .presupuesto-codigo {
            font-size: 14px;
            color: #666;
        }
        
        .cliente-info {
            margin-bottom: 30px;
        }
        
        .cliente-info p {
            margin: 5px 0;
        }
        
        .detalle-presupuesto table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .detalle-presupuesto th, 
        .detalle-presupuesto td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .detalle-presupuesto th {
            background-color: #f5f5f5;
            font-weight: 500;
        }
        
        .detalle-presupuesto .precio {
            text-align: right;
        }
        
        .presupuesto-total {
            text-align: right;
            font-size: 24px;
            font-weight: 700;
            color: #e50009;
            margin-bottom: 30px;
        }
        
        .presupuesto-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                background-color: white;
            }
            
            .presupuesto-container {
                box-shadow: none;
                padding: 0;
            }
            
            header, footer {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="no-print">
        <div class="container">
            <div class="logo">
                <h1>Presupuestos de Ascensores</h1>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="presupuesto-container">
                <div class="presupuesto-header">
                    <h2>Presupuesto de Ascensor</h2>
                    <div class="presupuesto-codigo">Código: <?php echo $codigo; ?></div>
                    <div class="presupuesto-fecha">Fecha: <?php echo date('d/m/Y'); ?></div>
                </div>
                
                <div class="cliente-info">
                    <h3>Datos del Cliente</h3>
                    <p><strong>Nombre:</strong> <?php echo $nombreCliente; ?></p>
                    <p><strong>Email:</strong> <?php echo $emailCliente; ?></p>
                    <?php if (!empty($telefonoCliente)): ?>
                    <p><strong>Teléfono:</strong> <?php echo $telefonoCliente; ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="detalle-presupuesto">
                    <h3>Detalle del Presupuesto</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Opción Seleccionada</th>
                                <th class="precio">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($opcionesSeleccionadas as $opcion): ?>
                            <tr>
                                <td><?php echo cleanString($opcion['categoria_nombre']); ?></td>
                                <td><?php echo cleanString($opcion['opcion_nombre']); ?></td>
                                <td class="precio"><?php echo formatNumber($opcion['precio']); ?> €</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="presupuesto-total">
                    Total: <?php echo formatNumber($total); ?> €
                </div>
                
                <div class="presupuesto-note">
                    <p>Este presupuesto tiene una validez de 30 días a partir de la fecha de emisión.</p>
                    <p>Los precios no incluyen IVA.</p>
                </div>
                
                <div class="presupuesto-actions no-print">
                    <a href="cotizador.php" class="btn btn-secondary">Volver al Cotizador</a>
                    <div>
                        <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Imprimir</button>
                        <a href="descargar_presupuesto.php?codigo=<?php echo $codigo; ?>" class="btn btn-secondary"><i class="fas fa-download"></i> Descargar PDF</a>
                        <a href="contactar.php?codigo=<?php echo $codigo; ?>" class="btn btn-primary">Contactar Ahora</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="no-print">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Presupuestos de Ascensores. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html> 
<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener categorías
    $query = "SELECT * FROM categorias ORDER BY orden ASC";
    $categorias = $conn->query($query);
    
    // Plazo por defecto
    $plazoSeleccionado = "120-150 días";
    
    // Obtener plazos de entrega
    $plazos = [
        ["nombre" => "30-60 días", "descripcion" => "Entrega rápida (30-60 días)", "factor" => 1.15],
        ["nombre" => "60-90 días", "descripcion" => "Entrega estándar (60-90 días)", "factor" => 1.10],
        ["nombre" => "90-120 días", "descripcion" => "Entrega normal (90-120 días)", "factor" => 1.05],
        ["nombre" => "120-150 días", "descripcion" => "Entrega programada (120-150 días)", "factor" => 1.00],
        ["nombre" => "150-180 días", "descripcion" => "Entrega extendida (150-180 días)", "factor" => 0.95],
        ["nombre" => "180-210 días", "descripcion" => "Entrega económica (180-210 días)", "factor" => 0.90]
    ];
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizador Simple</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background-color: #333; color: white; padding: 20px 0; }
        h1, h2, h3 { margin-top: 0; }
        .card { background-color: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .options { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .option-item { border: 1px solid #ddd; border-radius: 5px; padding: 15px; transition: all 0.3s; }
        .option-item:hover { border-color: #4CAF50; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .option-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .option-title { font-weight: 500; }
        .option-price { color: #e74c3c; font-weight: bold; }
        .option-description { color: #666; font-size: 14px; }
        .plazo-selector { margin-bottom: 20px; }
        .plazo-selector select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
        .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Cotizador Simple de Ascensores</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="card">
            <h2>Seleccione un plazo de entrega</h2>
            <div class="plazo-selector">
                <select id="plazoSelect">
                    <?php foreach ($plazos as $plazo): ?>
                        <option value="<?php echo $plazo['nombre']; ?>" data-factor="<?php echo $plazo['factor']; ?>"
                            <?php echo ($plazo['nombre'] === $plazoSeleccionado) ? 'selected' : ''; ?>>
                            <?php echo $plazo['nombre']; ?> (<?php echo ($plazo['factor'] > 1 ? '+' : '') . (($plazo['factor'] - 1) * 100) . '%'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if ($categorias && $categorias->num_rows > 0): ?>
            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <div class="card">
                    <h2><?php echo htmlspecialchars($categoria['nombre']); ?></h2>
                    <?php if (!empty($categoria['descripcion'])): ?>
                        <p><?php echo htmlspecialchars($categoria['descripcion']); ?></p>
                    <?php endif; ?>
                    
                    <div class="options">
                        <?php
                        // Obtener opciones para esta categoría
                        $query = "SELECT * FROM opciones WHERE categoria_id = " . $categoria['id'] . " ORDER BY orden ASC";
                        $opciones = $conn->query($query);
                        
                        if ($opciones && $opciones->num_rows > 0):
                            while ($opcion = $opciones->fetch_assoc()):
                                $precioBase = $opcion['precio'];
                        ?>
                            <div class="option-item" data-precio-base="<?php echo $precioBase; ?>">
                                <div class="option-header">
                                    <div class="option-title"><?php echo htmlspecialchars($opcion['nombre']); ?></div>
                                    <div class="option-price">$<?php echo number_format($precioBase, 2, ',', '.'); ?></div>
                                </div>
                                <?php if (!empty($opcion['descripcion'])): ?>
                                    <div class="option-description"><?php echo htmlspecialchars($opcion['descripcion']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <p>No hay opciones disponibles para esta categoría.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <h2>No hay categorías disponibles</h2>
                <p>No se encontraron categorías en la base de datos.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const plazoSelect = document.getElementById('plazoSelect');
            const optionItems = document.querySelectorAll('.option-item');
            
            // Función para actualizar precios según el plazo seleccionado
            function actualizarPrecios() {
                const selectedOption = plazoSelect.options[plazoSelect.selectedIndex];
                const factor = parseFloat(selectedOption.dataset.factor);
                
                optionItems.forEach(item => {
                    const precioBase = parseFloat(item.dataset.precioBase);
                    const precioAjustado = precioBase * factor;
                    const precioElement = item.querySelector('.option-price');
                    
                    precioElement.textContent = '$' + precioAjustado.toLocaleString('es-AR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).replace('.', ',');
                });
            }
            
            // Actualizar precios al cambiar el plazo
            plazoSelect.addEventListener('change', actualizarPrecios);
            
            // Actualizar precios iniciales
            actualizarPrecios();
        });
    </script>
</body>
</html>
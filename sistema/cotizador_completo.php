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
    
    // Obtener plazos de entrega
    $query = "SELECT * FROM plazos_entrega ORDER BY orden ASC";
    $plazos = $conn->query($query);
    
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizador de Ascensores</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background-color: #333; color: white; padding: 20px 0; }
        h1, h2, h3 { margin-top: 0; }
        .card { background-color: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .steps { display: flex; margin-bottom: 30px; }
        .step { flex: 1; text-align: center; padding: 15px; position: relative; }
        .step.active { font-weight: bold; color: #4CAF50; }
        .step:not(:last-child):after { content: ''; position: absolute; top: 50%; right: 0; width: 100%; height: 2px; background-color: #ddd; z-index: 1; }
        .step-content { display: none; }
        .step-content.active { display: block; }
        .product-grid, .options-grid, .adicionales-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .product-item, .option-item, .adicional-item { border: 1px solid #ddd; border-radius: 5px; padding: 15px; cursor: pointer; transition: all 0.3s; }
        .product-item:hover, .option-item:hover, .adicional-item:hover { border-color: #4CAF50; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .product-item.selected, .option-item.selected, .adicional-item.selected { border-color: #4CAF50; background-color: #e8f5e9; }
        .product-title, .option-title, .adicional-title { font-weight: 500; margin-bottom: 10px; }
        .product-description, .option-description, .adicional-description { color: #666; font-size: 14px; }
        .option-price, .adicional-price { color: #e74c3c; font-weight: bold; text-align: right; margin-top: 10px; }
        .plazo-selector { margin-bottom: 20px; }
        .plazo-selector select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; width: 100%; }
        .navigation { display: flex; justify-content: space-between; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn-secondary { background-color: #f5f5f5; color: #333; border: 1px solid #ddd; }
        .summary-section { margin-top: 30px; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .total-section { font-size: 18px; font-weight: bold; margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Cotizador de Ascensores</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="steps">
            <div class="step active" data-step="1">1. Seleccionar Producto</div>
            <div class="step" data-step="2">2. Seleccionar Opción</div>
            <div class="step" data-step="3">3. Seleccionar Plazo</div>
            <div class="step" data-step="4">4. Adicionales</div>
            <div class="step" data-step="5">5. Resumen</div>
        </div>
        
        <!-- Paso 1: Seleccionar Producto -->
        <div class="step-content active" id="step-1">
            <div class="card">
                <h2>Seleccione un Producto</h2>
                <div class="product-grid">
                    <?php if ($categorias && $categorias->num_rows > 0): ?>
                        <?php while ($categoria = $categorias->fetch_assoc()): ?>
                            <div class="product-item" data-id="<?php echo $categoria['id']; ?>">
                                <div class="product-title"><?php echo htmlspecialchars($categoria['nombre']); ?></div>
                                <?php if (!empty($categoria['descripcion'])): ?>
                                    <div class="product-description"><?php echo htmlspecialchars($categoria['descripcion']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No hay categorías disponibles.</p>
                    <?php endif; ?>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary" disabled>Anterior</button>
                    <button class="btn next-step" data-next="2">Siguiente</button>
                </div>
            </div>
        </div>
        
        <!-- Paso 2: Seleccionar Opción -->
        <div class="step-content" id="step-2">
            <div class="card">
                <h2>Seleccione una Opción</h2>
                <div id="options-container" class="options-grid">
                    <p>Primero seleccione un producto.</p>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary prev-step" data-prev="1">Anterior</button>
                    <button class="btn next-step" data-next="3">Siguiente</button>
                </div>
            </div>
        </div>
        
        <!-- Paso 3: Seleccionar Plazo -->
        <div class="step-content" id="step-3">
            <div class="card">
                <h2>Seleccione un Plazo de Entrega</h2>
                <div class="plazo-selector">
                    <select id="plazoSelect">
                        <?php if ($plazos && $plazos->num_rows > 0): ?>
                            <?php while ($plazo = $plazos->fetch_assoc()): ?>
                                <option value="<?php echo $plazo['id']; ?>" data-factor="<?php echo $plazo['factor']; ?>">
                                    <?php echo htmlspecialchars($plazo['nombre']); ?>
                                    <?php if ($plazo['factor'] != 1): ?>
                                        (<?php echo ($plazo['factor'] > 1 ? '+' : '') . (($plazo['factor'] - 1) * 100) . '%'; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">No hay plazos disponibles</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary prev-step" data-prev="2">Anterior</button>
                    <button class="btn next-step" data-next="4">Siguiente</button>
                </div>
            </div>
        </div>
        
        <!-- Paso 4: Adicionales -->
        <div class="step-content" id="step-4">
            <div class="card">
                <h2>Seleccione Adicionales (Opcional)</h2>
                <div id="adicionales-container" class="adicionales-grid">
                    <p>Cargando adicionales...</p>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary prev-step" data-prev="3">Anterior</button>
                    <button class="btn next-step" data-next="5">Siguiente</button>
                </div>
            </div>
        </div>
        
        <!-- Paso 5: Resumen -->
        <div class="step-content" id="step-5">
            <div class="card">
                <h2>Resumen del Presupuesto</h2>
                <div class="summary-section">
                    <h3>Producto y Opción Seleccionados</h3>
                    <div id="producto-resumen" class="summary-item">
                        <div class="item-name">-</div>
                        <div class="item-price">-</div>
                    </div>
                    <div id="opcion-resumen" class="summary-item">
                        <div class="item-name">-</div>
                        <div class="item-price">-</div>
                    </div>
                    
                    <h3>Adicionales Seleccionados</h3>
                    <div id="adicionales-resumen">
                        <div class="summary-item">
                            <div class="item-name">No hay adicionales seleccionados</div>
                            <div class="item-price">$0,00</div>
                        </div>
                    </div>
                    
                    <div class="total-section">
                        <div class="summary-item">
                            <div class="item-name">Total:</div>
                            <div id="total-precio" class="item-price">$0,00</div>
                        </div>
                    </div>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary prev-step" data-prev="4">Anterior</button>
                    <button class="btn" id="generar-pdf">Generar PDF</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let selectedProduct = null;
            let selectedOption = null;
            let selectedPlazo = null;
            let selectedAdicionales = [];
            
            // Elementos DOM
            const steps = document.querySelectorAll('.step');
            const stepContents = document.querySelectorAll('.step-content');
            const productItems = document.querySelectorAll('.product-item');
            const optionsContainer = document.getElementById('options-container');
            const adicionalesContainer = document.getElementById('adicionales-container');
            const plazoSelect = document.getElementById('plazoSelect');
            const nextButtons = document.querySelectorAll('.next-step');
            const prevButtons = document.querySelectorAll('.prev-step');
            
            // Elementos del resumen
            const productoResumen = document.getElementById('producto-resumen');
            const opcionResumen = document.getElementById('opcion-resumen');
            const adicionalesResumen = document.getElementById('adicionales-resumen');
            const totalPrecio = document.getElementById('total-precio');
            
            // Inicializar
            if (plazoSelect.options.length > 0) {
                selectedPlazo = {
                    id: plazoSelect.value,
                    nombre: plazoSelect.options[plazoSelect.selectedIndex].text,
                    factor: parseFloat(plazoSelect.options[plazoSelect.selectedIndex].dataset.factor)
                };
            }
            
            // Selección de producto
            productItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Quitar selección anterior
                    productItems.forEach(p => p.classList.remove('selected'));
                    
                    // Marcar como seleccionado
                    this.classList.add('selected');
                    
                    // Guardar producto seleccionado
                    selectedProduct = {
                        id: this.dataset.id,
                        nombre: this.querySelector('.product-title').textContent
                    };
                    
                    // Cargar opciones para este producto
                    cargarOpciones(selectedProduct.id);
                });
            });
            
            // Función para cargar opciones
            function cargarOpciones(productoId) {
                optionsContainer.innerHTML = '<p>Cargando opciones...</p>';
                
                // Realizar petición AJAX para obtener opciones
                fetch(`get_opciones.php?producto_id=${productoId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            optionsContainer.innerHTML = '';
                            data.forEach(opcion => {
                                const optionItem = document.createElement('div');
                                optionItem.className = 'option-item';
                                optionItem.dataset.id = opcion.id;
                                optionItem.dataset.precio = opcion.precio_base;
                                
                                optionItem.innerHTML = `
                                    <div class="option-title">${opcion.nombre}</div>
                                    <div class="option-price">$${formatNumber(opcion.precio_base)}</div>
                                `;
                                
                                optionItem.addEventListener('click', function() {
                                    // Quitar selección anterior
                                    document.querySelectorAll('.option-item').forEach(o => o.classList.remove('selected'));
                                    
                                    // Marcar como seleccionado
                                    this.classList.add('selected');
                                    
                                    // Guardar opción seleccionada
                                    selectedOption = {
                                        id: this.dataset.id,
                                        nombre: this.querySelector('.option-title').textContent,
                                        precio: parseFloat(this.dataset.precio)
                                    };
                                    
                                    // Actualizar precio según plazo seleccionado
                                    actualizarPrecioOpcion();
                                });
                                
                                optionsContainer.appendChild(optionItem);
                            });
                        } else {
                            optionsContainer.innerHTML = '<p>No hay opciones disponibles para este producto.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        optionsContainer.innerHTML = '<p>Error al cargar las opciones. Por favor, inténtelo de nuevo.</p>';
                    });
            }
            
            // Función para cargar adicionales
            function cargarAdicionales(productoId) {
                adicionalesContainer.innerHTML = '<p>Cargando adicionales...</p>';
                
                // Realizar petición AJAX para obtener adicionales
                fetch(`get_adicionales.php?producto_id=${productoId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            adicionalesContainer.innerHTML = '';
                            data.forEach(adicional => {
                                const adicionalItem = document.createElement('div');
                                adicionalItem.className = 'adicional-item';
                                adicionalItem.dataset.id = adicional.id;
                                adicionalItem.dataset.precio = adicional.precio_base;
                                
                                adicionalItem.innerHTML = `
                                    <div class="adicional-title">${adicional.nombre}</div>
                                    <div class="adicional-price">$${formatNumber(adicional.precio_base)}</div>
                                `;
                                
                                adicionalItem.addEventListener('click', function() {
                                    this.classList.toggle('selected');
                                    
                                    // Actualizar lista de adicionales seleccionados
                                    if (this.classList.contains('selected')) {
                                        selectedAdicionales.push({
                                            id: this.dataset.id,
                                            nombre: this.querySelector('.adicional-title').textContent,
                                            precio: parseFloat(this.dataset.precio)
                                        });
                                    } else {
                                        selectedAdicionales = selectedAdicionales.filter(a => a.id !== this.dataset.id);
                                    }
                                    
                                    // Actualizar precios de adicionales según plazo seleccionado
                                    actualizarPreciosAdicionales();
                                });
                                
                                adicionalesContainer.appendChild(adicionalItem);
                            });
                        } else {
                            adicionalesContainer.innerHTML = '<p>No hay adicionales disponibles para este producto.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        adicionalesContainer.innerHTML = '<p>Error al cargar los adicionales. Por favor, inténtelo de nuevo.</p>';
                    });
            }
            
            // Función para actualizar precio de la opción según plazo
            function actualizarPrecioOpcion() {
                if (selectedOption && selectedPlazo) {
                    // Calcular precio según factor del plazo
                    const precioAjustado = selectedOption.precio * selectedPlazo.factor;
                    
                    // Actualizar precio en la interfaz
                    const optionItem = document.querySelector(`.option-item[data-id="${selectedOption.id}"]`);
                    if (optionItem) {
                        const priceElement = optionItem.querySelector('.option-price');
                        priceElement.textContent = `$${formatNumber(precioAjustado)}`;
                        
                        // Actualizar precio en el objeto
                        selectedOption.precioAjustado = precioAjustado;
                    }
                }
            }
            
            // Función para actualizar precios de adicionales según plazo
            function actualizarPreciosAdicionales() {
                if (selectedPlazo) {
                    // Para cada adicional seleccionado
                    selectedAdicionales.forEach(adicional => {
                        // Calcular precio según factor del plazo
                        const precioAjustado = adicional.precio * selectedPlazo.factor;
                        
                        // Actualizar precio en la interfaz
                        const adicionalItem = document.querySelector(`.adicional-item[data-id="${adicional.id}"]`);
                        if (adicionalItem) {
                            const priceElement = adicionalItem.querySelector('.adicional-price');
                            priceElement.textContent = `$${formatNumber(precioAjustado)}`;
                            
                            // Actualizar precio en el objeto
                            adicional.precioAjustado = precioAjustado;
                        }
                    });
                }
            }
            
            // Cambio de plazo de entrega
            plazoSelect.addEventListener('change', function() {
                selectedPlazo = {
                    id: this.value,
                    nombre: this.options[this.selectedIndex].text,
                    factor: parseFloat(this.options[this.selectedIndex].dataset.factor)
                };
                
                // Actualizar precios
                actualizarPrecioOpcion();
                actualizarPreciosAdicionales();
            });
            
            // Navegación entre pasos
            nextButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const currentStep = parseInt(this.closest('.step-content').id.split('-')[1]);
                    const nextStep = parseInt(this.dataset.next);
                    
                    // Validar que se pueda avanzar
                    if (currentStep === 1 && !selectedProduct) {
                        alert('Por favor, seleccione un producto.');
                        return;
                    }
                    
                    if (currentStep === 2 && !selectedOption) {
                        alert('Por favor, seleccione una opción.');
                        return;
                    }
                    
                    // Si vamos al paso de adicionales, cargarlos
                    if (nextStep === 4 && selectedProduct) {
                        cargarAdicionales(selectedProduct.id);
                    }
                    
                    // Si vamos al paso de resumen, actualizarlo
                    if (nextStep === 5) {
                        actualizarResumen();
                    }
                    
                    // Cambiar al siguiente paso
                    cambiarPaso(nextStep);
                });
            });
            
            prevButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const prevStep = parseInt(this.dataset.prev);
                    cambiarPaso(prevStep);
                });
            });
            
            // Función para cambiar de paso
            function cambiarPaso(stepNumber) {
                // Ocultar todos los pasos
                stepContents.forEach(content => content.classList.remove('active'));
                steps.forEach(step => step.classList.remove('active'));
                
                // Mostrar el paso seleccionado
                document.getElementById(`step-${stepNumber}`).classList.add('active');
                document.querySelector(`.step[data-step="${stepNumber}"]`).classList.add('active');
            }
            
            // Función para actualizar el resumen
            function actualizarResumen() {
                if (selectedProduct && selectedOption) {
                    productoResumen.innerHTML = `
                        <div class="item-name">${selectedProduct.nombre}</div>
                        <div class="item-price">-</div>
                    `;
                    
                    opcionResumen.innerHTML = `
                        <div class="item-name">${selectedOption.nombre}</div>
                        <div class="item-price">$${formatNumber(selectedOption.precioAjustado || selectedOption.precio)}</div>
                    `;
                }
                
                // Actualizar adicionales
                if (selectedAdicionales.length > 0) {
                    adicionalesResumen.innerHTML = '';
                    selectedAdicionales.forEach(adicional => {
                        const adicionalItem = document.createElement('div');
                        adicionalItem.className = 'summary-item';
                        adicionalItem.innerHTML = `
                            <div class="item-name">${adicional.nombre}</div>
                            <div class="item-price">$${formatNumber(adicional.precioAjustado || adicional.precio)}</div>
                        `;
                        adicionalesResumen.appendChild(adicionalItem);
                    });
                } else {
                    adicionalesResumen.innerHTML = `
                        <div class="summary-item">
                            <div class="item-name">No hay adicionales seleccionados</div>
                            <div class="item-price">$0,00</div>
                        </div>
                    `;
                }
                
                // Calcular total
                let total = selectedOption ? (selectedOption.precioAjustado || selectedOption.precio) : 0;
                selectedAdicionales.forEach(adicional => {
                    total += (adicional.precioAjustado || adicional.precio);
                });
                
                totalPrecio.textContent = `$${formatNumber(total)}`;
            }
            
            // Función para formatear números
            function formatNumber(number) {
                return number.toLocaleString('es-AR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).replace('.', ',');
            }
            
            // Generar PDF
            document.getElementById('generar-pdf').addEventListener('click', function() {
                alert('Generando PDF del presupuesto...');
                // Aquí iría la lógica para generar el PDF
            });
        });
    </script>
</body>
</html>

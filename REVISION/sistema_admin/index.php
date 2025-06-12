<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar si el administrador está logueado
requireAdmin();

// Obtener información de la fuente de datos
$fuenteDatos = null;
$hayDatos = false;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $query = "SELECT * FROM fuente_datos ORDER BY fecha_actualizacion DESC LIMIT 1";
    $result = $db->query($query);
    
    if ($result && $db->numRows($result) > 0) {
        $fuenteDatos = $db->fetchArray($result);
        $hayDatos = true;
    }
} catch (Exception $e) {
    // La base de datos podría no existir todavía
}

// Obtener mensaje flash
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Presupuestos de Ascensores</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Administración</h3>
            <ul>
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="presupuestos.php"><i class="fas fa-list"></i> Presupuestos</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <div class="admin-header">
                <h2>Panel de Administración</h2>
            </div>
            
            <?php if ($flashMessage): ?>
            <div class="flash-message flash-<?php echo $flashMessage['type']; ?>">
                <?php echo $flashMessage['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="file-upload-section">
                <h3>Actualizar Datos</h3>
                
                <?php if ($hayDatos): ?>
                <div class="current-data-info">
                    <p><strong>Tipo de fuente actual:</strong> <?php echo $fuenteDatos['tipo'] === 'excel' ? 'Archivo Excel' : 'Google Sheets'; ?></p>
                    <?php if ($fuenteDatos['tipo'] === 'excel'): ?>
                    <p><strong>Archivo:</strong> <?php echo $fuenteDatos['archivo']; ?></p>
                    <?php else: ?>
                    <p><strong>URL Google Sheets:</strong> <?php echo $fuenteDatos['url']; ?></p>
                    <?php endif; ?>
                    <p><strong>Última actualización:</strong> <?php echo date('d/m/Y H:i:s', strtotime($fuenteDatos['fecha_actualizacion'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="file-upload-box">
                    <h4>Importación de Datos</h4>
                    <p>Selecciona el método de importación:</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
                        <a href="../../importar_xls_formulas_v2.php" class="btn btn-success" style="text-decoration: none;">
                            <i class="fas fa-file-excel"></i> Usar Importador Verificado
                        </a>
                        <p><strong>Recomendado:</strong> Este importador ha sido verificado y funciona correctamente con las tablas xls_.</p>
                        
                        <form id="fileUploadForm" action="upload_excel.php" method="post" enctype="multipart/form-data">
                            <h5>Método alternativo (no recomendado)</h5>
                            <input type="file" id="excelFile" name="excelFile" accept=".xlsx" style="display: none;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('excelFile').click();">Seleccionar Archivo</button>
                                <a href="create_template.php" class="btn btn-outline" style="border: 1px solid #ccc; padding: 8px 12px; border-radius: 4px; text-decoration: none; color: #666; font-size: 14px;">
                                    <i class="fas fa-download"></i> Descargar Plantilla
                                </a>
                            </div>
                            <span id="fileName">Ningún archivo seleccionado</span>
                            
                            <div class="progress-bar" id="progressBar" style="display: none;">
                                <div class="progress-bar-fill" id="progressBarFill"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Subir y Procesar</button>
                        </form>
                    </div>
                </div>
                
                <div class="or-divider">O</div>
                
                <div class="google-sheets-section">
                    <form id="googleSheetsForm" action="process_sheets.php" method="post">
                        <h4>Conectar con Google Sheets</h4>
                        <div class="form-group">
                            <label for="sheetsUrl">URL de Google Sheets</label>
                            <input type="url" id="sheetsUrl" name="sheetsUrl" placeholder="https://docs.google.com/spreadsheets/d/..." required>
                        </div>
                        <button type="submit" class="btn btn-primary">Conectar y Procesar</button>
                    </form>
                    
                    <?php if ($hayDatos && $fuenteDatos['tipo'] === 'google_sheets'): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <form action="reconnect_last_file.php" method="post">
                            <input type="hidden" name="source_id" value="<?php echo $fuenteDatos['id']; ?>">
                            <button type="submit" class="btn btn-secondary" style="display: inline-block;">
                                <i class="fas fa-sync-alt"></i> Reconectar último archivo
                            </button>
                        </form>
                        <p style="font-size: 12px; color: #666; margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> 
                            Actualiza los datos usando la última URL de Google Sheets conectada: 
                            <br><span style="font-style: italic; word-break: break-all;"><?php echo htmlspecialchars($fuenteDatos['url']); ?></span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($hayDatos): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>Estadísticas</h3>
                </div>
                
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php
                    // Obtener estadísticas
                    $stats = [
                        'presupuestos' => 0,
                        'categorias' => 0,
                        'opciones' => 0,
                        'plazos' => 0
                    ];
                    
                    try {
                        $query = "SELECT COUNT(*) AS total FROM presupuestos";
                        $result = $db->query($query);
                        if ($result) {
                            $row = $db->fetchArray($result);
                            $stats['presupuestos'] = $row['total'];
                        }
                        
                        $query = "SELECT COUNT(*) AS total FROM categorias";
                        $result = $db->query($query);
                        if ($result) {
                            $row = $db->fetchArray($result);
                            $stats['categorias'] = $row['total'];
                        }
                        
                        $query = "SELECT COUNT(*) AS total FROM opciones";
                        $result = $db->query($query);
                        if ($result) {
                            $row = $db->fetchArray($result);
                            $stats['opciones'] = $row['total'];
                        }
                        
                        $query = "SELECT COUNT(*) AS total FROM plazos_entrega";
                        $result = $db->query($query);
                        if ($result) {
                            $row = $db->fetchArray($result);
                            $stats['plazos'] = $row['total'];
                        }
                    } catch (Exception $e) {
                        // Error al obtener estadísticas
                    }
                    ?>
                    
                    <div class="stat-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 36px; color: #e50009;"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div style="font-size: 24px; font-weight: 500;"><?php echo $stats['presupuestos']; ?></div>
                        <div style="color: #666;">Presupuestos Generados</div>
                    </div>
                    
                    <div class="stat-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 36px; color: #e50009;"><i class="fas fa-th-list"></i></div>
                        <div style="font-size: 24px; font-weight: 500;"><?php echo $stats['categorias']; ?></div>
                        <div style="color: #666;">Categorías</div>
                    </div>
                    
                    <div class="stat-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 36px; color: #e50009;"><i class="fas fa-list-ul"></i></div>
                        <div style="font-size: 24px; font-weight: 500;"><?php echo $stats['opciones']; ?></div>
                        <div style="color: #666;">Opciones Disponibles</div>
                    </div>
                    
                    <div class="stat-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 36px; color: #e50009;"><i class="fas fa-clock"></i></div>
                        <div style="font-size: 24px; font-weight: 500;"><?php echo $stats['plazos']; ?></div>
                        <div style="color: #666;">Plazos de Entrega</div>
                    </div>
                </div>
            </div>
            
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>Gestión de Datos</h3>
                </div>
                
                <div class="admin-actions" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <a href="view_plazos_entrega.php" class="action-card" style="display: block; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-decoration: none; color: #333;">
                        <div style="font-size: 36px; color: #e50009; margin-bottom: 10px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4 style="margin: 0 0 10px 0;">Plazos de Entrega</h4>
                        <p style="margin: 0; color: #666;">Administra los plazos de entrega disponibles para los productos.</p>
                    </a>
                    
                    <!-- Puedes agregar más tarjetas de acciones aquí -->
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Funciones para el manejo de archivos Excel
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar la subida de archivos
            initFileUpload();
            
            // Inicializar el formulario de Google Sheets
            initGoogleSheetsForm();
        });
        
        function initFileUpload() {
            const fileInput = document.getElementById('excelFile');
            const fileForm = document.getElementById('fileUploadForm');
            const fileName = document.getElementById('fileName');
            const progressBar = document.getElementById('progressBar');
            const progressBarFill = document.getElementById('progressBarFill');
            
            if (!fileInput || !fileForm) return;
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                    fileName.style.color = '#333';
                    console.log('Archivo seleccionado:', this.files[0]);
                } else {
                    fileName.textContent = 'Ningún archivo seleccionado';
                    fileName.style.color = '#999';
                }
            });
            
            fileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (fileInput.files.length === 0) {
                    alert('Por favor, selecciona un archivo Excel.');
                    return;
                }
                
                // Mostrar barra de progreso
                progressBar.style.display = 'block';
                progressBarFill.style.width = '0%';
                
                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                
                xhr.open('POST', 'upload_excel.php', true);
                
                // Mostrar porcentaje de carga
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBarFill.style.width = percentComplete + '%';
                        console.log('Progreso de subida:', percentComplete.toFixed(2) + '%');
                    }
                };
                
                // Cuando la carga termine
                xhr.onload = function() {
                    console.log('Status:', xhr.status);
                    console.log('Response Text:', xhr.responseText);
                    
                    // Detectar si la respuesta ya es HTML (redirección o carga exitosa)
                    if (xhr.responseText.trim().startsWith('<!DOCTYPE html>') || 
                        xhr.responseText.trim().startsWith('<html>')) {
                        console.log('Respuesta HTML detectada - Probablemente la operación fue exitosa');
                        
                        // Verificar si hay un mensaje de éxito en el HTML
                        if (xhr.responseText.includes('flash-message flash-success') || 
                            xhr.responseText.includes('ha sido procesado correctamente')) {
                            alert('¡Archivo procesado correctamente!');
                        } else {
                            alert('La operación parece haber sido completada, pero se recomienda verificar.');
                        }
                        
                        window.location.reload();
                        return;
                    }
                    
                    try {
                        // Intentar parsear la respuesta como JSON
                        const response = JSON.parse(xhr.responseText);
                        console.log('Respuesta parseada:', response);
                        
                        if (response.success) {
                            alert('¡Archivo procesado correctamente!');
                            window.location.reload();
                        } else {
                            // Mostrar mensaje de error con detalles
                            let errorMsg = response.message || 'Error desconocido';
                            
                            // Si hay información de depuración, mostrarla en la consola
                            if (response.debug) {
                                console.error('Detalles del error:', response.debug);
                            }
                            
                            // Si hay una traza de error, mostrarla
                            if (response.trace) {
                                console.error('Traza de error:', response.trace);
                                console.error('Archivo:', response.file, 'Línea:', response.line);
                            }
                            
                            alert('Error: ' + errorMsg);
                        }
                    } catch (e) {
                        // Error al parsear la respuesta
                        console.error('Error al parsear respuesta:', e);
                        console.error('Respuesta recibida:', xhr.responseText);
                        
                        // Intentar determinar si hay alguna pista en la respuesta
                        let errorHint = '';
                        if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                            errorHint = ' - Error de PHP detectado en la respuesta.';
                        } else if (xhr.responseText.includes('Warning')) {
                            errorHint = ' - Advertencia de PHP detectada en la respuesta.';
                        } else if (xhr.responseText.includes('Notice')) {
                            errorHint = ' - Aviso de PHP detectado en la respuesta.';
                        }
                        
                        alert('Error al procesar la respuesta del servidor.' + errorHint + ' Verifica la consola para más detalles.');
                    }
                    
                    // Ocultar barra de progreso
                    progressBar.style.display = 'none';
                };
                
                // En caso de error de comunicación
                xhr.onerror = function() {
                    console.error('Error de conexión');
                    alert('Error de conexión. Por favor, verifica tu conexión a internet.');
                    progressBar.style.display = 'none';
                };
                
                // Si se cancela la petición
                xhr.onabort = function() {
                    console.log('Petición cancelada');
                    progressBar.style.display = 'none';
                };
                
                // Enviar formulario
                console.log('Enviando formulario...');
                xhr.send(formData);
            });
        }
        
        function initGoogleSheetsForm() {
            const sheetsForm = document.getElementById('googleSheetsForm');
            
            if (!sheetsForm) return;
            
            sheetsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const sheetsUrl = document.getElementById('sheetsUrl').value;
                
                if (!sheetsUrl) {
                    alert('Por favor, ingresa la URL de Google Sheets.');
                    return;
                }
                
                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                
                xhr.open('POST', 'process_sheets.php', true);
                
                xhr.onload = function() {
                    console.log('Status:', xhr.status);
                    console.log('Response Text:', xhr.responseText);
                    
                    // Detectar si la respuesta ya es HTML (redirección o carga exitosa)
                    if (xhr.responseText.trim().startsWith('<!DOCTYPE html>') || 
                        xhr.responseText.trim().startsWith('<html>')) {
                        console.log('Respuesta HTML detectada - Probablemente la operación fue exitosa');
                        
                        // Verificar si hay un mensaje de éxito en el HTML
                        if (xhr.responseText.includes('flash-message flash-success') || 
                            xhr.responseText.includes('ha sido procesado correctamente')) {
                            alert('¡Google Sheets conectado correctamente!');
                        } else {
                            alert('La operación parece haber sido completada, pero se recomienda verificar.');
                        }
                        
                        window.location.reload();
                        return;
                    }
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Respuesta parseada:', response);
                        
                        if (response.success) {
                            alert('Google Sheets conectado correctamente.');
                            window.location.reload();
                        } else {
                            alert('Error: ' + (response.message || 'Ocurrió un error al conectar con Google Sheets.'));
                        }
                    } catch (e) {
                        console.error('Error al parsear respuesta:', e);
                        console.error('Respuesta recibida:', xhr.responseText);
                        alert('Error al procesar la respuesta del servidor. Verifica la consola para más detalles.');
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Error de conexión');
                    alert('Error de conexión. Por favor, verifica tu conexión a internet.');
                };
                
                xhr.send(formData);
            });
        }
    </script>
</body>
</html> 
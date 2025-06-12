<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Cargar configuración
$configPath = __DIR__ . '/../sistema/config.php';
if (!file_exists($configPath)) {
    die("Error: Archivo de configuración no encontrado");
}
require_once $configPath;

// Procesar subida de archivo
$mensaje = '';
$error = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $uploadedFile = $_FILES['excel_file'];
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = 'import_' . date('YmdHis') . '.' . $extension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
                // Aquí iría la lógica de importación
                $mensaje = "Archivo subido exitosamente. Procesando datos...";
                
                // Simulación de resultados
                $resultados = [
                    'total_filas' => 150,
                    'importadas' => 145,
                    'errores' => 5,
                    'tiempo' => '2.5 segundos'
                ];
            } else {
                $error = "Error al mover el archivo subido";
            }
        } else {
            $error = "Por favor sube un archivo Excel (.xlsx o .xls)";
        }
    } else {
        $error = "Error al subir el archivo";
    }
}

// Obtener historial de importaciones
$historial = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Excel - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        .dashboard-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-wrapper {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
        }

        /* Upload area */
        .upload-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl) * 2;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            border-color: var(--accent-primary);
            background: rgba(59, 130, 246, 0.05);
        }

        .upload-area.drag-over {
            border-color: var(--accent-primary);
            background: rgba(59, 130, 246, 0.1);
            transform: scale(1.02);
        }

        .upload-icon {
            width: 80px;
            height: 80px;
            background: var(--bg-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-lg);
            color: var(--accent-primary);
        }

        .upload-text {
            margin-bottom: var(--spacing-md);
        }

        .upload-title {
            font-size: var(--text-lg);
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .upload-subtitle {
            color: var(--text-secondary);
            font-size: var(--text-sm);
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        /* Instructions */
        .instructions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .instruction-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
        }

        .instruction-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .instruction-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
        }

        .instruction-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .instruction-list {
            list-style: none;
            padding: 0;
        }

        .instruction-list li {
            padding: var(--spacing-sm) 0;
            color: var(--text-secondary);
            font-size: var(--text-sm);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-sm);
        }

        .instruction-list li::before {
            content: '✓';
            color: var(--accent-success);
            font-weight: bold;
            margin-top: 2px;
        }

        /* Results */
        .results-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }

        .result-item {
            text-align: center;
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }

        .result-value {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: var(--spacing-xs);
        }

        .result-label {
            font-size: var(--text-xs);
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* History */
        .history-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .history-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }

        .history-table {
            width: 100%;
        }

        .history-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 150px;
            padding: var(--spacing-md) var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: background 0.2s ease;
        }

        .history-row:hover {
            background: var(--bg-hover);
        }

        .history-header-row {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Progress bar */
        .progress-container {
            margin-top: var(--spacing-lg);
            display: none;
        }

        .progress-container.active {
            display: block;
        }

        .progress-bar {
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: var(--spacing-sm);
        }

        .progress-fill {
            height: 100%;
            background: var(--accent-primary);
            width: 0;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            text-align: center;
        }

        /* Sample file link */
        .sample-file {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--accent-primary);
            text-decoration: none;
            font-size: var(--text-sm);
            margin-top: var(--spacing-md);
            transition: opacity 0.2s ease;
        }

        .sample-file:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 style="font-size: var(--text-xl); display: flex; align-items: center; gap: var(--spacing-sm);">
                    <span id="logo-icon"></span>
                    Panel Admin
                </h1>
            </div>
            
            <nav class="sidebar-menu">
                <a href="index.php" class="sidebar-item">
                    <span id="nav-dashboard-icon"></span>
                    <span>Dashboard</span>
                </a>
                <a href="gestionar_datos.php" class="sidebar-item">
                    <span id="nav-data-icon"></span>
                    <span>Gestionar Datos</span>
                </a>
                <a href="presupuestos.php" class="sidebar-item">
                    <span id="nav-quotes-icon"></span>
                    <span>Presupuestos</span>
                </a>
                <a href="importar.php" class="sidebar-item active">
                    <span id="nav-import-icon"></span>
                    <span>Importar Excel</span>
                </a>
                <a href="ajustar_precios.php" class="sidebar-item">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <div style="margin-top: auto; padding: var(--spacing-md);">
                    <a href="../cotizador.php" class="sidebar-item" target="_blank">
                        <span id="nav-calculator-icon"></span>
                        <span>Ir al Cotizador</span>
                    </a>
                    <a href="index.php?logout=1" class="sidebar-item" style="color: var(--accent-danger);">
                        <span id="nav-logout-icon"></span>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); padding: var(--spacing-lg) var(--spacing-xl);">
                <div class="header-grid" style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 class="header-title" style="font-size: var(--text-lg); font-weight: 600;">Importar Excel</h2>
                        <p class="header-subtitle" style="font-size: var(--text-sm); color: var(--text-secondary);">Actualiza los datos desde un archivo Excel</p>
                    </div>
                    
                    <div class="header-actions" style="display: flex; gap: var(--spacing-md);">
                        <a href="#" class="btn btn-secondary" download>
                            <span id="template-icon"></span>
                            Descargar Plantilla
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content-wrapper">
                <?php if ($mensaje): ?>
                <div class="alert alert-success fade-in">
                    <span id="success-icon"></span>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger fade-in">
                    <span id="error-icon"></span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Upload Section -->
                <div class="upload-section">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" id="uploadArea">
                            <input type="file" name="excel_file" id="fileInput" class="file-input" accept=".xlsx,.xls">
                            
                            <div class="upload-icon">
                                <span id="upload-icon"></span>
                            </div>
                            
                            <div class="upload-text">
                                <h3 class="upload-title">Arrastra tu archivo Excel aquí</h3>
                                <p class="upload-subtitle">o haz clic para seleccionar</p>
                            </div>
                            
                            <p class="text-small text-muted">Formatos soportados: .xlsx, .xls (máx. 10MB)</p>
                        </div>
                        
                        <div class="progress-container" id="progressContainer">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                            <p class="progress-text" id="progressText">Procesando archivo...</p>
                        </div>
                    </form>
                    
                    <a href="#" class="sample-file" download>
                        <span id="download-sample-icon"></span>
                        Descargar archivo de ejemplo
                    </a>
                </div>

                <!-- Instructions -->
                <div class="instructions-section">
                    <div class="instruction-card">
                        <div class="instruction-header">
                            <div class="instruction-icon">
                                <span id="format-icon"></span>
                            </div>
                            <h3 class="instruction-title">Formato del archivo</h3>
                        </div>
                        <ul class="instruction-list">
                            <li>Primera fila con nombres de columnas</li>
                            <li>Columnas: Categoría, Nombre, Precio 90, Precio 160, Precio 270</li>
                            <li>Sin filas vacías intermedias</li>
                            <li>Precios en formato numérico</li>
                        </ul>
                    </div>
                    
                    <div class="instruction-card">
                        <div class="instruction-header">
                            <div class="instruction-icon" style="color: var(--accent-warning);">
                                <span id="tips-icon"></span>
                            </div>
                            <h3 class="instruction-title">Recomendaciones</h3>
                        </div>
                        <ul class="instruction-list">
                            <li>Hacer backup antes de importar</li>
                            <li>Verificar nombres de categorías</li>
                            <li>Revisar formato de precios</li>
                            <li>Máximo 1000 filas por archivo</li>
                        </ul>
                    </div>
                    
                    <div class="instruction-card">
                        <div class="instruction-header">
                            <div class="instruction-icon" style="color: var(--accent-success);">
                                <span id="process-icon"></span>
                            </div>
                            <h3 class="instruction-title">Proceso</h3>
                        </div>
                        <ul class="instruction-list">
                            <li>Validación automática de datos</li>
                            <li>Creación de categorías nuevas</li>
                            <li>Actualización de precios existentes</li>
                            <li>Reporte detallado de cambios</li>
                        </ul>
                    </div>
                </div>

                <!-- Results (si hay) -->
                <?php if (!empty($resultados)): ?>
                <div class="results-section">
                    <h3 style="margin-bottom: var(--spacing-md);">
                        <span id="results-icon"></span>
                        Resultados de la importación
                    </h3>
                    
                    <div class="results-grid">
                        <div class="result-item">
                            <div class="result-value"><?php echo $resultados['total_filas']; ?></div>
                            <div class="result-label">Total filas</div>
                        </div>
                        <div class="result-item" style="background: rgba(16, 185, 129, 0.1);">
                            <div class="result-value" style="color: var(--accent-success);">
                                <?php echo $resultados['importadas']; ?>
                            </div>
                            <div class="result-label">Importadas</div>
                        </div>
                        <div class="result-item" style="background: rgba(239, 68, 68, 0.1);">
                            <div class="result-value" style="color: var(--accent-danger);">
                                <?php echo $resultados['errores']; ?>
                            </div>
                            <div class="result-label">Errores</div>
                        </div>
                        <div class="result-item">
                            <div class="result-value" style="font-size: var(--text-lg);">
                                <?php echo $resultados['tiempo']; ?>
                            </div>
                            <div class="result-label">Tiempo</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- History -->
                <div class="history-section">
                    <div class="history-header">
                        <h3>Historial de importaciones</h3>
                    </div>
                    
                    <div class="history-table">
                        <div class="history-row history-header-row">
                            <div>Archivo</div>
                            <div>Registros</div>
                            <div>Estado</div>
                            <div>Fecha</div>
                            <div>Acciones</div>
                        </div>
                        
                        <!-- Ejemplo de historial -->
                        <div class="history-row">
                            <div>
                                <strong>productos_enero_2024.xlsx</strong>
                                <div class="text-small text-muted">234 KB</div>
                            </div>
                            <div>156</div>
                            <div>
                                <span class="badge badge-success">Completado</span>
                            </div>
                            <div class="text-small">15/01/2024 14:30</div>
                            <div>
                                <button class="btn btn-sm btn-secondary">
                                    <span id="history-view-icon"></span>
                                    Ver detalle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Cargar iconos
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar
            document.getElementById('logo-icon').innerHTML = modernUI.getIcon('chart');
            document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
            document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
            document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('nav-import-icon').innerHTML = modernUI.getIcon('upload');
            document.getElementById('nav-prices-icon').innerHTML = modernUI.getIcon('dollar');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
            
            // Header
            document.getElementById('template-icon').innerHTML = modernUI.getIcon('download');
            
            // Upload
            document.getElementById('upload-icon').innerHTML = modernUI.getIcon('upload', 'icon-lg');
            document.getElementById('download-sample-icon').innerHTML = modernUI.getIcon('download');
            
            // Instructions
            document.getElementById('format-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('tips-icon').innerHTML = modernUI.getIcon('info');
            document.getElementById('process-icon').innerHTML = modernUI.getIcon('settings');
            
            // Alerts
            const successIcon = document.getElementById('success-icon');
            if (successIcon) successIcon.innerHTML = modernUI.getIcon('check');
            const errorIcon = document.getElementById('error-icon');
            if (errorIcon) errorIcon.innerHTML = modernUI.getIcon('error');
            
            // Results
            const resultsIcon = document.getElementById('results-icon');
            if (resultsIcon) resultsIcon.innerHTML = modernUI.getIcon('chart');
            
            // History
            document.getElementById('history-view-icon').innerHTML = modernUI.getIcon('eye');
        });

        // Drag and drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        // Prevenir comportamiento por defecto
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight al arrastrar
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.classList.add('drag-over');
        }

        function unhighlight(e) {
            uploadArea.classList.remove('drag-over');
        }

        // Manejar drop
        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            handleFiles(files);
        }

        // Manejar selección de archivo
        fileInput.addEventListener('change', function(e) {
            handleFiles(this.files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                const fileName = file.name;
                const fileExt = fileName.split('.').pop().toLowerCase();
                
                if (fileExt === 'xlsx' || fileExt === 'xls') {
                    // Mostrar progreso
                    progressContainer.classList.add('active');
                    
                    // Simular progreso
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += 10;
                        progressFill.style.width = progress + '%';
                        progressText.textContent = `Procesando archivo... ${progress}%`;
                        
                        if (progress >= 100) {
                            clearInterval(interval);
                            // Enviar formulario
                            uploadForm.submit();
                        }
                    }, 200);
                } else {
                    modernUI.showToast('Por favor selecciona un archivo Excel válido', 'error');
                }
            }
        }
    </script>
</body>
</html> 
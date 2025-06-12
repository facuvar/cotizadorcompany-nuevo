<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Presupuestos Online</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom styles -->
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .nav-link {
            font-weight: 500;
            color: #333;
        }
        .nav-link.active {
            color: #007bff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Panel de Administración</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="importDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Importar Datos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="importDropdown">
                            <li><a class="dropdown-item" href="import_salvaescaleras.php">Salvaescaleras</a></li>
                            <li><a class="dropdown-item" href="import_giracoches.php">Giracoches</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="validateDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Validar Datos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="validateDropdown">
                            <li><a class="dropdown-item" href="validate_salvaescaleras.php">Salvaescaleras</a></li>
                            <li><a class="dropdown-item" href="validate_giracoches.php">Giracoches</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="presupuestos.php">
                            <i class="fas fa-file-invoice-dollar"></i> Presupuestos
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="../cotizador.php" class="btn btn-outline-light me-2" target="_blank">Ver Cotizador</a>
                    <a href="../logout.php" class="btn btn-danger">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container"> 
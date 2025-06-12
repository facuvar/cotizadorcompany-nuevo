<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Destruir la sesión
session_destroy();

// Redireccionar al login
redirect(SITE_URL . '/admin/login.php');
?> 
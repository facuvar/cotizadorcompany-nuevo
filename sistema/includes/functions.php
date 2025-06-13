<?php
// Cargar configuración solo si no está cargada
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Función para generar un código único para el presupuesto
 */
function generateUniqueCode($length = 8) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Función para verificar si es una petición AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Función para mostrar mensajes flash
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Función para obtener mensaje flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Función para verificar si el administrador está logueado
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Función para redirigir al login si no está autenticado
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Función para redireccionar
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Función para obtener la IP del cliente
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * Función para formatear números en formato de pesos argentinos
 */
function formatNumber($number) {
    // Formato de pesos argentinos: puntos para miles y coma para decimales
    return number_format($number, 2, ',', '.');
}

/**
 * Procesar archivo Excel
 */
function processExcelFile($filePath) {
    // Aquí iría la lógica para procesar el archivo Excel
    // Esta es una implementación de ejemplo
    
    // En un caso real, usaríamos una biblioteca como PhpSpreadsheet
    // para leer el archivo Excel y procesar sus datos
    
    return [
        'success' => true,
        'message' => 'Archivo procesado correctamente'
    ];
}

/**
 * Función para limpiar cadenas de texto
 */
function cleanString($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}
?> 
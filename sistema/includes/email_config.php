<?php
/**
 * Configuración de correos electrónicos
 * Usar variables de entorno para mayor seguridad
 */

// Configuración de SendGrid usando variables de entorno
$email_config = [
    'sendgrid_api_key' => $_ENV['SENDGRID_API_KEY'] ?? '',
    'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@ascensorescompany.com',
    'from_name' => $_ENV['FROM_NAME'] ?? 'Sistema de Presupuestos Company',
    'notification_email' => $_ENV['NOTIFICATION_EMAIL'] ?? 'facundo@maberik.com'
];

return $email_config;
?> 
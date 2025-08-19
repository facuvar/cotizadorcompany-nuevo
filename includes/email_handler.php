<?php
/**
 * Manejador de correos electrónicos usando SendGrid
 */

class EmailHandler {
    private $api_key;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Cargar configuración desde archivo externo
        $config = include __DIR__ . '/email_config.php';
        
        // Configuración de SendGrid
        $this->api_key = $config['sendgrid_api_key'];
        $this->from_email = $config['from_email'];
        $this->from_name = $config['from_name'];
        
        // Validar que la API key esté configurada
        if (empty($this->api_key)) {
            error_log('Warning: SENDGRID_API_KEY no está configurada. Los correos no se enviarán.');
        }
    }
    
    /**
     * Enviar correo de notificación de nuevo presupuesto
     */
    public function enviarNotificacionPresupuesto($presupuesto_data) {
        try {
            // Cargar configuración
            $config = include __DIR__ . '/email_config.php';
            
            // Emails de destino (donde llegan las notificaciones) - soporta múltiples emails separados por coma
            $notification_emails = $config['notification_email'];
            
            // Crear el contenido del correo
            $subject = "Nuevo Presupuesto Generado - " . $presupuesto_data['numero_presupuesto'];
            
            $html_content = $this->crearHTMLNotificacion($presupuesto_data);
            $text_content = $this->crearTextoNotificacion($presupuesto_data);
            
            // Enviar el correo a múltiples destinatarios
            return $this->enviarCorreoMultiple($notification_emails, $subject, $html_content, $text_content);
            
        } catch (Exception $e) {
            error_log('Error enviando correo: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear contenido HTML para la notificación
     */
    private function crearHTMLNotificacion($data) {
        $fecha = date('d/m/Y H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background-color: #3b82f6; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .title { font-size: 24px; margin: 0; }
                .section { margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
                .label { font-weight: bold; color: #333; }
                .value { color: #666; margin-left: 10px; }
                .total { font-size: 18px; font-weight: bold; color: #28a745; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 class='title'>Nuevo Presupuesto Generado</h1>
                    <p>Se ha generado un nuevo presupuesto en el sistema</p>
                </div>
                
                <div class='section'>
                    <h3>Información del Presupuesto</h3>
                    <p><span class='label'>Número:</span><span class='value'>{$data['numero_presupuesto']}</span></p>
                    <p><span class='label'>Fecha:</span><span class='value'>$fecha</span></p>
                    <p><span class='label'>Total:</span><span class='value total'>$" . number_format($data['totals']['total'], 2, ',', '.') . "</span></p>
                </div>
                
                <div class='section'>
                    <h3>Datos del Cliente</h3>
                    <p><span class='label'>Nombre:</span><span class='value'>{$data['customer']['nombre']}</span></p>
                    <p><span class='label'>Email:</span><span class='value'>{$data['customer']['email']}</span></p>
                    <p><span class='label'>Teléfono:</span><span class='value'>{$data['customer']['telefono']}</span></p>";
                    
        if (!empty($data['customer']['empresa'])) {
            $html .= "<p><span class='label'>Empresa:</span><span class='value'>{$data['customer']['empresa']}</span></p>";
        }
        
        $html .= "
                </div>
                
                <div class='section'>
                    <h3>Resumen Financiero</h3>
                    <p><span class='label'>Subtotal:</span><span class='value'>$" . number_format($data['totals']['subtotal'], 2, ',', '.') . "</span></p>";
                    
        if ($data['totals']['descuento_porcentaje'] > 0) {
            $html .= "<p><span class='label'>Descuento ({$data['totals']['descuento_porcentaje']}%):</span><span class='value'>-$" . number_format($data['totals']['descuento_monto'], 2, ',', '.') . "</span></p>";
        }
        
        $html .= "
                    <p><span class='label total'>TOTAL:</span><span class='value total'>$" . number_format($data['totals']['total'], 2, ',', '.') . "</span></p>
                </div>
                
                <div class='footer'>
                    <p>Este correo fue generado automáticamente por el Sistema de Presupuestos Company.</p>
                    <p>Para ver el presupuesto completo, ingrese al panel de administración.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Crear contenido de texto plano para la notificación
     */
    private function crearTextoNotificacion($data) {
        $fecha = date('d/m/Y H:i:s');
        
        $texto = "NUEVO PRESUPUESTO GENERADO\n\n";
        $texto .= "Número: {$data['numero_presupuesto']}\n";
        $texto .= "Fecha: $fecha\n";
        $texto .= "Total: $" . number_format($data['totals']['total'], 2, ',', '.') . "\n\n";
        
        $texto .= "DATOS DEL CLIENTE:\n";
        $texto .= "Nombre: {$data['customer']['nombre']}\n";
        $texto .= "Email: {$data['customer']['email']}\n";
        $texto .= "Teléfono: {$data['customer']['telefono']}\n";
        
        if (!empty($data['customer']['empresa'])) {
            $texto .= "Empresa: {$data['customer']['empresa']}\n";
        }
        
        $texto .= "\nRESUMEN FINANCIERO:\n";
        $texto .= "Subtotal: $" . number_format($data['totals']['subtotal'], 2, ',', '.') . "\n";
        
        if ($data['totals']['descuento_porcentaje'] > 0) {
            $texto .= "Descuento ({$data['totals']['descuento_porcentaje']}%): -$" . number_format($data['totals']['descuento_monto'], 2, ',', '.') . "\n";
        }
        
        $texto .= "TOTAL: $" . number_format($data['totals']['total'], 2, ',', '.') . "\n\n";
        $texto .= "Este correo fue generado automáticamente por el Sistema de Presupuestos Company.";
        
        return $texto;
    }
    
    /**
     * Enviar correo a múltiples destinatarios
     */
    private function enviarCorreoMultiple($emails_string, $subject, $html_content, $text_content) {
        // Separar emails por coma y limpiar espacios
        $emails = array_map('trim', explode(',', $emails_string));
        
        $success_count = 0;
        $total_emails = count($emails);
        
        foreach ($emails as $email) {
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Determinar nombre basado en el email
                $name = $this->getNombreFromEmail($email);
                
                if ($this->enviarCorreo($email, $name, $subject, $html_content, $text_content)) {
                    $success_count++;
                    error_log("Correo enviado exitosamente a: $email");
                } else {
                    error_log("Error enviando correo a: $email");
                }
            } else {
                error_log("Email inválido: $email");
            }
        }
        
        error_log("Notificación enviada a $success_count de $total_emails destinatarios");
        return $success_count > 0; // Retorna true si al menos un email se envió
    }
    
    /**
     * Obtener nombre basado en el email
     */
    private function getNombreFromEmail($email) {
        $names = [
            'facundo@maberik.com' => 'Facundo',
            'victoria.tucci@ascensorescompany.com' => 'Victoria Tucci'
        ];
        
        return $names[$email] ?? 'Equipo Company';
    }
    
    /**
     * Enviar correo usando la API de SendGrid
     */
    private function enviarCorreo($to_email, $to_name, $subject, $html_content, $text_content) {
        // Verificar que la API key esté configurada
        if (empty($this->api_key)) {
            error_log('Error: No se puede enviar correo - SENDGRID_API_KEY no configurada');
            return false;
        }
        
        $url = 'https://api.sendgrid.com/v3/mail/send';
        
        $data = [
            'personalizations' => [[
                'to' => [[
                    'email' => $to_email,
                    'name' => $to_name
                ]]
            ]],
            'from' => [
                'email' => $this->from_email,
                'name' => $this->from_name
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $text_content
                ],
                [
                    'type' => 'text/html',
                    'value' => $html_content
                ]
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("Error cURL enviando correo: " . $curl_error);
            return false;
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            error_log("Correo enviado exitosamente. Código HTTP: " . $http_code);
            return true;
        } else {
            error_log("Error enviando correo. Código HTTP: " . $http_code . " Respuesta: " . $response);
            return false;
        }
    }
}
?> 
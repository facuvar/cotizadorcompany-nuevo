<?php
/**
 * VERIFICACIÓN PRE-DEPLOY RAILWAY
 * 
 * Este script verifica que todos los archivos y configuraciones
 * estén listos para funcionar en Railway.
 */

echo "=== VERIFICACIÓN PRE-DEPLOY RAILWAY ===\n\n";

$errors = [];
$warnings = [];
$checks = [];

// 1. VERIFICAR ARCHIVOS CLAVE
echo "📁 Verificando archivos clave...\n";

$archivos_clave = [
    'config.php' => 'Configuración principal',
    'cotizador-tabs.php' => 'Cotizador con sistema de tabs',
    'admin/gestionar_datos.php' => 'Panel admin actualizado',
    'api/get_categories_ordered.php' => 'API de categorías',
    'railway_migration_compatibilidad.php' => 'Script de migración'
];

foreach ($archivos_clave as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "   ✅ $archivo - $descripcion\n";
        $checks[] = "Archivo $archivo presente";
    } else {
        echo "   ❌ $archivo - $descripcion FALTANTE\n";
        $errors[] = "Archivo faltante: $archivo";
    }
}

echo "\n";

// 2. VERIFICAR CONFIGURACIÓN RAILWAY
echo "⚙️  Verificando configuración Railway...\n";

// Simular detección de Railway
$railway_vars = ['MYSQLHOST', 'MYSQLUSER', 'MYSQLPASSWORD', 'MYSQLDATABASE', 'MYSQLPORT'];
$railway_config_ok = true;

foreach ($railway_vars as $var) {
    if (isset($_ENV[$var])) {
        echo "   ✅ Variable \$$var configurada\n";
    } else {
        echo "   ⚠️  Variable \$$var no detectada (normal en local)\n";
        if (!isset($checks['railway_local'])) {
            $warnings[] = "Variables Railway no detectadas (ejecutándose en local)";
            $checks['railway_local'] = true;
        }
    }
}

// 3. VERIFICAR CONFIGURACIÓN DE DETECCIÓN AUTOMÁTICA
echo "\n🔧 Verificando detección automática de entorno...\n";

if (file_exists('config.php')) {
    $config_content = file_get_contents('config.php');
    
    if (strpos($config_content, "isset(\$_ENV['RAILWAY_ENVIRONMENT'])") !== false) {
        echo "   ✅ Detección automática de Railway configurada\n";
        $checks[] = "Detección automática de entorno";
    } else {
        echo "   ❌ Detección automática de Railway NO configurada\n";
        $errors[] = "Falta detección automática de Railway en config.php";
    }
    
    if (strpos($config_content, 'DB_HOST') !== false && strpos($config_content, 'DB_NAME') !== false) {
        echo "   ✅ Configuración de base de datos presente\n";
        $checks[] = "Configuración de BD presente";
    } else {
        echo "   ❌ Configuración de base de datos incompleta\n";
        $errors[] = "Configuración de BD incompleta";
    }
} else {
    $errors[] = "config.php no encontrado";
}

// 4. VERIFICAR NUEVAS FUNCIONALIDADES
echo "\n🆕 Verificando nuevas funcionalidades...\n";

// Verificar tabs en cotizador
if (file_exists('cotizador-tabs.php')) {
    $cotizador_content = file_get_contents('cotizador-tabs.php');
    
    if (strpos($cotizador_content, 'ascensoresTabs') !== false) {
        echo "   ✅ Sistema de tabs implementado\n";
        $checks[] = "Sistema de tabs";
    } else {
        echo "   ❌ Sistema de tabs NO implementado\n";
        $errors[] = "Sistema de tabs faltante";
    }
    
    if (strpos($cotizador_content, 'compatible_') !== false) {
        echo "   ✅ Filtrado por compatibilidad implementado\n";
        $checks[] = "Filtrado por compatibilidad";
    } else {
        echo "   ❌ Filtrado por compatibilidad NO implementado\n";
        $errors[] = "Filtrado por compatibilidad faltante";
    }
}

// Verificar admin actualizado
if (file_exists('admin/gestionar_datos.php')) {
    $admin_content = file_get_contents('admin/gestionar_datos.php');
    
    if (strpos($admin_content, 'compatibility-option') !== false) {
        echo "   ✅ Interface de compatibilidad en admin\n";
        $checks[] = "Interface admin actualizada";
    } else {
        echo "   ❌ Interface de compatibilidad NO implementada\n";
        $errors[] = "Interface admin no actualizada";
    }
    
    if (strpos($admin_content, 'compatible_electromecanicos') !== false) {
        echo "   ✅ Campos de compatibilidad en admin\n";
        $checks[] = "Campos de compatibilidad";
    } else {
        echo "   ❌ Campos de compatibilidad NO implementados\n";
        $errors[] = "Campos de compatibilidad faltantes en admin";
    }
}

// 5. VERIFICAR DEPENDENCIAS
echo "\n📦 Verificando dependencias y archivos críticos...\n";

$dependencias = [
    'assets/css/modern-dark-theme.css' => 'Estilos modernos',
    'assets/js/modern-icons.js' => 'Iconos modernos',
    'api/generate_quote.php' => 'Generación de presupuestos',
    'includes/db.php' => 'Clase de base de datos'
];

foreach ($dependencias as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "   ✅ $archivo\n";
    } else {
        echo "   ⚠️  $archivo faltante\n";
        $warnings[] = "Dependencia faltante: $archivo";
    }
}

// 6. RESUMEN FINAL
echo "\n" . str_repeat("═", 60) . "\n";
echo "📊 RESUMEN DE VERIFICACIÓN\n";
echo str_repeat("═", 60) . "\n";

echo "✅ Verificaciones exitosas: " . count($checks) . "\n";
foreach ($checks as $check) {
    echo "   • $check\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  Advertencias: " . count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ Errores críticos: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

echo "\n" . str_repeat("═", 60) . "\n";

if (empty($errors)) {
    echo "🎉 LISTO PARA RAILWAY\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ Todos los archivos están en orden\n";
    echo "✅ Configuración Railway correcta\n";
    echo "✅ Nuevas funcionalidades implementadas\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n📋 PASOS PARA DEPLOY:\n";
    echo "1. Hacer push de todos los archivos a Railway\n";
    echo "2. Ejecutar: railway_migration_compatibilidad.php\n";
    echo "3. Probar cotizador-tabs.php en producción\n";
    echo "4. Configurar adicionales desde admin si es necesario\n\n";
    
    exit(0);
} else {
    echo "🚫 NO LISTO PARA RAILWAY\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "❌ Se encontraron errores críticos\n";
    echo "🔧 Corrige los errores antes del deploy\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    exit(1);
}
?>

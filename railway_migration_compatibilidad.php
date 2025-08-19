<?php
/**
 * MIGRACIÓN PARA RAILWAY - CAMPOS DE COMPATIBILIDAD
 * 
 * Este script agrega ÚNICAMENTE los campos de compatibilidad para adicionales
 * SIN tocar precios, nombres ni ningún dato existente.
 * 
 * IMPORTANTE: Los precios y condiciones en Railway son los correctos.
 * Esta migración solo agrega funcionalidad, no modifica datos.
 */

echo "=== MIGRACIÓN RAILWAY - CAMPOS COMPATIBILIDAD ===\n";
echo "Ejecutando en entorno: " . (isset($_ENV['RAILWAY_ENVIRONMENT']) ? 'RAILWAY' : 'LOCAL') . "\n\n";

// Cargar configuración
require_once 'config.php';

// Verificar que estamos en Railway para esta migración crítica
if (!isset($_ENV['RAILWAY_ENVIRONMENT'])) {
    die("❌ STOP: Este script está diseñado específicamente para Railway.\n   Para local usa: admin/agregar_campos_compatibilidad.php\n");
}

echo "✅ Entorno Railway detectado correctamente\n";
echo "🔗 Base de datos: " . DB_NAME . " en " . DB_HOST . "\n\n";

try {
    // Conectar usando la configuración de Railway
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    echo "✅ Conexión exitosa a Railway MySQL\n\n";
    
    // 1. VERIFICAR ESTRUCTURA ACTUAL
    echo "🔍 Verificando estructura actual de la tabla 'opciones'...\n";
    
    $result = $conn->query("DESCRIBE opciones");
    $columnas_existentes = [];
    while ($row = $result->fetch_assoc()) {
        $columnas_existentes[] = $row['Field'];
    }
    
    echo "   Campos actuales: " . count($columnas_existentes) . "\n";
    
    // 2. VERIFICAR SI YA EXISTEN LOS CAMPOS DE COMPATIBILIDAD
    $campos_compatibilidad = [
        'compatible_electromecanicos',
        'compatible_gearless', 
        'compatible_hidraulicos',
        'compatible_domiciliarios',
        'compatible_montavehiculos',
        'compatible_montacargas',
        'compatible_salvaescaleras',
        'compatible_montaplatos',
        'compatible_escaleras'
    ];
    
    $campos_faltantes = array_diff($campos_compatibilidad, $columnas_existentes);
    
    if (empty($campos_faltantes)) {
        echo "ℹ️  MIGRACIÓN YA APLICADA: Todos los campos de compatibilidad ya existen.\n";
        echo "   No se requieren cambios.\n";
        exit(0);
    }
    
    echo "📊 Campos a agregar: " . count($campos_faltantes) . "\n";
    foreach ($campos_faltantes as $campo) {
        echo "   - $campo\n";
    }
    echo "\n";
    
    // 3. CREAR BACKUP DE SEGURIDAD DE LA ESTRUCTURA
    echo "💾 Creando backup de seguridad...\n";
    
    $backup_structure = "CREATE TABLE opciones_backup_" . date('Ymd_His') . " AS SELECT * FROM opciones LIMIT 0";
    if (!$conn->query($backup_structure)) {
        throw new Exception("Error creando backup de estructura: " . $conn->error);
    }
    echo "   ✅ Backup de estructura creado\n";
    
    // 4. AGREGAR CAMPOS DE COMPATIBILIDAD (UNO POR UNO PARA SEGURIDAD)
    echo "\n🔧 Agregando campos de compatibilidad...\n";
    
    $campos_agregados = 0;
    foreach ($campos_faltantes as $campo) {
        $sql = "ALTER TABLE opciones ADD COLUMN $campo TINYINT(1) DEFAULT 0 COMMENT 'Compatible con ascensores'";
        
        echo "   Agregando: $campo... ";
        if ($conn->query($sql)) {
            echo "✅\n";
            $campos_agregados++;
        } else {
            echo "❌ Error: " . $conn->error . "\n";
            throw new Exception("Error agregando campo $campo");
        }
    }
    
    echo "\n✅ Campos agregados exitosamente: $campos_agregados\n\n";
    
    // 5. MIGRAR DATOS EXISTENTES BASADO EN NOMBRES (SOLO ADICIONALES)
    echo "📋 Migrando datos de compatibilidad para adicionales existentes...\n";
    
    // Obtener solo adicionales (categoria_id = 2)
    $adicionales = $conn->query("SELECT id, nombre FROM opciones WHERE categoria_id = 2");
    
    if (!$adicionales) {
        throw new Exception("Error obteniendo adicionales: " . $conn->error);
    }
    
    $migrados = 0;
    $sin_clasificar = [];
    
    while ($adicional = $adicionales->fetch_assoc()) {
        $id = $adicional['id'];
        $nombre = strtolower($adicional['nombre']);
        
        $updates = [];
        
        // Lógica de clasificación basada en palabras clave
        if (strpos($nombre, 'electromecanico') !== false) {
            $updates[] = "compatible_electromecanicos = 1";
            $updates[] = "compatible_gearless = 1"; // Gearless usan mismos adicionales que electromecanicos
        }
        
        if (strpos($nombre, 'hidraulico') !== false) {
            $updates[] = "compatible_hidraulicos = 1";
        }
        
        if (strpos($nombre, 'montacargas') !== false) {
            $updates[] = "compatible_montacargas = 1";
        }
        
        if (strpos($nombre, 'salvaescaleras') !== false) {
            $updates[] = "compatible_salvaescaleras = 1";
        }
        
        if (!empty($updates)) {
            $sql_update = "UPDATE opciones SET " . implode(', ', $updates) . " WHERE id = $id";
            if ($conn->query($sql_update)) {
                $migrados++;
                echo "   ✅ " . substr($adicional['nombre'], 0, 50) . "...\n";
            } else {
                echo "   ❌ Error migrando ID $id: " . $conn->error . "\n";
            }
        } else {
            $sin_clasificar[] = $adicional['nombre'];
        }
    }
    
    echo "\n📊 RESUMEN DE MIGRACIÓN:\n";
    echo "   ✅ Adicionales migrados: $migrados\n";
    echo "   ⚠️  Sin clasificar: " . count($sin_clasificar) . "\n";
    
    if (!empty($sin_clasificar)) {
        echo "\n⚠️  ADICIONALES SIN CLASIFICAR (requieren configuración manual en admin):\n";
        foreach ($sin_clasificar as $nombre) {
            echo "   - " . substr($nombre, 0, 60) . "\n";
        }
    }
    
    // 6. VERIFICACIÓN FINAL
    echo "\n🔍 Verificación final...\n";
    
    $result = $conn->query("DESCRIBE opciones");
    $campos_finales = [];
    while ($row = $result->fetch_assoc()) {
        $campos_finales[] = $row['Field'];
    }
    
    $campos_verificacion = array_intersect($campos_compatibilidad, $campos_finales);
    
    if (count($campos_verificacion) === count($campos_compatibilidad)) {
        echo "   ✅ Todos los campos de compatibilidad están presentes\n";
    } else {
        throw new Exception("❌ Verificación fallida: faltan campos");
    }
    
    // 7. VERIFICAR INTEGRIDAD DE DATOS
    $count_opciones = $conn->query("SELECT COUNT(*) as total FROM opciones")->fetch_assoc()['total'];
    echo "   ✅ Total opciones en BD: $count_opciones\n";
    
    $count_adicionales = $conn->query("SELECT COUNT(*) as total FROM opciones WHERE categoria_id = 2")->fetch_assoc()['total'];
    echo "   ✅ Total adicionales: $count_adicionales\n";
    
    echo "\n🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ Base de datos Railway actualizada\n";
    echo "✅ Campos de compatibilidad agregados\n";
    echo "✅ Datos existentes preservados\n";
    echo "✅ Precios y condiciones intactos\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n📋 PRÓXIMOS PASOS:\n";
    echo "1. Verificar que cotizador-tabs.php funcione correctamente\n";
    echo "2. Configurar adicionales sin clasificar desde el admin\n";
    echo "3. Probar filtrado de adicionales con diferentes ascensores\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR EN MIGRACIÓN: " . $e->getMessage() . "\n";
    echo "\n🔄 ROLLBACK AUTOMÁTICO:\n";
    
    // Intentar rollback básico
    if (isset($campos_agregados) && $campos_agregados > 0) {
        echo "Removiendo campos agregados...\n";
        foreach ($campos_faltantes as $campo) {
            if (in_array($campo, $columnas_existentes)) continue;
            $rollback_sql = "ALTER TABLE opciones DROP COLUMN $campo";
            if ($conn->query($rollback_sql)) {
                echo "   ✅ Removido: $campo\n";
            } else {
                echo "   ❌ Error removiendo: $campo\n";
            }
        }
    }
    
    echo "\n💡 La base de datos debería estar en su estado original.\n";
    echo "   Contacta al desarrollador si persisten problemas.\n\n";
    exit(1);
}
?>

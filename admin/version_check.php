<?php
// Verificación rápida de versión
echo "VERSION ARCHIVO: 2024-09-15 HOTFIX3<br>";
echo "TIMESTAMP: " . date('Y-m-d H:i:s') . "<br>";
echo "ARCHIVO MODIFICADO: " . date('Y-m-d H:i:s', filemtime(__FILE__)) . "<br>";

// Test rápido de presupuestos sin autenticación para debug
try {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
        echo "Config cargado<br>";
    }
    
    if (file_exists(__DIR__ . '/../includes/db.php')) {
        require_once __DIR__ . '/../includes/db.php';
        echo "DB cargado<br>";
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $result = $conn->query("SELECT COUNT(*) as total FROM presupuestos");
        $total = $result->fetch_assoc()['total'];
        echo "TOTAL PRESUPUESTOS: $total<br>";
        
        $query = "SELECT * FROM presupuestos WHERE 1=1 ORDER BY created_at DESC LIMIT 3";
        $result = $conn->query($query);
        echo "QUERY EXITOSA: " . ($result ? "SÍ" : "NO") . "<br>";
        
        if ($result) {
            $count = 0;
            while ($row = $result->fetch_assoc()) {
                $count++;
                echo "Presupuesto $count: ID=" . $row['id'] . ", Cliente=" . ($row['cliente_nombre'] ?? 'N/A') . "<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>

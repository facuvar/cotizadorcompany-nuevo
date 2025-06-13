<?php
require_once 'config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

try {
    // Verificar que se haya enviado el ID del producto
    if (!isset($_GET['producto_id']) || empty($_GET['producto_id'])) {
        throw new Exception("ID de producto no proporcionado");
    }
    
    $productoId = intval($_GET['producto_id']);
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener adicionales para el producto
    $query = "SELECT a.* FROM adicionales a 
              INNER JOIN adicionales_productos ap ON a.id = ap.adicional_id 
              WHERE ap.producto_id = ? 
              ORDER BY a.orden ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $productoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adicionales = [];
    
    if ($result && $result->num_rows > 0) {
        while ($adicional = $result->fetch_assoc()) {
            $adicionales[] = $adicional;
        }
    }
    
    echo json_encode($adicionales);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
require_once 'config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

try {
    // Verificar que se haya enviado el ID de la categoría/producto
    if (!isset($_GET['producto_id']) || empty($_GET['producto_id'])) {
        throw new Exception("ID de categoría no proporcionado");
    }
    
    $categoriaId = intval($_GET['producto_id']);
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener opciones para la categoría
    $query = "SELECT * FROM opciones WHERE categoria_id = ? ORDER BY orden ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $categoriaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $opciones = [];
    
    if ($result && $result->num_rows > 0) {
        while ($opcion = $result->fetch_assoc()) {
            $opciones[] = $opcion;
        }
    }
    
    echo json_encode($opciones);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

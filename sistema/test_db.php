<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Estado de la Base de Datos</h2>";
    
    // Verificar si existe la base de datos
    $result = $conn->query("SELECT DATABASE()");
    $dbName = $result->fetch_row()[0];
    echo "<p>Base de datos actual: " . ($dbName ?: "No seleccionada") . "</p>";
    
    // Verificar tablas necesarias
    $tables = ['categorias', 'opciones', 'fuente_datos'];
    echo "<h3>Estado de las tablas:</h3>";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        echo "<p>Tabla '$table': " . ($result->num_rows > 0 ? "Existe" : "No existe") . "</p>";
        
        if ($result->num_rows > 0) {
            // Contar registros
            $count = $conn->query("SELECT COUNT(*) FROM $table")->fetch_row()[0];
            echo "<p>- Número de registros: $count</p>";
            
            // Mostrar algunos registros de ejemplo
            $records = $conn->query("SELECT * FROM $table LIMIT 3");
            if ($records->num_rows > 0) {
                echo "<p>- Primeros registros:</p><pre>";
                while ($row = $records->fetch_assoc()) {
                    print_r($row);
                }
                echo "</pre>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 
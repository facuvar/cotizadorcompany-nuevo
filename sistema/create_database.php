<?php
require_once 'config.php';

try {
    // Conectar sin seleccionar base de datos
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    echo "<h2>Creación de Base de Datos</h2>";
    
    // Crear base de datos si no existe
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql)) {
        echo "<p>Base de datos creada o ya existente: " . DB_NAME . "</p>";
    } else {
        throw new Exception("Error al crear la base de datos: " . $conn->error);
    }
    
    // Seleccionar la base de datos
    $conn->select_db(DB_NAME);
    
    // Crear tabla categorias
    $sql = "CREATE TABLE IF NOT EXISTS categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        orden INT DEFAULT 0
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Tabla 'categorias' creada o ya existente</p>";
    } else {
        throw new Exception("Error al crear tabla categorias: " . $conn->error);
    }
    
    // Crear tabla opciones
    $sql = "CREATE TABLE IF NOT EXISTS opciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        es_obligatorio TINYINT(1) NOT NULL DEFAULT 0,
        orden INT DEFAULT 0,
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Tabla 'opciones' creada o ya existente</p>";
    } else {
        throw new Exception("Error al crear tabla opciones: " . $conn->error);
    }
    
    // Crear tabla fuente_datos
    $sql = "CREATE TABLE IF NOT EXISTS fuente_datos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        url TEXT,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Tabla 'fuente_datos' creada o ya existente</p>";
    } else {
        throw new Exception("Error al crear tabla fuente_datos: " . $conn->error);
    }
    
    // Crear tabla presupuestos
    $sql = "CREATE TABLE IF NOT EXISTS presupuestos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre_cliente VARCHAR(100) NOT NULL,
        email_cliente VARCHAR(100) NOT NULL,
        telefono_cliente VARCHAR(20),
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Tabla 'presupuestos' creada o ya existente</p>";
    } else {
        throw new Exception("Error al crear tabla presupuestos: " . $conn->error);
    }
    
    // Crear tabla presupuesto_detalle
    $sql = "CREATE TABLE IF NOT EXISTS presupuesto_detalle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        presupuesto_id INT NOT NULL,
        opcion_id INT NOT NULL,
        precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id),
        FOREIGN KEY (opcion_id) REFERENCES opciones(id)
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Tabla 'presupuesto_detalle' creada o ya existente</p>";
    } else {
        throw new Exception("Error al crear tabla presupuesto_detalle: " . $conn->error);
    }
    
    echo "<p>¡Base de datos inicializada correctamente!</p>";
    echo "<p><a href='test_db.php'>Ver estado de la base de datos</a></p>";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 
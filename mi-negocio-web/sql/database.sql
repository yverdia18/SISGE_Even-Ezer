-- Eliminar tablas si existen (para evitar conflictos)
DROP TABLE IF EXISTS venta_detalles;
DROP TABLE IF EXISTS compra_detalles;
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS compras;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS mi_negocio;
USE mi_negocio;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario') DEFAULT 'usuario',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de categorias
CREATE TABLE categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de productos
CREATE TABLE productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    categoria_id INT,
    precio_compra DECIMAL(10,2) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    stock_minimo INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

-- Tabla de clientes
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    direccion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de ventas
CREATE TABLE ventas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente','pagada','cancelada') DEFAULT 'pagada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

-- Tabla de detalles de venta
CREATE TABLE venta_detalles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Tabla de compras
CREATE TABLE compras (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proveedor VARCHAR(200),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de detalles de compra
CREATE TABLE compra_detalles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    compra_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Insertar usuario admin (contraseña: admin123)
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES 
('Administrador', 'admin@minegocio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Datos de ejemplo
INSERT INTO categorias (nombre, descripcion) VALUES
('Panadería', 'Productos de panadería y repostería'),
('Bebidas', 'Bebidas frías y calientes'),
('Snacks', 'Snacks y botanas'),
('Lácteos', 'Productos lácteos');

INSERT INTO productos (nombre, descripcion, categoria_id, precio_compra, precio_venta, stock) VALUES
('Pan Artesanal', 'Pan horneado con masa madre', 1, 1.50, 3.99, 45),
('Torta de Chocolate', 'Deliciosa torta de chocolate con relleno de crema', 1, 5.00, 15.99, 12),
('Galletas surtidas', 'Pack de galletas con sabores variados', 1, 3.00, 8.50, 30),
('Café Americano', 'Café de especialidad', 2, 1.00, 3.50, 50),
('Jugo Natural', 'Jugo de naranja recién exprimido', 2, 0.80, 4.00, 25),
('Chips de Maíz', 'Snack crujiente de maíz', 3, 0.60, 2.00, 60),
('Queso Fresco', 'Queso fresco de vaca', 4, 2.50, 6.00, 20);

INSERT INTO clientes (nombre, email, telefono) VALUES
('Cliente Regular', 'cliente@email.com', '555-1234'),
('María González', 'maria@gmail.com', '555-5678'),
('Juan Pérez', 'juan@yahoo.com', '555-9012');

INSERT INTO ventas (cliente_id, total) VALUES
(1, 25.97),
(2, 19.99),
(NULL, 8.50);

INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 3, 3.99, 11.97),
(1, 2, 1, 15.99, 15.99),
(2, 2, 1, 15.99, 15.99),
(3, 3, 1, 8.50, 8.50);
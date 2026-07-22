-- Actualizar tabla clientes con más campos
ALTER TABLE clientes 
ADD COLUMN apellidos VARCHAR(200) AFTER nombre,
ADD COLUMN telefono2 VARCHAR(20),
ADD COLUMN cuenta_bancaria VARCHAR(100),
ADD COLUMN banco VARCHAR(100),
ADD COLUMN area_trabajo VARCHAR(100),
ADD COLUMN puesto VARCHAR(100),
ADD COLUMN direccion2 TEXT,
ADD COLUMN ciudad VARCHAR(100),
ADD COLUMN estado VARCHAR(100),
ADD COLUMN codigo_postal VARCHAR(20),
ADD COLUMN notas TEXT,
ADD COLUMN fecha_nacimiento DATE,
ADD COLUMN redes_sociales TEXT,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Si no existe, crear tabla de historial de clientes
CREATE TABLE IF NOT EXISTS clientes_historial (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('venta', 'contacto', 'nota', 'seguimiento') DEFAULT 'contacto',
    descripcion TEXT,
    usuario_id INT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Agregar algunos campos de ejemplo
INSERT INTO clientes (nombre, apellidos, email, telefono, cuenta_bancaria, banco, area_trabajo, ciudad) VALUES
('María', 'González Pérez', 'maria.gonzalez@email.com', '55 1234 5678', '0123456789', 'BBVA', 'Contabilidad', 'Ciudad de México'),
('Juan', 'Martínez López', 'juan.martinez@email.com', '55 8765 4321', '9876543210', 'Santander', 'Administración', 'Guadalajara'),
('Ana', 'Rodríguez Sánchez', 'ana.rodriguez@email.com', '55 4567 8901', '4567890123', 'Citibanamex', 'Ventas', 'Monterrey');
-- Selecciona la base de datos creada por MariaDB.
USE novaharvest;

-- Productos que ofrecen los productores.
CREATE TABLE excedentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto VARCHAR(100) NOT NULL,
    productor VARCHAR(100) NOT NULL,
    pueblo VARCHAR(100) NOT NULL,
    kilos INT NOT NULL DEFAULT 0,
    precio_kg DECIMAL(6,2) NOT NULL
);

-- Solicitudes recibidas desde el formulario.
CREATE TABLE avisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    producto VARCHAR(100) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Datos iniciales del listado.
INSERT INTO excedentes (producto, productor, pueblo, kilos, precio_kg)
VALUES
    ('Cereza Picota', 'Huerta Los Castaños', 'Cabezuela del Valle', 223, 2.24),
    ('Castaña', 'Finca El Rebollar', 'Rebollar', 150, 2.10),
    ('Higo', 'Huerto La Garganta', 'Navaconcejo', 35, 2.80),
    ('Tomate de colgar', 'Hermanos Sánchez', 'Jerte', 80, 1.10),
    ('Calabacín', 'Huerta Los Castaños', 'Cabezuela del Valle', 0, 0.90);

-- La web puede consultar datos y registrar avisos.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'novaharvest_app'@'%';
GRANT SELECT, INSERT ON novaharvest.* TO 'novaharvest_app'@'%';
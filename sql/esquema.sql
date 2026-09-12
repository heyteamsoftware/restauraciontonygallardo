-- =============================================================
--  Cafetería del instituto — esquema de base de datos
--  Ejecutar una sola vez desde phpMyAdmin (panel de InfinityFree).
--  Compatible con MySQL 5.7 / MariaDB.
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
--  Productos que se pueden pedir
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100)  NOT NULL,
  categoria    VARCHAR(50)   NOT NULL,
  precio       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  stock        INT           NULL, -- NULL = sin límite (stock ilimitado)
  icono        VARCHAR(60)   NULL,
  activo       TINYINT(1)    NOT NULL DEFAULT 1,
  orden        INT           NOT NULL DEFAULT 0,
  creado_en    DATETIME      NOT NULL,
  INDEX idx_productos_activo (activo, categoria, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  Ingredientes (pestaña "Stock"): pan, embutidos, etc. No se venden
--  directamente, pero limitan cuántos productos se pueden preparar.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ingredientes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100)  NOT NULL,
  stock        INT           NULL, -- NULL = sin límite
  creado_en    DATETIME      NOT NULL,
  UNIQUE KEY uq_ingredientes_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  Receta de cada producto: qué ingredientes usa y cuántas unidades de
--  cada uno por unidad vendida (p. ej. Bocadillo de lomo = 1 Pan de
--  bocadillo + 2 Lomo). Un producto sin filas aquí no usa ingredientes;
--  su stock es directamente productos.stock.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS producto_ingredientes (
  producto_id     INT NOT NULL,
  ingrediente_id  INT NOT NULL,
  cantidad        INT NOT NULL DEFAULT 1,
  PRIMARY KEY (producto_id, ingrediente_id),
  CONSTRAINT fk_pi_producto    FOREIGN KEY (producto_id)    REFERENCES productos(id)    ON DELETE CASCADE,
  CONSTRAINT fk_pi_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  Pedidos
--  estado: pendiente -> en_curso -> completado  (o cancelado)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  codigo         VARCHAR(12)   NOT NULL,
  persona_id     VARCHAR(20)   NOT NULL, -- ID interno de includes/personas_autorizadas.php; NUNCA un DNI
  nombre         VARCHAR(120)  NOT NULL,
  email          VARCHAR(150)  NOT NULL,
  estado         ENUM('pendiente','en_curso','completado','archivado','cancelado')
                               NOT NULL DEFAULT 'pendiente',
  total          DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  notas          VARCHAR(255)  NULL,
  aviso_enviado  TINYINT(1)    NOT NULL DEFAULT 0,
  registrado_hoja TINYINT(1)   NOT NULL DEFAULT 0,
  fue_completado TINYINT(1)    NOT NULL DEFAULT 0,
  stock_repuesto TINYINT(1)    NOT NULL DEFAULT 0, -- 1 si se canceló y ya se devolvió el stock
  creado_en      DATETIME      NOT NULL,
  actualizado_en DATETIME      NOT NULL,
  UNIQUE KEY uq_pedidos_codigo (codigo),
  INDEX idx_pedidos_estado (estado, creado_en),
  INDEX idx_pedidos_fecha (creado_en),
  INDEX idx_pedidos_persona (persona_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  Líneas de cada pedido
--  Se guarda copia del nombre y del precio: si mañana cambia el
--  producto, el pedido antiguo sigue reflejando lo que se pidió.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedido_lineas (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id       INT           NOT NULL,
  producto_id     INT           NULL,
  nombre_producto VARCHAR(100)  NOT NULL,
  precio_unitario DECIMAL(5,2)  NOT NULL,
  cantidad        INT           NOT NULL,
  INDEX idx_lineas_pedido (pedido_id),
  CONSTRAINT fk_lineas_pedido  FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE,
  CONSTRAINT fk_lineas_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  Catálogo inicial
--  TODO: ajustar los precios a los reales de la cafetería.
-- -------------------------------------------------------------
INSERT INTO productos (nombre, categoria, precio, icono, activo, orden, creado_en) VALUES
  ('Bocata de embutido',  'Bocatas',    2.50, '01-bocata-embutido.png', 1, 1, NOW()),
  ('Bocata de pollo',     'Bocatas',    3.00, '02-bocata-pollo.png',    1, 2, NOW()),
  ('Bocata de lomo',      'Bocatas',    3.00, '03-bocata-lomo.png',     1, 3, NOW()),
  ('Croasant mixto',      'Croasants',  2.20, '04-croasant-mixto.png',  1, 4, NOW()),
  ('Croasant vegetal',    'Croasants',  2.20, '05-croasant-vegetal.png',1, 5, NOW()),
  ('Sandwich mixto',      'Sandwiches', 2.00, '06-sandwich-mixto.png',  1, 6, NOW()),
  ('Sandwich vegetal',    'Sandwiches', 2.00, '07-sandwich-vegetal.png',1, 7, NOW());

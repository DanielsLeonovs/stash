-- stash db shema + testa dati (katra tabula max 10 ieraksti, bez garumzimem)

CREATE DATABASE IF NOT EXISTS stash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stash;

DROP TABLE IF EXISTS stock_log;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    login    VARCHAR(30) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role     ENUM('admin','worker','sorter','') NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(100) NOT NULL,
    contact VARCHAR(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    category    VARCHAR(50) NOT NULL DEFAULT '',
    price       DECIMAL(10,2) NOT NULL DEFAULT 0,
    qty         INT NOT NULL DEFAULT 0,
    location    VARCHAR(30) NOT NULL DEFAULT '',
    supplier_id INT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE orders (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    product    VARCHAR(100) NOT NULL,
    qty        INT NOT NULL,
    client     VARCHAR(100) NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'Jauns',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB;

-- stock_log = precu kustibas vesture (piegade / izsniegsana / parvietosana)
CREATE TABLE stock_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    product    VARCHAR(100) NOT NULL,
    action     VARCHAR(20) NOT NULL,
    qty_change INT NOT NULL DEFAULT 0,
    from_loc   VARCHAR(100) NOT NULL DEFAULT '',
    to_loc     VARCHAR(100) NOT NULL DEFAULT '',
    by_user    VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB;

INSERT INTO users (login, password, role) VALUES
('admin',   '$2y$10$i3eKoAame6MhLmiDcalci.NyQbmZhUqU7/mWt98TiZ3vqsDU4FU9u', 'admin'),
('janis',   '$2y$10$iwMyjd21XA3iCD0bylnmaunbUc6x9PPNMRe1IJz9nntSJyjKm9zLG', 'worker'),
('anna',    '$2y$10$iwMyjd21XA3iCD0bylnmaunbUc6x9PPNMRe1IJz9nntSJyjKm9zLG', 'sorter');

INSERT INTO suppliers (name, contact) VALUES
('SIA VoltMax',       'info@voltmax.lv'),
('SIA TeksPro',       '+371 20123456'),
('ToolNord OU',       'sales@toolnord.ee'),
('SIA MetalGroup',    'kontakti@metalgroup.lv');

INSERT INTO products (code, name, category, price, qty, location, supplier_id) VALUES
('EL-001', 'LED spuldze 9W',          'Elektrika',   2.50, 120, 'A-1-1', 1),
('EL-002', 'Pagarinatajs 3m',         'Elektrika',   7.90,  35, 'A-1-2', 1),
('EL-003', 'Kabelis 2x1.5 10m',       'Elektrika',   5.20,  60, 'A-1-3', 1),
('TK-001', 'Krekls M izmers',         'Apgerbs',     6.00,  80, 'B-2-1', 2),
('TK-002', 'Darba cimdi',             'Apgerbs',     3.20,  40, 'B-2-2', 2),
('TK-003', 'Jaka L izmers',           'Apgerbs',    22.00,  25, 'B-2-3', 2),
('IN-001', 'Skruvgriezu komplekts',   'Instrumenti',14.50,  18, 'C-3-1', 3),
('IN-002', 'Amurs 500g',              'Instrumenti', 9.75,  22, 'C-3-2', 3),
('IN-003', 'Merlente 5m',             'Instrumenti', 4.40,  15, 'C-3-3', 3),
('IN-004', 'Urbjmasina akumulatora',  'Instrumenti',65.00,   8, 'C-3-4', 4);

INSERT INTO orders (product_id, product, qty, client, status, created_at) VALUES
(1,  'LED spuldze 9W',          10, 'SIA Centrs',          'Jauns',     '2026-11-10 09:15:00'),
(4,  'Krekls M izmers',          5, 'Peteris Kalnins',     'Komplektē', '2026-11-12 11:30:00'),
(7,  'Skruvgriezu komplekts',    2, 'SIA Marupe',          'Nosūtīts',  '2026-11-13 14:05:00'),
(2,  'Pagarinatajs 3m',          8, 'SIA Noliktava Plus',  'Jauns',     '2026-11-14 10:40:00'),
(10, 'Urbjmasina akumulatora',   1, 'SIA Centrs',          'Piegādāts', '2026-11-15 13:20:00'),
(6,  'Jaka L izmers',            3, 'Peteris Kalnins',     'Nosūtīts',  '2026-11-16 15:50:00');

INSERT INTO stock_log (product_id, product, action, qty_change, from_loc, to_loc, by_user, created_at) VALUES
(1,  'LED spuldze 9W',          'piegāde',       100, 'SIA VoltMax', 'A-1-1', 'admin', '2026-11-01 08:00:00'),
(8,  'Amurs 500g',              'pārvietošana',    0, 'C-3-1',       'C-3-2', 'anna',  '2026-11-05 10:20:00'),
(1,  'LED spuldze 9W',          'izsniegšana',   -10, '',            '',      'janis', '2026-11-10 09:15:00'),
(10, 'Urbjmasina akumulatora',  'piegāde',          8, 'SIA MetalGroup', 'C-3-4', 'admin', '2026-11-14 09:00:00'),
(7,  'Skruvgriezu komplekts',   'izsniegšana',     -2, '',            '',      'janis', '2026-11-13 14:05:00'),
(4,  'Krekls M izmers',         'izsniegšana',     -5, '',            '',      'anna',  '2026-11-12 11:30:00');

-- =============================================================================
-- blissim.sql: e-commerce database (customers, products, orders, order lines)
-- Engine: MySQL 8.0.16+ (CHECK constraints are enforced from this version)
-- Run:    mysql -u root -p < blissim.sql
-- Assumptions:
--   * amounts in cents (integers) with an ISO 4217 currency, EUR by default
--   * every order counts (no order status)
--   * a product appears at most once per order, the quantity covers the rest
--   * "last 7 days" = rolling window from the time the query runs
--   * products and customers are deactivated (is_active), never deleted
-- =============================================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS blissim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blissim;

-- -----------------------------------------------------------------------------
-- 1. SCHEMA
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS order_lines;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;

CREATE TABLE customers (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    is_active  BOOLEAN      NOT NULL DEFAULT TRUE, -- closed account: deactivated, never deleted
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email), -- case-insensitive (collation)
    CONSTRAINT chk_customers_names CHECK (CHAR_LENGTH(TRIM(first_name)) > 0 AND CHAR_LENGTH(TRIM(last_name)) > 0),
    CONSTRAINT chk_customers_email CHECK (CHAR_LENGTH(TRIM(email)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    description TEXT         NULL,
    price_cents INT UNSIGNED NOT NULL,                                          -- current price, can change
    currency    CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'EUR',
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,                            -- off sale: deactivated, never deleted
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_products_name     CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_products_currency CHECK (currency REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    currency    CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'EUR', -- frozen at purchase, applies to all lines
    ordered_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT, -- keep order history
    CONSTRAINT chk_orders_currency CHECK (currency REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_lines (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id         INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    unit_price_cents INT UNSIGNED NOT NULL, -- price paid, copied from the product at purchase, never changes
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_lines_order_product (order_id, product_id),
    CONSTRAINT fk_order_lines_order
        FOREIGN KEY (order_id)   REFERENCES orders (id)   ON DELETE CASCADE,  -- a line belongs to its order
    CONSTRAINT fk_order_lines_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT, -- a sold product is kept
    CONSTRAINT chk_order_lines_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_orders_ordered_at   ON orders (ordered_at);      -- query 2
CREATE INDEX idx_order_lines_product ON order_lines (product_id); -- queries 1 and 2

-- -----------------------------------------------------------------------------
-- 2. SEED (currency defaults to EUR)
-- -----------------------------------------------------------------------------
-- Customers 1 and 3 share the same name. Customer 7 never ordered and closed their account.
INSERT INTO customers (id, first_name, last_name, email) VALUES
    (1, 'Sophie',  'Martin',  'sophie.martin@example.com'),
    (2, 'Karim',   'Benali',  'karim.benali@example.com'),
    (3, 'Sophie',  'Martin',  'sophie.martin.lyon@example.com'),
    (4, 'Léa',     'Dubois',  'lea.dubois@example.com'),
    (5, 'Yanis',   'Diallo',  'yanis.diallo@example.com'),
    (6, 'Camille', 'Bernard', 'camille.bernard@example.com'),
    (7, 'Hugo',    'Petit',   'hugo.petit@example.com');
UPDATE customers SET is_active = FALSE WHERE id = 7;

-- Current prices. Product 1 went from 2290 to 2490, product 4 from 1790 to 1990.
-- Product 6 was sold, then taken off sale. Product 8 was never sold.
INSERT INTO products (id, name, description, price_cents) VALUES
    (1, 'Sérum visage hydratant 30 ml', 'Sérum à l''acide hyaluronique', 2490),
    (2, 'Crème mains karité',           NULL,                             990),
    (3, 'Mascara volume',               NULL,                            1790),
    (4, 'Huile sèche corps',            NULL,                            1990),
    (5, 'Eau micellaire 200 ml',        NULL,                             650),
    (6, 'Masque tissu x5',              NULL,                            1200),
    (7, 'Vernis à ongles rouge',        NULL,                             790),
    (8, 'Coffret découverte',           NULL,                            2990);
UPDATE products SET is_active = FALSE WHERE id = 6;

-- Dates relative to NOW(): orders 5 to 9 are within the last 7 days, order 4 is just outside.
INSERT INTO orders (id, customer_id, ordered_at) VALUES
    (1, 1, NOW() - INTERVAL 40 DAY),
    (2, 2, NOW() - INTERVAL 20 DAY),
    (3, 3, NOW() - INTERVAL 9 DAY),
    (4, 4, NOW() - INTERVAL 8 DAY),
    (5, 1, NOW() - INTERVAL 6 DAY),
    (6, 5, NOW() - INTERVAL 3 DAY),
    (7, 6, NOW() - INTERVAL 2 DAY),
    (8, 3, NOW() - INTERVAL 1 DAY),
    (9, 4, NOW() - INTERVAL 2 HOUR);

-- Orders 1 and 2 keep the old price of product 1 (2290).
INSERT INTO order_lines (order_id, product_id, quantity, unit_price_cents) VALUES
    (1, 1, 1, 2290), (1, 4, 1, 1790),
    (2, 1, 2, 2290), (2, 5, 3,  650),
    (3, 1, 1, 2490), (3, 7, 2,  790),
    (4, 3, 5, 1790),
    (5, 1, 1, 2490), (5, 2, 2,  990),
    (6, 2, 4,  990), (6, 4, 1, 1990), (6, 6, 1, 1200),
    (7, 4, 3, 1990), (7, 3, 1, 1790),
    (8, 2, 1,  990), (8, 5, 6,  650),
    (9, 5, 2,  650), (9, 7, 1,  790);

-- -----------------------------------------------------------------------------
-- 3. QUERIES
-- -----------------------------------------------------------------------------
-- Query 1: customers who ordered product 1.
-- Expected: Karim Benali, Sophie Martin, Sophie Martin (two different customers).
SELECT c.first_name, c.last_name
FROM customers c
WHERE EXISTS (
    SELECT 1
    FROM orders o
    JOIN order_lines ol ON ol.order_id = o.id
    WHERE o.customer_id = c.id
      AND ol.product_id = 1
)
ORDER BY c.last_name, c.first_name;

-- Query 2: products sold during the last 7 days, by total quantity.
-- Expected: Eau micellaire (8), Crème mains (7), Huile sèche (4), then 4 products at 1.
SELECT p.name, SUM(ol.quantity) AS total_quantity
FROM order_lines ol
JOIN orders   o ON o.id = ol.order_id
JOIN products p ON p.id = ol.product_id
WHERE o.ordered_at >= NOW() - INTERVAL 7 DAY
GROUP BY p.id, p.name
ORDER BY total_quantity DESC, p.name ASC;

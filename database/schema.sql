CREATE DATABASE IF NOT EXISTS gatee_shop
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gatee_shop;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    description_ar VARCHAR(255) NOT NULL,
    price DECIMAL(10,3) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BHD',
    image VARCHAR(100) NULL COMMENT 'Bootstrap Icons class name',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    mobile_code VARCHAR(5) NOT NULL DEFAULT '973',
    mobile VARCHAR(15) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_contact (email, mobile_code, mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    order_reference CHAR(19) NOT NULL COMMENT 'BAB-YYYYMMDD-XXXXXX',
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,3) NOT NULL,
    vat_amount DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    amount DECIMAL(10,3) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BHD',
    language CHAR(2) NOT NULL DEFAULT 'en',
    gateway_payment_id VARCHAR(40) NULL,
    gateway_payment_code VARCHAR(60) NULL,
    payment_url VARCHAR(255) NULL,
    status ENUM('initiated','pending','paid','failed','cancelled') NOT NULL DEFAULT 'initiated',
    status_message VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_reference (order_reference),
    KEY idx_payments_status (status),
    KEY idx_payments_created (created_at),
    KEY idx_payments_gateway (gateway_payment_id),
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_payments_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NOT NULL,
    fingerprint CHAR(40) NOT NULL COMMENT 'gateway transaction id, or a hash of the payload when it has none',
    gateway_transaction_id VARCHAR(60) NULL,
    status VARCHAR(30) NOT NULL,
    amount DECIMAL(10,3) NULL,
    card_masked VARCHAR(30) NULL,
    auth_code VARCHAR(30) NULL,
    response_message VARCHAR(255) NULL,
    raw_response JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transactions_fingerprint (payment_id, fingerprint),
    KEY idx_transactions_gateway (gateway_transaction_id),
    CONSTRAINT fk_transactions_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NULL,
    direction ENUM('outbound','inbound') NOT NULL,
    action VARCHAR(60) NOT NULL,
    request_body JSON NULL,
    response_body JSON NULL,
    http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_api_logs_created (created_at),
    KEY idx_api_logs_action (action),
    KEY idx_api_logs_payment (payment_id),
    CONSTRAINT fk_api_logs_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

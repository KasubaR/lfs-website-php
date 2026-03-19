-- =============================================================
-- LFS — Lusaka Fitness Squad
-- database/migrations/001_orders_and_payments.sql
--
-- Run once against your lfs_db database:
--   mysql -u root lfs_db < database/migrations/001_orders_and_payments.sql
-- =============================================================

-- ── Orders ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    order_number    VARCHAR(30)       NOT NULL,          -- e.g. LFS-20250601-A3F7C
    customer_name   VARCHAR(255)      NOT NULL,
    customer_email  VARCHAR(255)      NOT NULL,
    customer_phone  VARCHAR(30)       DEFAULT '',
    notes           TEXT              DEFAULT NULL,
    subtotal        DECIMAL(12,2)     NOT NULL DEFAULT 0,
    total           DECIMAL(12,2)     NOT NULL DEFAULT 0,
    status          VARCHAR(30)       NOT NULL DEFAULT 'pending_payment',
    -- Statuses: pending_payment | paid | processing | ready | collected | cancelled | payment_failed
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_order_number (order_number),
    INDEX idx_customer_email    (customer_email),
    INDEX idx_status            (status),
    INDEX idx_created_at        (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Order Items ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    order_id    BIGINT UNSIGNED  NOT NULL,
    product_id  VARCHAR(100)     NOT NULL,
    name        VARCHAR(255)     NOT NULL,
    size        VARCHAR(20)      NOT NULL DEFAULT '',
    qty         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2)    NOT NULL DEFAULT 0,
    line_total  DECIMAL(10,2)    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_order_id (order_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Payments ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id                    BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    order_number          VARCHAR(30)       NOT NULL,
    payment_method        VARCHAR(30)       NOT NULL DEFAULT 'mobile_money',
    amount                DECIMAL(12,2)     NOT NULL DEFAULT 0,
    currency              CHAR(3)           NOT NULL DEFAULT 'ZMW',
    status                VARCHAR(20)       NOT NULL DEFAULT 'pending',
    -- Internal statuses: pending | processing | completed | failed | cancelled | refunded

    customer_name         VARCHAR(255)      DEFAULT '',
    customer_email        VARCHAR(255)      DEFAULT '',
    customer_phone        VARCHAR(30)       DEFAULT '',

    -- Lenco fields
    lenco_transaction_id  VARCHAR(255)      DEFAULT NULL COMMENT 'col_xxx',
    lenco_reference       VARCHAR(255)      DEFAULT NULL COMMENT 'LNC-xxx',
    lenco_provider        VARCHAR(20)       DEFAULT NULL,   -- airtel | mtn
    lenco_status          VARCHAR(50)       DEFAULT NULL,   -- Lenco raw status
    lenco_response        JSON              DEFAULT NULL,

    -- Your reference
    transaction_id        VARCHAR(255)      DEFAULT NULL,

    payment_instructions  TEXT              DEFAULT NULL,
    expires_at            DATETIME          DEFAULT NULL,

    -- Lifecycle
    completed_at          DATETIME          DEFAULT NULL,
    failed_at             DATETIME          DEFAULT NULL,
    failure_reason        TEXT              DEFAULT NULL,

    -- Webhook
    webhook_received      TINYINT(1)        NOT NULL DEFAULT 0,
    webhook_payload       JSON              DEFAULT NULL,
    webhook_received_at   DATETIME          DEFAULT NULL,

    metadata              JSON              DEFAULT NULL,

    created_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE INDEX uq_lenco_transaction_id (lenco_transaction_id),
    UNIQUE INDEX uq_lenco_reference      (lenco_reference),
    UNIQUE INDEX uq_transaction_id       (transaction_id),
    INDEX idx_order_number               (order_number),
    INDEX idx_status                     (status),
    INDEX idx_created_at                 (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

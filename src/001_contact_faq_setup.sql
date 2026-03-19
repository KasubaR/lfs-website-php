-- ============================================================
-- LFS — Lusaka Fitness Squad
-- migrations/001_contact_faq_setup.sql
--
-- Run once against lfs_db.
-- Safe to re-run (uses IF NOT EXISTS / IF column missing guards).
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. contact_messages table
--    Create if not present; if already present, add optional
--    phone and satellite columns without destroying existing data.
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120)     NOT NULL,
    `email`      VARCHAR(254)     NOT NULL,
    `phone`      VARCHAR(30)      DEFAULT NULL,
    `satellite`  VARCHAR(60)      DEFAULT NULL,
    `subject`    VARCHAR(200)     DEFAULT NULL,
    `message`    TEXT             NOT NULL,
    `status`     ENUM('New','Read','Responded') NOT NULL DEFAULT 'New',
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status`     (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add phone column if the table already existed without it
SET @col_exists = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_messages'
       AND COLUMN_NAME  = 'phone'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `contact_messages` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `email`',
    'SELECT 1 -- phone column already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add satellite column if missing
SET @col_exists = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_messages'
       AND COLUMN_NAME  = 'satellite'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `contact_messages` ADD COLUMN `satellite` VARCHAR(60) DEFAULT NULL AFTER `phone`',
    'SELECT 1 -- satellite column already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ────────────────────────────────────────────────────────────
-- 2. faqs table
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `faqs` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `question`   VARCHAR(500)  NOT NULL,
    `answer`     TEXT          NOT NULL,
    `category`   VARCHAR(100)  DEFAULT NULL,
    `sort_order` SMALLINT      NOT NULL DEFAULT 0,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_category`   (`category`),
    INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add sort_order column if the table existed without it
SET @col_exists = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'faqs'
       AND COLUMN_NAME  = 'sort_order'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `faqs` ADD COLUMN `sort_order` SMALLINT NOT NULL DEFAULT 0 AFTER `category`',
    'SELECT 1 -- sort_order column already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ────────────────────────────────────────────────────────────
-- 3. Seed FAQ rows (only if table is empty)
--    These match the static Q&As previously hardcoded in the view.
-- ────────────────────────────────────────────────────────────

INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`, `created_at`)
SELECT * FROM (
    SELECT
        'What is LFS?' AS question,
        'LFS (Lusaka Fitness Squad) is Zambia''s biggest running community. We''re a vibrant squad of runners across six satellites in Lusaka, training, running, and competing together since 2017.' AS answer,
        'General' AS category,
        10 AS sort_order,
        NOW() AS created_at
    UNION ALL SELECT
        'How do I join LFS?',
        'Contact the captain of your nearest satellite (see the Satellites section) or use the contact form. Annual membership is K1,000 — you''ll get access to WhatsApp groups, voting rights, and priority event registration.',
        'Membership', 20, NOW()
    UNION ALL SELECT
        'When and where do runs happen?',
        'Every Saturday, all six satellites meet for a Long Slow Distance (LSD) run at a rotating host location. Individual satellites also run weekly sessions. See the Satellites section to find your nearest one.',
        'Training', 30, NOW()
    UNION ALL SELECT
        'Do I need to be a seasoned runner?',
        'No! LFS welcomes all paces and fitness levels. Whether you''re just starting out or training for a marathon, there''s a place for you. Our runs are inclusive and low-pressure.',
        'General', 40, NOW()
    UNION ALL SELECT
        'What events does LFS organise?',
        'LFS manages Zambia''s biggest running events — road races, cross-country, and social runs. We''ve been delivering world-class race experiences for over 7 years. See the Events section for upcoming races.',
        'Events', 50, NOW()
    UNION ALL SELECT
        'How can I get in touch?',
        'Use the contact form on this page, email info@lfszambia.run, or call the President at +260 966 755 326. You can also reach your nearest satellite captain directly from the Satellites section.',
        'General', 60, NOW()
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `faqs` LIMIT 1);

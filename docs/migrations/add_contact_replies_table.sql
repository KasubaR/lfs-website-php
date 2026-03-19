-- ============================================================
-- LFS — Add contact_replies table
-- Run once in phpMyAdmin (db: lfs_db)
-- ============================================================

-- Create replies table for admin responses to contact messages.
-- Uses UUID-style CHAR(36) IDs to match current contact_messages.id usage.
CREATE TABLE IF NOT EXISTS `contact_replies` (
    `id`                 CHAR(36)     NOT NULL DEFAULT (UUID()),
    `contact_message_id` CHAR(36)     NOT NULL,
    `reply_message`      TEXT         NOT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_replies_message_id` (`contact_message_id`),
    KEY `idx_contact_replies_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure contact_messages.status can hold "Responded".
-- Compatible with both ENUM and VARCHAR-based schemas.
SET @status_col_type = (
    SELECT DATA_TYPE
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_messages'
       AND COLUMN_NAME  = 'status'
     LIMIT 1
);

SET @is_responded_present = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS c
      JOIN information_schema.CHECK_CONSTRAINTS cc
        ON cc.CONSTRAINT_SCHEMA = c.TABLE_SCHEMA
     WHERE c.TABLE_SCHEMA = DATABASE()
       AND c.TABLE_NAME   = 'contact_messages'
       AND c.COLUMN_NAME  = 'status'
       AND (
            c.COLUMN_TYPE LIKE '%Responded%'
            OR cc.CHECK_CLAUSE LIKE '%Responded%'
       )
);

SET @sql = IF(
    @status_col_type = 'enum' AND @is_responded_present = 0,
    'ALTER TABLE `contact_messages` MODIFY `status` ENUM(''New'',''Read'',''Responded'') NOT NULL DEFAULT ''New''',
    'SELECT 1 -- status already supports Responded, or is non-ENUM'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


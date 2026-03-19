-- Create gallery_settings table to store a single global
-- gallery banner image, similar to event banners.
--
-- Run once, for example:
--   mysql -u YOUR_USER -p YOUR_DATABASE < docs/migrations/add_gallery_settings_table.sql

CREATE TABLE IF NOT EXISTS gallery_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  banner_image VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gallery_settings (id, banner_image)
VALUES (1, NULL)
ON DUPLICATE KEY UPDATE banner_image = banner_image;


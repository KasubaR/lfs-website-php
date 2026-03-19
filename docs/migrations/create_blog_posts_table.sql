-- LFS — Lusaka Fitness Squad
-- Migrate blog_posts table to new schema
--
-- Existing columns:
--   id, title, slug, content, featured_image, author_id,
--   category, published, created_at, updated_at
--
-- Changes:
--   ADD     excerpt, tags, status, featured, views, publish_date
--   RENAME  author_id  → author (VARCHAR — stores display name, not FK)
--   MIGRATE published (BOOL) → status (ENUM)
--   DROP    published
--
-- Run once:
--   mysql -u YOUR_USER -p YOUR_DATABASE < docs/migrations/create_blog_posts_table.sql

-- 1. Add new columns
ALTER TABLE blog_posts
  ADD COLUMN excerpt       TEXT          NULL            AFTER slug,
  ADD COLUMN tags          JSON          NULL            AFTER category,
  ADD COLUMN status        ENUM('draft','published')
                                         NOT NULL
                                         DEFAULT 'draft' AFTER tags,
  ADD COLUMN featured      TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN views         INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN publish_date  DATETIME     NULL;

-- 2. Populate status from the old published boolean
UPDATE blog_posts
SET status = IF(published = 1, 'published', 'draft');

-- 3. Rename author_id to author (stores display name going forward)
ALTER TABLE blog_posts
  CHANGE COLUMN author_id author VARCHAR(100) NOT NULL DEFAULT 'LFS Admin';

-- 4. Drop the old published column (data preserved in status)
ALTER TABLE blog_posts
  DROP COLUMN published;

-- 5. Add indexes
ALTER TABLE blog_posts
  ADD KEY idx_blog_posts_status       (status),
  ADD KEY idx_blog_posts_category     (category),
  ADD KEY idx_blog_posts_featured     (featured),
  ADD KEY idx_blog_posts_publish_date (publish_date);

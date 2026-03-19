-- Add per-photo flags so Featured, Homepage slider, and Event highlight apply to individual media.
-- Run once: mysql -u user -p dbname < docs/migrations/add_media_homepage_slider_event_highlight.sql

ALTER TABLE media
  ADD COLUMN homepage_slider TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN event_highlight TINYINT(1) NOT NULL DEFAULT 0;

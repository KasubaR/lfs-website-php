-- ============================================================
-- LFS — Add phone and satellite to contact_messages
-- Run once in phpMyAdmin (SQL tab, db: lfs_db) if your table
-- has: id, name, email, subject, message, status, created_at
-- ============================================================

-- Add phone (optional, for contact form)
ALTER TABLE contact_messages
  ADD COLUMN phone VARCHAR(30) DEFAULT NULL AFTER email;

-- Add satellite (optional, for “Nearest satellite” dropdown)
ALTER TABLE contact_messages
  ADD COLUMN satellite VARCHAR(60) DEFAULT NULL AFTER phone;

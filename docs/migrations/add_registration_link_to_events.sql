-- Migration: add registration_link column to events table
ALTER TABLE events
  ADD COLUMN registration_link VARCHAR(2048) NULL DEFAULT NULL
    AFTER registration_type;

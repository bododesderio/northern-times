-- Migration: 0044_users_display_name.sql
-- Adds display_name and extended social profile fields to users table

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS display_name      VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS facebook_url      TEXT         NULL,
  ADD COLUMN IF NOT EXISTS linkedin_url      TEXT         NULL,
  ADD COLUMN IF NOT EXISTS instagram_handle  VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS whatsapp_number   VARCHAR(30)  NULL,
  ADD COLUMN IF NOT EXISTS website_url       TEXT         NULL;

-- Back-fill display_name from username for existing users
UPDATE users SET display_name = username WHERE display_name IS NULL;

COMMENT ON COLUMN users.display_name IS 'Public-facing display name shown on author pages and bylines';

-- Migration: 0010_users_profile_columns.sql
-- Adds profile fields and active flag to the users table

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS bio              TEXT         NULL,
  ADD COLUMN IF NOT EXISTS avatar_url       TEXT         NULL,
  ADD COLUMN IF NOT EXISTS twitter_handle   VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS is_active        BOOLEAN      NOT NULL DEFAULT TRUE,
  ADD COLUMN IF NOT EXISTS updated_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW();

-- Back-fill updated_at for existing rows
UPDATE users SET updated_at = created_at WHERE updated_at IS NULL;

-- Index for filtering active users
CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);

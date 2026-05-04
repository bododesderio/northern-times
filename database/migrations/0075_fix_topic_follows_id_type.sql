-- Migration: 0075_fix_topic_follows_id_type.sql
-- Fix topic_follows.follow_id type from VARCHAR(255) to UUID.
-- categories.id and tags.id are UUID PRIMARY KEY — follow_id must match for FK integrity.
-- Wrapped in transaction for safety. Existing VARCHAR follow data is recast where valid.

BEGIN;

-- Add new UUID column
ALTER TABLE topic_follows ADD COLUMN IF NOT EXISTS follow_id_new UUID;

-- Attempt to cast existing VARCHAR values to UUID (invalid ones become NULL)
UPDATE topic_follows SET follow_id_new = follow_id::uuid
  WHERE follow_id ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$';

-- Drop old column and rename new
ALTER TABLE topic_follows DROP COLUMN follow_id;
ALTER TABLE topic_follows RENAME COLUMN follow_id_new TO follow_id;

-- Make NOT NULL (rows with invalid UUIDs will have been NULLed — remove them)
DELETE FROM topic_follows WHERE follow_id IS NULL;
ALTER TABLE topic_follows ALTER COLUMN follow_id SET NOT NULL;

-- Recreate the unique index
DROP INDEX IF EXISTS idx_topic_follows_unique;
CREATE UNIQUE INDEX idx_topic_follows_unique ON topic_follows(email, follow_type, follow_id);

COMMIT;

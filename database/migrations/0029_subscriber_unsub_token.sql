-- 0029_subscriber_unsub_token.sql
-- Add unsubscribe token for tokenized unsubscribe links.

ALTER TABLE newsletter_subscribers
  ADD COLUMN IF NOT EXISTS unsub_token VARCHAR(128) NULL;

-- Generate tokens for existing subscribers that don't have one
UPDATE newsletter_subscribers
  SET unsub_token = encode(gen_random_bytes(32), 'hex')
  WHERE unsub_token IS NULL;

-- Unique index on token (for fast lookups)
CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriber_unsub_token
  ON newsletter_subscribers(unsub_token)
  WHERE unsub_token IS NOT NULL;

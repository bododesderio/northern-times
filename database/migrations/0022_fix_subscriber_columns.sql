-- Migration 0022: Add status + source columns to newsletter_subscribers
-- Required by AdminSubscriberController which queries WHERE status = 'active'

ALTER TABLE newsletter_subscribers
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS source VARCHAR(50) NULL DEFAULT 'website';

-- Backfill: any rows without an explicit status default to active
UPDATE newsletter_subscribers SET status = 'active' WHERE status IS NULL;

CREATE INDEX IF NOT EXISTS idx_newsletter_status ON newsletter_subscribers(status);
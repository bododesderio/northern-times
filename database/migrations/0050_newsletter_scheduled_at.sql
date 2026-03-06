-- 0050_newsletter_scheduled_at.sql
-- Add scheduling support to newsletter_issues

ALTER TABLE newsletter_issues
  ADD COLUMN IF NOT EXISTS scheduled_at TIMESTAMPTZ NULL;

CREATE INDEX IF NOT EXISTS idx_newsletter_issues_scheduled
  ON newsletter_issues(scheduled_at)
  WHERE status = 'scheduled';

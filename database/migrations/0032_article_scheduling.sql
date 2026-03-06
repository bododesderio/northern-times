-- 0032_article_scheduling.sql
-- Add 'scheduled' to article status CHECK constraint.

-- Step 1: Drop existing check constraint
ALTER TABLE articles DROP CONSTRAINT IF EXISTS chk_article_status;

-- Step 2: Re-add with 'scheduled' included
ALTER TABLE articles
  ADD CONSTRAINT chk_article_status
  CHECK (status IN ('draft', 'pending_review', 'published', 'archived', 'scheduled'));

-- Step 3: Index for cron job efficiency
CREATE INDEX IF NOT EXISTS idx_articles_scheduled
  ON articles(published_at)
  WHERE status = 'scheduled';

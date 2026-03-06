-- 0025_editorial_workflow.sql
-- Add pending_review to article_status enum + review tracking columns.
-- Strategy: create new varchar column, copy data, swap columns.

-- Step 1: Add new varchar column
ALTER TABLE articles ADD COLUMN IF NOT EXISTS status_new VARCHAR(30) NOT NULL DEFAULT 'draft';

-- Step 2: Copy existing status values
UPDATE articles SET status_new = status::text;

-- Step 3: Drop the old enum column
ALTER TABLE articles DROP COLUMN IF EXISTS status;

-- Step 4: Rename new column to status
ALTER TABLE articles RENAME COLUMN status_new TO status;

-- Step 5: Add CHECK constraint for allowed statuses
DO $$
BEGIN
  ALTER TABLE articles
    ADD CONSTRAINT chk_article_status
    CHECK (status IN ('draft', 'pending_review', 'published', 'archived'));
EXCEPTION
  WHEN duplicate_object THEN NULL;
END $$;

-- Step 6: Recreate index on status
CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status);
CREATE INDEX IF NOT EXISTS idx_articles_pending_review ON articles(status) WHERE status = 'pending_review';

-- Step 7: Add review tracking columns
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS review_notes TEXT NULL,
  ADD COLUMN IF NOT EXISTS reviewed_by  UUID NULL REFERENCES users(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS reviewed_at  TIMESTAMPTZ NULL;

-- Step 8: Drop the old enum type
DO $$
BEGIN
  DROP TYPE IF EXISTS article_status;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

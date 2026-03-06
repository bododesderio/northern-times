-- Migration: 0018_story_threads_and_comments.sql
-- 1) Story Threads — group articles about the same ongoing story
-- 2) Simplify comments: visible / hidden / deleted (no approval queue)

-- ═══ Story Threads ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS story_threads (
  id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  title       VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) UNIQUE NOT NULL,
  description TEXT         NULL,
  color       VARCHAR(7)   NOT NULL DEFAULT '#cc0000',
  is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_st_slug   ON story_threads(slug);
CREATE INDEX IF NOT EXISTS idx_st_active ON story_threads(is_active);

-- Link articles to story threads
ALTER TABLE articles ADD COLUMN IF NOT EXISTS story_thread_id UUID NULL REFERENCES story_threads(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_articles_story_thread ON articles(story_thread_id);

-- ═══ Comments: simplify statuses ════════════════════════════════
-- If comments table exists with old statuses, migrate them:
-- pending  → visible  (no approval needed)
-- approved → visible
-- spam     → hidden
-- rejected → deleted
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='comments') THEN
    UPDATE comments SET status = 'visible' WHERE status IN ('pending','approved');
    UPDATE comments SET status = 'hidden'  WHERE status = 'spam';
    UPDATE comments SET status = 'deleted' WHERE status = 'rejected';
  END IF;
END $$;

-- 0073: AI Rewriter Upgrade
-- Renames original_* columns to rewritten_* (stores AI draft, not the original).
-- Adds new rewrite statuses for the approval workflow.
-- Seeds rewriter settings into site_settings.

BEGIN;

-- ── 1. Rename columns (idempotent) ─────────────────────────────────
-- The rewritten_* columns hold the AI-generated draft pending approval.
-- The main title/content/excerpt columns always hold the LIVE content.
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='articles' AND column_name='original_title') THEN
        ALTER TABLE articles RENAME COLUMN original_title   TO rewritten_title;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='articles' AND column_name='original_content') THEN
        ALTER TABLE articles RENAME COLUMN original_content TO rewritten_content;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='articles' AND column_name='original_excerpt') THEN
        ALTER TABLE articles RENAME COLUMN original_excerpt TO rewritten_excerpt;
    END IF;
END $$;

-- ── 2. Widen status + document new values ──────────────────────────
-- Old values: skipped | queued | processing | done | failed
-- New values: skipped | queued | processing | pending_approval | approved | rejected | failed
ALTER TABLE articles ALTER COLUMN rewrite_status TYPE VARCHAR(30);

COMMENT ON COLUMN articles.rewrite_status IS 'AI rewrite pipeline: skipped|queued|processing|pending_approval|approved|rejected|failed';
COMMENT ON COLUMN articles.rewritten_title   IS 'AI-rewritten title draft (pending approval or applied)';
COMMENT ON COLUMN articles.rewritten_content IS 'AI-rewritten content draft (pending approval or applied)';
COMMENT ON COLUMN articles.rewritten_excerpt IS 'AI-rewritten excerpt draft (pending approval or applied)';

-- ── 3. Data migration — fix articles processed under old workflow ──
-- Old workflow: main columns = AI text, original_* = crawled original.
-- New workflow: main columns = live/original, rewritten_* = AI draft.
-- Swap them back for any 'done' articles so main holds the original.
UPDATE articles
   SET title             = rewritten_title,
       content           = rewritten_content,
       excerpt           = COALESCE(rewritten_excerpt, excerpt),
       rewritten_title   = title,
       rewritten_content = content,
       rewritten_excerpt = excerpt,
       rewrite_status    = 'pending_approval'
 WHERE rewrite_status = 'done'
   AND rewritten_content IS NOT NULL;

-- Articles marked 'done' but with no stored original — just update status
UPDATE articles
   SET rewrite_status = 'approved'
 WHERE rewrite_status = 'done'
   AND rewritten_content IS NULL;

-- ── 4. Seed rewriter settings ──────────────────────────────────────
INSERT INTO site_settings (setting_key, setting_value, setting_group)
VALUES
  ('rewriter_auto_apply', 'false',  'rewriter'),
  ('rewriter_rules',      '',       'rewriter'),
  ('rewriter_max_words',  '4000',   'rewriter')
ON CONFLICT (setting_key) DO NOTHING;

COMMIT;

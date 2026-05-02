-- 0072: Per-source auto-rewrite toggle
-- Allows enabling the AI rewriter on a per-crawl-source basis

ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS auto_rewrite BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN crawl_sources.auto_rewrite IS 'Automatically queue crawled articles from this source for AI rewriting';

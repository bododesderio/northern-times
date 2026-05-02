-- 0071: AI Article Rewriter columns on articles table
-- Stores original content before rewriting + rewrite tracking metadata

ALTER TABLE articles ADD COLUMN IF NOT EXISTS original_title   TEXT;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS original_excerpt TEXT;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS original_content TEXT;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS rewrite_status   VARCHAR(20) NOT NULL DEFAULT 'skipped';
  -- values: skipped | queued | processing | done | failed
ALTER TABLE articles ADD COLUMN IF NOT EXISTS rewritten_at     TIMESTAMPTZ;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS rewriter_model   VARCHAR(100);

-- Per-source bypass columns for blocking/Cloudflare sites
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS custom_user_agent TEXT;
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS crawl_delay       SMALLINT NOT NULL DEFAULT 0;
  -- seconds to wait between article fetches (0 = no delay)
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS custom_headers    TEXT;
  -- JSON object of extra request headers, e.g. {"Cookie":"session=abc"}
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS use_browser_fetch BOOLEAN NOT NULL DEFAULT FALSE;
  -- Force Playwright browser fetch for Cloudflare-protected sites

COMMENT ON COLUMN articles.rewrite_status IS 'AI rewrite pipeline status: skipped|queued|processing|done|failed';
COMMENT ON COLUMN crawl_sources.use_browser_fetch IS 'Use Playwright headless browser for this source (for Cloudflare-protected sites)';

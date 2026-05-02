-- ═══════════════════════════════════════════════════════════════
-- Migration 0069: Crawler Reliability Improvements
--
-- 1. UNIQUE constraint on feed_url (prevent duplicate sources)
-- 2. CHECK constraint on crawl_interval (must be > 0)
-- 3. Widen content_selector/strip_selectors to TEXT
-- 4. Add consecutive_failures counter for adaptive scheduling
-- 5. Composite index on (is_active, last_crawled_at) for dueForCrawl()
-- 6. Remove CASCADE on crawl_logs FK (preserve history on source delete)
-- ═══════════════════════════════════════════════════════════════

-- 1. Unique feed_url (idempotent — skip if exists)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'crawl_sources_feed_url_unique'
    ) THEN
        ALTER TABLE crawl_sources ADD CONSTRAINT crawl_sources_feed_url_unique UNIQUE (feed_url);
    END IF;
END $$;

-- 2. Check crawl_interval > 0
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'crawl_sources_interval_positive'
    ) THEN
        ALTER TABLE crawl_sources ADD CONSTRAINT crawl_sources_interval_positive CHECK (crawl_interval > 0);
    END IF;
END $$;

-- 3. Widen selector columns from VARCHAR(255) to TEXT
ALTER TABLE crawl_sources ALTER COLUMN content_selector TYPE TEXT;
ALTER TABLE crawl_sources ALTER COLUMN strip_selectors TYPE TEXT;

-- 4. Add consecutive_failures counter (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'crawl_sources' AND column_name = 'consecutive_failures'
    ) THEN
        ALTER TABLE crawl_sources ADD COLUMN consecutive_failures INTEGER NOT NULL DEFAULT 0;
    END IF;
END $$;

-- 5. Composite index for dueForCrawl() query performance
CREATE INDEX IF NOT EXISTS idx_crawl_sources_active_crawled
    ON crawl_sources (is_active, last_crawled_at ASC NULLS FIRST)
    WHERE is_active = TRUE;

-- 6. Remove CASCADE on crawl_logs FK, replace with SET NULL
-- First drop the existing FK, then re-add without CASCADE
DO $$
DECLARE
    fk_name TEXT;
BEGIN
    SELECT conname INTO fk_name
    FROM pg_constraint
    WHERE conrelid = 'crawl_logs'::regclass
      AND confrelid = 'crawl_sources'::regclass
      AND contype = 'f'
    LIMIT 1;

    IF fk_name IS NOT NULL THEN
        EXECUTE 'ALTER TABLE crawl_logs DROP CONSTRAINT ' || fk_name;
        ALTER TABLE crawl_logs
            ADD CONSTRAINT crawl_logs_source_id_fkey
            FOREIGN KEY (source_id) REFERENCES crawl_sources(id) ON DELETE SET NULL;
    END IF;
END $$;

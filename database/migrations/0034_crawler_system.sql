-- 0034_crawler_system.sql
-- Phase 7A: News Aggregator crawler tables

-- ── crawl_sources: each configured RSS/website source ─────────
CREATE TABLE IF NOT EXISTS crawl_sources (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name            VARCHAR(200) NOT NULL,                 -- "Daily Monitor", "Reuters Africa"
  feed_url        TEXT NOT NULL,                          -- RSS/Atom feed URL
  website_url     TEXT NULL,                              -- Homepage for attribution link
  logo_url        TEXT NULL,                              -- Source icon/logo (hotlinked)
  source_type     VARCHAR(20) NOT NULL DEFAULT 'rss',     -- rss, atom, html
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  crawl_interval  INT NOT NULL DEFAULT 30,                -- minutes between crawls
  last_crawled_at TIMESTAMPTZ NULL,
  last_success_at TIMESTAMPTZ NULL,
  last_error      TEXT NULL,

  -- Category mapping
  default_category_id UUID NULL REFERENCES categories(id) ON DELETE SET NULL,
  category_map    JSONB NOT NULL DEFAULT '{}',            -- {"politics":"uuid","sport":"uuid",...}

  -- Content rules
  keyword_include TEXT NULL,                              -- comma-separated include keywords
  keyword_exclude TEXT NULL,                              -- comma-separated exclude keywords
  max_articles    INT NOT NULL DEFAULT 20,                -- max articles per crawl
  strip_selectors TEXT NULL,                              -- CSS selectors to strip (ads, nav, etc.)

  -- Attribution
  attribution_text VARCHAR(200) NULL,                     -- "Source: Daily Monitor"
  nofollow        BOOLEAN NOT NULL DEFAULT TRUE,

  -- Stats
  total_crawled   INT NOT NULL DEFAULT 0,
  total_published INT NOT NULL DEFAULT 0,
  total_errors    INT NOT NULL DEFAULT 0,

  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_crawl_sources_active ON crawl_sources(is_active) WHERE is_active = TRUE;

-- ── crawl_logs: record of each crawl execution ────────────────
CREATE TABLE IF NOT EXISTS crawl_logs (
  id              BIGSERIAL PRIMARY KEY,
  source_id       UUID NOT NULL REFERENCES crawl_sources(id) ON DELETE CASCADE,
  started_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  finished_at     TIMESTAMPTZ NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'running', -- running, success, partial, failed
  articles_found  INT NOT NULL DEFAULT 0,
  articles_new    INT NOT NULL DEFAULT 0,
  articles_dupes  INT NOT NULL DEFAULT 0,
  error_message   TEXT NULL,
  details         JSONB NULL                              -- per-article breakdown
);

CREATE INDEX IF NOT EXISTS idx_crawl_logs_source ON crawl_logs(source_id, started_at DESC);
CREATE INDEX IF NOT EXISTS idx_crawl_logs_status ON crawl_logs(status);

-- ── Add source tracking columns to articles ───────────────────
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS is_crawled      BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS crawl_source_id UUID NULL REFERENCES crawl_sources(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS source_url      TEXT NULL,
  ADD COLUMN IF NOT EXISTS source_name     TEXT NULL,
  ADD COLUMN IF NOT EXISTS source_hash     VARCHAR(64) NULL;  -- SHA-256 of source_url for dedup

CREATE INDEX IF NOT EXISTS idx_articles_crawled ON articles(is_crawled) WHERE is_crawled = TRUE;
CREATE INDEX IF NOT EXISTS idx_articles_source_hash ON articles(source_hash) WHERE source_hash IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_articles_crawl_source ON articles(crawl_source_id) WHERE crawl_source_id IS NOT NULL;

-- ── Crawler global settings (in site_settings) ────────────────
INSERT INTO site_settings (setting_key, setting_value, setting_group)
VALUES
  ('crawler_enabled',        'false',  'crawler'),
  ('crawler_interval',       '30',     'crawler'),
  ('crawler_auto_publish',   'true',   'crawler'),
  ('crawler_max_age_hours',  '72',     'crawler'),
  ('crawler_default_author', '',       'crawler')
ON CONFLICT (setting_key) DO NOTHING;

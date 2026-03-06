-- Migration: 0016_article_views.sql
-- Granular view logging for analytics dashboards and time-series charts
-- The articles.views column remains the authoritative total counter.
-- This table adds when/who detail so we can chart views over time.

CREATE TABLE IF NOT EXISTS article_views (
  id          BIGSERIAL    PRIMARY KEY,
  article_id  UUID         NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
  ip_address  INET         NULL,
  referer     TEXT         NULL,
  viewed_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_av_article   ON article_views(article_id);
CREATE INDEX IF NOT EXISTS idx_av_viewed_at ON article_views(viewed_at);

-- Composite index for daily aggregation queries
-- Use timezone-pinned cast so the expression is IMMUTABLE
CREATE INDEX IF NOT EXISTS idx_av_daily ON article_views(article_id, ((viewed_at AT TIME ZONE 'UTC')::date));
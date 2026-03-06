-- Migration: 0037_breaking_news_engine.sql
-- Breaking News Engine: scoring, velocity tracking, auto-detection

-- ── Extend articles table ─────────────────────────────────────
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS breaking_score      INT          NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS breaking_until      TIMESTAMPTZ  NULL,
  ADD COLUMN IF NOT EXISTS is_breaking_manual  BOOLEAN      NOT NULL DEFAULT FALSE;

-- Migrate existing manual flags
UPDATE articles SET is_breaking_manual = TRUE, breaking_score = 100,
  breaking_until = NOW() + INTERVAL '6 hours'
WHERE is_breaking = TRUE;

-- Index for fast breaking queries
CREATE INDEX IF NOT EXISTS idx_articles_breaking_score
  ON articles(breaking_score DESC) WHERE breaking_score >= 60;

-- ── View snapshots for velocity detection ─────────────────────
CREATE TABLE IF NOT EXISTS article_view_snapshots (
  id          BIGSERIAL PRIMARY KEY,
  article_id  UUID        NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
  views       INT         NOT NULL DEFAULT 0,
  snapshot_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_avs_article_time
  ON article_view_snapshots(article_id, snapshot_at DESC);

-- Auto-cleanup: only keep 48 hours of snapshots
CREATE INDEX IF NOT EXISTS idx_avs_cleanup
  ON article_view_snapshots(snapshot_at);

-- ── Average velocity baseline table ───────────────────────────
CREATE TABLE IF NOT EXISTS breaking_velocity_baseline (
  hour_bucket   INT NOT NULL, -- 0-23 (hour of day)
  avg_views_1h  FLOAT NOT NULL DEFAULT 0,
  avg_views_3h  FLOAT NOT NULL DEFAULT 0,
  sample_count  INT NOT NULL DEFAULT 0,
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (hour_bucket)
);

-- Seed with reasonable defaults (will self-calibrate over time)
INSERT INTO breaking_velocity_baseline (hour_bucket, avg_views_1h, avg_views_3h, sample_count)
SELECT h, 10, 25, 0
FROM generate_series(0, 23) AS h
ON CONFLICT (hour_bucket) DO NOTHING;

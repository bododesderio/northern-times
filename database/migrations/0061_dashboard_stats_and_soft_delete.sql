-- Migration: 0061_dashboard_stats_and_soft_delete.sql
-- Dashboard pre-aggregation table + article soft delete

-- ── 1. Daily Stats Aggregation Table ──────────────────────────
CREATE TABLE IF NOT EXISTS daily_stats (
    stat_date    DATE PRIMARY KEY DEFAULT CURRENT_DATE,
    total_views  INTEGER NOT NULL DEFAULT 0,
    unique_visitors INTEGER NOT NULL DEFAULT 0,
    articles_published INTEGER NOT NULL DEFAULT 0,
    articles_crawled INTEGER NOT NULL DEFAULT 0,
    comments_count INTEGER NOT NULL DEFAULT 0,
    subscribers_count INTEGER NOT NULL DEFAULT 0,
    top_category VARCHAR(100),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_daily_stats_date ON daily_stats(stat_date DESC);

-- ── 2. Article Soft Delete ────────────────────────────────────
ALTER TABLE articles ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_articles_deleted_at ON articles(deleted_at)
    WHERE deleted_at IS NOT NULL;

-- ── 3. Webhook Events Table ───────────────────────────────────
CREATE TABLE IF NOT EXISTS webhooks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    events TEXT NOT NULL DEFAULT 'article.published',
    secret VARCHAR(100),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_triggered_at TIMESTAMPTZ,
    last_status_code INTEGER,
    failure_count INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS webhook_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    webhook_id UUID NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event VARCHAR(50) NOT NULL,
    payload JSONB,
    response_code INTEGER,
    response_body TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_webhook_logs_webhook_id ON webhook_logs(webhook_id);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_created ON webhook_logs(created_at DESC);

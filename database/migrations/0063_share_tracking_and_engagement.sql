-- Migration: 0063_share_tracking_and_engagement.sql
-- Social share tracking + reader engagement scoring

-- Share counts per article
ALTER TABLE articles ADD COLUMN IF NOT EXISTS share_count INTEGER NOT NULL DEFAULT 0;

-- Share events log
CREATE TABLE IF NOT EXISTS article_shares (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    article_id UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    platform VARCHAR(30) NOT NULL,
    ip_address INET,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_article_shares_article ON article_shares(article_id);
CREATE INDEX IF NOT EXISTS idx_article_shares_created ON article_shares(created_at DESC);

-- Engagement metrics per article
ALTER TABLE articles ADD COLUMN IF NOT EXISTS avg_scroll_depth REAL DEFAULT 0;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS avg_time_on_page INTEGER DEFAULT 0;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS engagement_score REAL DEFAULT 0;

-- Engagement events (scroll depth, time on page)
CREATE TABLE IF NOT EXISTS engagement_events (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    article_id UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    ip_address INET,
    scroll_depth REAL DEFAULT 0,
    time_on_page INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_engagement_article ON engagement_events(article_id);

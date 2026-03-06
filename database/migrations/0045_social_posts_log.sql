-- Migration: 0045_social_posts_log.sql
-- Logs all auto-post attempts to social media platforms

CREATE TABLE IF NOT EXISTS social_posts_log (
    id           SERIAL PRIMARY KEY,
    article_id   UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    platform     VARCHAR(30)  NOT NULL,  -- 'facebook','twitter','telegram','whatsapp','linkedin'
    status       VARCHAR(20)  NOT NULL DEFAULT 'sent',  -- 'sent','failed','skipped'
    post_url     TEXT         NULL,      -- URL of the created post (if returned by platform)
    error        TEXT         NULL,      -- Error message if failed
    payload      TEXT         NULL,      -- JSON payload sent (for debugging)
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_social_posts_article  ON social_posts_log(article_id);
CREATE INDEX IF NOT EXISTS idx_social_posts_platform ON social_posts_log(platform);
CREATE INDEX IF NOT EXISTS idx_social_posts_created  ON social_posts_log(created_at DESC);

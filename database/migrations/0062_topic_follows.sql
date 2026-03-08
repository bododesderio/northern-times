-- Migration: 0062_topic_follows.sql
-- Email alerts: readers can follow categories or tags

CREATE TABLE IF NOT EXISTS topic_follows (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    follow_type VARCHAR(20) NOT NULL CHECK (follow_type IN ('category', 'tag')),
    follow_id UUID NOT NULL,
    unsub_token VARCHAR(64) NOT NULL DEFAULT encode(gen_random_bytes(32), 'hex'),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_topic_follows_unique
    ON topic_follows(email, follow_type, follow_id);
CREATE INDEX IF NOT EXISTS idx_topic_follows_active
    ON topic_follows(follow_type, follow_id) WHERE is_active = TRUE;

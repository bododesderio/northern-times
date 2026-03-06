-- Migration: 0046_push_subscriptions.sql
-- Stores browser push notification subscriptions (Web Push Protocol)

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id           SERIAL PRIMARY KEY,
    endpoint     TEXT         NOT NULL UNIQUE,
    p256dh       TEXT         NOT NULL,   -- Public key for payload encryption
    auth         TEXT         NOT NULL,   -- Auth secret
    user_agent   TEXT         NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    last_used_at TIMESTAMPTZ  NULL
);

CREATE INDEX IF NOT EXISTS idx_push_endpoint ON push_subscriptions(endpoint);
CREATE INDEX IF NOT EXISTS idx_push_created  ON push_subscriptions(created_at DESC);

-- Phase 8: System Administration — permanent system log table
-- This log CANNOT be cleared, even by factory reset.

CREATE TABLE IF NOT EXISTS system_log (
    id              SERIAL PRIMARY KEY,
    user_id         UUID REFERENCES users(id) ON DELETE SET NULL,
    user_email      VARCHAR(255) NOT NULL,
    action          VARCHAR(100) NOT NULL,
    details         TEXT DEFAULT '',
    records_affected INTEGER DEFAULT 0,
    danger_level    VARCHAR(20) DEFAULT 'safe',   -- safe, content, media, danger, factory
    ip_address      VARCHAR(45) DEFAULT '',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_system_log_created ON system_log (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_system_log_action  ON system_log (action);
CREATE INDEX IF NOT EXISTS idx_system_log_user    ON system_log (user_id);

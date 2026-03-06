-- Phase 8 Expansion: Cron Monitor, Session Manager, DB Backups

-- ── Cron Run Log ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cron_runs (
    id          SERIAL PRIMARY KEY,
    task_name   VARCHAR(100) NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'success',  -- success, error, running
    duration_ms INTEGER DEFAULT 0,
    records_affected INTEGER DEFAULT 0,
    output      TEXT DEFAULT '',
    error_msg   TEXT DEFAULT '',
    started_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    finished_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_cron_runs_task    ON cron_runs (task_name, started_at DESC);
CREATE INDEX IF NOT EXISTS idx_cron_runs_started ON cron_runs (started_at DESC);

-- ── Active Sessions ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS active_sessions (
    id          VARCHAR(128) PRIMARY KEY,
    user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address  VARCHAR(45) DEFAULT '',
    user_agent  TEXT DEFAULT '',
    last_activity TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_active_sessions_user ON active_sessions (user_id);
CREATE INDEX IF NOT EXISTS idx_active_sessions_activity ON active_sessions (last_activity DESC);

-- ── Database Backups ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS db_backups (
    id          SERIAL PRIMARY KEY,
    filename    VARCHAR(255) NOT NULL,
    file_size   BIGINT DEFAULT 0,
    file_path   TEXT NOT NULL,
    created_by  UUID REFERENCES users(id) ON DELETE SET NULL,
    is_auto     BOOLEAN DEFAULT FALSE,
    status      VARCHAR(20) DEFAULT 'completed',  -- completed, failed, in_progress
    error_msg   TEXT DEFAULT '',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_db_backups_created ON db_backups (created_at DESC);

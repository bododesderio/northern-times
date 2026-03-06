-- 0035_seo_audit.sql
-- Phase 7B: SEO Crawler + Image Health Monitor

-- ── seo_audits: each audit run ──────────────────────────────────
CREATE TABLE IF NOT EXISTS seo_audits (
  id              BIGSERIAL PRIMARY KEY,
  started_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  finished_at     TIMESTAMPTZ NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'running',  -- running, completed, failed
  pages_scanned   INT NOT NULL DEFAULT 0,
  issues_found    INT NOT NULL DEFAULT 0,
  health_score    INT NULL,                                -- 0–100
  critical_count  INT NOT NULL DEFAULT 0,
  warning_count   INT NOT NULL DEFAULT 0,
  passed_count    INT NOT NULL DEFAULT 0,
  error_message   TEXT NULL,
  details         JSONB NULL                               -- per-check breakdown
);

CREATE INDEX IF NOT EXISTS idx_seo_audits_status ON seo_audits(status);
CREATE INDEX IF NOT EXISTS idx_seo_audits_started ON seo_audits(started_at DESC);

-- ── seo_issues: individual issues found per audit ───────────────
CREATE TABLE IF NOT EXISTS seo_issues (
  id              BIGSERIAL PRIMARY KEY,
  audit_id        BIGINT NOT NULL REFERENCES seo_audits(id) ON DELETE CASCADE,
  severity        VARCHAR(10) NOT NULL DEFAULT 'warning',  -- critical, warning, info
  check_name      VARCHAR(80) NOT NULL,                    -- e.g. missing_meta_description
  page_url        TEXT NULL,
  article_id      UUID NULL REFERENCES articles(id) ON DELETE SET NULL,
  description     TEXT NOT NULL,
  suggestion      TEXT NULL,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_seo_issues_audit ON seo_issues(audit_id);
CREATE INDEX IF NOT EXISTS idx_seo_issues_severity ON seo_issues(severity);
CREATE INDEX IF NOT EXISTS idx_seo_issues_check ON seo_issues(check_name);

-- ── image_health_logs: broken image tracking ────────────────────
CREATE TABLE IF NOT EXISTS image_health_logs (
  id              BIGSERIAL PRIMARY KEY,
  checked_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  article_id      UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
  image_url       TEXT NOT NULL,
  http_status     INT NULL,                                -- response code or null for timeout
  is_broken       BOOLEAN NOT NULL DEFAULT FALSE,
  replaced        BOOLEAN NOT NULL DEFAULT FALSE,          -- was placeholder inserted?
  error_message   TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_image_health_article ON image_health_logs(article_id);
CREATE INDEX IF NOT EXISTS idx_image_health_broken ON image_health_logs(is_broken) WHERE is_broken = TRUE;
CREATE INDEX IF NOT EXISTS idx_image_health_checked ON image_health_logs(checked_at DESC);

-- ── SEO settings ────────────────────────────────────────────────
INSERT INTO site_settings (setting_key, setting_value, setting_group)
VALUES
  ('seo_audit_enabled',     'true',    'seo'),
  ('seo_audit_schedule',    'weekly',  'seo'),   -- manual, daily, weekly
  ('seo_last_run',          '',        'seo'),
  ('image_health_enabled',  'true',    'seo'),
  ('image_health_schedule', 'daily',   'seo'),
  ('image_placeholder_url', '/assets/img/image-unavailable.svg', 'seo')
ON CONFLICT (setting_key) DO NOTHING;

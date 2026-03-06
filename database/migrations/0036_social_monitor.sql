-- 0036_social_monitor.sql
-- Phase 7C: Social Media Monitor

-- ── social_mentions: tracked mentions across platforms ───────────
CREATE TABLE IF NOT EXISTS social_mentions (
  id              BIGSERIAL PRIMARY KEY,
  platform        VARCHAR(30) NOT NULL,                    -- twitter, facebook, reddit, google_news, web
  author_name     VARCHAR(200) NULL,
  author_url      TEXT NULL,
  content         TEXT NOT NULL,
  mention_url     TEXT NOT NULL,
  sentiment       VARCHAR(10) NOT NULL DEFAULT 'neutral',  -- positive, neutral, negative
  keyword_matched VARCHAR(200) NULL,                       -- which tracked keyword triggered this
  is_competitor   BOOLEAN NOT NULL DEFAULT FALSE,
  is_read         BOOLEAN NOT NULL DEFAULT FALSE,
  found_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_social_platform ON social_mentions(platform);
CREATE INDEX IF NOT EXISTS idx_social_sentiment ON social_mentions(sentiment);
CREATE INDEX IF NOT EXISTS idx_social_found ON social_mentions(found_at DESC);
CREATE INDEX IF NOT EXISTS idx_social_unread ON social_mentions(is_read) WHERE is_read = FALSE;
CREATE INDEX IF NOT EXISTS idx_social_competitor ON social_mentions(is_competitor) WHERE is_competitor = TRUE;

-- ── social_monitor_keywords: tracked keywords ───────────────────
CREATE TABLE IF NOT EXISTS social_monitor_keywords (
  id              BIGSERIAL PRIMARY KEY,
  keyword         VARCHAR(200) NOT NULL,
  is_competitor   BOOLEAN NOT NULL DEFAULT FALSE,          -- true = competitor outlet name
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_social_kw_unique ON social_monitor_keywords(LOWER(keyword));

-- ── Seed default keywords ───────────────────────────────────────
INSERT INTO social_monitor_keywords (keyword, is_competitor, is_active)
VALUES
  ('Northern Times',  FALSE, TRUE),
  ('northerntimes',   FALSE, TRUE),
  ('Daily Monitor',   TRUE,  TRUE),
  ('New Vision',      TRUE,  TRUE),
  ('NBS Television',  TRUE,  TRUE),
  ('The Observer UG', TRUE,  TRUE)
ON CONFLICT DO NOTHING;

-- ── Social monitor settings ─────────────────────────────────────
INSERT INTO site_settings (setting_key, setting_value, setting_group)
VALUES
  ('social_monitor_enabled',    'false',  'social'),
  ('social_monitor_interval',   '60',     'social'),   -- minutes
  ('social_alert_negative',     'true',   'social'),   -- email on negative spike
  ('social_alert_email',        '',       'social'),
  ('social_last_scan',          '',       'social')
ON CONFLICT (setting_key) DO NOTHING;

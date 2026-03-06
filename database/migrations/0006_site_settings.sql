CREATE TABLE IF NOT EXISTS site_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  setting_key VARCHAR(120) UNIQUE NOT NULL,
  setting_value TEXT NOT NULL,
  setting_group VARCHAR(60) NOT NULL DEFAULT 'general',
  setting_type VARCHAR(30) NOT NULL DEFAULT 'text',
  options JSONB NULL,
  label VARCHAR(120) NULL,
  description TEXT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_settings_group ON site_settings(setting_group);
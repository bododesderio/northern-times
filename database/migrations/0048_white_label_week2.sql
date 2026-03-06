-- Migration 0048: White-Label Week 2 settings
-- Adds app-level identity settings that were previously hardcoded.
-- All use ON CONFLICT DO NOTHING so re-running is safe.

INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
  -- Email sender identity (used by Mailer.php)
  ('mail_from_name',    '',  'general'),
  -- Custom robots.txt extra rules (appended by robotsTxt() controller)
  ('robots_txt_custom', '',  'general')
ON CONFLICT (setting_key) DO NOTHING;

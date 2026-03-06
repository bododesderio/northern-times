-- Migration: 0012_branding_settings.sql
-- Branding: logo, favicon, OG image — all stored as site_settings rows

INSERT INTO site_settings (setting_key, setting_value, setting_group, label) VALUES
  ('site_logo_url',     '',                       'branding', 'Site logo URL (empty = use text masthead)'),
  ('favicon_url',       '',                       'branding', 'Favicon URL (empty = no favicon)'),
  ('og_default_image',  '/assets/og-default.png', 'branding', 'Default OG / social share image')
ON CONFLICT (setting_key) DO NOTHING;

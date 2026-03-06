-- Phase 0: White-Label Refactor — add identity settings keys
-- These replace all hardcoded "NT Newsroom" / "Northern Times" strings throughout the app.
-- Run after all previous migrations.

INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
  ('site_abbreviation',       'NT',                     'general'),
  ('publisher_name',          'The Northern Times',      'general'),
  ('registration_number',     '',                        'general'),
  ('contact_address',         '',                        'general'),
  ('copyright_template',      '© {year} {publisher}. All rights reserved.', 'general'),
  ('default_crawl_author',    'NT Newsroom',             'general'),
  ('crawler_user_agent_name', 'NTCrawler/1.0',           'general')
ON CONFLICT (setting_key) DO NOTHING;

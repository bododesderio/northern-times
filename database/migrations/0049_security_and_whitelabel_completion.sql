-- Migration: 0049_security_and_whitelabel_completion.sql
-- Adds remaining white-label settings and system_log immutability rule.
-- All INSERTs use ON CONFLICT DO NOTHING — safe to re-run.

-- ── White-label settings ──────────────────────────────────────────
-- contact_email and admin_logo are new.
-- The rest may already exist from 0047/0048 — conflict guard handles it.

INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
  ('contact_email',        '',                                            'general'),
  ('admin_logo',           '',                                            'general'),
  ('site_abbreviation',    'NT',                                          'general'),
  ('publisher_name',       '',                                            'general'),
  ('contact_address',      '',                                            'general'),
  ('copyright_template',   '© {year} {publisher}. All rights reserved.', 'general'),
  ('default_crawl_author', '',                                            'general'),
  ('registration_number',  '',                                            'general')
ON CONFLICT (setting_key) DO NOTHING;

-- ── System log: append-only rule ─────────────────────────────────
-- system_log already has indexes from 0040. This just adds the DELETE rule.

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'system_log'
    ) THEN
        DROP RULE IF EXISTS no_delete_system_log ON system_log;
        EXECUTE '
            CREATE RULE no_delete_system_log AS
                ON DELETE TO system_log
                DO INSTEAD NOTHING
        ';
    END IF;
END
$$;

-- ── site_settings group index (not in 0006) ───────────────────────
CREATE INDEX IF NOT EXISTS idx_site_settings_group
    ON site_settings (setting_group);
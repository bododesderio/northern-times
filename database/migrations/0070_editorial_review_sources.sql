-- 0070: Add per-source editorial review toggle + 3-way publish mode
-- The global crawler_auto_publish setting becomes a 3-way:
--   'published'       = auto-publish (legacy default)
--   'pending_review'  = send to editorial review queue
--   'draft'           = save as draft

ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS require_review BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN crawl_sources.require_review IS 'If TRUE, articles from this source always go to pending_review regardless of global setting';

-- Migrate existing boolean setting to 3-way string
-- (existing 'true' → 'published', 'false' → 'draft')
UPDATE site_settings
   SET setting_value = 'published'
 WHERE setting_key = 'crawler_auto_publish' AND setting_value = 'true';

UPDATE site_settings
   SET setting_value = 'draft'
 WHERE setting_key = 'crawler_auto_publish' AND setting_value = 'false';

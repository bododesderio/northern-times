-- Multi-device ad images and link URLs
ALTER TABLE ad_slots ADD COLUMN IF NOT EXISTS content_mobile TEXT DEFAULT '';
ALTER TABLE ad_slots ADD COLUMN IF NOT EXISTS content_tablet TEXT DEFAULT '';
ALTER TABLE ad_slots ADD COLUMN IF NOT EXISTS link_url_mobile VARCHAR(500) DEFAULT '';
ALTER TABLE ad_slots ADD COLUMN IF NOT EXISTS link_url_tablet VARCHAR(500) DEFAULT '';

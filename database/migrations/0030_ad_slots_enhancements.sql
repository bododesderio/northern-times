-- Migration: 0030_ad_slots_enhancements.sql
-- Adds device targeting, responsive dimensions, nofollow, and custom CSS to ad_slots.

ALTER TABLE ad_slots
  ADD COLUMN IF NOT EXISTS device_target VARCHAR(10) NOT NULL DEFAULT 'all',
  ADD COLUMN IF NOT EXISTS max_width     VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS max_height    VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS custom_css    TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS nofollow      BOOLEAN NOT NULL DEFAULT TRUE,
  ADD COLUMN IF NOT EXISTS alt_text      VARCHAR(255) DEFAULT NULL;

-- device_target: 'all', 'mobile', 'desktop'
DO $$ BEGIN
  ALTER TABLE ad_slots
    ADD CONSTRAINT chk_ad_device_target
    CHECK (device_target IN ('all', 'mobile', 'desktop'));
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

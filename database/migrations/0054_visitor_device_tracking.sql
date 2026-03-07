-- Phase 12b: Enhanced visitor analytics — device, browser, OS tracking
-- Also tracks page_path per view for category analytics per city

ALTER TABLE site_visitors ADD COLUMN IF NOT EXISTS device_type VARCHAR(20) DEFAULT NULL;
ALTER TABLE site_visitors ADD COLUMN IF NOT EXISTS browser VARCHAR(50) DEFAULT NULL;
ALTER TABLE site_visitors ADD COLUMN IF NOT EXISTS os VARCHAR(50) DEFAULT NULL;

-- Index for device analytics queries
CREATE INDEX IF NOT EXISTS idx_visitors_device ON site_visitors (device_type, visit_date DESC) WHERE device_type IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_visitors_city_detail ON site_visitors (city, visit_date DESC) WHERE city IS NOT NULL;

-- Phase 12: Reader Heatmap — add geo columns to site_visitors
-- Stores coordinates on every visit for the world map dashboard widget.

ALTER TABLE site_visitors
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(9,6),
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(9,6),
    ADD COLUMN IF NOT EXISTS city      VARCHAR(100),
    ADD COLUMN IF NOT EXISTS country   VARCHAR(100),
    ADD COLUMN IF NOT EXISTS country_code VARCHAR(5),
    ADD COLUMN IF NOT EXISTS region    VARCHAR(100);

CREATE INDEX IF NOT EXISTS idx_visitors_geo
    ON site_visitors(visit_date DESC, city, country)
    WHERE latitude IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_visitors_country
    ON site_visitors(country_code, visit_date DESC);

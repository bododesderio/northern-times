-- Add full-page scraping columns to crawl_sources
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS full_page_scrape BOOLEAN DEFAULT FALSE;
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS content_selector VARCHAR(255) DEFAULT NULL;

-- Enable full-page scrape for Dokolo Post (WordPress — best results)
UPDATE crawl_sources SET full_page_scrape = TRUE WHERE name = 'Dokolo Post';

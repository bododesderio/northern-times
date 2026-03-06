-- 1. Delete all crawled articles
DELETE FROM articles WHERE source_hash IS NOT NULL;

-- 2. Reset crawl timestamps so all sources are due
UPDATE crawl_sources SET last_crawled_at = NULL, last_success_at = NULL;

-- 3. Enable full-page scraping for ALL sources
UPDATE crawl_sources SET full_page_scrape = TRUE;

-- 4. Enable image downloading for ALL sources
UPDATE crawl_sources SET download_images = TRUE;

-- 5. Clean up any orphaned media from previous crawls (optional)
-- DELETE FROM media_library WHERE source_type = 'crawled';

SELECT 'Reset complete. All crawled articles deleted, all sources ready for fresh crawl with full-page scraping.' AS status;

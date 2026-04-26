-- Clean up junk crawled articles:
-- 1. Delete articles with bloated content (> 100KB = scraped entire page)
-- 2. Delete duplicate titles (keep oldest)

-- Delete bloated articles (entire page scrapes)
DELETE FROM articles
WHERE is_crawled = TRUE
  AND LENGTH(content) > 102400;

-- Delete duplicate titles (keep the first one inserted)
DELETE FROM articles a
USING articles b
WHERE a.title = b.title
  AND a.id > b.id
  AND a.is_crawled = TRUE;

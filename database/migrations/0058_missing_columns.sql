-- 0058_missing_columns.sql
-- Add columns referenced in PHP models but missing from prior migrations.

-- reading_time: used in Article model INSERT/UPDATE and frontend views
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS reading_time INT NOT NULL DEFAULT 1;

-- download_images: used in CrawlSource model and AdminCrawlerController
ALTER TABLE crawl_sources
  ADD COLUMN IF NOT EXISTS download_images BOOLEAN NOT NULL DEFAULT TRUE;

-- show_in_sidebar: used in Category model and AdminCategoryController
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS show_in_sidebar BOOLEAN NOT NULL DEFAULT TRUE;

-- Index for sidebar category queries
CREATE INDEX IF NOT EXISTS idx_categories_sidebar
  ON categories (show_in_sidebar) WHERE show_in_sidebar = TRUE;

-- 0008_add_nav_columns_to_categories.sql
-- Add columns for dynamic navigation control

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS show_in_nav BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS sort_order INTEGER DEFAULT 50;

-- Set sensible defaults for existing rows
UPDATE categories
    SET show_in_nav = TRUE,
        sort_order = 50
    WHERE show_in_nav IS NULL OR sort_order IS NULL;

-- Optional: create index for faster sorting
CREATE INDEX IF NOT EXISTS idx_categories_nav_sort
    ON categories (show_in_nav DESC, sort_order ASC, name ASC);

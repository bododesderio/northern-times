-- Migration: 0060_fulltext_search.sql
-- Add tsvector full-text search to articles table

-- Add search_vector column
ALTER TABLE articles ADD COLUMN IF NOT EXISTS search_vector tsvector;

-- Populate existing articles
UPDATE articles SET search_vector =
    setweight(to_tsvector('english', COALESCE(title, '')), 'A') ||
    setweight(to_tsvector('english', COALESCE(excerpt, '')), 'B') ||
    setweight(to_tsvector('english', COALESCE(content, '')), 'C');

-- GIN index for fast lookups
CREATE INDEX IF NOT EXISTS idx_articles_search_vector ON articles USING GIN(search_vector);

-- Auto-update trigger
CREATE OR REPLACE FUNCTION articles_search_vector_update() RETURNS trigger AS $$
BEGIN
    NEW.search_vector :=
        setweight(to_tsvector('english', COALESCE(NEW.title, '')), 'A') ||
        setweight(to_tsvector('english', COALESCE(NEW.excerpt, '')), 'B') ||
        setweight(to_tsvector('english', COALESCE(NEW.content, '')), 'C');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_articles_search_vector ON articles;
CREATE TRIGGER trg_articles_search_vector
    BEFORE INSERT OR UPDATE OF title, excerpt, content ON articles
    FOR EACH ROW
    EXECUTE FUNCTION articles_search_vector_update();

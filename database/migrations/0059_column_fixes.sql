-- Migration: 0059_column_fixes.sql
-- Fix type mismatches and column issues found in system audit.

-- 1. popup_ab_tests.winner_id: BIGINT but popups.id is UUID
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name = 'popup_ab_tests' AND column_name = 'winner_id') THEN
        ALTER TABLE popup_ab_tests ALTER COLUMN winner_id TYPE UUID USING NULL;

        IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints
                       WHERE constraint_name = 'popup_ab_tests_winner_fkey'
                         AND table_name = 'popup_ab_tests') THEN
            ALTER TABLE popup_ab_tests
                ADD CONSTRAINT popup_ab_tests_winner_fkey
                FOREIGN KEY (winner_id) REFERENCES popups(id) ON DELETE SET NULL;
        END IF;
    END IF;
END $$;

-- 2. media_library.uploaded_by: ensure nullable (ON DELETE SET NULL requires it)
ALTER TABLE media_library
    ALTER COLUMN uploaded_by DROP NOT NULL;  -- safe even if already nullable

-- 3. Missing performance indexes
CREATE INDEX IF NOT EXISTS idx_article_tags_tag_id
    ON article_tags(tag_id);

CREATE INDEX IF NOT EXISTS idx_articles_status_published
    ON articles(status, published_at DESC)
    WHERE status = 'published';

CREATE INDEX IF NOT EXISTS idx_crawl_logs_finished
    ON crawl_logs(finished_at DESC)
    WHERE finished_at IS NOT NULL;

-- 4. story_clusters: clean orphans, then add NOT NULL to title
DELETE FROM story_clusters
    WHERE id NOT IN (SELECT DISTINCT story_cluster_id FROM articles WHERE story_cluster_id IS NOT NULL)
    AND canonical_article_id IS NULL;

-- Backfill any NULL titles before adding constraint
UPDATE story_clusters SET title = 'Untitled Cluster' WHERE title IS NULL;

ALTER TABLE story_clusters
    ALTER COLUMN title SET NOT NULL;

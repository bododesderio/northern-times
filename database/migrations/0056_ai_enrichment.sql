-- Migration: 0056_ai_enrichment.sql
-- Advanced AI enrichment: pgvector embeddings, summaries, sentiment,
-- quality scoring, NER entities, story clusters, adaptive scheduling

-- ═══ pgvector extension ══════════════════════════════════════
CREATE EXTENSION IF NOT EXISTS vector;

-- ═══ AI enrichment columns on articles ═══════════════════════
ALTER TABLE articles ADD COLUMN IF NOT EXISTS embedding vector(384);
ALTER TABLE articles ADD COLUMN IF NOT EXISTS ai_summary TEXT;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS sentiment VARCHAR(20);
ALTER TABLE articles ADD COLUMN IF NOT EXISTS sentiment_score REAL;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS quality_score SMALLINT;

-- Embedding index for cosine similarity search (semantic dedup + story clustering)
-- Using ivfflat for fast approximate nearest neighbor search
-- Note: ivfflat requires at least 1 row to build; will be created as empty initially
CREATE INDEX IF NOT EXISTS idx_articles_embedding
    ON articles USING hnsw (embedding vector_cosine_ops);

-- ═══ Story clusters ══════════════════════════════════════════
-- Groups articles covering the same story across different sources
CREATE TABLE IF NOT EXISTS story_clusters (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    canonical_article_id UUID REFERENCES articles(id) ON DELETE SET NULL,
    title VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE articles ADD COLUMN IF NOT EXISTS story_cluster_id UUID
    REFERENCES story_clusters(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_articles_story_cluster ON articles(story_cluster_id);

-- ═══ Article entities (NER) ══════════════════════════════════
CREATE TABLE IF NOT EXISTS article_entities (
    id BIGSERIAL PRIMARY KEY,
    article_id UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    entity_text VARCHAR(255) NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    salience REAL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_article_entities_article ON article_entities(article_id);
CREATE INDEX IF NOT EXISTS idx_article_entities_type ON article_entities(entity_type);
CREATE INDEX IF NOT EXISTS idx_article_entities_text ON article_entities(entity_text);

-- ═══ Tags (IF NOT EXISTS — may already exist) ════════════════
CREATE TABLE IF NOT EXISTS tags (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) UNIQUE NOT NULL,
    type VARCHAR(20) DEFAULT 'topic',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Add type column if tags table exists but lacks it
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'tags' AND column_name = 'type'
    ) THEN
        ALTER TABLE tags ADD COLUMN type VARCHAR(20) DEFAULT 'topic';
    END IF;
END $$;

-- Article-tags pivot (IF NOT EXISTS)
CREATE TABLE IF NOT EXISTS article_tags (
    article_id UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    tag_id BIGINT NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (article_id, tag_id)
);

-- ═══ Adaptive scheduling columns on crawl_sources ════════════
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS avg_articles_per_day REAL DEFAULT 0;
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS last_new_content_at TIMESTAMPTZ;
ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS consecutive_empty INT DEFAULT 0;

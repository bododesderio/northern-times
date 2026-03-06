-- ============================================================
-- 0043 — Article Revision History
-- Tracks every edit to an article for diff/restore.
-- ============================================================

CREATE TABLE IF NOT EXISTS article_revisions (
    id               SERIAL PRIMARY KEY,
    article_id       UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    user_id          UUID REFERENCES users(id) ON DELETE SET NULL,
    title            VARCHAR(500),
    content          TEXT,
    excerpt          TEXT,
    revision_number  INTEGER NOT NULL DEFAULT 1,
    word_count       INTEGER DEFAULT 0,
    created_at       TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_revisions_article ON article_revisions(article_id, revision_number DESC);
CREATE INDEX IF NOT EXISTS idx_revisions_user ON article_revisions(user_id);

-- Auto-prune trigger: keep last 50 revisions per article
CREATE OR REPLACE FUNCTION fn_prune_article_revisions()
RETURNS TRIGGER AS $$
BEGIN
    DELETE FROM article_revisions
    WHERE article_id = NEW.article_id
      AND id NOT IN (
          SELECT id FROM article_revisions
          WHERE article_id = NEW.article_id
          ORDER BY revision_number DESC
          LIMIT 50
      );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_prune_revisions ON article_revisions;

CREATE TRIGGER trg_prune_revisions
    AFTER INSERT ON article_revisions
    FOR EACH ROW
    EXECUTE FUNCTION fn_prune_article_revisions();
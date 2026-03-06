-- Migration: 0015_comments.sql
-- Article commenting system

CREATE TABLE IF NOT EXISTS comments (
  id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  article_id  UUID         NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
  parent_id   UUID         NULL REFERENCES comments(id) ON DELETE CASCADE,
  author_name VARCHAR(120) NOT NULL,
  author_email VARCHAR(255) NOT NULL,
  content     TEXT         NOT NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'pending',  -- pending, approved, spam, rejected
  ip_address  INET         NULL,
  user_agent  TEXT         NULL,
  created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_comments_article   ON comments(article_id);
CREATE INDEX IF NOT EXISTS idx_comments_status    ON comments(status);
CREATE INDEX IF NOT EXISTS idx_comments_parent    ON comments(parent_id);
CREATE INDEX IF NOT EXISTS idx_comments_created   ON comments(created_at DESC);

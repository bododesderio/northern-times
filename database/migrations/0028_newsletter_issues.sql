-- 0028_newsletter_issues.sql
-- Tracks each newsletter campaign sent by editors.

CREATE TABLE IF NOT EXISTS newsletter_issues (
  id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  subject      VARCHAR(500) NOT NULL,
  body_html    TEXT         NOT NULL DEFAULT '',
  article_ids  JSONB        NOT NULL DEFAULT '[]',
  sent_by      UUID         NULL REFERENCES users(id) ON DELETE SET NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'draft',
  sent_at      TIMESTAMPTZ  NULL,
  sent_count   INT          NOT NULL DEFAULT 0,
  failed_count INT          NOT NULL DEFAULT 0,
  created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_newsletter_issues_status ON newsletter_issues(status);

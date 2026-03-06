-- 0027_email_queue.sql
-- Email queue for newsletter bulk sends and transactional emails.

CREATE TABLE IF NOT EXISTS email_queue (
  id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  to_email     VARCHAR(255) NOT NULL,
  to_name      VARCHAR(255) NULL,
  subject      VARCHAR(500) NOT NULL,
  body_html    TEXT         NOT NULL,
  body_text    TEXT         NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
  attempts     INT          NOT NULL DEFAULT 0,
  last_error   TEXT         NULL,
  scheduled_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  sent_at      TIMESTAMPTZ  NULL,
  created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_queue_pending
  ON email_queue(status, scheduled_at)
  WHERE status = 'pending';

CREATE INDEX IF NOT EXISTS idx_email_queue_status ON email_queue(status);

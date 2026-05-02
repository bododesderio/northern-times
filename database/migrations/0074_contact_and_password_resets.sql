-- Migration 0074: Create contact_messages and password_resets tables
-- Previously created at runtime via CREATE TABLE IF NOT EXISTS in controllers

-- Contact form submissions
CREATE TABLE IF NOT EXISTS contact_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    ip_address INET,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_contact_messages_created ON contact_messages (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_contact_messages_unread ON contact_messages (is_read) WHERE is_read = FALSE;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets (token) WHERE used = FALSE;
CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets (email);

-- Rewriter performance: index on rewrite_status for cron queries (L-08 / M-09)
CREATE INDEX IF NOT EXISTS idx_articles_rewrite_status ON articles (rewrite_status) WHERE rewrite_status IS NOT NULL;

-- Migration: 0023_login_attempts.sql
-- Login rate limiting: tracks failed login attempts by IP

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGSERIAL    PRIMARY KEY,
  ip_address   INET         NOT NULL,
  email        VARCHAR(255) NOT NULL DEFAULT '',
  attempted_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip
  ON login_attempts(ip_address, attempted_at DESC);

CREATE INDEX IF NOT EXISTS idx_login_attempts_cleanup
  ON login_attempts(attempted_at);

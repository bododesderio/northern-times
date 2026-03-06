-- Migration: 0017_site_visitors.sql
-- Track unique daily visitors by IP (not page views — actual humans)
-- One row per IP per day. This gives us accurate unique visitor counts.

CREATE TABLE IF NOT EXISTS site_visitors (
  id          BIGSERIAL    PRIMARY KEY,
  ip_address  INET         NOT NULL,
  user_agent  TEXT         NULL,
  first_page  TEXT         NULL,
  visit_date  DATE         NOT NULL DEFAULT CURRENT_DATE,
  created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  UNIQUE(ip_address, visit_date)
);

CREATE INDEX IF NOT EXISTS idx_sv_date ON site_visitors(visit_date);
CREATE INDEX IF NOT EXISTS idx_sv_ip   ON site_visitors(ip_address);

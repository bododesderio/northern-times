-- Migration: 0019_breaking_news_and_socials.sql
-- 1. Add breaking news support to articles
-- 2. Add social media columns to users (facebook, linkedin, instagram)

-- ── Breaking news fields ──────────────────────────────────────────
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS is_breaking       BOOLEAN      NOT NULL DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS breaking_headline VARCHAR(300) NULL;

CREATE INDEX IF NOT EXISTS idx_articles_breaking ON articles(is_breaking) WHERE is_breaking = TRUE;

-- ── Author social profiles ────────────────────────────────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS facebook_url   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS linkedin_url   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS instagram_handle VARCHAR(80) NULL;

-- ── Mark one seed article as breaking for demo ────────────────────
UPDATE articles SET is_breaking = TRUE, breaking_headline = 'East Africa launches $4.2B trade corridor connecting six nations'
WHERE slug = 'east-africa-trade-corridor-six-nations' AND is_breaking = FALSE;

UPDATE articles SET is_breaking = TRUE, breaking_headline = 'New malaria vaccine reaches 500,000 children across East Africa'
WHERE slug = 'malaria-vaccine-rollout-east-africa' AND is_breaking = FALSE;

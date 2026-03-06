-- Ad Slots system
CREATE TABLE IF NOT EXISTS ad_slots (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  slot_name VARCHAR(50) UNIQUE NOT NULL,
  label VARCHAR(100) NOT NULL,
  ad_type VARCHAR(20) NOT NULL DEFAULT 'image',  -- image, html, adsense
  content TEXT,                                    -- image path, HTML code, or AdSense snippet
  link_url VARCHAR(500),                           -- click-through (for image type)
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  start_date TIMESTAMP NULL,
  end_date TIMESTAMP NULL,
  impressions INT NOT NULL DEFAULT 0,
  clicks INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Seed the 6 standard slots (inactive by default)
INSERT INTO ad_slots (slot_name, label, ad_type, is_active) VALUES
  ('top-banner',      'Top Leaderboard Banner', 'image', FALSE),
  ('sidebar',         'Sidebar Rectangle',      'image', FALSE),
  ('in-feed',         'In-Feed Banner',         'image', FALSE),
  ('in-article',      'In-Article',             'image', FALSE),
  ('below-article',   'Below Article',          'image', FALSE),
  ('article-sidebar', 'Article Sidebar',        'image', FALSE)
ON CONFLICT (slot_name) DO NOTHING;

-- Add score columns to articles for caching (optional, speeds up queries)
ALTER TABLE articles ADD COLUMN IF NOT EXISTS score_breaking SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS score_top SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS score_trending SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS scores_updated_at TIMESTAMP NULL;

-- Index for score-based queries
CREATE INDEX IF NOT EXISTS idx_articles_score_breaking ON articles (score_breaking DESC) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_articles_score_top ON articles (score_top DESC) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_articles_score_trending ON articles (score_trending DESC) WHERE status='published';

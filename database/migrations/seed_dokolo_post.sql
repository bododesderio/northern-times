-- Seed Dokolo Post crawl source
INSERT INTO crawl_sources (
  name, feed_url, website_url, logo_url, source_type, is_active,
  crawl_interval, default_category_id, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  attribution_text, nofollow
) VALUES (
  'Dokolo Post',
  'https://dokolopost.com/feed/',
  'https://dokolopost.com',
  NULL,
  'rss', TRUE,
  30,
  '64afe329-92af-48c3-9a6f-78bb96d11bbc',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "northern": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "dokolo": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "lango": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "lira": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "gulu": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "acholi": "64afe329-92af-48c3-9a6f-78bb96d11bbc"
  }'::jsonb,
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,#related-articles,.social-share,.sharedaddy',
  'Source: Dokolo Post',
  TRUE
);

-- Also fix Daily Monitor feed URL (try Nation Africa RSS)
UPDATE crawl_sources SET feed_url = 'https://nation.africa/uganda/rss' WHERE name = 'Daily Monitor';

-- Fix New Vision feed URL
UPDATE crawl_sources SET feed_url = 'https://www.newvision.co.ug/feed/' WHERE name = 'New Vision';

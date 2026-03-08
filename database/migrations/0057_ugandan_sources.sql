-- Migration: 0057_ugandan_sources.sql
-- Add 4 new Ugandan crawl sources: Nile Post, Observer, Independent UG, Uganda Radio Network
-- Also ensure region column exists on crawl_sources

ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS region VARCHAR(50) DEFAULT 'international';

-- Set region for existing Ugandan sources
UPDATE crawl_sources SET region = 'ugandan' WHERE website_url ILIKE '%monitor.co.ug%' OR website_url ILIKE '%newvision.co.ug%' OR website_url ILIKE '%dokolopost.com%' OR website_url ILIKE '%nation.africa%';

-- Category UUIDs:
--   Top Stories     = dd929398-6a5b-43e2-9f1e-4445d9cde194
--   Northern Uganda = 64afe329-92af-48c3-9a6f-78bb96d11bbc
--   Business        = e0046bd8-1301-493c-9dde-d5ad98d17945
--   Opinion         = bb9890e7-83a4-448c-a8dc-bc0f57e4cedf
--   Politics        = d82c59cd-7618-450e-97b6-12e6cc65d355
--   Technology      = cc1828a7-fd2a-4c46-ab39-516abd9172d2
--   World           = 5f65cdc8-a083-411c-8338-6c2684eb732f
--   Health          = 15a554c0-ac0b-4ff5-ad94-db17f7a7192f
--   Sports          = 7d908d92-418a-4f0c-9727-0bf915ee9b3e

-- 1. Nile Post
INSERT INTO crawl_sources (
  name, feed_url, website_url, source_type, is_active,
  crawl_interval, default_category_id, region, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  content_selector, attribution_text, nofollow
) VALUES (
  'Nile Post',
  'https://nilepost.co.ug/feed/',
  'https://nilepost.co.ug',
  'rss', TRUE,
  30,
  'dd929398-6a5b-43e2-9f1e-4445d9cde194',
  'ugandan',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2"
  }'::jsonb,
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,.social-share,.sharedaddy,#related-articles,.wp-block-newspack-blocks-homepage-articles',
  '.entry-content,.post-content',
  'Source: Nile Post',
  TRUE
) ON CONFLICT DO NOTHING;

-- 2. The Observer (Uganda)
INSERT INTO crawl_sources (
  name, feed_url, website_url, source_type, is_active,
  crawl_interval, default_category_id, region, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  content_selector, attribution_text, nofollow
) VALUES (
  'The Observer',
  'https://observer.ug/feed/',
  'https://observer.ug',
  'rss', TRUE,
  30,
  'dd929398-6a5b-43e2-9f1e-4445d9cde194',
  'ugandan',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2"
  }'::jsonb,
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
  '.entry-content,.post-content',
  'Source: The Observer',
  TRUE
) ON CONFLICT DO NOTHING;

-- 3. The Independent (Uganda)
INSERT INTO crawl_sources (
  name, feed_url, website_url, source_type, is_active,
  crawl_interval, default_category_id, region, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  content_selector, attribution_text, nofollow
) VALUES (
  'The Independent',
  'https://www.independent.co.ug/feed/',
  'https://www.independent.co.ug',
  'rss', TRUE,
  30,
  'dd929398-6a5b-43e2-9f1e-4445d9cde194',
  'ugandan',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2"
  }'::jsonb,
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,.social-share,.sharedaddy,#related-articles,.td-a-rec,.tdi_ad',
  '.entry-content,.tdb-block-inner',
  'Source: The Independent',
  TRUE
) ON CONFLICT DO NOTHING;

-- 4. Uganda Radio Network
INSERT INTO crawl_sources (
  name, feed_url, website_url, source_type, is_active,
  crawl_interval, default_category_id, region, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  content_selector, attribution_text, nofollow
) VALUES (
  'Uganda Radio Network',
  'https://ugandaradionetwork.net/feed/',
  'https://ugandaradionetwork.net',
  'rss', TRUE,
  30,
  'dd929398-6a5b-43e2-9f1e-4445d9cde194',
  'ugandan',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "northern": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "gulu": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "lira": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "acholi": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "lango": "64afe329-92af-48c3-9a6f-78bb96d11bbc"
  }'::jsonb,
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
  '.entry-content,.post-content',
  'Source: Uganda Radio Network',
  TRUE
) ON CONFLICT DO NOTHING;

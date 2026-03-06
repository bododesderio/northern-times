-- Seed crawl sources for Northern Times
-- Category UUIDs from your database:
--   Top Stories    = dd929398-6a5b-43e2-9f1e-4445d9cde194
--   Northern Uganda= 64afe329-92af-48c3-9a6f-78bb96d11bbc
--   Business       = e0046bd8-1301-493c-9dde-d5ad98d17945
--   Opinion        = bb9890e7-83a4-448c-a8dc-bc0f57e4cedf
--   Politics       = d82c59cd-7618-450e-97b6-12e6cc65d355
--   Technology     = cc1828a7-fd2a-4c46-ab39-516abd9172d2
--   World          = 5f65cdc8-a083-411c-8338-6c2684eb732f
--   Health         = 15a554c0-ac0b-4ff5-ad94-db17f7a7192f
--   Sports         = 7d908d92-418a-4f0c-9727-0bf915ee9b3e

-- ══════════════════════════════════════════════════════════════
-- 1. DAILY MONITOR (Uganda)
-- ══════════════════════════════════════════════════════════════
INSERT INTO crawl_sources (
  name, feed_url, website_url, logo_url, source_type, is_active,
  crawl_interval, default_category_id, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  attribution_text, nofollow
) VALUES (
  'Daily Monitor',
  'https://www.monitor.co.ug/uganda/rss',
  'https://www.monitor.co.ug',
  'https://www.monitor.co.ug/resource/blob/2095586/e69cd4e9fb2fc02d0a76e21f11b07472/monitor-logo-data.png',
  'rss', TRUE,
  30,
  'dd929398-6a5b-43e2-9f1e-4445d9cde194',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "football": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "northern": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "gulu": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "lira": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "acholi": "64afe329-92af-48c3-9a6f-78bb96d11bbc"
  }'::jsonb,
  'uganda,kampala,museveni,parliament,gulu,lira,northern,acholi,election,police,army,court',
  'sponsored,advertisement,casino,betting,obituary',
  20,
  '.ads,.sidebar,#related-articles,.social-share,.newsletter-signup',
  'Source: Daily Monitor',
  TRUE
);

-- ══════════════════════════════════════════════════════════════
-- 2. NEW VISION (Uganda)
-- ══════════════════════════════════════════════════════════════
INSERT INTO crawl_sources (
  name, feed_url, website_url, logo_url, source_type, is_active,
  crawl_interval, default_category_id, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  attribution_text, nofollow
) VALUES (
  'New Vision',
  'https://www.newvision.co.ug/feed',
  'https://www.newvision.co.ug',
  'https://www.newvision.co.ug/w-content/images/logo.png',
  'rss', TRUE,
  30,
  'dd929398-6a5b-43e2-9f1e-4445d9cde194',
  '{
    "news": "dd929398-6a5b-43e2-9f1e-4445d9cde194",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "football": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "northern": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "gulu": "64afe329-92af-48c3-9a6f-78bb96d11bbc"
  }'::jsonb,
  'uganda,kampala,museveni,parliament,gulu,lira,northern,election,police,army,court',
  'sponsored,advertisement,casino,betting,obituary',
  20,
  '.ads,.sidebar,#related-articles,.social-share',
  'Source: New Vision',
  TRUE
);

-- ══════════════════════════════════════════════════════════════
-- 3. THE GUARDIAN (World News + Africa)
-- ══════════════════════════════════════════════════════════════
INSERT INTO crawl_sources (
  name, feed_url, website_url, logo_url, source_type, is_active,
  crawl_interval, default_category_id, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  attribution_text, nofollow
) VALUES (
  'The Guardian',
  'https://www.theguardian.com/world/rss',
  'https://www.theguardian.com',
  'https://assets.guim.co.uk/images/guardian-logo-rss.c45beb1bafa34b347ac333af2e6fe23f.png',
  'rss', TRUE,
  30,
  '5f65cdc8-a083-411c-8338-6c2684eb732f',
  '{
    "world": "5f65cdc8-a083-411c-8338-6c2684eb732f",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "football": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "africa": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "middleeast": "5f65cdc8-a083-411c-8338-6c2684eb732f",
    "us-news": "5f65cdc8-a083-411c-8338-6c2684eb732f",
    "europe": "5f65cdc8-a083-411c-8338-6c2684eb732f"
  }'::jsonb,
  'trump,israel,iran,war,bomb,breaking,africa,uganda,election,conflict,sanctions,military,nuclear,weapons,politics,global',
  'sponsored,celebrity,gossip,horoscope,crossword,recipe',
  20,
  '.ad-slot,.js-ad-slot,#aside-body,.submeta',
  'Source: The Guardian',
  TRUE
);

-- ══════════════════════════════════════════════════════════════
-- 4. AL JAZEERA (Global + Middle East)
-- ══════════════════════════════════════════════════════════════
INSERT INTO crawl_sources (
  name, feed_url, website_url, logo_url, source_type, is_active,
  crawl_interval, default_category_id, category_map,
  keyword_include, keyword_exclude, max_articles, strip_selectors,
  attribution_text, nofollow
) VALUES (
  'Al Jazeera',
  'https://www.aljazeera.com/xml/rss/all.xml',
  'https://www.aljazeera.com',
  'https://www.aljazeera.com/mf/img/aje-logo.png',
  'rss', TRUE,
  30,
  '5f65cdc8-a083-411c-8338-6c2684eb732f',
  '{
    "news": "5f65cdc8-a083-411c-8338-6c2684eb732f",
    "world": "5f65cdc8-a083-411c-8338-6c2684eb732f",
    "politics": "d82c59cd-7618-450e-97b6-12e6cc65d355",
    "sport": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "sports": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "football": "7d908d92-418a-4f0c-9727-0bf915ee9b3e",
    "economy": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "business": "e0046bd8-1301-493c-9dde-d5ad98d17945",
    "health": "15a554c0-ac0b-4ff5-ad94-db17f7a7192f",
    "technology": "cc1828a7-fd2a-4c46-ab39-516abd9172d2",
    "opinion": "bb9890e7-83a4-448c-a8dc-bc0f57e4cedf",
    "africa": "64afe329-92af-48c3-9a6f-78bb96d11bbc",
    "middleeast": "5f65cdc8-a083-411c-8338-6c2684eb732f"
  }'::jsonb,
  'trump,israel,iran,war,bomb,breaking,africa,uganda,military,conflict,sanctions,nuclear,weapons,politics,hamas,hezbollah,gaza,global,drone,attack',
  'sponsored,advertisement,podcast,documentary,recipe',
  20,
  '.article-featured-media-credit,.article-body-artSource,.more-on',
  'Source: Al Jazeera',
  TRUE
);

-- Enable the crawler
UPDATE site_settings SET setting_value = 'true' WHERE setting_key = 'crawler_enabled';

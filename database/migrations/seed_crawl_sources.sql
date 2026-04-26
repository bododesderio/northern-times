-- Seed crawl sources for Northern Times
-- Uses slug-based category lookups (portable across DB instances)

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
  (SELECT id FROM categories WHERE slug = 'top-stories' LIMIT 1),
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'football',   (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'northern',   (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'gulu',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'lira',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'acholi',     (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1)
  )),
  'uganda,kampala,museveni,parliament,gulu,lira,northern,acholi,election,police,army,court',
  'sponsored,advertisement,casino,betting,obituary',
  20,
  '.ads,.sidebar,#related-articles,.social-share,.newsletter-signup',
  'Source: Daily Monitor',
  TRUE
) ON CONFLICT DO NOTHING;

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
  (SELECT id FROM categories WHERE slug = 'top-stories' LIMIT 1),
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'football',   (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'northern',   (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'gulu',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1)
  )),
  'uganda,kampala,museveni,parliament,gulu,lira,northern,election,police,army,court',
  'sponsored,advertisement,casino,betting,obituary',
  20,
  '.ads,.sidebar,#related-articles,.social-share',
  'Source: New Vision',
  TRUE
) ON CONFLICT DO NOTHING;

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
  (SELECT id FROM categories WHERE slug = 'world' LIMIT 1),
  (SELECT jsonb_build_object(
    'world',      (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'football',   (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'africa',     (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'middleeast', (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1),
    'us-news',    (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1),
    'europe',     (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1)
  )),
  'trump,israel,iran,war,bomb,breaking,africa,uganda,election,conflict,sanctions,military,nuclear,weapons,politics,global',
  'sponsored,celebrity,gossip,horoscope,crossword,recipe',
  20,
  '.ad-slot,.js-ad-slot,#aside-body,.submeta',
  'Source: The Guardian',
  TRUE
) ON CONFLICT DO NOTHING;

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
  (SELECT id FROM categories WHERE slug = 'world' LIMIT 1),
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1),
    'world',      (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'football',   (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'economy',    (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'africa',     (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'middleeast', (SELECT id::text FROM categories WHERE slug = 'world' LIMIT 1)
  )),
  'trump,israel,iran,war,bomb,breaking,africa,uganda,military,conflict,sanctions,nuclear,weapons,politics,hamas,hezbollah,gaza,global,drone,attack',
  'sponsored,advertisement,podcast,documentary,recipe',
  20,
  '.article-featured-media-credit,.article-body-artSource,.more-on',
  'Source: Al Jazeera',
  TRUE
) ON CONFLICT DO NOTHING;

-- Enable the crawler
UPDATE site_settings SET setting_value = 'true' WHERE setting_key = 'crawler_enabled';

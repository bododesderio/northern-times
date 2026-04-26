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
  (SELECT id FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'northern',   (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'dokolo',     (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'lango',      (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'lira',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'gulu',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'acholi',     (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1)
  )),
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,#related-articles,.social-share,.sharedaddy',
  'Source: Dokolo Post',
  TRUE
) ON CONFLICT DO NOTHING;

-- Also fix Daily Monitor feed URL (try Nation Africa RSS)
UPDATE crawl_sources SET feed_url = 'https://nation.africa/uganda/rss' WHERE name = 'Daily Monitor';

-- Fix New Vision feed URL
UPDATE crawl_sources SET feed_url = 'https://www.newvision.co.ug/feed/' WHERE name = 'New Vision';

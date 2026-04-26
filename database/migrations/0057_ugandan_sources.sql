-- Migration: 0057_ugandan_sources.sql
-- Add 4 new Ugandan crawl sources: Nile Post, Observer, Independent UG, Uganda Radio Network
-- Also ensure region column exists on crawl_sources
-- Uses slug-based category lookups (portable across DB instances)

ALTER TABLE crawl_sources ADD COLUMN IF NOT EXISTS region VARCHAR(50) DEFAULT 'international';

-- Set region for existing Ugandan sources
UPDATE crawl_sources SET region = 'ugandan' WHERE website_url ILIKE '%monitor.co.ug%' OR website_url ILIKE '%newvision.co.ug%' OR website_url ILIKE '%dokolopost.com%' OR website_url ILIKE '%nation.africa%';

-- Ensure required categories exist (idempotent)
INSERT INTO categories (id, name, slug, created_at) VALUES
  (gen_random_uuid(), 'Business',    'business',       NOW()),
  (gen_random_uuid(), 'Opinion',     'opinion',        NOW()),
  (gen_random_uuid(), 'Politics',    'politics',       NOW()),
  (gen_random_uuid(), 'Technology',  'technology',     NOW()),
  (gen_random_uuid(), 'World',       'world',          NOW()),
  (gen_random_uuid(), 'Health',      'health',         NOW()),
  (gen_random_uuid(), 'Sports',      'sports',         NOW()),
  (gen_random_uuid(), 'Crime & Security', 'crime-security', NOW()),
  (gen_random_uuid(), 'National',    'national',       NOW()),
  (gen_random_uuid(), 'Entertainment', 'entertainment', NOW()),
  (gen_random_uuid(), 'Education',   'education',      NOW()),
  (gen_random_uuid(), 'Environment', 'environment',    NOW()),
  (gen_random_uuid(), 'Lifestyle',   'lifestyle',      NOW())
ON CONFLICT (slug) DO NOTHING;

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
  (SELECT id FROM categories WHERE slug = 'top-stories' LIMIT 1),
  'ugandan',
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1)
  )),
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
  (SELECT id FROM categories WHERE slug = 'top-stories' LIMIT 1),
  'ugandan',
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1)
  )),
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
  (SELECT id FROM categories WHERE slug = 'top-stories' LIMIT 1),
  'ugandan',
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'technology', (SELECT id::text FROM categories WHERE slug = 'technology' LIMIT 1)
  )),
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
  (SELECT id FROM categories WHERE slug = 'top-stories' LIMIT 1),
  'ugandan',
  (SELECT jsonb_build_object(
    'news',       (SELECT id::text FROM categories WHERE slug = 'top-stories' LIMIT 1),
    'politics',   (SELECT id::text FROM categories WHERE slug = 'politics' LIMIT 1),
    'business',   (SELECT id::text FROM categories WHERE slug = 'business' LIMIT 1),
    'sport',      (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'sports',     (SELECT id::text FROM categories WHERE slug = 'sports' LIMIT 1),
    'health',     (SELECT id::text FROM categories WHERE slug = 'health' LIMIT 1),
    'opinion',    (SELECT id::text FROM categories WHERE slug = 'opinion' LIMIT 1),
    'northern',   (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'gulu',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'lira',       (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'acholi',     (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1),
    'lango',      (SELECT id::text FROM categories WHERE slug = 'northern-uganda' LIMIT 1)
  )),
  '',
  'sponsored,advertisement,casino,betting',
  20,
  '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
  '.entry-content,.post-content',
  'Source: Uganda Radio Network',
  TRUE
) ON CONFLICT DO NOTHING;

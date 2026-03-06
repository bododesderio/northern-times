-- 1. Clear source names from display_author (so byline falls back to actual user)
UPDATE articles SET display_author = NULL
WHERE display_author IN ('Al Jazeera', 'The Guardian', 'Dokolo Post', 'Daily Monitor', 'New Vision');

-- 2. Strip old "Source: X" attribution paragraphs from content
UPDATE articles SET content = regexp_replace(
  content,
  '<p[^>]*class="crawled-attribution"[^>]*>.*?</p>',
  '',
  'gi'
) WHERE content LIKE '%crawled-attribution%';

-- 3. Strip WordPress "The post X appeared first on Y" boilerplate
UPDATE articles SET content = regexp_replace(
  content,
  '<p[^>]*>\s*The post\s+<a[^>]*>.*?</a>\s+appeared first on\s+<a[^>]*>.*?</a>\.\s*</p>',
  '',
  'gi'
) WHERE content LIKE '%appeared first on%';

-- 4. Also strip plain text version
UPDATE articles SET content = regexp_replace(
  content,
  '<p[^>]*>\s*The post\s+.{5,300}\s+appeared first on\s+.{3,100}\.\s*</p>',
  '',
  'gi'
) WHERE content LIKE '%appeared first on%';

-- Done
SELECT 'Cleanup complete' AS status;

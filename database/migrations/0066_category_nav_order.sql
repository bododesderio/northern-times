-- Set category nav priority and hide secondary categories from nav

-- Priority categories (show in nav, ordered)
UPDATE categories SET sort_order = 1, show_in_nav = TRUE WHERE slug = 'top-stories';
UPDATE categories SET sort_order = 2, show_in_nav = TRUE WHERE slug = 'northern-uganda';
UPDATE categories SET sort_order = 3, show_in_nav = TRUE WHERE slug = 'politics';
UPDATE categories SET sort_order = 4, show_in_nav = TRUE WHERE slug = 'national';
UPDATE categories SET sort_order = 5, show_in_nav = TRUE WHERE slug = 'business';
UPDATE categories SET sort_order = 6, show_in_nav = TRUE WHERE slug = 'sports';
UPDATE categories SET sort_order = 7, show_in_nav = TRUE WHERE slug = 'health';
UPDATE categories SET sort_order = 8, show_in_nav = TRUE WHERE slug = 'world';
UPDATE categories SET sort_order = 9, show_in_nav = TRUE WHERE slug = 'opinion';
UPDATE categories SET sort_order = 10, show_in_nav = TRUE WHERE slug = 'technology';

-- Hide secondary categories from nav (still accessible via category pages)
UPDATE categories SET show_in_nav = FALSE, sort_order = 50 WHERE slug IN (
  'education', 'entertainment', 'environment', 'lifestyle', 'crime-security'
);

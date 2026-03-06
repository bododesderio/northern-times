-- Adds a per-article display author override (used for bylines).
-- Safe to run once.

BEGIN;

ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS display_author VARCHAR(120);

-- Optional: You can backfill existing rows if you want.
-- Uncomment ONE of the following options if needed:

-- Option A: Default all existing to 'Admin'
-- UPDATE articles SET display_author = 'Admin' WHERE display_author IS NULL;

-- Option B: Default existing to current user's username
-- UPDATE articles a
-- SET display_author = u.username
-- FROM users u
-- WHERE a.author_id = u.id AND a.display_author IS NULL;

COMMIT;
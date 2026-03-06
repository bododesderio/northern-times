-- ═══ ROLES TABLE ═══
CREATE TABLE IF NOT EXISTS roles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  slug VARCHAR(50) UNIQUE NOT NULL,
  label VARCHAR(100) NOT NULL,
  description TEXT,
  permissions JSONB NOT NULL DEFAULT '[]',
  is_system BOOLEAN NOT NULL DEFAULT FALSE,
  color VARCHAR(20) DEFAULT '#666',
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Seed default system roles
INSERT INTO roles (slug, label, description, permissions, is_system, color, sort_order) VALUES
  ('super_admin', 'Super Admin', 'Full access to everything including user management, settings, and role assignment.',
   '["*"]', TRUE, '#cc0000', 1),
  ('editor', 'Editor', 'Can manage all articles, categories, media, comments, and site settings. Cannot manage users.',
   '["articles.*","categories.*","media.*","comments.*","settings.*","subscribers.*","ads.*"]', TRUE, '#1a6bbf', 2),
  ('author', 'Author', 'Can write and edit their own articles. Can upload media. Cannot manage other users'' content.',
   '["articles.own","media.upload"]', TRUE, '#4a9a4a', 3)
ON CONFLICT (slug) DO NOTHING;

-- Make articles.author_id nullable (so we can SET NULL on user delete)
ALTER TABLE articles ALTER COLUMN author_id DROP NOT NULL;

-- Add ON DELETE SET NULL to articles FK (drop + recreate)
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'articles_author_id_fkey' AND table_name = 'articles'
  ) THEN
    ALTER TABLE articles DROP CONSTRAINT articles_author_id_fkey;
  END IF;
END $$;

ALTER TABLE articles
  ADD CONSTRAINT articles_author_id_fkey
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL;

-- Same for comments if they reference users
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'comments_user_id_fkey' AND table_name = 'comments'
  ) THEN
    ALTER TABLE comments DROP CONSTRAINT comments_user_id_fkey;
  END IF;
EXCEPTION WHEN undefined_table THEN NULL;
END $$;

-- Same for media_library uploaded_by
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'media_library_uploaded_by_fkey' AND table_name = 'media_library'
  ) THEN
    ALTER TABLE media_library DROP CONSTRAINT media_library_uploaded_by_fkey;
  END IF;
  
  ALTER TABLE media_library
    ADD CONSTRAINT media_library_uploaded_by_fkey
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

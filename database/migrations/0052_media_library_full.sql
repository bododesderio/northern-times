-- 0052_media_library_full.sql
-- Creates the media_library table with the full schema the MediaItem model expects.
-- Safe to run on installs that already have the table — adds missing columns only.

-- Ensure ENUMs exist
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'media_type') THEN
    CREATE TYPE media_type AS ENUM ('image','video','audio','document');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'media_source_type') THEN
    CREATE TYPE media_source_type AS ENUM ('upload','external','embed');
  END IF;
END $$;

-- Create table if it doesn't exist (full schema)
CREATE TABLE IF NOT EXISTS media_library (
  id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  media_type     media_type   NOT NULL DEFAULT 'image',
  source_type    media_source_type NOT NULL DEFAULT 'upload',
  title          VARCHAR(255) NULL,
  description    TEXT         NULL,
  folder         VARCHAR(120) NOT NULL DEFAULT 'Uploads',
  file_path      TEXT         NULL,
  public_url     TEXT         NULL,
  webp_url       TEXT         NULL,
  thumbnail_url  TEXT         NULL,
  original_name  VARCHAR(255) NULL,
  mime_type      VARCHAR(100) NULL,
  file_size      BIGINT       NULL,
  width          INT          NULL,
  height         INT          NULL,
  external_url   TEXT         NULL,
  embed_code     TEXT         NULL,
  thumbnail_path TEXT         NULL,
  dimensions     VARCHAR(50)  NULL,
  duration       INT          NULL,
  social_platform VARCHAR(50) NULL,
  tags           TEXT[]       NULL,
  sha256         CHAR(64)     NULL,
  uploaded_by    UUID         NULL REFERENCES users(id) ON DELETE SET NULL,
  created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Add any missing columns to existing installs
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='folder') THEN
    ALTER TABLE media_library ADD COLUMN folder VARCHAR(120) NOT NULL DEFAULT 'Uploads';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='public_url') THEN
    ALTER TABLE media_library ADD COLUMN public_url TEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='webp_url') THEN
    ALTER TABLE media_library ADD COLUMN webp_url TEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='thumbnail_url') THEN
    ALTER TABLE media_library ADD COLUMN thumbnail_url TEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='original_name') THEN
    ALTER TABLE media_library ADD COLUMN original_name VARCHAR(255) NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='mime_type') THEN
    ALTER TABLE media_library ADD COLUMN mime_type VARCHAR(100) NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='file_size') THEN
    ALTER TABLE media_library ADD COLUMN file_size BIGINT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='width') THEN
    ALTER TABLE media_library ADD COLUMN width INT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='media_library' AND column_name='height') THEN
    ALTER TABLE media_library ADD COLUMN height INT NULL;
  END IF;
END $$;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_media_type        ON media_library(media_type);
CREATE INDEX IF NOT EXISTS idx_media_uploaded_by ON media_library(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_media_sha256      ON media_library(sha256);
CREATE INDEX IF NOT EXISTS idx_media_folder      ON media_library(folder);
CREATE INDEX IF NOT EXISTS idx_media_created     ON media_library(created_at DESC);

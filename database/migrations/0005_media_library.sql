DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'media_type') THEN
    CREATE TYPE media_type AS ENUM ('image','video','audio','document');
  END IF;

  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'media_source_type') THEN
    CREATE TYPE media_source_type AS ENUM ('upload','external','embed');
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS media_library (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  media_type media_type NOT NULL,
  source_type media_source_type NOT NULL,
  title VARCHAR(255) NULL,
  description TEXT NULL,

  file_path TEXT NULL,
  external_url TEXT NULL,
  embed_code TEXT NULL,

  thumbnail_path TEXT NULL,
  dimensions VARCHAR(50) NULL,
  duration INT NULL,

  social_platform VARCHAR(50) NULL,
  tags TEXT[] NULL,

  uploaded_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,

  sha256 CHAR(64) NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_media_type ON media_library(media_type);
CREATE INDEX IF NOT EXISTS idx_media_uploaded_by ON media_library(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_media_sha256 ON media_library(sha256);
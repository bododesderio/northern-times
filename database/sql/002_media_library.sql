CREATE TABLE IF NOT EXISTS media_library (
  -- Standardize ID to UUID for consistency with other tables
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  
  media_type VARCHAR(20) NOT NULL,              -- image, video, audio, document, other
  source_type VARCHAR(20) NOT NULL DEFAULT 'upload',  -- upload, external
  title VARCHAR(255),
  description TEXT,
  folder VARCHAR(100) NOT NULL DEFAULT 'Articles',
  tags TEXT,

  file_path TEXT,          -- relative path inside storage/uploads
  public_url TEXT,         -- /uploads/....
  webp_url TEXT,           -- /uploads/...webp (if generated)
  thumbnail_url TEXT,      -- /uploads/...thumb

  original_name TEXT,
  mime_type TEXT,
  file_size BIGINT DEFAULT 0,

  sha256 CHAR(64) NOT NULL,
  width INT,
  height INT,

  -- CRITICAL FIX: Changed from BIGINT to UUID to match your user IDs
  uploaded_by uuid, 
  
  -- Use TIMESTAMPTZ to match your newsletter table and avoid timezone bugs
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_media_sha256 ON media_library(sha256);
CREATE INDEX IF NOT EXISTS idx_media_type ON media_library(media_type);
CREATE INDEX IF NOT EXISTS idx_media_folder ON media_library(folder);
CREATE INDEX IF NOT EXISTS idx_media_created_at ON media_library(created_at DESC);
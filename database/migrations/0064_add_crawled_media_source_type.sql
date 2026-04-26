-- Add 'crawled' value to media_source_type enum for crawler-downloaded images
ALTER TYPE media_source_type ADD VALUE IF NOT EXISTS 'crawled';

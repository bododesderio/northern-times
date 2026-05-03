-- Migration: 0075_fix_topic_follows_id_type.sql
-- Fix topic_follows.follow_id from UUID to BIGINT to match
-- categories.id and tags.id which are BIGINT PRIMARY KEY.
-- UUID cannot be cast to BIGINT; drop and recreate the column.
-- Existing follow data is lost (follow_id was UUID — incompatible with BIGINT FKs).

ALTER TABLE topic_follows DROP COLUMN follow_id;
ALTER TABLE topic_follows ADD COLUMN follow_id BIGINT NOT NULL DEFAULT 0;
ALTER TABLE topic_follows ALTER COLUMN follow_id DROP DEFAULT;

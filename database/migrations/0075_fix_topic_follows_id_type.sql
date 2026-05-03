-- Migration: 0075_fix_topic_follows_id_type.sql
-- Fix topic_follows.follow_id from VARCHAR(255) to BIGINT to match
-- categories.id and tags.id which are BIGINT PRIMARY KEY.
-- VARCHAR(255) worked via implicit cast but caused inefficient index scans.

ALTER TABLE topic_follows
    ALTER COLUMN follow_id TYPE BIGINT USING follow_id::BIGINT;

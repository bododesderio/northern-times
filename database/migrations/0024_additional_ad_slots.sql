-- Migration: 0024_additional_ad_slots.sql
-- Add footer and homepage-spotlight ad slots

INSERT INTO ad_slots (slot_name, label, ad_type, is_active) VALUES
  ('footer',              'Footer Banner',          'image', FALSE),
  ('homepage-spotlight',  'Homepage Spotlight',      'image', FALSE)
ON CONFLICT (slot_name) DO NOTHING;

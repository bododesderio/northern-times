-- Migration: 0039_masthead_ad_slots.sql
-- Add masthead-left and masthead-right ad positions
-- FIX: Include `label` column (NOT NULL constraint on ad_slots.label)

INSERT INTO ad_slots (slot_name, label, ad_type, content, is_active, device_target, max_width, max_height, alt_text)
VALUES
  ('masthead-left',  'Masthead Left',  'image', '', FALSE, 'desktop', '300px', '100px', 'Advertisement'),
  ('masthead-right', 'Masthead Right', 'image', '', FALSE, 'desktop', '300px', '100px', 'Advertisement')
ON CONFLICT (slot_name) DO NOTHING;

-- Also add sidebar-left if it doesn't exist
INSERT INTO ad_slots (slot_name, label, ad_type, content, is_active, device_target, alt_text)
VALUES
  ('sidebar-left', 'Sidebar Left', 'image', '', FALSE, 'desktop', 'Advertisement')
ON CONFLICT (slot_name) DO NOTHING;

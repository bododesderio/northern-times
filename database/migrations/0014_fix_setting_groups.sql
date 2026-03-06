-- Fix: Update setting_group for existing theme settings
-- These were saved as 'general' because the old controller didn't set the group
UPDATE site_settings SET setting_group = 'theme'    WHERE setting_key LIKE 'theme_%' AND setting_group = 'general';
UPDATE site_settings SET setting_group = 'branding'  WHERE setting_key IN ('site_logo_url', 'favicon_url', 'og_default_image') AND setting_group = 'general';
UPDATE site_settings SET setting_group = 'system'    WHERE setting_key = 'theme_cache_bust';

-- Migration: 0011_theme_settings.sql
-- Inserts all theme CSS tokens into site_settings with setting_group = 'theme'
-- Safe to run multiple times (ON CONFLICT DO NOTHING)

INSERT INTO site_settings (setting_key, setting_value, setting_group, label) VALUES

  -- ── Colours ────────────────────────────────────────────────────────
  ('theme_ink',           '#121212',                  'theme', 'Text colour'),
  ('theme_paper',         '#fdfdfd',                  'theme', 'Page background'),
  ('theme_surface',       '#ffffff',                  'theme', 'Card / surface background'),
  ('theme_border',        '#e2e2e2',                  'theme', 'Border colour'),
  ('theme_muted',         '#666666',                  'theme', 'Muted / secondary text'),
  ('theme_accent',        '#cc0000',                  'theme', 'Accent colour'),
  ('theme_accent_dark',   '#aa0000',                  'theme', 'Accent hover colour'),
  ('theme_accent_text',   '#ffffff',                  'theme', 'Text on accent background'),
  ('theme_selection_bg',  'rgba(204,0,0,.12)',         'theme', 'Text selection highlight'),

  -- ── Typography ─────────────────────────────────────────────────────
  ('theme_font_serif',    'Georgia, "Times New Roman", Times, serif',                              'theme', 'Body / article font'),
  ('theme_font_ui',       '"Libre Franklin", system-ui, -apple-system, Segoe UI, sans-serif',     'theme', 'UI / interface font'),
  ('theme_font_mast',     '"UnifrakturMaguntia", Georgia, serif',                                 'theme', 'Masthead / logo font'),
  ('theme_font_base',     '18px',                     'theme', 'Base font size'),
  ('theme_font_article',  '21px',                     'theme', 'Article body font size'),
  ('theme_line_height',   '1.7',                      'theme', 'Body line height'),

  -- ── Shape & Layout ─────────────────────────────────────────────────
  ('theme_radius',        '16px',                     'theme', 'Border radius'),
  ('theme_max_width',     '1180px',                   'theme', 'Max page width'),
  ('theme_content_max',   '1000px',                   'theme', 'Article column width'),
  ('theme_card_pad',      '20px',                     'theme', 'Card padding'),

  -- ── Animation ──────────────────────────────────────────────────────
  ('theme_speed',         '0.18s',                    'theme', 'Transition speed'),
  ('theme_speed_slow',    '0.35s',                    'theme', 'Slow transition speed'),
  ('theme_ease',          'cubic-bezier(.2,.8,.2,1)', 'theme', 'Easing curve'),

  -- ── Mode ───────────────────────────────────────────────────────────
  ('theme_mode',          'light',                    'theme', 'Colour mode'),

  -- ── Cache bust token (used to invalidate PHP-FPM static cache) ─────
  ('theme_cache_bust',    '0',                        'general', 'Theme cache version')

ON CONFLICT (setting_key) DO NOTHING;

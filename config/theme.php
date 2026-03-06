<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════
 *  config/theme.php — White-Label Theme Configuration
 * ═══════════════════════════════════════════════════════════════════
 *
 * PURPOSE
 * -------
 * This file is the single canonical source of defaults for every
 * theme token in the system. It serves three audiences:
 *
 *   1. WHITE-LABEL INSTALLERS
 *      Set env vars (THEME_ACCENT, THEME_MODE, etc.) in .env to lock
 *      a theme at deployment time — no DB access, no admin UI needed.
 *      Env vars override DB settings when both are present.
 *
 *   2. DEVELOPERS
 *      All default values for theme tokens are documented here rather
 *      than scattered across get_theme_css(), app.css, and dark-mode.js.
 *      When a new token is added, the default goes here first.
 *
 *   3. get_theme_css() in app/Support/helpers.php
 *      Reads this file as its fallback chain:
 *        DB setting → env var (this file) → hardcoded default (this file)
 *      Previously get_theme_css() used inline string literals for defaults;
 *      this file externalises them for maintainability.
 *
 * USAGE IN get_theme_css()
 * ------------------------
 * The function already reads from the DB. To integrate env overrides,
 * load this config once at bootstrap:
 *
 *   $themeCfg = require __DIR__ . '/../../config/theme.php';
 *
 * Then use $themeCfg['defaults'] as the fallback when a DB row is absent:
 *
 *   $ink = $t['theme_ink'] ?? $themeCfg['env']['theme_ink'] ?? $themeCfg['defaults']['theme_ink'];
 *
 * USAGE AS STANDALONE OVERRIDE
 * ----------------------------
 * White-label deployments that want to bypass the admin theme UI entirely
 * can set THEME_LOCKED=true in .env. When locked, get_theme_css() skips
 * the DB query and uses env/defaults directly (add this check to helpers.php).
 *
 * THREE-TIER MODE ARCHITECTURE (matches dark-mode.js v2)
 * -------------------------------------------------------
 *   'light'  → Always light. OS preference ignored.
 *   'dark'   → Always dark. OS preference ignored.
 *   'system' → Follows visitor's OS dark/light preference.
 *              Emits @media(prefers-color-scheme:dark) in get_theme_css().
 *   'auto'   → Legacy alias for 'system'. Normalised at runtime.
 *
 * ═══════════════════════════════════════════════════════════════════
 */

// ── Helper: read an env var, strip surrounding quotes ───────────────
// Dotenv already strips quotes, but raw $_ENV reads on some hosts don't.
$env = static function (string $key, string $default = ''): string {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === '') return $default;
    return trim((string)$val, " \t\n\r\0\x0B\"'");
};

// ── Deployment-time lock ─────────────────────────────────────────────
// When THEME_LOCKED=true, get_theme_css() should skip the DB and use
// env/defaults exclusively. Useful for white-label SaaS with per-tenant
// themes injected at deploy time rather than managed in admin UI.
$locked = filter_var($env('THEME_LOCKED', 'false'), FILTER_VALIDATE_BOOLEAN);

// ── Colour mode ──────────────────────────────────────────────────────
// Env: THEME_MODE=light|dark|system
// Valid values: 'light', 'dark', 'system'  ('auto' is normalised to 'system')
$modeRaw = strtolower($env('THEME_MODE', 'light'));
$mode     = match ($modeRaw) {
    'dark'   => 'dark',
    'system', 'auto' => 'system',
    default  => 'light',
};

// ── Light-mode token env overrides ───────────────────────────────────
// Each maps 1:1 to a site_settings DB row with the same key name.
// THEME_INK       → theme_ink        → CSS var --ink
// THEME_PAPER     → theme_paper      → CSS var --paper
// ... etc.
$envTokens = [
    'theme_ink'           => $env('THEME_INK',           ''),
    'theme_paper'         => $env('THEME_PAPER',         ''),
    'theme_surface'       => $env('THEME_SURFACE',       ''),
    'theme_border'        => $env('THEME_BORDER',        ''),
    'theme_muted'         => $env('THEME_MUTED',         ''),
    'theme_accent'        => $env('THEME_ACCENT',        ''),
    'theme_accent_dark'   => $env('THEME_ACCENT_DARK',   ''),
    'theme_accent_text'   => $env('THEME_ACCENT_TEXT',   ''),
    'theme_mast_color'    => $env('THEME_MAST_COLOR',    ''),
    'theme_selection_bg'  => $env('THEME_SELECTION_BG',  ''),

    // Dark-mode token env overrides (used when mode='dark' or mode='system')
    'theme_ink_dark'          => $env('THEME_INK_DARK',          ''),
    'theme_paper_dark'        => $env('THEME_PAPER_DARK',        ''),
    'theme_surface_dark'      => $env('THEME_SURFACE_DARK',      ''),
    'theme_border_dark'       => $env('THEME_BORDER_DARK',       ''),
    'theme_muted_dark'        => $env('THEME_MUTED_DARK',        ''),
    'theme_selection_bg_dark' => $env('THEME_SELECTION_BG_DARK', ''),

    // Typography
    'theme_font_serif'    => $env('THEME_FONT_SERIF',    ''),
    'theme_font_ui'       => $env('THEME_FONT_UI',       ''),
    'theme_font_mast'     => $env('THEME_FONT_MAST',     ''),
    'theme_font_base'     => $env('THEME_FONT_BASE',     ''),
    'theme_font_article'  => $env('THEME_FONT_ARTICLE',  ''),
    'theme_line_height'   => $env('THEME_LINE_HEIGHT',   ''),

    // Layout
    'theme_radius'        => $env('THEME_RADIUS',        ''),
    'theme_max_width'     => $env('THEME_MAX_WIDTH',     ''),
    'theme_content_max'   => $env('THEME_CONTENT_MAX',   ''),
    'theme_card_pad'      => $env('THEME_CARD_PAD',      ''),

    // Motion
    'theme_speed'         => $env('THEME_SPEED',         ''),
    'theme_speed_slow'    => $env('THEME_SPEED_SLOW',    ''),
    'theme_ease'          => $env('THEME_EASE',          ''),
];

// Strip empty strings — only non-empty env vars override DB/defaults
$envTokens = array_filter($envTokens, fn(string $v) => $v !== '');

// ── Canonical defaults ────────────────────────────────────────────────
// These are the ultimate fallback values used when both the DB row AND
// the env var are absent. They match the inline literals previously
// scattered across get_theme_css(). Centralised here so changes
// propagate automatically to the helper function.
$defaults = [
    // ── Light mode ──────────────────────────────────────────────
    'theme_mode'          => 'light',

    'theme_ink'           => '#121212',
    'theme_paper'         => '#fdfdfd',
    'theme_surface'       => '#ffffff',
    'theme_border'        => '#e2e2e2',
    'theme_muted'         => '#666666',
    'theme_accent'        => '#cc0000',   // Northern Times brand red
    'theme_accent_dark'   => '#aa0000',
    'theme_accent_text'   => '#ffffff',
    'theme_mast_color'    => '',          // empty = inherit from --accent
    'theme_selection_bg'  => 'rgba(204,0,0,.12)',

    // ── Dark mode ───────────────────────────────────────────────
    // These values are used by get_theme_css() for mode='dark' and
    // mode='system'. They are surfaced in the admin settings UI via
    // the theme_*_dark fields added in admin/settings.php.
    'theme_ink_dark'          => '#e0e0e0',
    'theme_paper_dark'        => '#1a1a1a',
    'theme_surface_dark'      => '#242424',
    'theme_border_dark'       => '#3a3a3a',
    'theme_muted_dark'        => '#999999',
    'theme_selection_bg_dark' => 'rgba(204,0,0,.22)',

    // ── Typography ──────────────────────────────────────────────
    'theme_font_serif'    => 'Georgia, "Times New Roman", Times, serif',
    'theme_font_ui'       => '"Libre Franklin", system-ui, sans-serif',
    'theme_font_mast'     => '"UnifrakturMaguntia", Georgia, serif',
    'theme_font_base'     => '18px',
    'theme_font_article'  => '21px',
    'theme_line_height'   => '1.7',

    // ── Layout ──────────────────────────────────────────────────
    'theme_radius'        => '16px',
    'theme_max_width'     => '1180px',
    'theme_content_max'   => '1000px',
    'theme_card_pad'      => '20px',

    // ── Motion ──────────────────────────────────────────────────
    'theme_speed'         => '0.18s',
    'theme_speed_slow'    => '0.35s',
    'theme_ease'          => 'cubic-bezier(.2,.8,.2,1)',
];

// ── Branding env overrides ────────────────────────────────────────────
// Logo and site name can be set at deploy time for white-label installs
// that manage assets via CI/CD rather than the admin media library.
$brandEnv = array_filter([
    'site_title'         => $env('BRAND_NAME',          ''),
    'site_logo_url'      => $env('BRAND_LOGO_URL',       ''),
    'site_logo_dark_url' => $env('BRAND_LOGO_DARK_URL',  ''),
    'favicon_url'        => $env('BRAND_FAVICON_URL',    ''),
    'site_abbreviation'  => $env('BRAND_ABBREVIATION',   ''),
], fn(string $v) => $v !== '');

return [
    // ── Deployment flags ─────────────────────────────────────────
    'locked'   => $locked,   // bool: skip DB when true
    'mode'     => $mode,     // resolved mode: 'light'|'dark'|'system'

    // ── Env token overrides (non-empty only) ─────────────────────
    // Merge these over DB values in get_theme_css():
    //   $merged = array_merge($t, $themeCfg['env']);
    'env'      => $envTokens,

    // ── Canonical defaults ────────────────────────────────────────
    // Use as fallback after env:
    //   $c = fn($k, $d) => $san($t[$k] ?? $themeCfg['env'][$k] ?? $themeCfg['defaults'][$k] ?? $d);
    'defaults' => $defaults,

    // ── Brand env overrides ───────────────────────────────────────
    // Merge into get_site_setting() results for white-label deployments.
    'brand'    => $brandEnv,

    // ── .env reference ────────────────────────────────────────────
    // Document which env vars are recognised by this config.
    // Copy relevant lines into .env.example for new installers.
    '_env_keys' => [
        // Core
        'THEME_LOCKED'           => 'bool  — skip DB, use env/defaults only',
        'THEME_MODE'             => 'light|dark|system — default colour mode',

        // Light tokens
        'THEME_INK'              => 'hex   — body text colour',
        'THEME_PAPER'            => 'hex   — page background',
        'THEME_SURFACE'          => 'hex   — card/panel background',
        'THEME_BORDER'           => 'hex   — border colour',
        'THEME_MUTED'            => 'hex   — secondary/muted text',
        'THEME_ACCENT'           => 'hex   — brand accent (links, buttons)',
        'THEME_ACCENT_DARK'      => 'hex   — accent hover state',
        'THEME_ACCENT_TEXT'      => 'hex   — text on accent background',
        'THEME_MAST_COLOR'       => 'hex   — masthead/logo text colour',
        'THEME_SELECTION_BG'     => 'rgba  — text selection highlight',

        // Dark tokens
        'THEME_INK_DARK'         => 'hex   — body text in dark mode',
        'THEME_PAPER_DARK'       => 'hex   — page background in dark mode',
        'THEME_SURFACE_DARK'     => 'hex   — card background in dark mode',
        'THEME_BORDER_DARK'      => 'hex   — border in dark mode',
        'THEME_MUTED_DARK'       => 'hex   — muted text in dark mode',
        'THEME_SELECTION_BG_DARK'=> 'rgba  — selection highlight in dark mode',

        // Typography
        'THEME_FONT_SERIF'       => 'CSS font-family — body serif',
        'THEME_FONT_UI'          => 'CSS font-family — UI/sans',
        'THEME_FONT_MAST'        => 'CSS font-family — masthead',
        'THEME_FONT_BASE'        => 'px    — body font size',
        'THEME_FONT_ARTICLE'     => 'px    — article body font size',
        'THEME_LINE_HEIGHT'      => 'unit  — body line height',

        // Layout
        'THEME_RADIUS'           => 'px    — default border radius',
        'THEME_MAX_WIDTH'        => 'px    — page max-width',
        'THEME_CONTENT_MAX'      => 'px    — article content max-width',
        'THEME_CARD_PAD'         => 'px    — card padding',

        // Motion
        'THEME_SPEED'            => 'e.g. 0.18s — base transition speed',
        'THEME_SPEED_SLOW'       => 'e.g. 0.35s — slow transition speed',
        'THEME_EASE'             => 'CSS easing function',

        // Brand
        'BRAND_NAME'             => 'string — site display name',
        'BRAND_LOGO_URL'         => 'path   — light-mode logo URL',
        'BRAND_LOGO_DARK_URL'    => 'path   — dark-mode logo URL',
        'BRAND_FAVICON_URL'      => 'path   — favicon URL',
        'BRAND_ABBREVIATION'     => 'string — short name (e.g. NT)',
    ],
];
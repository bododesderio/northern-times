<?php
declare(strict_types=1);

// ── Core output helpers ──────────────────────────────────────────

function h($string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return the configured bot user-agent name (white-label safe).
 */
function bot_name(): string
{
    return $_ENV['APP_BOT_NAME'] ?? 'NewsCrawlerBot';
}

/**
 * Render a styled error page and return a Response.
 */
function abort(int $code = 404): \Symfony\Component\HttpFoundation\Response
{
    $errorFile = __DIR__ . '/../Views/errors/' . $code . '.php';
    if (!file_exists($errorFile)) {
        $errorFile = __DIR__ . '/../Views/errors/500.php';
    }
    $detail = '';
    ob_start();
    require $errorFile;
    $body = ob_get_clean();
    return new \Symfony\Component\HttpFoundation\Response((string)$body, $code, ['Content-Type' => 'text/html; charset=UTF-8']);
}

/**
 * Sanitise HTML for safe rendering in article bodies.
 * Uses a DOM-based sanitizer immune to encoding tricks.
 *
 * @param string $html    Raw HTML (e.g. from CKEditor)
 * @param string $profile 'article' (default) or 'basic' (comments)
 */
function safe_html(string $html, string $profile = 'article'): string
{
    return \App\Services\Sanitizer::clean($html, $profile);
}

// ── Settings & theme ─────────────────────────────────────────────

/**
 * Load config/theme.php once per process and return it.
 *
 * Cached in a static so the file is require'd only once regardless of
 * how many times get_theme_css() or get_site_setting() is called.
 * Returns a safe default structure if the file doesn't exist yet
 * (backwards-compatible with installs that haven't added config/theme.php).
 *
 * @internal
 * @return array{locked:bool, mode:string, env:array<string,string>, defaults:array<string,string>, brand:array<string,string>}
 */
function &_theme_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../../config/theme.php';
        $cfg  = file_exists($path) ? (require $path) : [];
        // Ensure required keys always exist even if file returns a partial array
        $cfg += ['locked' => false, 'mode' => 'light', 'env' => [], 'defaults' => [], 'brand' => []];
    }
    return $cfg;
}

/**
 * Shared mutable state for get_site_setting cache.
 * Using an array reference allows get_site_setting_flush() to reset the cache
 * from outside the function — something PHP static vars don't support directly.
 *
 * @internal
 */
function &_get_site_setting_state(): array
{
    static $state = ['cache' => null, 'cacheVer' => null, 'verChecked' => false];
    return $state;
}

/**
 * Fetches a single site setting from the DB (with optional env override).
 *
 * Priority chain:
 *   1. Brand env override from config/theme.php $brand array
 *      (BRAND_NAME, BRAND_LOGO_URL, BRAND_LOGO_DARK_URL, etc. — set in .env)
 *   2. DB site_settings row
 *   3. $default parameter
 *
 * When THEME_LOCKED=true, brand overrides still apply for branding keys
 * (site_title, site_logo_url, etc.) so the site name and logo are always
 * env-driven in locked deployments even when the DB is bypassed.
 *
 * Cache is request-scoped and version-keyed via theme_cache_bust so
 * PHP-FPM workers pick up admin saves immediately without a restart.
 */
function get_site_setting(string $key, string $default = ''): string
{
    $cfg = &_theme_config();

    // Brand env overrides take highest priority (white-label deployment layer)
    if (!empty($cfg['brand'][$key])) {
        return $cfg['brand'][$key];
    }

    $s   = &_get_site_setting_state();
    $ver = '0';

    try {
        $pdo = \App\Services\DB::pdo();

        if (!$s['verChecked']) {
            $verRow = $pdo->query(
                "SELECT setting_value FROM site_settings WHERE setting_key = 'theme_cache_bust' LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $ver             = $verRow ? (string)$verRow['setting_value'] : '0';
            $s['verChecked'] = true;
        } else {
            $ver = $s['cacheVer'] ?? '0';
        }

        if ($s['cache'] === null || $s['cacheVer'] !== $ver) {
            $stmt       = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            $s['cache'] = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $s['cache'][$row['setting_key']] = $row['setting_value'];
            }
            $s['cacheVer'] = $ver;
        }
    } catch (\Throwable $e) {
        error_log('[helpers] get_site_setting: ' . $e->getMessage());
        if ($s['cache'] === null) $s['cache'] = [];
    }

    return $s['cache'][$key] ?? $default;
}

/**
 * Flush the get_site_setting() cache so the next call re-reads from DB.
 *
 * Call this in tests (or after admin bulk-save) when you need freshly-written
 * values to be visible within the same PHP process.
 */
if (!function_exists('get_site_setting_flush')) {
    function get_site_setting_flush(): void
    {
        $s               = &_get_site_setting_state();
        $s['cache']      = null;
        $s['cacheVer']   = null;
        $s['verChecked'] = false;
    }
}

/**
 * Builds an inline <style> block from theme settings.
 *
 * ── PRIORITY CHAIN (highest → lowest) ───────────────────────────
 *
 *   1. THEME_* env vars   — set in .env, parsed by config/theme.php
 *   2. DB site_settings   — managed via Admin → Settings → Theme
 *   3. config/theme.php   — canonical fallback defaults
 *   4. Inline hardcoded   — final safety net (matches config defaults)
 *
 * When THEME_LOCKED=true (set in .env), step 2 is skipped entirely.
 * Useful for SaaS multi-tenant deploys where each instance has its own
 * .env and the admin theme UI should have no effect on the output.
 *
 * ── THREE-TIER MODE ARCHITECTURE ────────────────────────────────
 *
 *   'light'  → :root emits light tokens only. color-scheme: light.
 *              No @media block emitted — OS preference is ignored.
 *              JS reads data-theme="light" and never applies html.dark.
 *
 *   'dark'   → :root emits light tokens THEN a full :root dark override
 *              block so the server-rendered page is dark before JS loads.
 *              Zero flash on hard reload. JS reads data-theme="dark" and
 *              applies html.dark for component-level overrides in app.css.
 *
 *   'system' → :root emits light tokens + @media(prefers-color-scheme:dark)
 *              { :root { dark tokens } }. JS reads data-theme="system"
 *              and defers to OS. All dark tokens are DB/env driven.
 *
 *   'auto'   → Legacy alias for 'system'. Normalised at runtime.
 *
 * ── CACHE ────────────────────────────────────────────────────────
 *   Version-keyed via theme_cache_bust DB row. PHP-FPM workers pick
 *   up admin saves immediately without a process restart.
 *   In locked mode a stable hash of env tokens is used as the key.
 *
 * @return string  Complete <style id="nt-theme">…</style> block, or '' on error.
 */
function get_theme_css(): string
{
    static $rendered    = null;
    static $renderedVer = null;

    $cfg    = &_theme_config();
    $locked = (bool)($cfg['locked'] ?? false);
    $envT   = (array)($cfg['env']      ?? []);   // THEME_* env overrides (non-empty only)
    $defs   = (array)($cfg['defaults'] ?? []);   // canonical fallbacks from config/theme.php

    $ver = '0';
    $t   = [];  // DB theme settings

    if (!$locked) {
        try {
            $pdo = \App\Services\DB::pdo();

            $verRow = $pdo->query(
                "SELECT setting_value FROM site_settings WHERE setting_key = 'theme_cache_bust' LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $ver = $verRow ? (string)$verRow['setting_value'] : '0';

            if ($rendered !== null && $renderedVer === $ver) {
                return $rendered;
            }

            $stmt = $pdo->query(
                "SELECT setting_key, setting_value FROM site_settings WHERE setting_group = 'theme'"
            );
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $t[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) {
            error_log('[helpers] get_theme_css: ' . $e->getMessage());
            // DB unavailable — continue with env/defaults (graceful degradation)
        }
    } else {
        // Locked mode: stable cache key based on env tokens so we render once
        $ver = 'locked-' . hash('sha256', serialize($envT));
        if ($rendered !== null && $renderedVer === $ver) {
            return $rendered;
        }
    }

    // Sanitise a single CSS value — strips HTML/injection vectors only.
    // Does NOT encode quotes so font-family strings remain valid.
    $san = static function (string $v): string {
        $v = preg_replace('/<[^>]*>/', '', $v) ?? $v;
        $v = preg_replace('/expression\s*\(/i', '', $v) ?? $v;
        return trim($v);
    };

    // Resolved value lookup: env → DB → config defaults → inline hardcoded default.
    $c = static function (string $k, string $hardDefault) use ($san, $t, $envT, $defs): string {
        $raw = $envT[$k] ?? $t[$k] ?? $defs[$k] ?? $hardDefault;
        return $san((string)$raw);
    };

    // ── Resolve theme mode ────────────────────────────────────────
    $modeRaw = $envT['theme_mode'] ?? $t['theme_mode'] ?? $defs['theme_mode'] ?? 'light';
    $mode    = $san((string)$modeRaw);
    if ($mode === 'auto') $mode = 'system'; // normalise legacy value
    if (!in_array($mode, ['light', 'dark', 'system'], true)) $mode = 'light';

    // ── Light-mode tokens (always emitted) ───────────────────────
    $ink         = $c('theme_ink',          '#121212');
    $paper       = $c('theme_paper',        '#fdfdfd');
    $surface     = $c('theme_surface',      '#ffffff');
    $border      = $c('theme_border',       '#e2e2e2');
    $muted       = $c('theme_muted',        '#666666');
    $accent      = $c('theme_accent',       '#cc0000');
    $accentDark  = $c('theme_accent_dark',  '#aa0000');
    $accentText  = $c('theme_accent_text',  '#ffffff');
    $mastColor   = $c('theme_mast_color',   '');
    $selection   = $c('theme_selection_bg', 'rgba(204,0,0,.12)');

    $fontSerif   = $c('theme_font_serif',   'Georgia, "Times New Roman", Times, serif');
    $fontUi      = $c('theme_font_ui',      '"Libre Franklin", system-ui, sans-serif');
    $fontMast    = $c('theme_font_mast',    '"UnifrakturMaguntia", Georgia, serif');
    $fontBase    = $c('theme_font_base',    '18px');
    $fontArticle = $c('theme_font_article', '21px');
    $lineHeight  = $c('theme_line_height',  '1.7');

    $radius      = $c('theme_radius',       '16px');
    $maxWidth    = $c('theme_max_width',    '1180px');
    $contentMax  = $c('theme_content_max',  '1000px');
    $cardPad     = $c('theme_card_pad',     '20px');

    $speed       = $c('theme_speed',        '0.18s');
    $speedSlow   = $c('theme_speed_slow',   '0.35s');
    $ease        = $c('theme_ease',         'cubic-bezier(.2,.8,.2,1)');

    // ── Dark-mode tokens (used for mode=dark and mode=system) ────
    // Env (THEME_INK_DARK etc.) → DB → config defaults → inline default.
    // No hardcoded hex — all fully controllable from .env or admin UI.
    $darkInk     = $c('theme_ink_dark',          '#e0e0e0');
    $darkPaper   = $c('theme_paper_dark',        '#1a1a1a');
    $darkSurface = $c('theme_surface_dark',      '#242424');
    $darkBorder  = $c('theme_border_dark',       '#3a3a3a');
    $darkMuted   = $c('theme_muted_dark',        '#999999');
    $darkSelBg   = $c('theme_selection_bg_dark', 'rgba(204,0,0,.22)');

    // ── Base :root block (light tokens, always present) ──────────
    $root = <<<CSS
:root {
  --ink: {$ink};
  --paper: {$paper};
  --surface: {$surface};
  --border: {$border};
  --muted: {$muted};
  --accent: {$accent};
  --accent-dark: {$accentDark};
  --accent-text: {$accentText};
  --selection-bg: {$selection};
  --mast-color: {$mastColor ?: 'var(--accent)'};

  --serif: {$fontSerif};
  --ui: {$fontUi};
  --mast: {$fontMast};
  --font-base: {$fontBase};
  --font-article: {$fontArticle};
  --line-height: {$lineHeight};

  --radius: {$radius};
  --max: {$maxWidth};
  --content-max: {$contentMax};
  --card-pad: {$cardPad};

  --speed: {$speed};
  --speed-slow: {$speedSlow};
  --ease: {$ease};

  color-scheme: light;
}
::selection { background: {$selection}; }
CSS;

    // ── Mode-specific dark block ──────────────────────────────────
    $darkBlock = '';

    if ($mode === 'dark') {
        // Full :root override emitted server-side so the page is dark
        // before any JS runs — zero flash on hard reload.
        // dark-mode.js also applies html.dark for component-level
        // overrides in app.css, but base colours are correct immediately.
        $darkBlock = <<<CSS

/* ── DARK MODE (server-declared) ──────────────────────────────── */
:root {
  --ink: {$darkInk};
  --paper: {$darkPaper};
  --surface: {$darkSurface};
  --border: {$darkBorder};
  --muted: {$darkMuted};
  --selection-bg: {$darkSelBg};
  color-scheme: dark;
}
::selection { background: {$darkSelBg}; }
img { filter: brightness(.95) contrast(1.05); }
CSS;

    } elseif ($mode === 'system') {
        // @media block ONLY emitted for 'system' mode.
        // When mode='light', this block is absent — OS preference is ignored,
        // which is intentional: the DB setting is authoritative.
        $darkBlock = <<<CSS

/* ── SYSTEM MODE — OS dark preference (server-declared) ─────── */
@media (prefers-color-scheme: dark) {
  :root {
    --ink: {$darkInk};
    --paper: {$darkPaper};
    --surface: {$darkSurface};
    --border: {$darkBorder};
    --muted: {$darkMuted};
    --selection-bg: {$darkSelBg};
    color-scheme: dark;
  }
  ::selection { background: {$darkSelBg}; }
  img { filter: brightness(.95) contrast(1.05); }
}
CSS;
    }
    // mode='light': $darkBlock stays '' — no @media, OS preference cannot override.

    $rendered    = '<style id="nt-theme">' . "\n" . $root . $darkBlock . "\n" . '</style>';
    $renderedVer = $ver;
    return $rendered;
}

// ── URL helpers ──────────────────────────────────────────────────

/**
 * Returns a fully-qualified URL using APP_URL env var.
 */
function app_url(string $path = '/'): string
{
    $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

// ── Text helpers ─────────────────────────────────────────────────

/**
 * Returns a plain-text excerpt of $text, stripped of HTML,
 * truncated to $length chars. Falls back to hard truncation
 * if word-boundary would cut more than 40% of the length.
 *
 * FIX (v2): Added fallback guard so very long words don't produce
 * unexpectedly short excerpts.
 */
function excerpt(string $text, int $length = 160): string
{
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    $text = trim($text);

    if (mb_strlen($text) <= $length) return $text;

    $truncated = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($truncated, ' ');

    // Guard: only snap to word boundary if it doesn't cut more than 40%
    if ($lastSpace !== false && $lastSpace >= (int)($length * 0.6)) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return $truncated . '…';
}

/**
 * Truncate text to N words, appending "…" if truncated.
 * Strips HTML tags first.
 *
 * Use this when you need a word-count cap (e.g. card teasers).
 * Use excerpt() when you need a character-length cap (e.g. meta descriptions).
 *
 * @param  string $text   HTML or plain text
 * @param  int    $limit  Number of words
 * @return string
 */
if (!function_exists('excerpt_words')) {
    function excerpt_words(string $text, int $limit = 25): string
    {
        if (empty($text)) return '';

        $text  = strip_tags($text);
        $text  = preg_replace('/\s+/', ' ', trim($text));
        $words = explode(' ', $text);

        if (count($words) <= $limit) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $limit)) . '…';
    }
}

/**
 * Estimate reading time in minutes.
 * Returns int (raw) — format with reading_time_label() for display.
 */
function reading_time(string $content, int $wpm = 200): int
{
    $words = str_word_count(strip_tags($content));
    return max(1, (int)ceil($words / $wpm));
}

/**
 * Human-readable reading time label.
 * Kept separate so callers that only need the int can use reading_time().
 *
 * @param  string $content HTML or plain text
 * @return string          e.g. "4 min read"
 */
function reading_time_label(string $content): string
{
    if (empty($content)) return '1 min read';
    return reading_time($content) . ' min read';
}

// ── Time helpers ─────────────────────────────────────────────────

/**
 * Human-friendly relative timestamp.
 *
 * - < 60s      → "just now"
 * - < 60 min   → "3 mins ago"
 * - < 24 hours → "7 hours ago"
 * - yesterday  → "yesterday"
 * - < 7 days   → "3 days ago"
 * - same year  → "Feb 14"
 * - older      → "Feb 14, 2024"
 */
if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string
    {
        if (empty($datetime)) return '';

        try {
            $now  = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $then = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return '';
        }

        $diff = $now->getTimestamp() - $then->getTimestamp();
        if ($diff < 0) $diff = 0;

        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   { $m = (int)floor($diff / 60);   return $m . ($m === 1 ? ' min ago'  : ' mins ago'); }
        if ($diff < 86400)  { $h = (int)floor($diff / 3600); return $h . ($h === 1 ? ' hour ago' : ' hours ago'); }
        if ($diff < 172800) return 'yesterday';
        if ($diff < 604800) { $d = (int)floor($diff / 86400); return $d . ' days ago'; }

        return $then->format('Y') === $now->format('Y')
            ? $then->format('M j')
            : $then->format('M j, Y');
    }
}

/**
 * relative_time() — README-specified alias for time_ago().
 *
 * The README §23 and §6.4 specify this function name for displaying
 * human-friendly relative timestamps on article cards, article pages,
 * and comment timestamps. This alias ensures all view templates that
 * call relative_time() work correctly.
 */
if (!function_exists('relative_time')) {
    function relative_time(string $datetime): string
    {
        return time_ago($datetime);
    }
}

/**
 * Format a large integer for display (1247 → "1,247"; 1200000 → "1.2M").
 * Used in dashboard widgets and analytics displays.
 */
if (!function_exists('format_number')) {
    function format_number(int|float $n, int $decimals = 0): string
    {
        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'M';
        }
        if ($n >= 10_000) {
            return number_format((int)$n);
        }
        return number_format((int)$n, $decimals);
    }
}

/**
 * Returns true if a URL is external (starts with http/https pointing
 * to a different host than APP_URL). Used to add rel="noopener" safely.
 */
if (!function_exists('is_external_url')) {
    function is_external_url(string $url): bool
    {
        if (!str_starts_with($url, 'http')) return false;
        $appHost = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_HOST) ?? '';
        $urlHost = parse_url($url, PHP_URL_HOST) ?? '';
        return $appHost !== '' && $urlHost !== $appHost;
    }
}

/**
 * Convenience wrapper: returns the configured site name.
 *
 * Priority chain:
 *   1. BRAND_NAME env var  (config/theme.php brand overrides → get_site_setting)
 *   2. DB site_title setting
 *   3. APP_NAME env var
 *   4. 'My Platform'  (neutral final fallback — never a hardcoded brand name)
 */
if (!function_exists('site_name')) {
    function site_name(): string
    {
        // get_site_setting() already checks brand env overrides first
        $fromDb = get_site_setting('site_title');
        if ($fromDb !== '') return $fromDb;
        return $_ENV['APP_NAME'] ?? 'My Platform';
    }
}

/**
 * Render a consistent admin avatar <img> or initials placeholder.
 * Eliminates broken-image icons across all admin profile displays.
 *
 * @param array  $user   User row with keys: avatar_url, name/display_name, role
 * @param int    $size   Pixel size (default 40)
 * @param string $class  Extra CSS classes
 */
if (!function_exists('admin_avatar_html')) {
    function admin_avatar_html(array $user, int $size = 40, string $class = ''): string
    {
        $name     = $user['display_name'] ?? $user['name'] ?? $user['username'] ?? '?';
        $initials = strtoupper(mb_substr($name, 0, 1));
        if (str_contains($name, ' ')) {
            $parts    = explode(' ', $name, 2);
            $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }

        $roleColors = [
            'super_admin' => '#c00000',
            'editor'      => '#1a6bbf',
            'author'      => '#2d8a4e',
            'contributor' => '#7c5cbf',
        ];
        $bg      = $roleColors[$user['role'] ?? 'author'] ?? '#4a4a4a';
        $cls     = h('admin-avatar' . ($class ? ' ' . $class : ''));
        $sStyle  = 'width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;object-fit:cover;flex-shrink:0';
        $pStyle  = 'width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;background:' . h($bg)
                 . ';display:flex;align-items:center;justify-content:center;'
                 . 'color:#fff;font-weight:700;font-size:' . max(10, (int)($size * 0.4)) . 'px;flex-shrink:0';

        $avatarUrl = trim($user['avatar_url'] ?? '');
        if ($avatarUrl !== '') {
            return '<img src="' . h($avatarUrl) . '" alt="' . h($name) . '" class="' . $cls . '" '
                 . 'style="' . $sStyle . '" loading="lazy" '
                 . 'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'
                 . '<span class="' . $cls . '" style="' . $pStyle . ';display:none" aria-hidden="true">'
                 . h($initials) . '</span>';
        }

        return '<span class="' . $cls . '" style="' . $pStyle . '" title="' . h($name) . '" aria-label="' . h($name) . '">'
             . h($initials) . '</span>';
    }
}

// ── Author helpers ───────────────────────────────────────────────

/**
 * Resolve author display info for an article.
 *
 * For manually-written articles: uses the actual author data
 * (joined from users table via author_id).
 * For crawled articles (source_id is set): uses the site owner
 * so crawled content shows the publication's editorial identity.
 *
 * Returns array with keys: name, avatar, url
 */
if (!function_exists('article_author')) {
    function article_author(array $article): array
    {
        // The SQL CASE expression in every Article model query already resolves
        // `author` to: super_admin.display_name for crawled articles, or
        // display_author / u.username for own articles.
        // This function just reads that pre-resolved value — never overrides it.
        $authorName = trim((string)($article['author_name'] ?? $article['author'] ?? ''));

        if ($authorName !== '') {
            return [
                'name'   => $authorName,
                'avatar' => $article['author_avatar'] ?? null,
                'url'    => !empty($article['author_username'])
                            ? '/author/' . $article['author_username']
                            : null,
            ];
        }

        // Last resort — should rarely fire now
        return [
            'name'   => function_exists('site_name') ? site_name() : 'Staff',
            'avatar' => null,
            'url'    => null,
        ];
    }
}

// ── Ad rendering ─────────────────────────────────────────────────

/**
 * Returns the white-label crawl author name for crawled/aggregated articles.
 * Used in SQL queries via PHP (CrawlerEngine, FrontendController).
 *
 * FIX: Falls back to site_name() (env-driven) instead of hardcoded brand string.
 *
 * @return string  e.g. "NT Newsroom" or whatever is set in site_settings
 */
if (!function_exists('crawl_author_name')) {
    /**
     * Legacy helper — prefer Article::crawlAuthorName() SQL subquery instead.
     * Returns the super_admin display_name from DB. No string concatenation.
     */
    function crawl_author_name(): string
    {
        $fromDb = get_site_setting('default_crawl_author');
        if ($fromDb !== '') return $fromDb;
        // Fetch super_admin name directly from DB
        try {
            $sa = \App\Models\User::getSuperAdmin();
            if ($sa) return $sa['display_name'] ?: $sa['username'] ?: 'Staff';
        } catch (\Throwable $e) {}
        return function_exists('site_name') ? site_name() : 'Staff';
    }
}

/**
 * Returns a formatted copyright line using the copyright_template setting.
 * Template variables: {year}, {publisher}, {site_name}, {abbreviation}
 *
 * FIX: Falls back to site_name() (env-driven) instead of hardcoded brand string.
 *
 * @return string  e.g. "© 2026 The Northern Times. All rights reserved."
 */
if (!function_exists('copyright_line')) {
    function copyright_line(): string
    {
        $template     = get_site_setting('copyright_template', '© {year} {publisher}. All rights reserved.');
        $publisher    = get_site_setting('publisher_name');
        if ($publisher === '') $publisher = site_name();
        $abbreviation = get_site_setting('site_abbreviation', '');
        return str_replace(
            ['{year}', '{publisher}', '{site_name}', '{abbreviation}'],
            [date('Y'), $publisher,  site_name(),   $abbreviation],
            $template
        );
    }
}

/**
 * Render an ad slot. Returns empty string if slot not active.
 *
 * MERGE: File 1 and File 2 both define render_ad() with different
 * signatures and data shapes. This version unifies both:
 *
 *   File 1 shape: $ads keyed by slot_name, ad has ad_type / content / link_url
 *   File 2 shape: $ads is a flat array, ad has position / image_url / embed_code
 *
 * The unified version accepts EITHER shape by detecting the key structure,
 * so existing callers of both versions continue to work without changes.
 *
 * SECURITY FIX: custom_css is now sanitized before injection into <style>.
 *
 * @param array  $ads      Active ad slots (keyed by slot_name OR flat array)
 * @param string $slotName Slot/position key (e.g. 'top-banner', 'sidebar')
 * @param string $class    Optional extra CSS class on wrapper div
 */
function render_ad(array $ads, string $slotName, string $class = ''): string
{
    // ── Detect data shape & find the ad ─────────────────────────
    $ad = null;

    // File 1 shape: keyed array  $ads['top-banner'] = [...]
    if (isset($ads[$slotName]) && !empty($ads[$slotName])) {
        $ad = $ads[$slotName];
    }

    // File 2 shape: flat array of rows with 'position' key
    if ($ad === null) {
        foreach ($ads as $row) {
            if (is_array($row)
                && ($row['position'] ?? '') === $slotName
                && !empty($row['is_active']))
            {
                $ad = $row;
                break;
            }
        }
    }

    if ($ad === null) return '';

    // ── Resolve ad_type (File 1) vs embed_code/image_url (File 2) ──
    $type = $ad['ad_type'] ?? null;
    if ($type === null) {
        if (!empty($ad['embed_code'])) $type = 'html';
        elseif (!empty($ad['image_url'])) $type = 'image';
        else return '';
    }

    // ── Device targeting (File 1 feature) ───────────────────────
    $device      = $ad['device_target'] ?? 'all';
    $deviceClass = match($device) {
        'mobile'  => ' ad-mobile-only',
        'desktop' => ' ad-desktop-only',
        default   => '',
    };

    $css = 'ad-slot ad-' . h($slotName) . ($class ? ' ' . $class : '') . $deviceClass;

    // ── Inline max-dimension style (File 1 feature) ──────────────
    $styles = [];
    if (!empty($ad['max_width']))  $styles[] = 'max-width:'  . h($ad['max_width']);
    if (!empty($ad['max_height'])) $styles[] = 'max-height:' . h($ad['max_height']);
    $inlineStyle = $styles ? ' style="' . implode(';', $styles) . ';margin:0 auto"' : '';

    // ── Custom CSS block — SANITIZED (security fix) ──────────────
    $customCssBlock = '';
    if (!empty($ad['custom_css'])) {
        $safeCss = strip_tags($ad['custom_css']);
        $safeCss = str_replace(['<', '>', '"', "'"], '', $safeCss);
        $safeCss = preg_replace('/expression\s*\(/i', '', $safeCss) ?? $safeCss;
        $customCssBlock = '<style>.ad-' . h($slotName) . '{' . $safeCss . '}</style>';
    }

    // ── Build inner content ───────────────────────────────────────
    $inner = '';

    if ($type === 'html' || $type === 'adsense') {
        // Raw HTML / AdSense / embed_code — sanitize to prevent stored XSS
        $inner = safe_html($ad['content'] ?? $ad['embed_code'] ?? '');

    } elseif ($type === 'image') {
        // Support both File 1 ('content' = img src) and File 2 ('image_url')
        $imgSrc   = $ad['image_url'] ?? $ad['content'] ?? '';
        $altText  = h($ad['alt_text'] ?? $ad['name'] ?? 'Advertisement');
        $nofollow = ($ad['nofollow'] ?? true) ? ' nofollow' : '';
        $imgStyle = 'width:100%;height:auto;display:block;border-radius:8px';

        $img = '<img src="' . h($imgSrc) . '" alt="' . $altText . '" loading="lazy" decoding="async" style="' . $imgStyle . '" />';

        $linkUrl = $ad['link_url'] ?? '';

        if (!empty($linkUrl)) {
            // File 1: direct href.  File 2: click-tracking route.
            $adId    = $ad['id'] ?? null;
            $href    = $adId ? '/ad-click/' . h((string)$adId) : h($linkUrl);
            $inner   = '<a href="' . $href . '" target="_blank" rel="noopener sponsored' . $nofollow . '">' . $img . '</a>';
        } else {
            $inner = $img;
        }
    }

    if (!$inner) return '';

    $adId = $ad['id'] ?? '';

    return $customCssBlock
         . '<div class="' . $css . '" data-ad-slot="' . h($slotName) . '" data-ad-id="' . h((string)$adId) . '"' . $inlineStyle . '>'
         . '<div class="ad-label">Advertisement</div>'
         . '<div class="ad-content">' . $inner . '</div>'
         . '</div>';
}

// ── RBAC template helpers ────────────────────────────────────────

function user_can(string $permission): bool     { return \App\Services\RBAC::can($permission); }
function user_can_any(array $permissions): bool  { return \App\Services\RBAC::canAny($permissions); }
function user_is_editor(): bool                  { return \App\Services\RBAC::isEditor(); }
function user_is_admin(): bool                   { return \App\Services\RBAC::isSuperAdmin(); }

function user_role(): string
{
    $u = \App\Services\Auth::user();
    return $u['role'] ?? 'author';
}

// ── Image helpers ────────────────────────────────────────────────

/**
 * Generate a responsive <img> element with srcset.
 * Falls back gracefully through three levels if Image service unavailable.
 */
function responsive_img(
    string $src,
    string $alt            = '',
    string $loading        = 'lazy',
    string $fetchpriority  = 'auto',
    string $sizes          = '(max-width: 640px) 100vw, (max-width: 1024px) 66vw, 960px'
): string {
    if (empty($src)) return '';

    $alt  = h($alt);
    $load = h($loading);
    $fp   = $fetchpriority !== 'auto' ? ' fetchpriority="' . h($fetchpriority) . '"' : '';
    $rp   = str_starts_with($src, 'http') ? ' referrerpolicy="no-referrer" crossorigin="anonymous"' : '';

    try {
        $srcset = \App\Services\Image::srcset($src);
        if ($srcset && str_contains($srcset, ',')) {
            return '<img src="' . h($src) . '"'
                 . ' srcset="' . $srcset . '"'
                 . ' sizes="' . h($sizes) . '"'
                 . ' alt="' . $alt . '"'
                 . ' loading="' . $load . '"'
                 . ' decoding="async"' . $fp . $rp
                 . ' style="width:100%;height:auto" />';
        }
    } catch (\Throwable $e) {
        error_log('[helpers] responsive_img srcset: ' . $e->getMessage());
    }

    $thumb = thumbnail_url($src);
    if ($thumb !== $src) {
        $srcset = h($thumb) . ' 600w, ' . h($src) . ' 1920w';
        return '<img src="' . h($src) . '"'
             . ' srcset="' . $srcset . '"'
             . ' sizes="' . h($sizes) . '"'
             . ' alt="' . $alt . '"'
             . ' loading="' . $load . '"'
             . ' decoding="async"' . $fp . $rp
             . ' style="width:100%;height:auto" />';
    }

    return '<img src="' . h($src) . '" alt="' . $alt . '" loading="' . $load . '" decoding="async"' . $fp . $rp . ' style="width:100%;height:auto" />';
}

/**
 * Infer thumbnail URL from an upload path.
 * Returns original URL if no thumbnail found.
 *
 * FIX (v2): Path normalised with realpath() and validated to stay
 * inside storage root, preventing path traversal via crafted URLs.
 * FIX (v2): No-op str_replace removed; path now built directly.
 */
function thumbnail_url(string $url): string
{
    if (empty($url)) return $url;
    if (!str_starts_with($url, '/uploads/')) return $url;

    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if (!in_array($ext, ['webp', 'jpg', 'jpeg', 'png'], true)) return $url;

    if (str_contains($url, '-thumb.')) return $url;

    $base     = substr($url, 0, -(strlen($ext) + 1));
    $thumbUrl = $base . '-thumb.jpg';

    $storageRoot = realpath(__DIR__ . '/../../storage');
    if ($storageRoot) {
        $thumbPath = realpath($storageRoot . $thumbUrl);

        // Reject paths that escape the storage root (path traversal guard)
        if ($thumbPath !== false
            && str_starts_with($thumbPath, $storageRoot . DIRECTORY_SEPARATOR)
            && file_exists($thumbPath))
        {
            return $thumbUrl;
        }
    }

    return $url;
}

// ── Social helpers ───────────────────────────────────────────────

/**
 * Generate social share buttons for an article.
 *
 * FIX (v2): Copy-link button now includes an inline onclick fallback
 * so it works even if the external JS handler is absent.
 */
function share_buttons(string $url, string $title, string $excerpt = ''): string
{
    $u = urlencode($url);
    $t = urlencode($title);
    $e = urlencode($excerpt ?: $title);

    $twitter  = "https://twitter.com/intent/tweet?url={$u}&text={$t}";
    $facebook = "https://www.facebook.com/sharer/sharer.php?u={$u}";
    $whatsapp = "https://wa.me/?text={$t}%20{$u}";
    $linkedin = "https://www.linkedin.com/sharing/share-offsite/?url={$u}";
    $email    = "mailto:?subject={$t}&body={$e}%0A%0A{$u}";

    $svgX    = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
    $svgFb   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
    $svgWa   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
    $svgLi   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
    $svgMail = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
    $svgLink = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

    // FIX: inline onclick fallback so button works without external JS
    $copyOnClick = "navigator.clipboard?.writeText(this.dataset.url).then(()=>{ this.title='Copied!'; }).catch(()=>{ prompt('Copy link:', this.dataset.url); })";

    return '<div class="share">'
         . '<a href="' . $twitter  . '" target="_blank" rel="noopener" class="share-btn" data-share="twitter"  style="background:#000"     title="Share on X">'        . $svgX    . '</a>'
         . '<a href="' . $facebook . '" target="_blank" rel="noopener" class="share-btn" data-share="facebook" style="background:#1877F2"  title="Share on Facebook">'  . $svgFb   . '</a>'
         . '<a href="' . $whatsapp . '" target="_blank" rel="noopener" class="share-btn" data-share="whatsapp" style="background:#25D366"  title="Share on WhatsApp">'  . $svgWa   . '</a>'
         . '<a href="' . $linkedin . '" target="_blank" rel="noopener" class="share-btn" data-share="linkedin" style="background:#0A66C2"  title="Share on LinkedIn">'  . $svgLi   . '</a>'
         . '<a href="' . $email    . '" class="share-btn" data-share="email" style="background:#555"                                    title="Email this article">' . $svgMail . '</a>'
         . '<button class="share-btn share-copy-btn" data-share="copy" style="background:#888" title="Copy link" data-url="' . h($url) . '" onclick="' . h($copyOnClick) . '">' . $svgLink . '</button>'
         . '</div>';
}
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminSettingsController extends Controller
{
    public function index(): Response
    {
        return $this->render('admin/settings', [
            'settings'      => Setting::allFlat(),
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function save(): Response
    {
        $request = Request::createFromGlobals();

        $pdo = Setting::pdo();

        // ── FIXED: upsert now includes setting_group ──────────────────
        $upsert = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value, setting_group, updated_at)
            VALUES (:key, :value, :group, NOW())
            ON CONFLICT (setting_key)
            DO UPDATE SET setting_value = :value, setting_group = :group, updated_at = NOW()
        ");

        // ── General fields ───────────────────────────────────────────
        $generalFields = [
            'site_title'              => '',
            'site_tagline'            => 'Independent journalism from Northern Uganda and beyond.',
            // White-label identity
            'site_abbreviation'       => 'NT',
            'publisher_name'          => '',
            'registration_number'     => '',
            'contact_address'         => '',
            'contact_email'           => '',
            'copyright_template'      => '© {year} {publisher}. All rights reserved.',
            'default_crawl_author'    => '',
            'crawler_user_agent_name' => '',
            // Content settings
            'editor_note_text'        => '',
            'editor_note_font_family' => 'var(--ui)',
            'editor_note_font_size'   => '15px',
            'editor_note_color'       => '#666666',
            'footer_about'            => '',
            'footer_tip_line'         => 'news@example.com',
            'footer_ads'              => 'ads@example.com',
            'newsletter_intro'        => 'Get the best stories in your inbox. No spam. No circus.',
            'newsletter_title'        => 'Stay informed',
            'newsletter_button'       => 'Subscribe',
            'newsletter_success'      => 'Welcome aboard! Check your inbox.',
            'newsletter_disclaimer'   => 'By subscribing you agree to receive editorial emails. Unsubscribe anytime.',
            'newsletter_bg_color'     => '',
            'newsletter_show_name'    => '1',
            'newsletter_enabled'      => '1',
            // Location label (topbar)
            'location_label_mode'     => 'auto',
            'location_label_static'   => '',
            'location_label_title'    => 'Location',
        ];

        // ── Theme fields ─────────────────────────────────────────────
        $themeFields = [
            // Colours
            'theme_ink'          => '#121212',
            'theme_paper'        => '#fdfdfd',
            'theme_surface'      => '#ffffff',
            'theme_border'       => '#e2e2e2',
            'theme_muted'        => '#666666',
            'theme_accent'       => '#cc0000',
            'theme_accent_dark'  => '#aa0000',
            'theme_accent_text'  => '#ffffff',
            'theme_selection_bg' => 'rgba(204,0,0,.12)',
            // Dark-mode colour overrides
            'theme_ink_dark'          => '#e0e0e0',
            'theme_paper_dark'        => '#1a1a1a',
            'theme_surface_dark'      => '#242424',
            'theme_border_dark'       => '#3a3a3a',
            'theme_muted_dark'        => '#999999',
            'theme_selection_bg_dark' => 'rgba(204,0,0,.22)',
            // Typography
            'theme_font_serif'   => 'Georgia, "Times New Roman", Times, serif',
            'theme_font_ui'      => '"Libre Franklin", system-ui, -apple-system, Segoe UI, sans-serif',
            'theme_font_mast'    => '"UnifrakturMaguntia", Georgia, serif',
            'theme_font_base'    => '18px',
            'theme_font_article' => '21px',
            'theme_line_height'  => '1.7',
            // Shape
            'theme_radius'       => '16px',
            'theme_max_width'    => '1180px',
            'theme_content_max'  => '1000px',
            'theme_card_pad'     => '20px',
            // Animation
            'theme_speed'        => '0.18s',
            'theme_speed_slow'   => '0.35s',
            'theme_ease'         => 'cubic-bezier(.2,.8,.2,1)',
            // FIX (C-01): theme_mode is handled with explicit whitelist validation below.
            // It is kept in this array so the loop processes it, but the value is not
            // blindly defaulted to 'light' when the key is absent from POST.
            'theme_mode'         => 'light',
        ];

        // ── Branding fields ──────────────────────────────────────────
        $brandingFields = [
            'site_logo_url'       => '',
            // UPGRADE (A-02): Dark-mode logo URL — used by frontend/layout.php to
            // populate data-dark-src on logo <img> tags for src-swap (no CSS filter).
            'site_logo_dark_url'  => '',
            'favicon_url'         => '',
            'og_default_image'    => '/assets/og-default.png',
            'admin_logo'              => '',
            'admin_sidebar_title'     => '',
            'admin_sidebar_subtitle'  => '',
            'og_locale'               => 'en_US',
            'twitter_handle'      => '',
        ];

        // ── Email / SMTP fields ─────────────────────────────────────
        $emailFields = [
            'mail_driver'       => 'smtp',
            'mail_host'         => '',
            'mail_port'         => '587',
            'mail_username'     => '',
            'mail_password'     => '',
            'mail_encryption'   => 'tls',
            'mail_from_address' => '',
            'mail_from_name'    => '',
        ];

        // ── Domain fields ───────────────────────────────────────────
        $domainFields = [
            'site_url'       => '',
            'site_domain'    => '',
            'site_timezone'  => 'Africa/Kampala',
            'analytics_id'   => '',
        ];

        // Detect which form was submitted via hidden _form field
        $formId           = $request->request->get('_form', '');
        $isThemeSubmit    = $formId === 'theme'
                         || $request->request->has('theme_mode')
                         || $request->request->has('theme_ink');
        $isBrandingSubmit = $formId === 'branding';
        $isEmailSubmit    = $formId === 'email';
        $isDomainSubmit   = $formId === 'domain';

        if ($isThemeSubmit) {
            $fieldsToSave = $themeFields;
            $group        = 'theme';
        } elseif ($isBrandingSubmit) {
            $fieldsToSave = $brandingFields;
            $group        = 'branding';
        } elseif ($isEmailSubmit) {
            $fieldsToSave = $emailFields;
            $group        = 'email';
        } elseif ($isDomainSubmit) {
            $fieldsToSave = $domainFields;
            $group        = 'domain';
        } else {
            $fieldsToSave = $generalFields;
            $group        = 'general';
        }

        // ── Whitelist: fields whose values must match a fixed set ────
        // FIX (C-01): theme_mode was silently forced to 'light' whenever the
        // POST key was absent (e.g. radio group not submitted, JS-disabled browser,
        // partial AJAX form). This meant any theme save that didn't include the
        // radio group would wipe the DB back to 'light'.
        //
        // The correct behaviour is:
        //   a) If theme_mode IS in POST → validate against whitelist, store it.
        //   b) If theme_mode is NOT in POST → skip the key entirely (preserve DB).
        //
        // FIX (S-02): All enum fields are now validated against a strict whitelist,
        // preventing crafted values from slipping past the tag-strip sanitiser.
        $enumFields = [
            'theme_mode'       => ['light', 'dark', 'system'],
            'mail_driver'      => ['smtp', 'sendmail'],
            'mail_encryption'  => ['tls', 'ssl', 'none', ''],
            'newsletter_show_name' => ['0', '1'],
            'newsletter_enabled'   => ['0', '1'],
            'location_label_mode'  => ['auto', 'static', 'off'],
        ];

        // ── CSS colour / value token safe-list ───────────────────────
        // FIX (S-02): get_theme_css() $san() strips tags and 'expression(' but
        // does not block 'url()' or 'javascript:' wrappers in crafted colour values.
        // Validate that colour tokens start with # or rgba/rgb/hsl — reject others.
        $colourFields = [
            'theme_ink', 'theme_paper', 'theme_surface', 'theme_border',
            'theme_muted', 'theme_accent', 'theme_accent_dark', 'theme_accent_text',
            'theme_selection_bg', 'theme_ink_dark', 'theme_paper_dark',
            'theme_surface_dark', 'theme_border_dark', 'theme_muted_dark',
            'theme_selection_bg_dark',
        ];

        foreach ($fieldsToSave as $key => $default) {
            $value = $request->request->get($key, null);

            // ── FIX (C-01): Enum fields — skip if absent from POST ────
            // For radio/select fields (like theme_mode), if the browser did not
            // submit the key at all, preserve the existing DB value rather than
            // resetting to the hardcoded default. This prevents a partial theme
            // form submission from wiping the dark/system mode selection.
            if (isset($enumFields[$key])) {
                if ($value === null) {
                    // Key absent from POST: do not touch DB row for this field.
                    continue;
                }
                $value = trim((string)$value);
                // Normalise legacy 'auto' → 'system' for theme_mode
                if ($key === 'theme_mode' && $value === 'auto') {
                    $value = 'system';
                }
                if (!in_array($value, $enumFields[$key], true)) {
                    // Invalid value submitted — use the default (do not skip, do not error)
                    $value = $default;
                }
                $upsert->execute([':key' => $key, ':value' => $value, ':group' => $group]);
                continue;
            }

            // ── Standard fields ───────────────────────────────────────
            if ($value === null) {
                $value = $default;
            }

            $value = trim((string)$value);

            // Strip all HTML tags
            $value = strip_tags($value);
            $value = trim($value);

            // FIX (S-02): Validate CSS colour tokens against a safe format.
            // Rejects url(), javascript:, expression() even if tag-strip missed them.
            if (in_array($key, $colourFields, true) && $value !== '') {
                $safeColour = preg_match(
                    '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]{3,60}\)|hsla?\([^)]{3,60}\))$/',
                    $value
                );
                if (!$safeColour) {
                    // Discard invalid colour value, fall back to default
                    $value = $default;
                }
            }

            // Special: don't overwrite mail_password with empty (user left placeholder)
            if ($key === 'mail_password' && $value === '') {
                continue;
            }

            // Fields that are allowed to be blank
            $blankAllowed = [
                'editor_note_text', 'footer_about', 'site_logo_url', 'site_logo_dark_url',
                'favicon_url', 'location_label_static', 'newsletter_bg_color',
                'mail_host', 'mail_username', 'mail_password', 'mail_from_address',
                'mail_from_name', 'admin_logo', 'admin_sidebar_title', 'admin_sidebar_subtitle', 'twitter_handle',
                'site_url', 'site_domain', 'analytics_id',
            ];

            if ($value === '' && !in_array($key, $blankAllowed, true)) {
                $value = $default;
            }

            $upsert->execute([':key' => $key, ':value' => $value, ':group' => $group]);
        }

        // ── FIX (C-02): Safe cache-bust increment ────────────────────
        // Previously used (string)time() which creates an ever-growing integer and
        // could theoretically overflow. Using time() as a unix timestamp is correct
        // and robust — it is always numeric, always increasing, and stable under the
        // int cast. Replacing the raw (string)$ver + 1 pattern that cast non-numeric
        // DB strings to 0 and got stuck at version "1" forever.
        $upsert->execute([
            ':key'   => 'theme_cache_bust',
            ':value' => (string)time(),
            ':group' => 'system',
        ]);

        if ($isThemeSubmit) {
            $msg = 'Theme saved successfully.';
        } elseif ($isBrandingSubmit) {
            $msg = 'Branding saved successfully.';
        } elseif ($isEmailSubmit) {
            $msg = 'Email settings saved. Use the "Send Test" button on the Newsletter page to verify.';
        } elseif ($isDomainSubmit) {
            $msg = 'Domain settings saved.';
        } else {
            $msg = 'Settings saved successfully.';
        }
        Flash::set('success', $msg);
        return $this->redirect('/admin/settings');
    }
}
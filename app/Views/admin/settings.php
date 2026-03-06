<?php
$pageTitle = 'Settings';
$activeNav = 'settings';

// Theme presets
$presets = [
  'classic' => [
    'label'       => 'Classic',
    'description' => 'Default newspaper look',
    'values' => [
      'theme_ink'          => '#121212',
      'theme_paper'        => '#fdfdfd',
      'theme_surface'      => '#ffffff',
      'theme_border'       => '#e2e2e2',
      'theme_muted'        => '#666666',
      'theme_accent'       => '#cc0000',
      'theme_accent_dark'  => '#aa0000',
      'theme_accent_text'  => '#ffffff',
      'theme_selection_bg' => 'rgba(204,0,0,.12)',
      'theme_font_base'    => '18px',
      'theme_font_article' => '21px',
      'theme_line_height'  => '1.7',
      'theme_radius'       => '16px',
      'theme_mode'         => 'light',
    ],
  ],
  'midnight' => [
    'label'       => 'Midnight',
    'description' => 'Dark amber editorial',
    'values' => [
      'theme_ink'          => '#f5e6c8',
      'theme_paper'        => '#0d0d0d',
      'theme_surface'      => '#1a1a1a',
      'theme_border'       => '#2e2e2e',
      'theme_muted'        => '#888888',
      'theme_accent'       => '#e8a030',
      'theme_accent_dark'  => '#c8881a',
      'theme_accent_text'  => '#0d0d0d',
      'theme_selection_bg' => 'rgba(232,160,48,.2)',
      'theme_font_base'    => '18px',
      'theme_font_article' => '21px',
      'theme_line_height'  => '1.75',
      'theme_radius'       => '12px',
      'theme_mode'         => 'dark',
    ],
  ],
  'slate' => [
    'label'       => 'Slate',
    'description' => 'Cool grey & blue',
    'values' => [
      'theme_ink'          => '#1e2533',
      'theme_paper'        => '#f4f6f9',
      'theme_surface'      => '#ffffff',
      'theme_border'       => '#dde2ea',
      'theme_muted'        => '#6b7a90',
      'theme_accent'       => '#2563eb',
      'theme_accent_dark'  => '#1d4ed8',
      'theme_accent_text'  => '#ffffff',
      'theme_selection_bg' => 'rgba(37,99,235,.14)',
      'theme_font_base'    => '17px',
      'theme_font_article' => '20px',
      'theme_line_height'  => '1.7',
      'theme_radius'       => '10px',
      'theme_mode'         => 'light',
    ],
  ],
  'sepia' => [
    'label'       => 'Sepia',
    'description' => 'Warm cream & burgundy',
    'values' => [
      'theme_ink'          => '#2c1810',
      'theme_paper'        => '#f9f3e8',
      'theme_surface'      => '#fdf8f0',
      'theme_border'       => '#ddd0ba',
      'theme_muted'        => '#8b6e5a',
      'theme_accent'       => '#8b1a2e',
      'theme_accent_dark'  => '#6e1424',
      'theme_accent_text'  => '#ffffff',
      'theme_selection_bg' => 'rgba(139,26,46,.14)',
      'theme_font_base'    => '18px',
      'theme_font_article' => '21px',
      'theme_line_height'  => '1.8',
      'theme_radius'       => '8px',
      'theme_mode'         => 'light',
    ],
  ],
  'forest' => [
    'label'       => 'Forest',
    'description' => 'Off-white & deep green',
    'values' => [
      'theme_ink'          => '#1a2e1a',
      'theme_paper'        => '#f6f9f4',
      'theme_surface'      => '#ffffff',
      'theme_border'       => '#d4e0d0',
      'theme_muted'        => '#5a7a5a',
      'theme_accent'       => '#2d6a2d',
      'theme_accent_dark'  => '#1e4d1e',
      'theme_accent_text'  => '#ffffff',
      'theme_selection_bg' => 'rgba(45,106,45,.14)',
      'theme_font_base'    => '18px',
      'theme_font_article' => '21px',
      'theme_line_height'  => '1.72',
      'theme_radius'       => '14px',
      'theme_mode'         => 'light',
    ],
  ],
  'mono' => [
    'label'       => 'Mono',
    'description' => 'Pure black & white',
    'values' => [
      'theme_ink'          => '#000000',
      'theme_paper'        => '#ffffff',
      'theme_surface'      => '#ffffff',
      'theme_border'       => '#cccccc',
      'theme_muted'        => '#555555',
      'theme_accent'       => '#000000',
      'theme_accent_dark'  => '#333333',
      'theme_accent_text'  => '#ffffff',
      'theme_selection_bg' => 'rgba(0,0,0,.12)',
      'theme_font_base'    => '18px',
      'theme_font_article' => '21px',
      'theme_line_height'  => '1.7',
      'theme_radius'       => '4px',
      'theme_mode'         => 'light',
    ],
  ],
];

ob_start();
?>

<div style="max-width:1060px">

<div class="page-header">
  <div>
    <h1>Settings</h1>
    <div class="sub">Site content, branding, and visual theme.</div>
  </div>
</div>

<?php if (!empty($flash_success)): ?>
  <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- ─── GENERAL SETTINGS ─────────────────────────────────────── -->
<form method="POST" action="/admin/settings" id="generalForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <div class="card" style="margin-bottom:24px">
    <div style="font-weight:700;font-size:15px;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border)">
      General
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div class="form-group">
        <label class="form-label">Site Title</label>
        <input name="site_title" class="form-control" value="<?= h($settings['site_title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Site Tagline</label>
        <input name="site_tagline" class="form-control" value="<?= h($settings['site_tagline'] ?? '') ?>">
      </div>
    </div>

    <!-- ── White-Label Identity ─────────────────────────────── -->
    <div style="margin:0 0 8px;padding:12px 16px;background:#fafafa;border:1px solid #e2e2e2;border-radius:10px">
      <div style="font-size:13px;font-weight:700;color:#555;margin-bottom:12px;letter-spacing:.04em;text-transform:uppercase">Publisher Identity</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group" style="margin:0">
          <label class="form-label">Publisher / Legal Name</label>
          <input name="publisher_name" class="form-control" value="<?= h($settings['publisher_name'] ?? '') ?>" placeholder="e.g. Acme Media Ltd">
          <p style="margin:4px 0 0;font-size:12px;color:#888">Used in copyright line and legal notices.</p>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Site Abbreviation</label>
          <input name="site_abbreviation" class="form-control" value="<?= h($settings['site_abbreviation'] ?? '') ?>" placeholder="e.g. NT" maxlength="10">
          <p style="margin:4px 0 0;font-size:12px;color:#888">Short code shown in crawled article avatar and metadata.</p>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group" style="margin:0">
          <label class="form-label">Crawled Content Author Name</label>
          <input name="default_crawl_author" class="form-control" value="<?= h($settings['default_crawl_author'] ?? '') ?>" placeholder="e.g. NT Newsroom">
          <p style="margin:4px 0 0;font-size:12px;color:#888">Displayed as the author on all auto-crawled articles.</p>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Crawler User-Agent Name</label>
          <input name="crawler_user_agent_name" class="form-control" value="<?= h($settings['crawler_user_agent_name'] ?? '') ?>" placeholder="e.g. NTCrawler/1.0">
          <p style="margin:4px 0 0;font-size:12px;color:#888">Identifies your crawler to external sites.</p>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group" style="margin:0">
          <label class="form-label">Copyright Template</label>
          <input name="copyright_template" class="form-control" value="<?= h($settings['copyright_template'] ?? '© {year} {publisher}. All rights reserved.') ?>" placeholder="© {year} {publisher}. All rights reserved.">
          <p style="margin:4px 0 0;font-size:12px;color:#888">Variables: <code>{year}</code>, <code>{publisher}</code>, <code>{abbreviation}</code></p>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Registration / Company Number</label>
          <input name="registration_number" class="form-control" value="<?= h($settings['registration_number'] ?? '') ?>" placeholder="Optional — shown in legal footer">
        </div>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Contact / Registered Address</label>
        <input name="contact_address" class="form-control" value="<?= h($settings['contact_address'] ?? '') ?>" placeholder="e.g. P.O. Box 123, Kampala, Uganda">
      </div>
      <div class="form-group" style="margin:8px 0 0">
        <label class="form-label">Public Contact Email</label>
        <input type="email" name="contact_email" class="form-control" value="<?= h($settings['contact_email'] ?? '') ?>" placeholder="e.g. contact@yourdomain.com">
        <p style="margin:4px 0 0;font-size:12px;color:#888">Shown on policy/about pages and used in JSON-LD organization schema.</p>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Editor's Note Text</label>
      <textarea name="editor_note_text" rows="4" class="form-control"><?= h($settings['editor_note_text'] ?? '') ?></textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Editor's Note Font</label>
        <input name="editor_note_font_family" class="form-control" value="<?= h($settings['editor_note_font_family'] ?? 'var(--ui)') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Editor's Note Size</label>
        <input name="editor_note_font_size" class="form-control" value="<?= h($settings['editor_note_font_size'] ?? '15px') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Editor's Note Colour</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input type="color" name="editor_note_color" value="<?= h($settings['editor_note_color'] ?? '#666666') ?>" style="width:46px;height:38px;border:1px solid var(--border);border-radius:8px;padding:2px;cursor:pointer">
          <input type="text" name="editor_note_color_text" value="<?= h($settings['editor_note_color'] ?? '#666666') ?>" class="form-control" style="width:100px" placeholder="#666666">
        </div>
      </div>
    </div>

    <div class="form-group" style="margin-top:16px">
      <label class="form-label">Footer About Text</label>
      <textarea name="footer_about" rows="4" class="form-control"><?= h($settings['footer_about'] ?? '') ?></textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Tip Line Email</label>
        <input name="footer_tip_line" type="email" class="form-control" value="<?= h($settings['footer_tip_line'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Ads Email</label>
        <input name="footer_ads" type="email" class="form-control" value="<?= h($settings['footer_ads'] ?? '') ?>">
      </div>
    </div>

    <!-- ── Newsletter Configuration ── -->
    <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border)">
      Newsletter Subscription Bar
    </div>
    <div class="form-hint" style="margin-bottom:14px">This controls the newsletter signup bar in the footer on all pages.</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Headline</label>
        <input name="newsletter_title" class="form-control" value="<?= h($settings['newsletter_title'] ?? 'Stay informed') ?>" placeholder="Stay informed">
      </div>
      <div class="form-group">
        <label class="form-label">Button Text</label>
        <input name="newsletter_button" class="form-control" value="<?= h($settings['newsletter_button'] ?? 'Subscribe') ?>" placeholder="Subscribe">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Description Text</label>
      <input name="newsletter_intro" class="form-control" value="<?= h($settings['newsletter_intro'] ?? '') ?>" placeholder="Get the best stories in your inbox. No spam. No circus.">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Success Message</label>
        <input name="newsletter_success" class="form-control" value="<?= h($settings['newsletter_success'] ?? 'Welcome aboard! Check your inbox.') ?>" placeholder="Welcome aboard!">
      </div>
      <div class="form-group">
        <label class="form-label">Disclaimer Text</label>
        <input name="newsletter_disclaimer" class="form-control" value="<?= h($settings['newsletter_disclaimer'] ?? 'By subscribing you agree to receive editorial emails. Unsubscribe anytime.') ?>" placeholder="Unsubscribe anytime.">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Bar Background</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input name="newsletter_bg_color" type="color" value="<?= h($settings['newsletter_bg_color'] ?? '') ?>"
                 style="width:42px;height:36px;border:1px solid var(--border);border-radius:8px;padding:2px;cursor:pointer"
                 id="nlBgColor">
          <select class="form-control" style="flex:1;font-size:12px" onchange="document.getElementById('nlBgColor').value=this.value">
            <option value="">Use theme accent</option>
            <option value="#cc0000" <?= ($settings['newsletter_bg_color'] ?? '') === '#cc0000' ? 'selected' : '' ?>>Red</option>
            <option value="#1a1a1a" <?= ($settings['newsletter_bg_color'] ?? '') === '#1a1a1a' ? 'selected' : '' ?>>Dark</option>
            <option value="#0a5a2e" <?= ($settings['newsletter_bg_color'] ?? '') === '#0a5a2e' ? 'selected' : '' ?>>Green</option>
            <option value="#1a3a6b" <?= ($settings['newsletter_bg_color'] ?? '') === '#1a3a6b' ? 'selected' : '' ?>>Navy</option>
          </select>
        </div>
        <div class="form-hint">Leave empty to match theme accent color.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Show Name Field</label>
        <select name="newsletter_show_name" class="form-control">
          <option value="1" <?= ($settings['newsletter_show_name'] ?? '1') === '1' ? 'selected' : '' ?>>Yes — show name input</option>
          <option value="0" <?= ($settings['newsletter_show_name'] ?? '1') === '0' ? 'selected' : '' ?>>No — email only</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Newsletter Enabled</label>
        <select name="newsletter_enabled" class="form-control">
          <option value="1" <?= ($settings['newsletter_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Visible</option>
          <option value="0" <?= ($settings['newsletter_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Hidden</option>
        </select>
      </div>
    </div>

    <!-- ── Location Label ── -->
    <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border)">
      Topbar Location Label
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Mode</label>
        <select name="location_label_mode" id="locationMode" class="form-control"
                onchange="document.getElementById('staticLabelRow').style.display=this.value==='static'?'':'none'">
          <?php foreach (['auto' => 'Auto (JS + timezone)', 'static' => 'Static text', 'off' => 'Off (hidden)'] as $v => $l): ?>
            <option value="<?= h($v) ?>" <?= ($settings['location_label_mode'] ?? 'auto') === $v ? 'selected' : '' ?>>
              <?= h($l) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Auto tries /api/geo then falls back to user's timezone.</div>
      </div>
      <div class="form-group" id="staticLabelRow"
           style="display:<?= ($settings['location_label_mode'] ?? 'auto') === 'static' ? '' : 'none' ?>">
        <label class="form-label">Static Label Text</label>
        <input name="location_label_static" class="form-control"
               value="<?= h($settings['location_label_static'] ?? '') ?>"
               placeholder="e.g. Gulu, Uganda">
        <div class="form-hint">Shown server-side — no JS, no flicker.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Tooltip / Title</label>
        <input name="location_label_title" class="form-control"
               value="<?= h($settings['location_label_title'] ?? 'Location') ?>"
               placeholder="Location">
        <div class="form-hint">Shown on hover of the label text.</div>
      </div>
    </div>
  </div>

  <div style="margin-bottom:32px">
    <button class="btn" type="submit">Save General Settings</button>
    <a class="btn light" href="/admin" style="margin-left:10px">Cancel</a>
  </div>
</form>

<!-- ─── BRANDING ─────────────────────────────────────────────── -->
<form method="POST" action="/admin/settings" id="brandingForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="_form" value="branding">

  <div class="card" style="margin-bottom:24px">
    <div style="font-weight:700;font-size:15px;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border)">
      Branding
      <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:10px">Logo, favicon, and social share image</span>
    </div>

    <!-- Site Logo -->
    <div class="form-group" style="margin-bottom:24px">
      <label class="form-label">Site Logo</label>
      <div class="form-hint" style="margin-bottom:10px">Upload a PNG, SVG or WebP image. Leave empty to use the blackletter text masthead.</div>
      <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <?php if (!empty($settings['site_logo_url'])): ?>
          <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--paper);flex-shrink:0">
            <img src="<?= h($settings['site_logo_url']) ?>" alt="Current logo"
                 style="max-height:60px;width:auto;max-width:200px;display:block" />
            <div class="form-hint" style="margin-top:6px;text-align:center">Current logo</div>
          </div>
        <?php else: ?>
          <div style="border:1px dashed var(--border);border-radius:10px;padding:16px 20px;background:var(--paper);color:var(--muted);font-size:13px;flex-shrink:0">
            No logo — using text masthead
          </div>
        <?php endif; ?>
        <div style="flex:1;min-width:260px">
          <input type="text"
                 name="site_logo_url"
                 id="site_logo_url"
                 class="form-control"
                 value="<?= h($settings['site_logo_url'] ?? '') ?>"
                 placeholder="e.g. /uploads/Branding/logo.png"
                 style="margin-bottom:8px">
          <button type="button"
                  class="btn light sm"
                  onclick="openBrandingPicker('site_logo_url','logo-preview')">
            Pick from Media Library
          </button>
          <?php if (!empty($settings['site_logo_url'])): ?>
            <button type="button"
                    class="btn light sm"
                    style="margin-left:8px;color:#c00"
                    onclick="document.getElementById('site_logo_url').value=''">
              Remove Logo
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Dark Mode Logo -->
    <!-- UPGRADE: site_logo_dark_url is read by layout.php (frontend) and
         dark-mode.js for the data-dark-src logo swap system.
         If blank, dark-mode.js falls back to a CSS filter inversion.
         Shown only when theme_mode is dark or system. -->
    <div class="form-group" style="margin-bottom:24px" id="dark-logo-section"
         style="display:<?= in_array($settings['theme_mode'] ?? 'light', ['dark','system','auto']) ? '' : 'none' ?>">
      <label class="form-label">Dark Mode Logo
        <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:6px">Optional — used when site is in dark mode</span>
      </label>
      <div class="form-hint" style="margin-bottom:10px">
        Upload a logo optimised for dark backgrounds (e.g. white or light version).
        If not set, the light logo will be CSS-inverted automatically.
      </div>
      <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <?php if (!empty($settings['site_logo_dark_url'])): ?>
          <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#1a1a1a;flex-shrink:0">
            <img src="<?= h($settings['site_logo_dark_url']) ?>" alt="Current dark logo"
                 style="max-height:60px;width:auto;max-width:200px;display:block" />
            <div class="form-hint" style="margin-top:6px;text-align:center;color:#888">Dark mode logo</div>
          </div>
        <?php else: ?>
          <div style="border:1px dashed var(--border);border-radius:10px;padding:16px 20px;background:#1a1a1a;color:#888;font-size:13px;flex-shrink:0">
            No dark logo — will use CSS filter fallback
          </div>
        <?php endif; ?>
        <div style="flex:1;min-width:260px">
          <input type="text"
                 name="site_logo_dark_url"
                 id="site_logo_dark_url"
                 class="form-control"
                 value="<?= h($settings['site_logo_dark_url'] ?? '') ?>"
                 placeholder="e.g. /uploads/Branding/logo-dark.png"
                 style="margin-bottom:8px">
          <button type="button"
                  class="btn light sm"
                  onclick="openBrandingPicker('site_logo_dark_url','dark-logo-preview')">
            Pick from Media Library
          </button>
          <?php if (!empty($settings['site_logo_dark_url'])): ?>
            <button type="button"
                    class="btn light sm"
                    style="margin-left:8px;color:#c00"
                    onclick="document.getElementById('site_logo_dark_url').value=''">
              Remove Dark Logo
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Favicon -->
    <div class="form-group" style="margin-bottom:24px">
      <label class="form-label">Favicon</label>
      <div class="form-hint" style="margin-bottom:10px">Shown in browser tabs. Use a square PNG or ICO, ideally 32×32 px or larger.</div>
      <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <?php if (!empty($settings['favicon_url'])): ?>
          <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--paper);flex-shrink:0;text-align:center">
            <img src="<?= h($settings['favicon_url']) ?>" alt="Current favicon"
                 style="width:32px;height:32px;object-fit:contain;display:block;margin:0 auto" />
            <div class="form-hint" style="margin-top:6px">Current favicon</div>
          </div>
        <?php else: ?>
          <div style="border:1px dashed var(--border);border-radius:10px;padding:16px 20px;background:var(--paper);color:var(--muted);font-size:13px;flex-shrink:0">
            No favicon set
          </div>
        <?php endif; ?>
        <div style="flex:1;min-width:260px">
          <input type="text"
                 name="favicon_url"
                 id="favicon_url"
                 class="form-control"
                 value="<?= h($settings['favicon_url'] ?? '') ?>"
                 placeholder="e.g. /uploads/Branding/favicon.png"
                 style="margin-bottom:8px">
          <button type="button"
                  class="btn light sm"
                  onclick="openBrandingPicker('favicon_url','favicon-preview')">
            Pick from Media Library
          </button>
          <?php if (!empty($settings['favicon_url'])): ?>
            <button type="button"
                    class="btn light sm"
                    style="margin-left:8px;color:#c00"
                    onclick="document.getElementById('favicon_url').value=''">
              Remove
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- OG Default Image -->
    <div class="form-group">
      <label class="form-label">Default Social Share Image</label>
      <div class="form-hint" style="margin-bottom:10px">Used for OG/Twitter cards when an article has no featured image. Recommended: 1200×630 px.</div>
      <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <?php
          $ogVal = $settings['og_default_image'] ?? '/assets/og-default.png';
        ?>
        <?php if ($ogVal): ?>
          <div style="border:1px solid var(--border);border-radius:10px;padding:8px;background:var(--paper);flex-shrink:0">
            <img src="<?= h($ogVal) ?>" alt="OG image"
                 style="max-height:80px;width:auto;max-width:180px;display:block;border-radius:6px" />
          </div>
        <?php endif; ?>
        <div style="flex:1;min-width:260px">
          <input type="text"
                 name="og_default_image"
                 id="og_default_image"
                 class="form-control"
                 value="<?= h($ogVal) ?>"
                 placeholder="/assets/og-default.png"
                 style="margin-bottom:8px">
          <button type="button"
                  class="btn light sm"
                  onclick="openBrandingPicker('og_default_image','og-preview')">
            Pick from Media Library
          </button>
        </div>
      </div>
    </div>

    <!-- Admin Logo (separate from public logo) -->
    <div class="form-group" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
      <label class="form-label">Admin Panel Logo <span style="font-size:11px;font-weight:400;color:var(--muted)">(optional — defaults to site logo)</span></label>
      <div class="form-hint" style="margin-bottom:10px">A separate logo displayed in the admin sidebar. Useful for white-label deployments where the admin brand differs from the public site.</div>
      <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <?php if (!empty($settings['admin_logo'])): ?>
          <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#1a1a2e;flex-shrink:0">
            <img src="<?= h($settings['admin_logo']) ?>" alt="Admin logo"
                 style="max-height:40px;width:auto;max-width:160px;display:block" />
            <div class="form-hint" style="margin-top:6px;text-align:center;color:#888">Current admin logo</div>
          </div>
        <?php else: ?>
          <div style="border:1px dashed var(--border);border-radius:10px;padding:14px 18px;background:#1a1a2e;color:#666;font-size:13px;flex-shrink:0">
            Using public site logo
          </div>
        <?php endif; ?>
        <div style="flex:1;min-width:260px">
          <input type="text"
                 name="admin_logo"
                 id="admin_logo"
                 class="form-control"
                 value="<?= h($settings['admin_logo'] ?? '') ?>"
                 placeholder="e.g. /uploads/Branding/admin-logo.png"
                 style="margin-bottom:8px">
          <button type="button"
                  class="btn light sm"
                  onclick="openBrandingPicker('admin_logo','admin-logo-preview')">
            Pick from Media Library
          </button>
          <?php if (!empty($settings['admin_logo'])): ?>
            <button type="button"
                    class="btn light sm"
                    style="margin-left:8px;color:#c00"
                    onclick="document.getElementById('admin_logo').value=''">
              Remove
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Admin Sidebar Title + Subtitle -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
      <div class="form-group">
        <label class="form-label" for="admin_sidebar_title">
          Sidebar Title
          <span style="font-size:11px;font-weight:400;color:var(--muted)">(defaults to site title)</span>
        </label>
        <div class="form-hint">Replaces the site name shown in the admin sidebar header.</div>
        <input type="text"
               name="admin_sidebar_title"
               id="admin_sidebar_title"
               class="form-control"
               value="<?= h($settings['admin_sidebar_title'] ?? '') ?>"
               placeholder="e.g. The Northern Times">
      </div>
      <div class="form-group">
        <label class="form-label" for="admin_sidebar_subtitle">
          Sidebar Subtitle
          <span style="font-size:11px;font-weight:400;color:var(--muted)">(defaults to abbreviation + Newsroom)</span>
        </label>
        <div class="form-hint">The small text shown below the title in the sidebar.</div>
        <input type="text"
               name="admin_sidebar_subtitle"
               id="admin_sidebar_subtitle"
               class="form-control"
               value="<?= h($settings['admin_sidebar_subtitle'] ?? '') ?>"
               placeholder="e.g. NT Newsroom">
      </div>
    </div>

    <!-- OG Locale + Twitter Handle -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
      <div class="form-group" style="margin:0">
        <label class="form-label">OG Locale</label>
        <input name="og_locale" class="form-control" value="<?= h($settings['og_locale'] ?? 'en_US') ?>" placeholder="en_US">
        <p style="margin:4px 0 0;font-size:12px;color:#888">e.g. <code>en_UG</code>, <code>en_US</code>, <code>fr_FR</code></p>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Twitter / X Handle</label>
        <input name="twitter_handle" class="form-control" value="<?= h($settings['twitter_handle'] ?? '') ?>" placeholder="@YourHandle">
        <p style="margin:4px 0 0;font-size:12px;color:#888">Used in Twitter Card <code>twitter:site</code> meta tag.</p>
      </div>
    </div>
  </div>

  <div style="margin-bottom:32px">
    <button class="btn" type="submit">Save Branding</button>
    <a class="btn light" href="/admin" style="margin-left:10px">Cancel</a>
  </div>
</form>

<script>
(function(){
  // ── Inline Media Picker Modal ─────────────────────────────────
  // Creates a full-screen overlay with an iframe pointing to the picker
  var modal = document.createElement('div');
  modal.id = 'mediaPickerModal';
  modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);padding:24px;align-items:center;justify-content:center';
  modal.innerHTML = '<div style="background:#fff;border-radius:16px;width:100%;max-width:940px;height:85vh;max-height:700px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.3);display:flex;flex-direction:column;position:relative">'
    + '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #e2e2e2;flex-shrink:0">'
    + '<span style="font-weight:700;font-size:15px">Pick from Media Library</span>'
    + '<button type="button" id="mediaPickerClose" style="background:none;border:none;font-size:22px;cursor:pointer;color:#666;padding:4px 8px;border-radius:8px" title="Close">&times;</button>'
    + '</div>'
    + '<iframe id="mediaPickerFrame" style="flex:1;border:none;width:100%" src="about:blank"></iframe>'
    + '</div>';
  document.body.appendChild(modal);

  var pickerFrame = document.getElementById('mediaPickerFrame');
  var pickerClose = document.getElementById('mediaPickerClose');

  function openBrandingPicker(fieldId) {
    pickerFrame.src = '/admin/media/picker?context=branding&field=' + encodeURIComponent(fieldId);
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  window.openBrandingPicker = openBrandingPicker;

  function closePickerModal() {
    modal.style.display = 'none';
    document.body.style.overflow = '';
    pickerFrame.src = 'about:blank';
  }

  pickerClose.addEventListener('click', closePickerModal);
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closePickerModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display === 'flex') closePickerModal();
  });

  // Listen for postMessage from picker iframe
  window.addEventListener('message', function(e) {
    if (e.origin !== window.location.origin) return;
    if (!e.data) return;

    // Handle media selection
    if (e.data.type === 'media_pick') {
      var field = document.getElementById(e.data.field);
      if (field) {
        field.value = e.data.url;
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }
      closePickerModal();
    }

    // Handle picker requesting close (Cancel button in iframe)
    if (e.data.type === 'media_picker_close') {
      closePickerModal();
    }
  });
})();
</script>

<!-- ─── EMAIL / SMTP SETTINGS ────────────────────────────────── -->
<form method="POST" action="/admin/settings" id="emailForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="_form" value="email">

  <div class="card" style="margin-bottom:24px">
    <div style="font-weight:700;font-size:15px;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border)">
      Email / SMTP
    </div>

    <p class="muted" style="margin-bottom:16px;font-size:13px">
      Configure how the system sends emails (newsletters, notifications, password resets). Without SMTP, emails will not be delivered from Docker environments.
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div class="form-group">
        <label class="form-label">Mail Driver</label>
        <select name="mail_driver" class="form-control" id="mailDriverSelect">
          <option value="smtp" <?= ($settings['mail_driver'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP (recommended)</option>
          <option value="mail" <?= ($settings['mail_driver'] ?? 'smtp') === 'mail' ? 'selected' : '' ?>>PHP mail() (requires local MTA)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Encryption</label>
        <select name="mail_encryption" class="form-control">
          <option value="tls" <?= ($settings['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (port 587)</option>
          <option value="ssl" <?= ($settings['mail_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
          <option value="none" <?= ($settings['mail_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>None (port 25)</option>
        </select>
      </div>
    </div>

    <div id="smtpFields">
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group">
          <label class="form-label">SMTP Host</label>
          <input name="mail_host" class="form-control" value="<?= h($settings['mail_host'] ?? '') ?>"
                 placeholder="e.g. smtp.gmail.com">
        </div>
        <div class="form-group">
          <label class="form-label">SMTP Port</label>
          <input name="mail_port" class="form-control" type="number" value="<?= h($settings['mail_port'] ?? '587') ?>"
                 placeholder="587">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group">
          <label class="form-label">SMTP Username</label>
          <input name="mail_username" class="form-control" value="<?= h($settings['mail_username'] ?? '') ?>"
                 placeholder="your-email@gmail.com" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">SMTP Password</label>
          <input name="mail_password" class="form-control" type="password" value="<?= h($settings['mail_password'] ?? '') ?>"
                 placeholder="<?= !empty($settings['mail_password']) ? '••••••••' : 'App password or SMTP key' ?>" autocomplete="off">
          <div class="form-hint">For Gmail: use an <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#cc0000">App Password</a>, not your regular password.</div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div class="form-group">
        <label class="form-label">From Address</label>
        <input name="mail_from_address" class="form-control" type="email" value="<?= h($settings['mail_from_address'] ?? '') ?>"
               placeholder="newsletter@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">From Name</label>
        <input name="mail_from_name" class="form-control" value="<?= h($settings['mail_from_name'] ?? '') ?>"
               placeholder="<?= h(get_site_setting('site_title', 'Your Site Name')) ?>">
      </div>
    </div>

    <div style="background:#f5f5f5;border-radius:8px;padding:12px 16px;margin-bottom:16px">
      <strong style="font-size:12px;color:#666">Quick Setup — Common Providers</strong>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
        <button type="button" class="btn sm light" onclick="fillSmtp('smtp.gmail.com','587','tls')">Gmail</button>
        <button type="button" class="btn sm light" onclick="fillSmtp('smtp-mail.outlook.com','587','tls')">Outlook</button>
        <button type="button" class="btn sm light" onclick="fillSmtp('smtp.zoho.com','587','tls')">Zoho</button>
        <button type="button" class="btn sm light" onclick="fillSmtp('smtp.sendgrid.net','587','tls')">SendGrid</button>
        <button type="button" class="btn sm light" onclick="fillSmtp('smtp.mailgun.org','587','tls')">Mailgun</button>
      </div>
    </div>
  </div>

  <div style="margin-bottom:32px">
    <button class="btn" type="submit">Save Email Settings</button>
    <a class="btn light" href="/admin" style="margin-left:10px">Cancel</a>
  </div>
</form>

<script>
function fillSmtp(host, port, enc) {
  document.querySelector('[name="mail_host"]').value = host;
  document.querySelector('[name="mail_port"]').value = port;
  document.querySelector('[name="mail_encryption"]').value = enc;
}
document.getElementById('mailDriverSelect')?.addEventListener('change', function() {
  document.getElementById('smtpFields').style.display = this.value === 'smtp' ? '' : 'none';
});
</script>

<!-- ─── THEME & APPEARANCE ───────────────────────────────────── -->
<div class="card" style="margin-bottom:24px" id="themeSection">

  <div style="font-weight:700;font-size:15px;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border)">
    Theme &amp; Appearance
    <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:10px">Changes apply live across the entire site</span>
  </div>

  <!-- Presets -->
  <div style="margin-bottom:28px">
    <div class="form-label" style="margin-bottom:12px">One-Click Presets</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ($presets as $key => $preset): ?>
        <button
          type="button"
          class="preset-btn"
          data-preset="<?= h($key) ?>"
          data-values="<?= h(json_encode($preset['values'])) ?>"
          style="padding:10px 16px;border:1px solid var(--border);border-radius:10px;background:var(--surface);cursor:pointer;font-size:13px;font-weight:600;transition:all .15s ease"
          onmouseover="this.style.background='var(--paper)';this.style.borderColor='#aaa'"
          onmouseout="this.style.background='var(--surface)';this.style.borderColor='var(--border)'"
        >
          <?= h($preset['label']) ?>
          <div style="font-weight:400;font-size:11px;color:var(--muted);margin-top:2px"><?= h($preset['description']) ?></div>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start">

    <!-- Left: Controls -->
    <form method="POST" action="/admin/settings" id="themeForm">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="_form" value="theme">

      <!-- Mode -->
      <div class="form-group" style="margin-bottom:22px">
        <label class="form-label">Colour Mode</label>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px">
          <?php
          // FIX: 'auto' renamed to 'system' throughout the theme engine.
          // Legacy DB rows with 'auto' are normalised by get_theme_css() at runtime.
          // The UI now shows 'system' as the stored value.
          $themeModes = [
            'light'  => ['label' => 'Light',               'icon' => '☀️',  'hint' => 'Always light — ignores OS preference'],
            'dark'   => ['label' => 'Dark',                'icon' => '🌙',  'hint' => 'Always dark — ignores OS preference'],
            'system' => ['label' => 'System (follows OS)', 'icon' => '🖥️', 'hint' => 'Follows the visitor\'s OS dark/light setting'],
          ];
          // Normalise legacy 'auto' value so the correct radio is checked
          $currentMode = $settings['theme_mode'] ?? 'light';
          if ($currentMode === 'auto') $currentMode = 'system';
          ?>
          <?php foreach ($themeModes as $val => $info): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:9px 16px;border:1px solid var(--border);border-radius:9px;font-size:14px;transition:all .15s" class="mode-btn" data-mode="<?= h($val) ?>">
              <input type="radio" name="theme_mode" value="<?= h($val) ?>"
                <?= $currentMode === $val ? 'checked' : '' ?>
                style="accent-color:var(--accent)">
              <span><?= $info['icon'] ?> <?= h($info['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div style="font-size:12px;color:var(--muted)" id="theme-mode-hint">
          <?php foreach ($themeModes as $val => $info): ?>
            <span class="theme-mode-desc" data-mode="<?= h($val) ?>" style="display:<?= $currentMode === $val ? 'inline' : 'none' ?>"><?= h($info['hint']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Colours -->
      <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:12px">Colours</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:22px">
        <?php
        $colourFields = [
          'theme_ink'          => ['Text',          '#121212'],
          'theme_paper'        => ['Background',    '#fdfdfd'],
          'theme_surface'      => ['Card surface',  '#ffffff'],
          'theme_border'       => ['Border',        '#e2e2e2'],
          'theme_muted'        => ['Muted text',    '#666666'],
          'theme_accent'       => ['Accent',        '#cc0000'],
          'theme_accent_dark'  => ['Accent hover',  '#aa0000'],
          'theme_accent_text'  => ['Accent text',   '#ffffff'],
        ];
        foreach ($colourFields as $key => [$label, $default]):
          $val = $settings[$key] ?? $default;
          // Normalize to hex for color picker (skip rgba values)
          $colorVal = (str_starts_with($val, '#') && strlen($val) <= 9) ? $val : $default;
        ?>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" style="font-size:11px"><?= h($label) ?></label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="color"
                   name="<?= h($key) ?>_picker"
                   value="<?= h($colorVal) ?>"
                   data-target="<?= h($key) ?>"
                   class="colour-picker"
                   style="width:38px;height:36px;border:1px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;flex-shrink:0">
            <input type="text"
                   name="<?= h($key) ?>"
                   value="<?= h($val) ?>"
                   id="<?= h($key) ?>"
                   class="form-control theme-input"
                   data-token="<?= h(str_replace('theme_', '--', $key)) ?>"
                   style="font-size:12px;padding:8px 10px"
                   placeholder="<?= h($default) ?>">
          </div>
        </div>
        <?php endforeach; ?>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" style="font-size:11px">Selection highlight</label>
          <input type="text"
                 name="theme_selection_bg"
                 value="<?= h($settings['theme_selection_bg'] ?? 'rgba(204,0,0,.12)') ?>"
                 id="theme_selection_bg"
                 class="form-control theme-input"
                 data-token="--selection-bg"
                 style="font-size:12px;padding:8px 10px">
        </div>
      </div>

      <!-- Dark mode colour overrides -->
      <!-- FIX (A-03): These fields supply DB values for the theme_*_dark tokens
           that get_theme_css() now reads for mode='dark' and mode='system'.
           Previously these had no UI — get_theme_css() used hardcoded fallbacks.
           The section is hidden when mode='light' since the values are unused. -->
      <div id="dark-tokens-section" style="display:<?= in_array($currentMode ?? ($settings['theme_mode'] ?? 'light'), ['dark','system','auto']) ? 'block' : 'none' ?>">
        <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:4px">
          Dark Mode Colours
          <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;text-transform:none;letter-spacing:0">
            Shown when visitors are in dark mode
          </span>
        </div>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
          Leave blank to use the defaults below. Only used when Colour Mode is Dark or System.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:22px">
          <?php
          $darkColourFields = [
            'theme_ink_dark'     => ['Dark text',        '#e0e0e0'],
            'theme_paper_dark'   => ['Dark background',  '#1a1a1a'],
            'theme_surface_dark' => ['Dark card surface', '#242424'],
            'theme_border_dark'  => ['Dark border',      '#3a3a3a'],
            'theme_muted_dark'   => ['Dark muted text',  '#999999'],
          ];
          foreach ($darkColourFields as $key => [$label, $default]):
            $val = $settings[$key] ?? $default;
            $colorVal = (str_starts_with($val, '#') && strlen($val) <= 9) ? $val : $default;
          ?>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label" style="font-size:11px"><?= h($label) ?></label>
            <div style="display:flex;gap:6px;align-items:center">
              <input type="color"
                     name="<?= h($key) ?>_picker"
                     value="<?= h($colorVal) ?>"
                     data-target="<?= h($key) ?>"
                     class="colour-picker"
                     style="width:38px;height:36px;border:1px solid var(--border);border-radius:7px;padding:2px;cursor:pointer;flex-shrink:0">
              <input type="text"
                     name="<?= h($key) ?>"
                     value="<?= h($val) ?>"
                     id="<?= h($key) ?>"
                     class="form-control"
                     style="font-size:12px;padding:8px 10px"
                     placeholder="<?= h($default) ?>">
            </div>
          </div>
          <?php endforeach; ?>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label" style="font-size:11px">Dark selection highlight</label>
            <input type="text"
                   name="theme_selection_bg_dark"
                   value="<?= h($settings['theme_selection_bg_dark'] ?? 'rgba(204,0,0,.22)') ?>"
                   id="theme_selection_bg_dark"
                   class="form-control"
                   style="font-size:12px;padding:8px 10px"
                   placeholder="rgba(204,0,0,.22)">
          </div>
        </div>
      </div>

      <!-- Typography -->
      <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:12px">Typography</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px">

        <div class="form-group">
          <label class="form-label" style="font-size:11px">Body / article font</label>
          <select name="theme_font_serif" id="theme_font_serif" class="form-control theme-input" data-token="--serif" style="font-size:13px">
            <?php
            $serifFonts = [
              'Georgia, "Times New Roman", Times, serif'           => 'Georgia (default)',
              '"Times New Roman", Times, serif'                     => 'Times New Roman',
              'Palatino, "Palatino Linotype", serif'                => 'Palatino',
              '"Lora", Georgia, serif'                              => 'Lora',
              '"Merriweather", Georgia, serif'                      => 'Merriweather',
              '"Playfair Display", Georgia, serif'                  => 'Playfair Display',
              '"Source Serif Pro", Georgia, serif'                  => 'Source Serif Pro',
              '"PT Serif", Georgia, serif'                          => 'PT Serif',
              '"Crimson Text", Georgia, serif'                      => 'Crimson Text',
              '"Noto Serif", Georgia, serif'                        => 'Noto Serif',
              '"EB Garamond", Garamond, serif'                      => 'EB Garamond',
              '"Bitter", Georgia, serif'                            => 'Bitter',
            ];
            $cur = $settings['theme_font_serif'] ?? 'Georgia, "Times New Roman", Times, serif';
            foreach ($serifFonts as $v => $lbl): ?>
              <option value="<?= h($v) ?>" <?= $cur === $v ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11px">UI / interface font</label>
          <select name="theme_font_ui" id="theme_font_ui" class="form-control theme-input" data-token="--ui" style="font-size:13px">
            <?php
            $uiFonts = [
              '"Libre Franklin", system-ui, -apple-system, Segoe UI, sans-serif' => 'Libre Franklin (default)',
              'system-ui, -apple-system, "Segoe UI", sans-serif'                 => 'System UI',
              '"Inter", system-ui, sans-serif'                                    => 'Inter',
              '"Source Sans Pro", system-ui, sans-serif'                          => 'Source Sans Pro',
              '"Open Sans", system-ui, sans-serif'                               => 'Open Sans',
              '"Roboto", system-ui, sans-serif'                                  => 'Roboto',
              '"Lato", system-ui, sans-serif'                                    => 'Lato',
              '"Nunito Sans", system-ui, sans-serif'                             => 'Nunito Sans',
              '"Work Sans", system-ui, sans-serif'                               => 'Work Sans',
              '"DM Sans", system-ui, sans-serif'                                 => 'DM Sans',
              '"IBM Plex Sans", system-ui, sans-serif'                           => 'IBM Plex Sans',
              '"Barlow", system-ui, sans-serif'                                  => 'Barlow',
            ];
            $cur = $settings['theme_font_ui'] ?? '"Libre Franklin", system-ui, -apple-system, Segoe UI, sans-serif';
            foreach ($uiFonts as $v => $lbl): ?>
              <option value="<?= h($v) ?>" <?= $cur === $v ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11px">Masthead / logo font</label>
          <select name="theme_font_mast" id="theme_font_mast" class="form-control theme-input" data-token="--mast" style="font-size:13px">
            <?php
            $mastFonts = [
              '"UnifrakturMaguntia", Georgia, serif'               => 'UnifrakturMaguntia (blackletter)',
              '"Playfair Display", Georgia, serif'                 => 'Playfair Display',
              'Georgia, "Times New Roman", serif'                  => 'Georgia',
              '"Old Standard TT", Georgia, serif'                  => 'Old Standard TT',
              '"Libre Baskerville", Georgia, serif'                => 'Libre Baskerville',
              '"Cormorant Garamond", Garamond, serif'              => 'Cormorant Garamond',
              '"DM Serif Display", Georgia, serif'                 => 'DM Serif Display',
              '"Cinzel", Georgia, serif'                           => 'Cinzel',
              '"Abril Fatface", Georgia, serif'                    => 'Abril Fatface',
            ];
            $cur = $settings['theme_font_mast'] ?? '"UnifrakturMaguntia", Georgia, serif';
            foreach ($mastFonts as $v => $lbl): ?>
              <option value="<?= h($v) ?>" <?= $cur === $v ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11px">Base font size</label>
          <select name="theme_font_base" id="theme_font_base" class="form-control theme-input" data-token="--font-base" style="font-size:13px">
            <?php
            $fontSizes = ['14px','15px','16px','17px','18px','19px','20px','21px','22px'];
            $curBase = $settings['theme_font_base'] ?? '18px';
            foreach ($fontSizes as $sz): ?>
              <option value="<?= h($sz) ?>" <?= $curBase === $sz ? 'selected' : '' ?>><?= h($sz) ?><?= $sz === '18px' ? ' (default)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11px">Article font size</label>
          <select name="theme_font_article" id="theme_font_article" class="form-control theme-input" data-token="--font-article" style="font-size:13px">
            <?php
            $artSizes = ['17px','18px','19px','20px','21px','22px','23px','24px'];
            $curArt = $settings['theme_font_article'] ?? '21px';
            foreach ($artSizes as $sz): ?>
              <option value="<?= h($sz) ?>" <?= $curArt === $sz ? 'selected' : '' ?>><?= h($sz) ?><?= $sz === '21px' ? ' (default)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11px">Line height</label>
          <input type="text" name="theme_line_height" id="theme_line_height"
                 value="<?= h($settings['theme_line_height'] ?? '1.7') ?>"
                 class="form-control theme-input" data-token="--line-height" style="font-size:13px">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11px">Border radius</label>
          <input type="text" name="theme_radius" id="theme_radius"
                 value="<?= h($settings['theme_radius'] ?? '16px') ?>"
                 class="form-control theme-input" data-token="--radius" style="font-size:13px">
        </div>
      </div>

      <!-- Layout -->
      <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:12px">Layout</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:22px">
        <div class="form-group">
          <label class="form-label" style="font-size:11px">Max page width</label>
          <input type="text" name="theme_max_width" id="theme_max_width"
                 value="<?= h($settings['theme_max_width'] ?? '1180px') ?>"
                 class="form-control theme-input" data-token="--max" style="font-size:13px">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px">Article column width</label>
          <input type="text" name="theme_content_max" id="theme_content_max"
                 value="<?= h($settings['theme_content_max'] ?? '1000px') ?>"
                 class="form-control theme-input" data-token="--content-max" style="font-size:13px">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px">Card padding</label>
          <input type="text" name="theme_card_pad" id="theme_card_pad"
                 value="<?= h($settings['theme_card_pad'] ?? '20px') ?>"
                 class="form-control theme-input" data-token="--card-pad" style="font-size:13px">
        </div>
      </div>

      <!-- Animation -->
      <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:12px">Animation</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:28px">
        <div class="form-group">
          <label class="form-label" style="font-size:11px">Transition speed</label>
          <input type="text" name="theme_speed" id="theme_speed"
                 value="<?= h($settings['theme_speed'] ?? '0.18s') ?>"
                 class="form-control theme-input" data-token="--speed" style="font-size:13px">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px">Slow transition</label>
          <input type="text" name="theme_speed_slow" id="theme_speed_slow"
                 value="<?= h($settings['theme_speed_slow'] ?? '0.35s') ?>"
                 class="form-control theme-input" data-token="--speed-slow" style="font-size:13px">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px">Easing curve</label>
          <input type="text" name="theme_ease" id="theme_ease"
                 value="<?= h($settings['theme_ease'] ?? 'cubic-bezier(.2,.8,.2,1)') ?>"
                 class="form-control theme-input" data-token="--ease" style="font-size:13px">
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" type="submit">Save Theme</button>
        <button type="button" class="btn light" id="resetThemeBtn">Reset to Defaults</button>
      </div>
    </form>

    <!-- Right: Live preview -->
    <div>
      <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:12px">Live Preview</div>
      <div id="themePreview" style="
        border:1px solid var(--border);
        border-radius:12px;
        overflow:hidden;
        font-size:13px;
        position:sticky;
        top:80px;
      ">
        <!-- Mini nav -->
        <div id="prev-nav" style="background:var(--surface);border-bottom:2px double var(--border);padding:10px 14px;display:flex;gap:8px;align-items:center">
          <span id="prev-mast" style="font-family:var(--mast);font-size:18px;color:var(--ink)"><?= h(get_site_setting('site_title', 'Site Name')) ?></span>
          <span style="flex:1"></span>
          <span id="prev-navitem" style="font-family:var(--ui);font-size:11px;color:var(--muted)">World</span>
          <span id="prev-navitem2" style="font-family:var(--ui);font-size:11px;color:var(--muted);margin-left:8px">Politics</span>
        </div>
        <!-- Article preview -->
        <div style="padding:16px;background:var(--paper)">
          <div id="prev-kicker" style="font-family:var(--ui);font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">World</div>
          <div id="prev-headline" style="font-size:17px;font-weight:800;line-height:1.2;color:var(--ink);margin-bottom:8px">Uganda Secures Major Infrastructure Deal</div>
          <div id="prev-excerpt" style="font-size:12px;color:var(--muted);font-family:var(--ui);line-height:1.5;margin-bottom:12px">Independent journalism from the north, delivered with clarity and precision.</div>
          <!-- Card -->
          <div id="prev-card" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:12px">
            <div style="font-size:12px;font-weight:600;color:var(--ink);margin-bottom:4px">Related Story</div>
            <div style="font-size:11px;color:var(--muted);font-family:var(--ui)">Parliament passes landmark amendment on press freedom.</div>
          </div>
          <!-- Blockquote -->
          <div id="prev-quote" style="border-left:4px solid var(--accent);padding:6px 12px;margin-bottom:12px;font-style:italic;font-size:13px;color:var(--muted)">
            "The truth shall set you free."
          </div>
          <!-- Button -->
          <div style="display:flex;gap:8px">
            <div id="prev-btn" style="background:var(--ink);color:var(--accent-text);border-radius:var(--radius);padding:7px 14px;font-family:var(--ui);font-size:11px;font-weight:700;display:inline-block">Read More</div>
            <div id="prev-btn-accent" style="background:var(--accent);color:var(--accent-text);border-radius:var(--radius);padding:7px 14px;font-family:var(--ui);font-size:11px;font-weight:700;display:inline-block">Subscribe</div>
          </div>
        </div>
        <!-- Mini footer -->
        <div id="prev-footer" style="background:var(--surface);border-top:2px double var(--border);padding:10px 14px">
          <div style="font-family:var(--ui);font-size:10px;color:var(--muted)">© <?= date('Y') ?> <?= h(get_site_setting('site_title', 'Site Name')) ?>. All rights reserved.</div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  'use strict';

  var root = document.documentElement;

  // ── Live preview: update CSS variable on any theme input change ──
  document.querySelectorAll('.theme-input').forEach(function (el) {
    el.addEventListener('input', function () {
      var token = el.dataset.token;
      if (token) root.style.setProperty(token, el.value);
    });
  });

  // ── Colour pickers sync with text inputs ─────────────────────────
  document.querySelectorAll('.colour-picker').forEach(function (picker) {
    var target = document.getElementById(picker.dataset.target);
    picker.addEventListener('input', function () {
      if (target) {
        target.value = picker.value;
        target.dispatchEvent(new Event('input'));
      }
    });
    if (target) {
      target.addEventListener('input', function () {
        if (target.value.match(/^#[0-9a-fA-F]{6}$/)) {
          picker.value = target.value;
        }
      });
    }
  });

  // ── Mode buttons highlight + dark section visibility ─────────────
  function applyModeUi(mode) {
    // Normalise legacy value
    if (mode === 'auto') mode = 'system';

    // Highlight selected mode button
    document.querySelectorAll('.mode-btn').forEach(function (b) {
      b.style.borderColor = 'var(--border)';
      b.style.background  = 'var(--surface)';
    });
    var activeBtn = document.querySelector('.mode-btn[data-mode="' + mode + '"]');
    if (activeBtn) {
      activeBtn.style.borderColor = 'var(--accent)';
      activeBtn.style.background  = 'var(--paper)';
    }

    // Show/hide hint text
    document.querySelectorAll('.theme-mode-desc').forEach(function (el) {
      el.style.display = el.dataset.mode === mode ? 'inline' : 'none';
    });

    // Show/hide dark token + dark logo sections
    var showDark = (mode === 'dark' || mode === 'system');
    var darkSection = document.getElementById('dark-tokens-section');
    var darkLogoSection = document.getElementById('dark-logo-section');
    if (darkSection)     darkSection.style.display     = showDark ? 'block' : 'none';
    if (darkLogoSection) darkLogoSection.style.display = showDark ? 'block' : 'none';
  }

  document.querySelectorAll('.mode-btn').forEach(function (btn) {
    var radio = btn.querySelector('input[type=radio]');
    if (radio) {
      radio.addEventListener('change', function () { applyModeUi(btn.dataset.mode); });
      if (radio.checked) applyModeUi(btn.dataset.mode);
    }
  });

  // ── Preset buttons ───────────────────────────────────────────────
  document.querySelectorAll('.preset-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var values = JSON.parse(btn.dataset.values || '{}');
      Object.keys(values).forEach(function (key) {
        var val = values[key];
        // Set form field
        var field = document.querySelector('[name="' + key + '"]');
        if (field) {
          field.value = val;
          field.dispatchEvent(new Event('input'));
        }
        // Sync colour picker if there is one
        var picker = document.querySelector('[data-target="' + key + '"]');
        if (picker && val.match(/^#[0-9a-fA-F]{6}$/)) picker.value = val;
        // Apply to :root live
        var token = '--' + key.replace('theme_', '').replace(/_/g, '-');
        root.style.setProperty(token, val);
      });
      // Handle mode radio
      if (values.theme_mode) {
        // FIX: normalise legacy 'auto' to 'system'
        var modeVal = values.theme_mode === 'auto' ? 'system' : values.theme_mode;
        var radio = document.querySelector('[name="theme_mode"][value="' + modeVal + '"]');
        if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
        applyModeUi(modeVal);
      }
    });
  });

  // ── Reset to defaults ────────────────────────────────────────────
  var defaults = {
    theme_ink:          '#121212',
    theme_paper:        '#fdfdfd',
    theme_surface:      '#ffffff',
    theme_border:       '#e2e2e2',
    theme_muted:        '#666666',
    theme_accent:       '#cc0000',
    theme_accent_dark:  '#aa0000',
    theme_accent_text:  '#ffffff',
    theme_selection_bg: 'rgba(204,0,0,.12)',
    theme_font_base:    '18px',
    theme_font_article: '21px',
    theme_line_height:  '1.7',
    theme_radius:       '16px',
    theme_max_width:    '1180px',
    theme_content_max:  '1000px',
    theme_card_pad:     '20px',
    theme_speed:        '0.18s',
    theme_speed_slow:   '0.35s',
    theme_ease:         'cubic-bezier(.2,.8,.2,1)',
    theme_mode:         'light',
  };

  var resetBtn = document.getElementById('resetThemeBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (!confirm('Reset all theme settings to defaults?')) return;
      Object.keys(defaults).forEach(function (key) {
        var val    = defaults[key];
        var field  = document.querySelector('[name="' + key + '"]');
        var picker = document.querySelector('[data-target="' + key + '"]');
        if (field)  { field.value = val; field.dispatchEvent(new Event('input')); }
        if (picker && val.match(/^#[0-9a-fA-F]{6}$/)) picker.value = val;
        var token = '--' + key.replace('theme_', '').replace(/_/g, '-');
        root.style.setProperty(token, val);
      });
      var radio = document.querySelector('[name="theme_mode"][value="light"]');
      if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
    });
  }

})();
</script>

</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
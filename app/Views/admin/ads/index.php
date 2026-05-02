<?php
declare(strict_types=1);
$slots = $slots ?? [];
$activeNav = 'ads';
ob_start();
?>

<div class="card" style="max-width:1100px">
  <header style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:24px">
    <div>
      <h1 style="margin:0 0 6px">Ad Placements</h1>
      <div class="muted">Full control over every ad slot. Set images, HTML, AdSense, device targeting, dimensions, and scheduling.</div>
    </div>
  </header>

  <!-- Placement map -->
  <div style="margin-bottom:24px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px">
    <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:#475569">Where ads appear on your site:</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;font-size:12px">
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #3b82f6"><strong>top-banner</strong> — Full-width above content</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #8b5cf6"><strong>sidebar</strong> — Homepage right sidebar</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #10b981"><strong>in-feed</strong> — Between article sections</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #f59e0b"><strong>in-article</strong> — Inside article body</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #ef4444"><strong>below-article</strong> — After article content</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #ec4899"><strong>article-sidebar</strong> — Article page sidebar</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #6366f1"><strong>footer</strong> — Footer banner area</div>
      <div style="padding:8px 12px;background:#fff;border-radius:8px;border-left:3px solid #14b8a6"><strong>homepage-spotlight</strong> — Homepage special slot</div>
    </div>
  </div>

  <?php if (!empty($flash_success)): ?>
    <div class="flash ok" style="margin-bottom:12px"><?= h($flash_success) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash_error)): ?>
    <div class="flash bad" style="margin-bottom:12px"><?= h($flash_error) ?></div>
  <?php endif; ?>

  <div style="display:grid;gap:16px">
    <?php foreach ($slots as $slot):
      $isActive = (bool)($slot['is_active'] ?? false);
      $slotDescriptions = [
        'top-banner'      => '728×90 or 970×90 — Full-width above header. Supports animated GIF, HTML5.',
        'sidebar'         => '300×250 or 300×600 — Sticky sidebar on homepage and articles.',
        'in-feed'         => '728×90 responsive — Between category sections (every 2nd).',
        'in-article'      => 'Responsive width — After article body, before author card.',
        'below-article'   => '728×90 or 970×250 — After author card, before related stories.',
        'article-sidebar' => '300×250 — Right sidebar on article pages.',
        'footer'          => '728×90 — Footer banner area.',
        'homepage-spotlight' => 'Custom size — Homepage featured ad position.',
      ];
      $desc = $slotDescriptions[$slot['slot_name']] ?? '';
      $device = $slot['device_target'] ?? 'all';
      $deviceLabel = match($device) { 'mobile' => 'Mobile Only', 'desktop' => 'Desktop Only', default => 'All Devices' };
      $deviceColor = match($device) { 'mobile' => '#8b5cf6', 'desktop' => '#3b82f6', default => '#6b7280' };
    ?>
    <div style="border:1px solid <?= $isActive ? '#22c55e40' : '#e2e2e2' ?>;border-radius:14px;padding:20px;background:<?= $isActive ? '#f0fdf4' : '#fff' ?>;border-left:4px solid <?= $isActive ? '#22c55e' : '#ccc' ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div>
          <div style="font-weight:800;font-size:16px;color:#121212"><?= h($slot['label']) ?></div>
          <div style="font-size:12px;color:#888;margin-top:2px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <code style="background:#f0f2f5;padding:2px 8px;border-radius:4px;font-size:11px"><?= h($slot['slot_name']) ?></code>
            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;background:<?= h($deviceColor) ?>18;color:<?= h($deviceColor) ?>"><?= h($deviceLabel) ?></span>
            <?= h($desc) ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:6px;background:<?= $isActive ? '#dcfce7' : '#f3f4f6' ?>;color:<?= $isActive ? '#166534' : '#888' ?>">
            <?= $isActive ? 'LIVE' : 'OFF' ?>
          </span>
          <form method="POST" action="/admin/ads/<?= h($slot['id']) ?>/toggle" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <button type="submit" class="btn light small"><?= $isActive ? 'Disable' : 'Enable' ?></button>
          </form>
        </div>
      </div>

      <!-- Live preview for image ads -->
      <?php if (($slot['ad_type'] ?? '') === 'image' && (!empty($slot['content']) || !empty($slot['content_tablet']) || !empty($slot['content_mobile']))): ?>
        <div style="margin-bottom:12px;padding:12px;background:#fafafa;border:1px dashed #e2e2e2;border-radius:10px">
          <div style="font-size:10px;color:#999;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;text-align:center">Preview</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;text-align:center">
            <?php if (!empty($slot['content'])): ?>
            <div>
              <div style="font-size:10px;color:#666;margin-bottom:4px;font-weight:600">Desktop</div>
              <img src="<?= h($slot['content']) ?>" alt="Desktop ad preview"
                   style="max-width:100%;max-height:120px;border-radius:6px;display:inline-block"
                   onerror="this.style.display='none'" />
            </div>
            <?php endif; ?>
            <?php if (!empty($slot['content_tablet'])): ?>
            <div>
              <div style="font-size:10px;color:#666;margin-bottom:4px;font-weight:600">Tablet</div>
              <img src="<?= h($slot['content_tablet']) ?>" alt="Tablet ad preview"
                   style="max-width:100%;max-height:120px;border-radius:6px;display:inline-block"
                   onerror="this.style.display='none'" />
            </div>
            <?php endif; ?>
            <?php if (!empty($slot['content_mobile'])): ?>
            <div>
              <div style="font-size:10px;color:#666;margin-bottom:4px;font-weight:600">Mobile</div>
              <img src="<?= h($slot['content_mobile']) ?>" alt="Mobile ad preview"
                   style="max-width:100%;max-height:120px;border-radius:6px;display:inline-block"
                   onerror="this.style.display='none'" />
            </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <details style="margin-top:8px">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#0ea5e9;user-select:none">Edit slot settings</summary>

        <form method="POST" action="/admin/ads/<?= h($slot['id']) ?>" enctype="multipart/form-data" style="margin-top:14px">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Ad Type</label>
              <select name="ad_type" style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
                <option value="image" <?= ($slot['ad_type']??'') === 'image' ? 'selected' : '' ?>>Image (JPG/PNG/GIF)</option>
                <option value="html" <?= ($slot['ad_type']??'') === 'html' ? 'selected' : '' ?>>Custom HTML</option>
                <option value="adsense" <?= ($slot['ad_type']??'') === 'adsense' ? 'selected' : '' ?>>Google AdSense</option>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Device Targeting</label>
              <select name="device_target" style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
                <option value="all" <?= $device === 'all' ? 'selected' : '' ?>>All Devices</option>
                <option value="desktop" <?= $device === 'desktop' ? 'selected' : '' ?>>Desktop Only</option>
                <option value="mobile" <?= $device === 'mobile' ? 'selected' : '' ?>>Mobile Only</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Desktop Link URL</label>
              <input type="url" name="link_url" value="<?= h($slot['link_url'] ?? '') ?>"
                     placeholder="https://advertiser.com/landing"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Tablet Link URL</label>
              <input type="url" name="link_url_tablet" value="<?= h($slot['link_url_tablet'] ?? '') ?>"
                     placeholder="Same as desktop if empty"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Mobile Link URL</label>
              <input type="url" name="link_url_mobile" value="<?= h($slot['link_url_mobile'] ?? '') ?>"
                     placeholder="Same as desktop if empty"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Max Width</label>
              <input name="max_width" value="<?= h($slot['max_width'] ?? '') ?>"
                     placeholder="e.g. 728px or 100%"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Max Height</label>
              <input name="max_height" value="<?= h($slot['max_height'] ?? '') ?>"
                     placeholder="e.g. 90px or 250px"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Alt Text (SEO &amp; accessibility)</label>
              <input name="alt_text" value="<?= h($slot['alt_text'] ?? '') ?>"
                     placeholder="Describe the ad image"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px">Upload Images</label>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
              <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#475569">Desktop Image</label>
                <input type="file" name="ad_image" accept="image/*,.gif" style="font-size:12px;width:100%">
                <small style="color:#94a3b8;font-size:11px">728x90 or 970x250</small>
                <?php if (!empty($slot['content']) && ($slot['ad_type'] ?? '') === 'image'): ?>
                  <div style="margin-top:4px;font-size:11px;color:#666">Current: <code><?= h($slot['content']) ?></code></div>
                <?php endif; ?>
              </div>
              <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#475569">Tablet Image</label>
                <input type="file" name="ad_image_tablet" accept="image/*,.gif" style="font-size:12px;width:100%">
                <small style="color:#94a3b8;font-size:11px">468x60 or 320x100</small>
                <?php if (!empty($slot['content_tablet'])): ?>
                  <div style="margin-top:4px;font-size:11px;color:#666">Current: <code><?= h($slot['content_tablet']) ?></code></div>
                <?php endif; ?>
              </div>
              <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#475569">Mobile Image</label>
                <input type="file" name="ad_image_mobile" accept="image/*,.gif" style="font-size:12px;width:100%">
                <small style="color:#94a3b8;font-size:11px">320x50 or 300x250</small>
                <?php if (!empty($slot['content_mobile'])): ?>
                  <div style="margin-top:4px;font-size:11px;color:#666">Current: <code><?= h($slot['content_mobile']) ?></code></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Content (HTML/AdSense code or image path)</label>
            <textarea name="content" rows="3"
                      placeholder="Paste AdSense snippet, custom HTML, or image path..."
                      style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:13px;font-family:monospace;resize:vertical"><?= h($slot['content'] ?? '') ?></textarea>
          </div>

          <div style="margin-bottom:14px">
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Custom CSS (applied to this slot wrapper)</label>
            <textarea name="custom_css" rows="2"
                      placeholder="e.g. border:2px solid #000; padding:10px; background:#f9f9f9;"
                      style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:13px;font-family:monospace;resize:vertical"><?= h($slot['custom_css'] ?? '') ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Start Date (optional)</label>
              <input type="datetime-local" name="start_date"
                     value="<?= !empty($slot['start_date']) ? date('Y-m-d\TH:i', strtotime($slot['start_date'])) : '' ?>"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">End Date (auto-expire)</label>
              <input type="datetime-local" name="end_date"
                     value="<?= !empty($slot['end_date']) ? date('Y-m-d\TH:i', strtotime($slot['end_date'])) : '' ?>"
                     style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600">
              <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> style="accent-color:#22c55e"> Active
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600">
              <input type="checkbox" name="nofollow" value="1" <?= ($slot['nofollow'] ?? true) ? 'checked' : '' ?>> Nofollow link
            </label>
            <button type="submit" class="btn" style="padding:8px 20px;font-size:13px">Save Changes</button>
          </div>

          <?php if (!empty($slot['impressions']) || !empty($slot['clicks'])): ?>
          <div style="margin-top:14px;padding:10px 14px;background:#f8fafc;border-radius:8px;display:flex;gap:20px;font-size:12px">
            <div>
              <span style="font-weight:700;color:#334155"><?= number_format((int)$slot['impressions']) ?></span>
              <span style="color:#94a3b8">impressions</span>
            </div>
            <div>
              <span style="font-weight:700;color:#334155"><?= number_format((int)$slot['clicks']) ?></span>
              <span style="color:#94a3b8">clicks</span>
            </div>
            <?php if ((int)$slot['impressions'] > 0): ?>
            <div>
              <span style="font-weight:700;color:#334155"><?= number_format(((int)$slot['clicks'] / (int)$slot['impressions']) * 100, 2) ?>%</span>
              <span style="color:#94a3b8">CTR</span>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </form>
      </details>
    </div>
    <?php endforeach; ?>

    <?php if (empty($slots)): ?>
      <div class="card" style="text-align:center;padding:40px">
        <h3>No ad slots found</h3>
        <p class="muted">Run migration 0020_ad_slots_and_scores.sql to create the default slots.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
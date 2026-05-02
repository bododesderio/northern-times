<?php
$pageTitle = ($mode === 'edit' ? 'Edit' : 'New') . ' Crawl Source';
$activeNav = 'crawler';
$isEdit    = $mode === 'edit';
$s         = $source ?? [];
$csrf      = $csrf ?? '';

// Decode category map JSON → textarea format (keyword=uuid per line)
$catMapRaw = '';
if (!empty($s['category_map'])) {
    $map = is_string($s['category_map']) ? json_decode($s['category_map'], true) : $s['category_map'];
    if (is_array($map)) {
        foreach ($map as $kw => $catId) {
            $catMapRaw .= $kw . '=' . $catId . "\n";
        }
    }
}

$slot = null;
ob_start();
?>

<form method="POST" action="<?= $isEdit ? '/admin/crawler/' . h($s['id']) . '/update' : '/admin/crawler/store' ?>" id="sourceForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <div style="max-width:800px;display:flex;flex-direction:column;gap:20px">

    <!-- Basics -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Source Details</h3>
      <div style="display:grid;gap:14px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Source Name *</label>
            <input name="name" value="<?= h($s['name'] ?? '') ?>" required placeholder="e.g. Daily Monitor"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:15px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Feed Type</label>
            <select name="source_type" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
              <?php foreach (['rss' => 'RSS 2.0', 'atom' => 'Atom', 'html' => 'HTML (Experimental)'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($s['source_type'] ?? 'rss') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Feed URL *</label>
          <div style="display:flex;gap:8px">
            <input name="feed_url" id="feedUrl" value="<?= h($s['feed_url'] ?? '') ?>" required placeholder="https://example.com/rss/feed.xml"
                   style="flex:1;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            <button type="button" onclick="testFeed()" class="btn light" style="padding:12px 16px;font-size:13px;white-space:nowrap">🔍 Test Feed</button>
          </div>
          <div id="feedTestResult" style="margin-top:8px;font-size:13px;display:none"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Website URL</label>
            <input name="website_url" value="<?= h($s['website_url'] ?? '') ?>" placeholder="https://example.com"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Logo URL</label>
            <input name="logo_url" value="<?= h($s['logo_url'] ?? '') ?>" placeholder="https://example.com/logo.png"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
        </div>
      </div>
    </div>

    <!-- Crawl Settings -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Crawl Settings</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Interval (minutes)</label>
          <input type="number" name="crawl_interval" value="<?= (int)($s['crawl_interval'] ?? 30) ?>" min="5" max="1440"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Max Articles/Crawl</label>
          <input type="number" name="max_articles" value="<?= (int)($s['max_articles'] ?? 20) ?>" min="1" max="100"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
        </div>
        <div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-top:28px">
            <input type="checkbox" name="is_active" value="1" <?= ($s['is_active'] ?? true) ? 'checked' : '' ?>>
            <span style="font-weight:600;font-size:14px">Active</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Category Mapping -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Category Mapping</h3>
      <div style="display:grid;gap:14px">
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Default Category</label>
          <select name="default_category_id" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            <option value="">— Select —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= h($cat['id']) ?>" <?= ($s['default_category_id'] ?? '') === $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Used when no category can be auto-detected from the feed.</div>
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Custom Category Map</label>
          <textarea name="category_map" rows="5" placeholder="keyword=category_uuid&#10;politics=abc-123&#10;sport=def-456&#10;business=ghi-789"
                    style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:13px;font-family:monospace;resize:vertical"><?= h(trim($catMapRaw)) ?></textarea>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">
            One mapping per line: <code>keyword=category_id</code>. The crawler first matches RSS tags, then URL segments, then title keywords.
            <br>Available categories:
            <?php foreach ($categories as $i => $cat): ?>
              <?php if ($i > 0) echo ', '; ?>
              <code><?= h($cat['name']) ?>=<?= h($cat['id']) ?></code>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Filtering -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Content Filtering</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Include Keywords</label>
          <input name="keyword_include" value="<?= h($s['keyword_include'] ?? '') ?>" placeholder="uganda, northern, gulu, lira"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Comma-separated. Article must contain at least one. Leave empty for all.</div>
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Exclude Keywords</label>
          <input name="keyword_exclude" value="<?= h($s['keyword_exclude'] ?? '') ?>" placeholder="sponsored, advertisement, casino"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Comma-separated. Article is skipped if it contains any of these.</div>
        </div>
      </div>
      <div style="margin-top:14px">
        <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Strip CSS Selectors</label>
        <input name="strip_selectors" value="<?= h($s['strip_selectors'] ?? '') ?>" placeholder=".ads, .sidebar, #related-articles"
               style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
        <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Comma-separated class/ID selectors to remove from crawled content.</div>
      </div>
    </div>

    <!-- Attribution -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Attribution</h3>
      <div style="display:grid;grid-template-columns:1fr auto;gap:14px;align-items:start">
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Attribution Text</label>
          <input name="attribution_text" value="<?= h($s['attribution_text'] ?? '') ?>" placeholder="Source: Daily Monitor"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Shown as a small ⓘ tooltip at the bottom of crawled articles.</div>
        </div>
        <div style="padding-top:28px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="nofollow" value="1" <?= ($s['nofollow'] ?? true) ? 'checked' : '' ?>>
            <span style="font-weight:600;font-size:14px">Add nofollow</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:8px">
            <input type="checkbox" name="download_images" value="1" <?= ($s['download_images'] ?? true) ? 'checked' : '' ?>>
            <span style="font-weight:600;font-size:14px">Download images locally</span>
          </label>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Store featured &amp; inline images on your server instead of hotlinking. Better quality, no broken images.</div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:8px">
            <input type="checkbox" name="require_review" value="1" <?= ($s['require_review'] ?? false) ? 'checked' : '' ?>>
            <span style="font-weight:600;font-size:14px">Require editorial review</span>
          </label>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Articles from this source always go to the review queue, regardless of global auto-publish setting.</div>
        </div>
      </div>
    </div>

    <!-- Full-Page Scraping -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:text-bottom;margin-right:4px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Full-Page Scraping
      </h3>
      <div style="display:grid;grid-template-columns:1fr;gap:14px">
        <div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="full_page_scrape" value="1" <?= ($s['full_page_scrape'] ?? false) ? 'checked' : '' ?>>
            <span style="font-weight:600;font-size:14px">Enable full-page scraping</span>
          </label>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">
            Fetch the actual article page for full content, full-size hero images, embedded videos, and inline images. Slower but much higher quality than RSS-only. Categories are auto-matched with 90% confidence.
          </div>
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Content CSS Selector <span style="font-weight:400;color:var(--muted,#888)">(optional)</span></label>
          <input name="content_selector" value="<?= h($s['content_selector'] ?? '') ?>" placeholder="e.g. article .entry-content"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px;font-family:monospace">
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">
            Custom CSS selector for the article body. Leave blank for auto-detection. Use browser inspector to find the selector.
          </div>
        </div>
      </div>
        </div>
      </div>
    </div>

    <?php if ($isEdit): ?>
    <!-- Stats (edit mode only) -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Statistics</h3>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;font-size:14px">
        <div><span style="color:var(--muted,#888)">Total Crawled:</span> <strong><?= (int)$s['total_crawled'] ?></strong></div>
        <div><span style="color:var(--muted,#888)">Published:</span> <strong><?= (int)$s['total_published'] ?></strong></div>
        <div><span style="color:var(--muted,#888)">Errors:</span> <strong><?= (int)$s['total_errors'] ?></strong></div>
        <div><span style="color:var(--muted,#888)">Last Success:</span> <strong><?= $s['last_success_at'] ? date('M j, g:i A', strtotime($s['last_success_at'])) : 'Never' ?></strong></div>
      </div>
      <?php if ($s['last_error']): ?>
        <div style="margin-top:12px;padding:12px;background:#fff5f5;border-radius:10px;font-size:13px;color:#c62828">
          <strong>Last Error:</strong> <?= h($s['last_error']) ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Submit -->
    <div style="display:flex;gap:12px">
      <button type="submit" class="btn" style="padding:14px 28px;font-size:15px"><?= $isEdit ? 'Update Source' : 'Create Source' ?></button>
      <a href="/admin/crawler" class="btn light" style="padding:14px 28px;font-size:15px">Cancel</a>
    </div>
  </div>
</form>

<script>
function testFeed() {
  var url = document.getElementById('feedUrl').value.trim();
  var result = document.getElementById('feedTestResult');
  if (!url) { result.style.display = 'block'; result.innerHTML = '<span style="color:#dc3545">Enter a feed URL first.</span>'; return; }

  result.style.display = 'block';
  result.innerHTML = '<span style="color:#666">⏳ Testing feed...</span>';

  fetch('/admin/crawler/test-feed?url=' + encodeURIComponent(url), {
    headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      result.innerHTML = '<span style="color:#28a745">✅ Feed is valid! Found <strong>' + data.items + '</strong> items. Type: ' + data.type + '</span>';
    } else {
      result.innerHTML = '<span style="color:#dc3545">❌ ' + (data.error || 'Failed to parse feed') + '</span>';
    }
  })
  .catch(function() {
    result.innerHTML = '<span style="color:#dc3545">❌ Network error — could not reach feed.</span>';
  });
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
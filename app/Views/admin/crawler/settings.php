<?php
$pageTitle = 'Crawler Settings';
$activeNav = 'crawler-settings';
$slot = null;
ob_start();
$st = $settings ?? [];
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash_error) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="/admin/crawler" style="font-size:14px;color:var(--muted,#888);text-decoration:none">← Back to Sources</a>
</div>

<form method="POST" action="/admin/crawler/settings/save" style="max-width:600px">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Master Toggle -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <h3 style="margin:0;font-size:16px;font-weight:700">Crawler Engine</h3>
          <p style="margin:4px 0 0;font-size:13px;color:var(--muted,#888)">Master on/off switch for automatic crawling.</p>
        </div>
        <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer">
          <input type="checkbox" name="crawler_enabled" value="true" <?= ($st['crawler_enabled'] ?? 'false') === 'true' ? 'checked' : '' ?>
                 style="opacity:0;width:0;height:0" onchange="updateToggle(this)">
          <span id="toggleTrack" style="position:absolute;inset:0;background:<?= ($st['crawler_enabled'] ?? 'false') === 'true' ? '#28a745' : '#ccc' ?>;border-radius:28px;transition:background .2s"></span>
          <span id="toggleDot" style="position:absolute;top:2px;<?= ($st['crawler_enabled'] ?? 'false') === 'true' ? 'left:26px' : 'left:2px' ?>;width:24px;height:24px;background:#fff;border-radius:50%;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)"></span>
        </label>
      </div>
    </div>

    <!-- Settings Grid -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Global Settings</h3>
      <div style="display:grid;gap:14px">
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Default Crawl Interval (minutes)</label>
          <input type="number" name="crawler_interval" value="<?= (int)($st['crawler_interval'] ?? 30) ?>" min="5" max="1440"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">How often the cron checks for sources to crawl (each source has its own interval too).</div>
        </div>
        <div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="crawler_auto_publish" value="true" <?= ($st['crawler_auto_publish'] ?? 'true') === 'true' ? 'checked' : '' ?>>
            <span style="font-weight:600;font-size:14px">Auto-Publish Crawled Articles</span>
          </label>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px;padding-left:24px">If unchecked, crawled articles are saved as drafts for manual review.</div>
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Max Article Age (hours)</label>
          <input type="number" name="crawler_max_age_hours" value="<?= (int)($st['crawler_max_age_hours'] ?? 72) ?>" min="1" max="720"
                 style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Skip feed items older than this. Set to 72 to only import articles from the last 3 days.</div>
        </div>
        <div>
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Default Author</label>
          <select name="crawler_default_author" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            <option value="">— Auto (first admin) —</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= h($u['id']) ?>" <?= ($st['crawler_default_author'] ?? '') === $u['id'] ? 'selected' : '' ?>><?= h($u['username'] ?? 'User') ?></option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">The author_id assigned to crawled articles (display name comes from the source).</div>
        </div>
      </div>
    </div>

    <!-- Cron Setup Instructions -->
    <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
      <h3 style="margin:0 0 12px;font-size:16px;font-weight:700">Cron Setup</h3>
      <p style="margin:0 0 12px;font-size:14px;color:var(--muted,#666)">Add this cron job to run the crawler automatically:</p>
      <div style="background:#1a1a1a;color:#e0e0e0;padding:14px;border-radius:10px;font-family:monospace;font-size:13px;overflow-x:auto">
        */5 * * * * cd /var/www/html && php cron/crawl.php >> /var/log/crawler.log 2>&1
      </div>
      <p style="margin:12px 0 0;font-size:12px;color:var(--muted,#888)">The cron runs every 5 minutes but only crawls sources whose individual interval has elapsed.</p>
    </div>

    <button type="submit" class="btn" style="padding:14px 28px;font-size:15px;align-self:start">Save Settings</button>
  </div>
</form>

<script>
function updateToggle(cb) {
  document.getElementById('toggleTrack').style.background = cb.checked ? '#28a745' : '#ccc';
  document.getElementById('toggleDot').style.left = cb.checked ? '26px' : '2px';
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
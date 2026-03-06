<?php
$pageTitle = 'Social Monitor';
$activeNav = 'crawler-social';
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
  <a href="/admin/crawler" style="font-size:14px;color:var(--muted,#888);text-decoration:none">← Back to Crawler</a>
</div>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px">
  <div style="background:#eff6ff;padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:24px;font-weight:800;color:#2563eb"><?= (int)($stats['total_7d'] ?? 0) ?></div>
    <div style="font-size:12px;color:#888">Mentions (7d)</div>
  </div>
  <div style="background:#f0fdf4;padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:24px;font-weight:800;color:#16a34a"><?= (int)($stats['positive_7d'] ?? 0) ?></div>
    <div style="font-size:12px;color:#888">Positive</div>
  </div>
  <div style="background:#fef2f2;padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:24px;font-weight:800;color:#dc2626"><?= (int)($stats['negative_7d'] ?? 0) ?></div>
    <div style="font-size:12px;color:#888">Negative</div>
  </div>
  <div style="background:#fff7ed;padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:24px;font-weight:800;color:#ea580c"><?= (int)($stats['unread'] ?? 0) ?></div>
    <div style="font-size:12px;color:#888">Unread</div>
  </div>
  <div style="background:#f5f5f5;padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:24px;font-weight:800"><?= (int)($stats['today'] ?? 0) ?></div>
    <div style="font-size:12px;color:#888">Today</div>
  </div>
</div>

<!-- Action Bar -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:13px;font-weight:600;color:<?= ($st['social_monitor_enabled'] ?? 'false') === 'true' ? '#28a745' : '#dc3545' ?>">
      ● Monitor <?= ($st['social_monitor_enabled'] ?? 'false') === 'true' ? 'ON' : 'OFF' ?>
    </span>
    <?php if ($st['social_last_scan'] ?? ''): ?>
      <span style="font-size:12px;color:#888">Last scan: <?= date('M j, g:i A', strtotime($st['social_last_scan'])) ?></span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px">
    <form method="POST" action="/admin/crawler/social/scan" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <button type="submit" class="btn sm">🔍 Scan Now</button>
    </form>
    <form method="POST" action="/admin/crawler/social/read-all" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <button type="submit" class="btn sm light">✓ Mark All Read</button>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">

  <!-- Mentions Feed -->
  <div>
    <!-- Filters -->
    <div style="display:flex;gap:8px;margin-bottom:14px">
      <a href="/admin/crawler/social" style="padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;<?= !$filterPlatform && !$filterSentiment ? 'background:var(--ink,#111);color:#fff' : 'background:#f0f0f0;color:#555' ?>">All</a>
      <?php foreach (['positive' => '😊', 'neutral' => '😐', 'negative' => '😡'] as $s => $emoji): ?>
        <a href="/admin/crawler/social?sentiment=<?= $s ?>" style="padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;<?= $filterSentiment === $s ? 'background:var(--ink,#111);color:#fff' : 'background:#f0f0f0;color:#555' ?>"><?= $emoji ?> <?= ucfirst($s) ?></a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:6px;margin-bottom:14px">
      <?php foreach (['google_news' => '📰', 'reddit' => '🔴', 'twitter' => '🐦', 'facebook' => '📘', 'instagram' => '📷', 'linkedin' => '💼', 'mastodon' => '🐘', 'bluesky' => '🦋', 'web' => '🌐'] as $p => $icon): ?>
        <a href="/admin/crawler/social?platform=<?= $p ?>" style="padding:5px 10px;border-radius:6px;font-size:12px;text-decoration:none;<?= $filterPlatform === $p ? 'background:var(--ink,#111);color:#fff' : 'background:#f5f5f5;color:#666' ?>"><?= $icon ?> <?= h(str_replace('_', ' ', ucfirst($p))) ?></a>
      <?php endforeach; ?>
    </div>

    <div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
      <?php if (empty($mentions)): ?>
        <div style="padding:40px;text-align:center;color:#888">
          No mentions found. <?php if (($st['social_monitor_enabled'] ?? 'false') !== 'true'): ?>Enable the monitor and run a scan.<?php endif; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($mentions as $m): ?>
        <div style="padding:14px 18px;border-bottom:1px solid #f0f0f0;<?= !$m['is_read'] ? 'background:#fafbff' : '' ?>">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span style="font-size:14px"><?= match($m['platform']) { 'google_news' => '📰', 'reddit' => '🔴', 'twitter' => '🐦', 'facebook' => '📘', 'instagram' => '📷', 'linkedin' => '💼', 'mastodon' => '🐘', 'bluesky' => '🦋', default => '🌐' } ?></span>
            <span style="font-size:12px;font-weight:600;color:#555"><?= h(str_replace('_', ' ', ucfirst($m['platform']))) ?></span>
            <?php if ($m['author_name']): ?>
              <span style="font-size:12px;color:#888">by <?= h($m['author_name']) ?></span>
            <?php endif; ?>
            <span style="margin-left:auto;font-size:11px;color:#aaa"><?= date('M j, g:i A', strtotime($m['found_at'])) ?></span>
          </div>
          <div style="font-size:14px;line-height:1.5;margin-bottom:6px"><?= h(mb_substr($m['content'], 0, 300)) ?></div>
          <div style="display:flex;align-items:center;gap:8px;font-size:12px">
            <?php $sc = match($m['sentiment']) { 'positive' => ['😊','#d4edda','#155724'], 'negative' => ['😡','#ffebee','#c62828'], default => ['😐','#f5f5f5','#666'] }; ?>
            <span style="padding:2px 8px;border-radius:4px;background:<?= $sc[1] ?>;color:<?= $sc[2] ?>;font-weight:600"><?= $sc[0] ?> <?= ucfirst($m['sentiment']) ?></span>
            <?php if ($m['keyword_matched']): ?>
              <span style="color:#888">matched: <?= h($m['keyword_matched']) ?></span>
            <?php endif; ?>
            <?php if ($m['is_competitor']): ?>
              <span style="padding:2px 6px;background:#e3f2fd;color:#1565c0;border-radius:4px;font-weight:600">Competitor</span>
            <?php endif; ?>
            <a href="<?= h($m['mention_url']) ?>" target="_blank" rel="noopener" style="margin-left:auto;color:var(--accent,#cc0000)">View →</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Sidebar: Keywords + Settings -->
  <div>
    <!-- Tracked Keywords -->
    <div style="background:var(--surface,#fff);padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);margin-bottom:16px">
      <h4 style="margin:0 0 12px;font-size:14px;font-weight:700">Tracked Keywords</h4>
      <?php foreach ($keywords as $kw): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f5f5f5;font-size:13px">
          <span style="flex:1;<?= !$kw['is_active'] ? 'text-decoration:line-through;color:#ccc' : '' ?>"><?= h($kw['keyword']) ?></span>
          <?php if ($kw['is_competitor']): ?>
            <span style="font-size:10px;padding:1px 5px;background:#e3f2fd;color:#1565c0;border-radius:3px">comp</span>
          <?php endif; ?>
          <form method="POST" action="/admin/crawler/social/keyword/<?= (int)$kw['id'] ?>/toggle" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <button type="submit" style="border:none;background:none;cursor:pointer;font-size:12px;padding:2px" title="Toggle"><?= $kw['is_active'] ? '⏸' : '▶' ?></button>
          </form>
          <form method="POST" action="/admin/crawler/social/keyword/<?= (int)$kw['id'] ?>/delete" style="display:inline" data-confirm="Remove this keyword?" data-confirm-title="Remove Keyword" data-confirm-level="warn" data-confirm-ok="Remove">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <button type="submit" style="border:none;background:none;cursor:pointer;font-size:12px;padding:2px;color:#c62828" title="Delete">×</button>
          </form>
        </div>
      <?php endforeach; ?>
      <form method="POST" action="/admin/crawler/social/keyword" style="margin-top:10px;display:flex;gap:6px">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input name="keyword" placeholder="Add keyword..." required style="flex:1;padding:8px 10px;border:1px solid #e2e2e2;border-radius:8px;font-size:13px">
        <label style="display:flex;align-items:center;gap:4px;font-size:11px;white-space:nowrap"><input type="checkbox" name="is_competitor" value="1"> Comp</label>
        <button type="submit" class="btn xs">+</button>
      </form>
    </div>

    <!-- Platform Stats -->
    <div style="background:var(--surface,#fff);padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2);margin-bottom:16px">
      <h4 style="margin:0 0 12px;font-size:14px;font-weight:700">By Platform (7d)</h4>
      <?php foreach ($platformStats as $ps): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:13px">
          <span style="flex:1"><?= h(str_replace('_', ' ', ucfirst($ps['platform']))) ?></span>
          <span style="font-weight:700"><?= (int)$ps['count'] ?></span>
          <?php if ((int)$ps['negative_count'] > 0): ?>
            <span style="font-size:11px;color:#dc2626">(<?= (int)$ps['negative_count'] ?> neg)</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($platformStats)): ?>
        <div style="color:#ccc;font-size:13px">No data yet.</div>
      <?php endif; ?>
    </div>

    <!-- Settings -->
    <div style="background:var(--surface,#fff);padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2)">
      <h4 style="margin:0 0 12px;font-size:14px;font-weight:700">Settings</h4>
      <form method="POST" action="/admin/crawler/social/settings" style="display:grid;gap:10px">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="social_monitor_enabled" value="1" <?= ($st['social_monitor_enabled'] ?? 'false') === 'true' ? 'checked' : '' ?>>
          <span style="font-weight:600">Enable Monitor</span>
        </label>
        <div>
          <label style="font-size:12px;font-weight:600">Scan interval (min)</label>
          <input type="number" name="social_monitor_interval" value="<?= (int)($st['social_monitor_interval'] ?? 60) ?>" min="15" max="1440" style="width:100%;padding:8px;border:1px solid #e2e2e2;border-radius:8px;font-size:13px">
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="social_alert_negative" value="1" <?= ($st['social_alert_negative'] ?? 'true') === 'true' ? 'checked' : '' ?>>
          <span style="font-weight:600">Email on negative spike</span>
        </label>
        <div>
          <label style="font-size:12px;font-weight:600">Alert email</label>
          <input name="social_alert_email" value="<?= h($st['social_alert_email'] ?? '') ?>" placeholder="<?= h(get_site_setting('contact_email', 'editor@' . parse_url(get_site_setting('site_url', $_ENV['APP_URL'] ?? ''), PHP_URL_HOST))) ?>" style="width:100%;padding:8px;border:1px solid #e2e2e2;border-radius:8px;font-size:13px">
        </div>
        <button type="submit" class="btn xs" style="justify-self:start">Save</button>
      </form>
    </div>
  </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
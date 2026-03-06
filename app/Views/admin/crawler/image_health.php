<?php
$pageTitle = 'Image Health';
$activeNav = 'crawler-seo';
$slot = null;
ob_start();
$st = $stats ?? ['total_checked' => 0, 'total_broken' => 0, 'total_replaced' => 0, 'last_check' => null];
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="/admin/crawler/seo" style="font-size:14px;color:var(--muted,#888);text-decoration:none">← SEO Audit</a>
  </div>
  <div style="display:flex;gap:10px">
    <a href="/admin/crawler/seo/images/export" style="padding:10px 20px;font-size:14px;border-radius:8px;border:1px solid var(--border,#e2e2e2);text-decoration:none;color:var(--ink,#333);font-weight:600">↓ Export CSV</a>
    <form method="POST" action="/admin/crawler/seo/images/run" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <button type="submit" class="btn" style="padding:10px 20px;font-size:14px">🔍 Run Image Check</button>
    </form>
  </div>
</div>

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
  <?php foreach ([
    ['Images Checked', (int)$st['total_checked'], '🖼️', '#e3f2fd'],
    ['Broken', (int)$st['total_broken'], '💔', (int)$st['total_broken'] > 0 ? '#ffebee' : '#f5f5f5'],
    ['Auto-Replaced', (int)$st['total_replaced'], '🔄', '#e8f5e9'],
    ['Last Check', $st['last_check'] ? date('M j, g:i A', strtotime($st['last_check'])) : 'Never', '🕐', '#fff3e0'],
  ] as [$label, $value, $icon, $bg]): ?>
    <div style="background:<?= $bg ?>;padding:20px;border-radius:14px;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:28px;margin-bottom:4px"><?= $icon ?></div>
      <div style="font-size:<?= is_numeric($value) ? '24px' : '15px' ?>;font-weight:800"><?= $value ?></div>
      <div style="font-size:13px;color:var(--muted,#888)"><?= $label ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Broken Images Table -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2)">
    <span style="font-weight:700;font-size:15px">Broken Images (Last 7 Days)</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr style="background:var(--surface-alt,#f8f8f8);border-bottom:1px solid var(--border,#e2e2e2)">
        <th style="padding:14px 16px;text-align:left;font-weight:700">Article</th>
        <th style="padding:14px 12px;text-align:left;font-weight:700">Image URL</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">HTTP Status</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Replaced?</th>
        <th style="padding:14px 12px;text-align:left;font-weight:700">Checked</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($brokenImages)): ?>
        <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted,#888)">
          <?php if ((int)$st['total_checked'] === 0): ?>
            No image checks have been run yet. Click "Run Image Check" to scan.
          <?php else: ?>
            🎉 No broken images found! All hotlinked images are healthy.
          <?php endif; ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($brokenImages ?? [] as $img): ?>
        <tr style="border-bottom:1px solid var(--border,#f0f0f0)">
          <td style="padding:14px 16px">
            <a href="/<?= h($img['article_slug'] ?? '') ?>" target="_blank" style="color:var(--accent,#cc0000);text-decoration:none;font-weight:600">
              <?= h(mb_substr($img['article_title'] ?? 'Untitled', 0, 50)) ?>
            </a>
          </td>
          <td style="padding:14px 12px;max-width:300px">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--muted,#888)" title="<?= h($img['image_url'] ?? '') ?>">
              <?= h($img['image_url'] ?? '') ?>
            </div>
            <?php if (!empty($img['error_message'])): ?>
              <div style="font-size:11px;color:#dc3545;margin-top:2px"><?= h($img['error_message']) ?></div>
            <?php endif; ?>
          </td>
          <td style="padding:14px 12px;text-align:center">
            <?php $status = (int)($img['http_status'] ?? 0); ?>
            <span style="padding:3px 8px;border-radius:6px;font-size:12px;font-weight:600;<?= $status >= 400 ? 'background:#ffebee;color:#c62828' : ($status === 0 ? 'background:#f5f5f5;color:#888' : 'background:#fff3cd;color:#856404') ?>">
              <?= $status > 0 ? $status : 'Timeout' ?>
            </span>
          </td>
          <td style="padding:14px 12px;text-align:center">
            <?php if (!empty($img['replaced'])): ?>
              <span style="color:#28a745;font-size:13px">✅ Yes</span>
            <?php else: ?>
              <span style="color:var(--muted,#888);font-size:13px">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:14px 12px;font-size:13px;color:var(--muted,#888)">
            <?= isset($img['checked_at']) ? date('M j, g:i A', strtotime($img['checked_at'])) : '—' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="margin-top:16px;padding:16px 20px;background:var(--surface,#fff);border-radius:12px;border:1px solid var(--border,#e2e2e2);font-size:13px;color:var(--muted,#888)">
  <strong>How it works:</strong> The image health checker sends HEAD requests to all hotlinked images in crawled articles.
  Broken images (HTTP 4xx/5xx or timeout) are logged and optionally auto-replaced with a placeholder image.
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
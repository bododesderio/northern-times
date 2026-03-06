<?php
$pageTitle = 'Social Post Log';
$activeNav = 'social-posts';
$slot = null;
ob_start();

$platformColors = [
  'facebook' => ['bg' => '#eff6ff', 'color' => '#1d4ed8', 'label' => 'Facebook'],
  'twitter'  => ['bg' => '#f0f9ff', 'color' => '#0369a1', 'label' => 'Twitter/X'],
  'telegram' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'label' => 'Telegram'],
  'whatsapp' => ['bg' => '#f0fdf4', 'color' => '#166534', 'label' => 'WhatsApp'],
  'linkedin' => ['bg' => '#eff6ff', 'color' => '#1e40af', 'label' => 'LinkedIn'],
];

$statusColors = [
  'sent'    => ['bg' => '#d4edda', 'color' => '#155724'],
  'failed'  => ['bg' => '#fde8e8', 'color' => '#991b1b'],
  'skipped' => ['bg' => '#fef3c7', 'color' => '#92400e'],
];
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fde8e8;color:#991b1b"><?= h($flash_error) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:22px;font-weight:800;margin:0">Social Post Log</h1>
    <p style="margin:4px 0 0;color:#666;font-size:14px"><?= number_format($total) ?> auto-post attempt<?= $total !== 1 ? 's' : '' ?> recorded</p>
  </div>
  <a href="/admin/push/settings" class="btn light" style="font-size:13px">⚙ Social & Push Settings</a>
</div>

<?php if (empty($entries)): ?>
  <div style="text-align:center;padding:60px 20px;background:#f9f9f9;border-radius:16px;border:1px dashed #e2e2e2">
    <div style="font-size:40px;margin-bottom:12px">📭</div>
    <p style="color:#666;font-size:15px">No posts yet. Enable social platforms in <a href="/admin/push/settings">settings</a> and publish an article.</p>
  </div>
<?php else: ?>
  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:14px;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:#f9f9f9;text-align:left">
          <th style="padding:12px 16px;font-weight:600">Article</th>
          <th style="padding:12px 16px;font-weight:600">Platform</th>
          <th style="padding:12px 16px;font-weight:600">Status</th>
          <th style="padding:12px 16px;font-weight:600">Post Link</th>
          <th style="padding:12px 16px;font-weight:600">Error</th>
          <th style="padding:12px 16px;font-weight:600">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $i => $e):
          $pc = $platformColors[$e['platform']] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'label' => ucfirst($e['platform'])];
          $sc = $statusColors[$e['status']]    ?? ['bg' => '#f3f4f6', 'color' => '#374151'];
        ?>
        <tr style="border-top:1px solid #f0f0f0;<?= $i % 2 === 0 ? '' : 'background:#fafafa' ?>">
          <td style="padding:12px 16px;max-width:220px">
            <?php if ($e['article_slug']): ?>
              <a href="/<?= h($e['article_slug']) ?>" target="_blank" style="color:inherit;text-decoration:none;font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($e['article_title'] ?? '') ?>">
                <?= h(mb_substr($e['article_title'] ?? 'Untitled', 0, 50)) ?>
              </a>
            <?php else: ?>
              <span style="color:#aaa">Article deleted</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px">
            <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?= $pc['bg'] ?>;color:<?= $pc['color'] ?>">
              <?= h($pc['label']) ?>
            </span>
          </td>
          <td style="padding:12px 16px">
            <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>">
              <?= h(ucfirst($e['status'])) ?>
            </span>
          </td>
          <td style="padding:12px 16px">
            <?php if ($e['post_url']): ?>
              <a href="<?= h($e['post_url']) ?>" target="_blank" rel="noopener" style="font-size:12px;color:#2563eb">View post ↗</a>
            <?php else: ?>
              <span style="color:#ccc">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;max-width:200px">
            <?php if ($e['error']): ?>
              <span title="<?= h($e['error']) ?>" style="font-size:12px;color:#991b1b;cursor:help;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= h(mb_substr($e['error'], 0, 60)) ?>
              </span>
            <?php else: ?>
              <span style="color:#ccc">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;color:#888;white-space:nowrap">
            <?= date('M j, g:i A', strtotime($e['created_at'])) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin-top:20px">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;text-decoration:none;<?= $p === $page ? 'background:#121212;color:#fff;font-weight:700' : 'background:#f3f4f6;color:#374151' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
?>
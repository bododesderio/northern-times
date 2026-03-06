<?php
$pageTitle = $pageTitle ?? 'Popup A/B Tests';
$activeNav = 'popups';
$flashSuccess = \App\Services\Flash::get('success');
$flashError   = \App\Services\Flash::get('error');
$flash     = $flashSuccess ?? $flashError ?? '';
$flashType = $flashSuccess !== null ? 'success' : 'error';
$tests     = $tests ?? [];

$slot = null;
ob_start();
?>

<?php if ($flash): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;<?= $flashType === 'success' ? 'background:#e6ffe6;color:#006400' : 'background:#fff3cd;color:#856404' ?>">
    <?= h($flash) ?>
  </div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
  <h2 style="margin:0;">A/B Tests</h2>
  <a href="/admin/popups/ab/create" style="margin-left:auto;padding:10px 20px;background:var(--accent,#cc0000);color:#fff;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;">+ New A/B Test</a>
  <a href="/admin/popups" style="padding:10px 20px;background:var(--surface,#fff);color:var(--text,#333);border:1px solid var(--border,#ddd);border-radius:10px;text-decoration:none;font-size:14px;">Back to Popups</a>
</div>

<?php if (empty($tests)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--muted,#888);">
    <div style="font-size:48px;margin-bottom:12px;">🧪</div>
    <p style="font-size:16px;">No A/B tests yet. Create one to start comparing popup variants.</p>
  </div>
<?php else: ?>
  <div style="display:grid;gap:16px;">
    <?php foreach ($tests as $t): ?>
      <a href="/admin/popups/ab/<?= (int)$t['id'] ?>" style="text-decoration:none;color:inherit;display:block;background:var(--surface,#fff);border:1px solid var(--border,#e2e2e2);border-radius:14px;padding:20px;transition:border-color .15s;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <span style="font-size:20px;">🧪</span>
          <strong style="font-size:16px;"><?= h($t['name']) ?></strong>

          <?php
            $statusColors = ['draft' => '#6b7280', 'running' => '#22c55e', 'paused' => '#f59e0b', 'completed' => '#3b82f6'];
            $sc = $statusColors[$t['status']] ?? '#888';
          ?>
          <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:<?= $sc ?>22;color:<?= $sc ?>;">
            <?= ucfirst($t['status']) ?>
          </span>

          <span style="margin-left:auto;font-size:13px;color:var(--muted,#888);">
            Metric: <?= h(str_replace('_', ' ', $t['metric'])) ?>
          </span>
        </div>
        <div style="display:flex;gap:24px;margin-top:12px;font-size:13px;color:var(--muted,#666);">
          <span>A: <?= h($t['variant_a_name'] ?? 'N/A') ?></span>
          <span>B: <?= h($t['variant_b_name'] ?? 'N/A') ?></span>
          <?php if ($t['winner_id']): ?>
            <span style="color:#22c55e;font-weight:600;">Winner declared</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';

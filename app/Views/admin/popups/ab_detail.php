<?php
$pageTitle = $pageTitle ?? 'A/B Test Detail';
$activeNav = 'popups';
$flashSuccess = \App\Services\Flash::get('success');
$flashError   = \App\Services\Flash::get('error');
$flash     = $flashSuccess ?? $flashError ?? '';
$flashType = $flashSuccess !== null ? 'success' : 'error';
$test      = $test ?? [];
$csrf      = \App\Services\Csrf::token();

$variants  = $test['variants'] ?? [];
$variantA  = $variants[0] ?? null;
$variantB  = $variants[1] ?? null;

$statusColors = ['draft' => '#6b7280', 'running' => '#22c55e', 'paused' => '#f59e0b', 'completed' => '#3b82f6'];
$sc = $statusColors[$test['status']] ?? '#888';

$slot = null;
ob_start();
?>

<?php if ($flash): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;<?= $flashType === 'success' ? 'background:#e6ffe6;color:#006400' : 'background:#fff3cd;color:#856404' ?>">
    <?= h($flash) ?>
  </div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
  <h2 style="margin:0;">🧪 <?= h($test['name']) ?></h2>
  <span style="padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;background:<?= $sc ?>22;color:<?= $sc ?>;">
    <?= ucfirst($test['status']) ?>
  </span>
  <span style="font-size:13px;color:var(--muted,#888);">Metric: <?= h(str_replace('_', ' ', $test['metric'])) ?></span>
  <a href="/admin/popups/ab" style="margin-left:auto;padding:8px 16px;background:var(--surface,#fff);border:1px solid var(--border,#ddd);border-radius:8px;text-decoration:none;font-size:13px;color:var(--text,#333);">Back to Tests</a>
</div>

<!-- Action Buttons -->
<?php if ($test['status'] !== 'completed'): ?>
<div style="display:flex;gap:10px;margin-bottom:28px;flex-wrap:wrap;">
  <?php if ($test['status'] === 'draft' || $test['status'] === 'paused'): ?>
    <form method="POST" action="/admin/popups/ab/<?= (int)$test['id'] ?>/action" style="margin:0;">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="start">
      <button type="submit" style="padding:10px 20px;background:#22c55e;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">
        <?= $test['status'] === 'paused' ? 'Resume Test' : 'Start Test' ?>
      </button>
    </form>
  <?php endif; ?>

  <?php if ($test['status'] === 'running'): ?>
    <form method="POST" action="/admin/popups/ab/<?= (int)$test['id'] ?>/action" style="margin:0;">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="pause">
      <button type="submit" style="padding:10px 20px;background:#f59e0b;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">
        Pause Test
      </button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Variant Comparison -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">
  <?php foreach ($variants as $v): ?>
    <?php
      $isWinner = $test['winner_id'] && (int)$v['id'] === (int)$test['winner_id'];
      $border = $isWinner ? '2px solid #22c55e' : '1px solid var(--border,#e2e2e2)';
    ?>
    <div style="background:var(--surface,#fff);border:<?= $border ?>;border-radius:16px;padding:24px;position:relative;">
      <?php if ($isWinner): ?>
        <div style="position:absolute;top:-10px;right:16px;background:#22c55e;color:#fff;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;">WINNER</div>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;font-weight:700;font-size:16px;
          <?= $v['ab_variant'] === 'A' ? 'background:#3b82f622;color:#3b82f6' : 'background:#8b5cf622;color:#8b5cf6' ?>">
          <?= h($v['ab_variant']) ?>
        </span>
        <div>
          <strong style="font-size:15px;"><?= h($v['name']) ?></strong>
          <div style="font-size:12px;color:var(--muted,#888);"><?= h($v['popup_type'] ?? '') ?> &bull; <?= h($v['style'] ?? '') ?></div>
        </div>
        <a href="/admin/popups/<?= (int)$v['id'] ?>/edit" style="margin-left:auto;font-size:12px;color:var(--accent,#cc0000);text-decoration:none;">Edit</a>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="background:var(--bg,#f8f9fa);padding:14px;border-radius:10px;text-align:center;">
          <div style="font-size:24px;font-weight:700;"><?= number_format((int)$v['impressions']) ?></div>
          <div style="font-size:12px;color:var(--muted,#888);">Impressions</div>
        </div>
        <div style="background:var(--bg,#f8f9fa);padding:14px;border-radius:10px;text-align:center;">
          <div style="font-size:24px;font-weight:700;"><?= number_format((int)$v['clicks']) ?></div>
          <div style="font-size:12px;color:var(--muted,#888);">Clicks</div>
        </div>
        <div style="background:var(--bg,#f8f9fa);padding:14px;border-radius:10px;text-align:center;">
          <div style="font-size:24px;font-weight:700;"><?= number_format((int)$v['conversions']) ?></div>
          <div style="font-size:12px;color:var(--muted,#888);">Conversions</div>
        </div>
        <div style="background:var(--bg,#f8f9fa);padding:14px;border-radius:10px;text-align:center;">
          <div style="font-size:24px;font-weight:700;"><?= $v['conversion_rate'] ?>%</div>
          <div style="font-size:12px;color:var(--muted,#888);">Conv. Rate</div>
        </div>
      </div>

      <!-- Visual bar comparison -->
      <?php
        $maxRate = max(
          (float)($variantA['conversion_rate'] ?? 0),
          (float)($variantB['conversion_rate'] ?? 0),
          0.1
        );
        $barPct = round(((float)$v['conversion_rate'] / $maxRate) * 100);
      ?>
      <div style="margin-top:14px;">
        <div style="font-size:12px;color:var(--muted,#888);margin-bottom:4px;">Conversion Rate</div>
        <div style="background:var(--bg,#e5e7eb);border-radius:6px;height:10px;overflow:hidden;">
          <div style="height:100%;border-radius:6px;transition:width .6s;width:<?= $barPct ?>%;background:<?= $v['ab_variant'] === 'A' ? '#3b82f6' : '#8b5cf6' ?>;"></div>
        </div>
      </div>

      <div style="margin-top:14px;">
        <div style="font-size:12px;color:var(--muted,#888);margin-bottom:4px;">Click Rate</div>
        <?php
          $maxClick = max((float)($variantA['click_rate'] ?? 0), (float)($variantB['click_rate'] ?? 0), 0.1);
          $clickPct = round(((float)$v['click_rate'] / $maxClick) * 100);
        ?>
        <div style="background:var(--bg,#e5e7eb);border-radius:6px;height:10px;overflow:hidden;">
          <div style="height:100%;border-radius:6px;transition:width .6s;width:<?= $clickPct ?>%;background:<?= $v['ab_variant'] === 'A' ? '#3b82f6' : '#8b5cf6' ?>;"></div>
        </div>
      </div>

      <?php if ($test['status'] !== 'completed'): ?>
        <form method="POST" action="/admin/popups/ab/<?= (int)$test['id'] ?>/action" style="margin-top:16px;">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="declare_winner">
          <input type="hidden" name="winner_id" value="<?= (int)$v['id'] ?>">
          <button type="submit" onclick="return confirm('Declare Variant <?= $v['ab_variant'] ?> as winner? The test will end and the other variant will be deactivated.')"
                  style="width:100%;padding:10px;background:transparent;border:1px solid var(--border,#ddd);border-radius:8px;font-size:13px;cursor:pointer;color:var(--text,#333);transition:all .15s;">
            Declare Winner
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- Test Info -->
<div style="background:var(--surface,#fff);border:1px solid var(--border,#e2e2e2);border-radius:14px;padding:20px;">
  <h3 style="margin:0 0 12px;font-size:15px;">Test Details</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;font-size:13px;">
    <div><span style="color:var(--muted,#888);">Created:</span> <?= h($test['created_at'] ?? '-') ?></div>
    <div><span style="color:var(--muted,#888);">Started:</span> <?= h($test['started_at'] ?? 'Not started') ?></div>
    <div><span style="color:var(--muted,#888);">Ended:</span> <?= h($test['ended_at'] ?? 'Running') ?></div>
    <div><span style="color:var(--muted,#888);">Confidence:</span> <?= $test['confidence'] ? $test['confidence'] . '%' : 'N/A' ?></div>
  </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';

<?php
$pageTitle = $pageTitle ?? 'Create A/B Test';
$activeNav = 'popups';
$popups    = $popups ?? [];
$csrf      = \App\Services\Csrf::token();

$slot = null;
ob_start();
?>

<div style="max-width:700px;margin:0 auto;">
  <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
    <h2 style="margin:0;">Create A/B Test</h2>
    <a href="/admin/popups/ab" style="margin-left:auto;padding:8px 16px;background:var(--surface,#fff);border:1px solid var(--border,#ddd);border-radius:8px;text-decoration:none;font-size:13px;color:var(--text,#333);">Cancel</a>
  </div>

  <form method="POST" action="/admin/popups/ab/store" style="display:grid;gap:20px;">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <div>
      <label style="font-size:14px;font-weight:600;display:block;margin-bottom:6px;">Test Name</label>
      <input type="text" name="name" required placeholder="e.g. Newsletter popup CTA test"
             style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--border,#ddd);font-size:14px;background:var(--surface,#fff);color:var(--text,#333);">
    </div>

    <div>
      <label style="font-size:14px;font-weight:600;display:block;margin-bottom:6px;">Variant A (Original)</label>
      <select name="popup_a" required style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--border,#ddd);font-size:14px;background:var(--surface,#fff);color:var(--text,#333);">
        <option value="">Select a popup...</option>
        <?php foreach ($popups as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (<?= h($p['popup_type']) ?>, <?= h($p['status']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <p style="font-size:12px;color:var(--muted,#888);margin-top:4px;">This is the original popup (control group).</p>
    </div>

    <div>
      <label style="font-size:14px;font-weight:600;display:block;margin-bottom:6px;">Variant B (Challenger)</label>
      <select name="popup_b" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--border,#ddd);font-size:14px;background:var(--surface,#fff);color:var(--text,#333);">
        <option value="">Auto-duplicate Variant A</option>
        <?php foreach ($popups as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (<?= h($p['popup_type']) ?>, <?= h($p['status']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <p style="font-size:12px;color:var(--muted,#888);margin-top:4px;">Leave empty to auto-create a duplicate. Or select an existing popup as Variant B.</p>
    </div>

    <div>
      <label style="font-size:14px;font-weight:600;display:block;margin-bottom:6px;">Success Metric</label>
      <select name="metric" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--border,#ddd);font-size:14px;background:var(--surface,#fff);color:var(--text,#333);">
        <option value="conversion_rate">Conversion Rate</option>
        <option value="click_rate">Click Rate</option>
        <option value="impressions">Impressions</option>
      </select>
    </div>

    <div style="background:var(--surface,#f8f9fa);border:1px solid var(--border,#e2e2e2);border-radius:12px;padding:16px;">
      <strong style="font-size:14px;">How A/B Testing Works</strong>
      <ul style="font-size:13px;color:var(--muted,#666);margin:8px 0 0;padding-left:18px;line-height:1.8;">
        <li>Traffic is split 50/50 between Variant A and Variant B.</li>
        <li>Each visitor is assigned one variant and always sees the same one (sticky assignment).</li>
        <li>After enough data, review the results and declare a winner.</li>
        <li>The winning popup stays active; the loser is deactivated.</li>
      </ul>
    </div>

    <button type="submit" style="padding:12px 28px;background:var(--accent,#cc0000);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;">
      Create A/B Test
    </button>
  </form>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';

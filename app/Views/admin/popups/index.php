<?php
$pageTitle = 'Popups';
$activeNav = 'popups';
$flashSuccess = \App\Services\Flash::get('success');
$flashError   = \App\Services\Flash::get('error');
$flash     = $flashSuccess ?? $flashError ?? '';
$flashType = $flashSuccess !== null ? 'success' : 'error';
$items     = $items ?? [];
$stats     = $stats ?? ['totals' => ['total' => 0, 'active' => 0, 'impressions' => 0, 'conversions' => 0], 'top_performers' => []];
$totals    = $stats['totals'];

$typeLabels = \App\Models\Popup::TYPE_LABELS;
$styleLabels = \App\Models\Popup::STYLE_LABELS;
$slot = null;
ob_start();
?>

<?php if ($flash): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;<?= $flashType === 'success' ? 'background:#e6ffe6;color:#006400' : 'background:#fff3cd;color:#856404' ?>">
    <?= h($flash) ?>
  </div>
<?php endif; ?>

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px">
  <div style="background:var(--surface,#fff);padding:20px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
    <div style="font-size:28px;font-weight:700"><?= (int)$totals['total'] ?></div>
    <div style="font-size:13px;color:var(--muted,#666)">Total Popups</div>
  </div>
  <div style="background:var(--surface,#fff);padding:20px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
    <div style="font-size:28px;font-weight:700;color:#006400"><?= (int)$totals['active'] ?></div>
    <div style="font-size:13px;color:var(--muted,#666)">Active</div>
  </div>
  <div style="background:var(--surface,#fff);padding:20px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
    <div style="font-size:28px;font-weight:700"><?= number_format((int)$totals['impressions']) ?></div>
    <div style="font-size:13px;color:var(--muted,#666)">Total Impressions</div>
  </div>
  <div style="background:var(--surface,#fff);padding:20px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
    <div style="font-size:28px;font-weight:700;color:#cc0000"><?= number_format((int)$totals['conversions']) ?></div>
    <div style="font-size:13px;color:var(--muted,#666)">Conversions</div>
  </div>
</div>

<!-- Actions Bar -->
<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px">
  <a href="/admin/popups/create" class="btn" style="padding:12px 20px">+ New Popup</a>

  <form method="GET" action="/admin/popups" style="display:flex;gap:8px;flex-wrap:wrap;flex:1">
    <select name="status" style="padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
      <option value="">All Statuses</option>
      <?php foreach (\App\Models\Popup::STATUSES as $s): ?>
        <option value="<?= h($s) ?>" <?= ($status ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" style="padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
      <option value="">All Types</option>
      <?php foreach ($typeLabels as $k => $v): ?>
        <option value="<?= h($k) ?>" <?= ($type ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn light" style="padding:10px 16px">Filter</button>
  </form>
</div>

<!-- Popups Table -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow-x:auto">
  <?php if (empty($items)): ?>
    <div style="padding:40px;text-align:center;color:var(--muted,#666)">
      No popups yet. <a href="/admin/popups/create">Create your first popup</a>
    </div>
  <?php else: ?>
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:var(--surface,#fafafa);border-bottom:2px solid var(--border,#e2e2e2)">
          <th style="padding:14px 16px;text-align:left;font-size:13px;font-weight:600">Name</th>
          <th style="padding:14px 16px;text-align:left;font-size:13px;font-weight:600">Type</th>
          <th style="padding:14px 16px;text-align:left;font-size:13px;font-weight:600">Style</th>
          <th style="padding:14px 16px;text-align:left;font-size:13px;font-weight:600">Status</th>
          <th style="padding:14px 16px;text-align:right;font-size:13px;font-weight:600">Views</th>
          <th style="padding:14px 16px;text-align:right;font-size:13px;font-weight:600">Conv.</th>
          <th style="padding:14px 16px;text-align:right;font-size:13px;font-weight:600">Rate</th>
          <th style="padding:14px 16px;text-align:right;font-size:13px;font-weight:600">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $p): ?>
          <?php
            $rate = (int)$p['impressions'] > 0
              ? round((int)$p['conversions'] / (int)$p['impressions'] * 100, 1) . '%'
              : '—';
            $statusColors = match($p['status']) {
                'active'    => 'background:#e6ffe6;color:#006400',
                'draft'     => 'background:#fff3cd;color:#856404',
                'scheduled' => 'background:#e3f2fd;color:#1565c0',
                'inactive'  => 'background:#f0f0f0;color:#666',
                default     => 'background:#f8d7da;color:#721c24',
            };
          ?>
          <tr style="border-bottom:1px solid var(--border,#f0f0f0)">
            <td style="padding:14px 16px">
              <strong><?= h($p['name']) ?></strong>
              <div style="font-size:12px;color:var(--muted,#888)">Priority: <?= (int)$p['priority'] ?></div>
            </td>
            <td style="padding:14px 16px;font-size:13px"><?= h($typeLabels[$p['popup_type']] ?? $p['popup_type']) ?></td>
            <td style="padding:14px 16px;font-size:13px"><?= h($styleLabels[$p['banner_style']] ?? $p['banner_style']) ?></td>
            <td style="padding:14px 16px">
              <span style="display:inline-block;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:500;<?= $statusColors ?>"><?= ucfirst($p['status']) ?></span>
            </td>
            <td style="padding:14px 16px;text-align:right;font-size:14px"><?= number_format((int)$p['impressions']) ?></td>
            <td style="padding:14px 16px;text-align:right;font-size:14px"><?= number_format((int)$p['conversions']) ?></td>
            <td style="padding:14px 16px;text-align:right;font-size:14px;font-weight:600"><?= $rate ?></td>
            <td style="padding:14px 16px;text-align:right">
              <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                <a href="/admin/popups/<?= h($p['id']) ?>/edit" class="btn light" style="padding:6px 12px;font-size:13px">Edit</a>
                <form method="POST" action="/admin/popups/<?= h($p['id']) ?>/toggle" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <button type="submit" class="btn light" style="padding:6px 12px;font-size:13px"><?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="POST" action="/admin/popups/<?= h($p['id']) ?>/duplicate" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <button type="submit" class="btn light" style="padding:6px 12px;font-size:13px">Duplicate</button>
                </form>
                <form method="POST" action="/admin/popups/<?= h($p['id']) ?>/delete" data-confirm="Delete this popup permanently?" data-confirm-title="Delete Popup" data-confirm-level="danger" data-confirm-ok="Delete" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <button type="submit" class="btn light" style="padding:6px 12px;font-size:13px;color:#c00">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if (($pages ?? 1) > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="/admin/popups?page=<?= $i ?>&status=<?= h($status ?? '') ?>&type=<?= h($type ?? '') ?>"
       style="padding:8px 14px;border-radius:8px;font-size:14px;text-decoration:none;<?= $i === (int)($page ?? 1) ? 'background:var(--accent,#cc0000);color:#fff' : 'background:#f0f0f0;color:#333' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
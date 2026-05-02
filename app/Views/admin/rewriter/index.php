<?php
$pageTitle = 'AI Rewriter';
$activeNav = 'rewriter';
$slot = null;
ob_start();

$queued     = (int)($counts['queued']           ?? 0);
$processing = (int)($counts['processing']       ?? 0);
$pending    = (int)($counts['pending_approval'] ?? 0);
$approved   = (int)($counts['approved']         ?? 0);
$failed     = (int)($counts['failed']           ?? 0);
$rejected   = (int)($counts['rejected']         ?? 0);
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Service Status -->
<div style="margin-bottom:20px;display:flex;align-items:center;gap:12px">
  <span style="font-size:13px;font-weight:600;color:<?= $rewriterEnabled ? '#28a745' : '#dc3545' ?>">
    <?= $rewriterEnabled ? '● Rewriter ON' : '● Rewriter OFF' ?>
  </span>
  <span id="rwHealth" style="font-size:12px;color:var(--muted,#888)">checking service...</span>
  <a href="/admin/rewriter/settings" style="font-size:13px;color:var(--accent,#cc0000);text-decoration:none;margin-left:auto">Settings</a>
</div>

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px">
  <?php foreach ([
    ['Queued',           $queued,   '#fff3e0', '#f57c00'],
    ['Processing',       $processing, '#e3f2fd', '#1976d2'],
    ['Pending Approval', $pending,  '#e8eaf6', '#283593'],
    ['Approved',         $approved, '#e8f5e9', '#2e7d32'],
    ['Failed',           $failed,   $failed > 0 ? '#ffebee' : '#f5f5f5', '#c62828'],
  ] as [$label, $value, $bg, $color]): ?>
    <div style="background:<?= $bg ?>;padding:18px;border-radius:14px;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:22px;font-weight:800;color:<?= $color ?>"><?= $value ?></div>
      <div style="font-size:12px;color:var(--muted,#888)"><?= $label ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Bulk Queue -->
<div style="background:var(--surface,#fff);border-radius:14px;border:1px solid var(--border,#e2e2e2);padding:20px;margin-bottom:24px">
  <div style="font-weight:700;font-size:15px;margin-bottom:14px">Bulk Queue</div>
  <form method="POST" action="/admin/rewriter/queue-bulk" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--muted,#888)">Source (optional)</label>
      <select name="source_id" style="padding:8px 12px;border:1px solid var(--border,#ddd);border-radius:8px;font-size:13px;min-width:180px">
        <option value="0">All crawl sources</option>
        <?php
          try {
            $allSources = \App\Services\DB::pdo()->query("SELECT id, name FROM crawl_sources ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($allSources as $src): ?>
              <option value="<?= h($src['id']) ?>"><?= h($src['name']) ?></option>
            <?php endforeach;
          } catch (\Throwable) {}
        ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--muted,#888)">Since date (optional)</label>
      <input type="date" name="since" style="padding:8px 12px;border:1px solid var(--border,#ddd);border-radius:8px;font-size:13px">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--muted,#888)">Max articles</label>
      <input type="number" name="limit" value="50" min="1" max="500" style="padding:8px 12px;border:1px solid var(--border,#ddd);border-radius:8px;font-size:13px;width:80px">
    </div>
    <button type="submit" style="padding:8px 20px;background:var(--accent,#cc0000);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Queue for Rewriting</button>
  </form>
</div>

<!-- Recent Jobs Table -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);display:flex;justify-content:space-between;align-items:center">
    <span style="font-weight:700;font-size:15px">Rewrite Queue</span>
    <span style="font-size:12px;color:var(--muted,#888)"><?= count($recent) ?> job(s)</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr style="background:var(--surface-alt,#f8f8f8);border-bottom:1px solid var(--border,#e2e2e2)">
        <th style="padding:12px 16px;text-align:left;font-weight:700">Article</th>
        <th style="padding:12px 12px;text-align:center;font-weight:700">Status</th>
        <th style="padding:12px 12px;text-align:center;font-weight:700">Model</th>
        <th style="padding:12px 12px;text-align:left;font-weight:700">Rewritten</th>
        <th style="padding:12px 16px;text-align:right;font-weight:700">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($recent)): ?>
        <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted,#888)">No articles in the rewrite queue yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr style="border-bottom:1px solid var(--border,#f0f0f0)">
          <td style="padding:12px 16px;max-width:300px">
            <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['title']) ?>"><?= h(mb_substr($r['title'], 0, 80)) ?></div>
            <div style="font-size:11px;color:var(--muted,#888)">ID: <?= h($r['id']) ?></div>
          </td>
          <td style="padding:12px 12px;text-align:center">
            <?php
              $badge = match($r['rewrite_status']) {
                'queued'           => ['#fff3e0', '#e65100', 'Queued'],
                'processing'       => ['#e3f2fd', '#1565c0', 'Processing'],
                'pending_approval' => ['#e8eaf6', '#283593', 'Pending Approval'],
                'approved'         => ['#e8f5e9', '#2e7d32', 'Approved'],
                'rejected'         => ['#fce4ec', '#880e4f', 'Rejected'],
                'failed'           => ['#ffebee', '#c62828', 'Failed'],
                default            => ['#f5f5f5', '#888',    $r['rewrite_status']],
              };
            ?>
            <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?= $badge[0] ?>;color:<?= $badge[1] ?>"><?= $badge[2] ?></span>
          </td>
          <td style="padding:12px 12px;text-align:center;font-size:12px;color:var(--muted,#888)"><?= h($r['rewriter_model'] ?? '—') ?></td>
          <td style="padding:12px 12px;font-size:13px"><?= !empty($r['rewritten_at']) ? date('M j, g:i A', strtotime($r['rewritten_at'])) : '<span style="color:var(--muted,#888)">—</span>' ?></td>
          <td style="padding:12px 16px;text-align:right">
            <div style="display:flex;justify-content:flex-end;gap:6px">
              <?php if ($r['rewrite_status'] === 'pending_approval'): ?>
                <a href="/admin/rewriter/<?= h($r['id']) ?>/review" style="padding:5px 12px;border:1px solid #c5cae9;border-radius:8px;background:#e8eaf6;text-decoration:none;font-size:12px;font-weight:600;color:#283593">Review</a>
              <?php endif; ?>
              <?php if (in_array($r['rewrite_status'], ['failed', 'rejected'], true)): ?>
                <form method="POST" action="/admin/rewriter/<?= h($r['id']) ?>/retry" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <button type="submit" title="Retry" style="padding:5px 10px;border:1px solid #e2e2e2;border-radius:8px;background:var(--surface,#fff);cursor:pointer;font-size:12px">Retry</button>
                </form>
              <?php endif; ?>
              <?php if ($r['rewrite_status'] === 'approved'): ?>
                <form method="POST" action="/admin/rewriter/<?= h($r['id']) ?>/revert" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <button type="submit" title="Revert to original" data-confirm="Revert this article to its original pre-rewrite content?" data-confirm-title="Revert Article" data-confirm-level="warn" data-confirm-ok="Revert" style="padding:5px 10px;border:1px solid #ffcdd2;border-radius:8px;background:#fff5f5;cursor:pointer;font-size:12px;color:#c62828">Revert</button>
                </form>
              <?php endif; ?>
              <?php if (in_array($r['rewrite_status'], ['skipped', 'rejected'], true)): ?>
                <form method="POST" action="/admin/rewriter/<?= h($r['id']) ?>/queue" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <button type="submit" title="Queue for rewriting" style="padding:5px 10px;border:1px solid #c8e6c9;border-radius:8px;background:#e8f5e9;cursor:pointer;font-size:12px;color:#2e7d32">+ Queue</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
(function(){
  fetch('/admin/rewriter/status-api')
    .then(r=>r.json())
    .then(d=>{
      const el=document.getElementById('rwHealth');
      if(d.healthy){el.textContent='Service healthy';el.style.color='#28a745'}
      else{el.textContent='Service unreachable';el.style.color='#dc3545'}
    })
    .catch(()=>{
      const el=document.getElementById('rwHealth');
      el.textContent='Could not check';el.style.color='#888';
    });
  <?php if ($processing > 0): ?>
  setTimeout(()=>location.reload(), 15000);
  <?php endif; ?>
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>

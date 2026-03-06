<?php
$pageTitle = 'Crawl Logs';
$activeNav = 'crawler';
$slot = null;
ob_start();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="/admin/crawler" style="font-size:14px;color:var(--muted,#888);text-decoration:none">← Back to Sources</a>
  </div>
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <select name="source" onchange="this.form.submit()" style="padding:10px 14px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
      <option value="">All Sources</option>
      <?php foreach ($sources as $s): ?>
        <option value="<?= h($s['id']) ?>" <?= $sourceId === $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr style="background:var(--surface-alt,#f8f8f8);border-bottom:1px solid var(--border,#e2e2e2)">
        <th style="padding:14px 16px;text-align:left;font-weight:700">Source</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Status</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Found</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">New</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Dupes</th>
        <th style="padding:14px 12px;text-align:left;font-weight:700">Started</th>
        <th style="padding:14px 12px;text-align:left;font-weight:700">Duration</th>
        <th style="padding:14px 16px;text-align:center;font-weight:700">Details</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="8" style="padding:40px;text-align:center;color:var(--muted,#888)">No crawl logs yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($logs as $i => $log): ?>
        <?php
          $statusBadge = match($log['status']) {
            'success' => ['✅ Success', '#d4edda', '#155724'],
            'partial' => ['⚠️ Partial', '#fff3cd', '#856404'],
            'failed'  => ['❌ Failed', '#ffebee', '#c62828'],
            default   => ['🔄 Running', '#e3f2fd', '#1565c0'],
          };
          $duration = '';
          if ($log['finished_at'] && $log['started_at']) {
            $secs = strtotime($log['finished_at']) - strtotime($log['started_at']);
            $duration = $secs < 60 ? $secs . 's' : round($secs / 60, 1) . 'm';
          }
        ?>
        <tr style="border-bottom:1px solid var(--border,#f0f0f0)">
          <td style="padding:14px 16px;font-weight:600"><?= h($log['source_name']) ?></td>
          <td style="padding:14px 12px;text-align:center">
            <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?= $statusBadge[1] ?>;color:<?= $statusBadge[2] ?>"><?= $statusBadge[0] ?></span>
          </td>
          <td style="padding:14px 12px;text-align:center"><?= (int)$log['articles_found'] ?></td>
          <td style="padding:14px 12px;text-align:center;font-weight:700;color:#28a745"><?= (int)$log['articles_new'] ?></td>
          <td style="padding:14px 12px;text-align:center;color:var(--muted,#888)"><?= (int)$log['articles_dupes'] ?></td>
          <td style="padding:14px 12px;font-size:13px"><?= date('M j, g:i:s A', strtotime($log['started_at'])) ?></td>
          <td style="padding:14px 12px;font-size:13px"><?= $duration ?></td>
          <td style="padding:14px 16px;text-align:center">
            <?php if ($log['details'] || $log['error_message']): ?>
              <button type="button" onclick="toggleDetails(<?= $i ?>)" style="padding:4px 10px;border:1px solid #e2e2e2;border-radius:6px;background:var(--surface,#fff);cursor:pointer;font-size:12px">▼</button>
            <?php else: ?>
              <span style="color:var(--muted,#ccc)">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($log['details'] || $log['error_message']): ?>
          <tr id="details-<?= $i ?>" style="display:none">
            <td colspan="8" style="padding:12px 24px;background:var(--surface-alt,#fafafa)">
              <?php if ($log['error_message']): ?>
                <div style="padding:10px;background:#fff5f5;border-radius:8px;font-size:13px;color:#c62828;margin-bottom:8px">
                  <strong>Error:</strong> <?= h($log['error_message']) ?>
                </div>
              <?php endif; ?>
              <?php
                $details = $log['details'] ? (is_string($log['details']) ? json_decode($log['details'], true) : $log['details']) : [];
                if (!empty($details)):
              ?>
                <div style="font-size:12px;max-height:200px;overflow-y:auto">
                  <?php foreach ($details as $d): ?>
                    <div style="padding:4px 0;display:flex;gap:8px;border-bottom:1px solid #f0f0f0">
                      <span><?= ($d['status'] ?? '') === 'published' ? '✅' : (($d['status'] ?? '') === 'duplicate' ? '🔁' : '❌') ?></span>
                      <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($d['title'] ?? '?') ?></span>
                      <span style="color:var(--muted,#888)"><?= h($d['status'] ?? '') ?></span>
                      <?php if (!empty($d['error'])): ?>
                        <span style="color:#c62828" title="<?= h($d['error']) ?>">⚠</span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function toggleDetails(i) {
  var row = document.getElementById('details-' + i);
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
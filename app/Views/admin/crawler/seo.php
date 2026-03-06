<?php
$pageTitle = 'SEO Audit';
$activeNav = 'crawler-seo';
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

<!-- Health Score Card -->
<?php if ($latest): ?>
<div style="display:grid;grid-template-columns:200px 1fr;gap:24px;margin-bottom:24px">
  <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <?php
      $score = (int)$latest['health_score'];
      $color = $score >= 80 ? '#22c55e' : ($score >= 60 ? '#f59e0b' : '#dc3545');
    ?>
    <div style="width:120px;height:120px;border-radius:50%;border:8px solid <?= $color ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
      <span style="font-size:36px;font-weight:900;color:<?= $color ?>"><?= $score ?></span>
    </div>
    <div style="font-weight:700;font-size:15px">Health Score</div>
    <div style="font-size:12px;color:var(--muted,#888);margin-top:4px"><?= date('M j, g:i A', strtotime($latest['started_at'])) ?></div>
  </div>

  <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px">
      <h3 style="margin:0;font-size:16px;font-weight:700">Latest Audit Results</h3>
      <div style="display:flex;gap:8px">
        <form method="POST" action="/admin/crawler/seo/run" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <button type="submit" class="btn sm">🔍 Run Audit</button>
        </form>
        <form method="POST" action="/admin/crawler/seo/images/run" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <button type="submit" class="btn sm light">🖼 Check Images</button>
        </form>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
      <div style="padding:12px;background:#f0fdf4;border-radius:10px;text-align:center">
        <div style="font-size:22px;font-weight:800;color:#16a34a"><?= (int)$latest['passed_count'] ?></div>
        <div style="font-size:12px;color:#888">Passed</div>
      </div>
      <div style="padding:12px;background:#fff7ed;border-radius:10px;text-align:center">
        <div style="font-size:22px;font-weight:800;color:#ea580c"><?= (int)$latest['warning_count'] ?></div>
        <div style="font-size:12px;color:#888">Warnings</div>
      </div>
      <div style="padding:12px;background:#fef2f2;border-radius:10px;text-align:center">
        <div style="font-size:22px;font-weight:800;color:#dc2626"><?= (int)$latest['critical_count'] ?></div>
        <div style="font-size:12px;color:#888">Critical</div>
      </div>
      <div style="padding:12px;background:#eff6ff;border-radius:10px;text-align:center">
        <div style="font-size:22px;font-weight:800;color:#2563eb"><?= (int)$latest['pages_scanned'] ?></div>
        <div style="font-size:12px;color:#888">Pages</div>
      </div>
    </div>

    <!-- Check breakdown -->
    <?php if (!empty($checkDetails)): ?>
    <div style="max-height:280px;overflow-y:auto">
      <?php foreach ($checkDetails as $name => $check): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #f5f5f5;font-size:13px">
          <?php if ($check['status'] === 'passed'): ?>
            <span style="color:#22c55e">✅</span>
          <?php elseif ($check['status'] === 'critical'): ?>
            <span style="color:#dc2626">🔴</span>
          <?php else: ?>
            <span style="color:#f59e0b">🟡</span>
          <?php endif; ?>
          <span style="flex:1"><?= h($check['label'] ?? $name) ?></span>
          <span style="font-weight:600;color:<?= $check['count'] > 0 ? '#dc2626' : '#22c55e' ?>"><?= (int)$check['count'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div style="background:var(--surface,#fff);padding:40px;border-radius:16px;border:1px solid var(--border,#e2e2e2);text-align:center;margin-bottom:24px">
  <div style="font-size:48px;margin-bottom:12px">🔍</div>
  <h3 style="margin:0 0 8px;font-size:18px">No SEO Audit Yet</h3>
  <p style="color:var(--muted,#888);margin:0 0 16px;font-size:14px">Run your first audit to get a site health score.</p>
  <form method="POST" action="/admin/crawler/seo/run" style="display:inline">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <button type="submit" class="btn" style="padding:12px 24px;font-size:15px">🔍 Run SEO Audit</button>
  </form>
</div>
<?php endif; ?>

<!-- Audit History -->
<?php if (!empty($recentAudits)): ?>
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden;margin-bottom:24px">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);font-weight:700;font-size:15px">Audit History</div>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr style="background:var(--surface-alt,#f8f8f8);border-bottom:1px solid var(--border,#e2e2e2)">
        <th style="padding:12px 16px;text-align:left;font-weight:700">Date</th>
        <th style="padding:12px;text-align:center;font-weight:700">Score</th>
        <th style="padding:12px;text-align:center;font-weight:700">Pages</th>
        <th style="padding:12px;text-align:center;font-weight:700">Issues</th>
        <th style="padding:12px;text-align:center;font-weight:700">Status</th>
        <th style="padding:12px 16px;text-align:right;font-weight:700">Details</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recentAudits as $audit): ?>
        <tr style="border-bottom:1px solid #f0f0f0">
          <td style="padding:12px 16px"><?= date('M j, Y g:i A', strtotime($audit['started_at'])) ?></td>
          <td style="padding:12px;text-align:center">
            <?php if ($audit['health_score'] !== null): ?>
              <?php $c = (int)$audit['health_score'] >= 80 ? '#22c55e' : ((int)$audit['health_score'] >= 60 ? '#f59e0b' : '#dc3545'); ?>
              <span style="font-weight:800;color:<?= $c ?>"><?= (int)$audit['health_score'] ?></span>
            <?php else: ?>
              <span style="color:#ccc">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px;text-align:center"><?= (int)$audit['pages_scanned'] ?></td>
          <td style="padding:12px;text-align:center;font-weight:600"><?= (int)$audit['issues_found'] ?></td>
          <td style="padding:12px;text-align:center">
            <?php $badge = match($audit['status']) { 'completed' => ['✅','#d4edda','#155724'], 'failed' => ['❌','#ffebee','#c62828'], default => ['🔄','#e3f2fd','#1565c0'] }; ?>
            <span style="padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?= $badge[1] ?>;color:<?= $badge[2] ?>"><?= $badge[0] ?> <?= h(ucfirst($audit['status'])) ?></span>
          </td>
          <td style="padding:12px 16px;text-align:right">
            <?php if ($audit['status'] === 'completed'): ?>
              <a href="/admin/crawler/seo/<?= (int)$audit['id'] ?>" style="color:var(--accent,#cc0000);font-size:13px">View →</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Settings -->
<div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
  <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">SEO Settings</h3>
  <form method="POST" action="/admin/crawler/seo/settings" style="display:grid;gap:14px;max-width:500px">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
      <input type="checkbox" name="seo_audit_enabled" value="1" <?= ($st['seo_audit_enabled'] ?? 'true') === 'true' ? 'checked' : '' ?>>
      <span style="font-weight:600;font-size:14px">Enable scheduled SEO audits</span>
    </label>
    <div>
      <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Audit Schedule</label>
      <select name="seo_audit_schedule" style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
        <option value="manual" <?= ($st['seo_audit_schedule'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual only</option>
        <option value="daily" <?= ($st['seo_audit_schedule'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
        <option value="weekly" <?= ($st['seo_audit_schedule'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
      </select>
    </div>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
      <input type="checkbox" name="image_health_enabled" value="1" checked>
      <span style="font-weight:600;font-size:14px">Enable daily image health checks</span>
    </label>
    <button type="submit" class="btn" style="padding:10px 20px;font-size:14px;align-self:start">Save Settings</button>
  </form>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
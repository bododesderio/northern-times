<?php
$pageTitle = 'Audit Details';
$activeNav = 'crawler-seo';
$slot = null;
ob_start();
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="/admin/crawler/seo" style="font-size:14px;color:var(--muted,#888);text-decoration:none">← Back to SEO Audit</a>
  <div style="margin-left:auto;display:flex;gap:8px">
    <a href="/admin/crawler/seo/<?= (int)$audit['id'] ?>/export" style="padding:8px 18px;border-radius:8px;background:var(--ink,#121212);color:#fff;font-size:13px;text-decoration:none;font-weight:600">Export CSV</a>
    <a href="/admin/crawler/seo/<?= (int)$audit['id'] ?>/export-pdf" style="padding:8px 18px;border-radius:8px;border:1px solid var(--border,#e2e2e2);color:var(--ink,#333);font-size:13px;text-decoration:none;font-weight:600">Export PDF</a>
  </div>
</div>

<!-- Audit Summary -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px">
  <?php $sc = (int)$audit['health_score']; $c = $sc >= 80 ? '#22c55e' : ($sc >= 60 ? '#f59e0b' : '#dc3545'); ?>
  <div style="background:var(--surface,#fff);padding:16px;border-radius:12px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:28px;font-weight:900;color:<?= $c ?>"><?= $sc ?></div>
    <div style="font-size:12px;color:#888">Score</div>
  </div>
  <div style="background:var(--surface,#fff);padding:16px;border-radius:12px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:28px;font-weight:900"><?= (int)$audit['pages_scanned'] ?></div>
    <div style="font-size:12px;color:#888">Pages</div>
  </div>
  <div style="background:var(--surface,#fff);padding:16px;border-radius:12px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:28px;font-weight:900;color:#dc2626"><?= (int)$audit['critical_count'] ?></div>
    <div style="font-size:12px;color:#888">Critical</div>
  </div>
  <div style="background:var(--surface,#fff);padding:16px;border-radius:12px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:28px;font-weight:900;color:#ea580c"><?= (int)$audit['warning_count'] ?></div>
    <div style="font-size:12px;color:#888">Warnings</div>
  </div>
  <div style="background:var(--surface,#fff);padding:16px;border-radius:12px;border:1px solid var(--border,#e2e2e2);text-align:center">
    <div style="font-size:28px;font-weight:900;color:#22c55e"><?= (int)$audit['passed_count'] ?></div>
    <div style="font-size:12px;color:#888">Passed</div>
  </div>
</div>

<!-- Issues List -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);font-weight:700;font-size:15px"><?= count($issues) ?> Issues Found</div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--surface-alt,#f8f8f8);border-bottom:1px solid var(--border,#e2e2e2)">
        <th style="padding:10px 16px;text-align:center;font-weight:700;width:36px">Sev</th>
        <th style="padding:10px 12px;text-align:left;font-weight:700">Check</th>
        <th style="padding:10px 12px;text-align:left;font-weight:700">Issue</th>
        <th style="padding:10px 12px;text-align:left;font-weight:700">Suggestion</th>
        <th style="padding:10px 16px;text-align:left;font-weight:700">Page</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($issues)): ?>
        <tr><td colspan="5" style="padding:40px;text-align:center;color:#888">🎉 No issues found! Perfect score.</td></tr>
      <?php endif; ?>
      <?php foreach ($issues as $issue): ?>
        <tr style="border-bottom:1px solid #f0f0f0">
          <td style="padding:10px 16px;text-align:center">
            <?= match($issue['severity']) { 'critical' => '🔴', 'warning' => '🟡', default => '🔵' } ?>
          </td>
          <td style="padding:10px 12px;font-weight:600;white-space:nowrap"><?= h(str_replace('_', ' ', $issue['check_name'])) ?></td>
          <td style="padding:10px 12px"><?= h($issue['description']) ?></td>
          <td style="padding:10px 12px;color:#666;font-style:italic"><?= h($issue['suggestion'] ?? '') ?></td>
          <td style="padding:10px 16px">
            <?php if ($issue['page_url']): ?>
              <a href="<?= h($issue['page_url']) ?>" target="_blank" style="color:var(--accent,#cc0000);font-size:12px"><?= h(mb_substr($issue['page_url'], 0, 40)) ?></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
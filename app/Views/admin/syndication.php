<?php
/**
 * Admin Syndication & Digest
 * Route: GET /admin/syndication
 */
$pageTitle       = $pageTitle ?? 'Syndication & Digest';
$activeNav       = $activeNav ?? 'syndication';
$totalArticles   = $totalArticles ?? 0;
$withSummary     = $withSummary ?? 0;
$thisWeek        = $thisWeek ?? 0;
$subscriberCount = $subscriberCount ?? 0;
$lastDigest      = $lastDigest ?? null;
$emailsQueued    = $emailsQueued ?? 0;
$recentDigests   = $recentDigests ?? [];
$baseUrl         = $baseUrl ?? '';
ob_start();
?>

<div class="nt-page-header">
  <h1 class="nt-page-title">Syndication & Digest</h1>
  <p class="nt-page-sub">API access and weekly digest management</p>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     SYNDICATION API
     ═══════════════════════════════════════════════════════════════ -->
<h2 style="font-size:1.1rem;font-weight:700;margin-bottom:16px">Syndication API</h2>

<!-- Summary Cards -->
<div class="dash-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px">
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Articles Available</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($totalArticles) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">With AI Summary</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($withSummary) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Published This Week</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($thisWeek) ?></div>
  </div>
</div>

<!-- API Documentation -->
<div class="dcard" style="padding:20px;margin-bottom:28px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">API Documentation</div>

  <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:16px">
    <tbody>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px;font-weight:600;width:160px;color:var(--muted)">Base URL</td>
        <td style="padding:8px"><code style="background:var(--bg-code,#f5f5f5);padding:2px 6px;border-radius:4px;font-size:.82rem"><?= h($baseUrl) ?>/api/v1/articles</code></td>
      </tr>
    </tbody>
  </table>

  <div style="font-size:.85rem;margin-bottom:12px">
    <strong>Endpoints</strong>
  </div>
  <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:16px">
    <thead>
      <tr style="border-bottom:1px solid var(--border)">
        <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Method</th>
        <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Endpoint</th>
        <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Description</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px"><span style="background:#28a745;color:#fff;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600">GET</span></td>
        <td style="padding:8px"><code style="background:var(--bg-code,#f5f5f5);padding:2px 6px;border-radius:4px;font-size:.82rem">/api/v1/articles</code></td>
        <td style="padding:8px">List articles. Params: <code>page</code>, <code>per_page</code>, <code>category</code>, <code>since</code></td>
      </tr>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px"><span style="background:#28a745;color:#fff;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600">GET</span></td>
        <td style="padding:8px"><code style="background:var(--bg-code,#f5f5f5);padding:2px 6px;border-radius:4px;font-size:.82rem">/api/v1/articles/{slug}</code></td>
        <td style="padding:8px">Get a single article by slug</td>
      </tr>
    </tbody>
  </table>

  <div style="font-size:.85rem;margin-bottom:8px"><strong>Example Request</strong></div>
  <pre style="background:var(--bg-code,#1e1e1e);color:#d4d4d4;padding:14px 16px;border-radius:6px;font-size:.8rem;overflow-x:auto;margin-bottom:16px"><code>curl -s "<?= h($baseUrl) ?>/api/v1/articles?per_page=5&category=politics" | jq .</code></pre>

  <div style="font-size:.85rem;margin-bottom:8px"><strong>Response Format</strong></div>
  <p style="font-size:.84rem;color:var(--muted);margin-bottom:12px">
    Returns JSON with <code>data</code> (array of articles), <code>meta</code> (pagination info: page, per_page, total, last_page), and each article includes: id, title, slug, excerpt, ai_summary, category, author, published_at, image_url, and url.
  </p>

  <div style="background:var(--bg-info,#e8f4fd);border-left:3px solid var(--accent-info,#0066cc);padding:10px 14px;border-radius:4px;font-size:.83rem;color:var(--text)">
    <strong>Rate Limiting:</strong> The API is rate-limited to 60 requests per minute per IP address.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     WEEKLY DIGEST
     ═══════════════════════════════════════════════════════════════ -->
<h2 style="font-size:1.1rem;font-weight:700;margin-bottom:16px">Weekly Digest</h2>

<!-- Summary Cards -->
<div class="dash-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px">
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Active Subscribers</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($subscriberCount) ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Last Digest Sent</div>
    <div style="font-size:1.3rem;font-weight:700;line-height:1"><?= $lastDigest ? date('M j, Y g:ia', strtotime($lastDigest)) : 'Never' ?></div>
  </div>
  <div class="dcard" style="padding:20px">
    <div class="card-label" style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Emails Queued</div>
    <div style="font-size:2rem;font-weight:700;line-height:1"><?= number_format($emailsQueued) ?></div>
  </div>
</div>

<!-- Digest Info -->
<div class="dcard" style="padding:20px;margin-bottom:28px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Digest Configuration</div>
  <table style="width:100%;font-size:.85rem;border-collapse:collapse">
    <tbody>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px;font-weight:600;width:160px;color:var(--muted)">Schedule</td>
        <td style="padding:8px">Every Sunday at 8:00 AM</td>
      </tr>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px;font-weight:600;color:var(--muted)">Content</td>
        <td style="padding:8px">Top 10 articles by views from the past week</td>
      </tr>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px;font-weight:600;color:var(--muted)">Recipients</td>
        <td style="padding:8px"><?= number_format($subscriberCount) ?> confirmed subscriber<?= $subscriberCount !== 1 ? 's' : '' ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Recent Digest History -->
<?php if (!empty($recentDigests)): ?>
<div class="dcard" style="padding:20px;margin-bottom:28px">
  <div class="card-head" style="font-size:.9rem;font-weight:700;margin-bottom:14px">Recent Digest History</div>
  <div style="overflow-x:auto">
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Subject</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Recipient</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Status</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Sent At</th>
          <th style="text-align:left;padding:6px 8px;color:var(--muted);font-weight:600">Queued At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentDigests as $d): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px;font-weight:500"><?= h(mb_substr($d['subject'] ?? '', 0, 60)) ?></td>
          <td style="padding:8px"><?= h($d['to_email'] ?? '') ?></td>
          <td style="padding:8px">
            <?php
              $st = $d['status'] ?? 'unknown';
              $colors = ['sent' => '#28a745', 'pending' => '#ffc107', 'failed' => '#dc3545'];
              $bg = $colors[$st] ?? '#6c757d';
            ?>
            <span style="background:<?= $bg ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600"><?= h(ucfirst($st)) ?></span>
          </td>
          <td style="padding:8px"><?= $d['sent_at'] ? date('M j, Y g:ia', strtotime($d['sent_at'])) : '-' ?></td>
          <td style="padding:8px"><?= $d['created_at'] ? date('M j, Y g:ia', strtotime($d['created_at'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/layout.php';

<?php
use App\Services\Auth;
use App\Services\DB;

$user = Auth::user();

$pageTitle = 'Articles - ' . site_name();
$activeNav = 'articles';

$baseQuery = [];
if (($q ?? '') !== '') $baseQuery['q'] = $q;
if (($status ?? '') !== '') $baseQuery['status'] = $status;
if (($category ?? '') !== '') $baseQuery['category'] = $category;

function pageUrl($p, $baseQuery) {
  $baseQuery['page'] = $p;
  return '/admin/articles?' . http_build_query($baseQuery);
}

ob_start();
?>
<div class="top" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h1 style="margin:0 0 6px;">Articles</h1>
    <div class="muted" style="font-size:14px">Manage all stories — full CRUD, filters, pagination.</div>
  </div>
  <a class="btn" href="/admin/articles/create">+ New Article</a>
</div>

<?php if (!empty($flash_success)): ?>
  <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<form class="card" method="GET" action="/admin/articles" style="margin:16px 0;padding:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;background:#fafafa">
  <input name="q" value="<?= h($q ?? '') ?>" placeholder="Search title or slug…" style="flex:1;min-width:220px;padding:12px;border:1px solid #e2e2e2;border-radius:12px">
  <select name="status" style="min-width:140px;padding:12px;border:1px solid #e2e2e2;border-radius:12px">
    <option value="">All statuses</option>
    <?php foreach (['draft','pending_review','published','scheduled'] as $s): ?>
      <option value="<?= h($s) ?>" <?= (($status ?? '') === $s) ? 'selected' : '' ?>><?= $s === 'pending_review' ? 'Pending Review' : ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category" style="min-width:180px;padding:12px;border:1px solid #e2e2e2;border-radius:12px">
    <option value="">All categories</option>
    <?php foreach (($categories ?? []) as $c): ?>
      <option value="<?= h($c['slug']) ?>" <?= (($category ?? '') === ($c['slug'] ?? '')) ? 'selected' : '' ?>>
        <?= h($c['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
  <a class="btn light" href="/admin/articles">Reset</a>
</form>

<div class="card" style="overflow:hidden;border-radius:14px">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#f8f8f8">
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Title</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Status</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Category</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Author</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Published</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Slug</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:right;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($articles)): ?>
        <tr><td colspan="7" class="muted" style="padding:24px;text-align:center;font-size:15px">No articles match your filters.</td></tr>
      <?php else: ?>
        <?php foreach ($articles as $a): ?>
          <tr style="transition:background .15s">
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0"><strong><?= h($a['title']) ?></strong></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0">
              <?php
                $badgeColors = match($a['status']) {
                    'published' => 'background:#e6ffe6;color:#006400',
                    'draft'     => 'background:#fff3cd;color:#856404',
                    'scheduled' => 'background:#e3f2fd;color:#1565c0',
                    'pending_review' => 'background:#f3e5f5;color:#7b1fa2',
                    default     => 'background:#f8d7da;color:#721c24',
                };
                $badgeLabel = $a['status'] === 'pending_review' ? 'Pending Review' : ucfirst($a['status']);
                if ($a['status'] === 'scheduled' && !empty($a['published_at'])) {
                    $badgeLabel = '⏰ ' . date('M j, g:i A', strtotime($a['published_at']));
                }
              ?>
              <span style="display:inline-block;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:500;<?= $badgeColors ?>">
                <?= h($badgeLabel) ?>
              </span>
            </td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0"><?= h($a['category']) ?></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0"><?= h($a['author']) ?></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0" class="muted"><?= h($a['published_at'] ?? '—') ?></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0" class="muted"><code style="background:#f6f6f6;padding:2px 6px;border-radius:4px"><?= h($a['slug']) ?></code></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0;text-align:right">
              <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                <a class="btn light" href="/admin/articles/<?= h($a['id']) ?>/edit" style="padding:8px 12px">Edit</a>
                <form method="POST" action="/admin/articles/<?= h($a['id']) ?>/toggle-breaking" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <?php $isBrk = !empty($a['is_breaking_manual']); ?>
                  <button class="btn" type="submit" title="<?= $isBrk ? 'Remove from breaking' : 'Mark as breaking news' ?>" style="padding:8px 12px;<?= $isBrk ? 'background:#cc0000;color:#fff;border:1px solid #cc0000' : 'background:#fff;color:#cc0000;border:1px solid #cc0000' ?>">
                    <?= $isBrk ? '🔴 Breaking' : '⚡ Break' ?>
                  </button>
                </form>
                <form method="POST" action="/admin/articles/<?= h($a['id']) ?>/delete" data-confirm="Move this article to archive?" data-confirm-title="Archive Article" data-confirm-level="warn" data-confirm-ok="Archive" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button class="btn" type="submit" style="padding:8px 12px;background:#fff3cd;color:#856404;border:1px solid #e2c97e">Archive</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div style="display:flex;gap:12px;align-items:center;justify-content:flex-end;margin-top:20px">
  <span class="muted" style="font-size:14px">Page <?= (int)($page ?? 1) ?> of <?= (int)($totalPages ?? 1) ?> (<?= number_format($total ?? 0) ?> total)</span>
  <a class="btn light" href="<?= h(pageUrl(1, $baseQuery)) ?>">First</a>
  <a class="btn light" href="<?= h(pageUrl(max(1,(int)($page ?? 1)-1), $baseQuery)) ?>">Prev</a>
  <a class="btn light" href="<?= h(pageUrl(min((int)($totalPages ?? 1),(int)($page ?? 1)+1), $baseQuery)) ?>">Next</a>
  <a class="btn light" href="<?= h(pageUrl((int)($totalPages ?? 1), $baseQuery)) ?>">Last</a>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
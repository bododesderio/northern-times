<?php
use App\Services\Auth;

$user = Auth::user();

$pageTitle = 'Archive - ' . site_name();
$activeNav = 'archive';

$baseQuery = [];
if (($q ?? '') !== '') $baseQuery['q'] = $q;

function archivePageUrl($p, $baseQuery) {
  $baseQuery['page'] = $p;
  return '/admin/articles/archive?' . http_build_query($baseQuery);
}

ob_start();
?>
<div class="top" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h1 style="margin:0 0 6px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom;margin-right:6px"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      Article Archive
    </h1>
    <div class="muted" style="font-size:14px">Archived articles can be restored to draft, edited, or permanently deleted.</div>
  </div>
  <a class="btn light" href="/admin/articles">&larr; Back to Articles</a>
</div>

<?php if (!empty($flash_success)): ?>
  <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Search -->
<form class="card" method="GET" action="/admin/articles/archive" style="margin:16px 0;padding:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;background:#fafafa">
  <input name="q" value="<?= h($q ?? '') ?>" placeholder="Search archived articles…" style="flex:1;min-width:220px;padding:12px;border:1px solid #e2e2e2;border-radius:12px">
  <button class="btn" type="submit">Search</button>
  <a class="btn light" href="/admin/articles/archive">Reset</a>
</form>

<!-- Bulk Actions -->
<form id="bulkForm" method="POST" action="/admin/articles/archive/bulk">
  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
  <input type="hidden" name="action" id="bulkAction" value="">

  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <label style="font-size:14px;color:#666;display:flex;align-items:center;gap:6px;cursor:pointer">
      <input type="checkbox" id="selectAll" style="width:16px;height:16px"> Select All
    </label>
    <button type="button" onclick="submitBulk('restore')" class="btn light" style="padding:8px 14px;font-size:13px" disabled id="btnBulkRestore">Restore Selected</button>
    <button type="button" onclick="submitBulk('delete')" class="btn danger" style="padding:8px 14px;font-size:13px" disabled id="btnBulkDelete">Delete Selected Permanently</button>
    <span id="selectedCount" class="muted" style="font-size:13px"></span>
  </div>

<div class="card" style="overflow:hidden;border-radius:14px">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#f8f8f8">
        <th style="padding:14px 12px;border-bottom:2px solid #e2e2e2;text-align:center;width:40px"></th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Title</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Category</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Author</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:left;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Date</th>
        <th style="padding:14px 16px;border-bottom:2px solid #e2e2e2;text-align:right;font-family:Arial,sans-serif;font-size:13px;font-weight:600;text-transform:uppercase">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($articles)): ?>
        <tr><td colspan="6" class="muted" style="padding:40px;text-align:center;font-size:15px">
          <div style="opacity:0.4;margin-bottom:8px">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
          </div>
          No archived articles yet.
        </td></tr>
      <?php else: ?>
        <?php foreach ($articles as $a): ?>
          <tr style="transition:background .15s" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background=''">
            <td style="padding:14px 12px;border-bottom:1px solid #f0f0f0;text-align:center">
              <input type="checkbox" name="ids[]" value="<?= h($a['id']) ?>" class="rowCheck" style="width:16px;height:16px">
            </td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0">
              <strong><?= h($a['title']) ?></strong>
              <div class="muted" style="font-size:11px;margin-top:2px"><code style="background:#f6f6f6;padding:1px 4px;border-radius:3px"><?= h($a['slug']) ?></code></div>
            </td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0"><?= h($a['category'] ?? '') ?></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0"><?= h($a['author'] ?? '') ?></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0" class="muted"><?= !empty($a['published_at']) ? date('M j, Y', strtotime($a['published_at'])) : '—' ?></td>
            <td style="padding:14px 16px;border-bottom:1px solid #f0f0f0;text-align:right">
              <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                <form method="POST" action="/admin/articles/<?= h($a['id']) ?>/restore" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button class="btn" type="submit" style="padding:7px 12px;font-size:13px;background:#e6ffe6;color:#006400;border:1px solid #b7e4b7">Restore</button>
                </form>
                <a class="btn light" href="/admin/articles/<?= h($a['id']) ?>/edit" style="padding:7px 12px;font-size:13px">Edit</a>
                <form method="POST" action="/admin/articles/<?= h($a['id']) ?>/permanent-delete" data-confirm="PERMANENTLY delete this article? This cannot be undone." data-confirm-title="Permanent Delete" data-confirm-level="danger" data-confirm-ok="Delete Forever" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button class="btn danger" type="submit" style="padding:7px 12px;font-size:13px">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</form>

<?php if (($totalPages ?? 1) > 1): ?>
<div style="display:flex;gap:12px;align-items:center;justify-content:flex-end;margin-top:20px">
  <span class="muted" style="font-size:14px">Page <?= (int)($page ?? 1) ?> of <?= (int)($totalPages ?? 1) ?></span>
  <a class="btn light" href="<?= h(archivePageUrl(1, $baseQuery)) ?>">First</a>
  <a class="btn light" href="<?= h(archivePageUrl(max(1,(int)($page ?? 1)-1), $baseQuery)) ?>">Prev</a>
  <a class="btn light" href="<?= h(archivePageUrl(min((int)($totalPages ?? 1),(int)($page ?? 1)+1), $baseQuery)) ?>">Next</a>
  <a class="btn light" href="<?= h(archivePageUrl((int)($totalPages ?? 1), $baseQuery)) ?>">Last</a>
</div>
<?php endif; ?>

<script>
var selectAll = document.getElementById('selectAll');
var checks = document.querySelectorAll('.rowCheck');
var btnRestore = document.getElementById('btnBulkRestore');
var btnDelete = document.getElementById('btnBulkDelete');
var countSpan = document.getElementById('selectedCount');
function updateBulk() {
  var n = document.querySelectorAll('.rowCheck:checked').length;
  btnRestore.disabled = n === 0;
  btnDelete.disabled = n === 0;
  countSpan.textContent = n > 0 ? n + ' selected' : '';
}
if (selectAll) selectAll.addEventListener('change', function() { checks.forEach(function(c){ c.checked = selectAll.checked; }); updateBulk(); });
checks.forEach(function(c) { c.addEventListener('change', updateBulk); });
function submitBulk(action) {
  var n = document.querySelectorAll('.rowCheck:checked').length;
  if (n === 0) return;
  if (!confirm(action === 'delete' ? 'PERMANENTLY delete ' + n + ' article(s)? This cannot be undone.' : 'Restore ' + n + ' article(s) as drafts?')) return;
  document.getElementById('bulkAction').value = action;
  document.getElementById('bulkForm').submit();
}
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
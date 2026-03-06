<?php
declare(strict_types=1);

/** @var array  $pages */
/** @var string $csrf */
/** @var string|null $flash_success */
/** @var string|null $flash_error */

$pageTitle = 'Policy Pages';
$activeNav = 'policies';

ob_start();
?>

<div style="max-width:1060px">

<div class="page-header">
  <div>
    <h1>Policy Pages</h1>
    <div class="sub">Editorial Standards, Corrections, Privacy, Terms — and any custom legal pages.</div>
  </div>
  <div>
    <a class="btn" href="/admin/policies/create">+ New Page</a>
  </div>
</div>

<?php if (!empty($flash_success)): ?>
  <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<form method="POST" action="/admin/policies/bulk" id="bulkForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="bulk_action" id="bulkAction" value="">

  <!-- Bulk action bar -->
  <div id="bulkBar" style="display:none;align-items:center;gap:10px;margin-bottom:12px;padding:10px 14px;background:var(--accent);border-radius:10px;color:#fff">
    <span id="bulkCount" style="font-weight:700;font-size:14px">0 selected</span>
    <span style="flex:1"></span>
    <button type="button" onclick="doBulk('publish')"   class="bulk-btn">Publish</button>
    <button type="button" onclick="doBulk('unpublish')" class="bulk-btn">Unpublish</button>
    <button type="button" onclick="doBulk('delete')"    class="bulk-btn danger"
            data-confirm="Delete selected policy pages? This cannot be undone."
            data-confirm-title="Bulk Delete" data-confirm-level="danger" data-confirm-ok="Delete">Delete</button>
    <button type="button" onclick="clearAll()" class="bulk-btn" style="opacity:.75">Cancel</button>
  </div>

  <div class="card" style="padding:0;overflow:hidden">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:36px;text-align:center;padding:10px 8px">
            <input type="checkbox" id="selectAll" title="Select all" style="accent-color:var(--accent);cursor:pointer">
          </th>
          <th>Title</th>
          <th>Slug</th>
          <th style="width:80px;text-align:center">Footer</th>
          <th style="width:60px;text-align:center">Order</th>
          <th style="width:120px;text-align:center">Last Updated</th>
          <th style="width:100px;text-align:center">Status</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pages)): ?> 
          <tr>
            <td colspan="8" style="text-align:center;padding:48px 0;color:var(--muted)">
              No policy pages yet. <a href="/admin/policies/create">Create one</a>.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($pages as $p): ?>
            <tr>
              <td style="text-align:center;padding:12px 8px">
                <input type="checkbox" name="ids[]" value="<?= h($p['id']) ?>"
                       class="row-check" style="accent-color:var(--accent);cursor:pointer">
              </td>
              <td>
                <div style="font-weight:600;color:var(--ink)"><?= h($p['title']) ?></div>
                <?php if ($p['is_published']): ?>
                  <div style="margin-top:4px;font-size:12px">
                    <a href="/policy/<?= h($p['slug']) ?>" target="_blank" rel="noopener"
                       style="color:var(--accent)">View live ↗</a>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <code style="background:var(--paper);border:1px solid var(--border);padding:2px 8px;border-radius:6px;font-size:12px"><?= h($p['slug']) ?></code>
              </td>
              <td style="text-align:center">
                <?php if ($p['show_in_footer']): ?>
                  <span style="color:#1e7e34;font-size:18px" title="Shown in footer">✓</span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:16px">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;font-variant-numeric:tabular-nums;color:var(--muted)">
                <?= (int)$p['sort_order'] ?>
              </td>
              <td style="text-align:center;font-size:12px;color:var(--muted);white-space:nowrap">
                <?php
                  $ts = strtotime($p['updated_at'] ?? '');
                  echo $ts ? date('d M Y', $ts) : '—';
                ?>
                <div style="font-size:11px;opacity:.7"><?= $ts ? date('H:i', $ts) : '' ?></div>
              </td>
              <td style="text-align:center">
                <span class="status-badge <?= $p['is_published'] ? 'published' : 'draft' ?>">
                  <?= $p['is_published'] ? 'Published' : 'Draft' ?>
                </span>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">
                  <a class="btn light sm" href="/admin/policies/<?= h($p['id']) ?>/edit">Edit</a>
                  <form method="POST" action="/admin/policies/<?= h($p['id']) ?>/toggle" style="margin:0">
                    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                    <button class="btn light sm" type="submit">
                      <?= $p['is_published'] ? 'Unpublish' : 'Publish' ?>
                    </button>
                  </form>
                  <form method="POST" action="/admin/policies/<?= h($p['id']) ?>/delete"
                        data-confirm="Delete «<?= h(addslashes($p['title'])) ?>»? This cannot be undone."
                        data-confirm-title="Delete Policy" data-confirm-level="danger" data-confirm-ok="Delete"
                        style="margin:0">
                    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                    <button class="btn danger sm" type="submit">Delete</button>
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

<div style="margin-top:12px;font-size:13px;color:var(--muted)">
  Published pages with "Footer" enabled appear automatically in the site footer.
</div>

</div>

<style>
.admin-table { width:100%;border-collapse:collapse;font-size:14px }
.admin-table th { text-align:left;padding:10px 14px;background:var(--paper);border-bottom:1px solid var(--border);color:var(--muted);text-transform:uppercase;font-size:11px;letter-spacing:.05em;font-weight:600 }
.admin-table td { padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle }
.admin-table tr:last-child td { border-bottom:none }
.admin-table tr:hover td { background:var(--paper) }
.admin-table tr.selected td { background:color-mix(in srgb,var(--accent) 6%,transparent) }
.status-badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em }
.status-badge.published { background:#e6f4ea;color:#1e7e34 }
.status-badge.draft { background:var(--paper);color:var(--muted);border:1px solid var(--border) }
.btn.sm { padding:5px 12px;font-size:12px }
.bulk-btn { background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:7px;padding:5px 12px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s }
.bulk-btn:hover { background:rgba(255,255,255,.35) }
.bulk-btn.danger { background:rgba(200,0,0,.35);border-color:rgba(255,100,100,.5) }
.bulk-btn.danger:hover { background:rgba(200,0,0,.55) }
</style>

<script>
(function(){
  var form      = document.getElementById('bulkForm');
  var bar       = document.getElementById('bulkBar');
  var countEl   = document.getElementById('bulkCount');
  var selectAll = document.getElementById('selectAll');
  var checks    = function(){ return document.querySelectorAll('.row-check'); };

  function update() {
    var checked = document.querySelectorAll('.row-check:checked');
    var n = checked.length;
    bar.style.display = n > 0 ? 'flex' : 'none';
    if (n > 0) countEl.textContent = n + ' page' + (n === 1 ? '' : 's') + ' selected';
    checks().forEach(function(cb){ cb.closest('tr').classList.toggle('selected', cb.checked); });
    selectAll.indeterminate = n > 0 && n < checks().length;
    selectAll.checked = n > 0 && n === checks().length;
  }

  selectAll.addEventListener('change', function(){
    checks().forEach(function(cb){ cb.checked = selectAll.checked; });
    update();
  });
  document.addEventListener('change', function(e){
    if (e.target.classList.contains('row-check')) update();
  });

  window.doBulk = function(action) {
    document.getElementById('bulkAction').value = action;
    form.submit();
  };
  window.clearAll = function(){
    checks().forEach(function(cb){ cb.checked = false; });
    selectAll.checked = false;
    update();
  };
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
<?php
$pageTitle = 'Comments';
$activeNav = 'comments';
ob_start();
?>

<?php if (!empty($flash_success)): ?><div class="alert alert-success"><?= h($flash_success) ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="alert alert-error"><?= h($flash_error) ?></div><?php endif; ?>

<div class="page-head">
  <div><h1>Comments</h1><p class="muted">Manage reader comments across all articles.</p></div>
  <div class="page-head-stats">
    <span class="ph-stat"><?= $counts['all'] ?? 0 ?> total</span>
    <span class="ph-stat vis"><?= $counts['visible'] ?? 0 ?> visible</span>
    <?php if (($counts['hidden'] ?? 0) > 0): ?><span class="ph-stat hid"><?= $counts['hidden'] ?> hidden</span><?php endif; ?>
    <?php if (($counts['deleted'] ?? 0) > 0): ?><span class="ph-stat del"><?= $counts['deleted'] ?> deleted</span><?php endif; ?>
  </div>
</div>

<!-- Filter Tabs -->
<div class="cm-tabs">
  <?php
    $tabs = [
      'all'     => ['label' => 'All Comments', 'count' => $counts['all'] ?? 0],
      'visible' => ['label' => 'Visible',      'count' => $counts['visible'] ?? 0],
      'hidden'  => ['label' => 'Hidden',        'count' => $counts['hidden'] ?? 0],
      'deleted' => ['label' => 'Deleted',        'count' => $counts['deleted'] ?? 0],
    ];
    foreach ($tabs as $key => $tab):
  ?>
    <a href="/admin/comments<?= $key !== 'all' ? '?filter='.$key : '' ?>" class="cm-tab <?= $filter === $key ? 'active' : '' ?>">
      <?= $tab['label'] ?>
      <span class="cm-tab-count<?= $key === 'hidden' ? ' count-warn' : ($key === 'deleted' ? ' count-del' : '') ?>"><?= $tab['count'] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Search -->
<form class="cm-search" method="GET" action="/admin/comments">
  <?php if ($filter && $filter !== 'all'): ?><input type="hidden" name="filter" value="<?= h($filter) ?>"><?php endif; ?>
  <div class="cm-search-wrap">
    <svg class="cm-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search comments, authors, articles…" class="form-input">
  </div>
  <button type="submit" class="btn sm">Search</button>
  <?php if ($search): ?><a href="/admin/comments<?= $filter && $filter !== 'all' ? '?filter='.h($filter) : '' ?>" class="btn sm light">Clear</a><?php endif; ?>
</form>

<?php if (!empty($comments)): ?>
<form method="POST" action="/admin/comments/bulk" id="bulkForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <!-- Bulk bar -->
  <div class="cm-bulk">
    <label class="cm-check-all"><input type="checkbox" id="checkAll" onchange="document.querySelectorAll('.cm-chk').forEach(c=>c.checked=this.checked)"><span>Select all</span></label>
    <select name="bulk_action" class="form-input cm-bulk-sel">
      <option value="">Bulk action…</option>
      <option value="hide">Hide from readers</option>
      <option value="restore">Restore to visible</option>
      <option value="delete">Soft delete</option>
      <option value="destroy">Permanently delete</option>
    </select>
    <button type="submit" class="btn sm light" onclick="return this.form.bulk_action.value ? confirm('Apply action to selected?') : (alert('Pick an action first'),false)">Apply</button>
    <span class="cm-bulk-count muted"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></span>
  </div>

  <!-- Comment cards -->
  <div class="cm-list">
    <?php foreach ($comments as $i => $c): ?>
      <div class="cm-card cm-st-<?= h($c['status']) ?>" style="animation-delay:<?= $i * 35 ?>ms">
        <div class="cm-card-left">
          <input type="checkbox" name="comment_ids[]" value="<?= h($c['id']) ?>" class="cm-chk">
        </div>

        <div class="cm-card-avatar"><?= strtoupper(mb_substr($c['author_name'],0,1)) ?></div>

        <div class="cm-card-body">
          <div class="cm-card-top">
            <strong><?= h($c['author_name']) ?></strong>
            <span class="cm-card-email"><?= h($c['author_email']) ?></span>
            <span class="cm-status-pill cm-pill-<?= h($c['status']) ?>"><?= ucfirst($c['status']) ?></span>
            <time class="cm-card-time"><?= date('M j, Y · g:ia', strtotime($c['created_at'])) ?></time>
          </div>

          <p class="cm-card-text"><?= nl2br(h($c['content'])) ?></p>

          <div class="cm-card-article">
            on <a href="/article/<?= h($c['article_slug']) ?>" target="_blank"><?= h($c['article_title']) ?></a>
          </div>

          <!-- Actions -->
          <div class="cm-card-actions">
            <?php if ($c['status'] === 'visible'): ?>
              <form method="POST" action="/admin/comments/<?= h($c['id']) ?>/hide" class="inline">
                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="return_filter" value="<?= h($filter) ?>">
                <button class="cm-act cm-act-hide" title="Hide from readers">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                  Hide
                </button>
              </form>
            <?php else: ?>
              <form method="POST" action="/admin/comments/<?= h($c['id']) ?>/show" class="inline">
                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="return_filter" value="<?= h($filter) ?>">
                <button class="cm-act cm-act-show" title="Make visible to readers">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  Restore
                </button>
              </form>
            <?php endif; ?>

            <?php if ($c['status'] !== 'deleted'): ?>
              <form method="POST" action="/admin/comments/<?= h($c['id']) ?>/delete" class="inline">
                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="return_filter" value="<?= h($filter) ?>">
                <button class="cm-act cm-act-del" title="Soft delete (can be restored)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  Delete
                </button>
              </form>
            <?php endif; ?>

            <?php if ($c['status'] === 'deleted'): ?>
              <form method="POST" action="/admin/comments/<?= h($c['id']) ?>/destroy" class="inline" data-confirm="Permanently remove this comment? This cannot be undone." data-confirm-title="Delete Comment" data-confirm-level="danger" data-confirm-ok="Delete">
                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <button class="cm-act cm-act-destroy">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  Remove forever
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</form>

<?php if ($pages > 1): ?>
  <div class="cm-pag">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <?php $qs = []; if ($filter && $filter !== 'all') $qs['filter'] = $filter; if ($search) $qs['q'] = $search; $qs['page'] = $p; ?>
      <a href="/admin/comments?<?= http_build_query($qs) ?>" class="cm-pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<?php else: ?>
  <div class="cm-empty">
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.25;margin-bottom:16px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <?php if ($search || ($filter && $filter !== 'all')): ?>
      <p>No comments match your filters.</p>
      <a href="/admin/comments" class="btn sm light" style="margin-top:10px">Clear filters</a>
    <?php else: ?>
      <p>No comments yet.</p>
      <p class="muted" style="font-size:13px;margin-top:4px">Comments will appear here once readers start engaging.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<style>
.inline { display:inline; }

/* Page head */
.page-head { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
.page-head h1 { font-size:22px; font-weight:800; margin:0 0 4px; }
.page-head p { margin:0; font-size:14px; }
.page-head-stats { display:flex; gap:8px; }
.ph-stat { font-size:12px; padding:4px 12px; border-radius:99px; background:#f0f2f5; color:var(--muted); font-weight:600; }
.ph-stat.vis { background:#ecfdf5; color:#059669; }
.ph-stat.hid { background:#fef3c7; color:#d97706; }
.ph-stat.del { background:#fef2f2; color:#dc2626; }

/* Tabs */
.cm-tabs { display:flex; gap:2px; margin-bottom:20px; border-bottom:2px solid var(--border); overflow-x:auto; }
.cm-tab { padding:10px 18px; font-size:13px; font-weight:600; color:var(--muted); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s; display:flex; align-items:center; gap:6px; white-space:nowrap; }
.cm-tab:hover { color:var(--ink); }
.cm-tab.active { color:var(--ink); border-bottom-color:var(--accent); }
.cm-tab-count { font-size:11px; background:#f0f2f5; padding:1px 7px; border-radius:99px; font-weight:700; }
.count-warn { background:#fef3c7; color:#d97706; }
.count-del { background:#fef2f2; color:#dc2626; }

/* Search */
.cm-search { display:flex; gap:8px; margin-bottom:16px; }
.cm-search-wrap { flex:1; position:relative; }
.cm-search-wrap .form-input { padding-left:38px; width:100%; }
.cm-search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; }

/* Bulk */
.cm-bulk { display:flex; align-items:center; gap:12px; margin-bottom:14px; padding:10px 16px; background:color-mix(in srgb,var(--ink) 3%,transparent); border-radius:10px; flex-wrap:wrap; }
.cm-check-all { display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; }
.cm-bulk-sel { width:auto; padding:6px 12px; font-size:13px; }
.cm-bulk-count { margin-left:auto; font-size:13px; }

/* Comment cards */
.cm-list { display:flex; flex-direction:column; gap:10px; }
.cm-card {
  display:flex; gap:14px; padding:18px 20px;
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius,14px); box-shadow:var(--adm-shadow);
  border-left:4px solid #22c55e;
  opacity:0; transform:translateY(10px);
  animation:cmIn .3s ease forwards;
  transition:box-shadow .18s, border-color .18s;
}
.cm-card:hover { box-shadow:var(--adm-shadow-lg); }
@keyframes cmIn { to { opacity:1; transform:translateY(0); } }

.cm-st-visible { border-left-color:#22c55e; }
.cm-st-hidden  { border-left-color:#f59e0b; background:color-mix(in srgb,#fef3c7 20%,var(--surface)); }
.cm-st-deleted { border-left-color:#ef4444; background:color-mix(in srgb,#fef2f2 30%,var(--surface)); opacity:.85; }

.cm-card-left { display:flex; align-items:flex-start; padding-top:2px; }
.cm-chk { width:16px; height:16px; cursor:pointer; accent-color:var(--accent); }
.cm-card-avatar { width:40px; height:40px; border-radius:50%; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; flex-shrink:0; }
.cm-card-body { flex:1; min-width:0; }
.cm-card-top { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
.cm-card-top strong { font-size:14px; }
.cm-card-email { font-size:12px; color:var(--muted); }
.cm-card-time { font-size:12px; color:var(--muted); margin-left:auto; }
.cm-status-pill { font-size:10px; font-weight:700; padding:2px 9px; border-radius:99px; text-transform:uppercase; letter-spacing:.03em; }
.cm-pill-visible { background:#ecfdf5; color:#059669; }
.cm-pill-hidden  { background:#fef3c7; color:#d97706; }
.cm-pill-deleted { background:#fef2f2; color:#dc2626; }

.cm-card-text { font-size:14px; line-height:1.65; color:var(--ink); margin:0 0 8px; word-break:break-word; }
.cm-card-article { font-size:12px; color:var(--muted); margin-bottom:12px; }
.cm-card-article a { color:var(--accent); font-weight:600; text-decoration:none; }
.cm-card-article a:hover { text-decoration:underline; }

.cm-card-actions { display:flex; gap:6px; flex-wrap:wrap; }
.cm-act { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:8px; border:1px solid var(--border); background:var(--surface); font-size:12px; font-weight:600; color:var(--muted); cursor:pointer; transition:all .15s; }
.cm-act:hover { color:var(--ink); border-color:#aaa; }
.cm-act-hide:hover    { background:#fef3c7; color:#d97706; border-color:#fcd34d; }
.cm-act-show:hover    { background:#ecfdf5; color:#059669; border-color:#86efac; }
.cm-act-del:hover     { background:#fef2f2; color:#dc2626; border-color:#fca5a5; }
.cm-act-destroy:hover { background:#dc2626; color:#fff; border-color:#dc2626; }

/* Pagination */
.cm-pag { display:flex; gap:4px; justify-content:center; margin-top:24px; }
.cm-pag-btn { padding:6px 12px; border-radius:8px; font-size:13px; font-weight:600; color:var(--muted); border:1px solid var(--border); text-decoration:none; transition:all .15s; }
.cm-pag-btn:hover { border-color:var(--accent); color:var(--accent); }
.cm-pag-btn.active { background:var(--accent); color:var(--accent-text,#fff); border-color:var(--accent); }

/* Empty */
.cm-empty { text-align:center; padding:60px 20px; color:var(--muted); }
.cm-empty p { margin:0 0 4px; font-size:15px; }

/* Alerts */
.alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; font-weight:500; animation:cmIn .2s ease both; }
.alert-success { background:#ecfdf5; color:#059669; border:1px solid #86efac; }
.alert-error { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }

@media (max-width:768px) {
  .cm-card { flex-wrap:wrap; padding:14px; }
  .cm-card-top { flex-direction:column; align-items:flex-start; gap:4px; }
  .cm-card-time { margin-left:0; }
  .page-head { flex-direction:column; align-items:flex-start; }
}
</style>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layout.php'; ?>
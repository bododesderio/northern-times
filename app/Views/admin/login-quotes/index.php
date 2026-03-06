<?php
$quotes    = $quotes ?? [];
$activeNav = 'login-quotes';
$pageTitle = 'Login Page Quotes';
$editId    = $_GET['edit'] ?? null;
ob_start();
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:28px">
  <div>
    <h1>Login Page Quotes</h1>
    <div class="sub">These rotate on the admin login screen every few seconds. Active quotes only are shown.</div>
  </div>
  <span class="lq-count-badge"><?= count(array_filter($quotes, fn($q) => $q['is_active'])) ?> active</span>
</div>

<?php if (!empty($flash_success)): ?><div class="flash ok"><?= h($flash_success) ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="flash bad"><?= h($flash_error) ?></div><?php endif; ?>

<!-- ── Add new quote ──────────────────────────────────────────────── -->
<div class="card lq-add-card" id="addCard">
  <div class="lq-add-header" onclick="toggleAddForm()">
    <span style="font-weight:700;font-size:14px">+ Add New Quote</span>
    <svg id="addArrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition:transform .2s"><polyline points="6 9 12 15 18 9"/></svg>
  </div>
  <div id="addForm" style="display:none;padding:16px;border-top:1px solid var(--border,#e2e2e2)">
    <form method="POST" action="/admin/login-quotes">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <div class="lq-form-row">
        <div class="lq-form-field lq-field-quote">
          <label class="form-label">Quote <span class="req">*</span></label>
          <textarea name="quote" rows="3" required class="form-control" placeholder="Enter the quote text…"></textarea>
        </div>
        <div class="lq-form-field lq-field-meta">
          <div>
            <label class="form-label">Attribution</label>
            <input type="text" name="author" class="form-control" placeholder="e.g. Walter Cronkite">
          </div>
          <div>
            <label class="form-label">Sort order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= count($quotes) + 1 ?>" min="0" style="width:100px">
          </div>
          <div style="display:flex;align-items:flex-end">
            <button type="submit" class="btn" style="white-space:nowrap">Add Quote</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ── Quote list ─────────────────────────────────────────────────── -->
<?php if (empty($quotes)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <p class="muted">No quotes yet. Add your first one above.</p>
  </div>
<?php else: ?>
<div class="card" style="overflow:hidden">
  <?php foreach ($quotes as $i => $q):
    $isEdit   = ($editId === $q['id']);
    $isActive = (bool)$q['is_active'];
  ?>
  <div class="lq-row <?= $isActive ? '' : 'lq-row--inactive' ?>" id="qrow-<?= h($q['id']) ?>">

    <?php if ($isEdit): ?>
    <!-- ── Inline edit form ── -->
    <form method="POST" action="/admin/login-quotes/<?= h($q['id']) ?>" class="lq-edit-form">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <div class="lq-form-row">
        <div class="lq-form-field lq-field-quote">
          <label class="form-label">Quote</label>
          <textarea name="quote" rows="3" required class="form-control"><?= h($q['quote']) ?></textarea>
        </div>
        <div class="lq-form-field lq-field-meta">
          <div>
            <label class="form-label">Attribution</label>
            <input type="text" name="author" class="form-control" value="<?= h($q['author']) ?>">
          </div>
          <div>
            <label class="form-label">Sort</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int)$q['sort_order'] ?>" min="0" style="width:80px">
          </div>
          <div style="display:flex;gap:8px;align-items:flex-end">
            <button type="submit" class="btn sm">Save</button>
            <a href="/admin/login-quotes" class="btn sm" style="background:#eee;color:#333">Cancel</a>
          </div>
        </div>
      </div>
    </form>

    <?php else: ?>
    <!-- ── Display row ── -->
    <div class="lq-row-body">
      <div class="lq-row-num"><?= $i + 1 ?></div>
      <div class="lq-row-text">
        <p class="lq-quote-text">&ldquo;<?= h($q['quote']) ?>&rdquo;</p>
        <?php if (!empty($q['author'])): ?>
          <cite class="lq-quote-cite">— <?= h($q['author']) ?></cite>
        <?php endif; ?>
      </div>
      <div class="lq-row-actions">
        <!-- Toggle active -->
        <button class="lq-toggle-btn <?= $isActive ? 'lq-toggle--on' : 'lq-toggle--off' ?>"
                id="toggle-<?= h($q['id']) ?>"
                onclick="toggleQuote('<?= h($q['id']) ?>', '<?= h($csrf) ?>')"
                title="<?= $isActive ? 'Disable' : 'Enable' ?>">
          <span class="lq-toggle-dot"></span>
        </button>
        <a href="/admin/login-quotes?edit=<?= h($q['id']) ?>#qrow-<?= h($q['id']) ?>" class="btn light sm">Edit</a>
        <form method="POST" action="/admin/login-quotes/<?= h($q['id']) ?>/delete" style="display:inline"
              data-confirm="Delete this quote permanently?" data-confirm-title="Delete Quote" data-confirm-level="danger" data-confirm-ok="Delete">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <button type="submit" class="btn danger sm">Delete</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Preview ────────────────────────────────────────────────────── -->
<?php $activeQuotes = array_filter($quotes, fn($q) => $q['is_active']); ?>
<?php if (!empty($activeQuotes)): ?>
<div class="card lq-preview-card">
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted,#999);margin-bottom:14px">Live Preview</div>
  <div class="lq-preview-panel">
    <blockquote class="lq-preview-quote" id="previewQuote">
      <?php $first = array_values($activeQuotes)[0]; ?>
      &ldquo;<?= h($first['quote']) ?>&rdquo;
    </blockquote>
    <cite class="lq-preview-cite" id="previewCite">— <?= h($first['author'] ?: 'Unknown') ?></cite>
  </div>
  <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
    <button class="btn sm" style="background:#eee;color:#333" onclick="previewPrev()">← Prev</button>
    <button class="btn sm" style="background:#eee;color:#333" onclick="previewNext()">Next →</button>
    <span style="font-size:12px;color:var(--muted)" id="previewCount">1 / <?= count($activeQuotes) ?></span>
  </div>
</div>
<?php endif; ?>

<style>
.lq-count-badge {
  display: inline-block; padding: 6px 14px;
  background: var(--accent, #cc0000); color: #fff;
  border-radius: 20px; font-size: 12px; font-weight: 700;
  font-family: var(--ui, system-ui, sans-serif);
}
.lq-add-card { padding: 0; overflow: hidden; }
.lq-add-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 18px; cursor: pointer;
  font-family: var(--ui, system-ui, sans-serif);
  transition: background .15s;
}
.lq-add-header:hover { background: var(--paper, #f8f8f8); }
.lq-form-row {
  display: grid; grid-template-columns: 1fr 320px; gap: 16px;
}
.lq-form-field { display: flex; flex-direction: column; gap: 8px; }
.lq-field-meta { display: flex; flex-direction: column; gap: 10px; }

/* Quote rows */
.lq-row {
  border-bottom: 1px solid var(--border, #e8e8e8);
  transition: background .1s;
}
.lq-row:last-child { border-bottom: none; }
.lq-row--inactive { opacity: .5; }
.lq-row-body {
  display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px;
}
.lq-row-num {
  flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%;
  background: var(--paper, #f4f4f4); border: 1px solid var(--border, #e2e2e2);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: var(--muted, #999);
  font-family: var(--ui, system-ui, sans-serif); margin-top: 2px;
}
.lq-row-text { flex: 1; min-width: 0; }
.lq-quote-text {
  font-family: Georgia, serif; font-size: 15px; line-height: 1.55;
  color: var(--ink, #1a1a1a); margin: 0 0 5px;
}
.lq-quote-cite {
  font-size: 12px; font-style: normal; font-weight: 600;
  color: var(--muted, #888); font-family: var(--ui, system-ui, sans-serif);
  letter-spacing: .04em;
}
.lq-row-actions {
  display: flex; align-items: center; gap: 8px; flex-shrink: 0;
}
.lq-edit-form { padding: 16px 18px; background: var(--paper, #fafafa); }

/* Toggle switch */
.lq-toggle-btn {
  width: 36px; height: 20px; border-radius: 10px; border: none;
  cursor: pointer; padding: 2px; position: relative;
  transition: background .2s; flex-shrink: 0;
}
.lq-toggle--on  { background: #22c55e; }
.lq-toggle--off { background: var(--border, #d1d5db); }
.lq-toggle-dot {
  display: block; width: 16px; height: 16px; border-radius: 50%;
  background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
  transition: transform .2s; position: absolute; top: 2px; left: 2px;
}
.lq-toggle--on  .lq-toggle-dot { transform: translateX(16px); }

/* Preview panel */
.lq-preview-card { padding: 20px; margin-top: 20px; }
.lq-preview-panel {
  background: #0d0d0d; border-radius: 10px;
  padding: 24px 28px; min-height: 100px;
}
.lq-preview-quote {
  font-family: Georgia, serif; font-size: 16px; font-style: italic;
  color: rgba(255,255,255,.65); line-height: 1.65; margin: 0 0 10px;
  transition: opacity .4s ease;
}
.lq-preview-cite {
  font-family: var(--ui, system-ui, sans-serif); font-size: 12px;
  font-weight: 600; letter-spacing: .06em;
  color: rgba(255,255,255,.3); font-style: normal;
  transition: opacity .4s ease;
}

/* Dark admin */
html[data-adm-theme="dark"] .lq-add-header:hover { background: rgba(255,255,255,.04); }
html[data-adm-theme="dark"] .lq-row-num { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.1); }
html[data-adm-theme="dark"] .lq-row { border-color: rgba(255,255,255,.08); }
html[data-adm-theme="dark"] .lq-edit-form { background: rgba(255,255,255,.03); }

@media (max-width: 700px) {
  .lq-form-row { grid-template-columns: 1fr; }
  .lq-row-body { flex-wrap: wrap; }
  .lq-row-actions { width: 100%; justify-content: flex-end; }
}
</style>

<script>
function toggleAddForm() {
  var f = document.getElementById('addForm');
  var a = document.getElementById('addArrow');
  var open = f.style.display !== 'none';
  f.style.display = open ? 'none' : 'block';
  a.style.transform = open ? '' : 'rotate(180deg)';
}

function toggleQuote(id, csrf) {
  var btn = document.getElementById('toggle-' + id);
  var row = document.getElementById('qrow-' + id);
  var fd  = new FormData();
  fd.append('_csrf', csrf);
  fetch('/admin/login-quotes/' + id + '/toggle', { method: 'POST', credentials: 'same-origin', body: fd })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      if (d.active) {
        btn.classList.remove('lq-toggle--off');
        btn.classList.add('lq-toggle--on');
        btn.title = 'Disable';
        if (row) row.classList.remove('lq-row--inactive');
      } else {
        btn.classList.remove('lq-toggle--on');
        btn.classList.add('lq-toggle--off');
        btn.title = 'Enable';
        if (row) row.classList.add('lq-row--inactive');
      }
    });
}

// Preview cycle
(function() {
  var quotes = <?php echo json_encode(array_values(array_map(fn($q) => ['q' => $q['quote'], 'a' => $q['author'] ?: 'Unknown'], $activeQuotes ?? []))); ?>;
  if (!quotes.length) return;
  var idx = 0;
  var qEl = document.getElementById('previewQuote');
  var cEl = document.getElementById('previewCite');
  var nEl = document.getElementById('previewCount');

  function show(i) {
    idx = ((i % quotes.length) + quotes.length) % quotes.length;
    if (qEl) { qEl.style.opacity = '0'; cEl.style.opacity = '0'; }
    setTimeout(function() {
      if (qEl) qEl.innerHTML = '\u201c' + quotes[idx].q + '\u201d';
      if (cEl) cEl.textContent = '\u2014 ' + quotes[idx].a;
      if (nEl) nEl.textContent = (idx + 1) + ' / ' + quotes.length;
      if (qEl) { qEl.style.opacity = '1'; cEl.style.opacity = '1'; }
    }, 400);
  }
  window.previewNext = function() { show(idx + 1); };
  window.previewPrev = function() { show(idx - 1); };
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
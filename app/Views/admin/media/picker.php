<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Pick Media</title>
  <style>
    :root {
      --ink: #121212; --paper: #fdfdfd; --surface: #fff;
      --border: #e2e2e2; --muted: #666; --accent: #c00;
      --radius: 12px; --ui: system-ui, -apple-system, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: var(--ui); background: var(--surface); color: var(--ink); overflow: hidden; }

    /* ── Layout shell ─────────────────────────────────────── */
    .picker-shell {
      display: flex; flex-direction: column; height: 100vh;
    }

    /* ── Toolbar (sticky top) ─────────────────────────────── */
    .toolbar {
      display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
      padding: 12px 16px; border-bottom: 1px solid var(--border);
      background: var(--surface); flex-shrink: 0;
    }
    .toolbar-title { font-size: 15px; font-weight: 700; flex-shrink: 0; }
    .toolbar input, .toolbar select {
      padding: 8px 12px; border: 1px solid var(--border);
      border-radius: 8px; font: inherit; font-size: 13px;
    }
    .toolbar input { flex: 1; min-width: 140px; }
    .btn {
      padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border);
      background: var(--surface); cursor: pointer; font: inherit; font-size: 13px;
      font-weight: 600; transition: all .15s ease; white-space: nowrap;
    }
    .btn:hover { background: var(--paper); }
    .btn.primary { background: var(--ink); color: #fff; border-color: var(--ink); }
    .btn.primary:hover { opacity: .88; }
    .btn:disabled { opacity: .45; cursor: not-allowed; }

    /* ── Scrollable image grid ────────────────────────────── */
    .grid-wrap {
      flex: 1; overflow-y: auto; padding: 14px;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 10px;
    }
    .item {
      border: 2px solid var(--border); border-radius: 10px;
      overflow: hidden; cursor: pointer; background: var(--surface);
      transition: border-color .15s, box-shadow .15s;
      user-select: none;
    }
    .item:hover { border-color: #aaa; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    .item.selected { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(204,0,0,.15); }
    .item img {
      width: 100%; height: 110px; object-fit: cover; display: block;
      background: #f0f0f0;
    }
    .item img.broken { object-fit: contain; padding: 16px; filter: grayscale(1) opacity(.3); }
    .item-label {
      padding: 5px 7px; font-size: 11px; color: var(--muted);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      border-top: 1px solid var(--border);
    }
    .empty {
      grid-column: 1 / -1; text-align: center; padding: 60px 20px;
      color: var(--muted); font-size: 14px;
    }

    /* ── Insert options panel (insert mode only) ──────────── */
    .insert-panel {
      display: none; flex-direction: column; gap: 10px;
      padding: 12px 16px; border-top: 1px solid var(--border);
      background: #fafafa; flex-shrink: 0;
    }
    .insert-panel.visible { display: flex; }
    .insert-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .insert-panel label { font-size: 12px; font-weight: 600; color: #555; flex-shrink: 0; }
    .insert-panel input[type="text"],
    .insert-panel select {
      padding: 7px 10px; border: 1px solid var(--border);
      border-radius: 7px; font: inherit; font-size: 13px;
    }
    .insert-panel input[type="text"] { flex: 1; min-width: 140px; }

    /* Width slider */
    .slider-wrap { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 180px; }
    .slider-wrap input[type="range"] {
      flex: 1; -webkit-appearance: none; height: 5px;
      border-radius: 3px; background: var(--border); border: none; padding: 0;
    }
    .slider-wrap input[type="range"]::-webkit-slider-thumb {
      -webkit-appearance: none; width: 18px; height: 18px;
      border-radius: 50%; background: var(--ink); cursor: pointer;
    }
    .slider-val { font-size: 13px; font-weight: 700; width: 38px; text-align: right; }

    /* Caption preview */
    .caption-preview {
      font-size: 12px; color: #888; font-style: italic;
      padding: 4px 0 0; min-height: 18px;
    }

    /* ── Footer (sticky bottom) ───────────────────────────── */
    .footer {
      display: flex; gap: 10px; align-items: center; justify-content: space-between;
      padding: 12px 16px; border-top: 1px solid var(--border);
      background: var(--surface); flex-shrink: 0;
    }
    .footer-info { font-size: 13px; color: var(--muted); }

    /* ── Toast ────────────────────────────────────────────── */
    .toast {
      position: fixed; top: 14px; right: 14px; z-index: 9999;
      background: var(--ink); color: #fff;
      padding: 9px 16px; border-radius: 10px;
      font-size: 13px; font-weight: 600;
      opacity: 0; transform: translateY(-6px);
      transition: all .18s ease; pointer-events: none;
    }
    .toast.show { opacity: 1; transform: translateY(0); }
  </style>
</head>
<body>
<div class="picker-shell">

  <!-- Toolbar -->
  <div class="toolbar">
    <span class="toolbar-title">Pick an Image</span>
    <input type="text" id="searchInput" placeholder="Search images…" value="<?= h($q ?? '') ?>">
    <select id="folderSelect">
      <option value="">All folders</option>
      <?php foreach (($folders ?? []) as $f): ?>
        <option value="<?= h($f) ?>" <?= ($folder ?? '') === $f ? 'selected' : '' ?>><?= h($f) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" id="searchBtn">Search</button>
    <button class="btn" id="cancelBtn">Cancel</button>
  </div>

  <!-- Scrollable grid -->
  <div class="grid-wrap">
    <div class="grid" id="grid">
      <?php if (empty($items)): ?>
        <div class="empty">No images found. Upload some in the Media Library.</div>
      <?php else: ?>
        <?php foreach ($items as $m): ?>
          <div class="item"
               data-url="<?= h($m['webp_url'] ?: $m['public_url']) ?>"
               data-title="<?= h($m['title'] ?: $m['original_name']) ?>">
            <img src="<?= h($m['thumbnail_url'] ?: $m['public_url']) ?>"
                 alt="<?= h($m['title'] ?: '') ?>"
                 onerror="this.classList.add('broken');if(!this._fb){this._fb=1;this.src='<?= h($m['public_url']) ?>'}"
                 loading="lazy">
            <div class="item-label"><?= h(mb_strimwidth((string)($m['title'] ?: $m['original_name']), 0, 28, '…')) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Insert options panel — only visible in 'insert' mode after selecting an image -->
  <div class="insert-panel" id="insertPanel">
    <div class="insert-row">
      <label>Alt text</label>
      <input type="text" id="insertAlt" placeholder="Describe the image for accessibility…">
    </div>
    <div class="insert-row">
      <label>Caption</label>
      <input type="text" id="insertCaption" placeholder="Optional caption (italic, small)…">
      <div class="caption-preview" id="captionPreview"></div>
    </div>
    <div class="insert-row">
      <label>Width</label>
      <div class="slider-wrap">
        <input type="range" id="widthSlider" min="20" max="100" value="100" step="5">
        <span class="slider-val" id="widthVal">100%</span>
      </div>
      <label style="margin-left:8px">Align</label>
      <select id="insertAlign">
        <option value="center">Centre</option>
        <option value="left">Float left</option>
        <option value="right">Float right</option>
        <option value="none">Inline</option>
      </select>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    <div class="footer-info" id="selectedInfo">Click an image to select it</div>
    <div style="display:flex;gap:8px">
      <button class="btn" id="cancelBtn2">Cancel</button>
      <button class="btn primary" id="insertBtn" disabled>Insert Selected</button>
    </div>
  </div>

</div>
<div class="toast" id="toast"></div>

<script>
(function () {
  'use strict';

  var fieldName = <?= json_encode($fieldName ?? '') ?>;
  var mode      = <?= json_encode($mode ?? '') ?>;
  var isInsert  = (mode === 'insert');
  var isIframe  = (window.parent !== window);
  var selected  = null;

  var grid         = document.getElementById('grid');
  var insertBtn    = document.getElementById('insertBtn');
  var selectedInfo = document.getElementById('selectedInfo');
  var toast        = document.getElementById('toast');
  var searchInput  = document.getElementById('searchInput');
  var folderSelect = document.getElementById('folderSelect');
  var searchBtn    = document.getElementById('searchBtn');
  var insertPanel  = document.getElementById('insertPanel');
  var captionInput = document.getElementById('insertCaption');
  var captionPrev  = document.getElementById('captionPreview');
  var widthSlider  = document.getElementById('widthSlider');
  var widthVal     = document.getElementById('widthVal');

  // Update insert button label
  if (isInsert) insertBtn.textContent = 'Insert into Article';

  // ── Caption live preview ───────────────────────────────────────
  if (captionInput) {
    captionInput.addEventListener('input', function () {
      captionPrev.textContent = captionInput.value || '';
    });
  }

  // ── Width slider ───────────────────────────────────────────────
  if (widthSlider) {
    widthSlider.addEventListener('input', function () {
      widthVal.textContent = widthSlider.value + '%';
    });
  }

  // ── Close ──────────────────────────────────────────────────────
  function closePicker() {
    if (isIframe) {
      window.parent.postMessage({ type: 'media_picker_close' }, window.location.origin);
    } else {
      window.close();
    }
  }
  document.getElementById('cancelBtn').addEventListener('click', closePicker);
  document.getElementById('cancelBtn2').addEventListener('click', closePicker);

  // ── Toast ──────────────────────────────────────────────────────
  function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 1400);
  }

  // ── Select item ────────────────────────────────────────────────
  grid.addEventListener('click', function (e) {
    var item = e.target.closest('.item');
    if (!item) return;
    document.querySelectorAll('.item.selected').forEach(function (el) { el.classList.remove('selected'); });
    item.classList.add('selected');
    selected = { url: item.dataset.url, title: item.dataset.title };
    selectedInfo.innerHTML = '<strong>' + selected.title.substring(0, 40) + '</strong>';
    insertBtn.disabled = false;
    if (isInsert && insertPanel) {
      insertPanel.classList.add('visible');
      var altInput = document.getElementById('insertAlt');
      if (altInput) { altInput.value = selected.title; altInput.focus(); altInput.select(); }
    }
  });

  // ── Double-click instant insert ────────────────────────────────
  grid.addEventListener('dblclick', function (e) {
    if (e.target.closest('.item')) insertSelected();
  });

  insertBtn.addEventListener('click', insertSelected);

  function insertSelected() {
    if (!selected) return;
    var msg = { type: 'media_pick', url: selected.url, title: selected.title, field: fieldName };
    if (isInsert) {
      msg.alt     = (document.getElementById('insertAlt')?.value || '').trim();
      msg.caption = (captionInput?.value || '').trim();
      msg.width   = (widthSlider?.value || '100') + '%';
      msg.align   = document.getElementById('insertAlign')?.value || 'center';
      msg.mode    = 'insert';
    }
    if (isIframe && window.parent) {
      window.parent.postMessage(msg, window.location.origin);
      showToast('Image inserted ✓');
    } else if (window.opener && !window.opener.closed) {
      window.opener.postMessage(msg, window.location.origin);
      showToast('Image inserted ✓');
      setTimeout(function () { window.close(); }, 500);
    } else {
      navigator.clipboard.writeText(selected.url)
        .then(function () { showToast('URL copied'); })
        .catch(function () { alert('URL: ' + selected.url); });
    }
  }

  // ── Search ─────────────────────────────────────────────────────
  function doSearch() {
    var params = new URLSearchParams({
      q: searchInput.value.trim(),
      folder: folderSelect.value,
      field: fieldName,
      mode: mode
    });
    window.location.href = '/admin/media/picker?' + params.toString();
  }
  searchBtn.addEventListener('click', doSearch);
  searchInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') doSearch(); });
  folderSelect.addEventListener('change', doSearch);

  // ── Keyboard ───────────────────────────────────────────────────
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePicker();
    if (e.key === 'Enter' && selected && document.activeElement.tagName !== 'INPUT') insertSelected();
  });
})();
</script>
</body>
</html>
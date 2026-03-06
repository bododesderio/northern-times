<?php
declare(strict_types=1);

$pageTitle = 'Media Library';
$activeNav = 'media';

ob_start();
?>

<div class="page-header">
  <div>
    <h1>Media Library</h1>
    <div class="sub">Upload • Dedupe • WebP • Thumbnails • Copy URLs. Max upload: <?= (int)($uploadMaxMb ?? 25) ?>MB</div>
  </div>
</div>

<?php if (!empty($flash_success)): ?>
  <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Upload panel -->
<div class="card" style="margin-bottom:20px">
  <div id="drop" style="border:2px dashed var(--border);border-radius:14px;padding:36px;text-align:center;cursor:pointer;transition:all .18s ease;margin-bottom:16px;position:relative">
    <div style="font-weight:700;font-size:16px;margin-bottom:6px">Drag &amp; drop files to upload</div>
    <div class="muted" style="font-size:13px">Drop multiple files at once. Images auto-optimized to WebP. Supports JPG, PNG, GIF, WebP, SVG, PDF, MP4, WebM, MP3, WAV.</div>
    <input type="file" id="dropInput" multiple accept="image/*,video/*,audio/*,.pdf,.svg"
           style="position:absolute;inset:0;opacity:0;cursor:pointer">
  </div>

  <div id="uploadQueue" style="display:none;margin-bottom:16px"></div>

  <form method="POST" action="/admin/media/upload" enctype="multipart/form-data"
        style="display:flex;gap:10px;flex-wrap:wrap;align-items:center" id="uploadForm">
    <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
    <input type="file" name="file" required style="flex:1;min-width:200px" multiple>
    <input name="folder" id="uploadFolder" class="form-control" style="width:200px"
           value="<?= h($folder ?? $_ENV['MEDIA_DEFAULT_FOLDER'] ?? 'Articles') ?>"
           placeholder="Folder (e.g. Articles, Ads)">
    <button class="btn" type="submit">Upload</button>
  </form>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px;padding:14px">
  <form style="display:flex;gap:10px;flex-wrap:wrap;align-items:center" method="GET" action="/admin/media">
    <input name="q" value="<?= h($q ?? '') ?>" placeholder="Search title/name/url…"
           class="form-control" style="flex:1;min-width:200px">
    <select name="type" class="form-control" style="min-width:140px">
      <option value="">All types</option>
      <?php foreach (['image','video','audio','document'] as $t): ?>
        <option value="<?= h($t) ?>" <?= (($type ?? '') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="folder" class="form-control" style="min-width:150px">
      <option value="">All folders</option>
      <?php foreach (($folders ?? []) as $f): ?>
        <option value="<?= h($f['folder']) ?>" <?= (($folder ?? '') === $f['folder']) ? 'selected' : '' ?>>
          <?= h($f['folder']) ?> (<?= (int)$f['cnt'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
    <a class="btn light" href="/admin/media">Reset</a>
  </form>
</div>

<!-- Media grid -->
<?php if (empty($items)): ?>
  <div class="card" style="text-align:center;padding:48px">
    <div style="font-size:32px;margin-bottom:12px">📂</div>
    <div style="font-weight:700;font-size:16px;margin-bottom:6px">No media found.</div>
    <div class="muted">Upload something. A newsroom without photos is just a group chat.</div>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:20px">
    <?php foreach ($items as $m): ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:box-shadow .18s ease"
           class="media-card">

        <?php if (($m['media_type'] ?? '') === 'image'): ?>
          <img src="<?= h($m['thumbnail_url'] ?: $m['public_url']) ?>" alt=""
               style="width:100%;height:160px;object-fit:cover;display:block;background:var(--paper)">
        <?php else: ?>
          <div style="width:100%;height:160px;background:var(--paper);display:flex;align-items:center;justify-content:center;font-size:36px">
            <?= match($m['media_type'] ?? '') { 'video' => '🎬', 'audio' => '🎵', default => '📄' } ?>
          </div>
        <?php endif; ?>

        <div style="padding:12px">
          <div style="font-weight:600;font-size:13px;margin-bottom:6px;line-height:1.3;color:var(--ink)">
            <?= h(mb_strimwidth((string)($m['title'] ?: $m['original_name']), 0, 40, '…')) ?>
          </div>
          <div class="muted" style="font-size:11px;margin-bottom:10px;display:flex;gap:6px;flex-wrap:wrap">
            <span style="background:var(--paper);border:1px solid var(--border);border-radius:99px;padding:1px 8px"><?= h($m['folder']) ?></span>
            <?php if (!empty($m['width'])): ?>
              <span style="background:var(--paper);border:1px solid var(--border);border-radius:99px;padding:1px 8px"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?></span>
            <?php endif; ?>
            <span><?= number_format(((int)$m['file_size'])/1024, 1) ?> KB</span>
          </div>

          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn light sm" type="button"
                    onclick="copyMediaUrl('<?= h(addslashes($m['webp_url'] ?: $m['public_url'])) ?>')">
              Copy URL
            </button>
            <?php if (!empty($m['thumbnail_url'])): ?>
              <button class="btn light sm" type="button"
                      onclick="copyMediaUrl('<?= h(addslashes($m['thumbnail_url'])) ?>')">
                Thumb
              </button>
            <?php endif; ?>
            <form method="POST" action="/admin/media/<?= h((string)$m['id']) ?>/delete" style="margin-left:auto"
                  data-confirm="Delete this media? This cannot be undone." data-confirm-title="Delete Media" data-confirm-level="danger" data-confirm-ok="Delete">
              <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
              <button class="btn danger sm" type="submit">Delete</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <div class="pagination">
    <span class="pcount"><?= number_format(count($items ?? [])) ?> items · Page <?= (int)($page ?? 1) ?> of <?= (int)($totalPages ?? 1) ?></span>
    <?php
      $base = [];
      if (($q ?? '') !== '') $base['q'] = $q;
      if (($type ?? '') !== '') $base['type'] = $type;
      if (($folder ?? '') !== '') $base['folder'] = $folder;
      $mk = fn($p) => '/admin/media?' . http_build_query(array_merge($base, ['page' => $p]));
    ?>
    <a class="btn light sm" href="<?= h($mk(1)) ?>">First</a>
    <a class="btn light sm" href="<?= h($mk(max(1, (int)($page ?? 1) - 1))) ?>">Prev</a>
    <a class="btn light sm" href="<?= h($mk(min((int)($totalPages ?? 1), (int)($page ?? 1) + 1))) ?>">Next</a>
    <a class="btn light sm" href="<?= h($mk((int)($totalPages ?? 1))) ?>">Last</a>
  </div>
<?php endif; ?>

<!-- Toast notification -->
<div id="mediaToast" style="
  position:fixed;right:20px;bottom:20px;z-index:99999;
  background:#121212;color:#fff;
  padding:12px 18px;border-radius:12px;
  font-family:var(--ui);font-size:13px;font-weight:600;
  opacity:0;transform:translateY(8px);
  transition:opacity .18s ease, transform .18s ease;
  pointer-events:none;
">Copied ✅</div>

<style>
  .media-card:hover { box-shadow: var(--shadow-hover); }
  .btn.sm { padding: 6px 12px; font-size: 12px; }
</style>

<script>
(function () {
  const toast = document.getElementById('mediaToast');
  const drop = document.getElementById('drop');
  const dropInput = document.getElementById('dropInput');
  const queue = document.getElementById('uploadQueue');
  const csrf = '<?= h($csrf ?? '') ?>';

  function showToast(msg, dur) {
    toast.textContent = msg || 'Done';
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
    }, dur || 1400);
  }

  window.copyMediaUrl = function (url) {
    navigator.clipboard.writeText(url)
      .then(() => showToast('Copied ✅'))
      .catch(() => prompt('Copy this URL:', url));
  };

  // ── Multi-file upload ──
  async function uploadFiles(files) {
    if (!files || !files.length) return;
    const folder = document.getElementById('uploadFolder')?.value || 'Articles';

    queue.style.display = 'block';
    queue.innerHTML = '';

    let completed = 0;
    const total = files.length;

    for (const file of files) {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)';
      row.innerHTML = '<div style="flex:1;font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
        + file.name.replace(/</g,'&lt;')
        + ' <span class="muted" style="font-weight:400">(' + (file.size/1024).toFixed(0) + ' KB)</span></div>'
        + '<div class="up-status" style="font-size:12px;color:var(--muted)">Uploading…</div>'
        + '<div style="width:100px;height:6px;background:var(--border);border-radius:3px;overflow:hidden">'
        + '<div class="up-bar" style="width:0;height:100%;background:var(--accent);border-radius:3px;transition:width .2s"></div></div>';
      queue.appendChild(row);

      const bar = row.querySelector('.up-bar');
      const status = row.querySelector('.up-status');

      const fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('folder', folder);
      fd.append('file', file);

      try {
        const res = await fetch('/admin/media/upload', {
          method: 'POST', body: fd,
          headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        const data = await res.json().catch(() => null);

        if (res.ok && data?.ok) {
          bar.style.width = '100%'; bar.style.background = '#22c55e';
          status.textContent = '✅ Done';
          status.style.color = '#22c55e';
        } else {
          bar.style.width = '100%'; bar.style.background = '#ef4444';
          status.textContent = data?.message || 'Failed';
          status.style.color = '#ef4444';
        }
      } catch {
        bar.style.width = '100%'; bar.style.background = '#ef4444';
        status.textContent = 'Network error';
        status.style.color = '#ef4444';
      }
      completed++;
    }

    showToast(completed + ' file(s) uploaded', 2000);
    setTimeout(() => location.reload(), 1200);
  }

  // Drag & drop zone
  ['dragover','dragenter'].forEach(e => {
    drop.addEventListener(e, ev => { ev.preventDefault(); drop.style.borderColor = 'var(--accent)'; drop.style.background = 'var(--paper)'; });
  });
  ['dragleave','dragend'].forEach(e => {
    drop.addEventListener(e, () => { drop.style.borderColor = 'var(--border)'; drop.style.background = ''; });
  });
  drop.addEventListener('drop', e => {
    e.preventDefault();
    drop.style.borderColor = 'var(--border)';
    drop.style.background = '';
    uploadFiles(e.dataTransfer.files);
  });

  // Click to select files
  dropInput.addEventListener('change', () => {
    if (dropInput.files.length) uploadFiles(dropInput.files);
  });
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
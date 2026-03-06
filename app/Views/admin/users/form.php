<?php
declare(strict_types=1);

$isEdit    = ($mode ?? 'create') === 'edit';
$pageTitle = $isEdit ? 'Edit User' : 'New User';
$activeNav = 'users';

$u      = $user ?? [];
$action = $isEdit ? '/admin/users/' . h((string)($u['id'] ?? '')) : '/admin/users';

$roleColors = [
    'super_admin' => '#c00',
    'editor'      => '#1a6bbf',
    'author'      => '#4a4a4a',
];

ob_start();
?>

<!-- Breadcrumb back link -->
<div style="margin-bottom:20px">
    <a href="/admin/users" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;color:var(--muted)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg>
        Back to Users
    </a>
</div>

<div style="max-width:760px">

<div class="page-header">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <div class="sub">
            <?= $isEdit ? 'Update account details, role, and permissions.' : 'Create a new newsroom account.' ?>
        </div>
    </div>
</div>

<?php if (!empty($flash_error)): ?>
    <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= h($action) ?>">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <!-- Identity -->
    <div class="card" style="margin-bottom:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            Account Details
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label class="form-label">Username <span class="req">*</span></label>
                <input
                    name="username"
                    type="text"
                    required
                    class="form-control"
                    value="<?= h((string)($u['username'] ?? '')) ?>"
                    placeholder="e.g. amara_okello"
                    autocomplete="username"
                />
                <div class="form-hint">Shown as the article author name if no display author is set.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Email <span class="req">*</span></label>
                <input
                    name="email"
                    type="email"
                    required
                    class="form-control"
                    value="<?= h((string)($u['email'] ?? '')) ?>"
                    placeholder="journalist@example.com"
                    autocomplete="email"
                />
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label class="form-label">
                    Password <?= $isEdit ? '' : '<span class="req">*</span>' ?>
                </label>
                <input
                    name="password"
                    type="password"
                    <?= $isEdit ? '' : 'required' ?>
                    class="form-control"
                    placeholder="<?= $isEdit ? 'Leave blank to keep current password' : 'Min. 8 characters' ?>"
                    autocomplete="new-password"
                    minlength="8"
                />
                <?php if ($isEdit): ?>
                    <div class="form-hint">Only fill in if you want to change this user's password.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Role <span class="req">*</span></label>
                <select name="role" class="form-control" id="roleSelect">
                    <?php foreach (($roles ?? []) as $rl): ?>
                        <option value="<?= h($rl['slug']) ?>" <?= ($u['role'] ?? 'author') === $rl['slug'] ? 'selected' : '' ?>
                                data-desc="<?= h($rl['description'] ?? '') ?>">
                            <?= h($rl['label']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($roles)): ?>
                        <option value="author" <?= ($u['role'] ?? 'author') === 'author' ? 'selected' : '' ?>>Author</option>
                        <option value="editor" <?= ($u['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
                        <option value="super_admin" <?= ($u['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <?php endif; ?>
                </select>
                <div class="form-hint" id="roleHint" style="margin-top:5px"></div>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="form-group">
            <label class="form-label">Account Status</label>
            <div class="toggle-wrap">
                <label class="toggle">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($u['is_active']) ? 'checked' : '' ?>>
                    <span class="toggle-track"></span>
                </label>
                <span style="font-size:14px">Active (user can log in)</span>
            </div>
            <!-- Hidden fallback so unchecked sends 0 -->
            <input type="hidden" name="is_active" value="0" style="display:none" id="isActiveHidden">
        </div>
        <?php endif; ?>
    </div>

    <!-- Profile info -->
    <div class="card" style="margin-bottom:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            Profile (Optional)
        </div>

        <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea
                name="bio"
                class="form-control"
                rows="3"
                placeholder="Short bio shown on article author cards…"
            ><?= h((string)($u['bio'] ?? '')) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label class="form-label">Twitter / X Handle</label>
                <div style="display:flex;align-items:center;gap:0">
                    <span style="padding:10px 12px;border:1px solid var(--border);border-right:none;border-radius:9px 0 0 9px;background:#f8f8f8;font-size:14px;color:var(--muted)">@</span>
                    <input
                        name="twitter_handle"
                        type="text"
                        class="form-control"
                        style="border-radius:0 9px 9px 0"
                        value="<?= h((string)($u['twitter_handle'] ?? '')) ?>"
                        placeholder="username"
                    />
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Avatar URL</label>
                <input
                    name="avatar_url"
                    type="text"
                    class="form-control"
                    value="<?= h((string)($u['avatar_url'] ?? '')) ?>"
                    placeholder="https://… or pick from media library"
                    id="avatarUrlInput"
                />
                <div class="form-hint">
                    <button type="button" class="btn light sm" onclick="openAvatarPicker()" style="font-size:12px;padding:4px 12px">Choose from Media Library</button>
                </div>
            </div>
        </div>

        <!-- Avatar preview -->
        <?php if (!empty($u['avatar_url'])): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px;background:#f8f8f8;border-radius:10px;margin-top:4px">
            <img src="<?= h($u['avatar_url']) ?>" alt="" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--border)"/>
            <div>
                <div style="font-weight:600;font-size:14px"><?= h((string)($u['username'] ?? '')) ?></div>
                <div class="muted" style="font-size:12px">Current avatar</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <button class="btn" type="submit">
            <?= $isEdit ? 'Save Changes' : 'Create User' ?>
        </button>
        <a class="btn light" href="/admin/users">Cancel</a>

        <?php if ($isEdit): ?>
            <div style="margin-left:auto;font-size:12px;color:var(--muted)">
                ID: <code style="font-size:11px"><?= h((string)($u['id'] ?? '')) ?></code>
            </div>
        <?php endif; ?>
    </div>

</form>
</div>

<script>
(function () {
    // Dynamic role hints from data attributes
    var select = document.getElementById('roleSelect');
    var hint   = document.getElementById('roleHint');

    function updateHint() {
        if (!hint || !select) return;
        var opt = select.options[select.selectedIndex];
        hint.textContent = opt ? (opt.dataset.desc || '') : '';
    }

    if (select) {
        select.addEventListener('change', updateHint);
        updateHint();
    }

    // Fix the active toggle
    var cb     = document.querySelector('input[name="is_active"][type="checkbox"]');
    var hidden = document.getElementById('isActiveHidden');
    if (cb && hidden) {
        cb.addEventListener('change', function () { hidden.disabled = cb.checked; });
        hidden.disabled = cb.checked;
    }
})();

// ═══ MEDIA PICKER MODAL (reusable) ═══
(function(){
  var modal = null, grid = null, search = null, targetField = null;

  function createModal() {
    if (modal) return;
    var backdrop = document.createElement('div');
    backdrop.id = 'mpBackdrop';
    backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:none';
    backdrop.onclick = closeModal;

    modal = document.createElement('div');
    modal.id = 'mpModal';
    modal.style.cssText = 'position:fixed;top:5%;left:50%;transform:translateX(-50%);width:90%;max-width:820px;height:85vh;background:var(--surface,#fff);border-radius:16px;box-shadow:0 20px 80px rgba(0,0,0,.3);z-index:9999;display:none;overflow:hidden;display:none;flex-direction:column';

    modal.innerHTML = `
      <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);display:flex;align-items:center;gap:12px">
        <div style="font-weight:700;font-size:16px;flex:1">Choose from Media Library</div>
        <input id="mpSearch" placeholder="Search…" style="padding:8px 14px;border:1px solid var(--border);border-radius:10px;font-size:13px;width:200px">
        <button id="mpClose" style="background:none;border:none;font-size:20px;cursor:pointer;padding:4px 8px;color:var(--muted)">&times;</button>
      </div>
      <div id="mpGrid" style="flex:1;overflow-y:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px"></div>
    `;

    document.body.appendChild(backdrop);
    document.body.appendChild(modal);

    grid = document.getElementById('mpGrid');
    search = document.getElementById('mpSearch');
    document.getElementById('mpClose').onclick = closeModal;
    search.addEventListener('input', debounce(loadItems, 300));
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
  }

  function openModal(fieldId) {
    createModal();
    targetField = document.getElementById(fieldId);
    document.getElementById('mpBackdrop').style.display = 'block';
    modal.style.display = 'flex';
    search.value = '';
    search.focus();
    loadItems();
  }

  function closeModal() {
    if (!modal) return;
    document.getElementById('mpBackdrop').style.display = 'none';
    modal.style.display = 'none';
  }

  async function loadItems() {
    if (!grid) return;
    grid.innerHTML = '<div class="muted" style="grid-column:1/-1;text-align:center;padding:40px">Loading…</div>';
    var q = encodeURIComponent(search?.value || '');
    try {
      var res = await fetch('/admin/media/picker?q=' + q, { headers: { 'X-Requested-With': 'fetch' } });
      var data = await res.json();
      if (!data.ok || !data.items || !data.items.length) {
        grid.innerHTML = '<div class="muted" style="grid-column:1/-1;text-align:center;padding:40px">No images found. Upload some in Media Library first.</div>';
        return;
      }
      var html = '';
      data.items.forEach(function(m) {
        var thumb = (m.thumbnail_url || m.public_url || '').replace(/"/g, '&quot;');
        var url = (m.public_url || '').replace(/"/g, '&quot;');
        var title = (m.title || m.original_name || '').replace(/</g, '&lt;').substring(0, 30);
        html += '<div class="mp-item" data-url="' + url + '" style="border:1px solid var(--border,#e2e2e2);border-radius:10px;overflow:hidden;cursor:pointer;transition:all .15s ease">'
              + '<img src="' + thumb + '" alt="" style="width:100%;height:120px;object-fit:cover;display:block">'
              + '<div style="padding:6px 8px;font-size:11px;color:var(--muted);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + title + '</div>'
              + '</div>';
      });
      grid.innerHTML = html;
      grid.querySelectorAll('.mp-item').forEach(function(el) {
        el.addEventListener('click', function() {
          if (targetField) {
            targetField.value = this.dataset.url;
            targetField.dispatchEvent(new Event('input'));
          }
          closeModal();
        });
        el.addEventListener('mouseenter', function(){ this.style.borderColor = 'var(--accent,#cc0000)'; this.style.transform = 'scale(1.02)'; });
        el.addEventListener('mouseleave', function(){ this.style.borderColor = 'var(--border,#e2e2e2)'; this.style.transform = 'scale(1)'; });
      });
    } catch(e) {
      grid.innerHTML = '<div class="muted" style="grid-column:1/-1;text-align:center;padding:40px">Failed to load media.</div>';
    }
  }

  function debounce(fn, ms) {
    var t; return function() { clearTimeout(t); t = setTimeout(fn, ms); };
  }

  window.openAvatarPicker = function() { openModal('avatarUrlInput'); };
  window.openMediaPickerFor = function(fieldId) { openModal(fieldId); };
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
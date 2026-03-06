<?php
declare(strict_types=1);
$isEdit    = ($mode ?? 'create') === 'edit';
$pageTitle = $isEdit ? 'Edit Role' : 'New Role';
$activeNav = 'roles';
$r = $role ?? [];
$action = $isEdit ? '/admin/roles/' . h((string)($r['id'] ?? '')) : '/admin/roles';
$isSystem = (bool)($r['is_system'] ?? false);

// Parse existing permissions
$existingPerms = [];
if (!empty($r['permissions'])) {
    $arr = json_decode($r['permissions'], true);
    if (is_array($arr)) $existingPerms = $arr;
}

// All available permissions organized by category
$permCategories = [
    'Content' => [
        'articles.own'      => 'Create & edit own articles',
        'articles.all'      => 'Edit all articles (any author)',
        'articles.publish'  => 'Publish / unpublish articles',
        'articles.delete'   => 'Delete articles',
        'categories.manage' => 'Manage categories',
    ],
    'Media' => [
        'media.upload'      => 'Upload media files',
        'media.delete'      => 'Delete media files',
        'media.manage'      => 'Manage all media & folders',
    ],
    'Engagement' => [
        'comments.view'      => 'View all comments',
        'comments.moderate'  => 'Moderate comments (show/hide/delete)',
        'subscribers.view'   => 'View newsletter subscribers',
        'subscribers.manage' => 'Manage subscribers (export/delete)',
    ],
    'Newsletter' => [
        'newsletter.view'    => 'View newsletter campaigns',
        'newsletter.manage'  => 'Compose & send newsletters',
    ],
    'Popups' => [
        'popups.view'        => 'View popups',
        'popups.manage'      => 'Create & edit popups',
    ],
    'Analytics' => [
        'analytics.view'     => 'View analytics & reports',
    ],
    'Advertising' => [
        'ads.view'           => 'View ad slots',
        'ads.manage'         => 'Create & edit ad campaigns',
    ],
    'Crawler' => [
        'crawler.view'       => 'View crawler sources & logs',
        'crawler.manage'     => 'Manage sources & run crawler',
    ],
    'Administration' => [
        'settings.view'      => 'View site settings',
        'settings.edit'      => 'Edit site settings & themes',
        'users.view'         => 'View user list',
        'users.manage'       => 'Create, edit & delete users',
        'roles.manage'       => 'Manage roles & permissions',
        'system.admin'       => 'Access system admin & health tools',
    ],
    'Full Access' => [
        '*'                 => 'Super admin — unrestricted access to everything',
    ],
];

ob_start();
?>

<div style="margin-bottom:20px">
  <a href="/admin/roles" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;color:var(--muted)">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg>
    Back to Roles
  </a>
</div>

<div style="max-width:760px">
  <h1><?= h($pageTitle) ?></h1>

  <?php if (!empty($flash_error)): ?><div class="flash bad"><?= h($flash_error) ?></div><?php endif; ?>
  <?php if ($isSystem && $isEdit): ?>
    <div class="flash" style="background:#fef3c7;border-color:#f59e0b;color:#92400e">
      System role — you can edit label, description, and color. Slug and core permissions are locked.
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= h($action) ?>">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <div class="card" style="margin-bottom:20px">
      <div style="font-weight:700;font-size:14px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">Role Details</div>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group">
          <label class="form-label">Label <span class="req">*</span></label>
          <input name="label" class="form-control" required value="<?= h($r['label'] ?? '') ?>" placeholder="e.g. Contributor">
        </div>
        <div class="form-group">
          <label class="form-label">Slug <span class="req">*</span></label>
          <input name="slug" class="form-control" required value="<?= h($r['slug'] ?? '') ?>"
                 placeholder="e.g. contributor" pattern="[a-z0-9_]+"
                 <?= ($isSystem && $isEdit) ? 'readonly style="opacity:.6;cursor:not-allowed"' : '' ?>>
          <div class="form-hint">Lowercase, underscores only.</div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="What can this role do?"><?= h($r['description'] ?? '') ?></textarea>
      </div>

      <div style="display:grid;grid-template-columns:120px 120px 1fr;gap:16px">
        <div class="form-group">
          <label class="form-label">Color</label>
          <input name="color" type="color" value="<?= h($r['color'] ?? '#666666') ?>"
                 style="width:100%;height:42px;border:1px solid var(--border);border-radius:10px;padding:4px;cursor:pointer">
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input name="sort_order" type="number" class="form-control" value="<?= (int)($r['sort_order'] ?? 10) ?>" min="0" max="999">
        </div>
        <div></div>
      </div>
    </div>

    <!-- PERMISSIONS GRID -->
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
        <div>
          <span style="font-weight:700;font-size:14px">Permissions</span>
          <span class="muted" style="font-weight:400;font-size:12px;margin-left:8px">Select what this role can do</span>
        </div>
        <span id="permCount" style="font-size:12px;font-weight:700;background:var(--accent);color:#fff;padding:3px 10px;border-radius:10px">0 selected</span>
      </div>

      <?php $lockedPerms = ($isSystem && $isEdit); ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <?php foreach ($permCategories as $catName => $perms): ?>
        <div class="perm-group" style="border:1px solid var(--border);border-radius:12px;padding:14px;<?= $catName === 'Full Access' ? 'grid-column:1/-1;background:var(--paper)' : '' ?>">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <span style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)"><?= h($catName) ?></span>
            <?php if ($catName !== 'Full Access' && !$lockedPerms): ?>
            <button type="button" onclick="ntToggleGroup(this)" style="font-size:11px;color:var(--accent);background:none;border:none;cursor:pointer;padding:0;font-weight:600">Select all</button>
            <?php endif; ?>
          </div>
          <?php foreach ($perms as $permSlug => $permLabel):
            $checked = in_array($permSlug, $existingPerms) || in_array('*', $existingPerms);
          ?>
          <label style="display:flex;align-items:flex-start;gap:8px;padding:6px 0;cursor:<?= $lockedPerms ? 'not-allowed' : 'pointer' ?>;font-size:13px">
            <input type="checkbox" name="permissions[]" value="<?= h($permSlug) ?>"
                   <?= $checked ? 'checked' : '' ?>
                   <?= $lockedPerms ? 'disabled' : '' ?>
                   style="margin-top:2px;accent-color:var(--accent)">
            <span>
              <span style="font-weight:600"><?= h($permLabel) ?></span>
              <code style="font-size:10px;color:var(--muted);margin-left:4px"><?= h($permSlug) ?></code>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($lockedPerms): ?>
        <!-- Send existing perms as hidden fields since disabled inputs don't submit -->
        <?php foreach ($existingPerms as $ep): ?>
          <input type="hidden" name="permissions[]" value="<?= h($ep) ?>">
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:12px">
      <button class="btn" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Role' ?></button>
      <a class="btn light" href="/admin/roles">Cancel</a>
    </div>
  </form>
</div>

<script>
(function(){
  var full   = document.querySelector('input[value="*"]');
  var others = document.querySelectorAll('input[name="permissions[]"]:not([value="*"])');
  var countEl = document.getElementById('permCount');

  function updateCount() {
    if (!countEl) return;
    var n = document.querySelectorAll('input[name="permissions[]"]:checked').length;
    countEl.textContent = n + ' selected';
    countEl.style.background = n === 0 ? '#999' : 'var(--accent)';
  }

  // Full-access toggle
  function toggleFull() {
    if (!full) return;
    others.forEach(function(cb) {
      cb.disabled = full.checked;
      if (full.checked) cb.checked = true;
    });
    updateCount();
  }
  if (full) { full.addEventListener('change', toggleFull); toggleFull(); }

  // Per-group select-all toggle
  window.ntToggleGroup = function(btn) {
    var group = btn.closest('.perm-group');
    if (!group) return;
    var boxes = group.querySelectorAll('input[type="checkbox"]:not(:disabled)');
    var allChecked = Array.from(boxes).every(function(b){ return b.checked; });
    boxes.forEach(function(b){ b.checked = !allChecked; });
    btn.textContent = allChecked ? 'Select all' : 'Deselect all';
    updateCount();
  };

  // Live counter on every change
  document.querySelectorAll('input[name="permissions[]"]').forEach(function(cb){
    cb.addEventListener('change', updateCount);
  });

  updateCount();
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
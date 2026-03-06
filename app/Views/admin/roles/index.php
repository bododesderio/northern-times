<?php
declare(strict_types=1);
$roles     = $roles ?? [];
$activeNav = 'roles';

// Permission categories + all slugs for matrix display
$permCategories = [
    'Content'        => ['articles.own','articles.all','articles.publish','articles.delete','categories.manage'],
    'Media'          => ['media.upload','media.delete','media.manage'],
    'Engagement'     => ['comments.view','comments.moderate','subscribers.view','subscribers.manage'],
    'Newsletter'     => ['newsletter.view','newsletter.manage'],
    'Popups'         => ['popups.view','popups.manage'],
    'Analytics'      => ['analytics.view'],
    'Advertising'    => ['ads.view','ads.manage'],
    'Crawler'        => ['crawler.view','crawler.manage'],
    'Administration' => ['settings.view','settings.edit','users.view','users.manage','roles.manage','system.admin'],
];
$permLabels = [
    'articles.own'=>'Own articles','articles.all'=>'All articles','articles.publish'=>'Publish',
    'articles.delete'=>'Delete','categories.manage'=>'Categories','media.upload'=>'Upload',
    'media.delete'=>'Delete','media.manage'=>'Manage','comments.view'=>'View comments',
    'comments.moderate'=>'Moderate','subscribers.view'=>'View subs','subscribers.manage'=>'Manage subs',
    'newsletter.view'=>'View','newsletter.manage'=>'Send','popups.view'=>'View',
    'popups.manage'=>'Manage','analytics.view'=>'Analytics','ads.view'=>'View ads',
    'ads.manage'=>'Manage ads','crawler.view'=>'View','crawler.manage'=>'Manage',
    'settings.view'=>'View settings','settings.edit'=>'Edit settings','users.view'=>'View users',
    'users.manage'=>'Manage users','roles.manage'=>'Manage roles','system.admin'=>'System admin',
];

/**
 * Check if a permission slug is granted, handling wildcards like "articles.*"
 */
function permGranted(string $slug, array $perms): bool {
    if (in_array('*', $perms)) return true;
    if (in_array($slug, $perms)) return true;
    // Check wildcards: articles.* grants articles.own, articles.all, etc.
    $parts = explode('.', $slug);
    if (count($parts) >= 2) {
        $wildcard = $parts[0] . '.*';
        if (in_array($wildcard, $perms)) return true;
    }
    return false;
}

ob_start();
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:28px">
  <div>
    <h1>Roles &amp; Permissions</h1>
    <div class="sub">Click any role&rsquo;s color swatch to change it instantly.</div>
  </div>
  <a class="btn" href="/admin/roles/create">+ New Role</a>
</div>

<?php if (!empty($flash_success)): ?><div class="flash ok"><?= h($flash_success) ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="flash bad"><?= h($flash_error) ?></div><?php endif; ?>

<div style="display:grid;gap:16px;max-width:960px" id="rolesGrid">
  <?php foreach ($roles as $r):
    $perms     = json_decode($r['permissions'] ?? '[]', true) ?: [];
    $isSuperAdmin = in_array('*', $perms);
    $isSystem  = (bool)$r['is_system'];
    $userCount = (int)($r['user_count'] ?? 0);
    $color     = $r['color'] ?? '#666666';
    $roleId    = h($r['id']);
  ?>
  <div class="role-card" id="role-<?= $roleId ?>">

    <!-- Top row -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:14px">
      <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">

        <!-- ── Inline color swatch ── -->
        <div class="role-swatch-wrap" title="Click to change color">
          <div class="role-swatch" id="swatch-<?= $roleId ?>"
               style="background:<?= h($color) ?>"
               onclick="openColorPicker('<?= $roleId ?>', '<?= h($color) ?>', '<?= h($csrf) ?>')">
            <svg class="swatch-edit-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </div>
          <!-- Hidden native color input -->
          <input type="color" class="role-color-native" id="colorInput-<?= $roleId ?>"
                 value="<?= h($color) ?>" tabindex="-1"
                 onchange="applyColor('<?= $roleId ?>', this.value, '<?= h($csrf) ?>')">
        </div>

        <div style="min-width:0">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px">
            <span style="font-weight:800;font-size:16px;color:var(--ink)"><?= h($r['label']) ?></span>
            <code style="font-size:11px;background:var(--paper);border:1px solid var(--border);border-radius:5px;padding:2px 7px;color:var(--muted)"><?= h($r['slug']) ?></code>
            <?php if ($isSystem): ?>
              <span style="font-size:10px;font-weight:700;background:var(--accent);color:#fff;padding:2px 8px;border-radius:5px;letter-spacing:.04em">SYSTEM</span>
            <?php endif; ?>
            <?php if ($isSuperAdmin): ?>
              <span style="font-size:10px;font-weight:700;background:#7c3aed;color:#fff;padding:2px 8px;border-radius:5px;letter-spacing:.04em">SUPER ADMIN</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($r['description'])): ?>
            <div style="font-size:13px;color:var(--muted);line-height:1.4"><?= h($r['description']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
        <div class="role-user-pill" id="pill-<?= $roleId ?>" style="border-color:<?= h($color) ?>20;color:<?= h($color) ?>;background:<?= h($color) ?>12">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?>
        </div>
        <a class="btn light sm" href="/admin/roles/<?= $roleId ?>/edit">Edit</a>
        <?php
          // Only block deleting the sole super_admin role
          $canDelete = !($r['slug'] === 'super_admin' && count(array_filter($roles, fn($x) => $x['slug'] === 'super_admin')) <= 1);
        ?>
        <?php if ($canDelete): ?>
          <form method="POST" action="/admin/roles/<?= $roleId ?>/delete"
                data-confirm="Delete &ldquo;<?= h($r['label']) ?>&rdquo;? Users will be reassigned to Author."
                data-confirm-title="Delete Role" data-confirm-level="danger" data-confirm-ok="Delete">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <button class="btn danger sm" type="submit">Delete</button>
          </form>
        <?php else: ?>
          <button class="btn danger sm" disabled title="Cannot delete the only Super Admin role" style="opacity:.4;cursor:not-allowed">Delete</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Permission matrix -->
    <?php if ($isSuperAdmin): ?>
      <div class="perm-superadmin">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Unrestricted access to everything
      </div>
    <?php else:
      $hasAny = false;
      foreach ($permCategories as $slugs) {
        foreach ($slugs as $s) {
          if (permGranted($s, $perms)) { $hasAny = true; break 2; }
        }
      }
    ?>
      <div class="perm-matrix">
        <?php if ($hasAny): ?>
          <?php foreach ($permCategories as $catName => $slugs):
            $granted = array_filter($slugs, fn($s) => permGranted($s, $perms));
            if (empty($granted)) continue;
          ?>
          <div class="perm-group-block">
            <div class="perm-group-name"><?= h($catName) ?></div>
            <div class="perm-chips">
              <?php foreach ($granted as $slug): ?>
                <span class="perm-chip" style="--chip-color:<?= h($color) ?>"><?= h($permLabels[$slug] ?? $slug) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <span style="font-size:12px;color:var(--muted);font-style:italic">No permissions assigned</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
  <?php endforeach; ?>

  <?php if (empty($roles)): ?>
    <div class="card" style="text-align:center;padding:40px">
      <h3>No roles found</h3>
      <p class="muted">Run migration 0021_roles_and_fk_fixes.sql to create default roles.</p>
    </div>
  <?php endif; ?>
</div>

<style>
.role-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px 22px;
  transition: box-shadow .15s;
}
.role-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.07); }

/* Swatch */
.role-swatch-wrap { position: relative; flex-shrink: 0; }
.role-swatch {
  width: 38px; height: 38px; border-radius: 10px;
  cursor: pointer; position: relative;
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.12);
  display: flex; align-items: center; justify-content: center;
  transition: transform .15s, box-shadow .15s;
}
.role-swatch:hover { transform: scale(1.08); box-shadow: 0 3px 10px rgba(0,0,0,.25); }
.swatch-edit-icon {
  color: rgba(255,255,255,.85);
  opacity: 0;
  transition: opacity .15s;
  filter: drop-shadow(0 1px 1px rgba(0,0,0,.3));
}
.role-swatch:hover .swatch-edit-icon { opacity: 1; }
.role-color-native {
  position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%;
  cursor: pointer; border: none; padding: 0;
}

/* User pill */
.role-user-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 20px; border: 1.5px solid;
  font-size: 12px; font-weight: 600;
  font-family: var(--ui, system-ui, sans-serif);
  white-space: nowrap; transition: all .2s;
}

/* Permission matrix */
.perm-matrix {
  display: flex; flex-wrap: wrap; gap: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.perm-group-block { display: flex; flex-direction: column; gap: 5px; }
.perm-group-name {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--muted);
  font-family: var(--ui, system-ui, sans-serif);
}
.perm-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.perm-chip {
  display: inline-block; padding: 3px 9px; border-radius: 5px;
  font-size: 11px; font-weight: 600;
  font-family: var(--ui, system-ui, sans-serif);
  background: color-mix(in srgb, var(--chip-color) 12%, transparent);
  color: var(--chip-color);
  border: 1px solid color-mix(in srgb, var(--chip-color) 25%, transparent);
  transition: background .2s;
}

/* Super admin banner */
.perm-superadmin {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 14px;
  background: #f5f3ff; color: #6d28d9;
  border: 1px solid #ede9fe; border-radius: 8px;
  font-size: 12px; font-weight: 700;
  font-family: var(--ui, system-ui, sans-serif);
  margin-top: 14px;
}

/* Dark */
html[data-adm-theme="dark"] .role-card { background: var(--adm-surface); border-color: var(--adm-border); }
html[data-adm-theme="dark"] .role-card:hover { box-shadow: 0 2px 16px rgba(0,0,0,.3); }
html[data-adm-theme="dark"] .perm-matrix { border-color: var(--adm-border); }
html[data-adm-theme="dark"] .perm-superadmin { background: rgba(109,40,217,.15); border-color: rgba(109,40,217,.3); color: #c4b5fd; }

.btn.sm { padding: 6px 14px; font-size: 12px; }
</style>

<script>
function openColorPicker(roleId, currentColor, csrf) {
  // Trigger the hidden native color input
  var input = document.getElementById('colorInput-' + roleId);
  if (input) input.click();
}

function applyColor(roleId, newColor, csrf) {
  var swatch  = document.getElementById('swatch-' + roleId);
  var pill    = document.getElementById('pill-' + roleId);
  var card    = document.getElementById('role-' + roleId);

  // Optimistic UI — update immediately
  if (swatch) swatch.style.background = newColor;
  if (pill) {
    pill.style.color        = newColor;
    pill.style.borderColor  = newColor + '33';
    pill.style.background   = newColor + '18';
  }
  // Update all perm chips in this card
  if (card) {
    card.querySelectorAll('.perm-chip').forEach(function(chip) {
      chip.style.setProperty('--chip-color', newColor);
    });
  }

  // Persist via POST
  var fd = new FormData();
  fd.append('color', newColor);
  fd.append('_csrf', csrf);
  fetch('/admin/roles/' + roleId + '/color', { method: 'POST', credentials: 'same-origin', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) {
        // Revert on failure — reload to get DB state
        window.location.reload();
      }
    })
    .catch(function() { window.location.reload(); });
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
<?php
declare(strict_types=1);

$pageTitle = 'Users & Roles';
$activeNav = 'users';

$roleColors = [
    'super_admin' => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'label' => 'Super Admin'],
    'editor'      => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'label' => 'Editor'],
    'author'      => ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => 'Author'],
];

function userInitials(string $username): string {
    return strtoupper(substr($username, 0, 1));
}
function avatarBg(string $role): string {
    return match($role) { 'super_admin' => '#c00', 'editor' => '#1a6bbf', default => '#4a4a4a' };
}

ob_start();
?>

<div class="page-header">
    <div>
        <h1>Users &amp; Roles</h1>
        <div class="sub">Manage who has access to the newsroom and what they can do.</div>
    </div>
    <a class="btn" href="/admin/users/create">+ New User</a>
</div>

<?php if (!empty($flash_success)): ?>
    <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="padding:16px;margin-bottom:20px">
    <form method="GET" action="/admin/users" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input
            name="q"
            value="<?= h($q ?? '') ?>"
            placeholder="Search by name or email…"
            class="form-control"
            style="flex:1;min-width:220px"
        />
        <select name="role" class="form-control" style="min-width:160px">
            <option value="">All roles</option>
            <option value="super_admin" <?= ($roleFilter ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
            <option value="editor"      <?= ($roleFilter ?? '') === 'editor'      ? 'selected' : '' ?>>Editor</option>
            <option value="author"      <?= ($roleFilter ?? '') === 'author'      ? 'selected' : '' ?>>Author</option>
        </select>
        <button class="btn" type="submit">Filter</button>
        <?php if (($q ?? '') !== '' || ($roleFilter ?? '') !== ''): ?>
            <a class="btn light" href="/admin/users">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Member Since</th>
                <th>Last Login</th>
                <th style="text-align:right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="7" class="muted" style="text-align:center;padding:40px">
                    No users match your filters.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
            <?php
                $rc    = $roleColors[$u['role']] ?? $roleColors['author'];
                $isMe  = $u['id'] === ($_SESSION['user']['id'] ?? '');
            ?>
            <tr>
                <!-- User cell with avatar -->
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="
                            width:34px;height:34px;border-radius:50%;flex-shrink:0;
                            background:<?= avatarBg($u['role']) ?>;
                            display:flex;align-items:center;justify-content:center;
                            font-weight:700;font-size:13px;color:#fff
                        ">
                            <?php if (!empty($u['avatar_url'])): ?>
                                <img src="<?= h($u['avatar_url']) ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover"/>
                            <?php else: ?>
                                <?= userInitials($u['username']) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:14px">
                                <?= h($u['username']) ?>
                                <?php if ($isMe): ?>
                                    <span style="font-size:11px;color:#888;font-weight:400;margin-left:4px">(you)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </td>

                <td class="muted" style="font-size:13px"><?= h($u['email']) ?></td>

                <!-- Role badge -->
                <td>
                    <span style="
                        display:inline-block;padding:3px 10px;border-radius:99px;
                        font-size:11px;font-weight:700;letter-spacing:.03em;
                        background:<?= h($rc['bg']) ?>;color:<?= h($rc['text']) ?>
                    ">
                        <?= h($rc['label']) ?>
                    </span>
                </td>

                <!-- Active/Inactive -->
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge green">Active</span>
                    <?php else: ?>
                        <span class="badge red">Inactive</span>
                    <?php endif; ?>
                </td>

                <td class="muted" style="font-size:13px">
                    <?= h($u['created_at'] ? date('M j, Y', strtotime((string)$u['created_at'])) : '—') ?>
                </td>

                <td class="muted" style="font-size:13px">
                    <?= h($u['last_login'] ? date('M j, Y g:i A', strtotime((string)$u['last_login'])) : 'Never') ?>
                </td>

                <!-- Actions -->
                <td style="text-align:right;white-space:nowrap">
                    <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                        <a class="btn light sm" href="/admin/users/<?= h($u['id']) ?>/edit">Edit</a>

                        <?php if (!$isMe): ?>
                            <!-- Toggle active -->
                            <form method="POST" action="/admin/users/<?= h($u['id']) ?>/toggle" style="display:inline">
                                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                <button class="btn light sm" type="submit"
                                    data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?" data-confirm-title="Toggle User" data-confirm-level="warn" data-confirm-ok="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>

                            <!-- Delete -->
                            <form method="POST" action="/admin/users/<?= h($u['id']) ?>/delete" style="display:inline">
                                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                <button class="btn danger sm" type="submit"
                                    data-confirm="Permanently delete <?= h(addslashes($u['username'])) ?>? This cannot be undone." data-confirm-title="Delete User" data-confirm-level="danger" data-confirm-ok="Delete Forever">
                                    Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if (($totalPages ?? 1) > 1): ?>
<div class="pagination">
    <span class="pcount"><?= number_format($total ?? 0) ?> users · Page <?= (int)($page ?? 1) ?> of <?= (int)($totalPages ?? 1) ?></span>
    <?php
    $base = '/admin/users?' . http_build_query(array_filter(['q' => $q ?? '', 'role' => $roleFilter ?? '']));
    ?>
    <a class="btn light sm" href="<?= h($base . '&page=1') ?>">First</a>
    <a class="btn light sm" href="<?= h($base . '&page=' . max(1, ($page ?? 1) - 1)) ?>">Prev</a>
    <a class="btn light sm" href="<?= h($base . '&page=' . min($totalPages ?? 1, ($page ?? 1) + 1)) ?>">Next</a>
    <a class="btn light sm" href="<?= h($base . '&page=' . ($totalPages ?? 1)) ?>">Last</a>
</div>
<?php endif; ?>

<!-- Role legend -->
<div class="card" style="margin-top:28px;padding:20px">
    <div style="font-weight:700;font-size:13px;margin-bottom:14px;letter-spacing:.02em">Role Permissions</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c">Super Admin</span>
            </div>
            <div class="muted" style="font-size:13px">Full access — users, settings, all content, all roles.</div>
        </div>
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;background:#dbeafe;color:#1d4ed8">Editor</span>
            </div>
            <div class="muted" style="font-size:13px">Manage all articles, categories, media, and settings. Cannot manage users.</div>
        </div>
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;background:#f3f4f6;color:#374151">Author</span>
            </div>
            <div class="muted" style="font-size:13px">Create articles and upload media. Can only edit their own articles.</div>
        </div>
    </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';

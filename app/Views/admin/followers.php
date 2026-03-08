<?php
declare(strict_types=1);

/** @var array $followers */
/** @var string $search */
/** @var string $type */
/** @var int $page */
/** @var int $totalPages */
/** @var int $filteredTotal */
/** @var int $totalCount */
/** @var int $activeCount */
/** @var int $categoryCount */
/** @var int $tagCount */
/** @var string $csrf */
/** @var string|null $flash_success */
/** @var string|null $flash_error */

$pageTitle = 'Topic Followers';
$activeNav = 'followers';
ob_start();
?>

<?php if (!empty($flash_success)): ?>
    <div class="alert alert-success"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="alert alert-error"><?= h($flash_error) ?></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <h1>Topic Followers</h1>
        <p class="muted">Manage readers who follow categories and tags for email alerts.</p>
    </div>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
    <div style="text-align:center;padding:14px;background:#f8f8f8;border-radius:10px">
        <div style="font-size:26px;font-weight:700"><?= number_format($totalCount) ?></div>
        <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Total Followers</div>
    </div>
    <div style="text-align:center;padding:14px;background:#e6f4ea;border-radius:10px">
        <div style="font-size:26px;font-weight:700;color:#1e7e34"><?= number_format($activeCount) ?></div>
        <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Active</div>
    </div>
    <div style="text-align:center;padding:14px;background:#e3f2fd;border-radius:10px">
        <div style="font-size:26px;font-weight:700;color:#1565c0"><?= number_format($categoryCount) ?></div>
        <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Category Follows</div>
    </div>
    <div style="text-align:center;padding:14px;background:#f3e5f5;border-radius:10px">
        <div style="font-size:26px;font-weight:700;color:#7b1fa2"><?= number_format($tagCount) ?></div>
        <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Tag Follows</div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="/admin/followers" class="tf-filter-bar">
    <input type="text" name="search"
           value="<?= h($search) ?>"
           placeholder="Search by email..."
           class="form-control"
           style="flex:1;min-width:200px">
    <select name="type" class="form-control" style="width:auto;min-width:140px">
        <option value="all"<?= $type === 'all' ? ' selected' : '' ?>>All Types</option>
        <option value="category"<?= $type === 'category' ? ' selected' : '' ?>>Category</option>
        <option value="tag"<?= $type === 'tag' ? ' selected' : '' ?>>Tag</option>
    </select>
    <button class="btn light" type="submit">Filter</button>
    <?php if ($search !== '' || $type !== 'all'): ?>
        <a class="btn light" href="/admin/followers">Reset</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Email</th>
                <th style="width:90px">Type</th>
                <th>Topic Name</th>
                <th style="width:90px">Status</th>
                <th style="width:130px">Subscribed</th>
                <th style="text-align:right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($followers)): ?>
                <tr>
                    <td colspan="6" class="muted" style="text-align:center;padding:40px 0;">
                        <?= ($search !== '' || $type !== 'all') ? 'No followers found matching your filters.' : 'No topic followers yet.' ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($followers as $f): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:#333"><?= h($f['email']) ?></div>
                        </td>
                        <td>
                            <span class="tf-type-pill tf-type-<?= h($f['follow_type']) ?>">
                                <?= $f['follow_type'] === 'category' ? 'Category' : 'Tag' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($f['topic_name'])): ?>
                                <?= h($f['topic_name']) ?>
                            <?php else: ?>
                                <span class="muted" style="font-size:12px;opacity:.4">(deleted)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $f['is_active'] ? 'badge-yes' : 'badge-pending' ?>">
                                <?= $f['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <span class="muted" style="font-size:13px">
                                <?= h(date('M j, Y', strtotime((string)$f['created_at']))) ?>
                            </span>
                        </td>
                        <td style="text-align:right;white-space:nowrap;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <form method="POST" action="/admin/followers/<?= h((string)$f['id']) ?>/toggle">
                                    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                    <button class="btn light small" type="submit">
                                        <?= $f['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                    </button>
                                </form>
                                <form method="POST" action="/admin/followers/<?= h((string)$f['id']) ?>/delete"
                                      data-confirm="Remove this topic follower?" data-confirm-title="Remove Follower" data-confirm-level="warn" data-confirm-ok="Remove">
                                    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                    <button class="btn danger small" type="submit">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:18px;flex-wrap:wrap">
        <?php if ($page > 1): ?>
            <a class="btn light small" href="/admin/followers?page=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $type !== 'all' ? '&type=' . urlencode($type) : '' ?>">&#8592; Prev</a>
        <?php endif; ?>
        <span class="muted" style="align-self:center;font-size:13px">
            Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($filteredTotal) ?> results)
        </span>
        <?php if ($page < $totalPages): ?>
            <a class="btn light small" href="/admin/followers?page=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $type !== 'all' ? '&type=' . urlencode($type) : '' ?>">Next &#8594;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
/* Filter bar */
.tf-filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    background: #f9f9f9;
    padding: 12px;
    border-radius: 12px;
    align-items: center;
    flex-wrap: wrap;
}

/* Type pills */
.tf-type-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.tf-type-category { background: #e3f2fd; color: #1565c0; }
.tf-type-tag      { background: #f3e5f5; color: #7b1fa2; }

/* Table */
.table-container {
    overflow: auto;
    border: 1px solid #e2e2e2;
    border-radius: 12px;
    background: #fff;
}
.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.admin-table th {
    text-align: left;
    padding: 12px;
    background: #fafafa;
    border-bottom: 1px solid #e2e2e2;
    color: #666;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.05em;
}
.admin-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.admin-table tr:last-child td {
    border-bottom: none;
}
.badge {
    font-size: 11px;
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 10px;
    text-transform: uppercase;
}
.badge-yes     { background: #e6f4ea; color: #1e7e34; }
.badge-pending { background: #fff8e1; color: #e65100; }
.btn.small { padding: 6px 12px; font-size: 12px; }
.form-control {
    padding: 10px 12px;
    border: 1px solid #e2e2e2;
    border-radius: 8px;
}

/* Alerts */
.alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; font-weight:500; }
.alert-success { background:#ecfdf5; color:#059669; border:1px solid #86efac; }
.alert-error   { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }

/* Page head */
.page-head { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
.page-head h1 { font-size:22px; font-weight:800; margin:0 0 4px; }
.page-head p { margin:0; font-size:14px; }

@media (max-width:768px) {
    .tf-filter-bar { flex-direction:column; }
    .page-head { flex-direction:column; align-items:flex-start; }
}
</style>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';

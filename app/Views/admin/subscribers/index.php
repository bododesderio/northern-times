<?php
declare(strict_types=1);

/** @var array $subscribers */
/** @var string|null $q */
/** @var int $page */
/** @var int $totalPages */
/** @var int $filteredTotal */
/** @var int $totalCount */
/** @var int $activeCount */
/** @var string|null $flash_success */
/** @var string|null $flash_error */
/** @var string $csrf */

$activeNav = 'subscribers';
ob_start();
?>

<div class="card">
    <header style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 24px;">
        <div>
            <h1 style="margin: 0 0 6px;">Subscribers</h1>
            <div class="muted">Manage newsletter subscribers captured from the front-end sign-up form.</div>
        </div>
        <a class="btn light" href="/admin/subscribers/export">Export CSV</a>
    </header>

    <?php if (!empty($flash_success)): ?>
        <div class="flash ok" style="margin-bottom: 12px;"><?= h($flash_success) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($flash_error)): ?>
        <div class="flash bad" style="margin-bottom: 12px;"><?= h($flash_error) ?></div>
    <?php endif; ?>

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
        <div style="text-align:center;padding:14px;background:#f8f8f8;border-radius:10px">
            <div style="font-size:26px;font-weight:700"><?= number_format($totalCount) ?></div>
            <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Total</div>
        </div>
        <div style="text-align:center;padding:14px;background:#e6f4ea;border-radius:10px">
            <div style="font-size:26px;font-weight:700;color:#1e7e34"><?= number_format($activeCount) ?></div>
            <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Active</div>
        </div>
        <div style="text-align:center;padding:14px;background:#fff8e1;border-radius:10px">
            <div style="font-size:26px;font-weight:700;color:#e65100"><?= number_format($totalCount - $activeCount) ?></div>
            <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">Inactive</div>
        </div>
    </div>

    <form method="GET" action="/admin/subscribers" class="search-form">
        <input name="q" 
               value="<?= h($q ?? '') ?>" 
               placeholder="Search email or name…" 
               class="form-control" 
               style="flex: 1; min-width: 240px;">
        <button class="btn light" type="submit">Search</button>
        <?php if ($q): ?>
            <a class="btn light" href="/admin/subscribers">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th style="width:100px">Status</th>
                    <th style="width:130px">Subscribed</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                    <tr>
                        <td colspan="5" class="muted" style="text-align: center; padding: 40px 0;">
                            <?= $q ? 'No subscribers found matching your search.' : 'No subscribers yet.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:#333"><?= h($s['email']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($s['name'])): ?>
                                    <?= h($s['name']) ?>
                                <?php else: ?>
                                    <span class="muted" style="font-size:12px;opacity:.4">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= ($s['status'] === 'active') ? 'badge-yes' : 'badge-pending' ?>">
                                    <?= ($s['status'] === 'active') ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <span class="muted" style="font-size:13px">
                                    <?= h(date('M j, Y', strtotime((string)$s['created_at']))) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <form method="POST" action="/admin/subscribers/<?= h((string)$s['id']) ?>/toggle">
                                        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                        <button class="btn light small" type="submit" title="Toggle confirmation">
                                            <?= ($s['status'] === 'active') ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="/admin/subscribers/<?= h((string)$s['id']) ?>/delete"
                                          data-confirm="Remove this subscriber?" data-confirm-title="Remove Subscriber" data-confirm-level="warn" data-confirm-ok="Remove">
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
                <a class="btn light small" href="/admin/subscribers?page=<?= $page - 1 ?><?= $q ? '&q=' . urlencode($q) : '' ?>">← Prev</a>
            <?php endif; ?>
            <span class="muted" style="align-self:center;font-size:13px">
                Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($filteredTotal) ?> results)
            </span>
            <?php if ($page < $totalPages): ?>
                <a class="btn light small" href="/admin/subscribers?page=<?= $page + 1 ?><?= $q ? '&q=' . urlencode($q) : '' ?>">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .search-form {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        background: #f9f9f9;
        padding: 12px;
        border-radius: 12px;
        align-items: center;
    }
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
    .badge-yes { background: #e6f4ea; color: #1e7e34; }
    .badge-pending { background: #fff8e1; color: #e65100; }
    .btn.small { padding: 6px 12px; font-size: 12px; }
    .form-control {
        padding: 10px 12px;
        border: 1px solid #e2e2e2;
        border-radius: 8px;
    }
</style>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
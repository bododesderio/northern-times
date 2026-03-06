<?php
declare(strict_types=1);

/** @var array $categories */
/** @var string|null $q */
/** @var string|null $flash_success */
/** @var string|null $flash_error */
/** @var string $csrf */

$activeNav = 'categories';
ob_start();
?>

<div class="card" style="max-width: 1100px;">
    <header style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 24px;">
        <div>
            <h1 style="margin: 0 0 6px;">Categories</h1>
            <div class="muted">Manage categories for your <code>/category/{slug}</code> pages and navigation.</div>
        </div>
        <a class="btn" href="/admin/categories/create">+ New Category</a>
    </header>

    <?php if (!empty($flash_success)): ?>
        <div class="flash ok" style="margin-bottom: 12px;"><?= h($flash_success) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($flash_error)): ?>
        <div class="flash bad" style="margin-bottom: 12px;"><?= h($flash_error) ?></div>
    <?php endif; ?>

    <form method="GET" action="/admin/categories" class="search-form">
        <input name="q" 
               value="<?= h($q ?? '') ?>" 
               placeholder="Search name or slug…" 
               class="form-control" 
               style="flex: 1; min-width: 240px;">
        <button class="btn light" type="submit">Search</button>
        <?php if ($q): ?>
            <a class="btn light" href="/admin/categories">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Parent</th>
                    <th>Slug</th>
                    <th style="width: 80px;">Nav</th>
                    <th style="width: 80px;">Order</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="muted" style="text-align: center; padding: 40px 0;">
                            No categories found matching your search.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #333;"><?= h($c['name']) ?></div>
                                <?php if (!empty($c['description'])): ?>
                                    <div class="muted" style="margin-top: 4px; font-size: 13px; max-width: 300px;">
                                        <?= h($c['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['parent_name'])): ?>
                                    <span class="muted" style="font-size:13px"><?= h($c['parent_name']) ?></span>
                                <?php else: ?>
                                    <span class="muted" style="font-size:12px;opacity:.4">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="slug-badge"><?= h($c['slug']) ?></code>
                                <div style="margin-top: 6px; font-size: 12px;">
                                    <a href="/category/<?= h($c['slug']) ?>" target="_blank" rel="noopener">View Live ↗</a>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= ((int)($c['show_in_nav'] ?? 0) === 1) ? 'badge-yes' : 'badge-no' ?>">
                                    <?= ((int)($c['show_in_nav'] ?? 0) === 1) ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= ((int)($c['show_in_sidebar'] ?? 0) === 1) ? 'badge-yes' : 'badge-no' ?>">
                                    <?= ((int)($c['show_in_sidebar'] ?? 0) === 1) ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-variant-numeric: tabular-nums;"><?= (int)($c['sort_order'] ?? 0) ?></span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a class="btn light small" href="/admin/categories/<?= h($c['id']) ?>/edit">Edit</a>
                                    
                                    <form method="POST" action="/admin/categories/<?= h($c['id']) ?>/delete" 
                                          data-confirm="Delete this category? This cannot be undone." data-confirm-title="Delete Category" data-confirm-level="danger" data-confirm-ok="Delete">
                                        <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                                        <button class="btn danger small" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
        vertical-align: top;
    }
    .admin-table tr:last-child td {
        border-bottom: none;
    }
    .slug-badge {
        background: #f0f2f5;
        padding: 2px 8px;
        border-radius: 6px;
        font-family: monospace;
        color: #444;
    }
    .badge {
        font-size: 11px;
        font-weight: bold;
        padding: 2px 8px;
        border-radius: 10px;
        text-transform: uppercase;
    }
    .badge-yes { background: #e6f4ea; color: #1e7e34; }
    .badge-no { background: #fce8e6; color: #d93025; }
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
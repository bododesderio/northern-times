<?php
declare(strict_types=1);

/** @var array $category */
/** @var string $mode */
/** @var string|null $flash_error */
/** @var string $csrf */

$isEdit    = ($mode ?? 'create') === 'edit';
$pageTitle = $isEdit ? 'Edit Category' : 'New Category';
$activeNav = 'categories';

// Form action logic
$action = $isEdit 
    ? '/admin/categories/' . h((string)$category['id']) 
    : '/admin/categories';

ob_start();
?>

<div class="card" style="max-width: 980px;">
    <header style="margin-bottom: 20px;">
        <h1 style="margin: 0 0 6px;"><?= h($pageTitle) ?></h1>
        <p class="muted" style="margin: 0;">
            Slug powers the URL: <code>/category/{slug}</code>. 
            If left empty, it will be generated from the name.
        </p>
    </header>

    <?php if (!empty($flash_error)): ?>
        <div class="flash bad" style="margin-bottom: 20px;">
            <?= h($flash_error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= h($action) ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">

        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Name *</label>
            <input name="name" 
                   type="text" 
                   required 
                   value="<?= h($category['name'] ?? '') ?>" 
                   class="form-control"
                   placeholder="Category name">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Slug (optional)</label>
            <input name="slug" 
                   type="text" 
                   value="<?= h($category['slug'] ?? '') ?>" 
                   class="form-control"
                   placeholder="e.g. world, politics, business">
            <small class="muted" style="display: block; margin-top: 6px;">
                Tip: use simple lowercase words. No spaces. Hyphens are fine.
            </small>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Description (optional)</label>
            <textarea name="description" 
                      class="form-control" 
                      style="min-height: 110px; resize: vertical;"><?= h($category['description'] ?? '') ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px;">
            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input type="number" 
                       name="sort_order" 
                       value="<?= h((string)($category['sort_order'] ?? 50)) ?>" 
                       class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Show in Navbar</label>
                <select name="show_in_nav" class="form-control">
                    <option value="1" <?= ((int)($category['show_in_nav'] ?? 1) === 1) ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ((int)($category['show_in_nav'] ?? 1) === 0) ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Show in Sidebar</label>
                <select name="show_in_sidebar" class="form-control">
                    <option value="1" <?= ((int)($category['show_in_sidebar'] ?? 1) === 1) ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ((int)($category['show_in_sidebar'] ?? 1) === 0) ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 1px solid #eee; pt: 20px;">
            <button class="btn" type="submit">
                <?= $isEdit ? 'Update Category' : 'Create Category' ?>
            </button>
            
            <a class="btn light" href="/admin/categories">Cancel</a>

            <?php if ($isEdit): ?>
                <div style="margin-left: auto;">
                    <span class="muted" style="font-size: 12px;">Internal ID: <?= (int)$category['id'] ?></span>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
    .form-label {
        display: block;
        margin-bottom: 6px;
        font-family: Arial, sans-serif;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #666;
    }
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e2e2;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        border-color: #a0a0a0;
        outline: none;
    }
</style>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
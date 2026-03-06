<?php
$pageTitle = 'Review Queue';
$activeNav = 'review';
ob_start();
?>

<?php if (!empty($flash_success)): ?>
  <div class="alert alert-success"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="alert alert-danger"><?= h($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Review Queue</h1>
  <p class="muted">Articles submitted for editorial review</p>
</div>

<?php if (empty($articles)): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4">
      <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
    </svg>
    <h3>All clear!</h3>
    <p>No articles pending review right now.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Article</th>
          <th>Author</th>
          <th>Category</th>
          <th>Submitted</th>
          <th style="width:200px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($articles as $a): ?>
        <tr>
          <td>
            <a href="/admin/review/<?= h($a['id']) ?>" class="strong"><?= h($a['title']) ?></a>
          </td>
          <td><?= h($a['author_name'] ?? 'Staff') ?></td>
          <td><span class="badge grey"><?= h($a['category'] ?? '—') ?></span></td>
          <td><time><?= h($a['updated_at'] ? date('M j, g:i A', strtotime($a['updated_at'])) : '—') ?></time></td>
          <td>
            <div class="btn-group">
              <a href="/admin/review/<?= h($a['id']) ?>" class="btn sm light">Review</a>
              <form method="POST" action="/admin/review/<?= h($a['id']) ?>/approve" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                <button type="submit" class="btn sm success">Approve</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (($totalPages ?? 1) > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a class="btn light" href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
    <?php endif; ?>
    <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a class="btn light" href="?page=<?= $page + 1 ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
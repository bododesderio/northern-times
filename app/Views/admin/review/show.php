<?php
$pageTitle = 'Review: ' . h($article['title'] ?? 'Article');
$activeNav = 'review';
ob_start();
?>

<div class="page-header" style="margin-bottom:24px">
  <a href="/admin/review" class="btn light sm">&larr; Back to Queue</a>
  <h1 style="margin-top:12px">Review Article</h1>
</div>

<div class="review-layout" style="display:grid; grid-template-columns:1fr 340px; gap:28px; align-items:start;">

  <!-- Article preview -->
  <div class="card" style="padding:28px">
    <div style="margin-bottom:16px">
      <span class="badge yellow">Pending Review</span>
      <?php if (!empty($article['category_id'])): ?>
        <?php
          $cat = \App\Models\Category::find($article['category_id']);
        ?>
        <?php if ($cat): ?>
          <span class="badge grey"><?= h($cat['name']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <h2 style="font-size:28px; line-height:1.2; margin:0 0 12px;"><?= h($article['title']) ?></h2>

    <?php if (!empty($article['excerpt'])): ?>
      <p style="color:#666; font-size:15px; line-height:1.6; margin-bottom:16px; font-style:italic;"><?= h($article['excerpt']) ?></p>
    <?php endif; ?>

    <div class="meta" style="font-size:13px; color:#888; margin-bottom:20px;">
      By <strong><?= h($article['display_author'] ?? 'Staff') ?></strong>
      &middot; Updated <?= h(date('M j, Y g:i A', strtotime($article['updated_at'] ?? 'now'))) ?>
    </div>

    <?php if (!empty($article['featured_image'])): ?>
      <div style="margin-bottom:20px; border-radius:8px; overflow:hidden;">
        <img src="<?= h($article['featured_image']) ?>" alt="" style="width:100%; height:auto; display:block;">
      </div>
    <?php endif; ?>

    <div class="article-preview" style="font-size:16px; line-height:1.7; color:#333;">
      <?= $article['content'] ?? '' ?>
    </div>

    <?php if (!empty($article['review_notes'])): ?>
      <div style="margin-top:24px; padding:16px; background:#fef3cd; border-radius:8px; border-left:4px solid #ffc107;">
        <strong style="font-size:13px; text-transform:uppercase; letter-spacing:.05em;">Previous Review Notes</strong>
        <p style="margin:8px 0 0; font-size:14px;"><?= h($article['review_notes']) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Actions sidebar -->
  <div>
    <!-- Approve -->
    <div class="card" style="padding:20px; margin-bottom:16px; border-left:4px solid #28a745;">
      <h3 style="margin:0 0 12px; font-size:16px; color:#28a745;">Approve &amp; Publish</h3>
      <form method="POST" action="/admin/review/<?= h($article['id']) ?>/approve">
        <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Notes (optional)</label>
          <textarea name="notes" rows="3" class="form-control" placeholder="Great work! Published as-is." style="font-size:14px;"></textarea>
        </div>
        <button type="submit" class="btn success" style="width:100%">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:6px"><path d="M20 6L9 17l-5-5"/></svg>
          Approve &amp; Publish
        </button>
      </form>
    </div>

    <!-- Reject -->
    <div class="card" style="padding:20px; border-left:4px solid #dc3545;">
      <h3 style="margin:0 0 12px; font-size:16px; color:#dc3545;">Request Revision</h3>
      <form method="POST" action="/admin/review/<?= h($article['id']) ?>/reject">
        <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Feedback (required)</label>
          <textarea name="notes" rows="4" class="form-control" placeholder="Please revise the introduction and add sources..." required style="font-size:14px;"></textarea>
        </div>
        <button type="submit" class="btn danger" style="width:100%">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:6px"><path d="M18 6L6 18M6 6l12 12"/></svg>
          Send Back for Revision
        </button>
      </form>
    </div>

    <!-- Quick info -->
    <div class="card" style="padding:16px; font-size:13px; color:#666;">
      <div style="margin-bottom:8px"><strong>Article ID:</strong> <?= h(substr($article['id'] ?? '', 0, 8)) ?>...</div>
      <div style="margin-bottom:8px"><strong>Created:</strong> <?= h(date('M j, Y', strtotime($article['created_at'] ?? 'now'))) ?></div>
      <div style="margin-bottom:8px"><strong>Slug:</strong> <?= h($article['slug'] ?? '') ?></div>
      <div><strong>Word count:</strong> ~<?= number_format(str_word_count(strip_tags($article['content'] ?? ''))) ?></div>
    </div>
  </div>
</div>

<style>
  .review-layout .article-preview img { max-width: 100%; height: auto; border-radius: 6px; }
  .review-layout .article-preview h1,
  .review-layout .article-preview h2,
  .review-layout .article-preview h3 { margin-top: 20px; }
  .review-layout .article-preview blockquote { border-left: 3px solid #ddd; padding-left: 16px; color: #555; }
  @media (max-width: 900px) { .review-layout { grid-template-columns: 1fr !important; } }
</style>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
<?php
$pageTitle = 'Review Rewrite';
$activeNav = 'rewriter';
$slot = null;
ob_start();

$a = $article;
$origWc    = str_word_count(strip_tags($a['content'] ?? ''));
$rewriteWc = str_word_count(strip_tags($a['rewritten_content'] ?? ''));
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <a href="/admin/rewriter" style="font-size:13px;color:var(--accent,#cc0000);text-decoration:none">&larr; Back to Queue</a>
  </div>
  <div style="display:flex;gap:8px">
    <form method="POST" action="/admin/rewriter/<?= h($a['id']) ?>/approve" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <button type="submit" style="padding:10px 24px;background:#2e7d32;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">Approve &amp; Apply</button>
    </form>
    <form method="POST" action="/admin/rewriter/<?= h($a['id']) ?>/reject" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <button type="submit" data-confirm="Reject this rewrite? The AI-generated version will be discarded." data-confirm-title="Reject Rewrite" data-confirm-level="warn" data-confirm-ok="Reject" style="padding:10px 24px;background:#c62828;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">Reject</button>
    </form>
  </div>
</div>

<!-- Meta Bar -->
<div style="background:var(--surface,#fff);border:1px solid var(--border,#e2e2e2);border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;gap:24px;flex-wrap:wrap;font-size:13px;color:var(--muted,#888)">
  <span><strong>Model:</strong> <?= h($a['rewriter_model'] ?? '—') ?></span>
  <span><strong>Rewritten:</strong> <?= !empty($a['rewritten_at']) ? date('M j, Y g:i A', strtotime($a['rewritten_at'])) : '—' ?></span>
  <span><strong>Words:</strong> <?= $origWc ?> &rarr; <?= $rewriteWc ?>
    <?php $diff = $rewriteWc - $origWc; ?>
    <span style="color:<?= $diff > 0 ? '#2e7d32' : ($diff < 0 ? '#c62828' : '#888') ?>">(<?= $diff > 0 ? '+' : '' ?><?= $diff ?>)</span>
  </span>
</div>

<style>
  .rw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  @media(max-width:1000px) { .rw-grid { grid-template-columns: 1fr; } }
  .rw-panel { background: var(--surface,#fff); border: 1px solid var(--border,#e2e2e2); border-radius: 14px; overflow: hidden; }
  .rw-head { padding: 14px 20px; border-bottom: 1px solid var(--border,#e2e2e2); font-weight: 700; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
  .rw-body { padding: 20px; }
  .rw-title { font-size: 20px; font-weight: 800; line-height: 1.3; margin-bottom: 8px; }
  .rw-excerpt { font-size: 14px; color: var(--muted,#666); font-style: italic; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border,#f0f0f0); }
  .rw-content { font-size: 15px; line-height: 1.7; }
  .rw-content p { margin: 0 0 12px; }
  .rw-content img { max-width: 100%; border-radius: 8px; }
  .rw-wc { font-size: 11px; color: var(--muted,#aaa); }
</style>

<!-- Side-by-Side Comparison -->
<div class="rw-grid">
  <!-- Left: Original / Current Content -->
  <div class="rw-panel">
    <div class="rw-head">
      <span>Current Content</span>
      <span class="rw-wc"><?= number_format($origWc) ?> words</span>
    </div>
    <div class="rw-body">
      <div class="rw-title"><?= h($a['title'] ?? '') ?></div>
      <?php if (!empty($a['excerpt'])): ?>
        <div class="rw-excerpt"><?= h($a['excerpt']) ?></div>
      <?php endif; ?>
      <div class="rw-content"><?= $a['content'] ?? '' ?></div>
    </div>
  </div>

  <!-- Right: AI Rewrite -->
  <div class="rw-panel" style="border-color:#c5cae9">
    <div class="rw-head" style="background:#e8eaf6;color:#283593">
      <span>AI Rewrite</span>
      <span class="rw-wc"><?= number_format($rewriteWc) ?> words</span>
    </div>
    <div class="rw-body">
      <div class="rw-title"><?= h($a['rewritten_title'] ?? '') ?></div>
      <?php if (!empty($a['rewritten_excerpt'])): ?>
        <div class="rw-excerpt"><?= h($a['rewritten_excerpt']) ?></div>
      <?php endif; ?>
      <div class="rw-content"><?= $a['rewritten_content'] ?? '' ?></div>
    </div>
  </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>

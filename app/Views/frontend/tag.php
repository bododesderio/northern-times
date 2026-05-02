<?php
/**
 * TAG PAGE — Nocturnal Prestige Editorial Redesign
 * Reuses np-cat grid + sidebar patterns for tag archives
 */
$tag      = $tag ?? null;
$articles = $articles ?? [];
$result   = $result ?? ['total' => 0, 'page' => 1, 'pages' => 1];
$ads      = $ads ?? [];
$related  = $related ?? [];
if (!$tag) { echo "<h1>Tag not found</h1>"; return; }
?>
<div class="np-page-wrap">

  <!-- Tag Header -->
  <header class="np-cat-header">
    <div class="np-cat-accent-line"></div>
    <span class="np-cat-tag">Tag</span>
    <h1 class="np-cat-title">#<?= h($tag['name']) ?></h1>
    <p class="np-cat-desc"><?= $result['total'] ?> article<?= $result['total'] !== 1 ? 's' : '' ?> tagged &ldquo;<?= h($tag['name']) ?>&rdquo;</p>
  </header>

  <?php if (!empty($articles)): ?>
  <div class="np-cat-layout">

    <!-- Main Content: Article Grid -->
    <div class="np-cat-main">
      <div class="np-cat-article-grid">
        <?php foreach ($articles as $i => $a): ?>
        <article class="np-cat-card">
          <a href="/article/<?= h($a['slug']) ?>" class="np-cat-card-link">
            <div class="np-cat-card-img">
              <?php if (!empty($a['featured_image'])): ?>
                <img src="<?= h($a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>">
              <?php else: ?>
                <div class="np-cat-card-placeholder"></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($a['category'])): ?>
              <span class="np-cat-tag"><?= h($a['category']) ?></span>
            <?php endif; ?>
            <h3 class="np-cat-card-title"><?= h($a['title']) ?></h3>
            <div class="np-cat-card-meta">
              <span>By <?= h($a['author'] ?? 'Staff') ?></span>
              <span class="np-meta-dot">&bull;</span>
              <span><?= h(!empty($a['published_at']) ? time_ago((string)$a['published_at']) : '') ?></span>
              <?php if (!empty($a['reading_time'])): ?>
                <span class="np-meta-dot">&bull;</span>
                <span><?= (int)$a['reading_time'] ?> min read</span>
              <?php endif; ?>
            </div>
          </a>
        </article>
        <?php endforeach; ?>
      </div>

      <?php if ($result['pages'] > 1): ?>
      <nav class="np-pagination" aria-label="Tag pagination">
        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
          <?php if ($p === $result['page']): ?>
            <span class="np-pagination-current"><?= $p ?></span>
          <?php else: ?>
            <a href="/tag/<?= h($tag['slug']) ?>?page=<?= $p ?>" class="np-pagination-link"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>

      <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>
    </div>

    <!-- Sidebar -->
    <aside class="np-cat-sidebar">

      <!-- Related Tags -->
      <?php if (!empty($related)): ?>
      <section class="np-sidebar-section">
        <div class="np-sidebar-heading-accent">
          <h2 class="np-sidebar-heading-text">Related Tags</h2>
        </div>
        <div class="np-tag-cloud">
          <?php foreach (array_slice($related, 0, 12) as $rt): ?>
            <a href="/tag/<?= h($rt['slug']) ?>" class="np-tag-chip">#<?= h($rt['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Newsletter CTA -->
      <section class="np-sidebar-newsletter">
        <div class="np-sidebar-newsletter-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          <h2 class="np-sidebar-heading-text">The Daily Brief</h2>
        </div>
        <p class="np-sidebar-newsletter-desc">Never miss a story. Get our best journalism delivered daily.</p>
        <form class="np-sidebar-newsletter-form" action="/api/newsletter" method="POST">
          <input type="hidden" name="_csrf" value="<?= h(\App\Services\Csrf::token()) ?>">
          <input type="email" name="email" placeholder="EMAIL ADDRESS" required class="np-sidebar-input">
          <button type="submit" class="np-sidebar-btn">Subscribe Now</button>
        </form>
      </section>

    </aside>

  </div>

  <?php else: ?>
    <div class="np-empty-state">
      <h3>No articles with this tag yet</h3>
      <p>Articles will appear here when tagged.</p>
    </div>
  <?php endif; ?>

</div>

<?php
/**
 * CATEGORY PAGE — Nocturnal Prestige Editorial Redesign
 * 12-col grid: 9-col article grid + 3-col sidebar
 */
$category = $category ?? null;
$articles = $articles ?? [];
$ads = $ads ?? [];
$most = $most ?? [];
if (!$category) { echo "<h1>Not found</h1>"; return; }

$allArticles = $articles;
$gridArticles = $allArticles;
?>
<div class="np-page-wrap">

  <!-- Category Header -->
  <header class="np-cat-header">
    <div class="np-cat-accent-line"></div>
    <span class="np-cat-tag">Section</span>
    <h1 class="np-cat-title"><?= h($category['name']) ?></h1>
    <p class="np-cat-desc"><?= h($category['description'] ?: ('Deep-dive reporting and expert analysis on ' . strtolower($category['name']) . '.')) ?></p>
  </header>

  <?php if (!empty($gridArticles)): ?>
  <div class="np-cat-layout">

    <!-- Main Content: Article Grid -->
    <div class="np-cat-main">
      <div class="np-cat-grid-header">
        <h2 class="np-cat-grid-title">Latest in <?= h($category['name']) ?></h2>
      </div>

      <div class="np-cat-article-grid">
        <?php foreach ($gridArticles as $i => $a): ?>
        <article class="np-cat-card">
          <a href="/article/<?= h($a['slug']) ?>" class="np-cat-card-link">
            <div class="np-cat-card-img">
              <?php if (!empty($a['featured_image'])): ?>
                <img src="<?= h($a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>">
              <?php else: ?>
                <div class="np-cat-card-placeholder"></div>
              <?php endif; ?>
            </div>
            <span class="np-cat-tag"><?= h($a['category'] ?? $category['name']) ?></span>
            <h3 class="np-cat-card-title"><?= h($a['title']) ?></h3>
            <div class="np-cat-card-meta">
              <span>By <?= h($a['author'] ?? 'Staff') ?></span>
              <span class="np-meta-dot">&bull;</span>
              <span><?= h(!empty($a['published_at']) ? time_ago((string)$a['published_at']) : '') ?></span>
            </div>
          </a>
        </article>
        <?php endforeach; ?>
      </div>

      <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>
    </div>

    <!-- Sidebar -->
    <aside class="np-cat-sidebar">

      <!-- Trending in Category -->
      <?php if (!empty($most)): ?>
      <section class="np-sidebar-section">
        <div class="np-sidebar-heading-accent">
          <h2 class="np-sidebar-heading-text">Trending in <?= h($category['name']) ?></h2>
        </div>
        <div class="np-trending-list">
          <?php foreach (array_slice($most, 0, 4) as $i => $m): ?>
          <a href="/article/<?= h($m['slug']) ?>" class="np-trending-item">
            <span class="np-trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <div>
              <h4 class="np-trending-title"><?= h($m['title']) ?></h4>
              <?php if (!empty($m['views'])): ?>
                <span class="np-trending-reads"><?= number_format((int)$m['views']) ?> reads</span>
              <?php endif; ?>
            </div>
          </a>
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
        <p class="np-sidebar-newsletter-desc">Stay ahead of the curve with our morning briefing.</p>
        <form class="follow-form np-sidebar-newsletter-form" data-type="category" data-id="<?= h($category['id'] ?? '') ?>">
          <input type="email" placeholder="EMAIL ADDRESS" required class="np-sidebar-input">
          <button type="submit" class="np-sidebar-btn">Subscribe Now</button>
          <span class="follow-msg np-sidebar-msg"></span>
        </form>
      </section>

    </aside>

  </div>

  <?php else: ?>
    <div class="np-empty-state">
      <h3>Nothing published here yet</h3>
      <p>Articles will appear here when published.</p>
    </div>
  <?php endif; ?>

</div>

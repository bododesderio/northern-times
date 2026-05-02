<?php
/**
 * AUTHOR PAGE — Nocturnal Prestige Editorial Redesign
 * Author profile hero + article grid + sidebar
 */
$author   = $author ?? null;
$articles = $articles ?? [];
$result   = $result ?? ['total' => 0, 'page' => 1, 'pages' => 1];
$ads      = $ads ?? [];
if (!$author) { echo "<h1>Author not found</h1>"; return; }
$displayName = $author['display_name'] ?? $author['username'];
$avatar = $author['avatar_url'] ?? '/assets/default-avatar.svg';
?>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Person","name":"<?= h($displayName) ?>","url":"<?= h(app_url('/author/' . $author['username'])) ?>"<?php if (!empty($author['bio'])): ?>,"description":"<?= h(substr($author['bio'], 0, 200)) ?>"<?php endif; ?>,"jobTitle":"<?= h(ucfirst($author['role'] ?? 'writer')) ?>"}
</script>

<div class="np-page-wrap">

  <!-- Author Profile Header -->
  <header class="np-author-header">
    <div class="np-author-profile">
      <img src="<?= h($avatar) ?>" alt="<?= h($displayName) ?>" class="np-author-avatar" />
      <div class="np-author-info">
        <div class="np-cat-accent-line"></div>
        <span class="np-cat-tag"><?= h(ucfirst($author['role'] ?? 'Writer')) ?></span>
        <h1 class="np-author-name"><?= h($displayName) ?></h1>
        <?php if (!empty($author['bio'])): ?>
          <p class="np-author-bio"><?= h($author['bio']) ?></p>
        <?php endif; ?>
        <p class="np-author-stats"><?= $result['total'] ?> article<?= $result['total'] !== 1 ? 's' : '' ?> published</p>
      </div>
    </div>
  </header>

  <?php if (!empty($articles)): ?>
  <div class="np-cat-layout">

    <!-- Main Content: Article Grid -->
    <div class="np-cat-main">
      <div class="np-cat-grid-header">
        <h2 class="np-cat-grid-title">Articles by <?= h($displayName) ?></h2>
      </div>

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
            <p class="np-cat-card-excerpt"><?= h($a['excerpt'] ?: excerpt((string)($a['content'] ?? ''), 100)) ?></p>
            <div class="np-cat-card-meta">
              <span><?= h(!empty($a['published_at']) ? date('M j, Y', strtotime((string)$a['published_at'])) : '') ?></span>
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
      <nav class="np-pagination" aria-label="Author articles pagination">
        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
          <?php if ($p === $result['page']): ?>
            <span class="np-pagination-current"><?= $p ?></span>
          <?php else: ?>
            <a href="/author/<?= h($author['username']) ?>?page=<?= $p ?>" class="np-pagination-link"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>

      <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>
    </div>

    <!-- Sidebar -->
    <aside class="np-cat-sidebar">

      <!-- Newsletter CTA -->
      <section class="np-sidebar-newsletter">
        <div class="np-sidebar-newsletter-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          <h2 class="np-sidebar-heading-text">The Daily Brief</h2>
        </div>
        <p class="np-sidebar-newsletter-desc">Stay ahead of the curve with our morning briefing.</p>
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
      <h3>No published articles yet</h3>
      <p>Articles by <?= h($displayName) ?> will appear here when published.</p>
    </div>
  <?php endif; ?>

</div>

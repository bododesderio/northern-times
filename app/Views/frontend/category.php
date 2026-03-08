<?php
$category = $category ?? null;
$articles = $articles ?? [];
$ads = $ads ?? [];
if (!$category) { echo "<h1>Not found</h1>"; return; }

$siteAbbr = get_site_setting('site_abbreviation', '') ?: mb_strtoupper(mb_substr(preg_replace('/\s+.*/u', '', get_site_setting('site_title', 'News')), 0, 3)) ?: 'NEWS';
$lead = !empty($articles) ? $articles[0] : null;
$sideStories = array_slice($articles, 1, 2);
$gridStories = array_slice($articles, 3);
?>
<section class="category-page">
  <header class="cat-page-header">
    <h1><?= h($category['name']) ?></h1>
    <p class="muted"><?= h($category['description'] ?: ('Latest stories in ' . $category['name'] . '.')) ?></p>
  </header>

  <?php if ($lead): ?>
  <!-- Lead + 2 stacked -->
  <div class="cat-top-row">
    <article class="cat-lead">
      <a href="/article/<?= h($lead['slug']) ?>">
        <?php if (!empty($lead['featured_image'])): ?>
          <div class="cat-lead-img">
            <img src="<?= h($lead['featured_image']) ?>" alt="<?= h($lead['title']) ?>" loading="eager" />
          </div>
        <?php else: ?>
          <div class="cat-lead-placeholder"><?= h($siteAbbr) ?></div>
        <?php endif; ?>
        <h3><?= h($lead['title']) ?></h3>
        <p class="cat-lead-excerpt"><?= h($lead['excerpt'] ?: excerpt((string)($lead['content'] ?? ''), 180)) ?></p>
        <div class="meta tiny">
          <?= h($lead['author'] ?? 'Staff') ?>
          <span class="dot">&middot;</span>
          <?= h($lead['published_at'] ? date('M j, Y', strtotime((string)$lead['published_at'])) : '') ?><?php if (!empty($lead['published_at'])): ?> &middot; <span title="<?= h(date('M j, Y', strtotime((string)$lead['published_at']))) ?>"><?= time_ago((string)$lead['published_at']) ?></span><?php endif; ?>
        </div>
      </a>
    </article>

    <?php if (!empty($sideStories)): ?>
    <div class="cat-side-stack">
      <?php foreach ($sideStories as $a): ?>
        <article class="cat-side-item">
          <a href="/article/<?= h($a['slug']) ?>">
            <div class="cat-side-row">
              <?php if (!empty($a['featured_image'])): ?>
                <img src="<?= h($a['featured_image']) ?>" alt="<?= h($a['title']) ?>" class="cat-side-thumb" loading="lazy" />
              <?php endif; ?>
              <div class="cat-side-text">
                <h4><?= h($a['title']) ?></h4>
                <p><?= h($a['excerpt'] ?: excerpt((string)($a['content'] ?? ''), 90)) ?></p>
                <div class="meta tiny">
                  <?= h($a['author'] ?? 'Staff') ?>
                  <span class="dot">&middot;</span>
                  <?= h($a['published_at'] ? date('M j, Y', strtotime((string)$a['published_at'])) : '') ?><?php if (!empty($a['published_at'])): ?> &middot; <?= time_ago((string)$a['published_at']) ?><?php endif; ?>
                </div>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ═══ IN-FEED AD ═══ -->
  <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>

  <!-- 4-column grid for rest -->
  <?php if (!empty($gridStories)): ?>
  <div class="cat-bottom-grid" style="margin-top:28px">
    <?php foreach ($gridStories as $a): ?>
      <article class="cat-grid-card">
        <a href="/article/<?= h($a['slug']) ?>">
          <?php if (!empty($a['featured_image'])): ?>
            <div class="cat-grid-img">
              <img src="<?= h($a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="lazy" />
            </div>
          <?php else: ?>
            <div class="cat-grid-placeholder"><?= h($siteAbbr) ?></div>
          <?php endif; ?>
          <div class="cat-grid-body">
            <h4><?= h($a['title']) ?></h4>
            <p class="cat-grid-excerpt"><?= h($a['excerpt'] ?: excerpt((string)($a['content'] ?? ''), 80)) ?></p>
            <div class="meta tiny">
              <?= h($a['author'] ?? 'Staff') ?>
              <span class="dot">&middot;</span>
              <?= h($a['published_at'] ? date('M j', strtotime((string)$a['published_at'])) : '') ?><?php if (!empty($a['published_at'])): ?> &middot; <?= time_ago((string)$a['published_at']) ?><?php endif; ?>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
    <div class="card" style="margin-top:24px">
      <h3 style="margin:0 0 6px;">Nothing published here yet</h3>
      <p class="muted" style="margin:0;">Articles will appear here when published.</p>
    </div>
  <?php endif; ?>
</section>
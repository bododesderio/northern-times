<?php
/**
 * HOME PAGE — Nocturnal Prestige Editorial Redesign
 * Dark-first premium layout with varied category sections
 */
$breaking          = $breaking          ?? [];
$hasManualBreaking = $hasManualBreaking ?? false;
$heroPool          = $heroPool          ?? [];
$topStories        = $topStories        ?? [];
$latest            = $latest            ?? [];
$most              = $most              ?? [];
$sections          = $sections          ?? [];
$ads               = $ads               ?? [];
$sidebarCats       = $sidebarCats       ?? [];

// Hero uses first article from hero pool
$hero              = $heroPool[0]            ?? null;

// Latest stories sidebar (8 articles)
$latestSidebar     = array_slice($latest, 0, 8);

// Top stories section uses remaining articles
$heroIds           = array_column($heroPool, 'id');
$remaining         = array_filter($topStories, fn($a) => !in_array($a['id'], $heroIds));
$remaining         = array_values($remaining);

// Read-time helper
function nt_rt(array $a): string {
    $mins = (int)($a['reading_time'] ?? 0);
    if ($mins > 0) return $mins . ' Min Read';
    if (!empty($a['content'])) {
        $w = str_word_count(strip_tags((string)$a['content']));
        $m = max(1, (int)ceil($w / 200));
        return $m . ' Min Read';
    }
    return '';
}
?>

<?= render_ad($ads, 'top-banner', 'ad-leaderboard') ?>

<?php if (!empty($breaking)): ?>
<?php $manual = $hasManualBreaking; ?>
<div class="ticker-bar<?= $manual ? '' : ' ticker-bar-trending' ?>">
  <span class="ticker-badge<?= $manual ? '' : ' ticker-trending' ?>">
    <span class="ticker-pulse"></span><?= $manual ? 'BREAKING' : 'TRENDING' ?>
  </span>
  <div class="ticker-track">
    <div class="ticker-scroll">
      <?php foreach (array_merge($breaking, $breaking) as $b): ?>
        <a class="ticker-item" href="/article/<?= h($b['slug']) ?>">
          <?= h($b['breaking_headline'] ?: $b['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="np-home">

<!-- ═══════════════════════════════════════════════════════════
     HERO SECTION — 8/4 grid: Major hero + Latest stories
═══════════════════════════════════════════════════════════ -->
<section class="np-hero-section">

  <!-- Major Hero Article -->
  <?php if ($hero): ?>
  <article class="np-hero-major">
    <a href="/article/<?= h($hero['slug']) ?>" class="np-hero-link">
      <div class="np-hero-img">
        <?php if (!empty($hero['featured_image'])): ?>
          <img src="<?= h($hero['featured_image']) ?>" alt="<?= h($hero['title']) ?>" loading="eager">
        <?php else: ?>
          <div class="np-hero-placeholder"></div>
        <?php endif; ?>
        <span class="np-badge-feature">Main Feature</span>
      </div>
      <h1 class="np-hero-title"><?= h($hero['title']) ?></h1>
      <p class="np-hero-excerpt"><?= h(excerpt_words($hero['excerpt'] ?: strip_tags($hero['content'] ?? ''), 30)) ?></p>
      <div class="np-meta">
        <?php $a = article_author($hero); ?>
        <span>By <?= h($a['name']) ?></span>
        <span class="np-meta-dot">&bull;</span>
        <?php $rt = nt_rt($hero); if ($rt): ?>
          <span><?= h($rt) ?></span>
        <?php endif; ?>
      </div>
    </a>

    <!-- Hero Banner Ad — fills space below hero article, stays in hero column -->
    <?= render_ad($ads, 'hero-banner', 'ad-hero-banner') ?>
  </article>
  <?php endif; ?>

  <!-- Latest Stories Sidebar -->
  <div class="np-latest-sidebar">
    <h2 class="np-sidebar-heading">
      Latest Stories
      <svg class="np-arrow-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </h2>
    <div class="np-latest-list">
      <?php foreach ($latestSidebar as $i => $la): ?>
      <article class="np-latest-item<?= $i > 0 ? ' np-latest-bordered' : '' ?>">
        <a href="/article/<?= h($la['slug']) ?>" class="np-latest-link">
          <span class="np-cat-tag"><?= h($la['category'] ?? '') ?></span>
          <h3 class="np-latest-title"><?= h($la['title']) ?></h3>
          <p class="np-latest-excerpt"><?= h(excerpt_words($la['excerpt'] ?: strip_tags($la['content'] ?? ''), 20)) ?></p>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>

</section>


<!-- ═══════════════════════════════════════════════════════════
     CATEGORY SECTIONS — Varied layouts per section
═══════════════════════════════════════════════════════════ -->
<?php
$sectionIdx = 0;
foreach ($sections as $section):
  $catSlug     = $section['slug']     ?? '';
  $catName     = $section['name']     ?? '';
  $catArticles = $section['articles'] ?? [];
  if (empty($catArticles)) continue;
  $sectionIdx++;

  // Determine layout variant based on section index
  // 1st section: thumbnail grid (Politics-style)
  // 2nd section: bento grid (Technology-style)
  // 3rd+ sections: card grid (Economy-style)
  $variant = match(true) {
    $sectionIdx % 3 === 1 => 'thumbnail',
    $sectionIdx % 3 === 2 => 'bento',
    default               => 'cards',
  };
?>

<?php if ($sectionIdx > 1 && $sectionIdx % 2 === 0): ?>
  <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>
<?php endif; ?>

<?php if ($sectionIdx === 2 && get_site_setting('newsletter_enabled', '1') === '1'): ?>
<!-- Inline newsletter CTA between category sections -->
<div class="np-newsletter-banner">
  <div class="np-newsletter-banner-inner">
    <div class="np-newsletter-banner-text">
      <strong><?= h(get_site_setting('newsletter_title', 'Stay informed')) ?></strong>
      <span><?= h(get_site_setting('newsletter_intro', 'Get the best stories in your inbox. No spam.')) ?></span>
    </div>
    <form class="np-newsletter-banner-form" data-newsletter-form>
      <input type="hidden" name="_csrf" value="<?= h($csrf ?? \App\Services\Csrf::token()) ?>">
      <input type="email" name="email" placeholder="Email address" required>
      <button type="submit"><?= h(get_site_setting('newsletter_button', 'Subscribe')) ?></button>
    </form>
    <div class="np-newsletter-msg" style="font-size:13px;margin-top:4px;"></div>
  </div>
</div>
<?php endif; ?>

<section class="np-category-section" id="section-<?= h($catSlug) ?>">

  <!-- Section Header -->
  <div class="np-section-header">
    <div>
      <h2 class="np-section-title"><?= h($catName) ?></h2>
    </div>
    <a href="/category/<?= h($catSlug) ?>" class="np-view-all">View All <?= h($catName) ?></a>
  </div>

  <?php if ($variant === 'thumbnail'): ?>
  <!-- ── THUMBNAIL GRID: 3-col with square thumbnails ── -->
  <div class="np-thumbnail-grid">
    <?php foreach (array_slice($catArticles, 0, 9) as $i => $ga): ?>
    <a href="/article/<?= h($ga['slug']) ?>" class="np-thumb-card<?= $i >= 3 ? ' np-thumb-bordered' : '' ?>">
      <div class="np-thumb-img">
        <?php if (!empty($ga['featured_image'])): ?>
          <img src="<?= h($ga['featured_image']) ?>" alt="<?= h($ga['title']) ?>" loading="lazy">
        <?php else: ?>
          <div class="np-thumb-placeholder"></div>
        <?php endif; ?>
      </div>
      <div class="np-thumb-text">
        <span class="np-cat-tag"><?= h($ga['category'] ?? $catName) ?></span>
        <h4 class="np-thumb-title"><?= h($ga['title']) ?></h4>
        <?php $a = article_author($ga); ?>
        <span class="np-thumb-author">By <?= h($a['name']) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php elseif ($variant === 'bento'): ?>
  <!-- ── BENTO GRID: Large hero + small cards + text row ── -->
  <div class="np-bento-grid">
    <?php $bentoLead = $catArticles[0]; $bentoSmall = array_slice($catArticles, 1, 2); $bentoText = array_slice($catArticles, 3, 2); ?>

    <!-- Large hero card -->
    <a href="/article/<?= h($bentoLead['slug']) ?>" class="np-bento-hero">
      <?php if (!empty($bentoLead['featured_image'])): ?>
        <img src="<?= h($bentoLead['featured_image']) ?>" alt="<?= h($bentoLead['title']) ?>" loading="lazy">
      <?php endif; ?>
      <div class="np-bento-overlay">
        <span class="np-badge-feature">Special Report</span>
        <h3 class="np-bento-title"><?= h($bentoLead['title']) ?></h3>
      </div>
    </a>

    <!-- Small cards -->
    <?php foreach ($bentoSmall as $bs): ?>
    <a href="/article/<?= h($bs['slug']) ?>" class="np-bento-small">
      <span class="np-cat-tag"><?= h($bs['category'] ?? $catName) ?></span>
      <h4 class="np-bento-small-title"><?= h($bs['title']) ?></h4>
      <span class="np-read-more">Read Article <svg class="np-arrow-sm" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
    </a>
    <?php endforeach; ?>

    <!-- Text row -->
    <?php if (!empty($bentoText)): ?>
    <div class="np-bento-text-row">
      <?php foreach ($bentoText as $j => $bt): ?>
      <a href="/article/<?= h($bt['slug']) ?>" class="np-bento-text-item">
        <span class="np-bento-num"><?= str_pad((string)($j + 1), 2, '0', STR_PAD_LEFT) ?> / <?= h(strtoupper($bt['category'] ?? $catName)) ?></span>
        <h5 class="np-bento-text-title"><?= h($bt['title']) ?></h5>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <!-- ── CARD GRID: Image cards + text list ── -->
  <div class="np-cards-section">
    <!-- Top 3 image cards -->
    <div class="np-card-grid">
      <?php foreach (array_slice($catArticles, 0, 3) as $ca): ?>
      <a href="/article/<?= h($ca['slug']) ?>" class="np-image-card">
        <div class="np-image-card-img">
          <?php if (!empty($ca['featured_image'])): ?>
            <img src="<?= h($ca['featured_image']) ?>" alt="<?= h($ca['title']) ?>" loading="lazy">
          <?php else: ?>
            <div class="np-thumb-placeholder np-aspect-video"></div>
          <?php endif; ?>
        </div>
        <h3 class="np-image-card-title"><?= h($ca['title']) ?></h3>
        <p class="np-image-card-excerpt"><?= h(excerpt_words($ca['excerpt'] ?: strip_tags($ca['content'] ?? ''), 18)) ?></p>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Bottom text-only list -->
    <?php $textItems = array_slice($catArticles, 3, 6); ?>
    <?php if (!empty($textItems)): ?>
    <div class="np-text-list">
      <?php foreach ($textItems as $ti): ?>
      <a href="/article/<?= h($ti['slug']) ?>" class="np-text-item">
        <span class="np-text-cat"><?= h(strtoupper($ti['category'] ?? $catName)) ?></span>
        <h4 class="np-text-title"><?= h($ti['title']) ?></h4>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</section>

<?php endforeach; ?>

</div><!-- .np-home -->

<button class="scroll-top" id="scrollTopBtn" title="Back to top" aria-label="Scroll to top">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<script>
(function(){
  const btn = document.getElementById('scrollTopBtn');
  if (!btn) return;
  window.addEventListener('scroll', () => btn.classList.toggle('visible', scrollY > 400), {passive:true});
  btn.addEventListener('click', () => window.scrollTo({top:0,behavior:'smooth'}));
})();
</script>

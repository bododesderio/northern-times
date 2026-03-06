<?php
/**
 * HOME PAGE v4 — Full metadata on every card
 * title · excerpt · featured image · author · published time · read time
 */
$breaking          = $breaking          ?? [];
$hasManualBreaking = $hasManualBreaking ?? false;
$topStories        = $topStories        ?? [];
$latest            = $latest            ?? [];
$most              = $most              ?? [];
$sections          = $sections          ?? [];
$ads               = $ads               ?? [];
$sidebarCats       = $sidebarCats       ?? [];

// Distribute topStories into zones
$hero              = $topStories[0]          ?? null;
$stackArticles     = array_slice($topStories, 1, 2);
$gridArticles      = array_slice($topStories, 3, 6);
$remaining         = array_slice($topStories, 9);
$tsLead            = $remaining[0]           ?? null;
$tsGrid            = array_slice($remaining, 1, 4);

// Read-time: prefer stored column, fall back to word-count
function nt_rt(array $a): string {
    $mins = (int)($a['reading_time'] ?? 0);
    if ($mins > 0) return $mins . ' min read';
    if (!empty($a['content'])) {
        $w = str_word_count(strip_tags((string)$a['content']));
        $m = max(1, (int)ceil($w / 200));
        return $m . ' min read';
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
          <span class="ticker-cat"><?= h($b['category'] ?? '') ?></span>
          <?= h($b['breaking_headline'] ?: $b['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<section class="home">

<!-- ═══════════════════════════════════════════════════════════
     HERO ZONE  —  3 columns: sidebar | hero+grid | latest
═══════════════════════════════════════════════════════════ -->
<div class="hero-zone">

  <!-- LEFT: category sidebar -->
  <aside class="cat-sidebar">
    <div class="cat-sidebar-header">Categories</div>
    <nav class="cat-sidebar-list">
      <?php foreach ($sidebarCats as $sc): ?>
        <a href="/category/<?= h($sc['slug']) ?>"
           class="cat-sidebar-item<?= ($sc['new_count'] ?? 0) > 0 ? ' has-new' : '' ?>">
          <span class="cat-sidebar-name"><?= h($sc['name']) ?></span>
          <?php if (($sc['new_count'] ?? 0) > 0): ?>
            <span class="cat-sidebar-badge"><?= (int)$sc['new_count'] ?></span>
          <?php else: ?>
            <span class="cat-sidebar-count"><?= (int)($sc['article_count'] ?? 0) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <!-- CENTER: hero content -->
  <div class="hero-center">

    <!-- Top row: big hero + 2 stacked -->
    <div class="hero-top-row">

      <?php if ($hero): ?>
      <a href="/article/<?= h($hero['slug']) ?>" class="hero-card">
        <div class="hero-card-img">
          <?php if (!empty($hero['featured_image'])): ?>
            <img src="<?= h($hero['featured_image']) ?>" alt="<?= h($hero['title']) ?>" loading="eager">
          <?php else: ?><div class="hero-card-placeholder"></div><?php endif; ?>
          <span class="card-cat-badge"><?= h($hero['category'] ?? '') ?></span>
        </div>
        <div class="hero-card-body">
          <h2 class="hero-card-title"><?= h($hero['title']) ?></h2>
          <p class="hero-card-excerpt"><?= h(excerpt_words($hero['excerpt'] ?: strip_tags($hero['content'] ?? ''), 30)) ?></p>
          <div class="card-meta">
            <?php $a = article_author($hero); ?>
            <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <span class="card-author"><?= h($a['name']) ?></span>
            <span class="card-dot">·</span>
            <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span class="card-time"><?= time_ago($hero['published_at'] ?? '') ?></span>
            <?php $rt = nt_rt($hero); if ($rt): ?>
              <span class="card-dot">·</span>
              <span class="card-read"><?= h($rt) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endif; ?>

      <!-- 2 stacked cards -->
      <div class="hero-stack">
        <?php foreach ($stackArticles as $sa): ?>
        <a href="/article/<?= h($sa['slug']) ?>" class="stack-card">
          <div class="stack-card-img">
            <?php if (!empty($sa['featured_image'])): ?>
              <img src="<?= h($sa['featured_image']) ?>" alt="<?= h($sa['title']) ?>" loading="eager">
            <?php else: ?><div class="stack-card-placeholder"></div><?php endif; ?>
            <span class="card-cat-badge sm"><?= h($sa['category'] ?? '') ?></span>
          </div>
          <div class="stack-card-body">
            <h3 class="stack-card-title"><?= h($sa['title']) ?></h3>
            <p class="stack-card-excerpt"><?= h(excerpt_words($sa['excerpt'] ?: strip_tags($sa['content'] ?? ''), 16)) ?></p>
            <div class="card-meta sm">
              <?php $a = article_author($sa); ?>
              <span class="card-author"><?= h($a['name']) ?></span>
              <span class="card-dot">·</span>
              <span class="card-time"><?= time_ago($sa['published_at'] ?? '') ?></span>
              <?php $rt = nt_rt($sa); if ($rt): ?>
                <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

    </div><!-- .hero-top-row -->

    <!-- 3×2 grid -->
    <?php if (!empty($gridArticles)): ?>
    <div class="hero-grid-3x3">
      <?php foreach ($gridArticles as $hg): ?>
      <a href="/article/<?= h($hg['slug']) ?>" class="grid-card">
        <div class="grid-card-img">
          <?php if (!empty($hg['featured_image'])): ?>
            <img src="<?= h($hg['featured_image']) ?>" alt="<?= h($hg['title']) ?>" loading="lazy">
          <?php else: ?><div class="grid-card-placeholder"></div><?php endif; ?>
          <span class="card-cat-badge sm"><?= h($hg['category'] ?? '') ?></span>
        </div>
        <div class="grid-card-body">
          <h4 class="grid-card-title"><?= h($hg['title']) ?></h4>
          <p class="grid-card-excerpt"><?= h(excerpt_words($hg['excerpt'] ?: strip_tags($hg['content'] ?? ''), 14)) ?></p>
          <div class="card-meta sm">
            <?php $a = article_author($hg); ?>
            <span class="card-author"><?= h($a['name']) ?></span>
            <span class="card-dot">·</span>
            <span class="card-time"><?= time_ago($hg['published_at'] ?? '') ?></span>
            <?php $rt = nt_rt($hg); if ($rt): ?>
              <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- .hero-center -->

  <!-- RIGHT: latest feed -->
  <aside class="latest-sidebar">
    <div class="sidebar-section-header">
      <span class="sidebar-section-dot latest-dot"></span> Latest
    </div>
    <div class="latest-feed">
      <?php foreach (array_slice($latest, 0, 12) as $i => $la): ?>
      <a href="/article/<?= h($la['slug']) ?>" class="latest-item">
        <span class="latest-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <div class="latest-text">
          <span class="latest-cat"><?= h($la['category'] ?? '') ?></span>
          <span class="latest-title"><?= h($la['title']) ?></span>
          <span class="latest-time"><?= time_ago($la['published_at'] ?? '') ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </aside>

</div><!-- .hero-zone -->


<!-- ═══════════════════════════════════════════════════════════
     LATEST ARTICLES  —  lead + 2×2 grid + most-read sidebar
═══════════════════════════════════════════════════════════ -->
<?php if ($tsLead): ?>
<div class="section-block">
  <div class="section-header">
    <h2 class="section-title"><span class="section-accent"></span>Latest Articles</h2>
  </div>
  <div class="ts-layout">
    <div class="ts-main">
      <div class="ts-lead-grid">

        <a href="/article/<?= h($tsLead['slug']) ?>" class="ts-lead-card">
          <div class="lead-card-img">
            <?php if (!empty($tsLead['featured_image'])): ?>
              <img src="<?= h($tsLead['featured_image']) ?>" alt="<?= h($tsLead['title']) ?>" loading="lazy">
            <?php else: ?><div class="lead-card-placeholder"></div><?php endif; ?>
            <span class="card-cat-badge"><?= h($tsLead['category'] ?? '') ?></span>
          </div>
          <div class="lead-card-body">
            <h3 class="lead-card-title"><?= h($tsLead['title']) ?></h3>
            <p class="lead-card-excerpt"><?= h(excerpt_words($tsLead['excerpt'] ?: strip_tags($tsLead['content'] ?? ''), 30)) ?></p>
            <div class="card-meta">
              <?php $a = article_author($tsLead); ?>
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
              <span class="card-author"><?= h($a['name']) ?></span>
              <span class="card-dot">·</span>
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              <span class="card-time"><?= time_ago($tsLead['published_at'] ?? '') ?></span>
              <?php $rt = nt_rt($tsLead); if ($rt): ?>
                <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>

        <div class="ts-grid-2x2">
          <?php foreach ($tsGrid as $tg): ?>
          <a href="/article/<?= h($tg['slug']) ?>" class="grid-card">
            <div class="grid-card-img">
              <?php if (!empty($tg['featured_image'])): ?>
                <img src="<?= h($tg['featured_image']) ?>" alt="<?= h($tg['title']) ?>" loading="lazy">
              <?php else: ?><div class="grid-card-placeholder"></div><?php endif; ?>
              <span class="card-cat-badge sm"><?= h($tg['category'] ?? '') ?></span>
            </div>
            <div class="grid-card-body">
              <h4 class="grid-card-title"><?= h($tg['title']) ?></h4>
              <p class="grid-card-excerpt"><?= h(excerpt_words($tg['excerpt'] ?: strip_tags($tg['content'] ?? ''), 14)) ?></p>
              <div class="card-meta sm">
                <?php $a = article_author($tg); ?>
                <span class="card-author"><?= h($a['name']) ?></span>
                <span class="card-dot">·</span>
                <span class="card-time"><?= time_ago($tg['published_at'] ?? '') ?></span>
                <?php $rt = nt_rt($tg); if ($rt): ?>
                  <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>

      </div><!-- .ts-lead-grid -->
    </div><!-- .ts-main -->

    <aside class="most-read-sidebar">
      <div class="sidebar-section-header">
        <span class="sidebar-section-dot mostread-dot"></span> Most Read
      </div>
      <div class="most-read-list">
        <?php foreach (array_slice($most, 0, 8) as $i => $mr): ?>
        <a href="/article/<?= h($mr['slug']) ?>" class="most-read-item">
          <span class="most-read-rank"><?= $i + 1 ?></span>
          <div class="most-read-text">
            <span class="most-read-title"><?= h($mr['title']) ?></span>
            <span class="most-read-time"><?= time_ago($mr['published_at'] ?? '') ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════
     CATEGORY SECTIONS  —  lead · 2×3 grid · 4-col row
═══════════════════════════════════════════════════════════ -->
<?php
$sectionIdx = 0;
foreach ($sections as $section):
  $catSlug     = $section['slug']     ?? '';
  $catName     = $section['name']     ?? '';
  $catArticles = $section['articles'] ?? [];
  if (empty($catArticles)) continue;
  $sectionIdx++;

  $lead      = $catArticles[0];
  $gridCards = array_slice($catArticles, 1, 6);   // 2×3
  $rowCards  = array_slice($catArticles, 7, 4);   // 4-col
?>

<?php if ($sectionIdx > 1 && ($sectionIdx - 1) % 3 === 0): ?>
  <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>
<?php endif; ?>

<div class="section-block" id="section-<?= h($catSlug) ?>">
  <div class="section-header">
    <h2 class="section-title"><span class="section-accent"></span><?= h($catName) ?></h2>
    <a href="/category/<?= h($catSlug) ?>" class="section-viewall">View all →</a>
  </div>

  <!-- Lead + 2×3 grid -->
  <div class="cat-lead-grid">

    <a href="/article/<?= h($lead['slug']) ?>" class="cat-lead-card">
      <div class="lead-card-img">
        <?php if (!empty($lead['featured_image'])): ?>
          <img src="<?= h($lead['featured_image']) ?>" alt="<?= h($lead['title']) ?>" loading="lazy">
        <?php else: ?><div class="lead-card-placeholder"></div><?php endif; ?>
        <span class="card-cat-badge"><?= h($catName) ?></span>
      </div>
      <div class="lead-card-body">
        <h3 class="lead-card-title"><?= h($lead['title']) ?></h3>
        <p class="lead-card-excerpt"><?= h(excerpt_words($lead['excerpt'] ?: '', 30)) ?></p>
        <div class="card-meta">
          <?php $a = article_author($lead); ?>
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          <span class="card-author"><?= h($a['name']) ?></span>
          <span class="card-dot">·</span>
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          <span class="card-time"><?= time_ago($lead['published_at'] ?? '') ?></span>
          <?php $rt = nt_rt($lead); if ($rt): ?>
            <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </a>

    <div class="cat-grid-2x3">
      <?php foreach ($gridCards as $gc): ?>
      <a href="/article/<?= h($gc['slug']) ?>" class="grid-card">
        <div class="grid-card-img">
          <?php if (!empty($gc['featured_image'])): ?>
            <img src="<?= h($gc['featured_image']) ?>" alt="<?= h($gc['title']) ?>" loading="lazy">
          <?php else: ?><div class="grid-card-placeholder"></div><?php endif; ?>
          <span class="card-cat-badge sm"><?= h($gc['category'] ?? $catName) ?></span>
        </div>
        <div class="grid-card-body">
          <h4 class="grid-card-title"><?= h($gc['title']) ?></h4>
          <p class="grid-card-excerpt"><?= h(excerpt_words($gc['excerpt'] ?: '', 14)) ?></p>
          <div class="card-meta sm">
            <?php $a = article_author($gc); ?>
            <span class="card-author"><?= h($a['name']) ?></span>
            <span class="card-dot">·</span>
            <span class="card-time"><?= time_ago($gc['published_at'] ?? '') ?></span>
            <?php $rt = nt_rt($gc); if ($rt): ?>
              <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

  </div><!-- .cat-lead-grid -->

  <!-- 4-col row -->
  <?php if (!empty($rowCards)): ?>
  <div class="four-col-row">
    <?php foreach ($rowCards as $rc): ?>
    <a href="/article/<?= h($rc['slug']) ?>" class="four-col-card">
      <div class="four-col-img">
        <?php if (!empty($rc['featured_image'])): ?>
          <img src="<?= h($rc['featured_image']) ?>" alt="<?= h($rc['title']) ?>" loading="lazy">
        <?php else: ?><div class="grid-card-placeholder"></div><?php endif; ?>
        <span class="card-cat-badge sm"><?= h($rc['category'] ?? $catName) ?></span>
      </div>
      <div class="four-col-body">
        <h4 class="four-col-title"><?= h($rc['title']) ?></h4>
        <p class="four-col-excerpt"><?= h(excerpt_words($rc['excerpt'] ?: '', 12)) ?></p>
        <div class="card-meta sm">
          <?php $a = article_author($rc); ?>
          <span class="card-author"><?= h($a['name']) ?></span>
          <span class="card-dot">·</span>
          <span class="card-time"><?= time_ago($rc['published_at'] ?? '') ?></span>
          <?php $rt = nt_rt($rc); if ($rt): ?>
            <span class="card-dot">·</span><span class="card-read"><?= h($rt) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- .section-block -->

<?php endforeach; ?>

<button class="scroll-top" id="scrollTopBtn" title="Back to top" aria-label="Scroll to top">↑</button>

</section>

<script>
(function(){
  const btn = document.getElementById('scrollTopBtn');
  if (!btn) return;
  window.addEventListener('scroll', () => btn.classList.toggle('visible', scrollY > 400), {passive:true});
  btn.addEventListener('click', () => window.scrollTo({top:0,behavior:'smooth'}));
})();
</script>
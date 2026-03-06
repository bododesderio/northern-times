<?php
$tag      = $tag ?? null;
$articles = $articles ?? [];
$result   = $result ?? ['total' => 0, 'page' => 1, 'pages' => 1];
$ads      = $ads ?? [];
$siteAbbr = get_site_setting('site_abbreviation', '') ?: mb_strtoupper(mb_substr(preg_replace('/\s+.*/u', '', get_site_setting('site_title', 'News')), 0, 3)) ?: 'NEWS';
if (!$tag) { echo "<h1>Tag not found</h1>"; return; }
?>
<section class="category-page">
  <header class="cat-page-header">
    <h1>#<?= h($tag['name']) ?></h1>
    <p class="muted"><?= $result['total'] ?> article<?= $result['total'] !== 1 ? 's' : '' ?> tagged "<?= h($tag['name']) ?>"</p>
  </header>

  <?php if (!empty($articles)): ?>
  <div class="cat-bottom-grid">
    <?php foreach ($articles as $a): ?>
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
            <span class="cat-grid-badge"><?= h($a['category'] ?? '') ?></span>
            <h4><?= h($a['title']) ?></h4>
            <p class="cat-grid-excerpt"><?= h($a['excerpt'] ?: excerpt((string)($a['content'] ?? ''), 80)) ?></p>
            <div class="meta tiny">
              <?= h($a['author'] ?? 'Staff') ?>
              <span class="dot">&middot;</span>
              <?= h($a['published_at'] ? date('M j, Y', strtotime((string)$a['published_at'])) : '') ?>
              <?php if (!empty($a['reading_time'])): ?>
                <span class="dot">&middot;</span> <?= (int)$a['reading_time'] ?> min read
              <?php endif; ?>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($result['pages'] > 1): ?>
  <div style="text-align:center;margin:32px 0;display:flex;gap:8px;justify-content:center">
    <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
      <?php if ($p === $result['page']): ?>
        <span style="padding:8px 14px;border-radius:6px;background:var(--accent,#cc0000);color:#fff;font-weight:600"><?= $p ?></span>
      <?php else: ?>
        <a href="/tag/<?= h($tag['slug']) ?>?page=<?= $p ?>" style="padding:8px 14px;border-radius:6px;border:1px solid var(--border);color:var(--ink);text-decoration:none"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?= render_ad($ads, 'in-feed', 'ad-in-feed') ?>

  <?php else: ?>
    <div class="card" style="margin-top:24px">
      <h3 style="margin:0 0 6px">No articles with this tag yet</h3>
      <p class="muted" style="margin:0">Articles will appear here when tagged.</p>
    </div>
  <?php endif; ?>
</section>
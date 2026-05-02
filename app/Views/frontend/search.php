<?php
/**
 * SEARCH PAGE — Nocturnal Prestige Editorial Redesign
 * Dense list results + sidebar (top stories, tags, newsletter)
 */
$q = $q ?? '';
$articles = $articles ?? [];
$tags = $tags ?? [];
$trending = $trending ?? [];
?>
<div class="np-page-wrap">

  <!-- Search Results Header -->
  <header class="np-search-header">
    <?php if ($q !== ''): ?>
      <div class="np-search-title-row">
        <h1 class="np-search-title">Search Results for:</h1>
        <span class="np-search-query"><?= h($q) ?></span>
      </div>
      <div class="np-search-accent-line"></div>
    <?php else: ?>
      <h1 class="np-search-title">Search</h1>
    <?php endif; ?>
  </header>

  <!-- Search input with live dropdown -->
  <div class="search-wrapper np-search-wrapper">
    <div class="np-search-input-box">
      <svg class="np-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="liveSearch" value="<?= h($q) ?>" placeholder="Search headlines, topics, authors…" autocomplete="off" />
      <span id="searchSpinner" class="search-spinner" style="display:none"></span>
    </div>
    <!-- Live dropdown -->
    <div id="liveResults" class="live-results" style="display:none"></div>
  </div>

  <?php if ($q !== ''): ?>
    <div class="np-search-meta">
      <?= count($articles) ?> result<?= count($articles) !== 1 ? 's' : '' ?> for <strong>"<?= h($q) ?>"</strong>
    </div>
  <?php endif; ?>

  <div class="np-search-layout">

    <!-- Main Content: Search Results -->
    <section class="np-search-main">
      <?php if (!empty($articles)): ?>
      <div class="np-search-results">
        <?php foreach ($articles as $a): ?>
        <article class="np-search-result">
          <a href="/article/<?= h($a['slug']) ?>" class="np-search-result-link">
            <?php if (!empty($a['featured_image'])): ?>
            <div class="np-search-thumb">
              <img src="<?= h($a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="lazy">
            </div>
            <?php endif; ?>
            <div class="np-search-result-body">
              <div class="np-search-result-tags">
                <?php if (!empty($a['category'])): ?>
                  <span class="np-search-badge"><?= h($a['category']) ?></span>
                <?php endif; ?>
                <?php if (!empty($a['published_at'])): ?>
                  <span class="np-search-date"><?= h(date('M j, Y', strtotime((string)$a['published_at']))) ?></span>
                <?php endif; ?>
              </div>
              <h3 class="np-search-result-title"><?= h($a['title']) ?></h3>
              <p class="np-search-result-excerpt"><?= h($a['excerpt'] ?: excerpt((string)($a['content'] ?? ''), 160)) ?></p>
              <div class="np-search-result-meta">
                <span>By <?= h($a['author'] ?? 'Staff') ?></span>
              </div>
            </div>
          </a>
        </article>
        <?php endforeach; ?>
      </div>
      <?php elseif ($q !== ''): ?>
      <div class="np-empty-state">
        <h3>No results found</h3>
        <p>Try different keywords or browse our categories above.</p>
      </div>
      <?php endif; ?>
    </section>

    <!-- Sidebar -->
    <aside class="np-search-sidebar">

      <!-- Top Stories -->
      <?php if (!empty($trending)): ?>
      <section class="np-sidebar-section">
        <div class="np-sidebar-heading-accent">
          <h2 class="np-sidebar-heading-text">Top Stories</h2>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--np-accent)" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="np-trending-list">
          <?php foreach (array_slice($trending, 0, 3) as $t): ?>
          <a href="/article/<?= h($t['slug']) ?>" class="np-trending-story">
            <span class="np-cat-tag"><?= h($t['category'] ?? '') ?></span>
            <h4 class="np-trending-title"><?= h($t['title']) ?></h4>
            <?php if (!empty($t['published_at'])): ?>
              <span class="np-trending-reads"><?= time_ago((string)$t['published_at']) ?></span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Popular Tags -->
      <?php if (!empty($tags)): ?>
      <section class="np-sidebar-section">
        <div class="np-sidebar-heading-border">
          <h2 class="np-sidebar-heading-text">Popular Tags</h2>
        </div>
        <div class="np-tag-cloud">
          <?php foreach (array_slice($tags, 0, 8) as $t): ?>
            <a href="/tag/<?= h($t['slug']) ?>" class="np-tag-chip">#<?= h($t['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Newsletter CTA -->
      <section class="np-sidebar-newsletter">
        <h2 class="np-sidebar-newsletter-title">The Briefing</h2>
        <p class="np-sidebar-newsletter-desc">Get the most important stories delivered to your inbox every morning.</p>
        <form class="np-sidebar-newsletter-form" action="/api/newsletter" method="POST">
          <input type="hidden" name="_csrf" value="<?= h(\App\Services\Csrf::token()) ?>">
          <input type="email" name="email" placeholder="Your email address" required class="np-sidebar-input">
          <button type="submit" class="np-sidebar-btn">Sign Up Free</button>
        </form>
      </section>

    </aside>

  </div>

</div>

<script>
(function(){
  const input = document.getElementById('liveSearch');
  const dropdown = document.getElementById('liveResults');
  const spinner = document.getElementById('searchSpinner');
  let timer = null;
  let controller = null;

  input.addEventListener('input', function(){
    clearTimeout(timer);
    const q = this.value.trim();

    if (q.length < 2) {
      dropdown.style.display = 'none';
      return;
    }

    spinner.style.display = 'block';
    timer = setTimeout(async () => {
      if (controller) controller.abort();
      controller = new AbortController();

      try {
        const res = await fetch('/api/search?q=' + encodeURIComponent(q), { signal: controller.signal });
        const data = await res.json();
        spinner.style.display = 'none';

        if (!data.results || data.results.length === 0) {
          dropdown.innerHTML = '<div class="live-empty">No results found</div>';
          dropdown.style.display = 'block';
          return;
        }

        function esc(s) {
          const d = document.createElement('div');
          d.textContent = s;
          return d.innerHTML;
        }

        let html = '';
        data.results.forEach(r => {
          html += `<a class="live-item" href="/article/${encodeURIComponent(r.slug)}">
            ${r.image ? `<img src="${esc(r.image)}" alt="${esc(r.title)}" class="live-thumb" />` : ''}
            <div class="live-text">
              <span class="live-cat">${esc(r.category)}</span>
              <span class="live-title">${esc(r.title)}</span>
              <span class="live-date">${esc(r.date || '')}</span>
            </div>
          </a>`;
        });
        html += `<a class="live-all" href="/search?q=${encodeURIComponent(q)}">View all results →</a>`;
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
      } catch(e) {
        if (e.name !== 'AbortError') spinner.style.display = 'none';
      }
    }, 250);
  });

  // Submit on Enter
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
      dropdown.style.display = 'none';
      window.location.href = '/search?q=' + encodeURIComponent(this.value.trim());
    }
  });

  // Close dropdown on click outside
  document.addEventListener('click', function(e){
    if (!e.target.closest('.search-wrapper')) dropdown.style.display = 'none';
  });

  // Focus input on page load
  if (!input.value) input.focus();
})();
</script>

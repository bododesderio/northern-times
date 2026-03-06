<?php
$q = $q ?? '';
$articles = $articles ?? [];
?>
<section class="search-page">
  <header class="cat-page-header">
    <h1>Search</h1>
  </header>

  <!-- Search input with live dropdown -->
  <div class="search-wrapper">
    <div class="search-input-box">
      <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="text" id="liveSearch" value="<?= h($q) ?>" placeholder="Search headlines, topics, authors…" autocomplete="off" />
      <span id="searchSpinner" class="search-spinner" style="display:none"></span>
    </div>
    <!-- Live dropdown -->
    <div id="liveResults" class="live-results" style="display:none"></div>
  </div>

  <!-- Full results (shown after submit or on page load with ?q=) -->
  <?php if ($q !== ''): ?>
    <div class="search-meta">
      <?= count($articles) ?> result<?= count($articles) !== 1 ? 's' : '' ?> for <strong>"<?= h($q) ?>"</strong>
    </div>
  <?php endif; ?>

  <?php if (!empty($articles)): ?>
  <div class="search-results-grid">
    <?php foreach ($articles as $a): ?>
      <article class="search-result-card">
        <a href="/article/<?= h($a['slug']) ?>">
          <div class="search-result-row">
            <?php if (!empty($a['featured_image'])): ?>
              <div class="search-result-img">
                <img src="<?= h($a['featured_image']) ?>" alt="" loading="lazy" />
              </div>
            <?php endif; ?>
            <div class="search-result-text">
              <span class="kicker-sm"><?= h($a['category'] ?? '') ?></span>
              <h3><?= h($a['title']) ?></h3>
              <p><?= h($a['excerpt'] ?: excerpt((string)($a['content'] ?? ''), 140)) ?></p>
              <div class="meta tiny">
                <?php if (!empty($a['published_at'])): ?>
                  <time title="<?= h(date('F j, Y', strtotime((string)$a['published_at']))) ?>"><?= h(date('M j, Y', strtotime((string)$a['published_at']))) ?></time>
                  <span class="dot">&middot;</span>
                  <span><?= time_ago((string)$a['published_at']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
  <?php elseif ($q !== ''): ?>
    <div class="card" style="margin-top:24px;text-align:center;padding:40px">
      <h3 style="margin:0 0 8px">No results found</h3>
      <p class="muted" style="margin:0">Try different keywords or browse our categories above.</p>
    </div>
  <?php endif; ?>
</section>

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

        let html = '';
        data.results.forEach(r => {
          html += `<a class="live-item" href="/article/${r.slug}">
            ${r.image ? `<img src="${r.image}" alt="" class="live-thumb" />` : ''}
            <div class="live-text">
              <span class="live-cat">${r.category}</span>
              <span class="live-title">${r.title}</span>
              <span class="live-date">${r.date}</span>
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
<?php
declare(strict_types=1);

$siteAbbr = get_site_setting('site_abbreviation', '')
            ?: mb_strtoupper(mb_substr(preg_replace('/\s+.*/u', '', get_site_setting('site_title', 'News')), 0, 3))
            ?: 'NEWS';

$article        = $article ?? [];
$readMins       = $readMins ?? 1;
$related        = $related ?? [];
$author         = $author ?? [];
$threadArticles = $threadArticles ?? [];
$comments       = $comments ?? [];
$ads            = $ads ?? [];

$articleUrl = app_url('/article/' . ($article['slug'] ?? ''));
$title      = $article['title'] ?? 'Untitled';
$category   = $article['category'] ?? '';
$catSlug    = $article['category_slug'] ?? '';
$pubDate    = !empty($article['published_at']) ? date('F j, Y', strtotime((string)$article['published_at'])) : '';
$pubTime    = !empty($article['published_at']) ? date('g:i A', strtotime((string)$article['published_at'])) : '';
$isCrawled  = !empty($article['is_crawled']);
// $author is always the live DB profile (super_admin for crawled, publisher for own articles)
$authorName     = !empty($author['name']) ? $author['name'] : ($author['username'] ?? 'Staff');
$authorUsername = $isCrawled ? '' : ($author['username'] ?? ''); // never link to profile for crawled
$sourceName = $article['source_name'] ?? '';
$sourceUrl  = $article['source_url'] ?? '';
$content    = $article['content'] ?? '';
$heroImg    = $article['featured_image'] ?? '';
$excerpt    = $article['excerpt'] ?? '';

// Inject story thread inline (after 3rd paragraph) if available
$inlineThreadHtml = '';
if (!empty($threadArticles)) {
    $inlineThreadHtml = '<aside class="thread-inline"><div class="thread-inline-label">More in this story</div>';
    foreach ($threadArticles as $t) {
        $inlineThreadHtml .= '<a href="/article/' . h($t['slug']) . '">' . h($t['title']);
        if (!empty($t['published_at'])) {
            $inlineThreadHtml .= ' <span class="muted tiny">' . date('M j', strtotime((string)$t['published_at'])) . '</span>';
        }
        $inlineThreadHtml .= '</a>';
    }
    $inlineThreadHtml .= '</aside>';

    // Insert after 3rd </p> tag
    $parts = preg_split('#(</p>)#i', $content, 5, PREG_SPLIT_DELIM_CAPTURE);
    if (count($parts) >= 7) {
        array_splice($parts, 6, 0, [$inlineThreadHtml]);
        $content = implode('', $parts);
    } elseif (count($parts) >= 3) {
        $content .= $inlineThreadHtml;
    }
}

// Author role label
$roleMap = [
  'super_admin' => 'Editor-in-Chief',
  'editor'      => 'Editor',
  'author'      => 'Staff Writer',
  'contributor' => 'Contributor',
];
$authorRole = $roleMap[$author['role'] ?? ''] ?? 'Staff Writer';

// Check if sidebar ad is active
$hasSidebarAd = false;
foreach ($ads as $ad) {
  if (($ad['slot_name'] ?? '') === 'article-sidebar' && !empty($ad['is_active'])) {
    $hasSidebarAd = true;
    break;
  }
}

// Tags for this article (Phase 10)
$articleTags = [];
try { $articleTags = \App\Models\Tag::forArticle($article['id'] ?? ''); } catch (\Throwable) {}
?>

<?= render_ad($ads, 'top-banner', 'ad-leaderboard') ?>

<article class="article" itemscope itemtype="https://schema.org/NewsArticle">
  <header class="article-header">
    <?php if ($catSlug): ?>
      <div class="kicker"><a href="/category/<?= h($catSlug) ?>"><?= h($category) ?></a></div>
    <?php endif; ?>

    <h1 class="article-title" itemprop="headline"><?= h($title) ?></h1>

    <div class="article-meta">
      <span itemprop="author" itemscope itemtype="https://schema.org/Person">
        <?php if (!$isCrawled && $authorUsername): ?>
          <a href="/author/<?= h($authorUsername) ?>" style="color:inherit;text-decoration:underline dotted;text-underline-offset:3px" itemprop="url">
            <span itemprop="name"><?= h($authorName) ?></span>
          </a>
        <?php else: ?>
          <span itemprop="name"><?= h($authorName) ?></span>
        <?php endif; ?>
      </span>
      <?php /* ── Source info icon — ONLY for crawled articles, nowhere else ── */ ?>
      <?php if ($isCrawled && $sourceUrl): ?>
        <button
          class="src-info-btn"
          type="button"
          aria-label="View original source"
          data-src-url="<?= h($sourceUrl) ?>"
          data-src-name="<?= h($sourceName ?: 'Original article') ?>"
          title="View original source"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="8.5"/>
            <line x1="12" y1="11" x2="12" y2="16"/>
          </svg>
        </button>
      <?php endif; ?>
      <span class="dot">&middot;</span>
      <time datetime="<?= h($article['published_at'] ?? '') ?>" itemprop="datePublished" title="<?= h($pubDate . ($pubTime ? ' at ' . $pubTime : '')) ?>"><?= h($pubDate) ?><?php if (!empty($article['published_at'])): ?> <span class="muted" style="font-size:.85em">(<?= time_ago((string)$article['published_at']) ?>)</span><?php endif; ?></time>
      <?php if ($pubTime): ?>
        <span class="dot">&middot;</span>
        <span><?= h($pubTime) ?></span>
      <?php endif; ?>
      <span class="dot">&middot;</span>
      <span><?= $readMins ?> min read</span>
      <?php if (!empty($article['views']) && (int)$article['views'] > 50): ?>
        <span class="dot">&middot;</span>
        <span><?= number_format((int)$article['views']) ?> views</span>
      <?php endif; ?>
    </div>

    <div style="margin-top:16px;display:flex;justify-content:center">
      <?= share_buttons($articleUrl, $title, $excerpt) ?>
    </div>
  </header>

  <?php if ($heroImg): ?>
    <div class="article-hero">
      <?= responsive_img($heroImg, $title, 'eager', 'high', '(max-width: 640px) 100vw, (max-width: 1024px) 80vw, 1000px') ?>
    </div>
  <?php endif; ?>

  <?php if ($hasSidebarAd): ?>
    <div class="article-with-sidebar">
      <div class="article-body" itemprop="articleBody">
        <?= safe_html($content) ?>
        <?= render_ad($ads, 'in-article', 'ad-in-article') ?>
      </div>
      <aside class="article-sidebar-col">
        <?= render_ad($ads, 'article-sidebar', 'ad-sidebar-slot') ?>
      </aside>
    </div>
  <?php else: ?>
    <div class="article-body" itemprop="articleBody">
      <?= safe_html($content) ?>
      <?= render_ad($ads, 'in-article', 'ad-in-article') ?>
    </div>
  <?php endif; ?>

  <!-- ── Tag Pills (Phase 10) ────────────────────────────────── -->
  <?php if (!empty($articleTags)): ?>
  <div class="article-tags" style="max-width:var(--content-max,780px);margin:28px auto 0;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
    <span style="font-weight:600;color:var(--muted,#666);font-size:13px;margin-right:4px">Tags:</span>
    <?php foreach ($articleTags as $t): ?>
      <a href="/tag/<?= h($t['slug']) ?>" class="article-tag-pill">#<?= h($t['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <footer class="article-footer">
    <div style="display:flex;justify-content:center">
      <?= share_buttons($articleUrl, $title, $excerpt) ?>
    </div>
  </footer>

  <?= render_ad($ads, 'below-article', 'ad-below-article') ?>

  <!-- ── Author Card — always shows live DB profile ──────────── -->
  <div class="author-card">
    <div class="author-card-top">
      <div class="author-card-avatar">
        <?php if (!empty($author['avatar_url'])): ?>
          <img src="<?= h($author['avatar_url']) ?>" alt="<?= h($authorName) ?>" loading="lazy" decoding="async" />
        <?php else: ?>
          <div class="avatar-initials"><?= strtoupper(mb_substr($authorName, 0, 1)) ?></div>
        <?php endif; ?>
      </div>
      <div class="author-card-name">
        <?php if ($authorUsername): ?>
          <a href="/author/<?= h($authorUsername) ?>" style="color:inherit;text-decoration:none">
            <h4 style="transition:color .15s" onmouseover="this.style.color='var(--accent,#cc0000)'" onmouseout="this.style.color='inherit'"><?= h($authorName) ?></h4>
          </a>
        <?php else: ?>
          <h4><?= h($authorName) ?></h4>
        <?php endif; ?>
        <span class="author-card-role"><?= h($authorRole) ?></span>
      </div>
    </div>

    <?php if (!empty($author['bio'])): ?>
      <div class="author-card-bio"><p><?= h($author['bio']) ?></p></div>
    <?php endif; ?>

    <?php
      $hasSocials = !empty($author['twitter_handle']) || !empty($author['facebook_url'])
                 || !empty($author['linkedin_url']) || !empty($author['instagram_handle'])
                 || !empty($author['whatsapp_number']) || !empty($author['website_url']);
    ?>
    <?php if ($hasSocials): ?>
      <div class="author-card-socials">
        <?php if (!empty($author['twitter_handle'])): ?>
          <a href="https://twitter.com/<?= h($author['twitter_handle']) ?>" target="_blank" rel="noopener" class="social-icon" title="@<?= h($author['twitter_handle']) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($author['facebook_url'])): ?>
          <a href="<?= h($author['facebook_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="Facebook">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($author['instagram_handle'])): ?>
          <a href="https://instagram.com/<?= h($author['instagram_handle']) ?>" target="_blank" rel="noopener" class="social-icon" title="@<?= h($author['instagram_handle']) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($author['linkedin_url'])): ?>
          <a href="<?= h($author['linkedin_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="LinkedIn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($author['whatsapp_number'])): ?>
          <a href="https://wa.me/<?= h($author['whatsapp_number']) ?>" target="_blank" rel="noopener" class="social-icon" title="WhatsApp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (!empty($author['website_url'])): ?>
          <a href="<?= h($author['website_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="<?= h($author['website_url']) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($authorUsername && !$isCrawled): ?>
      <div style="margin-top:12px;text-align:center">
        <a href="/author/<?= h($authorUsername) ?>" style="font-size:13px;color:var(--accent,#cc0000);text-decoration:none;font-weight:600">
          View all articles by <?= h($authorName) ?> →
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Story Thread ────────────────────────────────────────── -->
  <?php if (!empty($threadArticles)): ?>
    <div class="thread-banner">
      <div class="thread-label">More in this story</div>
      <div class="thread-links">
        <?php foreach ($threadArticles as $t): ?>
          <a href="/article/<?= h($t['slug']) ?>">
            <?= h($t['title']) ?>
            <?php if (!empty($t['published_at'])): ?>
              <span class="muted tiny"><?= date('M j', strtotime((string)$t['published_at'])) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ── Comments Section ────────────────────────────────────── -->
  <div class="comments-section" style="max-width:var(--content-max,1000px);margin:40px auto 0">
    <h3 style="font-size:20px;font-weight:800;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid var(--ink,#121212)">
      <?php if (!empty($comments)): ?>
        <?= count($comments) ?> Comment<?= count($comments) !== 1 ? 's' : '' ?>
      <?php else: ?>
        Leave a Comment
      <?php endif; ?>
    </h3>

    <!-- Existing comments -->
    <?php if (!empty($comments)): ?>
      <div id="commentsList">
        <?php foreach ($comments as $c): ?>
          <div class="comment-item" style="display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border,#e2e2e2)">
            <div style="flex-shrink:0">
              <div style="width:40px;height:40px;border-radius:50%;background:var(--border,#e2e2e2);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--muted,#999)">
                <?= strtoupper(mb_substr($c['author_name'] ?? 'A', 0, 1)) ?>
              </div>
            </div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:6px">
                <strong style="font-size:14px"><?= h($c['author_name'] ?? 'Anonymous') ?></strong>
                <?php if (!empty($c['created_at'])): ?>
                  <span class="muted" style="font-size:12px"><?= date('M j, Y \a\t g:i A', strtotime((string)$c['created_at'])) ?></span>
                <?php endif; ?>
              </div>
              <div style="font-size:15px;line-height:1.6;color:var(--ink,#333)">
                <?= nl2br(h($c['content'] ?? '')) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div id="commentsList"></div>
      <p class="muted" style="margin-bottom:20px;font-size:14px" id="noCommentsMsg">Be the first to comment on this article.</p>
    <?php endif; ?>

    <!-- Comment form -->
    <div id="commentForm" style="margin-top:24px;padding:24px;background:var(--surface,#fff);border:1px solid var(--border,#e2e2e2);border-radius:12px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Name <span style="color:#c00">*</span></label>
          <input type="text" id="commentName" placeholder="Your name" required maxlength="100"
                 style="width:100%;padding:10px 14px;border:1px solid var(--border,#e2e2e2);border-radius:8px;font:inherit;font-size:14px" />
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Email <span style="color:var(--muted,#999);font-weight:400">(optional, not shown)</span></label>
          <input type="email" id="commentEmail" placeholder="your@email.com" maxlength="255"
                 style="width:100%;padding:10px 14px;border:1px solid var(--border,#e2e2e2);border-radius:8px;font:inherit;font-size:14px" />
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Comment <span style="color:#c00">*</span></label>
        <textarea id="commentBody" rows="4" placeholder="Share your thoughts…" required maxlength="5000"
                  style="width:100%;padding:10px 14px;border:1px solid var(--border,#e2e2e2);border-radius:8px;font:inherit;font-size:14px;resize:vertical"></textarea>
      </div>
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink,#555);cursor:pointer">
            <input type="checkbox" id="commentSubscribe" style="width:16px;height:16px;accent-color:var(--accent,#c00);cursor:pointer" />
            Subscribe me to the newsletter
          </label>
        </div>
        <button type="button" id="commentSubmitBtn"
                style="padding:10px 24px;background:var(--ink,#121212);color:#fff;border:none;border-radius:8px;font:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .15s">
          Post Comment
        </button>
        <span id="commentStatus" style="font-size:13px;display:none"></span>
      </div>
    </div>
  </div>

  <!-- Comment submission JS -->
  <script>
  (function() {
    var articleId = <?= json_encode($article['id'] ?? '') ?>;
    var submitBtn = document.getElementById('commentSubmitBtn');
    var statusEl  = document.getElementById('commentStatus');
    var listEl    = document.getElementById('commentsList');
    var noMsg     = document.getElementById('noCommentsMsg');

    submitBtn.addEventListener('click', function() {
      var name  = document.getElementById('commentName').value.trim();
      var email = document.getElementById('commentEmail').value.trim();
      var body  = document.getElementById('commentBody').value.trim();

      if (!name || !body) {
        statusEl.style.display = 'inline';
        statusEl.style.color = '#c00';
        statusEl.textContent = 'Name and comment are required.';
        return;
      }

      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.6';
      statusEl.style.display = 'inline';
      statusEl.style.color = 'var(--muted,#666)';
      statusEl.textContent = 'Posting…';

      var csrfMeta = document.querySelector('meta[name="x-csrf-token"]');
      var csrfToken = csrfMeta ? csrfMeta.content : '';
      fetch('/api/comment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'article_id=' + encodeURIComponent(articleId)
            + '&author_name=' + encodeURIComponent(name)
            + '&author_email=' + encodeURIComponent(email)
            + '&content=' + encodeURIComponent(body)
            + '&subscribe_newsletter=' + (document.getElementById('commentSubscribe').checked ? '1' : '0')
            + '&_csrf=' + encodeURIComponent(csrfToken)
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';

        if (data.ok) {
          statusEl.style.color = '#15803d';
          statusEl.textContent = 'Comment posted!';

          // Add comment to list
          if (noMsg) noMsg.style.display = 'none';
          var c = data.comment;
          var initial = (c.author_name || 'A').charAt(0).toUpperCase();
          function escComment(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
          var safeName = escComment(c.author_name || 'Anonymous');
          var safeContent = escComment(c.content || '').replace(/\n/g, '<br>');
          var safeTime = escComment(c.time_label || '');
          var html = '<div class="comment-item" style="display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border,#e2e2e2)">'
            + '<div style="flex-shrink:0"><div style="width:40px;height:40px;border-radius:50%;background:var(--border,#e2e2e2);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--muted,#999)">' + initial + '</div></div>'
            + '<div style="flex:1;min-width:0">'
            + '<div style="display:flex;align-items:baseline;gap:10px;margin-bottom:6px"><strong style="font-size:14px">' + safeName + '</strong><span class="muted" style="font-size:12px">' + safeTime + '</span></div>'
            + '<div style="font-size:15px;line-height:1.6;color:var(--ink,#333)">' + safeContent + '</div>'
            + '</div></div>';
          listEl.insertAdjacentHTML('beforeend', html);

          // Clear form
          document.getElementById('commentBody').value = '';
          setTimeout(function() { statusEl.style.display = 'none'; }, 3000);

          // Update heading count
          var heading = document.querySelector('.comments-section h3');
          var items = listEl.querySelectorAll('.comment-item');
          if (heading && items.length) {
            heading.textContent = items.length + ' Comment' + (items.length !== 1 ? 's' : '');
          }
        } else {
          statusEl.style.color = '#c00';
          statusEl.textContent = data.message || 'Failed to post comment.';
        }
      })
      .catch(function() {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        statusEl.style.color = '#c00';
        statusEl.textContent = 'Network error. Please try again.';
      });
    });
  })();
  </script>

  <!-- ── Related Articles ────────────────────────────────────── -->
  <?php if (!empty($related)): ?>
    <div class="related">
      <div class="section-divider"><span class="section-label">Related Stories</span></div>
      <div class="related-grid">
        <?php foreach (array_slice($related, 0, 4) as $r): ?>
          <a class="related-card" href="/article/<?= h($r['slug']) ?>">
            <?php if (!empty($r['featured_image'])): ?>
              <img class="related-thumb" src="<?= h(thumbnail_url($r['featured_image'])) ?>" alt="<?= h($r['title']) ?>" loading="lazy" decoding="async" />
            <?php else: ?>
              <div class="related-placeholder"><?= h($siteAbbr) ?></div>
            <?php endif; ?>
            <div class="related-title"><?= h($r['title']) ?></div>
            <div class="muted tiny" style="padding:0 14px 12px">
              <?= h($r['author'] ?? 'Staff') ?>
              <span class="dot">&middot;</span>
              <?= h(!empty($r['published_at']) ? date('M j', strtotime((string)$r['published_at'])) : '') ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</article>

<?php /* ── Source info button + popup (crawled articles only) ── */ ?>
<?php if ($isCrawled && $sourceUrl): ?>
<style>
/* Source info icon button */
.src-info-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 17px;
  height: 17px;
  padding: 0;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--muted, #888);
  vertical-align: middle;
  margin-left: 2px;
  border-radius: 50%;
  transition: color .15s, transform .15s;
  position: relative;
  top: -1px;
  flex-shrink: 0;
}
.src-info-btn:hover { color: var(--accent, #cc0000); transform: scale(1.15); }
.src-info-btn svg { width: 15px; height: 15px; }

/* Source popup */
.src-popup-overlay {
  position: fixed; inset: 0; z-index: 8000;
  display: none;
}
.src-popup-overlay.open { display: block; }
.src-popup {
  position: fixed;
  background: var(--surface, #fff);
  border: 1px solid var(--border, #e2e2e2);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,.14);
  padding: 14px 16px;
  max-width: 300px;
  width: max-content;
  z-index: 8001;
  font-size: 13px;
  line-height: 1.5;
  color: var(--ink, #121212);
  animation: srcPopIn .15s ease both;
}
@keyframes srcPopIn {
  from { opacity:0; transform: translateY(-4px) scale(.97); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}
.src-popup-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--muted, #888);
  margin-bottom: 6px;
}
.src-popup-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--accent, #cc0000);
  font-weight: 600;
  text-decoration: none;
  word-break: break-all;
}
.src-popup-link:hover { text-decoration: underline; }
.src-popup-link svg { width: 12px; height: 12px; flex-shrink: 0; }
</style>

<div class="src-popup-overlay" id="srcPopupOverlay"></div>
<div class="src-popup" id="srcPopup" style="display:none" role="tooltip">
  <div class="src-popup-label">Original Source</div>
  <a class="src-popup-link" id="srcPopupLink" href="<?= h($sourceUrl) ?>" target="_blank" rel="nofollow noopener">
    <?= h($sourceName ?: parse_url($sourceUrl, PHP_URL_HOST) ?: $sourceUrl) ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
    </svg>
  </a>
</div>

<script>
(function () {
  'use strict';
  var btn      = document.querySelector('.src-info-btn');
  var overlay  = document.getElementById('srcPopupOverlay');
  var popup    = document.getElementById('srcPopup');
  if (!btn || !popup) return;

  var open = false;

  function openPopup() {
    var rect = btn.getBoundingClientRect();
    var top  = rect.bottom + window.scrollY + 6;
    var left = rect.left + window.scrollX;

    popup.style.display = 'block';
    popup.style.top  = top + 'px';
    popup.style.left = left + 'px';

    // Clamp to viewport right edge
    var popW = popup.offsetWidth;
    if (left + popW > window.innerWidth - 16) {
      popup.style.left = Math.max(8, window.innerWidth - popW - 16) + 'px';
    }

    overlay.classList.add('open');
    open = true;
  }

  function closePopup() {
    popup.style.display = 'none';
    overlay.classList.remove('open');
    open = false;
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    open ? closePopup() : openPopup();
  });

  overlay.addEventListener('click', closePopup);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && open) closePopup();
  });

  // Reposition on scroll/resize
  window.addEventListener('scroll', function () { if (open) closePopup(); }, { passive: true });
  window.addEventListener('resize', function () { if (open) closePopup(); }, { passive: true });
})();
</script>
<?php endif; ?>

<!-- Tag pill + sidebar styles -->
<style>
  .article-tag-pill {
    display:inline-block;padding:5px 14px;border-radius:20px;
    background:var(--surface,#f5f5f5);color:var(--ink,#333);
    font-size:13px;text-decoration:none;border:1px solid var(--border,#e0e0e0);
    transition:all .2s;font-weight:500;
  }
  .article-tag-pill:hover {
    background:var(--accent,#cc0000);color:#fff;
    border-color:var(--accent,#cc0000);
  }
  .article-with-sidebar {
    max-width: 1200px; margin: 40px auto 0;
    display: grid; grid-template-columns: 1fr 300px; gap: 40px; align-items: start;
  }
  .article-with-sidebar .article-body { max-width: none; margin: 0; }
  .article-sidebar-col { position: sticky; top: 24px; }
  @media (max-width: 900px) {
    .article-with-sidebar { grid-template-columns: 1fr; }
    .article-sidebar-col { position: static; }
  }
</style>

<!-- Copy link handler -->
<script>
document.querySelectorAll('.share-copy-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var url = this.getAttribute('data-url');
    var el = this;
    var originalHTML = el.innerHTML;
    navigator.clipboard.writeText(url).then(function() {
      el.textContent = 'Copied!';
      setTimeout(function() { el.innerHTML = originalHTML; }, 1500);
    }).catch(function() {
      var ta = document.createElement('textarea');
      ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select(); document.execCommand('copy');
      document.body.removeChild(ta);
      el.textContent = 'Copied!';
      setTimeout(function() { el.innerHTML = originalHTML; }, 1500);
    });
  });
});
</script>

<!-- JSON-LD -->
<script type="application/ld+json">
<?= json_encode(array_filter([
  '@context' => 'https://schema.org',
  '@type' => 'NewsArticle',
  'headline' => $title,
  'description' => $excerpt ?: excerpt($content, 160),
  'image' => $heroImg ? [app_url($heroImg)] : [],
  'datePublished' => $article['published_at'] ?? '',
  'dateModified' => $article['updated_at'] ?? $article['published_at'] ?? '',
  'author' => array_filter([
    '@type' => 'Person',
    'name' => $authorName,
    'url' => $authorUsername ? app_url('/author/' . $authorUsername) : null,
  ]),
  'publisher' => array_filter([
    '@type' => 'Organization',
    'name'  => site_name(),
    'logo'  => get_site_setting('site_logo_url') ? [
      '@type' => 'ImageObject',
      'url'   => app_url(get_site_setting('site_logo_url')),
    ] : null,
  ]),
  'mainEntityOfPage' => $articleUrl,
  'wordCount' => str_word_count(strip_tags($content)),
  'keywords' => !empty($articleTags) ? implode(', ', array_column($articleTags, 'name')) : null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT) ?>
</script>
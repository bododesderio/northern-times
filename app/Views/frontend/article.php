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

  <!-- ── Full-Bleed Hero ──────────────────────────────────────── -->
  <?php if ($heroImg): ?>
  <div class="np-hero">
    <div class="np-hero-image" style="background-image:url('<?= h($heroImg) ?>')">
      <?= responsive_img($heroImg, $title, 'eager', 'high', '(max-width: 640px) 100vw, 100vw') ?>
    </div>
    <div class="np-hero-overlay">
      <div class="np-hero-content">
        <?php if ($catSlug): ?>
          <a href="/category/<?= h($catSlug) ?>" class="np-badge-feature"><?= h($category) ?></a>
        <?php endif; ?>
        <h1 class="np-hero-title" itemprop="headline"><?= h($title) ?></h1>
        <div class="np-hero-meta">
          <div class="np-hero-meta-inner">
            <div class="np-hero-author">
              <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                <?php if (!$isCrawled && $authorUsername): ?>
                  <a href="/author/<?= h($authorUsername) ?>" class="np-hero-author-link" itemprop="url">
                    <span itemprop="name"><?= h($authorName) ?></span>
                  </a>
                <?php else: ?>
                  <span itemprop="name"><?= h($authorName) ?></span>
                <?php endif; ?>
              </span>
              <?php if ($isCrawled && $sourceUrl): ?>
                <button
                  class="src-info-btn"
                  type="button"
                  aria-label="View original source"
                  data-src-url="<?= h($sourceUrl) ?>"
                  data-src-name="<?= h($sourceName ?: 'Original article') ?>"
                  title="View original source"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </button>
              <?php endif; ?>
              <span class="np-hero-role"><?= h($authorRole) ?></span>
            </div>
            <div class="np-hero-date">
              <time datetime="<?= h($article['published_at'] ?? '') ?>" itemprop="datePublished" title="<?= h($pubDate . ($pubTime ? ' at ' . $pubTime : '')) ?>">
                <?= h($pubDate) ?>
              </time>
              <?php if (!empty($article['published_at'])): ?>
                <?php $relTime = time_ago((string)$article['published_at']); if (str_contains($relTime, 'ago') || $relTime === 'just now' || $relTime === 'yesterday'): ?>
                <span class="np-time-ago">(<?= $relTime ?>)</span>
              <?php endif; ?>
              <?php endif; ?>
              <span class="dot">&middot;</span>
              <span><?= $readMins ?> min read</span>
              <?php if (!empty($article['views']) && (int)$article['views'] > 50): ?>
                <span class="dot">&middot;</span>
                <span><?= number_format((int)$article['views']) ?> views</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <!-- No hero image fallback -->
  <header class="np-header-no-hero">
    <?php if ($catSlug): ?>
      <a href="/category/<?= h($catSlug) ?>" class="np-badge-feature"><?= h($category) ?></a>
    <?php endif; ?>
    <h1 class="np-hero-title" itemprop="headline"><?= h($title) ?></h1>
    <div class="np-hero-meta">
      <div class="np-hero-meta-inner">
        <div class="np-hero-author">
          <span itemprop="author" itemscope itemtype="https://schema.org/Person">
            <?php if (!$isCrawled && $authorUsername): ?>
              <a href="/author/<?= h($authorUsername) ?>" class="np-hero-author-link" itemprop="url">
                <span itemprop="name"><?= h($authorName) ?></span>
              </a>
            <?php else: ?>
              <span itemprop="name"><?= h($authorName) ?></span>
            <?php endif; ?>
          </span>
          <?php if ($isCrawled && $sourceUrl): ?>
            <button
              class="src-info-btn"
              type="button"
              aria-label="View original source"
              data-src-url="<?= h($sourceUrl) ?>"
              data-src-name="<?= h($sourceName ?: 'Original article') ?>"
              title="View original source"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </button>
          <?php endif; ?>
          <span class="np-hero-role"><?= h($authorRole) ?></span>
        </div>
        <div class="np-hero-date">
          <time datetime="<?= h($article['published_at'] ?? '') ?>" itemprop="datePublished" title="<?= h($pubDate . ($pubTime ? ' at ' . $pubTime : '')) ?>">
            <?= h($pubDate) ?>
          </time>
          <?php if (!empty($article['published_at'])): ?>
            <span class="np-time-ago">(<?= time_ago((string)$article['published_at']) ?>)</span>
          <?php endif; ?>
          <span class="dot">&middot;</span>
          <span><?= $readMins ?> min read</span>
          <?php if (!empty($article['views']) && (int)$article['views'] > 50): ?>
            <span class="dot">&middot;</span>
            <span><?= number_format((int)$article['views']) ?> views</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>
  <?php endif; ?>

  <!-- ── TL;DR Box ────────────────────────────────────────────── -->
  <?php if (!empty($article['ai_summary'])): ?>
  <div class="tldr-box">
    <button class="tldr-toggle" type="button" aria-expanded="false">
      <span class="tldr-label">TL;DR</span>
      <svg class="tldr-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="tldr-content" hidden>
      <p><?= h($article['ai_summary']) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Three-Column Article Layout ──────────────────────────── -->
  <div class="np-article-layout">

    <!-- Share Sidebar (left, desktop) -->
    <aside class="np-share-sidebar">
      <div class="np-share-sidebar-inner">
        <?= share_buttons($articleUrl, $title, $excerpt) ?>
        <button class="np-share-icon-btn bookmark-btn" id="bookmarkBtn"
                data-slug="<?= h($article['slug'] ?? '') ?>"
                data-title="<?= h($title) ?>"
                data-image="<?= h($heroImg) ?>"
                data-category="<?= h($category) ?>"
                data-date="<?= h($pubDate) ?>"
                aria-label="Save article" title="Save for later">
          <svg class="bookmark-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        </button>
      </div>
    </aside>

    <!-- Article Body (center) -->
    <div class="np-article-center">
      <!-- Mobile share bar -->
      <div class="np-mobile-share">
        <?= share_buttons($articleUrl, $title, $excerpt) ?>
        <button class="np-share-icon-btn bookmark-btn" id="bookmarkBtnMobile"
                data-slug="<?= h($article['slug'] ?? '') ?>"
                data-title="<?= h($title) ?>"
                data-image="<?= h($heroImg) ?>"
                data-category="<?= h($category) ?>"
                data-date="<?= h($pubDate) ?>"
                aria-label="Save article" title="Save for later">
          <svg class="bookmark-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        </button>
      </div>

      <?php if ($hasSidebarAd): ?>
        <div class="article-with-sidebar">
          <div class="article-body np-article-body np-drop-cap" itemprop="articleBody">
            <?= safe_html($content) ?>
            <?= render_ad($ads, 'in-article', 'ad-in-article') ?>
          </div>
          <aside class="article-sidebar-col">
            <?= render_ad($ads, 'article-sidebar', 'ad-sidebar-slot') ?>
          </aside>
        </div>
      <?php else: ?>
        <div class="article-body np-article-body np-drop-cap" itemprop="articleBody">
          <?= safe_html($content) ?>
          <?= render_ad($ads, 'in-article', 'ad-in-article') ?>
        </div>
      <?php endif; ?>

      <!-- ── Tag Pills ────────────────────────────────────────── -->
      <?php if (!empty($articleTags)): ?>
      <div class="np-article-tags">
        <span class="np-tags-label">Tags</span>
        <?php foreach ($articleTags as $t): ?>
          <a href="/tag/<?= h($t['slug']) ?>" class="article-tag-pill">#<?= h($t['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <footer class="np-article-footer">
        <?= share_buttons($articleUrl, $title, $excerpt) ?>
      </footer>

      <?= render_ad($ads, 'below-article', 'ad-below-article') ?>

      <!-- ── Author Card ──────────────────────────────────────── -->
      <div class="np-author-card">
        <div class="np-cat-tag">About the Author</div>
        <div class="np-author-card-top">
          <div class="np-author-card-avatar">
            <?php if (!empty($author['avatar_url'])): ?>
              <img src="<?= h($author['avatar_url']) ?>" alt="<?= h($authorName) ?>" loading="lazy" decoding="async" />
            <?php else: ?>
              <div class="np-avatar-initials"><?= strtoupper(mb_substr($authorName, 0, 1)) ?></div>
            <?php endif; ?>
          </div>
          <div class="np-author-card-info">
            <?php if ($authorUsername): ?>
              <a href="/author/<?= h($authorUsername) ?>" class="np-author-card-name"><?= h($authorName) ?></a>
            <?php else: ?>
              <span class="np-author-card-name"><?= h($authorName) ?></span>
            <?php endif; ?>
            <span class="np-author-card-role"><?= h($authorRole) ?></span>
          </div>
        </div>

        <?php if (!empty($author['bio'])): ?>
          <div class="np-author-card-bio"><p><?= h($author['bio']) ?></p></div>
        <?php endif; ?>

        <?php
          $hasSocials = !empty($author['twitter_handle']) || !empty($author['facebook_url'])
                     || !empty($author['linkedin_url']) || !empty($author['instagram_handle'])
                     || !empty($author['whatsapp_number']) || !empty($author['website_url']);
        ?>
        <?php if ($hasSocials): ?>
          <div class="np-author-card-socials">
            <?php if (!empty($author['twitter_handle'])): ?>
              <a href="https://twitter.com/<?= h($author['twitter_handle']) ?>" target="_blank" rel="noopener" class="np-social-icon" title="@<?= h($author['twitter_handle']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($author['facebook_url'])): ?>
              <a href="<?= h($author['facebook_url']) ?>" target="_blank" rel="noopener" class="np-social-icon" title="Facebook">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($author['instagram_handle'])): ?>
              <a href="https://instagram.com/<?= h($author['instagram_handle']) ?>" target="_blank" rel="noopener" class="np-social-icon" title="@<?= h($author['instagram_handle']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($author['linkedin_url'])): ?>
              <a href="<?= h($author['linkedin_url']) ?>" target="_blank" rel="noopener" class="np-social-icon" title="LinkedIn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($author['whatsapp_number'])): ?>
              <a href="https://wa.me/<?= h($author['whatsapp_number']) ?>" target="_blank" rel="noopener" class="np-social-icon" title="WhatsApp">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($author['website_url'])): ?>
              <a href="<?= h($author['website_url']) ?>" target="_blank" rel="noopener" class="np-social-icon" title="<?= h($author['website_url']) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($authorUsername && !$isCrawled): ?>
          <div class="np-author-card-more">
            <a href="/author/<?= h($authorUsername) ?>">
              View all articles by <?= h($authorName) ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- ── Story Thread ─────────────────────────────────────── -->
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

      <!-- ── Comments Section ─────────────────────────────────── -->
      <div class="np-comments-section">
        <h3 class="np-comments-heading">
          <?php if (!empty($comments)): ?>
            <?= count($comments) ?> Comment<?= count($comments) !== 1 ? 's' : '' ?>
          <?php else: ?>
            Join the Conversation
          <?php endif; ?>
        </h3>

        <!-- Existing comments -->
        <?php if (!empty($comments)): ?>
          <div id="commentsList">
            <?php foreach ($comments as $c): ?>
              <div class="comment-item np-comment-item">
                <div class="np-comment-avatar">
                  <?= strtoupper(mb_substr($c['author_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="np-comment-body">
                  <div class="np-comment-meta">
                    <strong><?= h($c['author_name'] ?? 'Anonymous') ?></strong>
                    <?php if (!empty($c['created_at'])): ?>
                      <span class="np-comment-time"><?= date('M j, Y \a\t g:i A', strtotime((string)$c['created_at'])) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="np-comment-text">
                    <?= nl2br(h($c['content'] ?? '')) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div id="commentsList"></div>
          <p class="np-no-comments" id="noCommentsMsg">Be the first to comment on this article.</p>
        <?php endif; ?>

        <!-- Comment form -->
        <div id="commentForm" class="np-comment-form">
          <div class="np-comment-form-heading">Leave a Comment</div>
          <div class="np-comment-form-grid">
            <div>
              <label class="np-form-label">Name <span class="np-required">*</span></label>
              <input type="text" id="commentName" class="np-form-input" placeholder="Your name" required maxlength="100" />
            </div>
            <div>
              <label class="np-form-label">Email <span class="np-form-optional">(optional, not shown)</span></label>
              <input type="email" id="commentEmail" class="np-form-input" placeholder="your@email.com" maxlength="255" />
            </div>
          </div>
          <div class="np-comment-form-textarea">
            <label class="np-form-label">Comment <span class="np-required">*</span></label>
            <textarea id="commentBody" class="np-form-input" rows="4" placeholder="Share your thoughts..." required maxlength="5000"></textarea>
          </div>
          <div class="np-comment-form-actions">
            <label class="np-subscribe-label">
              <input type="checkbox" id="commentSubscribe" class="np-checkbox" />
              Subscribe me to the newsletter
            </label>
            <button type="button" id="commentSubmitBtn" class="np-comment-submit">
              Post Comment
            </button>
            <span id="commentStatus" class="np-comment-status" style="display:none"></span>
          </div>
        </div>
      </div>
    </div><!-- /np-article-center -->

    <!-- Related Sidebar (right, desktop) -->
    <?php if (!empty($related)): ?>
    <aside class="np-related-sidebar">
      <div class="np-related-sidebar-inner">
        <div class="np-cat-tag np-related-heading">Related Analysis</div>
        <?php foreach (array_slice($related, 0, 4) as $r): ?>
          <a class="np-related-card" href="/article/<?= h($r['slug']) ?>">
            <?php if (!empty($r['featured_image'])): ?>
              <div class="np-related-thumb">
                <img src="<?= h(thumbnail_url($r['featured_image'])) ?>" alt="<?= h($r['title']) ?>" loading="lazy" decoding="async" />
              </div>
            <?php else: ?>
              <div class="np-related-placeholder"><?= h($siteAbbr) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['category'])): ?>
              <span class="np-related-cat"><?= h($r['category']) ?></span>
            <?php endif; ?>
            <span class="np-related-title"><?= h($r['title']) ?></span>
          </a>
        <?php endforeach; ?>

        <!-- Newsletter CTA -->
        <div class="np-newsletter-cta">
          <div class="np-newsletter-title">Stay Informed</div>
          <p class="np-newsletter-desc">Get the latest analysis and breaking stories delivered to your inbox.</p>
          <form class="np-newsletter-form" data-newsletter-form>
            <input type="hidden" name="_csrf" value="<?= h($csrf ?? \App\Services\Csrf::token()) ?>">
            <input type="email" name="email" class="np-newsletter-input" placeholder="your@email.com" required />
            <button type="submit" class="np-newsletter-btn">Subscribe</button>
          </form>
          <div class="np-newsletter-msg" style="font-size:13px;margin-top:6px;"></div>
        </div>
      </div>
    </aside>
    <?php endif; ?>

  </div><!-- /np-article-layout -->

</article>

<?php /* ── Source info button + popup (crawled articles only) ── */ ?>
<?php if ($isCrawled && $sourceUrl): ?>
<style>
/* Source info icon button */
.src-info-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--np-text-low);
  vertical-align: middle;
  margin-left: 4px;
  transition: color .15s, transform .15s;
  flex-shrink: 0;
}
.src-info-btn:hover { color: var(--np-accent); transform: scale(1.15); }

/* Source popup */
.src-popup-overlay {
  position: fixed; inset: 0; z-index: 8000;
  display: none;
}
.src-popup-overlay.open { display: block; }
.src-popup {
  position: fixed;
  background: var(--np-surface-1);
  border: 1px solid var(--np-border);
  box-shadow: 0 8px 32px rgba(0,0,0,.4);
  padding: 14px 16px;
  max-width: 300px;
  width: max-content;
  z-index: 8001;
  font-size: 13px;
  line-height: 1.5;
  color: var(--np-text-high);
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
  color: var(--np-text-low);
  margin-bottom: 6px;
}
.src-popup-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--np-accent);
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

<!-- ── Nocturnal Prestige Article Styles ──────────────────────── -->
<style>
/* ── Full-Bleed Hero ─────────────────────────────────────────── */
.np-hero {
  position: relative;
  width: 100vw;
  margin-left: calc(-50vw + 50%);
  max-height: 70vh;
  overflow: hidden;
}
.np-hero-image {
  position: relative;
  width: 100%;
  max-height: 70vh;
  background-size: cover;
  background-position: center;
}
.np-hero-image img {
  width: 100%;
  height: 100%;
  max-height: 70vh;
  object-fit: cover;
  display: block;
}
.np-hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    var(--np-surface-0) 0%,
    rgba(12, 10, 9, 0.85) 40%,
    transparent 70%
  );
  display: flex;
  align-items: flex-end;
}
.np-hero-content {
  width: 100%;
  max-width: 900px;
  margin: 0 auto;
  padding: 0 var(--np-margin, 64px) 48px;
}

/* No-hero fallback header */
.np-header-no-hero {
  max-width: 900px;
  margin: 40px auto 0;
  padding: 0 var(--np-margin, 64px);
}

/* Category badge */
.np-badge-feature {
  display: inline-block;
  background: var(--np-accent);
  color: #fff;
  font-family: var(--font-label);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: 5px 14px;
  text-decoration: none;
  margin-bottom: 16px;
  transition: background .2s;
}
.np-badge-feature:hover { background: #b71c1c; }

/* Hero title */
.np-hero-title {
  font-family: var(--font-headline);
  font-size: clamp(28px, 5vw, 52px);
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -.02em;
  color: var(--np-text-high);
  margin: 0 0 20px;
}

/* Hero meta block */
.np-hero-meta {
  border-left: 3px solid var(--np-accent);
  padding-left: 16px;
}
.np-hero-meta-inner {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.np-hero-author {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 15px;
  font-weight: 600;
  color: var(--np-text-high);
}
.np-hero-author-link {
  color: var(--np-text-high);
  text-decoration: underline;
  text-decoration-style: dotted;
  text-underline-offset: 3px;
  transition: color .2s;
}
.np-hero-author-link:hover { color: var(--np-accent-light); }
.np-hero-role {
  font-size: 13px;
  font-weight: 400;
  color: var(--np-text-mid);
  font-family: var(--font-label);
}
.np-hero-date {
  font-size: 13px;
  color: var(--np-text-mid);
  font-family: var(--font-label);
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0;
}
.np-time-ago {
  font-size: 12px;
  color: var(--np-text-low);
  margin-left: 6px;
}

/* ── TL;DR Box ───────────────────────────────────────────────── */
.tldr-box {
  max-width: 780px;
  margin: 28px auto;
  border: 1px solid var(--np-border);
  overflow: hidden;
  background: var(--np-surface-1);
}
.tldr-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 12px 16px;
  border: none;
  background: none;
  cursor: pointer;
  font-weight: 700;
  font-size: 13px;
  color: var(--np-accent);
  text-transform: uppercase;
  letter-spacing: .08em;
  font-family: var(--font-label);
}
.tldr-toggle[aria-expanded="true"] .tldr-chevron { transform: rotate(180deg); }
.tldr-chevron { font-size: 20px; transition: transform .2s; }
.tldr-content {
  padding: 0 16px 14px;
  font-size: 15px;
  line-height: 1.7;
  color: var(--np-text-mid);
}
.tldr-content p { margin: 0; }

/* ── Three-Column Layout ─────────────────────────────────────── */
.np-article-layout {
  display: flex;
  max-width: 1280px;
  margin: 32px auto 0;
  padding: 0 var(--np-margin, 64px);
  gap: 40px;
  align-items: flex-start;
}

/* ── Share Sidebar (left) ────────────────────────────────────── */
.np-share-sidebar {
  width: 48px;
  flex-shrink: 0;
  position: sticky;
  top: 80px;
}
.np-share-sidebar-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.np-share-sidebar .share {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.np-share-sidebar .share-btn {
  width: 44px;
  height: 44px;
  border-radius: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent !important;
  border: 1px solid var(--np-border);
  color: var(--np-text-mid);
  transition: all .2s;
}
.np-share-sidebar .share-btn:hover {
  border-color: var(--np-accent);
  color: var(--np-accent);
  background: transparent !important;
}
.np-share-sidebar .share-btn svg { fill: currentColor; stroke: currentColor; }

.np-share-icon-btn {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--np-border);
  background: transparent;
  color: var(--np-text-mid);
  cursor: pointer;
  transition: all .2s;
}
.np-share-icon-btn:hover {
  border-color: var(--np-accent);
  color: var(--np-accent);
}
.np-share-icon-btn.saved {
  color: var(--np-accent);
  background: rgba(211, 47, 47, .1);
  border-color: var(--np-accent);
}

/* Mobile share bar */
.np-mobile-share {
  display: none;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 0;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--np-border);
}

/* ── Article Body ────────────────────────────────────────────── */
.np-article-center {
  flex: 1;
  min-width: 0;
  max-width: 780px;
}

.np-article-body {
  font-size: 18px;
  line-height: 1.8;
  color: var(--np-text-high);
  font-family: var(--font-body);
}
.np-article-body p {
  margin-bottom: 1.5em;
}
.np-article-body h2,
.np-article-body h3,
.np-article-body h4 {
  font-family: var(--font-headline);
  color: var(--np-text-high);
  margin-top: 2em;
  margin-bottom: 0.75em;
}
.np-article-body a {
  color: var(--np-accent-light);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.np-article-body a:hover { color: var(--np-accent); }
.np-article-body blockquote {
  margin: 48px 0;
  padding: 0;
  border: none;
  background: transparent;
  text-align: center;
  position: relative;
}
.np-article-body blockquote::before {
  content: '\201C\201C';
  display: block;
  font-family: var(--font-headline);
  font-size: 48px;
  color: var(--np-accent);
  line-height: 1;
  margin-bottom: 16px;
}
.np-article-body blockquote p {
  font-family: var(--font-headline);
  font-size: 24px;
  font-weight: 500;
  line-height: 1.4;
  color: var(--np-text-high);
  font-style: italic;
  max-width: 600px;
  margin: 0 auto;
}
.np-article-body img {
  max-width: 100%;
  height: auto;
}
.np-article-body figure {
  margin: 2em 0;
}
.np-article-body figcaption {
  font-size: 13px;
  color: var(--np-text-low);
  margin-top: 8px;
  font-family: var(--font-label);
}

/* Drop cap */
.np-drop-cap > p:first-of-type::first-letter,
.np-drop-cap > .safe-html > p:first-of-type::first-letter {
  font-family: var(--font-headline);
  float: left;
  font-size: 4.2em;
  line-height: 0.8;
  font-weight: 900;
  color: var(--np-accent);
  margin: 4px 12px 0 0;
  padding: 0;
}

/* ── Tag Pills ───────────────────────────────────────────────── */
.np-article-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--np-border);
}
.np-tags-label {
  font-family: var(--font-label);
  font-weight: 700;
  font-size: 11px;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--np-text-low);
  margin-right: 4px;
}
.article-tag-pill {
  display: inline-block;
  padding: 5px 14px;
  background: var(--np-surface-1);
  color: var(--np-text-mid);
  font-size: 13px;
  text-decoration: none;
  border: 1px solid var(--np-border);
  transition: all .2s;
  font-weight: 500;
  font-family: var(--font-label);
}
.article-tag-pill:hover {
  background: var(--np-accent);
  color: #fff;
  border-color: var(--np-accent);
}

/* ── Article Footer ──────────────────────────────────────────── */
.np-article-footer {
  display: flex;
  justify-content: center;
  padding: 24px 0;
  margin-top: 24px;
  border-top: 1px solid var(--np-border);
  border-bottom: 1px solid var(--np-border);
}

/* ── Author Card ─────────────────────────────────────────────── */
.np-author-card {
  background: var(--np-surface-1);
  padding: 32px;
  margin-top: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.np-cat-tag {
  font-family: var(--font-label);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--np-accent);
  margin-bottom: 20px;
}
.np-author-card-top {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}
.np-author-card-avatar {
  width: 96px;
  height: 96px;
  flex-shrink: 0;
  overflow: hidden;
  border: 2px solid var(--np-accent);
  border-radius: 50%;
}
.np-author-card-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: grayscale(100%);
  transition: filter .3s;
}
.np-author-card:hover .np-author-card-avatar img { filter: grayscale(0); }
.np-avatar-initials {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--np-surface-2);
  color: var(--np-text-mid);
  font-size: 28px;
  font-weight: 700;
  font-family: var(--font-headline);
}
.np-author-card-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.np-author-card-name {
  font-family: var(--font-headline);
  font-size: 22px;
  font-weight: 700;
  color: var(--np-text-high);
  text-decoration: none;
  transition: color .2s;
}
a.np-author-card-name:hover { color: var(--np-accent); }
.np-author-card-role {
  font-family: var(--font-label);
  font-size: 13px;
  color: var(--np-text-low);
  text-transform: uppercase;
  letter-spacing: .06em;
}
.np-author-card-bio {
  color: var(--np-text-mid);
  font-size: 15px;
  line-height: 1.7;
  margin-bottom: 16px;
}
.np-author-card-bio p { margin: 0; }
.np-author-card-socials {
  display: flex;
  gap: 12px;
  margin-top: 4px;
  justify-content: center;
}
.np-social-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--np-border);
  color: var(--np-text-mid);
  text-decoration: none;
  transition: all .2s;
}
.np-social-icon:hover {
  color: var(--np-accent);
  border-color: var(--np-accent);
}
.np-social-icon svg { width: 18px; height: 18px; }
.np-author-card-more {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--np-border);
}
.np-author-card-more a {
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--np-accent);
  text-decoration: none;
  transition: color .2s;
}
.np-author-card-more a:hover { color: var(--np-accent-light); }

/* ── Comments Section ────────────────────────────────────────── */
.np-comments-section {
  margin-top: 48px;
}
.np-comments-heading {
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--np-text-high);
  padding-bottom: 16px;
  border-bottom: 2px solid var(--np-accent);
  margin-bottom: 24px;
}
.np-comment-item {
  display: flex;
  gap: 14px;
  padding: 20px 0;
  border-bottom: 1px solid var(--np-border);
}
.np-comment-avatar {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  background: var(--np-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: var(--np-text-mid);
  font-family: var(--font-label);
}
.np-comment-body {
  flex: 1;
  min-width: 0;
}
.np-comment-meta {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 8px;
}
.np-comment-meta strong {
  font-size: 14px;
  color: var(--np-text-high);
}
.np-comment-time {
  font-size: 12px;
  color: var(--np-text-low);
  font-family: var(--font-label);
}
.np-comment-text {
  font-size: 15px;
  line-height: 1.7;
  color: var(--np-text-mid);
}
.np-no-comments {
  color: var(--np-text-low);
  font-size: 14px;
  margin-bottom: 20px;
}

/* Comment form */
.np-comment-form {
  margin-top: 28px;
  padding: 28px;
  background: var(--np-surface-1);
  border: 1px solid var(--np-border);
}
.np-comment-form-heading {
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--np-accent);
  margin-bottom: 20px;
}
.np-comment-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 14px;
}
.np-form-label {
  display: block;
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--np-text-mid);
  margin-bottom: 6px;
}
.np-required { color: var(--np-accent); }
.np-form-optional {
  color: var(--np-text-low);
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
}
.np-form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--np-border);
  background: var(--np-surface-0);
  color: var(--np-text-high);
  font: inherit;
  font-size: 14px;
  transition: border-color .2s;
}
.np-form-input:focus {
  outline: none;
  border-color: var(--np-accent);
}
.np-form-input::placeholder { color: var(--np-text-low); }
textarea.np-form-input { resize: vertical; }
.np-comment-form-textarea { margin-bottom: 14px; }
.np-comment-form-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.np-subscribe-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--np-text-mid);
  cursor: pointer;
}
.np-checkbox {
  width: 16px;
  height: 16px;
  accent-color: var(--np-accent);
  cursor: pointer;
}
.np-comment-submit {
  padding: 10px 28px;
  background: var(--np-accent);
  color: #fff;
  border: none;
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  cursor: pointer;
  transition: background .2s;
}
.np-comment-submit:hover { background: #b71c1c; }
.np-comment-status { font-size: 13px; }

/* ── Related Sidebar (right) ─────────────────────────────────── */
.np-related-sidebar {
  width: 300px;
  flex-shrink: 0;
}
.np-related-sidebar-inner {
  position: sticky;
  top: 80px;
}
.np-related-heading {
  padding-bottom: 12px;
  border-bottom: 2px solid var(--np-accent);
  margin-bottom: 20px;
}
.np-related-card {
  display: block;
  text-decoration: none;
  margin-bottom: 20px;
  transition: transform .2s;
}
.np-related-card:hover { transform: translateY(-2px); }
.np-related-thumb {
  width: 100%;
  aspect-ratio: 16/9;
  overflow: hidden;
  margin-bottom: 10px;
}
.np-related-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: grayscale(100%);
  transition: filter .4s;
}
.np-related-card:hover .np-related-thumb img { filter: grayscale(0); }
.np-related-placeholder {
  width: 100%;
  aspect-ratio: 16/9;
  background: var(--np-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--np-text-low);
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 10px;
}
.np-related-cat {
  display: block;
  font-family: var(--font-label);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--np-accent);
  margin-bottom: 4px;
}
.np-related-title {
  display: block;
  font-family: var(--font-headline);
  font-size: 16px;
  font-weight: 700;
  color: var(--np-text-high);
  line-height: 1.3;
  transition: color .2s;
}
.np-related-card:hover .np-related-title { color: var(--np-accent-light); }

/* ── Newsletter CTA ──────────────────────────────────────────── */
.np-newsletter-cta {
  margin-top: 28px;
  padding: 24px;
  background: linear-gradient(135deg, var(--np-surface-1) 0%, rgba(211, 47, 47, .06) 100%);
  border: 1px solid var(--np-border);
}
.np-newsletter-title {
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  color: var(--np-text-high);
  margin-bottom: 8px;
}
.np-newsletter-desc {
  font-size: 14px;
  color: var(--np-text-mid);
  line-height: 1.6;
  margin-bottom: 16px;
}
.np-newsletter-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.np-newsletter-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--np-border);
  background: var(--np-surface-0);
  color: var(--np-text-high);
  font: inherit;
  font-size: 14px;
}
.np-newsletter-input:focus {
  outline: none;
  border-color: var(--np-accent);
}
.np-newsletter-input::placeholder { color: var(--np-text-low); }
.np-newsletter-btn {
  width: 100%;
  padding: 10px;
  background: var(--np-accent);
  color: #fff;
  border: none;
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  cursor: pointer;
  transition: background .2s;
}
.np-newsletter-btn:hover { background: #b71c1c; }

/* ── Article-with-sidebar (ad layout) ────────────────────────── */
.article-with-sidebar {
  display: grid; grid-template-columns: 1fr 300px; gap: 40px; align-items: start;
}
.article-with-sidebar .article-body { max-width: none; margin: 0; }
.article-sidebar-col { position: sticky; top: 24px; }

/* ── Thread styling ──────────────────────────────────────────── */
.thread-banner {
  background: var(--np-surface-1);
  border: 1px solid var(--np-border);
  border-left: 3px solid var(--np-accent);
  padding: 20px 24px;
  margin-top: 32px;
}
.thread-label {
  font-family: var(--font-label);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--np-accent);
  margin-bottom: 12px;
}
.thread-links { display: flex; flex-direction: column; gap: 8px; }
.thread-links a {
  color: var(--np-text-mid);
  text-decoration: none;
  font-size: 14px;
  transition: color .2s;
}
.thread-links a:hover { color: var(--np-accent-light); }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .np-related-sidebar { display: none; }
}
@media (max-width: 768px) {
  .np-share-sidebar { display: none; }
  .np-mobile-share { display: flex; }
  .np-article-layout { padding: 0 var(--np-margin-mobile, 20px); }
  .np-hero-content { padding: 0 var(--np-margin-mobile, 20px) 24px; }
  .np-header-no-hero { padding: 0 var(--np-margin-mobile, 20px); margin-top: 20px; }
  .np-hero-title { margin-bottom: 14px; }
  .np-comment-form-grid { grid-template-columns: 1fr; }
  .np-author-card { padding: 20px; }
  .np-author-card-top { flex-direction: column; text-align: center; }
  .np-author-card-info { align-items: center; }
  .np-author-card-socials { justify-content: center; }
  .article-with-sidebar { grid-template-columns: 1fr; }
  .article-sidebar-col { position: static; }
}
@media (max-width: 480px) {
  .np-hero-meta { padding-left: 12px; }
  .np-hero-date { flex-direction: column; gap: 2px; }
  .np-hero-date .dot { display: none; }
}

/* ── Bookmark button override ────────────────────────────────── */
.bookmark-btn {
  border-radius: 0;
}
.bookmark-btn .bookmark-icon { display: block; }
</style>

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
      statusEl.style.color = '#D32F2F';
      statusEl.textContent = 'Name and comment are required.';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.style.opacity = '0.6';
    statusEl.style.display = 'inline';
    statusEl.style.color = 'var(--np-text-low)';
    statusEl.textContent = 'Posting...';

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
        statusEl.style.color = '#4caf50';
        statusEl.textContent = 'Comment posted!';

        // Add comment to list
        if (noMsg) noMsg.style.display = 'none';
        var c = data.comment;
        var initial = (c.author_name || 'A').charAt(0).toUpperCase();
        function escComment(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        var safeName = escComment(c.author_name || 'Anonymous');
        var safeContent = escComment(c.content || '').replace(/\n/g, '<br>');
        var safeTime = escComment(c.time_label || '');
        var html = '<div class="comment-item np-comment-item">'
          + '<div class="np-comment-avatar">' + initial + '</div>'
          + '<div class="np-comment-body">'
          + '<div class="np-comment-meta"><strong>' + safeName + '</strong><span class="np-comment-time">' + safeTime + '</span></div>'
          + '<div class="np-comment-text">' + safeContent + '</div>'
          + '</div></div>';
        listEl.insertAdjacentHTML('beforeend', html);

        // Clear form
        document.getElementById('commentBody').value = '';
        setTimeout(function() { statusEl.style.display = 'none'; }, 3000);

        // Update heading count
        var heading = document.querySelector('.np-comments-heading');
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

<script>
(function() {
  // Bookmark logic
  var KEY = 'nt_bookmarks';
  function getBookmarks() {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch(e) { return []; }
  }
  function saveBookmarks(bm) {
    try { localStorage.setItem(KEY, JSON.stringify(bm)); } catch(e) {}
  }

  // Init all bookmark buttons (desktop + mobile)
  document.querySelectorAll('.bookmark-btn').forEach(function(btn) {
    var slug = btn.getAttribute('data-slug');

    function isBookmarked() {
      return getBookmarks().some(function(b) { return b.slug === slug; });
    }
    function updateUI() {
      var icon = btn.querySelector('.bookmark-icon');
      if (isBookmarked()) {
        btn.classList.add('saved');
        btn.title = 'Remove from saved';
        if (icon) icon.setAttribute('fill', 'currentColor');
      } else {
        btn.classList.remove('saved');
        btn.title = 'Save for later';
        if (icon) icon.setAttribute('fill', 'none');
      }
    }
    btn.addEventListener('click', function() {
      var bm = getBookmarks();
      if (isBookmarked()) {
        bm = bm.filter(function(b) { return b.slug !== slug; });
      } else {
        bm.unshift({
          slug: slug,
          title: btn.getAttribute('data-title'),
          image: btn.getAttribute('data-image'),
          category: btn.getAttribute('data-category'),
          date: btn.getAttribute('data-date'),
          savedAt: new Date().toISOString()
        });
        if (bm.length > 50) bm = bm.slice(0, 50);
      }
      saveBookmarks(bm);
      // Update all bookmark buttons
      document.querySelectorAll('.bookmark-btn').forEach(function(b) {
        if (b.getAttribute('data-slug') === slug) {
          if (isBookmarked()) { b.classList.add('saved'); b.title = 'Remove from saved'; var i = b.querySelector('.bookmark-icon'); if(i) i.setAttribute('fill','currentColor'); }
          else { b.classList.remove('saved'); b.title = 'Save for later'; var i = b.querySelector('.bookmark-icon'); if(i) i.setAttribute('fill','none'); }
        }
      });
      updateUI();
    });
    updateUI();
  });

  // TL;DR toggle
  var tldrBtn = document.querySelector('.tldr-toggle');
  if (tldrBtn) {
    tldrBtn.addEventListener('click', function() {
      var content = tldrBtn.nextElementSibling;
      var expanded = tldrBtn.getAttribute('aria-expanded') === 'true';
      tldrBtn.setAttribute('aria-expanded', String(!expanded));
      content.hidden = expanded;
    });
  }
})();
</script>

<script>
(function() {
  var articleId = '<?= h($article['id'] ?? '') ?>';
  if (!articleId) return;

  // Share tracking — intercept share button clicks
  document.querySelectorAll('.share-btn, [data-share]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var platform = btn.getAttribute('data-share') || btn.getAttribute('data-platform') || 'unknown';
      try {
        navigator.sendBeacon('/api/share-track', JSON.stringify({article_id: articleId, platform: platform}));
      } catch(e) {}
    });
  });

  // Engagement tracking — scroll depth + time on page
  var maxScroll = 0;
  var startTime = Date.now();
  var tracked = false;

  function getScrollPct() {
    var docH = document.documentElement.scrollHeight - window.innerHeight;
    if (docH <= 0) return 100;
    return Math.round((window.scrollY / docH) * 100);
  }

  window.addEventListener('scroll', function() {
    var pct = getScrollPct();
    if (pct > maxScroll) maxScroll = pct;
  }, {passive: true});

  function sendEngagement() {
    if (tracked) return;
    tracked = true;
    var timeOnPage = Math.round((Date.now() - startTime) / 1000);
    try {
      navigator.sendBeacon('/api/engagement', JSON.stringify({
        article_id: articleId,
        scroll_depth: maxScroll,
        time_on_page: timeOnPage
      }));
    } catch(e) {}
  }

  // Send on page unload or after 5 minutes
  window.addEventListener('beforeunload', sendEngagement);
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden') sendEngagement();
  });
  setTimeout(sendEngagement, 300000);
})();
</script>

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

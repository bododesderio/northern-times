<?php
declare(strict_types=1);

/** @var array  $page   ['title', 'content', 'updated_at'] */
/** @var array  $meta */

$pageTitle = $page['title'];
$activeNav = '';

ob_start();
?>

<article class="policy-article">

  <header class="policy-header">
    <h1 class="policy-title"><?= h($page['title']) ?></h1>
    <div class="policy-meta">
      Last updated
      <?= h(date('F j, Y', strtotime($page['updated_at']))) ?>
    </div>
  </header>

  <div class="policy-body rich-text">
    <?= $page['content'] /* already HTML from CKEditor — trusted content */ ?>
  </div>

</article>

<style>
.policy-article {
  max-width: var(--content-max, 740px);
  margin: 48px auto 80px;
  padding: 0 16px;
}

.policy-header {
  margin-bottom: 40px;
  padding-bottom: 24px;
  border-bottom: 2px double var(--border, #e2e2e2);
}

.policy-title {
  font-family: var(--mast, "UnifrakturMaguntia", Georgia, serif);
  font-size: clamp(28px, 5vw, 42px);
  font-weight: 700;
  line-height: 1.15;
  color: var(--ink, #121212);
  margin: 0 0 12px;
}

.policy-meta {
  font-size: 13px;
  color: var(--muted, #666);
  font-family: var(--ui, system-ui, sans-serif);
}

/* ── Rich text content styles ── */
.rich-text {
  font-family: var(--serif, Georgia, serif);
  font-size: var(--font-article, 18px);
  line-height: var(--line-height, 1.7);
  color: var(--ink, #121212);
}

.rich-text h2 {
  font-size: 1.4em;
  font-weight: 700;
  margin: 2em 0 .6em;
  color: var(--ink, #121212);
}

.rich-text h3 {
  font-size: 1.15em;
  font-weight: 700;
  margin: 1.6em 0 .5em;
  color: var(--ink, #121212);
}

.rich-text h4 {
  font-size: 1em;
  font-weight: 700;
  margin: 1.4em 0 .4em;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--muted, #666);
}

.rich-text p {
  margin: 0 0 1.2em;
}

.rich-text a {
  color: var(--accent, #cc0000);
  text-decoration: underline;
  text-decoration-thickness: 1px;
  text-underline-offset: 2px;
}

.rich-text a:hover {
  color: var(--accent-dark, #aa0000);
}

.rich-text ul,
.rich-text ol {
  margin: 0 0 1.2em 1.4em;
}

.rich-text li {
  margin-bottom: .4em;
}

.rich-text blockquote {
  border-left: 3px solid var(--accent, #cc0000);
  margin: 1.5em 0;
  padding: .5em 1.2em;
  color: var(--muted, #666);
  font-style: italic;
}

.rich-text hr {
  border: none;
  border-top: 1px solid var(--border, #e2e2e2);
  margin: 2em 0;
}

.rich-text table {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5em 0;
  font-size: .9em;
}

.rich-text th,
.rich-text td {
  padding: 9px 12px;
  border: 1px solid var(--border, #e2e2e2);
  text-align: left;
}

.rich-text th {
  background: var(--paper, #fdfdfd);
  font-weight: 700;
  font-family: var(--ui, system-ui, sans-serif);
  font-size: .85em;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--muted, #666);
}
</style>

<?php /* content captured by FrontendPolicyController::show() */ ?>
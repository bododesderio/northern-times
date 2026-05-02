<?php
/**
 * POLICY PAGE — Nocturnal Prestige Editorial Redesign
 * Clean single-column rich-text layout for legal/editorial pages
 */
declare(strict_types=1);

/** @var array $page ['title', 'content', 'updated_at'] */
/** @var array $meta */

$pageTitle = $page['title'];
$activeNav = '';
?>

<div class="np-page-wrap">
  <article class="np-policy">

    <header class="np-policy-header">
      <div class="np-cat-accent-line"></div>
      <span class="np-cat-tag">Policy</span>
      <h1 class="np-policy-title"><?= h($page['title']) ?></h1>
      <div class="np-policy-meta">
        Last updated <?= h(date('F j, Y', strtotime($page['updated_at']))) ?>
      </div>
    </header>

    <div class="np-policy-body">
      <?= safe_html($page['content'] ?? '') ?>
    </div>

  </article>
</div>


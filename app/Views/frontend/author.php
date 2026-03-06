<?php
$author   = $author ?? null;
$articles = $articles ?? [];
$result   = $result ?? ['total'=>0,'page'=>1,'pages'=>1];
$ads      = $ads ?? [];
$siteAbbr = get_site_setting('site_abbreviation', '') ?: mb_strtoupper(mb_substr(preg_replace('/\s+.*/u', '', get_site_setting('site_title', 'News')), 0, 3)) ?: 'NEWS';
if (!$author) { echo "<h1>Author not found</h1>"; return; }
$displayName = $author['display_name'] ?? $author['username'];
$avatar = $author['avatar_url'] ?? '/assets/default-avatar.svg';
?>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Person","name":"<?= h($displayName) ?>","url":"<?= h(app_url('/author/'.$author['username'])) ?>",<?php if(!empty($author['bio'])):?>"description":"<?= h(substr($author['bio'],0,200)) ?>",<?php endif;?>"jobTitle":"<?= h(ucfirst($author['role']??'writer')) ?>"}
</script>
<section class="category-page">
  <div style="display:flex;gap:24px;align-items:flex-start;padding:32px 0;border-bottom:1px solid var(--border,#e0e0e0);margin-bottom:28px">
    <img src="<?= h($avatar) ?>" alt="<?= h($displayName) ?>" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--border,#e0e0e0);flex-shrink:0"/>
    <div>
      <h1 style="margin:0 0 4px;font-size:28px"><?= h($displayName) ?></h1>
      <p style="margin:0 0 8px;color:var(--muted,#666);font-size:14px;text-transform:capitalize"><?= h($author['role']??'Writer') ?></p>
      <?php if(!empty($author['bio'])):?><p style="margin:0 0 12px;line-height:1.6;max-width:600px"><?= h($author['bio']) ?></p><?php endif;?>
      <p class="muted" style="margin:0;font-size:13px"><?= $result['total'] ?> article<?= $result['total']!==1?'s':'' ?> published</p>
    </div>
  </div>
  <?php if(!empty($articles)):?>
  <div class="cat-bottom-grid">
    <?php foreach($articles as $a):?>
      <article class="cat-grid-card"><a href="/article/<?= h($a['slug']) ?>">
        <?php if(!empty($a['featured_image'])):?><div class="cat-grid-img"><img src="<?= h($a['featured_image']) ?>" alt="<?= h($a['title']) ?>" loading="lazy"/></div>
        <?php else:?><div class="cat-grid-placeholder"><?= h($siteAbbr) ?></div><?php endif;?>
        <div class="cat-grid-body">
          <span class="cat-grid-badge"><?= h($a['category']??'') ?></span>
          <h4><?= h($a['title']) ?></h4>
          <p class="cat-grid-excerpt"><?= h($a['excerpt']?:excerpt((string)($a['content']??''),80)) ?></p>
          <div class="meta tiny"><?= h($a['published_at']?date('M j, Y',strtotime((string)$a['published_at'])):'') ?><?php if(!empty($a['reading_time'])):?> <span class="dot">&middot;</span> <?= (int)$a['reading_time'] ?> min read<?php endif;?></div>
        </div>
      </a></article>
    <?php endforeach;?>
  </div>
  <?php if($result['pages']>1):?>
  <div style="text-align:center;margin:32px 0;display:flex;gap:8px;justify-content:center">
    <?php for($p=1;$p<=$result['pages'];$p++):?>
      <?php if($p===$result['page']):?><span style="padding:8px 14px;border-radius:6px;background:var(--accent,#cc0000);color:#fff;font-weight:600"><?= $p ?></span>
      <?php else:?><a href="/author/<?= h($author['username']) ?>?page=<?= $p ?>" style="padding:8px 14px;border-radius:6px;border:1px solid var(--border);color:var(--ink);text-decoration:none"><?= $p ?></a><?php endif;?>
    <?php endfor;?>
  </div>
  <?php endif;?>
  <?= render_ad($ads,'in-feed','ad-in-feed') ?>
  <?php else:?>
    <div class="card" style="margin-top:24px"><h3 style="margin:0 0 6px">No published articles yet</h3><p class="muted" style="margin:0">Articles will appear here when published.</p></div>
  <?php endif;?>
</section>
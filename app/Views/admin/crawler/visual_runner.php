<?php
$pageTitle = 'Visual Crawler Runner';
$activeNav = 'crawler';
$slot = null;
ob_start();
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
  <div>
    <h2 style="margin:0;font-size:22px;font-weight:800">Visual Crawler Runner</h2>
    <p style="color:var(--text-muted,#888);margin:4px 0 0;font-size:14px">Run sources one-by-one and watch results in real time</p>
  </div>
  <a href="/admin/crawler" style="padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;background:var(--surface,#f4f4f5);color:var(--text,#333);text-decoration:none;border:1px solid var(--border,#ddd)">&larr; Back to Crawler</a>
</div>

<style>
.vr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
.vr-card{border-radius:12px;border:1px solid var(--border,#e2e2e2);background:var(--surface,#fff);overflow:hidden;transition:.2s}
.vr-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08)}
.vr-head{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border,#eee)}
.vr-name{font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
.vr-dot{width:8px;height:8px;border-radius:50%;background:#999;flex-shrink:0;margin-right:8px;display:inline-block}
.vr-dot.ok{background:#10b981}.vr-dot.err{background:#ef4444}.vr-dot.run{background:#f59e0b;animation:vrPulse 1s infinite}
@keyframes vrPulse{0%,100%{opacity:1}50%{opacity:.3}}
.vr-body{padding:14px 18px;font-size:13px;color:var(--text-muted,#666);min-height:60px}
.vr-stat{display:flex;gap:16px;margin-top:8px;font-size:12px;font-weight:600}
.vr-stat span{display:flex;align-items:center;gap:4px}
.vr-btn{padding:6px 14px;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;background:var(--np-accent,#e67e22);color:#fff;transition:.15s}
.vr-btn:hover{opacity:.85}.vr-btn:disabled{opacity:.4;cursor:not-allowed}
.vr-run-all{padding:10px 24px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;background:var(--np-accent,#e67e22);color:#fff;margin-bottom:20px}
html.dark .vr-card{border-color:var(--np-border,#333);background:var(--np-surface-1,#1c1917)}
html.dark .vr-head{border-color:var(--np-border,#333)}
html.dark .vr-body{color:var(--np-text-muted,#aaa)}
</style>

<button class="vr-run-all" onclick="vrRunAll()">Run All Sources</button>

<div class="vr-grid">
<?php foreach ($sources as $s): ?>
  <div class="vr-card" id="vr-<?= (int)$s['id'] ?>">
    <div class="vr-head">
      <div style="display:flex;align-items:center;overflow:hidden">
        <span class="vr-dot" id="vr-dot-<?= (int)$s['id'] ?>"></span>
        <span class="vr-name" title="<?= h($s['name']) ?>"><?= h($s['name']) ?></span>
      </div>
      <button class="vr-btn" onclick="vrRun(<?= (int)$s['id'] ?>,this)" id="vr-btn-<?= (int)$s['id'] ?>">Crawl</button>
    </div>
    <div class="vr-body" id="vr-body-<?= (int)$s['id'] ?>">
      Ready &mdash; <?= h($s['feed_url'] ?? '') ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;
async function vrRun(id, btn) {
  if (btn) btn.disabled = true;
  const dot = document.getElementById('vr-dot-'+id);
  const body = document.getElementById('vr-body-'+id);
  dot.className = 'vr-dot run';
  body.innerHTML = 'Crawling&hellip;';
  try {
    const r = await fetch('/admin/crawler/crawl-source/'+id, {
      method: 'POST',
      headers: {'X-CSRF-Token': csrf, 'Accept': 'application/json'}
    });
    const d = await r.json();
    if (d.error) {
      dot.className = 'vr-dot err';
      body.innerHTML = '<span style="color:#ef4444">'+d.error+'</span>';
    } else {
      dot.className = 'vr-dot ok';
      body.innerHTML = '<div class="vr-stat"><span>Found: '+(d.found||0)+'</span><span>New: '+(d.new||0)+'</span><span>Dupes: '+(d.dupes||0)+'</span><span>'+((d.elapsed||0))+'s</span></div>';
    }
  } catch(e) {
    dot.className = 'vr-dot err';
    body.innerHTML = '<span style="color:#ef4444">Network error</span>';
  }
  if (btn) btn.disabled = false;
}
async function vrRunAll() {
  const btns = document.querySelectorAll('.vr-btn');
  for (const b of btns) {
    const id = b.id.replace('vr-btn-','');
    await vrRun(id, b);
  }
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>

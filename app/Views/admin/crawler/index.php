<?php
$pageTitle = 'News Crawler';
$activeNav = 'crawler';
$slot = null;
ob_start();

$activeSources = array_filter($sources, fn($s) => $s['is_active'] ?? false);

function detectRegion(string $ws): array {
    $ws = strtolower($ws);
    if (str_contains($ws, '.ug')) return ['🇺🇬', 'uganda'];
    if (preg_match('/\.ke|\.co\.tz|\.co\.za|africa|punch|premium|nation\.|citizen|eastafrican|news24|timeslive|star\.co|maverick|iol\.co|vanguard|guardian\.ng|sahara/', $ws)) return ['🌍', 'africa'];
    if (preg_match('/\.co\.uk|guardian\.com|bbc|sky|scotsman|irishtimes|rte\.ie|dw\.com|france24|euronews|thelocal|euobserver|politico\.eu|aa\.com\.tr|swissinfo/', $ws)) return ['🇪🇺', 'uk'];
    if (preg_match('/cnn|npr|nyt|nbc|abc|cbs|fox|usatoday|wash|apnews|pbs\.org|thehill|politico\.com|cbc\.ca|global|globe|business/', $ws)) return ['🇺🇸', 'usa'];
    if (preg_match('/aljazeera|middleeast|arabnews|gulfnews|national.*uae|jpost|timesofisrael|dailysabah|tehrantimes|al-monitor/', $ws)) return ['🕌', 'mideast'];
    if (preg_match('/scmp|hindu|times.*india|ndtv|smh|abc\.net|nhk|japantimes|channel.*asia|straits|bangkok|nikkei|nzherald|inquirer|korea/', $ws)) return ['🌏', 'asia'];
    if (preg_match('/batimes|mercopress|rio|mexico|colombia|jamaica|tico|santiago|peru|trinidad/', $ws)) return ['🌎', 'latam'];
    return ['🌐', 'world'];
}
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash_error) ?></div>
<?php endif; ?>

<style>
/* ── Inline Runner Card ─────────────────────────────────── */
.cr{display:none;margin-bottom:24px;border-radius:14px;border:1px solid var(--border,#e2e2e2);background:var(--surface,#fff);overflow:hidden}
.cr.on{display:block;animation:crIn .25s ease}
@keyframes crIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.cr-top{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--border,#eee);flex-wrap:wrap}
.cr-ti{font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px}
.cr-ti .dot{width:8px;height:8px;border-radius:50%;background:#999;flex-shrink:0}
.cr-ti .dot.live{background:#10b981;animation:pulse 1.2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.cr-btns{display:flex;gap:5px;align-items:center}
.cb{padding:5px 12px;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;transition:.12s;display:inline-flex;align-items:center;gap:4px}
.cb:disabled{opacity:.3;cursor:not-allowed}
.cb-go{background:#10b981;color:#fff}.cb-go:hover:not(:disabled){background:#059669}
.cb-pa{background:#f59e0b;color:#fff}.cb-st{background:#ef4444;color:#fff}
.cb-rs{background:var(--surface2,#f3f3f3);color:var(--ink,#555);border:1px solid var(--border,#ddd)}
.cr-filters{display:flex;gap:2px}
.cr-fb{padding:4px 8px;border:1px solid var(--border,#ddd);border-radius:5px;font-size:10px;font-weight:700;cursor:pointer;background:transparent;color:var(--muted,#999);transition:.1s}
.cr-fb.on{background:#1a1a2e;color:#fff;border-color:transparent}
.cr-dly{display:flex;align-items:center;gap:4px;font-size:11px;color:var(--muted,#888)}
.cr-dly input{width:60px;accent-color:#3b82f6}

/* Stats row */
.cr-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;padding:14px 20px;border-bottom:1px solid var(--border,#f0f0f0)}
@media(max-width:800px){.cr-stats{grid-template-columns:repeat(3,1fr)}}
.cr-s{text-align:center;padding:8px 4px;border-radius:8px;background:var(--surface2,#fafafa)}
.cr-sv{font-size:20px;font-weight:800;line-height:1.1}
.cr-sl{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted,#999);margin-top:2px}

/* Progress bar */
.cr-prog{padding:6px 20px 0}
.cr-pb{height:4px;background:var(--border,#e8e8e8);border-radius:2px;overflow:hidden}
.cr-pf{height:100%;border-radius:2px;width:0%;background:linear-gradient(90deg,#3b82f6,#10b981);transition:width .35s}
.cr-pi{display:flex;justify-content:space-between;font-size:10px;color:var(--muted,#aaa);margin-top:3px;padding:0 2px}

/* Body: two columns — feed left, console right */
.cr-body{display:grid;grid-template-columns:1fr 1fr;gap:0;border-top:1px solid var(--border,#f0f0f0)}
@media(max-width:900px){.cr-body{grid-template-columns:1fr}}
.cr-left{padding:12px 16px;max-height:280px;overflow-y:auto;border-right:1px solid var(--border,#f0f0f0)}
@media(max-width:900px){.cr-left{border-right:none;border-bottom:1px solid var(--border,#f0f0f0)}}
.cr-right{display:flex;flex-direction:column}

/* Feed items */
.fi{display:grid;grid-template-columns:22px 1fr 56px 90px 44px;align-items:center;gap:6px;padding:5px 8px;border-radius:8px;font-size:12px;margin-bottom:1px;transition:.2s}
.fi.crawling{background:rgba(59,130,246,.05)}.fi.success{background:rgba(16,185,129,.03)}.fi.error{background:rgba(239,68,68,.03)}.fi.waiting{opacity:.4}.fi.hidden{display:none}
.fi-ic{font-size:13px;text-align:center}.fi-nm{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fi-st{font-size:11px;font-weight:600;text-align:center}
.fi-br{height:2px;border-radius:1px;background:var(--border,#e8e8e8);overflow:hidden}
.fi-bf{height:100%;border-radius:1px;width:0%;transition:width .3s}
.fi-bf.act{background:#3b82f6;animation:bp .6s infinite}.fi-bf.done{background:#10b981;width:100%}.fi-bf.err{background:#ef4444;width:100%}
@keyframes bp{0%,100%{opacity:1}50%{opacity:.2}}
.fi-tm{text-align:right;font-size:11px;color:var(--muted,#aaa);font-variant-numeric:tabular-nums}
.fi-rs{font-size:10px;margin-top:1px;grid-column:2/5}

/* Console */
.cr-con{background:#0a0a14;color:#b8b8c8;font-family:'JetBrains Mono','Fira Code','Consolas',monospace;font-size:10px;line-height:1.7;padding:10px 14px;flex:1;overflow-y:auto;max-height:280px;min-height:120px}
.cr-con::-webkit-scrollbar{width:3px}.cr-con::-webkit-scrollbar-thumb{background:#333;border-radius:2px}
.ct{color:#555}.cok{color:#34d399}.cer{color:#f87171}.cin{color:#60a5fa}.cwn{color:#fbbf24}

/* Speed mini chart */
.cr-ch{padding:6px 14px;border-top:1px solid rgba(255,255,255,.04);background:#0a0a14}
#crCanvas{width:100%;height:40px;display:block}

/* Ranking */
.cr-rank{padding:10px 20px;border-top:1px solid var(--border,#eee);display:none}
.rr{display:flex;align-items:center;gap:6px;margin-bottom:3px;font-size:11px}
.rp{width:18px;text-align:right;font-weight:800;color:#bbb}
.rn{width:130px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rt{flex:1;height:10px;background:var(--border,#eee);border-radius:2px;overflow:hidden}
.rf{height:100%;background:linear-gradient(90deg,#10b981,#3b82f6);border-radius:2px;transition:width .5s}
.rv{width:32px;text-align:right;font-weight:700;color:#10b981;font-size:12px}
</style>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
  <?php foreach ([
    ['Active Sources', $stats['active_sources'] . '/' . $stats['total_sources'], '📡', '#e8f5e9'],
    ['Published Today', $today['published_today'], '📰', '#e3f2fd'],
    ['Crawls Today', $today['crawls_today'], '🔄', '#fff3e0'],
    ['Errors Today', $today['errors_today'], '⚠️', (int)$today['errors_today'] > 0 ? '#ffebee' : '#f5f5f5'],
  ] as [$label, $value, $icon, $bg]): ?>
    <div style="background:<?= $bg ?>;padding:20px;border-radius:14px;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:28px;margin-bottom:4px"><?= $icon ?></div>
      <div style="font-size:24px;font-weight:800"><?= $value ?></div>
      <div style="font-size:13px;color:var(--muted,#888)"><?= $label ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Action Bar -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div style="display:flex;align-items:center;gap:12px">
    <span style="font-size:13px;font-weight:600;color:<?= $crawlerEnabled ? '#28a745' : '#dc3545' ?>">● Crawler is <?= $crawlerEnabled ? 'ON' : 'OFF' ?></span>
    <a href="/admin/crawler/settings" style="font-size:13px;color:var(--accent,#cc0000);text-decoration:none">⚙ Settings</a>
  </div>
  <div style="display:flex;gap:10px">
    <button type="button" class="btn" style="padding:10px 20px;font-size:14px" onclick="openCR('all')">⚡ Crawl All Now</button>
    <a href="/admin/crawler/create" class="btn" style="padding:10px 20px;font-size:14px;background:var(--accent,#cc0000);color:#fff;text-decoration:none;border-radius:10px">+ Add Source</a>
    <a href="/admin/crawler/logs" style="padding:10px 20px;font-size:14px;border:1px solid #e2e2e2;border-radius:10px;text-decoration:none;color:var(--text,#333)">📋 Logs</a>
  </div>
</div>

<!-- ═══ INLINE CRAWLER CARD ═══ -->
<div class="cr" id="crCard">
  <!-- Top bar: title + controls -->
  <div class="cr-top">
    <div class="cr-ti"><span class="dot" id="crDot"></span><span id="crTi">Crawler Ready</span></div>
    <div class="cr-btns">
      <button class="cb cb-go" id="cGo" onclick="crGo()">▶ Start</button>
      <button class="cb cb-pa" id="cPa" onclick="crPa()" disabled>⏸</button>
      <button class="cb cb-st" id="cSt" onclick="crSt()" disabled>■</button>
      <button class="cb cb-rs" onclick="crRs()">↺</button>
      <span style="width:1px;height:20px;background:var(--border,#ddd);margin:0 4px"></span>
      <div class="cr-filters">
        <button class="cr-fb on" onclick="crFi('all',this)">All</button>
        <button class="cr-fb" onclick="crFi('uganda',this)">🇺🇬</button>
        <button class="cr-fb" onclick="crFi('africa',this)">🌍</button>
        <button class="cr-fb" onclick="crFi('uk',this)">🇪🇺</button>
        <button class="cr-fb" onclick="crFi('usa',this)">🇺🇸</button>
        <button class="cr-fb" onclick="crFi('mideast',this)">🕌</button>
        <button class="cr-fb" onclick="crFi('asia',this)">🌏</button>
        <button class="cr-fb" onclick="crFi('latam',this)">🌎</button>
        <button class="cr-fb" onclick="crFi('world',this)">🌐</button>
      </div>
      <span style="width:1px;height:20px;background:var(--border,#ddd);margin:0 4px"></span>
      <div class="cr-dly">
        <span>Delay</span>
        <input type="range" id="crDly" min="0" max="3000" step="250" value="0" oninput="document.getElementById('crDL').textContent=(this.value/1000).toFixed(1)+'s'">
        <span id="crDL">0s</span>
      </div>
    </div>
  </div>

  <!-- Stats row -->
  <div class="cr-stats">
    <div class="cr-s"><div class="cr-sv" id="sN" style="color:#10b981">0</div><div class="cr-sl">New</div></div>
    <div class="cr-s"><div class="cr-sv" id="sF" style="color:#3b82f6">0</div><div class="cr-sl">Found</div></div>
    <div class="cr-s"><div class="cr-sv" id="sD" style="color:#f59e0b">0</div><div class="cr-sl">Dupes</div></div>
    <div class="cr-s"><div class="cr-sv" id="sE" style="color:#ef4444">0</div><div class="cr-sl">Errors</div></div>
    <div class="cr-s"><div class="cr-sv" id="sS" style="color:#8b5cf6">0</div><div class="cr-sl">Art/min</div></div>
    <div class="cr-s"><div class="cr-sv" id="sT" style="font-size:14px">—</div><div class="cr-sl">ETA</div></div>
  </div>

  <!-- Progress -->
  <div class="cr-prog">
    <div class="cr-pb"><div class="cr-pf" id="cP"></div></div>
    <div class="cr-pi"><span id="cPL">0 / 0</span><span id="cPP">0%</span></div>
  </div>

  <!-- Body: feed + console side by side -->
  <div class="cr-body">
    <div class="cr-left" id="crFd"></div>
    <div class="cr-right">
      <div class="cr-con" id="crCn"><div><span class="cin">▸ Ready.</span></div></div>
      <div class="cr-ch"><canvas id="crCanvas" height="40"></canvas></div>
    </div>
  </div>

  <!-- Ranking (after finish) -->
  <div class="cr-rank" id="crRk"><div style="font-size:13px;font-weight:800;margin-bottom:8px">🏆 Top Sources</div><div id="crRL"></div></div>
</div>

<!-- Sources Table -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr style="background:var(--surface-alt,#f8f8f8);border-bottom:1px solid var(--border,#e2e2e2)">
        <th style="padding:14px 16px;text-align:left;font-weight:700">Source</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Status</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Interval</th>
        <th style="padding:14px 12px;text-align:center;font-weight:700">Articles</th>
        <th style="padding:14px 12px;text-align:left;font-weight:700">Last Crawl</th>
        <th style="padding:14px 16px;text-align:right;font-weight:700">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($sources)): ?>
        <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted,#888)">No sources. <a href="/admin/crawler/create" style="color:var(--accent,#cc0000)">Add one →</a></td></tr>
      <?php endif; ?>
      <?php foreach ($sources as $s): ?>
        <tr style="border-bottom:1px solid var(--border,#f0f0f0)">
          <td style="padding:14px 16px">
            <div style="display:flex;align-items:center;gap:10px">
              <?php [$flag] = detectRegion($s['website_url'] ?? ''); ?>
              <div style="width:28px;height:28px;border-radius:6px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;font-size:16px"><?= $flag ?></div>
              <div>
                <div style="font-weight:700"><?= h($s['name']) ?></div>
                <div style="font-size:12px;color:var(--muted,#888);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($s['feed_url']) ?></div>
              </div>
            </div>
          </td>
          <td style="padding:14px 12px;text-align:center">
            <?php if ($s['is_active']): ?>
              <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#d4edda;color:#155724">Active</span>
            <?php else: ?>
              <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f5f5f5;color:#888">Paused</span>
            <?php endif; ?>
            <?php if (!empty($s['last_error'])): ?>
              <div style="font-size:11px;color:#dc3545;margin-top:4px" title="<?= h($s['last_error']) ?>">⚠ Error</div>
            <?php endif; ?>
          </td>
          <td style="padding:14px 12px;text-align:center;font-size:13px"><?= (int)$s['crawl_interval'] ?>m</td>
          <td style="padding:14px 12px;text-align:center"><span style="font-weight:700"><?= (int)($s['article_count'] ?? 0) ?></span></td>
          <td style="padding:14px 12px;font-size:13px"><?= !empty($s['last_crawled_at']) ? date('M j, g:i A', strtotime($s['last_crawled_at'])) : '<span style="color:var(--muted,#888)">Never</span>' ?></td>
          <td style="padding:14px 16px;text-align:right">
            <div style="display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap">
              <button type="button" onclick="openCR('<?= h($s['id']) ?>')" title="Crawl now" style="padding:6px 10px;border:1px solid #e2e2e2;border-radius:8px;background:var(--surface,#fff);cursor:pointer;font-size:12px">🔄</button>
              <a href="/admin/crawler/<?= h($s['id']) ?>/edit" title="Edit" style="padding:6px 10px;border:1px solid #e2e2e2;border-radius:8px;text-decoration:none;font-size:12px">✏️</a>
              <form method="POST" action="/admin/crawler/<?= h($s['id']) ?>/toggle" style="display:inline"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <button type="submit" title="<?= $s['is_active'] ? 'Pause' : 'Activate' ?>" style="padding:6px 10px;border:1px solid #e2e2e2;border-radius:8px;background:var(--surface,#fff);cursor:pointer;font-size:12px"><?= $s['is_active'] ? '⏸' : '▶️' ?></button>
              </form>
              <form method="POST" action="/admin/crawler/<?= h($s['id']) ?>/archive-articles" style="display:inline"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <button type="submit" title="Archive all" data-confirm="Archive all articles from this source?" data-confirm-title="Archive Articles" data-confirm-level="warn" data-confirm-ok="Archive" style="padding:6px 10px;border:1px solid #e2e2e2;border-radius:8px;background:var(--surface,#fff);cursor:pointer;font-size:12px">📦</button>
              </form>
              <form method="POST" action="/admin/crawler/<?= h($s['id']) ?>/delete" style="display:inline"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="delete_articles" value="0">
                <button type="submit" title="Delete" onclick="if(!confirm('Delete this source?'))return false;" style="padding:6px 10px;border:1px solid #ffcdd2;border-radius:8px;background:#fff5f5;cursor:pointer;font-size:12px;color:#c62828">🗑</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($recentLogs)): ?>
<div style="margin-top:24px;background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);display:flex;justify-content:space-between;align-items:center">
    <span style="font-weight:700;font-size:15px">Recent Activity</span>
    <a href="/admin/crawler/logs" style="font-size:13px;color:var(--accent,#cc0000);text-decoration:none">View all →</a>
  </div>
  <div style="padding:4px 0">
    <?php foreach ($recentLogs as $log): ?>
      <div style="padding:10px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border,#f5f5f5);font-size:13px">
        <?php $icon = match($log['status']) { 'success' => '✅', 'partial' => '⚠️', 'failed' => '❌', default => '🔄' }; ?>
        <span><?= $icon ?></span>
        <span style="font-weight:600;min-width:140px"><?= h($log['source_name']) ?></span>
        <span style="color:var(--muted,#888)"><?= (int)$log['articles_new'] ?> new, <?= (int)$log['articles_dupes'] ?> dupes</span>
        <span style="margin-left:auto;color:var(--muted,#888);font-size:12px"><?= date('M j g:i A', strtotime($log['started_at'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
const CSRF='<?= h($csrf) ?>';
const SRC={<?php foreach($sources as $s){[$f,$r]=detectRegion($s['website_url']??'');echo "'".htmlspecialchars($s['id'],ENT_QUOTES,'UTF-8')."':{n:".json_encode($s['name'],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP).",f:'".htmlspecialchars($f,ENT_QUOTES,'UTF-8')."',r:'".htmlspecialchars($r,ENT_QUOTES,'UTF-8')."',a:".($s['is_active']?'1':'0')."},";}?>};
let run=0,pau=0,stp=0,Q=[],I=0,tN=0,tF=0,tD=0,tE=0,T0=0,spd=[],res=[],gCtx;

function initG(){const c=document.getElementById('crCanvas');if(!c)return;gCtx=c.getContext('2d');c.width=c.offsetWidth*(devicePixelRatio||1);c.height=40*(devicePixelRatio||1);gCtx.scale(devicePixelRatio||1,devicePixelRatio||1)}
function drawG(){if(!gCtx)return;const c=document.getElementById('crCanvas'),w=c.offsetWidth,h=40;gCtx.clearRect(0,0,w*3,h*3);if(spd.length<2)return;const mx=Math.max(...spd,1);gCtx.fillStyle='rgba(59,130,246,.08)';gCtx.beginPath();gCtx.moveTo(0,h);spd.forEach((v,i)=>gCtx.lineTo((i/(spd.length-1))*w,h-(v/mx)*(h-6)));gCtx.lineTo(w,h);gCtx.fill();gCtx.strokeStyle='#3b82f6';gCtx.lineWidth=1.5;gCtx.lineJoin='round';gCtx.beginPath();spd.forEach((v,i)=>{const x=(i/(spd.length-1))*w,y=h-(v/mx)*(h-6);i===0?gCtx.moveTo(x,y):gCtx.lineTo(x,y)});gCtx.stroke()}
function cL(m,c='cin'){const e=document.getElementById('crCn');e.innerHTML+='<div><span class="ct">['+new Date().toLocaleTimeString('en',{hour12:false})+']</span> <span class="'+c+'">▸ '+m+'</span></div>';e.scrollTop=e.scrollHeight}

function openCR(mode){
  Q=mode==='all'?Object.keys(SRC).filter(id=>SRC[id].a):[mode];
  if(!Q.length){alert('No active sources');return}
  document.getElementById('crTi').textContent=mode==='all'?Q.length+' sources queued':'Crawl: '+(SRC[mode]?.n||'');
  document.getElementById('crFd').innerHTML=Q.map(id=>{const s=SRC[id];return`<div class="fi waiting" id="f-${id}" data-region="${s.r}"><div class="fi-ic">${s.f}</div><div class="fi-nm">${s.n}</div><div class="fi-st" id="st-${id}">—</div><div><div class="fi-br"><div class="fi-bf" id="b-${id}"></div></div></div><div class="fi-tm" id="tm-${id}">—</div></div>`}).join('');
  const card=document.getElementById('crCard');card.classList.add('on');
  card.scrollIntoView({behavior:'smooth',block:'start'});
  initG();crRs(1);cL(Q.length+' source'+(Q.length>1?'s':'')+' loaded. Press ▶ Start.');
}

function crFi(r,b){document.querySelectorAll('.cr-fb').forEach(x=>x.classList.remove('on'));b.classList.add('on');document.querySelectorAll('.fi').forEach(el=>{el.classList.toggle('hidden',r!=='all'&&el.dataset.region!==r)})}

function crRs(keep){
  I=0;tN=0;tF=0;tD=0;tE=0;spd=[];res=[];
  ['sN','sF','sD','sE','sS'].forEach(id=>document.getElementById(id).textContent='0');
  document.getElementById('sT').textContent='—';
  document.getElementById('cP').style.width='0%';
  document.getElementById('cPL').textContent='0 / '+Q.length;
  document.getElementById('cPP').textContent='0%';
  document.getElementById('crRk').style.display='none';
  document.getElementById('crDot').classList.remove('live');
  document.getElementById('cGo').disabled=0;
  document.getElementById('cPa').disabled=1;
  document.getElementById('cSt').disabled=1;
  if(gCtx)drawG();
  if(!keep){
    document.getElementById('crCn').innerHTML='<div><span class="cin">▸ Reset.</span></div>';
    document.querySelectorAll('.fi').forEach(el=>{
      el.className='fi waiting';
      const id=el.id.replace('f-','');
      const b=document.getElementById('b-'+id);if(b){b.className='fi-bf';b.style.width='0%'}
      const s=document.getElementById('st-'+id);if(s)s.textContent='—';
      const t=document.getElementById('tm-'+id);if(t)t.textContent='—';
    });
  }
}

function upS(){
  document.getElementById('sN').textContent=tN;
  document.getElementById('sF').textContent=tF;
  document.getElementById('sD').textContent=tD;
  document.getElementById('sE').textContent=tE;
  const el=(Date.now()-T0)/1000;
  const sp=el>0?((tN/el)*60).toFixed(1):'0';
  document.getElementById('sS').textContent=sp;
  spd.push(parseFloat(sp));drawG();
  const pc=Math.round((I/Q.length)*100);
  document.getElementById('cP').style.width=pc+'%';
  document.getElementById('cPL').textContent=I+' / '+Q.length;
  document.getElementById('cPP').textContent=pc+'%';
  if(I>0){const av=el/I,rm=(Q.length-I)*av,m=Math.floor(rm/60),s=Math.round(rm%60);document.getElementById('sT').textContent=m>0?m+'m '+s+'s':s+'s'}
}

function crGo(){
  if(run&&pau){pau=0;document.getElementById('cPa').textContent='⏸';cL('Resumed','cwn');crNx();return}
  if(run)return;run=1;pau=0;stp=0;T0=Date.now();
  document.getElementById('crDot').classList.add('live');
  document.getElementById('cGo').disabled=1;document.getElementById('cPa').disabled=0;document.getElementById('cSt').disabled=0;
  cL('🚀 Crawl started — '+Q.length+' sources','cok');crNx();
}

async function crNx(){
  if(stp||I>=Q.length){crDn();return}
  if(pau)return;
  const id=Q[I],s=SRC[id];
  const el=document.getElementById('f-'+id),bar=document.getElementById('b-'+id),st=document.getElementById('st-'+id),tm=document.getElementById('tm-'+id);
  el.scrollIntoView({behavior:'smooth',block:'nearest'});
  el.className='fi crawling';bar.className='fi-bf act';bar.style.width='60%';st.textContent='…';st.style.color='#3b82f6';
  cL('Fetching: <strong>'+s.n+'</strong>');
  const t0=Date.now();
  try{
    const rp=await fetch('/admin/crawler/crawl-source/'+id,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'_csrf='+encodeURIComponent(CSRF)});
    const d=await rp.json();const sc=((Date.now()-t0)/1000).toFixed(1);
    if(d.status==='ok'){
      tF+=d.found||0;tN+=d.new||0;tD+=d.dupes||0;
      el.className='fi success';bar.className='fi-bf done';st.textContent='✓';st.style.color='#10b981';tm.textContent=sc+'s';
      if(d.new>0)cL('✅ <strong>'+s.n+'</strong>: +'+d.new+' new / '+d.found+' found ('+sc+'s)','cok');
      else if(d.found>0)cL('⏭ '+s.n+': '+d.found+' found, all dupes ('+sc+'s)','cwn');
      else cL('○ '+s.n+': empty ('+sc+'s)','cwn');
      res.push({n:s.n,v:d.new||0});
    }else{
      tE++;el.className='fi error';bar.className='fi-bf err';st.textContent='✗';st.style.color='#ef4444';tm.textContent=sc+'s';
      cL('❌ <strong>'+s.n+'</strong>: '+(d.error||'Unknown').substring(0,60),'cer');
    }
  }catch(e){tE++;el.className='fi error';bar.className='fi-bf err';st.textContent='✗';st.style.color='#ef4444';cL('❌ '+s.n+': network error','cer')}
  I++;upS();
  const dl=parseInt(document.getElementById('crDly').value);
  if(dl>0&&I<Q.length)setTimeout(()=>crNx(),dl);else crNx();
}

function crPa(){pau=!pau;document.getElementById('cPa').textContent=pau?'▶':'⏸';cL(pau?'⏸ Paused':'▶ Resumed','cwn');if(!pau)crNx()}
function crSt(){stp=1;cL('🛑 Stopping…','cer')}

function crDn(){
  run=0;document.getElementById('crDot').classList.remove('live');
  document.getElementById('cGo').disabled=0;document.getElementById('cPa').disabled=1;document.getElementById('cSt').disabled=1;
  document.getElementById('cP').style.width='100%';document.getElementById('cPP').textContent='100%';document.getElementById('sT').textContent='Done';
  const sc=((Date.now()-T0)/1000).toFixed(1);
  cL('🏁 <strong>Complete!</strong> '+tN+' new articles from '+I+' sources in '+sc+'s','cok');
  const top=res.filter(r=>r.v>0).sort((a,b)=>b.v-a.v).slice(0,8);
  if(top.length){const mx=top[0].v;document.getElementById('crRL').innerHTML=top.map((r,i)=>'<div class="rr"><div class="rp">#'+(i+1)+'</div><div class="rn">'+r.n+'</div><div class="rt"><div class="rf" style="width:'+(r.v/mx*100).toFixed(0)+'%"></div></div><div class="rv">'+r.v+'</div></div>').join('');document.getElementById('crRk').style.display=''}
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
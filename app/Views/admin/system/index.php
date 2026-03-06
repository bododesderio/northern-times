<?php
declare(strict_types=1);

$activeNav = 'system';
$pageTitle = $pageTitle ?? 'System Administration';
$health    = $health ?? [];
$counts    = $counts ?? [];
$logs      = $logs ?? ['rows' => [], 'total' => 0];
$csrf      = $csrf ?? '';

ob_start();
?>
<style>
.sys-grid { display: grid; gap: 20px; margin-bottom: 28px; }
.sys-grid-3 { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
.sys-grid-2 { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }

.sys-card {
  background: #fff; border-radius: 12px; padding: 22px;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
}
.sys-card h3 { font-size: 14px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }

/* Health widgets */
.sys-health-item { text-align: center; padding: 18px; }
.sys-health-value { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.sys-health-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: .05em; }

/* Section headers with colored dots */
.sys-section { margin: 32px 0 16px; display: flex; align-items: center; gap: 10px; }
.sys-section h2 { font-size: 18px; font-weight: 700; margin: 0; }
.sys-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.sys-dot.green { background: #22c55e; }
.sys-dot.yellow { background: #eab308; }
.sys-dot.orange { background: #f97316; }
.sys-dot.red { background: #ef4444; }
.sys-dot.factory { background: #ef4444; animation: factoryPulse 1.5s ease-in-out infinite; }
@keyframes factoryPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.5); } 50% { box-shadow: 0 0 0 8px rgba(239,68,68,0); } }

/* Action buttons */
.sys-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
  border: none; cursor: pointer; transition: all .15s ease;
  font-family: inherit;
}
.sys-btn:hover { transform: translateY(-1px); }
.sys-btn.safe { background: #dcfce7; color: #166534; }
.sys-btn.safe:hover { background: #bbf7d0; }
.sys-btn.content { background: #fef9c3; color: #854d0e; }
.sys-btn.content:hover { background: #fef08a; }
.sys-btn.media { background: #ffedd5; color: #9a3412; }
.sys-btn.media:hover { background: #fed7aa; }
.sys-btn.danger { background: #fecaca; color: #991b1b; }
.sys-btn.danger:hover { background: #fca5a5; }
.sys-btn.factory-btn {
  background: #991b1b; color: #fff; font-size: 14px; padding: 12px 24px;
  animation: factoryPulse 1.5s ease-in-out infinite;
}
.sys-btn.factory-btn:hover { background: #7f1d1d; }
.sys-btn.tool { background: #e0e7ff; color: #3730a3; }
.sys-btn.tool:hover { background: #c7d2fe; }
.sys-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

/* Action row */
.sys-actions { display: flex; flex-wrap: wrap; gap: 10px; }

/* Confirmation modal */
.sys-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; display: none; align-items: center; justify-content: center; }
.sys-modal-bg.active { display: flex; }
.sys-modal {
  background: #fff; border-radius: 16px; padding: 28px; max-width: 460px; width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.sys-modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
.sys-modal p { font-size: 14px; color: #666; margin-bottom: 16px; line-height: 1.5; }
.sys-modal input[type="text"] {
  width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 8px;
  font-size: 14px; font-family: monospace; margin-bottom: 16px;
}
.sys-modal input[type="text"]:focus { outline: none; border-color: #ef4444; }
.sys-modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.sys-modal .cancel-btn {
  padding: 8px 18px; border-radius: 8px; border: 1px solid #e5e7eb;
  background: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
}
.sys-modal .confirm-btn {
  padding: 8px 18px; border-radius: 8px; border: none;
  background: #ef4444; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
}
.sys-modal .confirm-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Count badge */
.sys-count { font-size: 12px; color: #888; font-weight: 400; margin-left: auto; }

/* Log table */
.sys-log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sys-log-table th { text-align: left; padding: 8px 10px; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: .04em; border-bottom: 2px solid #f1f1f1; }
.sys-log-table td { padding: 8px 10px; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
.sys-log-table tr:hover td { background: #fafafa; }
.sys-log-level { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.sys-log-level.safe { background: #dcfce7; color: #166534; }
.sys-log-level.content { background: #fef9c3; color: #854d0e; }
.sys-log-level.media { background: #ffedd5; color: #9a3412; }
.sys-log-level.danger { background: #fecaca; color: #991b1b; }
.sys-log-level.factory { background: #991b1b; color: #fff; }

/* Table sizes */
.sys-table-sizes { width: 100%; border-collapse: collapse; font-size: 13px; }
.sys-table-sizes th { text-align: left; padding: 6px 10px; font-size: 11px; color: #888; text-transform: uppercase; border-bottom: 2px solid #f1f1f1; }
.sys-table-sizes td { padding: 6px 10px; border-bottom: 1px solid #f5f5f5; }

/* Flash messages */
.sys-flash { padding: 12px 18px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; font-weight: 500; }
.sys-flash.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.sys-flash.error { background: #fecaca; color: #991b1b; border: 1px solid #fca5a5; }
</style>

<div style="max-width:1100px;margin:0 auto;padding:28px 24px;">

  <h1 style="font-size:24px;font-weight:800;margin-bottom:6px;">🔧 System Administration</h1>
  <p style="font-size:14px;color:#888;margin-bottom:24px;">Super Admin tools for managing resets, purges, and database health.</p>

  <?php if (!empty($flash_success)): ?>
    <div class="sys-flash success"><?= h($flash_success) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash_error)): ?>
    <div class="sys-flash error"><?= h($flash_error) ?></div>
  <?php endif; ?>

  <!-- ── System Health ──────────────────────────────────────────── -->
  <div class="sys-grid sys-grid-3">
    <div class="sys-card sys-health-item">
      <div class="sys-health-value"><?= h($health['db_size'] ?? 'N/A') ?></div>
      <div class="sys-health-label">Database Size</div>
    </div>
    <div class="sys-card sys-health-item">
      <div class="sys-health-value"><?= h($health['pg_version'] ?? 'N/A') ?></div>
      <div class="sys-health-label">PostgreSQL</div>
    </div>
    <div class="sys-card sys-health-item">
      <div class="sys-health-value"><?= h($health['php_version'] ?? 'N/A') ?></div>
      <div class="sys-health-label">PHP Version</div>
    </div>
    <div class="sys-card sys-health-item">
      <div class="sys-health-value"><?= h($health['disk_used_pct'] ?? '0') ?>%</div>
      <div class="sys-health-label">Disk Used</div>
    </div>
    <div class="sys-card sys-health-item">
      <div class="sys-health-value"><?= h($health['disk_free'] ?? 'N/A') ?></div>
      <div class="sys-health-label">Disk Free</div>
    </div>
    <div class="sys-card sys-health-item">
      <div class="sys-health-value"><?= h($health['uptime'] ?? 'N/A') ?></div>
      <div class="sys-health-label">Server Uptime</div>
    </div>
  </div>

  <!-- ── 🟢 Safe Resets ─────────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot green"></span>
    <h2>Safe Resets — Analytics & Counters</h2>
  </div>
  <div class="sys-card">
    <p style="font-size:13px;color:#888;margin-bottom:16px;">These reset counters only. No content is deleted.</p>
    <div class="sys-actions">
      <form method="POST" action="/admin/system/reset-article-views" data-confirm="Reset all article view counters?" data-confirm-title="Reset Article Views" data-confirm-level="info" data-confirm-ok="Reset">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe">Reset Article Views <span class="sys-count"><?= number_format($counts['article_views'] ?? 0) ?></span></button>
      </form>
      <form method="POST" action="/admin/system/reset-site-visitors" data-confirm="Reset site visitor data?" data-confirm-title="Reset Site Visitors" data-confirm-level="info" data-confirm-ok="Reset">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe">Reset Site Visitors <span class="sys-count"><?= number_format($counts['site_visitors'] ?? 0) ?></span></button>
      </form>
      <form method="POST" action="/admin/system/reset-ad-stats" data-confirm="Reset ad impressions and clicks?" data-confirm-title="Reset Ad Stats" data-confirm-level="info" data-confirm-ok="Reset">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe">Reset Ad Stats <span class="sys-count"><?= number_format($counts['ad_slots'] ?? 0) ?> slots</span></button>
      </form>
      <form method="POST" action="/admin/system/reset-popup-analytics" data-confirm="Reset popup analytics?" data-confirm-title="Reset Popup Analytics" data-confirm-level="info" data-confirm-ok="Reset">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe">Reset Popup Analytics <span class="sys-count"><?= number_format($counts['popup_events'] ?? 0) ?> events</span></button>
      </form>
      <form method="POST" action="/admin/system/reset-newsletter-stats" data-confirm="Reset newsletter stats?" data-confirm-title="Reset Newsletter Stats" data-confirm-level="info" data-confirm-ok="Reset">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe">Reset Newsletter Stats <span class="sys-count"><?= number_format($counts['newsletter_issues'] ?? 0) ?></span></button>
      </form>
      <form method="POST" action="/admin/system/reset-crawler-stats" data-confirm="Reset crawler stats and logs?" data-confirm-title="Reset Crawler Stats" data-confirm-level="info" data-confirm-ok="Reset">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe">Reset Crawler Stats <span class="sys-count"><?= number_format($counts['crawl_log'] ?? 0) ?> logs</span></button>
      </form>
    </div>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f1f1;">
      <form method="POST" action="/admin/system/reset-all-analytics" data-confirm="This clears every analytics counter above. This cannot be undone." data-confirm-title="Reset ALL Analytics" data-confirm-level="warn" data-confirm-ok="Reset All">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe" style="background:#166534;color:#fff;">🔄 Reset ALL Analytics</button>
      </form>
    </div>
  </div>

  <!-- ── 🟡 Content Purges ──────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot yellow"></span>
    <h2>Content Purges</h2>
  </div>
  <div class="sys-card">
    <p style="font-size:13px;color:#888;margin-bottom:16px;">Delete content records. This cannot be undone.</p>
    <div class="sys-actions">
      <?php
      $contentPurges = [
        ['action' => 'purge-comments',        'label' => 'Comments',        'table' => 'comments',        'confirm' => 'comments'],
        ['action' => 'purge-subscribers',      'label' => 'Subscribers',     'table' => 'newsletter_subscribers',     'confirm' => 'newsletter_subscribers'],
        ['action' => 'purge-notifications',    'label' => 'Notifications',   'table' => 'notifications',   'confirm' => 'notifications'],
        ['action' => 'purge-crawl-history',    'label' => 'Crawl History',   'table' => 'crawl_log',       'confirm' => 'crawl_log'],
        ['action' => 'purge-seo-history',      'label' => 'SEO History',     'table' => 'seo_audits',      'confirm' => 'seo_audits'],
        ['action' => 'purge-social-mentions',  'label' => 'Social Mentions', 'table' => 'social_mentions', 'confirm' => 'social_mentions'],
        ['action' => 'purge-login-attempts',   'label' => 'Login Attempts',  'table' => 'login_attempts',  'confirm' => 'login_attempts'],
      ];
      foreach ($contentPurges as $p):
        $count = $counts[$p['table']] ?? 0;
      ?>
      <form method="POST" action="/admin/system/<?= $p['action'] ?>" data-confirm="Purge all <?= $p['label'] ?>? This cannot be undone." data-confirm-title="Purge <?= $p['label'] ?>" data-confirm-level="warn" data-confirm-ok="Purge">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn content"><?= $p['label'] ?> <span class="sys-count"><?= number_format($count) ?></span></button>
      </form>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── 🟠 Media Management ────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot orange"></span>
    <h2>Media Management</h2>
  </div>
  <div class="sys-card">
    <p style="font-size:13px;color:#888;margin-bottom:16px;">Manage uploaded media files. Purging deletes files from disk.</p>
    <div class="sys-actions">
      <button type="button" class="sys-btn media" onclick="openModal('purge-media', 'DELETE MEDIA', '/admin/system/purge-all-media', 'This will delete ALL uploaded media files from disk and database.')">
        Purge All Media <span class="sys-count"><?= number_format($counts['media_items'] ?? 0) ?></span>
      </button>
      <form method="POST" action="/admin/system/clean-orphan-media" data-confirm="Remove orphan media files not linked to any article?" data-confirm-title="Clean Orphan Media" data-confirm-level="warn" data-confirm-ok="Clean">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn media">Clean Orphan Media</button>
      </form>
      <!-- FIX (Audit B-05): Route + method now exist. Button was previously posting to 404. -->
      <form method="POST" action="/admin/system/regenerate-thumbnails" data-confirm="Regenerate all image thumbnails? This may take a moment for large libraries." data-confirm-title="Regenerate Thumbnails" data-confirm-level="info" data-confirm-ok="Regenerate">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn media">🖼 Regenerate Thumbnails</button>
      </form>
    </div>
  </div>

  <!-- ── 🔴 Danger Zone ─────────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot red"></span>
    <h2>Danger Zone</h2>
  </div>
  <div class="sys-card" style="border-color:#fca5a5;">
    <p style="font-size:13px;color:#991b1b;margin-bottom:16px;font-weight:500;">⚠ These actions are destructive and irreversible. Each requires typing a confirmation phrase.</p>
    <div class="sys-actions">
      <button type="button" class="sys-btn danger" onclick="openModal('delete-articles', 'DELETE ALL ARTICLES', '/admin/system/delete-all-articles', 'This permanently deletes ALL articles, their comments, views, tags, and revisions.')">
        Delete All Articles <span class="sys-count"><?= number_format($counts['articles'] ?? 0) ?></span>
      </button>
      <button type="button" class="sys-btn danger" onclick="openModal('delete-crawled', 'DELETE CRAWLED ARTICLES', '/admin/system/delete-crawled-articles', 'This deletes only articles imported by the crawler. Manual articles are preserved.')">
        Delete Crawled Articles
      </button>
      <button type="button" class="sys-btn danger" onclick="openModal('delete-popups', 'DELETE ALL POPUPS', '/admin/system/reset-all-popups', 'This deletes all popup/banner configurations.')">
        Reset All Popups <span class="sys-count"><?= number_format($counts['popups'] ?? 0) ?></span>
      </button>
      <button type="button" class="sys-btn danger" onclick="openModal('reset-crawler', 'RESET CRAWLER', '/admin/system/reset-crawler-system', 'This deletes all crawler sources and logs. You will need to re-add sources.')">
        Reset Crawler System <span class="sys-count"><?= number_format($counts['crawl_sources'] ?? 0) ?> sources</span>
      </button>
    </div>

    <!-- Factory Reset -->
    <div style="margin-top:20px;padding-top:20px;border-top:2px solid #fca5a5;">
      <h3 style="color:#991b1b;font-size:15px;margin-bottom:8px;">🔴🔴 Factory Reset</h3>
      <p style="font-size:13px;color:#666;margin-bottom:14px;line-height:1.6;">
        Resets the entire CMS to a clean state. <strong>Preserved:</strong> user accounts, roles, site settings, theme, SMTP config, crawler source configs, system log, Docker infrastructure.<br>
        <strong>Deleted:</strong> all articles, categories, media, comments, subscribers, newsletters, popups, crawler data, analytics, notifications, SEO audits, social mentions, login attempts.
      </p>
      <button type="button" class="sys-btn factory-btn" onclick="openModal('factory-reset', 'FACTORY RESET <?= strtoupper(h(site_name())) ?>', '/admin/system/factory-reset', 'This resets the ENTIRE CMS. Type the exact phrase to confirm.')">
        🔴 FACTORY RESET
      </button>
    </div>
  </div>

  <!-- ── 💾 Database Tools ──────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot" style="background:#6366f1;"></span>
    <h2>Database Tools</h2>
  </div>
  <div class="sys-grid sys-grid-2">
    <div class="sys-card">
      <h3>💾 Export & Maintenance</h3>
      <div class="sys-actions" style="flex-direction:column;">
        <a href="/admin/system/export-database" class="sys-btn tool" style="text-decoration:none;text-align:center;justify-content:center;">📥 Export SQL Dump</a>
        <form method="POST" action="/admin/system/vacuum" data-confirm="Run VACUUM ANALYZE to reclaim space and update query statistics?" data-confirm-title="Vacuum Database" data-confirm-level="info" data-confirm-ok="Run Vacuum">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <button type="submit" class="sys-btn tool" style="width:100%;">🧹 Vacuum Database</button>
        </form>
        <form method="POST" action="/admin/system/clear-cache" data-confirm="Clear all cached files?" data-confirm-title="Clear Cache" data-confirm-level="info" data-confirm-ok="Clear">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <button type="submit" class="sys-btn tool" style="width:100%;">🗑 Clear Cache</button>
        </form>
      </div>
    </div>
    <div class="sys-card">
      <h3>📊 Table Sizes</h3>
      <div id="tableSizesContainer" style="max-height:260px;overflow-y:auto;">
        <p style="font-size:13px;color:#aaa;">Loading...</p>
      </div>
    </div>
  </div>

  <!-- ── ⏱ Scheduled Tasks Monitor ───────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot" style="background:#06b6d4;"></span>
    <h2>Scheduled Tasks</h2>
    <?php if ($cronFailures > 0): ?>
      <span style="background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;margin-left:8px;"><?= $cronFailures ?> failure<?= $cronFailures > 1 ? 's' : '' ?> (24h)</span>
    <?php endif; ?>
  </div>
  <div class="sys-card">
    <?php if (empty($cronTasks)): ?>
      <p style="font-size:14px;color:#aaa;text-align:center;padding:16px;">No cron runs recorded yet. Tasks will appear after their first execution.</p>
    <?php else: ?>
    <table class="sys-log-table">
      <thead>
        <tr><th>Task</th><th>Status</th><th>Duration</th><th>Records</th><th>Output</th><th>Last Run</th></tr>
      </thead>
      <tbody>
      <?php foreach ($cronTasks as $t): ?>
        <tr>
          <td><code style="font-size:12px;background:#f5f5f5;padding:2px 6px;border-radius:4px;"><?= h($t['task_name']) ?></code></td>
          <td>
            <?php if ($t['status'] === 'success'): ?>
              <span style="color:#16a34a;font-weight:600;font-size:12px;">✅ Success</span>
            <?php elseif ($t['status'] === 'error'): ?>
              <span style="color:#dc2626;font-weight:600;font-size:12px;">❌ Error</span>
            <?php else: ?>
              <span style="color:#d97706;font-weight:600;font-size:12px;">⏳ Running</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;"><?= number_format((int)($t['duration_ms'] ?? 0)) ?>ms</td>
          <td style="font-size:12px;text-align:right;"><?= number_format((int)($t['records_affected'] ?? 0)) ?></td>
          <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($t['status'] === 'error' ? ($t['error_msg'] ?? '') : ($t['output'] ?? '')) ?></td>
          <td style="font-size:12px;color:#888;white-space:nowrap;"><?= h($t['started_at'] ? date('M j, g:ia', strtotime($t['started_at'])) : 'Never') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f1f1f1;">
      <form method="POST" action="/admin/system/cron-clean" data-confirm="Clean cron logs older than 30 days?" data-confirm-title="Clean Cron Logs" data-confirm-level="info" data-confirm-ok="Clean" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn tool">🧹 Clean Logs (30+ days)</button>
      </form>
    </div>
  </div>

  <!-- ── 👥 User Session Manager ────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot" style="background:#8b5cf6;"></span>
    <h2>Active Sessions</h2>
    <span style="background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;margin-left:8px;"><?= $activeUserCount ?> online now</span>
  </div>
  <div class="sys-card" style="overflow-x:auto;">
    <?php if (empty($sessions)): ?>
      <p style="font-size:14px;color:#aaa;text-align:center;padding:16px;">No tracked sessions yet.</p>
    <?php else: ?>
    <table class="sys-log-table">
      <thead>
        <tr><th>User</th><th>Role</th><th>IP</th><th>Browser</th><th>Last Active</th><th>Session Start</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($sessions as $s):
        $isActive = strtotime($s['last_activity']) > (time() - 1800);
        $isSelf = ($s['session_id'] === session_id());
        $ua = $s['user_agent'] ?? '';
        $browser = 'Unknown';
        if (str_contains($ua, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
        elseif (str_contains($ua, 'Edge')) $browser = 'Edge';
      ?>
        <tr style="<?= $isActive ? '' : 'opacity:.55;' ?>">
          <td style="font-size:13px;font-weight:600;">
            <?= h($s['username'] ?? 'Unknown') ?>
            <?php if ($isSelf): ?><span style="font-size:10px;color:#16a34a;font-weight:700;"> (you)</span><?php endif; ?>
          </td>
          <td><span class="sys-log-level <?= $s['role'] === 'super_admin' ? 'danger' : ($s['role'] === 'editor' ? 'content' : 'safe') ?>"><?= h($s['role'] ?? '') ?></span></td>
          <td style="font-size:12px;font-family:monospace;"><?= h($s['ip_address'] ?? '') ?></td>
          <td style="font-size:12px;"><?= h($browser) ?></td>
          <td style="font-size:12px;white-space:nowrap;">
            <?php if ($isActive): ?>
              <span style="color:#16a34a;">● </span>
            <?php else: ?>
              <span style="color:#aaa;">○ </span>
            <?php endif; ?>
            <?= h(date('M j, g:ia', strtotime($s['last_activity']))) ?>
          </td>
          <td style="font-size:12px;color:#888;white-space:nowrap;"><?= h(date('M j, g:ia', strtotime($s['created_at']))) ?></td>
          <td>
            <?php if (!$isSelf): ?>
            <form method="POST" action="/admin/system/force-logout-session" data-confirm="Force-logout this session for <?= h($s['username']) ?>?" data-confirm-title="Force Logout" data-confirm-level="warn" data-confirm-ok="Logout" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="session_id" value="<?= h($s['session_id']) ?>">
              <button type="submit" class="sys-btn content" style="padding:4px 10px;font-size:11px;">Force Logout</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f1f1f1;">
      <form method="POST" action="/admin/system/clean-sessions" data-confirm="Remove sessions inactive for 24+ hours?" data-confirm-title="Clean Stale Sessions" data-confirm-level="info" data-confirm-ok="Clean" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn tool">🧹 Clean Stale Sessions (24h+)</button>
      </form>
    </div>
  </div>

  <!-- ── 💾 Database Backups ────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot" style="background:#059669;"></span>
    <h2>Database Backups</h2>
    <span style="font-size:12px;color:#888;margin-left:auto;">Retention: last <?= \App\Models\DbBackup::MAX_BACKUPS ?> backups • Total: <?= \App\Models\DbBackup::formatSize($backupTotalSize) ?></span>
  </div>
  <div class="sys-card">
    <div style="margin-bottom:14px;">
      <form method="POST" action="/admin/system/create-backup" data-confirm="Create a new database backup? This may take a moment." data-confirm-title="Create Backup" data-confirm-level="info" data-confirm-ok="Create Backup" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="sys-btn safe" style="background:#059669;color:#fff;">💾 Create Manual Backup</button>
      </form>
    </div>
    <?php if (empty($backups)): ?>
      <p style="font-size:14px;color:#aaa;text-align:center;padding:16px;">No backups yet. Create your first backup above.</p>
    <?php else: ?>
    <table class="sys-log-table">
      <thead>
        <tr><th>Filename</th><th>Size</th><th>Type</th><th>Status</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td style="font-size:12px;"><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;"><?= h($b['filename']) ?></code></td>
          <td style="font-size:12px;white-space:nowrap;"><?= \App\Models\DbBackup::formatSize((int)($b['file_size'] ?? 0)) ?></td>
          <td style="font-size:12px;"><?= $b['is_auto'] === 't' || $b['is_auto'] === true ? '⏰ Auto' : '👤 Manual' ?></td>
          <td>
            <?php if ($b['status'] === 'completed'): ?>
              <span style="color:#16a34a;font-size:12px;font-weight:600;">✅ OK</span>
            <?php elseif ($b['status'] === 'failed'): ?>
              <span style="color:#dc2626;font-size:12px;font-weight:600;">❌ Failed</span>
            <?php else: ?>
              <span style="color:#d97706;font-size:12px;font-weight:600;">⏳ Running</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#888;white-space:nowrap;"><?= h(date('M j, g:ia', strtotime($b['created_at']))) ?></td>
          <td style="white-space:nowrap;">
            <?php if ($b['status'] === 'completed'): ?>
              <a href="/admin/system/backup/<?= $b['id'] ?>/download" class="sys-btn tool" style="padding:4px 10px;font-size:11px;text-decoration:none;">📥 Download</a>
            <?php endif; ?>
            <form method="POST" action="/admin/system/backup/<?= $b['id'] ?>/delete" data-confirm="Delete this backup file?" data-confirm-title="Delete Backup" data-confirm-level="warn" data-confirm-ok="Delete" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
              <button type="submit" class="sys-btn content" style="padding:4px 10px;font-size:11px;">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- ── 🔍 Environment Inspector ───────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot" style="background:#7c3aed;"></span>
    <h2>Environment Inspector</h2>
  </div>
  <div class="sys-grid sys-grid-2">
    <div class="sys-card">
      <h3>🐘 PHP Configuration</h3>
      <table class="sys-table-sizes">
        <tbody>
          <tr><td>Version</td><td style="text-align:right;font-weight:600;"><?= h($environment['php_version'] ?? '') ?></td></tr>
          <tr><td>SAPI</td><td style="text-align:right;"><?= h($environment['php_sapi'] ?? '') ?></td></tr>
          <tr><td>Memory Limit</td><td style="text-align:right;"><?= h($environment['memory_limit'] ?? '') ?></td></tr>
          <tr><td>Max Execution</td><td style="text-align:right;"><?= h($environment['max_execution'] ?? '') ?></td></tr>
          <tr><td>Upload Max</td><td style="text-align:right;"><?= h($environment['upload_max'] ?? '') ?></td></tr>
          <tr><td>Post Max</td><td style="text-align:right;"><?= h($environment['post_max'] ?? '') ?></td></tr>
          <tr><td>OPcache</td><td style="text-align:right;"><?= !empty($environment['opcache_enabled']) ? '<span style="color:#16a34a;">✅ Enabled</span>' : '<span style="color:#aaa;">Disabled</span>' ?></td></tr>
          <tr><td>Timezone</td><td style="text-align:right;"><?= h($environment['timezone'] ?? '') ?></td></tr>
          <tr><td>OS</td><td style="text-align:right;font-size:11px;"><?= h($environment['os'] ?? '') ?></td></tr>
          <tr><td>Hostname</td><td style="text-align:right;font-size:11px;"><?= h($environment['hostname'] ?? '') ?></td></tr>
        </tbody>
      </table>
    </div>

    <div class="sys-card">
      <h3>🔌 PHP Extensions</h3>
      <div style="display:flex;flex-wrap:wrap;gap:6px;">
        <?php foreach (($environment['extensions'] ?? []) as $ext => $loaded): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:500;
            background:<?= $loaded ? '#dcfce7' : '#fef2f2' ?>;color:<?= $loaded ? '#166534' : '#991b1b' ?>;">
            <?= $loaded ? '✓' : '✗' ?> <?= h($ext) ?>
          </span>
        <?php endforeach; ?>
      </div>

      <h3 style="margin-top:18px;">🔴 Redis</h3>
      <table class="sys-table-sizes">
        <tbody>
          <tr><td>Status</td><td style="text-align:right;font-weight:600;">
            <?php if (($environment['redis_status'] ?? '') === 'connected'): ?>
              <span style="color:#16a34a;">🟢 Connected</span>
            <?php else: ?>
              <span style="color:#dc2626;">🔴 <?= h($environment['redis_status'] ?? 'Unknown') ?></span>
            <?php endif; ?>
          </td></tr>
          <?php if (!empty($environment['redis_info'])): ?>
          <tr><td>Version</td><td style="text-align:right;"><?= h($environment['redis_info']['version'] ?? '') ?></td></tr>
          <tr><td>Memory</td><td style="text-align:right;"><?= h($environment['redis_info']['memory_used'] ?? '') ?></td></tr>
          <tr><td>Clients</td><td style="text-align:right;"><?= h($environment['redis_info']['connected_clients'] ?? '') ?></td></tr>
          <tr><td>Uptime</td><td style="text-align:right;"><?= h($environment['redis_info']['uptime_days'] ?? '') ?>d</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="sys-card" style="grid-column: 1 / -1;">
      <h3>⚙️ Environment Variables</h3>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach (($environment['env_vars'] ?? []) as $key => $val): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:6px;font-size:12px;background:#f5f5f5;">
            <code style="font-weight:600;"><?= h($key) ?></code>=<span style="color:#555;"><?= h($val) ?></span>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── 📋 Reset Log ──────────────────────────────────────────── -->
  <div class="sys-section">
    <span class="sys-dot" style="background:#8b5cf6;"></span>
    <h2>System Log</h2>
    <span style="font-size:12px;color:#888;margin-left:auto;">Permanent • Cannot be deleted</span>
  </div>
  <div class="sys-card" style="overflow-x:auto;">
    <?php if (empty($logs['rows'])): ?>
      <p style="font-size:14px;color:#aaa;text-align:center;padding:20px;">No system log entries yet.</p>
    <?php else: ?>
    <table class="sys-log-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>User</th>
          <th>Action</th>
          <th>Details</th>
          <th>Records</th>
          <th>Level</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logs['rows'] as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-size:12px;color:#888;"><?= h(date('M j, g:ia', strtotime($log['created_at']))) ?></td>
          <td style="font-size:12px;"><?= h($log['user_email'] ?? 'system') ?></td>
          <td><code style="font-size:12px;background:#f5f5f5;padding:2px 6px;border-radius:4px;"><?= h($log['action']) ?></code></td>
          <td style="font-size:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;"><?= h($log['details']) ?></td>
          <td style="font-size:12px;text-align:right;"><?= number_format((int)($log['records_affected'] ?? 0)) ?></td>
          <td><span class="sys-log-level <?= h($log['danger_level'] ?? 'safe') ?>"><?= h($log['danger_level'] ?? 'safe') ?></span></td>
          <td style="font-size:11px;color:#aaa;"><?= h($log['ip_address'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<!-- Confirmation Modal -->
<div class="sys-modal-bg" id="sysModal">
  <div class="sys-modal">
    <h3 id="modalTitle">Confirm Action</h3>
    <p id="modalDesc">Are you sure?</p>
    <p style="font-size:13px;font-weight:600;margin-bottom:4px;">Type <code id="modalPhrase" style="color:#ef4444;"></code> to confirm:</p>
    <input type="text" id="modalInput" placeholder="Type confirmation phrase..." autocomplete="off" spellcheck="false">
    <form method="POST" id="modalForm">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="confirmation" id="modalConfirmInput">
      <div class="sys-modal-actions">
        <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        <button type="submit" class="confirm-btn" id="modalConfirmBtn" disabled>Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal logic
function openModal(id, phrase, action, description) {
  document.getElementById('modalTitle').textContent = phrase;
  document.getElementById('modalDesc').textContent = description;
  document.getElementById('modalPhrase').textContent = phrase;
  document.getElementById('modalForm').action = action;
  document.getElementById('modalInput').value = '';
  document.getElementById('modalConfirmBtn').disabled = true;
  document.getElementById('sysModal').classList.add('active');
  document.getElementById('modalInput').dataset.phrase = phrase;
  setTimeout(() => document.getElementById('modalInput').focus(), 100);
}

function closeModal() {
  document.getElementById('sysModal').classList.remove('active');
}

document.getElementById('modalInput').addEventListener('input', function() {
  const match = this.value.trim() === this.dataset.phrase;
  document.getElementById('modalConfirmBtn').disabled = !match;
  document.getElementById('modalConfirmInput').value = this.value.trim();
});

document.getElementById('sysModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});

// Table sizes loader
(function loadTableSizes() {
  fetch('/admin/system/table-sizes')
    .then(r => r.json())
    .then(data => {
      const container = document.getElementById('tableSizesContainer');
      if (!data.tables || data.tables.length === 0) {
        container.innerHTML = '<p style="color:#aaa;font-size:13px;">No tables found.</p>';
        return;
      }
      let html = '<table class="sys-table-sizes"><thead><tr><th>Table</th><th>Rows</th><th>Size</th></tr></thead><tbody>';
      data.tables.forEach(t => {
        html += '<tr><td><code style="font-size:12px;">' + t.table_name + '</code></td>';
        html += '<td style="text-align:right;">' + Number(t.row_count || 0).toLocaleString() + '</td>';
        html += '<td style="text-align:right;white-space:nowrap;">' + t.total_size + '</td></tr>';
      });
      html += '</tbody></table>';
      container.innerHTML = html;
    })
    .catch(() => {
      document.getElementById('tableSizesContainer').innerHTML = '<p style="color:#ccc;font-size:13px;">Failed to load table sizes.</p>';
    });
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
<?php
$pageTitle = 'Notifications';
$activeNav = 'notifications';
ob_start();
?>

<?php if (!empty($flash_success)): ?>
  <div class="alert alert-success"><?= h($flash_success) ?></div>
<?php endif; ?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
  <h1>Notifications</h1>
  <?php if (!empty($notifications)): ?>
    <form method="POST" action="/admin/notifications/read-all">
      <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
      <button type="submit" class="btn light sm">Mark all as read</button>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
    <h3>No notifications</h3>
    <p>You're all caught up.</p>
  </div>
<?php else: ?>
  <div class="notif-list">
    <?php foreach ($notifications as $n): ?>
      <a href="/admin/notifications/<?= h($n['id']) ?>/read"
         class="notif-item <?= $n['is_read'] ? '' : 'notif-unread' ?>"
         style="display:flex; gap:14px; padding:16px; border-bottom:1px solid #eee; text-decoration:none; color:inherit; transition:background .15s;">
        <div class="notif-icon" style="flex-shrink:0; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center;
          <?php
            $bg = '#e3e8ef'; $fg = '#666';
            if (str_contains($n['type'], 'approved')) { $bg = '#d4edda'; $fg = '#28a745'; }
            elseif (str_contains($n['type'], 'rejected')) { $bg = '#f8d7da'; $fg = '#dc3545'; }
            elseif (str_contains($n['type'], 'submitted')) { $bg = '#cce5ff'; $fg = '#007bff'; }
          ?>
          background:<?= $bg ?>; color:<?= $fg ?>;">
          <?php if (str_contains($n['type'], 'approved')): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
          <?php elseif (str_contains($n['type'], 'rejected')): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
          <?php elseif (str_contains($n['type'], 'submitted')): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <?php else: ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
          <?php endif; ?>
        </div>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:<?= $n['is_read'] ? '400' : '600' ?>; font-size:14px; margin-bottom:3px;"><?= h($n['title']) ?></div>
          <?php if (!empty($n['body'])): ?>
            <div style="font-size:13px; color:#666; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($n['body']) ?></div>
          <?php endif; ?>
          <div style="font-size:12px; color:#999; margin-top:4px;">
            <?= h(date('M j, g:i A', strtotime($n['created_at']))) ?>
          </div>
        </div>
        <?php if (!$n['is_read']): ?>
          <div style="flex-shrink:0; width:8px; height:8px; border-radius:50%; background:var(--accent,#cc0000); margin-top:6px;"></div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<style>
  .notif-item:hover { background: #f8f9fa; }
  .notif-unread { background: #fafbff; }
</style>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
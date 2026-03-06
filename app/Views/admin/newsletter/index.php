<?php
$pageTitle = $pageTitle ?? 'Newsletter';
$activeNav = $activeNav ?? 'newsletter';
$tab       = $tab ?? 'sent';
$stats     = $stats ?? [];
$queueStats = $queueStats ?? [];
ob_start();
?>

<!-- Flash messages -->
<?php if (!empty($flash_success)): ?>
  <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Header + Actions -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="margin:0">Newsletter</h1>
    <p class="muted" style="margin:4px 0 0">
      <?= number_format($activeCount ?? 0) ?> active subscribers
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/admin/newsletter/compose" class="btn">+ Compose</a>
  </div>
</div>

<!-- Stats bar -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px">
  <div class="card" style="padding:14px;text-align:center">
    <div style="font-size:24px;font-weight:700;color:#1a1a1a"><?= $stats['sent'] ?? 0 ?></div>
    <div class="muted" style="font-size:12px">Sent</div>
  </div>
  <div class="card" style="padding:14px;text-align:center">
    <div style="font-size:24px;font-weight:700;color:#1a1a1a"><?= $activeCount ?? 0 ?></div>
    <div class="muted" style="font-size:12px">Subscribers</div>
  </div>
  <div class="card" style="padding:14px;text-align:center">
    <div style="font-size:24px;font-weight:700;color:<?= ($queueStats['pending'] ?? 0) > 0 ? '#e67700' : '#1a1a1a' ?>"><?= $queueStats['pending'] ?? 0 ?></div>
    <div class="muted" style="font-size:12px">Queue Pending</div>
  </div>
  <div class="card" style="padding:14px;text-align:center">
    <div style="font-size:24px;font-weight:700;color:<?= ($queueStats['failed'] ?? 0) > 0 ? '#cc0000' : '#1a1a1a' ?>"><?= $queueStats['failed'] ?? 0 ?></div>
    <div class="muted" style="font-size:12px">Queue Failed</div>
  </div>
</div>

<!-- Test email + Process queue -->
<div class="card" style="padding:16px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:end">
  <form method="POST" action="/admin/newsletter/test" style="display:flex;gap:8px;flex:1;min-width:250px;align-items:end">
    <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
    <div style="flex:1">
      <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;margin-bottom:4px">Send Test Email</label>
      <input type="email" name="test_email" placeholder="your@email.com" required class="form-control" style="padding:8px 12px;width:100%">
    </div>
    <button type="submit" class="btn sm" style="white-space:nowrap">Send Test</button>
  </form>
  <form method="POST" action="/admin/newsletter/process-queue" style="display:flex;align-items:end">
    <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
    <button type="submit" class="btn sm" style="background:#333;white-space:nowrap" data-confirm="Process email queue now?" data-confirm-title="Process Queue" data-confirm-level="info" data-confirm-ok="Process">
      Process Queue (<?= $queueStats['pending'] ?? 0 ?>)
    </button>
  </form>
</div>

<!-- ── Email Queue Detail Panel ───────────────────────────────────── -->
<?php
$queueItems  = $queueItems ?? [];
$hasPending  = ($queueStats['pending'] ?? 0) > 0;
$hasFailed   = ($queueStats['failed']  ?? 0) > 0;
$panelOpen   = $hasPending || $hasFailed;
?>
<div class="card" style="margin-bottom:20px;overflow:hidden">

  <!-- Panel header — always visible, click to expand/collapse -->
  <button
    type="button"
    id="queue-panel-toggle"
    onclick="(function(){var p=document.getElementById('queue-panel-body');var a=document.getElementById('queue-panel-arrow');var open=p.style.display!=='none';p.style.display=open?'none':'block';a.style.transform=open?'rotate(0deg)':'rotate(180deg)';})()"
    style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:none;border:none;cursor:pointer;font-family:inherit;gap:12px"
  >
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-weight:700;font-size:14px">Email Queue</span>
      <?php if ($hasFailed): ?>
        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#fef2f2;color:#cc0000">
          <?= $queueStats['failed'] ?> failed
        </span>
      <?php endif; ?>
      <?php if ($hasPending): ?>
        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#fff7ed;color:#e67700">
          <?= $queueStats['pending'] ?> pending
        </span>
      <?php endif; ?>
      <?php if (!$hasFailed && !$hasPending): ?>
        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;background:#ecfdf5;color:#15803d">
          Queue clear
        </span>
      <?php endif; ?>
    </div>
    <svg id="queue-panel-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
      style="flex-shrink:0;transition:transform .2s ease;transform:rotate(<?= $panelOpen ? '180' : '0' ?>deg)">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
  </button>

  <!-- Panel body — expanded by default if there are pending/failed items -->
  <div id="queue-panel-body" style="display:<?= $panelOpen ? 'block' : 'none' ?>;border-top:1px solid #e2e2e2">
    <?php if (empty($queueItems)): ?>
      <p class="muted" style="padding:20px 16px;margin:0;font-size:14px">No items in the queue.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="border-bottom:1px solid #e2e2e2;background:#fafafa">
              <th style="text-align:left;padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600;white-space:nowrap">Recipient</th>
              <th style="text-align:left;padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600">Subject</th>
              <th style="text-align:center;padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600;white-space:nowrap">Status</th>
              <th style="text-align:center;padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600;white-space:nowrap">Retries</th>
              <th style="text-align:left;padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600;white-space:nowrap">Queued At</th>
              <th style="text-align:left;padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600">Last Error</th>
              <th style="padding:9px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600;white-space:nowrap;text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($queueItems as $qi): ?>
              <?php
                $qStatus = $qi['status'] ?? 'pending';
                $qColors = [
                    'pending' => ['bg'=>'#fff7ed','fg'=>'#e67700'],
                    'sent'    => ['bg'=>'#ecfdf5','fg'=>'#15803d'],
                    'failed'  => ['bg'=>'#fef2f2','fg'=>'#cc0000'],
                ];
                $qc = $qColors[$qStatus] ?? ['bg'=>'#f3f4f6','fg'=>'#666'];
                $retries = (int)($qi['attempts'] ?? 0);
              ?>
              <tr style="border-bottom:1px solid #f0f0f0">
                <td style="padding:9px 14px;white-space:nowrap">
                  <?php if (!empty($qi['to_name'])): ?>
                    <span style="font-weight:600"><?= h($qi['to_name']) ?></span><br>
                    <span style="color:#888;font-size:12px"><?= h($qi['to_email']) ?></span>
                  <?php else: ?>
                    <span><?= h($qi['to_email']) ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:9px 14px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?= h($qi['subject'] ?? '—') ?>
                </td>
                <td style="padding:9px 14px;text-align:center">
                  <span style="display:inline-block;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700;background:<?= $qc['bg'] ?>;color:<?= $qc['fg'] ?>">
                    <?= ucfirst($qStatus) ?>
                  </span>
                </td>
                <td style="padding:9px 14px;text-align:center;font-weight:<?= $retries > 0 ? '700' : '400' ?>;color:<?= $retries >= 3 ? '#cc0000' : ($retries > 0 ? '#e67700' : '#666') ?>">
                  <?= $retries ?>
                </td>
                <td style="padding:9px 14px;color:#666;white-space:nowrap;font-size:12px">
                  <?= !empty($qi['created_at']) ? date('M j, g:ia', strtotime($qi['created_at'])) : '—' ?>
                </td>
                <td style="padding:9px 14px;color:#999;font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?php if (!empty($qi['last_error'])): ?>
                    <span title="<?= h($qi['last_error']) ?>" style="cursor:help;color:#cc0000">
                      <?= h(mb_strimwidth($qi['last_error'], 0, 60, '…')) ?>
                    </span>
                  <?php else: ?>
                    <span style="color:#bbb">—</span>
                  <?php endif; ?>
                </td>
                <td style="padding:9px 14px;text-align:right;white-space:nowrap">
                  <?php if ($qStatus === 'failed'): ?>
                    <form method="POST" action="/admin/newsletter/queue/<?= h($qi['id']) ?>/retry" style="display:inline">
                      <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                      <button type="submit" class="btn sm" style="background:#15803d;font-size:11px;padding:4px 10px">Retry</button>
                    </form>
                  <?php endif; ?>
                  <?php if (in_array($qStatus, ['pending', 'failed'])): ?>
                    <form method="POST" action="/admin/newsletter/queue/<?= h($qi['id']) ?>/delete" style="display:inline"
                          data-confirm="Delete this queued email? It will not be sent." data-confirm-title="Delete Queue Item" data-confirm-level="warn" data-confirm-ok="Delete">
                      <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                      <button type="submit" class="btn sm" style="background:#cc0000;font-size:11px;padding:4px 10px">Delete</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($qStatus === 'sent'): ?>
                    <span style="color:#bbb;font-size:12px">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
        $shownCount = count($queueItems);
        $totalQueue = ($queueStats['pending'] ?? 0) + ($queueStats['failed'] ?? 0) + ($queueStats['sent'] ?? 0);
      ?>
      <?php if ($totalQueue > $shownCount): ?>
        <p class="muted" style="padding:10px 16px;margin:0;font-size:12px;border-top:1px solid #f0f0f0">
          Showing <?= $shownCount ?> most recent items. <?= number_format($totalQueue) ?> total in queue.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #e2e2e2">
  <a href="/admin/newsletter?tab=sent" style="padding:10px 20px;font-weight:600;font-size:14px;text-decoration:none;border-bottom:2px solid <?= $tab === 'sent' ? '#cc0000' : 'transparent' ?>;margin-bottom:-2px;color:<?= $tab === 'sent' ? '#cc0000' : '#666' ?>">
    Campaigns (<?= $stats['sent'] ?? 0 ?>)
  </a>
  <a href="/admin/newsletter?tab=scheduled" style="padding:10px 20px;font-weight:600;font-size:14px;text-decoration:none;border-bottom:2px solid <?= $tab === 'scheduled' ? '#0369a1' : 'transparent' ?>;margin-bottom:-2px;color:<?= $tab === 'scheduled' ? '#0369a1' : '#666' ?>;display:flex;align-items:center;gap:6px">
    Scheduled
    <?php if (($stats['scheduled'] ?? 0) > 0): ?>
      <span style="background:#eff6ff;color:#0369a1;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px"><?= $stats['scheduled'] ?></span>
    <?php endif; ?>
  </a>
  <a href="/admin/newsletter?tab=drafts" style="padding:10px 20px;font-weight:600;font-size:14px;text-decoration:none;border-bottom:2px solid <?= $tab === 'drafts' ? '#e67700' : 'transparent' ?>;margin-bottom:-2px;color:<?= $tab === 'drafts' ? '#e67700' : '#666' ?>;display:flex;align-items:center;gap:6px">
    Drafts
    <?php if (($stats['draft'] ?? 0) > 0): ?>
      <span style="background:#fff7ed;color:#e67700;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px"><?= $stats['draft'] ?></span>
    <?php endif; ?>
  </a>
  <a href="/admin/newsletter?tab=trash" style="padding:10px 20px;font-weight:600;font-size:14px;text-decoration:none;border-bottom:2px solid <?= $tab === 'trash' ? '#cc0000' : 'transparent' ?>;margin-bottom:-2px;color:<?= $tab === 'trash' ? '#cc0000' : '#666' ?>">
    Trash (<?= $stats['deleted'] ?? 0 ?>)
  </a>
</div>

<!-- Issues list -->
<?php if (empty($issues)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <p class="muted">
      <?php if ($tab === 'trash'): ?>Trash is empty.
      <?php elseif ($tab === 'drafts'): ?>No drafts saved yet.
      <?php elseif ($tab === 'scheduled'): ?>No newsletters scheduled.
      <?php else: ?>No newsletters sent yet.<?php endif; ?>
    </p>
    <?php if ($tab === 'sent' || $tab === 'drafts' || $tab === 'scheduled'): ?>
      <a href="/admin/newsletter/compose" class="btn" style="margin-top:12px">Compose Newsletter</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card" style="overflow-x:auto">
    <table class="" style="width:100%">
      <thead>
        <tr>
          <th style="text-align:left">Subject</th>
          <th>Status</th>
          <th>Sent</th>
          <th>Failed</th>
          <th>Sent By</th>
          <th>Date</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($issues as $issue): ?>
          <tr>
            <td style="font-weight:600;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($issue['subject'] ?? '') ?>
            </td>
            <td style="text-align:center">
              <?php
                $statusColors = ['sent'=>'#15803d','sending'=>'#e67700','draft'=>'#666','failed'=>'#cc0000','deleted'=>'#999'];
                $st = $issue['status'] ?? 'draft';
                $color = $statusColors[$st] ?? '#666';
              ?>
              <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;background:<?= $color ?>15;color:<?= $color ?>">
                <?= ucfirst($st) ?>
              </span>
            </td>
            <td style="text-align:center;font-weight:600"><?= number_format((int)($issue['sent_count'] ?? 0)) ?></td>
            <td style="text-align:center;color:<?= ($issue['failed_count'] ?? 0) > 0 ? '#cc0000' : '#666' ?>"><?= (int)($issue['failed_count'] ?? 0) ?></td>
            <td style="text-align:center;font-size:13px"><?= h($issue['sent_by_name'] ?? '—') ?></td>
            <td style="text-align:center;font-size:13px;color:#666">
              <?php if ($tab === 'scheduled' && !empty($issue['scheduled_at'])): ?>
                <span style="color:#0369a1;font-weight:600"><?= date('M j, Y g:ia', strtotime($issue['scheduled_at'])) ?></span>
              <?php elseif (!empty($issue['sent_at'])): ?>
                <?= date('M j, Y g:ia', strtotime($issue['sent_at'])) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="text-align:right;white-space:nowrap">
              <?php if ($tab === 'trash'): ?>
                <form method="POST" action="/admin/newsletter/<?= $issue['id'] ?>/restore" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button type="submit" class="btn sm" style="background:#15803d;font-size:11px;padding:4px 10px">Restore</button>
                </form>
                <form method="POST" action="/admin/newsletter/<?= $issue['id'] ?>/permanent-delete" style="display:inline" data-confirm="Permanently delete this newsletter? This cannot be undone." data-confirm-title="Delete Newsletter" data-confirm-level="danger" data-confirm-ok="Delete Forever">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button type="submit" class="btn sm" style="background:#cc0000;font-size:11px;padding:4px 10px">Delete Forever</button>
                </form>
              <?php elseif ($tab === 'scheduled'): ?>
                <form method="POST" action="/admin/newsletter/<?= h($issue['id']) ?>/delete" style="display:inline"
                      data-confirm="Cancel this scheduled newsletter?" data-confirm-title="Cancel Schedule" data-confirm-level="warn" data-confirm-ok="Cancel Send">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button type="submit" class="btn sm" style="background:#666;font-size:11px;padding:4px 10px">Cancel</button>
                </form>
              <?php elseif ($tab === 'drafts'): ?>
                <a href="/admin/newsletter/compose?draft_id=<?= h($issue['id']) ?>" class="btn sm" style="background:#e67700;color:#fff;font-size:11px;padding:4px 10px">Edit Draft</a>
                <form method="POST" action="/admin/newsletter/<?= h($issue['id']) ?>/draft-delete" style="display:inline"
                      data-confirm="Delete this draft permanently?" data-confirm-title="Delete Draft" data-confirm-level="danger" data-confirm-ok="Delete">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button type="submit" class="btn sm" style="background:#cc0000;font-size:11px;padding:4px 10px">Delete</button>
                </form>
              <?php else: ?>
                <form method="POST" action="/admin/newsletter/<?= $issue['id'] ?>/delete" style="display:inline" data-confirm="Move this newsletter to trash?" data-confirm-title="Trash Newsletter" data-confirm-level="warn" data-confirm-ok="Move to Trash">
                  <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                  <button type="submit" class="btn sm" style="background:#666;font-size:11px;padding:4px 10px">Trash</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:16px">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="/admin/newsletter?tab=<?= $tab ?>&page=<?= $p ?>" class="btn sm" style="<?= $p === $page ? 'background:#cc0000' : 'background:#eee;color:#333' ?>;padding:6px 12px;font-size:12px"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
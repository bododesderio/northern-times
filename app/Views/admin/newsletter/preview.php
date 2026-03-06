<?php
$pageTitle = $pageTitle ?? 'Preview Newsletter';
$activeNav = $activeNav ?? 'newsletter';
ob_start();
?>

<div style="max-width:800px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
      <h1 style="margin:0">Preview Newsletter</h1>
      <p class="muted" style="margin:4px 0 0">
        <?= (int)($articleCount ?? 0) ?> articles &middot;
        Sending to <?= number_format($activeCount ?? 0) ?> subscribers
      </p>
    </div>
  </div>

  <!-- Summary card -->
  <div class="card" style="padding:16px;margin-bottom:16px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600">Subject</div>
      <div style="font-size:16px;font-weight:700;margin-top:2px"><?= h($subject ?? '') ?></div>
    </div>
    <div style="display:flex;gap:8px">
      <!-- Back to edit -->
      <form method="POST" action="/admin/newsletter/preview" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
        <input type="hidden" name="subject" value="<?= h($subject ?? '') ?>">
        <input type="hidden" name="intro" value="<?= h($intro ?? '') ?>">
        <?php foreach (($articleIds ?? []) as $aid): ?>
          <input type="hidden" name="article_ids[]" value="<?= h($aid) ?>">
        <?php endforeach; ?>
        <button type="button" onclick="history.back()" class="btn sm" style="background:#eee;color:#333">← Edit</button>
      </form>

      <!-- Schedule -->
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div id="schedulePanel" style="display:none;align-items:center;gap:8px;flex-wrap:wrap">
          <form method="POST" action="/admin/newsletter/schedule" id="scheduleForm">
            <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
            <input type="hidden" name="subject" value="<?= h($subject ?? '') ?>">
            <input type="hidden" name="intro" value="<?= h($intro ?? '') ?>">
            <?php foreach (($articleIds ?? []) as $aid): ?>
              <input type="hidden" name="article_ids[]" value="<?= h($aid) ?>">
            <?php endforeach; ?>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="datetime-local" name="send_at" id="scheduleAt" required
                     min="<?= date('Y-m-d\TH:i') ?>"
                     style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;background:var(--paper);color:var(--ink)">
              <button type="submit" class="btn sm" style="background:#e67700;white-space:nowrap">
                &#128197; Confirm Schedule
              </button>
              <button type="button" class="btn sm" style="background:#eee;color:#333" onclick="document.getElementById('schedulePanel').style.display='none'">Cancel</button>
            </div>
          </form>
        </div>
        <button type="button" class="btn sm" style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db"
                onclick="var p=document.getElementById('schedulePanel');p.style.display=p.style.display==='none'?'flex':'none'">
          &#128197; Schedule
        </button>
      </div>

      <!-- Send now -->
      <form method="POST" action="/admin/newsletter/send" id="sendForm">
        <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
        <input type="hidden" name="subject" value="<?= h($subject ?? '') ?>">
        <input type="hidden" name="intro" value="<?= h($intro ?? '') ?>">
        <?php foreach (($articleIds ?? []) as $aid): ?>
          <input type="hidden" name="article_ids[]" value="<?= h($aid) ?>">
        <?php endforeach; ?>
        <button type="submit" class="btn" data-confirm="Send this newsletter to <?= number_format($activeCount ?? 0) ?> subscribers? This cannot be undone." data-confirm-title="Send Newsletter" data-confirm-level="warn" data-confirm-ok="Send Now">
          Send Now &rarr;
        </button>
      </form>
    </div>
  </div>

  <!-- Email preview -->
  <div class="card" style="padding:0;overflow:hidden">
    <div style="padding:12px 16px;background:#f5f5f5;border-bottom:1px solid #e2e2e2;font-size:12px;color:#666;display:flex;gap:16px">
      <span><strong>From:</strong> <?= h($_ENV['MAIL_FROM_NAME'] ?? get_site_setting('site_title', 'Newsletter')) ?></span>
      <span><strong>Subject:</strong> <?= h($subject ?? '') ?></span>
    </div>
    <div style="background:#f4f4f5;padding:16px">
      <iframe id="previewFrame" style="width:100%;min-height:600px;border:none;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1)" srcdoc="<?= htmlspecialchars($previewHtml ?? '', ENT_QUOTES, 'UTF-8') ?>"></iframe>
    </div>
  </div>
</div>

<script>
// Auto-resize iframe
const frame = document.getElementById('previewFrame');
if (frame) {
  frame.onload = function() {
    try { frame.style.height = frame.contentDocument.body.scrollHeight + 40 + 'px'; } catch(e) {}
  };
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
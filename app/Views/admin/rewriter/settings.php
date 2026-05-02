<?php
$pageTitle = 'Rewriter Settings';
$activeNav = 'rewriter-settings';
$slot = null;
ob_start();
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Service Status Card -->
<div style="background:var(--surface,#fff);border-radius:14px;border:1px solid var(--border,#e2e2e2);padding:20px;margin-bottom:24px">
  <div style="font-weight:700;font-size:15px;margin-bottom:14px">Service Status</div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
    <div style="padding:14px;border-radius:10px;background:<?= $healthy ? '#e8f5e9' : '#ffebee' ?>;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:13px;font-weight:600;color:<?= $healthy ? '#2e7d32' : '#c62828' ?>"><?= $healthy ? '● Connected' : '● Unreachable' ?></div>
      <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Rewriter service</div>
    </div>
    <div style="padding:14px;border-radius:10px;background:#f5f5f5;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:13px;font-weight:600"><?= h($model) ?></div>
      <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Ollama model</div>
    </div>
    <div style="padding:14px;border-radius:10px;background:#f5f5f5;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:13px;font-weight:600">&plusmn;<?= h($tolerance) ?> words</div>
      <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Word tolerance</div>
    </div>
    <div style="padding:14px;border-radius:10px;background:#f5f5f5;border:1px solid var(--border,#e2e2e2)">
      <div style="font-size:13px;font-weight:600"><?= h($batchSize) ?>/run</div>
      <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Batch size</div>
    </div>
  </div>
</div>

<!-- Rewriter Rules & Settings -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden;margin-bottom:24px">
  <form method="POST" action="/admin/rewriter/rules">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);display:flex;justify-content:space-between;align-items:center">
      <div>
        <span style="font-weight:700;font-size:15px">Rewriter Rules &amp; Settings</span>
        <div style="font-size:12px;color:var(--muted,#888);margin-top:2px">Configure AI rewriting behaviour and editorial guidelines</div>
      </div>
      <button type="submit" style="padding:8px 18px;background:var(--accent,#cc0000);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Save</button>
    </div>

    <div style="padding:20px;display:flex;flex-direction:column;gap:20px">
      <!-- Auto-Apply Toggle -->
      <div>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="auto_apply" value="1" <?= ($autoApply ?? 'false') === 'true' ? 'checked' : '' ?>
                 style="width:18px;height:18px;accent-color:var(--accent,#cc0000)">
          <div>
            <div style="font-weight:600;font-size:14px">Auto-apply rewrites</div>
            <div style="font-size:12px;color:var(--muted,#888)">When enabled, rewrites are applied immediately without editor review. When disabled, rewrites go to the approval queue.</div>
          </div>
        </label>
      </div>

      <!-- Max Words -->
      <div>
        <label style="display:block;font-weight:600;font-size:14px;margin-bottom:6px">Max words per chunk</label>
        <input type="number" name="max_words" value="<?= h($maxWords ?? '4000') ?>" min="500" max="10000" step="500"
               style="padding:8px 12px;border:1px solid var(--border,#ddd);border-radius:8px;font-size:13px;width:120px">
        <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">Articles exceeding this word count are split into chunks and rewritten in parts. Recommended: 3000–5000 for llama3:8b.</div>
      </div>

      <!-- Editorial Rules -->
      <div>
        <label style="display:block;font-weight:600;font-size:14px;margin-bottom:6px">Editorial Rules</label>
        <textarea name="rules" rows="10"
                  style="width:100%;padding:12px 14px;border:1px solid var(--border,#ddd);border-radius:10px;font-size:13px;font-family:'JetBrains Mono','Fira Code',monospace;line-height:1.6;resize:vertical"
                  placeholder="Add editorial guidelines for the AI rewriter. These are injected into every rewrite prompt. Example:&#10;&#10;- Always use &quot;Mr./Ms.&quot; for first reference to government officials&#10;- Never use passive voice in headlines&#10;- Attribute all statistics to their source&#10;- Do not add opinions or commentary&#10;- Use &quot;Uganda&quot; not &quot;the country&quot; on first reference&#10;- Keep all direct quotes exactly as they appear&#10;- Spell out numbers under 10"
        ><?= h($rules ?? '') ?></textarea>
        <div style="font-size:12px;color:var(--muted,#888);margin-top:4px">These rules are passed to the AI model as strict editorial guidelines with every rewrite. Be specific and clear. One rule per line.</div>
      </div>
    </div>
  </form>
</div>

<!-- Per-Source Auto-Rewrite Toggles -->
<div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden;margin-bottom:24px">
  <form method="POST" action="/admin/rewriter/settings">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border,#e2e2e2);display:flex;justify-content:space-between;align-items:center">
      <div>
        <span style="font-weight:700;font-size:15px">Auto-Rewrite per Source</span>
        <div style="font-size:12px;color:var(--muted,#888);margin-top:2px">Crawled articles from enabled sources will be automatically queued for rewriting</div>
      </div>
      <button type="submit" style="padding:8px 18px;background:var(--accent,#cc0000);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Save</button>
    </div>

    <?php if (empty($sources)): ?>
      <div style="padding:40px;text-align:center;color:var(--muted,#888)">No crawl sources configured yet.</div>
    <?php else: ?>
      <div style="padding:8px 0">
        <?php foreach ($sources as $src): ?>
          <label style="display:flex;align-items:center;gap:12px;padding:12px 20px;cursor:pointer;border-bottom:1px solid var(--border,#f5f5f5);transition:background .1s"
                 onmouseover="this.style.background='var(--surface-alt,#f9f9f9)'" onmouseout="this.style.background='transparent'">
            <input type="checkbox" name="auto_rewrite[]" value="<?= (int)$src['id'] ?>"
                   <?= !empty($src['auto_rewrite']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--accent,#cc0000)">
            <span style="font-weight:600;font-size:14px"><?= h($src['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </form>
</div>

<!-- Environment Note -->
<div style="background:#fff8e1;border:1px solid #ffecb3;border-radius:12px;padding:14px 18px;margin-bottom:24px;font-size:13px;color:#795548">
  <strong>Note:</strong> Model, word tolerance, batch size and timeout are configured via environment variables
  (<code>OLLAMA_MODEL</code>, <code>REWRITER_WORD_TOLERANCE</code>, <code>REWRITER_BATCH_SIZE</code>, <code>REWRITER_TIMEOUT</code>).
  Set <code>REWRITER_ENABLED=true</code> in <code>.env</code> to activate the cron job.
</div>

<!-- Crontab -->
<div style="background:var(--surface,#fff);border-radius:14px;border:1px solid var(--border,#e2e2e2);padding:20px">
  <div style="font-weight:700;font-size:15px;margin-bottom:10px">Crontab Setup</div>
  <div style="font-size:13px;color:var(--muted,#888);margin-bottom:10px">Add this to your container's crontab to process the rewrite queue every minute:</div>
  <div style="background:#0a0a14;color:#b8b8c8;padding:14px 16px;border-radius:10px;font-family:'JetBrains Mono','Fira Code','Consolas',monospace;font-size:12px;overflow-x:auto">
    * * * * * cd /var/www/html && php cron/rewrite.php >> storage/logs/rewriter.log 2>&1
  </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>

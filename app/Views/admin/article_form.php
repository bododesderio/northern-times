<?php
$isEdit = ($mode ?? 'create') === 'edit';
$pageTitle = $isEdit ? 'Edit Article' : 'New Article';
$activeNav = 'articles';

$action = $isEdit ? ('/admin/articles/' . h($article['id'])) : '/admin/articles';
$publishedAtLabel = !empty($article['published_at'])
    ? (($article['status'] ?? '') === 'scheduled'
        ? '⏰ Scheduled for ' . date('F j, Y \a\t g:i A', strtotime($article['published_at']))
        : date('F j, Y \a\t g:i A', strtotime($article['published_at'])))
    : '— (auto when published)';

// Role-based status options
$isEditor = function_exists('user_is_editor') ? user_is_editor() : false;
$currentStatus = $article['status'] ?? 'draft';

// Was this article recently rejected? (status back to draft + has review notes)
$wasRejected = $isEdit
    && $currentStatus === 'draft'
    && !empty($article['review_notes'])
    && !empty($article['reviewed_at']);

ob_start();
?>
<div class="card" style="max-width:980px">
  <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-end;margin-bottom:20px">
    <div>
      <h1 style="margin:0 0 8px;"><?= h($pageTitle) ?></h1>
      <div class="muted" style="font-size:14px">Slug auto-generated. Publish date set automatically. Autosave active.</div>
    </div>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <span id="autosaveBadge" class="muted" style="font-size:14px">Autosave: idle</span>
      <button type="button" class="btn light" id="insertMediaBtn">Insert Media</button>
    </div>
  </div>

  <?php if (!empty($flash_error)): ?>
    <div class="flash bad" style="margin:16px 0"><?= h($flash_error) ?></div>
  <?php endif; ?>

  <?php if ($wasRejected): ?>
    <div style="margin:16px 0;padding:16px 20px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;border-left:4px solid #dc2626">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <strong style="color:#b91c1c;font-size:14px">Revision Requested</strong>
        <?php if (!empty($article['reviewed_at'])): ?>
          <span style="color:#999;font-size:12px;margin-left:auto"><?= date('M j, g:i A', strtotime($article['reviewed_at'])) ?></span>
        <?php endif; ?>
      </div>
      <p style="color:#7f1d1d;font-size:14px;line-height:1.6;margin:0"><?= h($article['review_notes']) ?></p>
      <p style="color:#999;font-size:12px;margin:8px 0 0">Address the feedback above and submit for review again when ready.</p>
    </div>
  <?php endif; ?>

  <?php if ($isEdit && $currentStatus === 'pending_review'): ?>
    <div style="margin:16px 0;padding:14px 20px;background:#fef9c3;border:1px solid #fde68a;border-radius:12px;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#854d0e" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span style="color:#854d0e;font-size:14px;font-weight:600">This article is awaiting editorial review.</span>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= h($action) ?>" id="articleForm">
    <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">

    <label style="display:block;margin:16px 0 8px;font-weight:600">Title *</label>
    <input name="title" id="title" required value="<?= h($article['title'] ?? '') ?>" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:18px">

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px">
      <div>
        <label style="display:block;margin-bottom:8px;font-weight:600">Category *</label>
        <select name="category_id" id="category_id" required style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px">
          <option value="">Select category</option>
          <?php foreach (($categories ?? []) as $c): ?>
            <option value="<?= h($c['id']) ?>" <?= ((string)($article['category_id'] ?? '') === (string)$c['id']) ? 'selected' : '' ?>>
              <?= h($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label style="display:block;margin-bottom:8px;font-weight:600">Status</label>
        <?php if ($isEditor): ?>
          <!-- Editors/Super Admin: full control -->
          <select name="status" id="status" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px" onchange="toggleScheduleUI()">
            <?php foreach (['draft','pending_review','published','scheduled','archived'] as $s): ?>
              <option value="<?= h($s) ?>" <?= ($currentStatus === $s) ? 'selected' : '' ?>>
                <?= $s === 'pending_review' ? 'Pending Review' : ucfirst($s) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <!-- Authors: can only draft or submit for review -->
          <select name="status" id="status" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px" onchange="toggleScheduleUI()">
            <option value="draft" <?= ($currentStatus === 'draft') ? 'selected' : '' ?>>Draft</option>
            <option value="pending_review" <?= ($currentStatus === 'pending_review') ? 'selected' : '' ?>>Submit for Review</option>
            <?php if ($currentStatus === 'published'): ?>
              <option value="published" selected disabled>Published (editor only)</option>
            <?php endif; ?>
            <?php if ($currentStatus === 'scheduled'): ?>
              <option value="scheduled" selected disabled>Scheduled (editor only)</option>
            <?php endif; ?>
            <?php if ($currentStatus === 'archived'): ?>
              <option value="archived" selected disabled>Archived (editor only)</option>
            <?php endif; ?>
          </select>
          <div class="form-hint" style="margin-top:6px;font-size:12px;color:#888">
            Articles must be reviewed and approved by an editor before publishing.
          </div>
        <?php endif; ?>

        <!-- Schedule datetime picker (shown only when status = scheduled) -->
        <div id="scheduleBox" style="display:none;margin-top:10px;padding:12px;background:#fffbea;border:1px solid #ffe082;border-radius:12px">
          <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">
            ⏰ Publish Date & Time
          </label>
          <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                 value="<?= ($currentStatus === 'scheduled' && !empty($article['published_at']))
                    ? date('Y-m-d\TH:i', strtotime($article['published_at']))
                    : '' ?>"
                 min="<?= date('Y-m-d\TH:i') ?>"
                 style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:8px;font-size:15px" />
          <div style="margin-top:6px;font-size:12px;color:#888">
            Article will auto-publish at this date/time. Cron must be running.
          </div>
        </div>
      </div>

      <div>
        <label style="display:block;margin-bottom:8px;font-weight:600">Display Author (optional)</label>
        <input name="author_name" id="author_name" placeholder="Admin or custom name" value="<?= h($article['author_name'] ?? 'Admin') ?>"
               style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px">
      </div>

      <div>
        <label style="display:block;margin-bottom:8px;font-weight:600">Featured Image</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input name="featured_image" id="featured_image" placeholder="/uploads/Articles/..." value="<?= h($article['featured_image'] ?? '') ?>"
                 style="flex:1;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px">
          <button type="button" class="btn light" onclick="openFeaturedPicker()" style="white-space:nowrap;padding:12px 16px">Choose</button>
        </div>
        <?php if (!empty($article['featured_image'])): ?>
          <img src="<?= h($article['featured_image']) ?>" alt="" id="featuredPreview"
               style="margin-top:8px;max-height:100px;border-radius:8px;border:1px solid #e2e2e2">
        <?php else: ?>
          <img src="" alt="" id="featuredPreview" style="margin-top:8px;max-height:100px;border-radius:8px;border:1px solid #e2e2e2;display:none">
        <?php endif; ?>
      </div>
    </div>

    <!-- Breaking news + Story thread row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px">
      <?php if (!empty($storyThreads)): ?>
      <div>
        <label style="display:block;margin-bottom:8px;font-weight:600">Ongoing Story (thread)</label>
        <select name="story_thread_id" id="story_thread_id" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px">
          <option value="">— None —</option>
          <?php foreach (($storyThreads ?? []) as $st): ?>
            <option value="<?= h($st['id']) ?>" <?= ((string)($article['story_thread_id'] ?? '') === (string)$st['id']) ? 'selected' : '' ?>>
              <?= h($st['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div>
        <label style="display:block;margin-bottom:8px;font-weight:600">Breaking News</label>
        <div style="display:flex;gap:16px;align-items:center;padding:12px;border:1px solid #e2e2e2;border-radius:12px;background:#fff">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;font-size:15px">
            <input type="checkbox" name="is_breaking" value="1" <?= !empty($article['is_breaking']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:#cc0000"
                   onchange="document.getElementById('breakingHeadlineRow').style.display = this.checked ? 'block' : 'none'">
            Mark as Breaking
          </label>
        </div>
      </div>
    </div>

    <div id="breakingHeadlineRow" style="margin-top:12px;<?= empty($article['is_breaking']) ? 'display:none' : '' ?>">
      <label style="display:block;margin-bottom:8px;font-weight:600">Breaking Headline (ticker text, optional)</label>
      <input name="breaking_headline" id="breaking_headline" placeholder="Short punchy headline for the ticker…"
             value="<?= h($article['breaking_headline'] ?? '') ?>"
             style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:12px;font-size:16px" maxlength="300">
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         TAGS — chip input with autocomplete (Phase 10)
         ═══════════════════════════════════════════════════════════ -->
    <div style="margin-top:20px">
      <label style="display:block;margin-bottom:8px;font-weight:600">
        Tags <span style="font-weight:400;color:#999;font-size:13px">(max 10 — type and press Enter, comma, or Tab to add)</span>
      </label>
      <div id="tagContainer" style="display:flex;flex-wrap:wrap;gap:6px;padding:8px 12px;border:1px solid #e2e2e2;border-radius:12px;min-height:46px;align-items:center;background:#fff;cursor:text;position:relative"
           onclick="document.getElementById('tagInput').focus()">
        <?php foreach (($tags ?? []) as $t): ?>
          <span class="tag-chip" data-name="<?= h($t['name']) ?>">
            #<?= h($t['name']) ?>
            <button type="button" onclick="removeTag(this.parentElement)" style="background:none;border:none;color:inherit;cursor:pointer;font-size:14px;padding:0 2px;opacity:.7">&times;</button>
          </span>
        <?php endforeach; ?>
        <input type="text" id="tagInput" placeholder="Type a tag…"
               style="border:none;outline:none;font-size:14px;flex:1;min-width:120px;background:transparent"
               autocomplete="off" maxlength="60">
        <div id="tagSuggest" style="display:none;position:absolute;left:0;top:100%;z-index:100;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:200px;overflow-y:auto;margin-top:4px;width:100%"></div>
      </div>
      <input type="hidden" name="tags" id="tagsHidden" value="<?= h(implode(',', array_column($tags ?? [], 'name'))) ?>">
    </div>

    <div style="margin:24px 0">
      <label style="display:block;margin-bottom:8px;font-weight:600">Published Date</label>
      <div class="card" style="padding:14px;background:#fafafa;border-radius:12px;font-size:15px">
        <?= h($publishedAtLabel) ?>
      </div>
    </div>

    <label style="display:block;margin:24px 0 8px;font-weight:600">Excerpt (optional)</label>
    <textarea name="excerpt" id="excerpt" style="width:100%;min-height:120px;padding:12px;border:1px solid #e2e2e2;border-radius:12px;resize:vertical;font-size:16px;line-height:1.6"><?= h($article['excerpt'] ?? '') ?></textarea>

    <label style="display:block;margin:32px 0 12px;font-weight:600">Content *</label>
    <textarea name="content" id="content" required style="width:100%;min-height:500px;border:1px solid #e2e2e2;border-radius:12px;resize:vertical;font-size:17px;line-height:1.7"><?= h($article['content'] ?? '') ?></textarea>

    <div style="display:flex;gap:16px;margin-top:32px;flex-wrap:wrap;align-items:center">
      <button class="btn" type="submit" id="saveBtn" style="padding:12px 32px;font-size:17px"><?= $isEdit ? 'Update Article' : 'Create Article' ?></button>

      <?php if (!$isEditor && ($currentStatus === 'draft' || !$isEdit)): ?>
        <button type="submit" class="btn success" id="submitReviewBtn" style="padding:12px 32px;font-size:17px"
                onclick="document.getElementById('status').value='pending_review'">
          <?= $wasRejected ? 'Resubmit for Review' : 'Submit for Review' ?>
        </button>
      <?php endif; ?>

      <a class="btn light" href="/admin/articles" style="padding:12px 32px;font-size:17px">Cancel</a>

      <button type="button" class="btn light" id="clearDraftBtn" title="Remove autosaved draft">Clear Draft</button>

      <?php if ($isEdit): ?>
        <span class="muted" style="margin-left:auto;font-size:15px">
          Slug: <code style="background:#f6f6f6;padding:4px 8px;border-radius:6px"><?= h($article['slug'] ?? '') ?></code>
        </span>
      <?php endif; ?>
    </div>
  </form>

  <!-- ═══════════════════════════════════════════════════════════
       REVISION HISTORY — collapsible table (Phase 10, edit mode only)
       ═══════════════════════════════════════════════════════════ -->
  <?php if ($isEdit && !empty($revisions)): ?>
  <div style="margin-top:28px;border-top:1px solid #e2e2e2;padding-top:20px">
    <details>
      <summary style="cursor:pointer;font-weight:600;font-size:15px;color:var(--muted,#666);padding:8px 0;user-select:none">
        <span style="display:inline-flex;align-items:center;gap:8px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
          Revision History (<?= count($revisions) ?> revision<?= count($revisions) !== 1 ? 's' : '' ?>)
        </span>
      </summary>
      <div style="margin-top:12px;border:1px solid #eee;border-radius:10px;overflow:hidden">
        <table style="width:100%;font-size:13px;border-collapse:collapse">
          <thead>
            <tr style="background:#f9f9f9;text-align:left">
              <th style="padding:10px 14px;font-weight:600">#</th>
              <th style="padding:10px 14px;font-weight:600">Editor</th>
              <th style="padding:10px 14px;font-weight:600">Words</th>
              <th style="padding:10px 14px;font-weight:600">Date</th>
              <th style="padding:10px 14px;font-weight:600">Note</th>
              <th style="padding:10px 14px;font-weight:600"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($revisions as $i => $rev): ?>
              <tr style="border-top:1px solid #f0f0f0;<?= $i === 0 ? 'background:#fefce8' : '' ?>">
                <td style="padding:10px 14px;font-weight:600"><?= (int)$rev['revision_number'] ?></td>
                <td style="padding:10px 14px"><?= h($rev['editor_name']) ?></td>
                <td style="padding:10px 14px"><?= number_format((int)$rev['word_count']) ?></td>
                <td style="padding:10px 14px"><?= date('M j, Y g:i A', strtotime($rev['created_at'])) ?></td>
                <td style="padding:10px 14px;color:#888;font-size:12px"><?= h($rev['note'] ?? '') ?></td>
                <td style="padding:10px 14px">
                  <?php if ($i !== 0): // Don't show restore for the current (newest) revision ?>
                  <form method="POST" action="/admin/articles/<?= h($article['id']) ?>/revisions/<?= (int)$rev['id'] ?>/restore"
                        style="display:inline"
                        onsubmit="return confirm('Restore this revision? Your current content will be saved automatically.')">
                    <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
                    <button type="submit" style="padding:4px 12px;font-size:12px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;color:#374151">
                      Restore
                    </button>
                  </form>
                  <?php else: ?>
                  <span style="font-size:11px;color:#888;font-style:italic">current</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>
  <?php endif; ?>

</div>

<!-- Media Picker Modal — uses the picker iframe in insert mode -->
<div id="mediaModalBackdrop" class="nt-picker-overlay" style="z-index:9998">
  <div class="nt-picker-modal" style="height:min(700px,90vh)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:15px 20px;border-bottom:1px solid var(--adm-border,#e2e2e2);flex-shrink:0">
      <h3 style="margin:0;font-size:15px;font-weight:700">Insert Media from Library</h3>
      <button type="button" class="btn light sm" id="closeMediaModal">Close</button>
    </div>
    <!-- The picker iframe handles search, grid, selection, and insert options natively -->
    <iframe
      id="mediaPickerIframe"
      src=""
      style="flex:1;width:100%;border:none;display:block"
      title="Media Picker"
    ></iframe>
  </div>
</div>

<!-- Tag chip + Revision styles -->
<style>
.tag-chip {
  display:inline-flex;align-items:center;gap:4px;padding:5px 12px;
  border-radius:20px;background:#fef2f2;color:#cc0000;font-size:13px;
  font-weight:500;border:1px solid #fecaca;white-space:nowrap;
  transition:background .15s;
}
.tag-chip:hover { background:#fee2e2; }
.tag-suggest-item {
  padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0;
  transition:background .1s;
}
.tag-suggest-item:hover, .tag-suggest-item.active { background:#fef2f2;color:#cc0000; }
.tag-suggest-item:last-child { border-bottom:none; }
</style>

<!-- CKEditor Upload Adapter -->
<script src="/assets/ckeditor-adapter.js"></script>

<!-- CKEditor CDN -->
<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41.4.2/build/ckeditor.js"></script>

<script>
// ── Schedule UI toggle ──
function toggleScheduleUI() {
  var box = document.getElementById('scheduleBox');
  var sel = document.getElementById('status');
  if (box && sel) {
    box.style.display = (sel.value === 'scheduled') ? 'block' : 'none';
  }
}
// Run on page load to show/hide if editing a scheduled article
document.addEventListener('DOMContentLoaded', toggleScheduleUI);
</script>

<!-- ═══════════════════════════════════════════════════════════
     TAG CHIP INPUT — autocomplete, keyboard nav, comma/tab add
     ═══════════════════════════════════════════════════════════ -->
<script>
(function() {
  var input = document.getElementById('tagInput');
  var container = document.getElementById('tagContainer');
  var hidden = document.getElementById('tagsHidden');
  var suggest = document.getElementById('tagSuggest');
  var debounceTimer = null;
  var activeIdx = -1;

  if (!input || !container || !hidden) return;

  function getTags() {
    return Array.from(container.querySelectorAll('.tag-chip')).map(function(c){ return c.dataset.name; });
  }
  function syncHidden() { hidden.value = getTags().join(','); }

  function addTag(name) {
    name = name.trim().replace(/,/g, '');
    if (!name || getTags().length >= 10) return;
    if (getTags().some(function(t){ return t.toLowerCase() === name.toLowerCase(); })) return;
    var chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.dataset.name = name;
    chip.innerHTML = '#' + name.replace(/</g,'&lt;') +
      ' <button type="button" onclick="removeTag(this.parentElement)" style="background:none;border:none;color:inherit;cursor:pointer;font-size:14px;padding:0 2px;opacity:.7">&times;</button>';
    container.insertBefore(chip, input);
    input.value = '';
    syncHidden();
    closeSuggest();
  }

  window.removeTag = function(chip) { chip.remove(); syncHidden(); };

  function closeSuggest() { suggest.style.display = 'none'; suggest.innerHTML = ''; activeIdx = -1; }

  function showSuggestions(tags) {
    if (!tags.length) { closeSuggest(); return; }
    suggest.innerHTML = '';
    suggest.style.display = 'block';
    activeIdx = -1;
    tags.forEach(function(t) {
      var div = document.createElement('div');
      div.className = 'tag-suggest-item';
      div.textContent = '#' + t.name;
      div.addEventListener('mousedown', function(e) { e.preventDefault(); addTag(t.name); });
      suggest.appendChild(div);
    });
  }

  input.addEventListener('input', function() {
    var q = this.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 1) { closeSuggest(); return; }
    debounceTimer = setTimeout(function() {
      fetch('/api/tags/search?q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data){ showSuggestions(data); })
        .catch(function(){ closeSuggest(); });
    }, 200);
  });

  input.addEventListener('keydown', function(e) {
    var items = suggest.querySelectorAll('.tag-suggest-item');
    if (e.key === 'ArrowDown' && items.length) {
      e.preventDefault();
      activeIdx = Math.min(activeIdx + 1, items.length - 1);
      items.forEach(function(it,i){ it.classList.toggle('active', i === activeIdx); });
    } else if (e.key === 'ArrowUp' && items.length) {
      e.preventDefault();
      activeIdx = Math.max(activeIdx - 1, 0);
      items.forEach(function(it,i){ it.classList.toggle('active', i === activeIdx); });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeIdx >= 0 && items[activeIdx]) {
        addTag(items[activeIdx].textContent.replace('#',''));
      } else if (this.value.trim()) {
        addTag(this.value);
      }
    } else if (e.key === 'Backspace' && !this.value) {
      var chips = container.querySelectorAll('.tag-chip');
      if (chips.length) chips[chips.length - 1].remove();
      syncHidden();
    } else if (e.key === ',' || e.key === 'Tab') {
      if (this.value.trim()) {
        e.preventDefault();
        addTag(this.value);
      }
    }
  });

  input.addEventListener('blur', function() { setTimeout(closeSuggest, 200); });
})();
</script>

<script>
// CKEditor + Autosave + Media Picker (enhanced)
(function(){
  const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
  const articleId = <?= $isEdit ? json_encode((string)$article['id']) : 'null' ?>;

  const form = document.getElementById('articleForm');
  const textarea = document.getElementById('content');
  const badge = document.getElementById('autosaveBadge');
  const clearBtn = document.getElementById('clearDraftBtn');

  const draftKey = `nt_article_draft_${isEdit ? 'edit_' + articleId : 'new'}`;
  const dirtyKey = draftKey + '_dirty';

  let editor = null;
  let isDirty = false;
  let saveTimer = null;

  function setBadge(text) {
    if (badge) badge.textContent = text;
  }

  function markDirty() {
    isDirty = true;
    localStorage.setItem(dirtyKey, '1');
    setBadge('Autosave: changes detected');
  }

  function buildDraftPayload() {
    return {
      ts: Date.now(),
      title: document.getElementById('title')?.value || '',
      category_id: document.getElementById('category_id')?.value || '',
      status: document.getElementById('status')?.value || 'draft',
      author_name: document.getElementById('author_name')?.value || '',
      featured_image: document.getElementById('featured_image')?.value || '',
      excerpt: document.getElementById('excerpt')?.value || '',
      content: editor ? editor.getData() : (textarea?.value || ''),
      tags: document.getElementById('tagsHidden')?.value || ''
    };
  }

  function saveDraft() {
    if (!isDirty) return;
    const payload = buildDraftPayload();
    try {
      localStorage.setItem(draftKey, JSON.stringify(payload));
      isDirty = false;
      localStorage.removeItem(dirtyKey);
      setBadge('Autosave: saved at ' + new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}));
    } catch (e) {
      setBadge('Autosave: failed (storage full?)');
    }
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveDraft, 800);
  }

  function restoreDraft() {
    const raw = localStorage.getItem(draftKey);
    if (!raw) return;
    let payload;
    try { payload = JSON.parse(raw); } catch { return; }
    if (!payload?.content) return;

    if (confirm('Restore autosaved draft?')) {
      document.getElementById('title').value = payload.title || '';
      document.getElementById('category_id').value = payload.category_id || '';
      document.getElementById('status').value = payload.status || 'draft';
      document.getElementById('author_name').value = payload.author_name || '';
      document.getElementById('featured_image').value = payload.featured_image || '';
      document.getElementById('excerpt').value = payload.excerpt || '';
      if (editor) editor.setData(payload.content);
      else textarea.value = payload.content;
      // Restore tags from draft
      if (payload.tags) {
        document.getElementById('tagsHidden').value = payload.tags;
        var container = document.getElementById('tagContainer');
        var tagInput = document.getElementById('tagInput');
        // Clear existing chips
        container.querySelectorAll('.tag-chip').forEach(function(c){ c.remove(); });
        // Recreate chips from saved tags
        payload.tags.split(',').forEach(function(name) {
          name = name.trim();
          if (!name) return;
          var chip = document.createElement('span');
          chip.className = 'tag-chip';
          chip.dataset.name = name;
          chip.innerHTML = '#' + name.replace(/</g,'&lt;') +
            ' <button type="button" onclick="removeTag(this.parentElement)" style="background:none;border:none;color:inherit;cursor:pointer;font-size:14px;padding:0 2px;opacity:.7">&times;</button>';
          container.insertBefore(chip, tagInput);
        });
      }
      setBadge('Draft restored');
    }
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (confirm('Clear autosaved draft?')) {
        localStorage.removeItem(draftKey);
        localStorage.removeItem(dirtyKey);
        setBadge('Autosave: cleared');
      }
    });
  }

  // CKEditor with custom upload adapter integration
  ClassicEditor
    .create(textarea, {
      extraPlugins: [NTUploadAdapterPlugin],
      toolbar: {
        items: [
          'heading', '|',
          'bold', 'italic', 'underline', 'strikethrough', 'link', '|',
          'bulletedList', 'numberedList', 'blockQuote', 'code', 'codeBlock', '|',
          'insertTable', 'mediaEmbed', 'imageUpload', '|',
          'undo', 'redo'
        ],
        shouldNotGroupWhenFull: true
      },
      image: {
        toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side'],
        upload: {
          types: ['png', 'jpeg', 'jpg', 'gif', 'webp']
        }
      },
      table: {
        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
      }
    })
    .then(ed => {
      editor = ed;
      restoreDraft();

      editor.model.document.on('change:data', () => {
        markDirty();
        scheduleSave();
      });

      // Mark dirty on form fields (including tags hidden input)
      ['title', 'category_id', 'status', 'author_name', 'featured_image', 'excerpt', 'tagsHidden'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', markDirty);
          el.addEventListener('change', markDirty);
        }
      });

      setBadge('Editor ready - autosave active');
    })
    .catch(err => {
      console.error(err);
      setBadge('Editor failed to load');
    });

  // Before unload warning
  window.addEventListener('beforeunload', e => {
    if (localStorage.getItem(dirtyKey) === '1') {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // Submit handler - sync editor data
  if (form) {
    form.addEventListener('submit', () => {
      if (editor) textarea.value = editor.getData();
      saveDraft();
    });
  }

  // ═══ INSERT MEDIA — iframe picker in insert mode ═══
  const backdrop  = document.getElementById('mediaModalBackdrop');
  const openBtn   = document.getElementById('insertMediaBtn');
  const closeBtn  = document.getElementById('closeMediaModal');
  const pickerIframe = document.getElementById('mediaPickerIframe');

  function openModal() {
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
    // Open picker in 'insert' mode so it shows alt/caption/size/align controls
    pickerIframe.src = '/admin/media/picker?mode=insert&field=article_content';
  }

  function closeModal() {
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
    pickerIframe.src = '';  // unload to free memory
  }

  openBtn?.addEventListener('click', openModal);
  closeBtn?.addEventListener('click', closeModal);
  backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && backdrop?.classList.contains('active')) closeModal(); });

  // ── Receive the picked image from the picker iframe ──────────────
  window.addEventListener('message', e => {
    if (!e.data) return;
    if (e.origin && e.origin !== window.location.origin) return;

    // picker.php posts { type:'media_pick', url, alt, caption, size, align, mode:'insert' }
    if (e.data.type === 'media_pick' && e.data.url) {
      const url     = e.data.url     || '';
      const alt     = e.data.alt     || e.data.title || '';
      const caption = e.data.caption || '';
      const size    = e.data.size    || 'full';
      const align   = e.data.align   || 'center';
      const width   = e.data.width   || '100%';

      if (e.data.mode === 'insert' || e.data.field === 'article_content') {
        // ── Primary path: inject via CKEditor insert helper ──────
        if (editor && typeof ntInsertImageIntoEditor === 'function') {
          ntInsertImageIntoEditor(editor, url, alt, size, align, caption, width);
        } else if (editor) {
          // Fallback: raw HTML fragment
          const imgHtml = `<figure class="image"><img src="${url}" alt="${alt.replace(/"/g,'&quot;')}"></figure>`;
          const frag = editor.data.processor.toView(imgHtml);
          editor.model.insertContent(editor.data.toModel(frag));
        } else if (textarea) {
          // No CKEditor: append raw HTML to textarea
          const cls   = `article-image article-image-${size}${align !== 'center' ? ` article-image-${align}` : ''}`;
          const figEl = `<figure class="${cls}"><img src="${url}" alt="${alt}" loading="lazy">${caption ? `<figcaption>${caption}</figcaption>` : ''}</figure>`;
          textarea.value += `\n\n${figEl}\n`;
        }
        closeModal();
      }
    }

    // picker.php posts close signal
    if (e.data.type === 'media_picker_close') {
      closeModal();
    }

    // Featured image picker (separate flow handled below)
  });

  // ═══ FEATURED IMAGE PICKER MODAL ═══
  var fpOverlay = null, fpGrid = null, fpSearch = null;

  function createFeaturedPicker() {
    if (fpOverlay) return;

    fpOverlay = document.createElement('div');
    fpOverlay.className = 'nt-picker-overlay';
    fpOverlay.style.zIndex = '10998';

    var modal = document.createElement('div');
    modal.className = 'nt-picker-modal';

    modal.innerHTML =
      '<div class="nt-picker-toolbar">'
      + '<span class="nt-picker-toolbar-title">Choose Featured Image</span>'
      + '<input id="fpSearch" placeholder="Search images…">'
      + '<button id="fpClose" class="btn light sm">Cancel</button>'
      + '</div>'
      + '<div class="nt-picker-grid-wrap"><div id="fpGrid" class="nt-picker-grid"></div></div>';

    fpOverlay.appendChild(modal);
    document.body.appendChild(fpOverlay);

    fpGrid   = document.getElementById('fpGrid');
    fpSearch = document.getElementById('fpSearch');
    document.getElementById('fpClose').onclick = closeFeaturedPicker;
    fpOverlay.addEventListener('click', function(e) { if (e.target === fpOverlay) closeFeaturedPicker(); });
    let debT;
    fpSearch.addEventListener('input', () => { clearTimeout(debT); debT = setTimeout(loadFPItems, 300); });
  }

  window.openFeaturedPicker = function() {
    createFeaturedPicker();
    fpOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    fpSearch.value = '';
    fpSearch.focus();
    loadFPItems();
  };

  function closeFeaturedPicker() {
    if (!fpOverlay) return;
    fpOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  async function loadFPItems() {
    if (!fpGrid) return;
    fpGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#888">Loading…</div>';
    var q = encodeURIComponent(fpSearch?.value || '');
    try {
      var res = await fetch('/admin/media/picker?q=' + q, { headers: { 'X-Requested-With': 'fetch' } });
      var data = await res.json();
      if (!data.ok || !data.items || !data.items.length) {
        fpGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#888">No images found.</div>';
        return;
      }
      var html = '';
      data.items.forEach(function(m) {
        var thumb = (m.thumbnail_url || m.public_url || '').replace(/"/g, '&quot;');
        var url = (m.public_url || '').replace(/"/g, '&quot;');
        var title = (m.title || m.original_name || '').replace(/</g, '&lt;').substring(0, 30);
        html += '<div class="nt-picker-item fp-item" data-url="' + url + '">'
              + '<img src="' + thumb + '" alt="" loading="lazy">'
              + '<div class="nt-picker-item-label">' + title + '</div>'
              + '</div>';
      });
      fpGrid.innerHTML = html;
      fpGrid.querySelectorAll('.fp-item').forEach(function(el) {
        el.addEventListener('click', function() {
          var url = this.dataset.url;
          document.getElementById('featured_image').value = url;
          var prev = document.getElementById('featuredPreview');
          if (prev) { prev.src = url; prev.style.display = 'block'; }
          closeFeaturedPicker();
        });
      });
    } catch(e) {
      fpGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#888">Failed to load.</div>';
    }
  }

  document.addEventListener('keydown', e => { if (e.key === 'Escape' && fpModal?.style.display === 'flex') closeFeaturedPicker(); });
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
<?php
$pageTitle = $pageTitle ?? 'Compose Newsletter';
$activeNav = $activeNav ?? 'newsletter';
ob_start();
?>

<div style="max-width:800px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
      <h1 style="margin:0">Compose Newsletter</h1>
      <p class="muted" style="margin:4px 0 0">Sending to <?= number_format($activeCount ?? 0) ?> active subscribers</p>
    </div>
    <a href="/admin/newsletter" class="btn sm" style="background:#eee;color:#333">&larr; Back</a>
  </div>

  <!-- Recommendations -->
  <div class="card" style="padding:14px;margin-bottom:20px;background:#fffbeb;border:1px solid #f59e0b30">
    <strong style="font-size:13px;color:#92400e">&#128161; Tips for better newsletters</strong>
    <ul style="margin:8px 0 0;padding-left:20px;font-size:13px;color:#78350f;line-height:1.8">
      <li>Pick 3-5 of your best articles &mdash; too many overwhelms readers</li>
      <li>Write a personal intro &mdash; newsletters with a human touch get 2x more opens</li>
      <li>Use a clear, curiosity-driven subject line (e.g. &quot;The story behind&hellip;&quot;)</li>
      <li>Send on Tuesday-Thursday mornings for best engagement</li>
      <li>Always preview before sending &mdash; check images, links, and spelling</li>
    </ul>
  </div>

  <form method="POST" action="/admin/newsletter/preview" id="composeForm">
    <input type="hidden" name="_csrf" value="<?= h($csrf ?? '') ?>">
    <input type="hidden" name="draft_id" value="<?= h(($draft['id'] ?? '')) ?>">

    <div class="card" style="padding:20px;margin-bottom:16px">
      <!-- Subject -->
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#666;margin-bottom:6px;font-weight:600">
          Subject Line <span style="color:#cc0000">*</span>
        </label>
        <input type="text" name="subject" class="form-control" required
               placeholder="e.g. This Week's Top Stories from <?= h(get_site_setting('site_title', 'Our Site')) ?>"
               style="width:100%;padding:10px 14px;font-size:15px;border:1px solid #ddd;border-radius:8px">
        <p class="muted" style="margin:4px 0 0;font-size:11px">Keep it under 60 characters for best inbox display.</p>
      </div>

      <!-- Intro text (CKEditor rich text) -->
      <div>
        <label style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#666;margin-bottom:6px;font-weight:600">
          Personal Introduction <span style="color:#999">(optional)</span>
        </label>
        <textarea name="intro" id="introEditor" rows="3" class="form-control"
                  placeholder="Write a brief personal note to your readers. This appears above the articles."
                  style="width:100%;padding:10px 14px;font-size:14px;border:1px solid #ddd;border-radius:8px;resize:vertical"></textarea>
        <p class="muted" style="margin:4px 0 0;font-size:11px">A personal note increases engagement. You can drag-and-drop images or use the toolbar to insert media.</p>
      </div>
    </div>

    <!-- Article selection -->
    <div class="card" style="padding:20px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <label style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#666;font-weight:600">
          Select Articles <span style="color:#cc0000">*</span>
          <span id="selectedCount" style="color:#cc0000;font-weight:700;margin-left:6px">(0 selected)</span>
        </label>
        <div style="display:flex;gap:8px;align-items:center">
          <input type="text" id="articleSearch" placeholder="Search articles..."
                 style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:200px">
          <button type="button" id="selectAllBtn" onclick="toggleAll()" class="btn sm" style="background:#eee;color:#333;font-size:11px;padding:4px 10px">
            Select All
          </button>
        </div>
      </div>

      <?php if (empty($articles)): ?>
        <p class="muted" style="text-align:center;padding:20px">No published articles yet.</p>
      <?php else: ?>
        <div id="articleList" style="max-height:500px;overflow-y:auto;border:1px solid #eee;border-radius:8px">
          <?php foreach ($articles as $i => $a): ?>
            <label class="article-item" data-title="<?= strtolower(h($a['title'] ?? '')) ?>" data-category="<?= strtolower(h($a['category'] ?? '')) ?>"
                   style="display:flex;gap:12px;padding:12px 14px;border-bottom:1px solid #f0f0f0;cursor:pointer;align-items:flex-start;transition:background .1s">
              <input type="checkbox" name="article_ids[]" value="<?= h($a['id']) ?>" style="margin-top:4px;flex-shrink:0;width:16px;height:16px;accent-color:#cc0000" onchange="updateCount()">
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:14px;line-height:1.3"><?= h($a['title'] ?? '') ?></div>
                <div style="font-size:12px;color:#888;margin-top:3px">
                  <?= h($a['category'] ?? '') ?> &middot;
                  <?= h($a['author'] ?? 'Staff') ?> &middot;
                  <?= $a['published_at'] ? date('M j', strtotime($a['published_at'])) : '---' ?>
                </div>
              </div>
              <?php if (!empty($a['featured_image'])): ?>
                <img src="<?= h($a['featured_image']) ?>" style="width:60px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0" alt="">
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Submit -->
    <div style="display:flex;gap:12px;margin-top:20px;justify-content:flex-end;flex-wrap:wrap">
      <a href="/admin/newsletter" class="btn" style="background:#eee;color:#333">Cancel</a>
      <button type="button" class="btn" id="saveDraftBtn"
              style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db"
              onclick="submitDraft()">
        &#128196; Save Draft
      </button>
      <button type="submit" class="btn" id="previewBtn">Preview Newsletter &rarr;</button>
    </div>
  </form>
</div>

<style>
  .article-item:hover { background: #f9f9f9; }
  .article-item:has(input:checked) { background: #fef2f2; }
  /* CKEditor container sizing */
  .ck-editor__editable { min-height: 100px; max-height: 300px; }
</style>

<?php if (!empty($draft)): ?>
<div style="background:#fffbeb;border:1px solid #f59e0b40;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:10px">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  <span>Editing draft &mdash; <strong><?= h($draft['subject']) ?></strong>. Last saved <?= time_ago($draft['updated_at'] ?? '') ?>.</span>
</div>
<?php endif; ?>

<!-- CKEditor 5 + Upload Adapter -->
<script src="/assets/ckeditor-adapter.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41.4.2/build/ckeditor.js"></script>

<script>
function updateCount() {
  const checked = document.querySelectorAll('#articleList input[type=checkbox]:checked').length;
  document.getElementById('selectedCount').textContent = '(' + checked + ' selected)';
}

function toggleAll() {
  const boxes = document.querySelectorAll('#articleList input[type=checkbox]');
  const allChecked = Array.from(boxes).every(b => b.checked);
  boxes.forEach(b => { b.checked = !allChecked; });
  updateCount();
  document.getElementById('selectAllBtn').textContent = allChecked ? 'Select All' : 'Deselect All';
}

// Search filter
document.getElementById('articleSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.article-item').forEach(item => {
    const title = item.dataset.title || '';
    const cat = item.dataset.category || '';
    item.style.display = (title.includes(q) || cat.includes(q)) ? '' : 'none';
  });
});

// CKEditor on intro field
(function() {
  var introEl = document.getElementById('introEditor');
  if (introEl && typeof ClassicEditor !== 'undefined') {
    ClassicEditor.create(introEl, {
      extraPlugins: [NTUploadAdapterPlugin],
      toolbar: {
        items: [
          'bold', 'italic', 'link', '|',
          'imageUpload', 'blockQuote', '|',
          'undo', 'redo'
        ]
      },
      image: {
        toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side'],
        upload: { types: ['jpeg', 'png', 'gif', 'webp'] }
      },
      placeholder: 'Write a brief personal note to your readers. You can include images too.'
    })
    .then(function(editor) {
      window._ntNewsletterEditor = editor;
      // Sync CKEditor content to hidden textarea on form submit
      var form = document.getElementById('composeForm');
      if (form) {
        form.addEventListener('submit', function() {
          introEl.value = editor.getData();
        });
      }
    })
    .catch(function(err) {
      console.warn('CKEditor init failed for newsletter intro:', err);
    });
  }
})();
</script>

<script>
// Submit form to saveDraft endpoint
function submitDraft() {
  var form = document.getElementById('composeForm');
  if (!form) return;
  var orig = form.action;
  form.action = '/admin/newsletter/draft';
  // Sync CKEditor if active
  if (window._ntNewsletterEditor) {
    document.getElementById('introEditor').value = window._ntNewsletterEditor.getData();
  }
  form.submit();
  form.action = orig;
}

<?php if (!empty($draft) && !empty($draft['article_ids'])): ?>
// Pre-select draft article IDs
(function() {
  var draftIds = <?= json_encode(json_decode($draft['article_ids'], true) ?: []) ?>;
  document.querySelectorAll('#articleList input[type=checkbox]').forEach(function(cb) {
    if (draftIds.indexOf(cb.value) !== -1) {
      cb.checked = true;
    }
  });
  updateCount();
})();
<?php endif; ?>
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
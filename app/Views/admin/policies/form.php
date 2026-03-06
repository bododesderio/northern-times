<?php
declare(strict_types=1);

/** @var array|null $page    null = create, array = edit */
/** @var array      $errors */
/** @var string     $csrf */
/** @var array|null $input   repopulated on validation failure */

$isEdit    = !empty($page);
$pageTitle = $isEdit ? 'Edit Policy Page' : 'New Policy Page';
$activeNav = 'policies';
$input     = $input ?? [];
$errors    = $errors ?? [];

// Values: prefer $input (validation failure) → $page (edit) → default
$val = function(string $key, mixed $default = '') use ($input, $page, $isEdit): mixed {
    if (isset($input[$key])) return $input[$key];
    if ($isEdit && isset($page[$key])) return $page[$key];
    return $default;
};

ob_start();
?>

<div style="max-width:900px">

<div class="page-header">
  <div>
    <h1><?= $isEdit ? 'Edit: ' . h($page['title']) : 'New Policy Page' ?></h1>
    <div class="sub">
      <?= $isEdit
        ? '<a href="/admin/policies">← Back to Policy Pages</a>'
        : 'Create a new editorial, legal, or policy page.' ?>
    </div>
  </div>
  <?php if ($isEdit && $page['is_published']): ?>
    <a class="btn light" href="/policy/<?= h($page['slug']) ?>" target="_blank" rel="noopener">
      View Live ↗
    </a>
  <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
  <div class="flash bad">
    <?php foreach ($errors as $e): ?>
      <div><?= h($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="POST"
      action="<?= $isEdit ? '/admin/policies/' . h($page['id']) : '/admin/policies' ?>"
      id="policyForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <!-- ── Title + Slug ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-section-label">Identity</div>

    <div class="form-group">
      <label class="form-label">Title <span class="required">*</span></label>
      <input name="title"
             id="titleInput"
             class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
             value="<?= h((string)$val('title')) ?>"
             placeholder="e.g. Privacy Policy"
             required
             autofocus>
      <?php if (isset($errors['title'])): ?>
        <div class="field-error"><?= h($errors['title']) ?></div>
      <?php endif; ?>
    </div>

    <div class="form-group" style="margin-bottom:0">
      <label class="form-label">
        Slug
        <span style="font-weight:400;color:var(--muted);margin-left:6px;font-size:12px">
          — public URL: <code>/policy/<span id="slugPreview"><?= h((string)$val('slug', $isEdit ? $page['slug'] : '')) ?></span></code>
        </span>
      </label>
      <input name="slug"
             id="slugInput"
             class="form-control"
             value="<?= h((string)$val('slug', $isEdit ? $page['slug'] : '')) ?>"
             placeholder="auto-generated from title"
             style="font-family:monospace">
      <div class="form-hint">Leave blank to auto-generate from title. Only letters, numbers, and hyphens.</div>
    </div>
  </div>

  <!-- ── Content ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-section-label">Content</div>

    <div class="form-group" style="margin-bottom:0">
      <label class="form-label">Page Content</label>
      <textarea name="content"
                id="contentEditor"
                class="form-control"
                rows="20"
                style="font-family:var(--serif);font-size:15px;line-height:1.6"><?= h((string)$val('content')) ?></textarea>
      <div class="form-hint">
        CKEditor will load automatically. Write in the editor — HTML is preserved.
      </div>
    </div>
  </div>

  <!-- ── Options ── -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-section-label">Options</div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Sort Order</label>
        <input name="sort_order"
               type="number"
               min="0"
               class="form-control"
               value="<?= (int)$val('sort_order', 0) ?>">
        <div class="form-hint">Lower = appears first in footer.</div>
      </div>

      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Show in Footer</label>
        <label class="toggle-label">
          <input type="hidden" name="show_in_footer" value="0">
          <input type="checkbox" name="show_in_footer" value="1"
                 <?= $val('show_in_footer', true) ? 'checked' : '' ?>>
          <span class="toggle-track"></span>
          <span class="toggle-text">Visible in site footer</span>
        </label>
      </div>

      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Status</label>
        <label class="toggle-label">
          <input type="hidden" name="is_published" value="0">
          <input type="checkbox" name="is_published" value="1"
                 <?= $val('is_published', false) ? 'checked' : '' ?>>
          <span class="toggle-track"></span>
          <span class="toggle-text">Published</span>
        </label>
        <div class="form-hint">Unpublished pages return 404 to visitors.</div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn" type="submit">
      <?= $isEdit ? 'Save Changes' : 'Create Page' ?>
    </button>
    <a class="btn light" href="/admin/policies">Cancel</a>
    <?php if ($isEdit): ?>
      <div style="flex:1"></div>
      <form method="POST" action="/admin/policies/<?= h($page['id']) ?>/toggle" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button class="btn light" type="submit">
          <?= $page['is_published'] ? 'Unpublish' : 'Publish' ?>
        </button>
      </form>
    <?php endif; ?>
  </div>

</form>
</div>

<!-- CKEditor Upload Adapter -->
<script src="/assets/ckeditor-adapter.js"></script>

<!-- CKEditor 5 CDN -->
<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41.4.2/build/ckeditor.js"></script>
<script>
(function () {
  'use strict';

  // ── CKEditor init ─────────────────────────────────────────────
  const editorEl = document.getElementById('contentEditor');
  if (editorEl && typeof ClassicEditor !== 'undefined') {
    ClassicEditor.create(editorEl, {
      extraPlugins: [NTUploadAdapterPlugin],
      toolbar: {
        items: [
          'heading', '|',
          'bold', 'italic', 'underline', 'strikethrough', '|',
          'link', 'blockQuote', '|',
          'bulletedList', 'numberedList', '|',
          'insertTable', 'horizontalLine', 'imageUpload', '|',
          'undo', 'redo', '|',
          'sourceEditing'
        ]
      },
      image: {
        toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side'],
        upload: {
          types: ['png', 'jpeg', 'jpg', 'gif', 'webp']
        }
      },
      heading: {
        options: [
          { model: 'paragraph', title: 'Paragraph' },
          { model: 'heading2', view: 'h2', title: 'Heading 2' },
          { model: 'heading3', view: 'h3', title: 'Heading 3' },
          { model: 'heading4', view: 'h4', title: 'Heading 4' },
        ]
      }
    }).catch(err => {
      console.warn('CKEditor failed to load, falling back to textarea.', err);
    });
  }

  // ── Auto-slug from title ──────────────────────────────────────
  const titleEl = document.getElementById('titleInput');
  const slugEl  = document.getElementById('slugInput');
  const slugPv  = document.getElementById('slugPreview');
  let slugManual = <?= json_encode($isEdit) ?>; // don't auto-generate on edit

  function makeSlug(str) {
    return str.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/[\s-]+/g, '-')
      .replace(/^-|-$/g, '');
  }

  if (titleEl && slugEl && !slugManual) {
    titleEl.addEventListener('input', function () {
      if (!slugManual) {
        const s = makeSlug(titleEl.value);
        slugEl.value = s;
        if (slugPv) slugPv.textContent = s || '…';
      }
    });
  }

  if (slugEl) {
    slugEl.addEventListener('input', function () {
      slugManual = true; // user took manual control
      const s = makeSlug(slugEl.value);
      if (slugPv) slugPv.textContent = s || '…';
    });

    // On blur, normalise the slug
    slugEl.addEventListener('blur', function () {
      slugEl.value = makeSlug(slugEl.value);
    });
  }

  // Update preview on load
  if (slugPv && slugEl.value) {
    slugPv.textContent = slugEl.value;
  }
})();
</script>

<style>
.card-section-label {
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
  color:var(--muted);margin-bottom:16px;padding-bottom:10px;
  border-bottom:1px solid var(--border)
}
.form-group { margin-bottom:16px }
.form-label { display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--ink) }
.form-control { width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--surface);color:var(--ink);box-sizing:border-box }
.form-control.is-invalid { border-color:#c00 }
.form-hint { margin-top:5px;font-size:12px;color:var(--muted) }
.field-error { margin-top:5px;font-size:12px;color:#c00;font-weight:600 }
.required { color:#c00 }
.toggle-label { display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 0 }
.toggle-track { width:36px;height:20px;border-radius:10px;background:var(--border);position:relative;flex-shrink:0;transition:background .15s }
.toggle-label input[type=checkbox]:checked ~ .toggle-track { background:var(--accent) }
.toggle-label input[type=checkbox] { position:absolute;opacity:0;width:0;height:0 }
.toggle-text { font-size:13px;color:var(--ink) }
/* CKEditor theme integration */
.ck.ck-editor__editable { min-height:300px;font-family:var(--serif,Georgia,serif);font-size:16px;line-height:1.7;color:var(--ink,#121212) }
.ck.ck-toolbar { border-radius:8px 8px 0 0 !important }
.ck.ck-editor__editable_type_classic { border-radius:0 0 8px 8px !important }
</style>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
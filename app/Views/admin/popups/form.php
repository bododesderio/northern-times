<?php
$pageTitle = ($mode === 'edit' ? 'Edit' : 'New') . ' Popup';
$activeNav = 'popups';
$isEdit    = $mode === 'edit';
$p         = $popup ?? [];
$csrf      = $csrf ?? '';
$flash     = \App\Services\Flash::get('error') ?? '';

$typeLabels  = \App\Models\Popup::TYPE_LABELS;
$styleLabels = \App\Models\Popup::STYLE_LABELS;

$slot = null;
ob_start();
?>

<style>
/* ── Style picker cards ──────────────────────────── */
.style-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.style-card {
  border: 2px solid var(--border, #e2e2e2); border-radius: 12px; padding: 0;
  cursor: pointer; transition: all .2s; overflow: hidden; background: var(--surface, #fff);
  position: relative;
}
.style-card:hover { border-color: var(--accent, #cc0000); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.style-card.active { border-color: var(--accent, #cc0000); box-shadow: 0 0 0 3px rgba(204,0,0,.15); }
.style-card.active::after {
  content: '✓'; position: absolute; top: 8px; right: 8px; width: 22px; height: 22px;
  background: var(--accent, #cc0000); color: #fff; border-radius: 50%; font-size: 12px;
  display: flex; align-items: center; justify-content: center; font-weight: 700;
}
.style-card-preview {
  height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden;
}
.style-card-label { padding: 8px 12px; font-size: 12px; font-weight: 700; text-align: center; border-top: 1px solid var(--border, #e2e2e2); }

/* Mini preview shapes inside style cards */
.mini-page { width: 90%; height: 90%; background: #fff; border-radius: 4px; position: relative; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.mini-page .mini-header { height: 10px; background: #ddd; margin: 6px 8px 0; border-radius: 2px; }
.mini-page .mini-line { height: 4px; background: #eee; margin: 4px 8px 0; border-radius: 2px; }
.mini-popup { position: absolute; border-radius: 3px; box-shadow: 0 2px 8px rgba(0,0,0,.2); }

/* ── Preview simulator ──────────────────────────── */
.preview-stage {
  background: #e8e8e8; border-radius: 8px; position: relative; overflow: hidden;
  min-height: 420px; display: flex; flex-direction: column;
}
.preview-website {
  flex: 1; background: #fff; margin: 8px; border-radius: 4px; padding: 12px; position: relative;
  display: flex; flex-direction: column; gap: 6px; overflow: hidden;
}
.preview-website .fake-header { height: 12px; background: #eee; border-radius: 3px; width: 40%; }
.preview-website .fake-line { height: 6px; background: #f3f3f3; border-radius: 3px; }
.preview-website .fake-line.w80 { width: 80%; }
.preview-website .fake-line.w60 { width: 60%; }
.preview-website .fake-line.w90 { width: 90%; }
.preview-website .fake-img { height: 50px; background: #f0f0f0; border-radius: 4px; }

/* Preview popup overlay & popup itself */
.pv-overlay {
  position: absolute; inset: 0; z-index: 5; display: flex; align-items: center; justify-content: center;
  transition: background .3s;
}
.pv-popup { position: relative; z-index: 6; transition: all .3s; }
.pv-close {
  position: absolute; top: 4px; right: 6px; width: 18px; height: 18px; border-radius: 50%;
  background: rgba(0,0,0,.4); color: #fff; border: none; font-size: 11px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; line-height: 1; z-index: 10;
}

/* ── Animations ──────────────────────────── */
@keyframes pvFadeIn    { from { opacity: 0; } to { opacity: 1; } }
@keyframes pvSlideUp   { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pvSlideDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pvSlideIn   { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes pvSlideInL  { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes pvScaleIn   { from { opacity: 0; transform: scale(.85); } to { opacity: 1; transform: scale(1); } }

.pv-anim-card_modal    { animation: pvScaleIn .35s ease-out; }
.pv-anim-minimal_bar   { animation: pvSlideDown .3s ease-out; }
.pv-anim-split_image   { animation: pvScaleIn .35s ease-out; }
.pv-anim-fullscreen    { animation: pvFadeIn .4s ease-out; }
.pv-anim-slide_in      { animation: pvSlideIn .35s ease-out; }
.pv-anim-floating      { animation: pvSlideUp .35s ease-out; }

/* ── Image field with media picker ──────────────── */
.img-field-wrap { display: flex; gap: 8px; align-items: center; }
.img-field-wrap input[type=text] { flex: 1; }
.img-field-btn {
  padding: 10px 14px; border: 1px solid #e2e2e2; border-radius: 10px; background: var(--surface, #fff);
  font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all .15s;
  display: flex; align-items: center; gap: 6px;
}
.img-field-btn:hover { border-color: var(--accent, #cc0000); color: var(--accent, #cc0000); }
.img-field-btn svg { flex-shrink: 0; }
.img-thumb-preview {
  margin-top: 8px; display: none; border-radius: 8px; overflow: hidden; border: 1px solid #e2e2e2;
  max-height: 120px;
}
.img-thumb-preview img { display: block; max-width: 100%; max-height: 120px; object-fit: cover; }

/* Replay button */
.replay-btn {
  position: absolute; top: 8px; left: 8px; z-index: 20; padding: 5px 10px; border-radius: 8px;
  background: rgba(0,0,0,.6); color: #fff; font-size: 11px; font-weight: 600; border: none;
  cursor: pointer; display: flex; align-items: center; gap: 4px; transition: background .15s;
}
.replay-btn:hover { background: rgba(0,0,0,.8); }
</style>

<?php if ($flash): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fff3cd;color:#856404"><?= h($flash) ?></div>
<?php endif; ?>

<form method="POST" action="<?= $isEdit ? '/admin/popups/' . h($p['id']) . '/update' : '/admin/popups/store' ?>" id="popupForm">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <div style="display:grid;grid-template-columns:1fr 420px;gap:24px;align-items:start">

    <!-- LEFT: Form fields -->
    <div style="display:flex;flex-direction:column;gap:20px">

      <!-- Identity -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Identity</h3>
        <div style="display:grid;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Popup Name *</label>
            <input name="name" value="<?= h($p['name'] ?? '') ?>" required placeholder="e.g. Newsletter Signup Modal"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:15px" onchange="rebuildPreview()">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Popup Type</label>
            <select name="popup_type" id="popupType" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px" onchange="rebuildPreview();toggleTypeFields()">
              <?php foreach ($typeLabels as $k => $v): ?>
                <option value="<?= h($k) ?>" <?= ($p['popup_type'] ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Banner Style Picker -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 6px;font-size:16px;font-weight:700">Choose a Banner Style</h3>
        <p style="margin:0 0 16px;font-size:13px;color:var(--muted,#888)">Click a style to see its animated preview on the right.</p>
        <input type="hidden" name="banner_style" id="bannerStyle" value="<?= h($p['banner_style'] ?? 'card_modal') ?>">
        <div class="style-grid">
          <!-- Card Modal -->
          <div class="style-card <?= ($p['banner_style'] ?? 'card_modal') === 'card_modal' ? 'active' : '' ?>" data-style="card_modal" onclick="selectStyle('card_modal')">
            <div class="style-card-preview">
              <div class="mini-page">
                <div class="mini-header"></div>
                <div class="mini-line w80"></div>
                <div class="mini-line w60"></div>
                <div class="mini-popup" style="width:50%;height:45%;background:#fff;top:50%;left:50%;transform:translate(-50%,-50%);border:1px solid #ccc;padding:4px;text-align:center">
                  <div style="height:3px;background:#cc0000;width:60%;margin:0 auto 3px;border-radius:1px"></div>
                  <div style="height:2px;background:#ddd;width:80%;margin:0 auto 2px;border-radius:1px"></div>
                  <div style="height:6px;background:#cc0000;width:40%;margin:2px auto 0;border-radius:2px"></div>
                </div>
              </div>
            </div>
            <div class="style-card-label">Card Modal</div>
          </div>
          <!-- Minimal Bar -->
          <div class="style-card <?= ($p['banner_style'] ?? '') === 'minimal_bar' ? 'active' : '' ?>" data-style="minimal_bar" onclick="selectStyle('minimal_bar')">
            <div class="style-card-preview">
              <div class="mini-page">
                <div class="mini-popup" style="width:100%;height:14px;background:#333;top:0;left:0;display:flex;align-items:center;justify-content:center">
                  <div style="height:3px;background:#fff;width:40%;border-radius:1px"></div>
                </div>
                <div class="mini-header" style="margin-top:20px"></div>
                <div class="mini-line w80"></div>
              </div>
            </div>
            <div class="style-card-label">Minimal Bar</div>
          </div>
          <!-- Split Image -->
          <div class="style-card <?= ($p['banner_style'] ?? '') === 'split_image' ? 'active' : '' ?>" data-style="split_image" onclick="selectStyle('split_image')">
            <div class="style-card-preview">
              <div class="mini-page">
                <div class="mini-header"></div>
                <div class="mini-line w60"></div>
                <div class="mini-popup" style="width:70%;height:50%;background:#fff;top:50%;left:50%;transform:translate(-50%,-50%);border:1px solid #ccc;display:flex">
                  <div style="width:50%;background:linear-gradient(135deg,#ddd,#bbb)"></div>
                  <div style="width:50%;padding:3px;display:flex;flex-direction:column;justify-content:center;align-items:center">
                    <div style="height:2px;background:#333;width:70%;border-radius:1px;margin-bottom:2px"></div>
                    <div style="height:5px;background:#cc0000;width:50%;border-radius:2px;margin-top:2px"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="style-card-label">Split Image</div>
          </div>
          <!-- Fullscreen -->
          <div class="style-card <?= ($p['banner_style'] ?? '') === 'fullscreen' ? 'active' : '' ?>" data-style="fullscreen" onclick="selectStyle('fullscreen')">
            <div class="style-card-preview">
              <div class="mini-page" style="background:linear-gradient(135deg,#555,#222)">
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
                  <div style="height:3px;background:#fff;width:40px;margin:0 auto 3px;border-radius:1px"></div>
                  <div style="height:2px;background:rgba(255,255,255,.5);width:50px;margin:0 auto 3px;border-radius:1px"></div>
                  <div style="height:6px;background:#cc0000;width:30px;margin:0 auto;border-radius:2px"></div>
                </div>
              </div>
            </div>
            <div class="style-card-label">Fullscreen</div>
          </div>
          <!-- Slide-in Panel -->
          <div class="style-card <?= ($p['banner_style'] ?? '') === 'slide_in' ? 'active' : '' ?>" data-style="slide_in" onclick="selectStyle('slide_in')">
            <div class="style-card-preview">
              <div class="mini-page">
                <div class="mini-header"></div>
                <div class="mini-line w80"></div>
                <div class="mini-line w60"></div>
                <div class="mini-popup" style="width:35%;height:55%;background:#fff;bottom:4px;right:4px;border:1px solid #ccc;border-radius:4px;padding:4px">
                  <div style="height:3px;background:#333;width:80%;border-radius:1px;margin-bottom:2px"></div>
                  <div style="height:2px;background:#ddd;width:90%;border-radius:1px;margin-bottom:3px"></div>
                  <div style="height:5px;background:#cc0000;width:60%;border-radius:2px"></div>
                </div>
              </div>
            </div>
            <div class="style-card-label">Slide-in Panel</div>
          </div>
          <!-- Floating Banner -->
          <div class="style-card <?= ($p['banner_style'] ?? '') === 'floating' ? 'active' : '' ?>" data-style="floating" onclick="selectStyle('floating')">
            <div class="style-card-preview">
              <div class="mini-page">
                <div class="mini-header"></div>
                <div class="mini-line w80"></div>
                <div class="mini-line w60"></div>
                <div class="mini-popup" style="width:55%;height:22%;background:#fff;bottom:6px;right:6px;border:1px solid #ccc;border-radius:4px;display:flex;align-items:center;gap:3px;padding:0 4px">
                  <div style="width:18px;height:18px;background:#eee;border-radius:3px;flex-shrink:0"></div>
                  <div style="flex:1">
                    <div style="height:2px;background:#333;width:80%;border-radius:1px;margin-bottom:2px"></div>
                    <div style="height:4px;background:#cc0000;width:50%;border-radius:1px"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="style-card-label">Floating Banner</div>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Content</h3>
        <div style="display:grid;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Title</label>
            <input name="title" id="pTitle" value="<?= h($p['title'] ?? '') ?>" placeholder="e.g. Stay Updated"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:15px" oninput="rebuildPreview()">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Body Text</label>
            <textarea name="body" id="pBody" rows="3" placeholder="e.g. Get the best stories delivered to your inbox."
                      style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:15px;resize:vertical" oninput="rebuildPreview()"><?= h($p['body'] ?? '') ?></textarea>
          </div>

          <!-- Image with media picker -->
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Image</label>
            <div class="img-field-wrap">
              <input type="text" name="image_url" id="pImage" value="<?= h($p['image_url'] ?? '') ?>" placeholder="Select or upload an image"
                     style="padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px" oninput="onImageChange()">
              <button type="button" class="img-field-btn" onclick="openMediaPicker()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
                Library
              </button>
              <button type="button" class="img-field-btn" onclick="triggerUpload()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Upload
              </button>
              <input type="file" id="imgUploadInput" accept="image/*" style="display:none" onchange="handleFileUpload(this)">
            </div>
            <div class="img-thumb-preview" id="imgThumbPreview">
              <img id="imgThumbImg" src="" alt="Preview">
              <button type="button" onclick="clearImage()" style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">×</button>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
              <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Button Text</label>
              <input name="button_text" id="pBtn" value="<?= h($p['button_text'] ?? 'Subscribe') ?>"
                     style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px" oninput="rebuildPreview()">
            </div>
            <div>
              <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Button URL</label>
              <input name="button_url" value="<?= h($p['button_url'] ?? '') ?>" placeholder="Optional — leave empty for email popups"
                     style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
              <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Secondary Button Text</label>
              <input name="secondary_btn_text" value="<?= h($p['secondary_btn_text'] ?? '') ?>" placeholder="No thanks"
                     style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
            <div>
              <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Secondary Button URL</label>
              <input name="secondary_btn_url" value="<?= h($p['secondary_btn_url'] ?? '') ?>"
                     style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
            </div>
          </div>
        </div>
      </div>

      <!-- Appearance -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Appearance</h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
          <?php foreach ([
            ['bg_color', 'Background', '#ffffff'],
            ['text_color', 'Text', '#1a1a1a'],
            ['btn_bg_color', 'Button BG', '#cc0000'],
            ['btn_text_color', 'Button Text', '#ffffff'],
          ] as [$field, $label, $default]): ?>
            <div>
              <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px"><?= $label ?></label>
              <div style="display:flex;align-items:center;gap:8px">
                <input type="color" name="<?= $field ?>" value="<?= h($p[$field] ?? $default) ?>"
                       style="width:40px;height:36px;border:1px solid #e2e2e2;border-radius:8px;cursor:pointer" onchange="syncColorText(this);rebuildPreview()">
                <input type="text" value="<?= h($p[$field] ?? $default) ?>" style="flex:1;padding:8px;border:1px solid #e2e2e2;border-radius:8px;font-size:12px;font-family:monospace"
                       onchange="this.previousElementSibling.value=this.value;rebuildPreview()">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Position</label>
            <select name="position" id="pPosition" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px" onchange="rebuildPreview()">
              <?php foreach (\App\Models\Popup::POSITIONS as $pos): ?>
                <option value="<?= h($pos) ?>" <?= ($p['position'] ?? 'center') === $pos ? 'selected' : '' ?>><?= ucfirst(str_replace('-', ' ', $pos)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Overlay Opacity</label>
            <input type="range" name="overlay_opacity" min="0" max="1" step="0.05" value="<?= h($p['overlay_opacity'] ?? '0.50') ?>"
                   style="width:100%" onchange="rebuildPreview()">
          </div>
        </div>
      </div>

      <!-- Behavior -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Behavior</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Trigger</label>
            <select name="trigger_type" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
              <?php foreach (\App\Models\Popup::TRIGGERS as $t): ?>
                <option value="<?= h($t) ?>" <?= ($p['trigger_type'] ?? 'page_load') === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Trigger Value</label>
            <input name="trigger_value" value="<?= h($p['trigger_value'] ?? '') ?>" placeholder="e.g. 50 for 50% scroll"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Frequency</label>
            <select name="frequency" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
              <?php foreach (\App\Models\Popup::FREQUENCIES as $f): ?>
                <option value="<?= h($f) ?>" <?= ($p['frequency'] ?? 'once_session') === $f ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $f)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-top:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Show Delay (sec)</label>
            <input type="number" name="show_delay" value="<?= (int)($p['show_delay'] ?? 0) ?>" min="0"
                   style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Close Delay (sec)</label>
            <input type="number" name="close_delay" value="<?= (int)($p['close_delay'] ?? 0) ?>" min="0"
                   style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Priority</label>
            <input type="number" name="priority" value="<?= (int)($p['priority'] ?? 10) ?>" min="1"
                   style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Freq. Days</label>
            <input type="number" name="frequency_days" value="<?= (int)($p['frequency_days'] ?? 0) ?>" min="0" placeholder="Custom days"
                   style="width:100%;padding:10px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
        </div>
      </div>

      <!-- Targeting -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Targeting</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Audience</label>
            <select name="target_audience" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
              <?php foreach (['all' => 'Everyone', 'first_time' => 'First-time Visitors', 'returning' => 'Returning Visitors', 'subscribers' => 'Subscribers Only', 'non_subscribers' => 'Non-Subscribers'] as $k => $v): ?>
                <option value="<?= h($k) ?>" <?= ($p['target_audience'] ?? 'all') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Device</label>
            <select name="target_device" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
              <?php foreach (['all' => 'All Devices', 'desktop' => 'Desktop Only', 'mobile' => 'Mobile Only'] as $k => $v): ?>
                <option value="<?= h($k) ?>" <?= ($p['target_device'] ?? 'all') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Pages (comma-separated)</label>
            <input name="target_pages" value="<?= h($p['target_pages'] ?? '') ?>" placeholder="/, /article/*, /category/*"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Categories</label>
            <input name="target_categories" value="<?= h($p['target_categories'] ?? '') ?>" placeholder="politics, business"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
        </div>
      </div>

      <!-- Schedule & Status -->
      <div style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2)">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Schedule & Status</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Status</label>
            <select name="status" style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
              <?php foreach (\App\Models\Popup::STATUSES as $s): ?>
                <option value="<?= h($s) ?>" <?= ($p['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Start Date</label>
            <input type="datetime-local" name="start_date"
                   value="<?= !empty($p['start_date']) ? date('Y-m-d\TH:i', strtotime($p['start_date'])) : '' ?>"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">End Date</label>
            <input type="datetime-local" name="end_date"
                   value="<?= !empty($p['end_date']) ? date('Y-m-d\TH:i', strtotime($p['end_date'])) : '' ?>"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
        </div>
      </div>

      <!-- Type-specific fields -->
      <div id="emailFields" style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2);display:none">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Newsletter Options</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="has_email_field" value="1" <?= !empty($p['has_email_field']) ? 'checked' : '' ?> onchange="rebuildPreview()">
              <span style="font-weight:600;font-size:14px">Include Email Field</span>
            </label>
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Subscriber Source Tag</label>
            <input name="subscriber_source" value="<?= h($p['subscriber_source'] ?? '') ?>" placeholder="e.g. popup_newsletter"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
        </div>
      </div>

      <div id="adFields" style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2);display:none">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Advertisement Options</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Sponsor Name</label>
            <input name="sponsor_name" value="<?= h($p['sponsor_name'] ?? '') ?>"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Click URL</label>
            <input name="click_url" value="<?= h($p['click_url'] ?? '') ?>"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="nofollow" value="1" <?= !empty($p['nofollow']) ? 'checked' : '' ?>>
              <span style="font-weight:600;font-size:14px">Add rel="nofollow"</span>
            </label>
          </div>
        </div>
      </div>

      <div id="promoFields" style="background:var(--surface,#fff);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e2e2);display:none">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700">Promotion Options</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Promo Code</label>
            <input name="promo_code" value="<?= h($p['promo_code'] ?? '') ?>"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
          <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Campaign Name</label>
            <input name="campaign_name" value="<?= h($p['campaign_name'] ?? '') ?>"
                   style="width:100%;padding:12px;border:1px solid #e2e2e2;border-radius:10px;font-size:14px">
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div style="display:flex;gap:12px">
        <button type="submit" class="btn" style="padding:14px 28px;font-size:15px"><?= $isEdit ? 'Update Popup' : 'Create Popup' ?></button>
        <a href="/admin/popups" class="btn light" style="padding:14px 28px;font-size:15px">Cancel</a>
      </div>
    </div>

    <!-- RIGHT: Live Preview (real-time postMessage iframe) -->
    <div style="position:sticky;top:20px">
      <div style="background:var(--surface,#fff);border-radius:16px;border:1px solid var(--border,#e2e2e2);overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border,#e2e2e2);display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:700;font-size:14px">Live Preview</span>
          <span id="previewStyleLabel" style="font-size:12px;color:var(--muted,#888)">Card Modal</span>
        </div>
        <!-- Real-time preview iframe — receives postMessage on every keystroke -->
        <div style="position:relative;height:320px;background:#f0f0f0;border-radius:0 0 16px 16px;overflow:hidden">
          <iframe
            id="popupPreviewFrame"
            src="/assets/popup-preview-frame.php"
            style="width:100%;height:100%;border:none;display:block"
            sandbox="allow-scripts allow-same-origin"
            title="Popup live preview"
          ></iframe>
          <button type="button" id="previewReplayBtn"
            style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.45);color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;backdrop-filter:blur(4px)">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1,4 1,10 7,10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            Replay
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- Media Picker iframe (hidden) -->
<div id="mediaPickerOverlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5)">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:900px;height:80vh;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #e2e2e2">
      <span style="font-weight:700">Select Image from Library</span>
      <button type="button" onclick="closeMediaPicker()" style="background:none;border:none;font-size:20px;cursor:pointer;padding:4px">×</button>
    </div>
    <iframe id="mediaPickerFrame" style="width:100%;height:calc(100% - 52px);border:none" src=""></iframe>
  </div>
</div>

<script>
var csrfToken = '<?= h($csrf) ?>';

/* ── Style picker ────────────────────────────── */
function selectStyle(style) {
  document.getElementById('bannerStyle').value = style;
  document.querySelectorAll('.style-card').forEach(function(c) {
    c.classList.toggle('active', c.dataset.style === style);
  });
  rebuildPreview();
}

function syncColorText(colorInput) {
  var next = colorInput.nextElementSibling;
  if (next) next.value = colorInput.value;
}

/* ── Gather form values ──────────────────────── */
function getVals() {
  var f = document.getElementById('popupForm');
  var hasEmail = f.querySelector('[name=has_email_field]');
  return {
    style:    document.getElementById('bannerStyle').value,
    title:    document.getElementById('pTitle').value || 'Stay Updated',
    body:     document.getElementById('pBody').value || 'Get the best stories in your inbox.',
    btn:      document.getElementById('pBtn').value || 'Subscribe',
    img:      document.getElementById('pImage').value,
    bg:       f.querySelector('[name=bg_color]').value,
    text:     f.querySelector('[name=text_color]').value,
    btnBg:    f.querySelector('[name=btn_bg_color]').value,
    btnText:  f.querySelector('[name=btn_text_color]').value,
    overlay:  f.querySelector('[name=overlay_opacity]').value,
    position: document.getElementById('pPosition').value,
    email:    hasEmail && hasEmail.checked,
  };
}

/* ── Build preview HTML per style ────────────── */
/* ── Build preview ─────────────────────────── */
var _previewFrame   = document.getElementById('popupPreviewFrame');
var _previewReady   = false;
var _pendingPreview = null;

/* When iframe signals it's ready, send any queued preview */
window.addEventListener('message', function(e) {
  if (!e.data) return;
  if (e.data.type === 'nt_preview_ready') {
    _previewReady = true;
    if (_pendingPreview) { sendPreview(_pendingPreview); _pendingPreview = null; }
  }
  /* Also handle media picker messages */
  var isMediaPick = (e.data.type === 'media_pick' || e.data.type === 'media-selected');
  if (isMediaPick && e.data.url) {
    document.getElementById('pImage').value = e.data.url;
    onImageChange();
    closeMediaPicker();
  }
  if (e.data.type === 'media_picker_close') { closeMediaPicker(); }
});

function sendPreview(v) {
  if (!_previewFrame || !_previewFrame.contentWindow) return;
  _previewFrame.contentWindow.postMessage({
    type:   'nt_popup_preview',
    values: v
  }, window.location.origin);
}

function rebuildPreview() {
  var v = getVals();
  var labels = {
    card_modal:'Card Modal', minimal_bar:'Minimal Bar', split_image:'Split Image',
    fullscreen:'Fullscreen',  slide_in:'Slide-in Panel', floating:'Floating Banner',
    top_bar:'Top Bar'
  };
  document.getElementById('previewStyleLabel').textContent = labels[v.style] || v.style;

  /* Normalise for the iframe: camelCase keys */
  var msg = {
    style:          v.style,
    title:          v.title,
    body:           v.body,
    btn:            v.btn,
    bg:             v.bg,
    text:           v.text,
    btnBg:          v.btnBg,
    btnText:        v.btnText,
    img:            v.img,
    overlayOpacity: v.overlay,
    position:       v.position,
    email:          v.email
  };

  if (_previewReady) {
    sendPreview(msg);
  } else {
    _pendingPreview = msg;
  }
}

function replayAnimation() {
  /* Reload the iframe to replay entry animation, then send current state */
  _previewReady = false;
  _pendingPreview = null;
  _previewFrame.src = _previewFrame.src;
  /* rebuildPreview() will be triggered by the next nt_preview_ready message */
}

/* ── Image handling ──────────────────────────── */
function onImageChange() {
  var url = document.getElementById('pImage').value;
  var thumb = document.getElementById('imgThumbPreview');
  var img   = document.getElementById('imgThumbImg');
  if (url) {
    thumb.style.display = 'block';
    thumb.style.position = 'relative';
    img.src = url;
  } else {
    thumb.style.display = 'none';
  }
  rebuildPreview();
}

function clearImage() {
  document.getElementById('pImage').value = '';
  document.getElementById('imgThumbPreview').style.display = 'none';
  rebuildPreview();
}

function triggerUpload() {
  document.getElementById('imgUploadInput').click();
}

function handleFileUpload(input) {
  if (!input.files || !input.files[0]) return;
  var file = input.files[0];
  var fd = new FormData();
  fd.append('file', file);
  fd.append('folder', 'Popups');
  fd.append('_csrf', csrfToken);

  fetch('/admin/media/upload', {
    method: 'POST',
    headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
    body: fd
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok && data.url) {
      document.getElementById('pImage').value = data.url;
      onImageChange();
    } else {
      alert(data.message || 'Upload failed');
    }
  })
  .catch(function() { alert('Upload failed — check connection'); });

  input.value = '';
}

function openMediaPicker() {
  document.getElementById('mediaPickerOverlay').style.display = 'block';
  document.getElementById('mediaPickerFrame').src = '/admin/media/picker?field=popup_image&mode=select';
}

function closeMediaPicker() {
  document.getElementById('mediaPickerOverlay').style.display = 'none';
  document.getElementById('mediaPickerFrame').src = '';
}

// Listen for message from media picker iframe
window.addEventListener('message', function(e) {
  if (!e.data) return;
  // Support both type values: 'media_pick' (picker.php) and legacy 'media-selected'
  var isMediaPick = (e.data.type === 'media_pick' || e.data.type === 'media-selected');
  if (isMediaPick && e.data.url) {
    document.getElementById('pImage').value = e.data.url;
    onImageChange();
    closeMediaPicker();
  }
  // Also handle close signal
  if (e.data.type === 'media_picker_close') {
    closeMediaPicker();
  }
});

/* ── Type-specific field toggles ─────────────── */
function toggleTypeFields() {
  var type = document.getElementById('popupType').value;
  document.getElementById('emailFields').style.display =
    (type === 'newsletter_signup' || type === 'exit_intent') ? 'block' : 'none';
  document.getElementById('adFields').style.display =
    (type === 'ad_popup') ? 'block' : 'none';
  document.getElementById('promoFields').style.display =
    (type === 'promotion') ? 'block' : 'none';
}

/* ── Init ─────────────────────────────────────── */
toggleTypeFields();
onImageChange();
document.getElementById('previewReplayBtn').addEventListener('click', replayAnimation);
/* Initial preview will fire once iframe posts nt_preview_ready */
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layout.php';
?>
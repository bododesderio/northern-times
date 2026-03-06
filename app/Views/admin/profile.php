<?php
declare(strict_types=1);

$pageTitle = 'My Profile';
$activeNav = 'profile';

$u = $user ?? [];

$roleColors = [
    'super_admin' => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'label' => 'Super Admin'],
    'editor'      => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'label' => 'Editor'],
    'author'      => ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => 'Author'],
];
$rc       = $roleColors[$u['role'] ?? 'author'] ?? $roleColors['author'];
$initials = strtoupper(substr((string)($u['username'] ?? 'U'), 0, 1));
$avatarBg = match($u['role'] ?? 'author') { 'super_admin' => '#c00', 'editor' => '#1a6bbf', default => '#4a4a4a' };

ob_start();
?>

<div style="max-width:820px">

<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <div class="sub">Manage your account details, bio, and password.</div>
    </div>
</div>

<?php if (!empty($flash_success)): ?>
    <div class="flash ok"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="flash bad"><?= h($flash_error) ?></div>
<?php endif; ?>

<!-- Profile header card -->
<div class="card" style="margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div style="position:relative;flex-shrink:0">
        <?php if (!empty($u['avatar_url'])): ?>
            <img src="<?= h($u['avatar_url']) ?>"
                 alt=""
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border)"
                 id="avatarPreviewImg"
            />
        <?php else: ?>
            <div id="avatarPlaceholder" style="
                width:72px;height:72px;border-radius:50%;
                background:<?= h($avatarBg) ?>;
                display:flex;align-items:center;justify-content:center;
                font-size:28px;font-weight:700;color:#fff
            "><?= h($initials) ?></div>
        <?php endif; ?>
    </div>

    <div style="flex:1;min-width:0">
        <div style="font-size:20px;font-weight:800;margin-bottom:4px"><?= h((string)($u['username'] ?? '')) ?></div>
        <div class="muted" style="font-size:14px;margin-bottom:8px"><?= h((string)($u['email'] ?? '')) ?></div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="display:inline-block;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:700;background:<?= h($rc['bg']) ?>;color:<?= h($rc['text']) ?>">
                <?= h($rc['label']) ?>
            </span>
            <?php if (!empty($u['twitter_handle'])): ?>
                <a href="https://x.com/<?= h($u['twitter_handle']) ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:var(--muted);text-decoration:none" title="X / Twitter">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    @<?= h($u['twitter_handle']) ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($u['facebook_url'])): ?>
                <a href="<?= h($u['facebook_url']) ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:var(--muted);text-decoration:none" title="Facebook">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </a>
            <?php endif; ?>
            <?php if (!empty($u['instagram_handle'])): ?>
                <a href="https://instagram.com/<?= h($u['instagram_handle']) ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:var(--muted);text-decoration:none" title="Instagram">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    @<?= h($u['instagram_handle']) ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($u['linkedin_url'])): ?>
                <a href="<?= h($u['linkedin_url']) ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:var(--muted);text-decoration:none" title="LinkedIn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    LinkedIn
                </a>
            <?php endif; ?>
            <?php if (!empty($u['whatsapp_number'])): ?>
                <a href="https://wa.me/<?= h($u['whatsapp_number']) ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:var(--muted);text-decoration:none" title="WhatsApp">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    +<?= h($u['whatsapp_number']) ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($u['website_url'])): ?>
                <a href="<?= h($u['website_url']) ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:var(--muted);text-decoration:none" title="Website">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    Website
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align:right;flex-shrink:0">
        <div class="muted" style="font-size:12px">Member since</div>
        <div style="font-size:14px;font-weight:600">
            <?= h($u['created_at'] ? date('M j, Y', strtotime((string)$u['created_at'])) : '—') ?>
        </div>
        <?php if (!empty($u['last_login'])): ?>
            <div class="muted" style="font-size:12px;margin-top:4px">
                Last login: <?= h(date('M j, Y g:i A', strtotime((string)$u['last_login']))) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="/admin/profile">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <!-- Identity -->
    <div class="card" style="margin-bottom:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            Account Information
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label class="form-label">Username <span class="req">*</span></label>
                <input name="username" type="text" required class="form-control"
                       value="<?= h((string)($u['username'] ?? '')) ?>" autocomplete="username"/>
            </div>
            <div class="form-group">
                <label class="form-label">Email <span class="req">*</span></label>
                <input name="email" type="email" required class="form-control"
                       value="<?= h((string)($u['email'] ?? '')) ?>" autocomplete="email"/>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-control" rows="4"
                      placeholder="A short bio shown on your article pages…"
            ><?= h((string)($u['bio'] ?? '')) ?></textarea>
            <div class="form-hint">Displayed on article pages in the author bio box below each story.</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label class="form-label">Twitter / X Handle</label>
                <div style="display:flex;align-items:center">
                    <span style="padding:10px 12px;border:1px solid var(--border);border-right:none;border-radius:9px 0 0 9px;background:#f8f8f8;font-size:14px;color:var(--muted)">@</span>
                    <input name="twitter_handle" type="text" class="form-control"
                           style="border-radius:0 9px 9px 0"
                           value="<?= h((string)($u['twitter_handle'] ?? '')) ?>"
                           placeholder="yourhandle"/>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Avatar</label>
                <div style="display:flex;gap:8px;align-items:flex-start">
                    <input name="avatar_url" type="text" class="form-control"
                           value="<?= h((string)($u['avatar_url'] ?? '')) ?>"
                           placeholder="https://… or pick from media"
                           id="avatarInput"/>
                    <button type="button" class="btn light sm"
                            style="white-space:nowrap;flex-shrink:0"
                            onclick="openPickerModal('avatar_url')">
                        Pick Image
                    </button>
                </div>
                <div class="form-hint">Square image works best. Will appear circular on screen.</div>
            </div>
        </div>

        <!-- Live avatar preview -->
        <div id="avatarPreviewWrap" style="<?= empty($u['avatar_url']) ? 'display:none' : '' ?>;display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f8f8f8;border-radius:10px;margin-top:4px">
            <img id="avatarPreview" src="<?= h((string)($u['avatar_url'] ?? '')) ?>" alt=""
                 style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--border)"/>
            <span style="font-size:13px;color:var(--muted)">Avatar preview</span>
        </div>

        <!-- Social Media Links -->
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border)">
            <div style="font-weight:700;font-size:13px;margin-bottom:14px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">Social Media & Contact</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label class="form-label">Facebook</label>
                    <input name="facebook_url" type="url" class="form-control"
                           value="<?= h((string)($u['facebook_url'] ?? '')) ?>"
                           placeholder="https://facebook.com/yourpage"/>
                    <div class="form-hint">Full URL to your Facebook profile or page.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">LinkedIn</label>
                    <input name="linkedin_url" type="url" class="form-control"
                           value="<?= h((string)($u['linkedin_url'] ?? '')) ?>"
                           placeholder="https://linkedin.com/in/yourprofile"/>
                    <div class="form-hint">Full URL to your LinkedIn profile.</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label class="form-label">Instagram</label>
                    <div style="display:flex;align-items:center">
                        <span style="padding:10px 12px;border:1px solid var(--border);border-right:none;border-radius:9px 0 0 9px;background:#f8f8f8;font-size:14px;color:var(--muted)">@</span>
                        <input name="instagram_handle" type="text" class="form-control"
                               style="border-radius:0 9px 9px 0"
                               value="<?= h((string)($u['instagram_handle'] ?? '')) ?>"
                               placeholder="yourhandle"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">WhatsApp</label>
                    <div style="display:flex;align-items:center">
                        <span style="padding:10px 12px;border:1px solid var(--border);border-right:none;border-radius:9px 0 0 9px;background:#f8f8f8;font-size:14px;color:var(--muted)">+</span>
                        <input name="whatsapp_number" type="text" class="form-control"
                               style="border-radius:0 9px 9px 0"
                               value="<?= h((string)($u['whatsapp_number'] ?? '')) ?>"
                               placeholder="256700000000"/>
                    </div>
                    <div class="form-hint">International format without + (e.g. 256700000000)</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Website</label>
                <input name="website_url" type="url" class="form-control"
                       value="<?= h((string)($u['website_url'] ?? '')) ?>"
                       placeholder="https://yourwebsite.com"/>
                <div class="form-hint">Your personal blog, portfolio, or company website.</div>
            </div>
        </div>

        <!-- Role (read-only) -->
        <div style="margin-top:16px;padding:12px 14px;background:#f8f8f8;border-radius:10px;display:flex;align-items:center;gap:10px">
            <span style="font-size:13px;color:var(--muted)">Your role:</span>
            <span style="display:inline-block;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:700;background:<?= h($rc['bg']) ?>;color:<?= h($rc['text']) ?>">
                <?= h($rc['label']) ?>
            </span>
            <span style="font-size:12px;color:var(--muted);margin-left:4px">Roles can only be changed by a Super Admin.</span>
        </div>
    </div>

    <!-- Change password -->
    <div class="card" style="margin-bottom:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:6px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            Change Password
        </div>
        <div class="muted" style="font-size:13px;margin-bottom:16px">
            Leave all three fields blank to keep your current password.
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input name="current_password" type="password" class="form-control"
                       placeholder="Your current password" autocomplete="current-password"/>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input name="new_password" type="password" class="form-control"
                       placeholder="Min. 8 characters" autocomplete="new-password"
                       minlength="8" id="newPwField"/>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input name="confirm_password" type="password" class="form-control"
                       placeholder="Repeat new password" autocomplete="new-password"
                       id="confirmPwField"/>
            </div>
        </div>
        <div id="pwMatch" style="font-size:13px;margin-top:4px;display:none"></div>
    </div>

    <!-- Save -->
    <div style="display:flex;align-items:center;gap:12px">
        <button class="btn" type="submit">Save Profile</button>
        <a class="btn light" href="/admin">Cancel</a>
    </div>
</form>
</div>

<!-- ─── Inline Media Picker Modal ──────────────────────────────────────── -->
<div id="pickerOverlay" class="nt-picker-overlay">
  <div class="nt-picker-modal">
    <!-- Toolbar -->
    <div class="nt-picker-toolbar">
      <span class="nt-picker-toolbar-title">Pick an Image</span>
      <input id="pickerSearch" type="text" placeholder="Search images…">
      <select id="pickerFolder"><option value="">All folders</option></select>
      <button class="btn light sm" id="pickerSearchBtn">Search</button>
      <button class="btn light sm" onclick="closePickerModal()">Cancel</button>
    </div>
    <!-- Scrollable grid -->
    <div class="nt-picker-grid-wrap">
      <div id="pickerGrid" class="nt-picker-grid"></div>
    </div>
    <!-- Footer -->
    <div class="nt-picker-footer">
      <div id="pickerSelectedInfo" class="nt-picker-footer-info">Click an image to select it</div>
      <div style="display:flex;gap:8px">
        <button class="btn light sm" onclick="closePickerModal()">Cancel</button>
        <button class="btn sm" id="pickerInsertBtn" disabled
                style="background:#121212;color:#fff;border-color:#121212">Insert Selected</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    // ── Avatar URL live preview ──────────────────────────────────────
    var avatarInput      = document.getElementById('avatarInput');
    var avatarPreview    = document.getElementById('avatarPreview');
    var avatarPreviewWrap = document.getElementById('avatarPreviewWrap');

    function updateAvatarPreview(url) {
        if (url) {
            avatarPreviewWrap.style.display = 'flex';
            if (avatarPreview) avatarPreview.src = url;
        } else {
            avatarPreviewWrap.style.display = 'none';
        }
    }

    if (avatarInput) {
        avatarInput.addEventListener('input', function () {
            updateAvatarPreview(avatarInput.value.trim());
        });
    }

    // ── Password match indicator ─────────────────────────────────────
    var newPw     = document.getElementById('newPwField');
    var confirmPw = document.getElementById('confirmPwField');
    var matchMsg  = document.getElementById('pwMatch');

    function checkMatch() {
        if (!newPw || !confirmPw || !matchMsg) return;
        if (newPw.value === '' && confirmPw.value === '') {
            matchMsg.style.display = 'none'; return;
        }
        matchMsg.style.display = 'block';
        if (newPw.value === confirmPw.value) {
            matchMsg.style.color = '#15803d';
            matchMsg.textContent = '✓ Passwords match';
            confirmPw.style.borderColor = '#86efac';
        } else {
            matchMsg.style.color = '#b91c1c';
            matchMsg.textContent = '✗ Passwords do not match';
            confirmPw.style.borderColor = '#fca5a5';
        }
    }
    if (newPw)     newPw.addEventListener('input', checkMatch);
    if (confirmPw) confirmPw.addEventListener('input', checkMatch);

    // ── Inline Media Picker ──────────────────────────────────────────
    var overlay         = document.getElementById('pickerOverlay');
    var grid            = document.getElementById('pickerGrid');
    var searchInput     = document.getElementById('pickerSearch');
    var folderSelect    = document.getElementById('pickerFolder');
    var insertBtn       = document.getElementById('pickerInsertBtn');
    var selectedInfo    = document.getElementById('pickerSelectedInfo');
    var pickerSearchBtn = document.getElementById('pickerSearchBtn');

    var currentField = null;
    var selectedItem = null;

    window.openPickerModal = function (fieldName) {
        currentField = fieldName;
        selectedItem = null;
        insertBtn.disabled = true;
        selectedInfo.textContent = 'Click an image to select it';
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        loadImages('', '');
    };

    window.closePickerModal = function () {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    };

    // Close on backdrop click
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closePickerModal();
    });

    // Escape key closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.style.display === 'flex') closePickerModal();
    });

    function loadImages(q, folder) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#999">Loading…</div>';
        folderSelect.innerHTML = '<option value="">All folders</option>';

        var url = '/admin/media/picker?q=' + encodeURIComponent(q) +
                  '&folder=' + encodeURIComponent(folder);

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var items = data.items || [];

            // Build folder list from items
            var folders = {};
            items.forEach(function (m) { if (m.folder) folders[m.folder] = true; });
            Object.keys(folders).sort().forEach(function (f) {
                var opt = document.createElement('option');
                opt.value = f; opt.textContent = f;
                if (f === folder) opt.selected = true;
                folderSelect.appendChild(opt);
            });

            if (!items.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:#999">No images found. Upload some in the Media Library.</div>';
                return;
            }

            grid.innerHTML = '';
            items.forEach(function (m) {
                // Use thumbnail if available, else public_url. Both are plain URL strings.
                var thumbSrc = (m.thumbnail_url || m.public_url || '').trim();
                var fullUrl  = (m.webp_url || m.public_url || '').trim();
                var title    = (m.title || m.original_name || '').trim();

                var card = document.createElement('div');
                card.className     = 'nt-picker-item';
                card.dataset.url   = fullUrl;
                card.dataset.title = title;

                var img = document.createElement('img');
                img.src = thumbSrc;
                img.alt = title;
                img.loading = 'lazy';

                var label = document.createElement('div');
                label.className   = 'nt-picker-item-label';
                label.textContent = title.length > 28 ? title.substring(0, 28) + '…' : title;

                card.appendChild(img);
                card.appendChild(label);

                card.addEventListener('click', function () {
                    document.querySelectorAll('#pickerGrid .nt-picker-item').forEach(function (el) {
                        el.classList.remove('selected');
                    });
                    card.classList.add('selected');
                    selectedItem = { url: fullUrl, title: title };
                    selectedInfo.innerHTML = '<strong>' + title.substring(0, 45) + '</strong>';
                    insertBtn.disabled = false;
                });

                card.addEventListener('dblclick', function () { doInsert(); });

                grid.appendChild(card);
            });
        })
        .catch(function () {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:#c00">Failed to load images. Please try again.</div>';
        });
    }

    function doInsert() {
        if (!selectedItem || !currentField) return;

        var el = document.querySelector('[name="' + currentField + '"]');
        if (el) {
            el.value = selectedItem.url;
            el.dispatchEvent(new Event('input'));
        }

        // Also update the avatar preview immediately if it's the avatar field
        if (currentField === 'avatar_url') {
            updateAvatarPreview(selectedItem.url);
        }

        closePickerModal();
    }

    insertBtn.addEventListener('click', doInsert);

    function doSearch() {
        loadImages(searchInput.value.trim(), folderSelect.value);
    }

    pickerSearchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') doSearch();
    });
    folderSelect.addEventListener('change', doSearch);
})();
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
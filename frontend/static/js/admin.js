/**
 * Northern Times Admin — UI utilities
 * Toast notifications, confirm/prompt modals, form helpers
 */
(function() {
  'use strict';

  // ── Toast (stackable, auto-dismiss) ──────────────────────────────────────
  function toast(message, type, duration) {
    type = type || 'info';
    duration = duration || 2500;
    var container = document.getElementById('nt-admin-toasts');
    if (!container) {
      container = document.createElement('div');
      container.id = 'nt-admin-toasts';
      container.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:999999;max-width:92vw;pointer-events:none;display:flex;flex-direction:column;align-items:center;gap:8px';
      document.body.appendChild(container);
    }
    var t = document.createElement('div');
    t.textContent = message;
    t.style.cssText = 'pointer-events:auto;padding:11px 20px;border-radius:10px;background:var(--adm-ink,#1a1a1a);color:var(--adm-surface,#fff);box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:14px;font-weight:500;cursor:pointer;user-select:none;opacity:0;transform:translateY(10px);transition:all .22s ease';
    if (type === 'ok')  t.style.background = '#16a34a';
    if (type === 'bad') t.style.background = '#dc2626';
    container.appendChild(t);
    requestAnimationFrame(function() {
      t.style.opacity = '1';
      t.style.transform = 'translateY(0)';
    });
    var dismiss = function() {
      t.style.opacity = '0';
      t.style.transform = 'translateY(8px)';
      setTimeout(function() { t.remove(); }, 200);
    };
    t.addEventListener('click', dismiss);
    setTimeout(dismiss, duration);
  }
  window.toast = toast;

  // ── Alert (toast-based) ──────────────────────────────────────────────────
  window.ntAlert = function(message, type) {
    toast(message, type || 'info', 3500);
  };

  // ── Confirm Modal ────────────────────────────────────────────────────────
  window.ntConfirm = function(message, title) {
    return new Promise(function(resolve) {
      var overlay = document.createElement('div');
      overlay.className = 'nt-modal-overlay';
      overlay.innerHTML =
        '<div class="nt-modal-box">' +
          '<div class="nt-modal-title">' + (title || 'Confirm Action') + '</div>' +
          '<div class="nt-modal-msg">' + message + '</div>' +
          '<div class="nt-modal-actions">' +
            '<button class="nt-modal-cancel">Cancel</button>' +
            '<button class="nt-modal-ok">Continue</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(overlay);
      requestAnimationFrame(function() { overlay.classList.add('show'); });

      function close(result) {
        overlay.classList.remove('show');
        setTimeout(function() { overlay.remove(); }, 180);
        document.removeEventListener('keydown', keyHandler);
        resolve(result);
      }

      function keyHandler(e) {
        if (e.key === 'Escape') close(false);
        if (e.key === 'Enter') close(true);
      }

      overlay.querySelector('.nt-modal-cancel').addEventListener('click', function() { close(false); });
      overlay.querySelector('.nt-modal-ok').addEventListener('click', function() { close(true); });
      overlay.addEventListener('click', function(e) { if (e.target === overlay) close(false); });
      document.addEventListener('keydown', keyHandler);
      overlay.querySelector('.nt-modal-ok').focus();
    });
  };

  // ── Prompt Modal ─────────────────────────────────────────────────────────
  window.ntPrompt = function(message, title, placeholder) {
    return new Promise(function(resolve) {
      var overlay = document.createElement('div');
      overlay.className = 'nt-modal-overlay';
      overlay.innerHTML =
        '<div class="nt-modal-box">' +
          '<div class="nt-modal-title">' + (title || 'Input Required') + '</div>' +
          '<div class="nt-modal-msg">' + message + '</div>' +
          '<input type="text" class="nt-modal-input" placeholder="' + (placeholder || '') + '" autocomplete="off" />' +
          '<div class="nt-modal-actions">' +
            '<button class="nt-modal-cancel">Cancel</button>' +
            '<button class="nt-modal-ok">Confirm</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(overlay);
      requestAnimationFrame(function() { overlay.classList.add('show'); });

      var input = overlay.querySelector('.nt-modal-input');

      function close(value) {
        overlay.classList.remove('show');
        setTimeout(function() { overlay.remove(); }, 180);
        document.removeEventListener('keydown', keyHandler);
        resolve(value);
      }

      function keyHandler(e) {
        if (e.key === 'Escape') close(null);
        if (e.key === 'Enter') close(input.value);
      }

      overlay.querySelector('.nt-modal-cancel').addEventListener('click', function() { close(null); });
      overlay.querySelector('.nt-modal-ok').addEventListener('click', function() { close(input.value); });
      overlay.addEventListener('click', function(e) { if (e.target === overlay) close(null); });
      document.addEventListener('keydown', keyHandler);
      input.focus();
    });
  };

  // ── Form submit with confirm ─────────────────────────────────────────────
  window.ntSubmitConfirm = function(event, message, title) {
    event.preventDefault();
    var form = event.target;
    ntConfirm(message, title).then(function(ok) {
      if (ok) {
        form.removeAttribute('onsubmit');
        form.submit();
      }
    });
    return false;
  };

  // ── Factory Reset (confirm + prompt chain) ───────────────────────────────
  window.ntFactoryReset = function(event) {
    event.preventDefault();
    var form = event.target;
    ntConfirm(
      'FACTORY RESET: Delete ALL data except your account and roles? This CANNOT be undone!',
      'Danger Zone'
    ).then(function(ok) {
      if (!ok) return;
      return ntPrompt('Type <strong>FACTORY RESET</strong> to confirm:', 'Final Confirmation', 'FACTORY RESET');
    }).then(function(value) {
      if (value === 'FACTORY RESET') {
        form.removeAttribute('onsubmit');
        form.submit();
      } else if (value !== undefined && value !== null) {
        ntAlert('Reset cancelled — input did not match.', 'bad');
      }
    });
    return false;
  };

  // ── Bulk action with validation ──────────────────────────────────────────
  window.ntBulkConfirm = function(event, selectName) {
    event.preventDefault();
    var form = event.target;
    var select = form.querySelector('select[name="' + selectName + '"]');
    if (!select || !select.value) {
      ntAlert('Pick an action first.', 'bad');
      return false;
    }
    ntConfirm('Apply "' + select.options[select.selectedIndex].text + '" to selected items?').then(function(ok) {
      if (ok) {
        form.removeAttribute('onsubmit');
        form.submit();
      }
    });
    return false;
  };

  // ── Reusable Image Upload Component ────────────────────────────────────────
  /**
   * ntImageUpload(containerId, inputName)
   *
   * Initializes a reusable image upload component inside the element with the
   * given containerId. It creates:
   *   - A file input for device upload (AJAX POST to /admin/media/upload/)
   *   - A "Media Library" button that opens the media picker in a modal
   *   - A URL input as fallback manual entry
   *   - An image preview that updates on selection
   *
   * The selected URL is stored in a hidden input with the given inputName.
   *
   * Usage:
   *   <div id="featured-image"></div>
   *   <script>ntImageUpload('featured-image', 'featured_image_url');</script>
   */
  window.ntImageUpload = function(containerId, inputName, initialValue) {
    var container = document.getElementById(containerId);
    if (!container) return;

    initialValue = initialValue || '';

    // Build the component HTML
    container.innerHTML =
      '<div class="nt-img-upload">' +
        '<div class="nt-img-preview" id="' + containerId + '-preview" style="margin-bottom:12px;border-radius:10px;overflow:hidden;border:1px solid var(--adm-border,#e0e0e0);background:#f5f5f5;display:' + (initialValue ? 'block' : 'none') + '">' +
          '<img id="' + containerId + '-preview-img" src="' + initialValue + '" alt="Preview" style="max-width:100%;max-height:220px;display:block;object-fit:cover">' +
        '</div>' +
        '<input type="hidden" name="' + inputName + '" id="' + containerId + '-value" value="' + initialValue + '">' +
        '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px">' +
          '<label class="btn light" style="cursor:pointer;margin:0;font-size:13px;padding:8px 14px">' +
            '<input type="file" accept="image/*" id="' + containerId + '-file" style="display:none">' +
            'Upload from Device' +
          '</label>' +
          '<button type="button" class="btn light" style="font-size:13px;padding:8px 14px" id="' + containerId + '-picker-btn">Media Library</button>' +
          '<span id="' + containerId + '-status" style="font-size:12px;color:var(--adm-muted,#888)"></span>' +
        '</div>' +
        '<div class="form-group" style="margin-bottom:0">' +
          '<label class="form-label" style="font-size:12px">Or paste image URL</label>' +
          '<input type="url" id="' + containerId + '-url" class="form-control" placeholder="https://..." value="' + initialValue + '" style="font-size:13px">' +
        '</div>' +
      '</div>';

    var hiddenInput = document.getElementById(containerId + '-value');
    var urlInput = document.getElementById(containerId + '-url');
    var fileInput = document.getElementById(containerId + '-file');
    var pickerBtn = document.getElementById(containerId + '-picker-btn');
    var previewWrap = document.getElementById(containerId + '-preview');
    var previewImg = document.getElementById(containerId + '-preview-img');
    var statusEl = document.getElementById(containerId + '-status');

    function setImage(url) {
      hiddenInput.value = url;
      urlInput.value = url;
      if (url) {
        previewImg.src = url;
        previewWrap.style.display = 'block';
      } else {
        previewWrap.style.display = 'none';
      }
    }

    function getCsrfToken() {
      var meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) return meta.content;
      var match = document.cookie.match(/csrftoken=([^;]+)/);
      return match ? match[1] : '';
    }

    // File upload via AJAX
    fileInput.addEventListener('change', function() {
      if (!fileInput.files.length) return;
      var formData = new FormData();
      formData.append('file', fileInput.files[0]);
      formData.append('folder', 'Articles');

      statusEl.textContent = 'Uploading...';

      fetch('/admin/media/upload/', {
        method: 'POST',
        headers: { 'X-CSRFToken': getCsrfToken() },
        body: formData
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.url) {
          setImage(data.url);
          statusEl.textContent = 'Uploaded!';
          setTimeout(function() { statusEl.textContent = ''; }, 2000);
        } else {
          statusEl.textContent = data.error || 'Upload failed.';
        }
      })
      .catch(function(err) {
        statusEl.textContent = 'Error: ' + err.message;
      });
    });

    // URL input change
    urlInput.addEventListener('change', function() {
      setImage(urlInput.value.trim());
    });
    urlInput.addEventListener('blur', function() {
      setImage(urlInput.value.trim());
    });

    // Media Library picker (opens in modal iframe)
    pickerBtn.addEventListener('click', function() {
      var overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .18s ease';
      var modal = document.createElement('div');
      modal.style.cssText = 'background:#fff;border-radius:14px;width:90vw;max-width:860px;height:80vh;max-height:640px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 16px 64px rgba(0,0,0,.25)';
      modal.innerHTML =
        '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #e0e0e0">' +
          '<strong style="font-size:15px">Select from Media Library</strong>' +
          '<button type="button" id="' + containerId + '-picker-close" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;line-height:1">&times;</button>' +
        '</div>' +
        '<iframe src="/admin/media/picker/" style="flex:1;border:none;width:100%"></iframe>';
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      requestAnimationFrame(function() { overlay.style.opacity = '1'; });

      function closeModal() {
        overlay.style.opacity = '0';
        setTimeout(function() { overlay.remove(); }, 180);
        window.removeEventListener('message', messageHandler);
      }

      function messageHandler(e) {
        if (e.data && e.data.type === 'nt-media-selected' && e.data.url) {
          setImage(e.data.url);
          closeModal();
        }
      }

      document.getElementById(containerId + '-picker-close').addEventListener('click', closeModal);
      overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });
      window.addEventListener('message', messageHandler);
    });
  };

})();

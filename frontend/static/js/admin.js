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

})();

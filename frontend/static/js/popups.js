/**
 * Northern Times — Popup Engine
 *
 * Fetches active popups from /api/popups, checks targeting/frequency,
 * shows one at a time in priority order, tracks events.
 */
(function () {
  'use strict';

  var STORAGE_PREFIX = 'nt_popup_';
  var API_URL = '/api/popups';
  var TRACK_URL = '/api/popup-track';
  var activePopup = null;

  // ── Helpers ──────────────────────────────────────────────────

  function isMobile() {
    return window.innerWidth < 768;
  }

  function getVisitCount() {
    var c = parseInt(localStorage.getItem('nt_visit_count') || '0', 10);
    return c;
  }

  function incrementVisitCount() {
    var c = getVisitCount() + 1;
    localStorage.setItem('nt_visit_count', String(c));
    return c;
  }

  function isReturning() {
    return getVisitCount() > 1;
  }

  function getDismissalKey(popup) {
    return STORAGE_PREFIX + 'dismiss_' + popup.id + '_v' + popup.version;
  }

  function isDismissed(popup) {
    var key = getDismissalKey(popup);
    var stored = localStorage.getItem(key);
    if (!stored) return false;

    var data;
    try { data = JSON.parse(stored); } catch (e) { return false; }

    var now = Date.now();
    var freq = popup.frequency;

    if (freq === 'once_ever') return true;
    if (freq === 'every_visit') return false;

    if (freq === 'once_session') {
      return sessionStorage.getItem(key) === '1';
    }

    var dayMs = 86400000;
    var elapsed = now - (data.time || 0);

    if (freq === 'daily') return elapsed < dayMs;
    if (freq === 'weekly') return elapsed < dayMs * 7;
    if (freq === 'monthly') return elapsed < dayMs * 30;
    if (freq === 'custom_days') {
      var days = parseInt(popup.frequency_days, 10) || 1;
      return elapsed < dayMs * days;
    }

    return false;
  }

  function markDismissed(popup) {
    var key = getDismissalKey(popup);
    var data = JSON.stringify({ time: Date.now() });
    try {
      localStorage.setItem(key, data);
      sessionStorage.setItem(key, '1');
    } catch (e) {}
  }

  // ── Targeting ────────────────────────────────────────────────

  function matchesTarget(popup) {
    // Device
    if (popup.target_device === 'mobile' && !isMobile()) return false;
    if (popup.target_device === 'desktop' && isMobile()) return false;

    // Audience
    var aud = popup.target_audience;
    if (aud === 'first_time' && isReturning()) return false;
    if (aud === 'returning' && !isReturning()) return false;

    // Pages
    if (popup.target_pages) {
      var pages = popup.target_pages.split(',').map(function (p) { return p.trim(); });
      var path = window.location.pathname;
      var match = pages.some(function (pattern) {
        if (pattern.endsWith('*')) {
          return path.startsWith(pattern.slice(0, -1));
        }
        return path === pattern;
      });
      if (!match) return false;
    }

    return true;
  }

  // ── Tracking ─────────────────────────────────────────────────

  function track(popupId, event, extra) {
    var body = { popup_id: popupId, event: event, page_url: window.location.href };
    if (extra) {
      for (var k in extra) body[k] = extra[k];
    }
    try {
      navigator.sendBeacon(TRACK_URL, JSON.stringify(body));
    } catch (e) {
      // Fallback to fetch
      fetch(TRACK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).catch(function () {});
    }
  }

  // ── Rendering ────────────────────────────────────────────────

  function posClass(pos) {
    return 'pos-' + (pos || 'center');
  }

  function buildPopupHTML(popup) {
    var style = popup.banner_style;
    var hasImg = popup.image_url && popup.image_url !== '';
    var imgHTML = hasImg ? '<div class="nt-popup-img"><img src="' + escH(popup.image_url) + '" alt="" loading="lazy"></div>' : '';

    var titleHTML = popup.title ? '<h3>' + escH(popup.title) + '</h3>' : '';
    var bodyHTML = popup.body ? '<p>' + escH(popup.body) + '</p>' : '';

    var emailHTML = '';
    if (popup.has_email_field === true || popup.has_email_field === 't' || popup.has_email_field === '1') {
      emailHTML = '<div class="nt-popup-email">' +
        '<input type="email" placeholder="Your email address" class="nt-popup-email-input" />' +
        '</div>';
    }

    var btnHTML = popup.button_text
      ? '<button class="nt-popup-btn" data-action="click" style="background:' + escColor(popup.btn_bg_color || '#cc0000') + ';color:' + escColor(popup.btn_text_color || '#fff') + '">' + escH(popup.button_text) + '</button>'
      : '';

    var secBtnHTML = popup.secondary_btn_text
      ? '<button class="nt-popup-btn-secondary" data-action="dismiss">' + escH(popup.secondary_btn_text) + '</button>'
      : '';

    var closeHTML = '<button class="nt-popup-close" data-action="close" aria-label="Close">&times;</button>';

    var innerContent = titleHTML + bodyHTML + emailHTML + btnHTML + '<br>' + secBtnHTML;

    var html = '';

    if (style === 'minimal_bar') {
      html = '<div class="nt-popup nt-popup-minimal_bar ' + posClass(popup.position) + '" style="background:' + escColor(popup.bg_color || '#fff') + ';color:' + escColor(popup.text_color || '#1a1a1a') + '">' +
        (hasImg ? '<img src="' + escH(popup.image_url) + '" style="width:24px;height:24px;border-radius:4px" />' : '') +
        '<span>' + escH(popup.title || '') + '</span>' +
        (popup.body ? '<span style="opacity:.7">' + escH(popup.body) + '</span>' : '') +
        btnHTML + closeHTML +
        '</div>';
    } else if (style === 'split_image') {
      html = '<div class="nt-popup nt-popup-split_image" style="background:' + escColor(popup.bg_color || '#fff') + ';color:' + escColor(popup.text_color || '#1a1a1a') + '">' +
        imgHTML +
        '<div class="nt-popup-content">' + closeHTML + innerContent + '</div>' +
        '</div>';
    } else if (style === 'fullscreen') {
      var bgStyle = hasImg ? 'background-image:url(' + escH(popup.image_url) + ')' : 'background:' + escH(popup.bg_color || '#333');
      html = '<div class="nt-popup nt-popup-fullscreen" style="' + bgStyle + '">' +
        '<div class="nt-popup-content" style="background:' + escColor(popup.bg_color || '#fff') + ';color:' + escColor(popup.text_color || '#1a1a1a') + '">' +
        closeHTML + innerContent +
        '</div></div>';
    } else if (style === 'slide_in') {
      html = '<div class="nt-popup nt-popup-slide_in ' + posClass(popup.position) + '" style="background:' + escColor(popup.bg_color || '#fff') + ';color:' + escColor(popup.text_color || '#1a1a1a') + '">' +
        closeHTML + imgHTML +
        '<div class="nt-popup-content">' + innerContent + '</div></div>';
    } else if (style === 'floating') {
      html = '<div class="nt-popup nt-popup-floating ' + posClass(popup.position) + '" style="background:' + escColor(popup.bg_color || '#fff') + ';color:' + escColor(popup.text_color || '#1a1a1a') + '">' +
        imgHTML +
        '<div class="nt-popup-content">' + titleHTML + bodyHTML + btnHTML + '</div>' +
        closeHTML + '</div>';
    } else {
      // Default: card_modal
      html = '<div class="nt-popup nt-popup-card_modal" style="background:' + escColor(popup.bg_color || '#fff') + ';color:' + escColor(popup.text_color || '#1a1a1a') + '">' +
        closeHTML + imgHTML +
        '<div class="nt-popup-content">' + innerContent + '</div></div>';
    }

    return html;
  }

  function showPopup(popup) {
    if (activePopup) return; // Only one at a time
    activePopup = popup;

    var needsOverlay = ['card_modal', 'split_image', 'fullscreen'].indexOf(popup.banner_style) >= 0;
    var wrapper = document.createElement('div');
    wrapper.id = 'nt-popup-wrapper';

    if (needsOverlay) {
      wrapper.className = 'nt-popup-overlay';
      wrapper.style.setProperty('--nt-popup-overlay', popup.overlay_opacity || '0.5');
    }

    wrapper.innerHTML = buildPopupHTML(popup);
    document.body.appendChild(wrapper);

    // Dialog semantics + body scroll-lock for overlay modals
    var dialogEl = wrapper.querySelector('.nt-popup');
    if (dialogEl) {
      dialogEl.setAttribute('role', 'dialog');
      dialogEl.setAttribute('aria-modal', 'true');
    }
    if (needsOverlay) document.body.style.overflow = 'hidden';

    // Focus management: remember the opener, focus the first control
    wrapper._lastFocus = document.activeElement;
    var firstFocusable = wrapper.querySelector('button, [href], input, textarea, select');
    if (firstFocusable) firstFocusable.focus();

    // Keyboard: Escape closes; Tab is trapped inside the popup
    wrapper._escHandler = function (e) {
      if (e.key === 'Escape') { closePopup(popup); track(popup.id, 'close'); return; }
      if (e.key === 'Tab') {
        var f = wrapper.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), textarea, select, [tabindex]:not([tabindex="-1"])');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    };
    document.addEventListener('keydown', wrapper._escHandler);

    // Track impression
    track(popup.id, 'impression');

    // Close delay: hide X button initially
    if (parseInt(popup.close_delay, 10) > 0) {
      var closeBtn = wrapper.querySelector('.nt-popup-close');
      if (closeBtn) {
        closeBtn.style.display = 'none';
        setTimeout(function () { closeBtn.style.display = ''; }, parseInt(popup.close_delay, 10) * 1000);
      }
    }

    // Event delegation
    wrapper.addEventListener('click', function (e) {
      var el = e.target.closest('[data-action]');
      var action = e.target.getAttribute('data-action') || (el ? el.getAttribute('data-action') : null);

      if (action === 'close' || action === 'dismiss') {
        closePopup(popup);
        track(popup.id, 'close');
      } else if (action === 'click') {
        // Check for email submission
        var emailInput = wrapper.querySelector('.nt-popup-email-input');
        if (emailInput && emailInput.value) {
          track(popup.id, 'email_submit', { email: emailInput.value });
          closePopup(popup);
          return;
        }

        track(popup.id, 'click');

        if (popup.button_url || popup.click_url) {
          var url = popup.button_url || popup.click_url;
          try {
            var parsed = new URL(url, window.location.origin);
            if (/^https?:$/.test(parsed.protocol)) {
              window.open(parsed.href, '_blank', 'noopener');
            }
          } catch (_) {}
        }

        closePopup(popup);
      }
    });

    // Click overlay to close (for modals)
    if (needsOverlay) {
      wrapper.addEventListener('click', function (e) {
        if (e.target === wrapper) {
          closePopup(popup);
          track(popup.id, 'close');
        }
      });
    }
  }

  function closePopup(popup) {
    var wrapper = document.getElementById('nt-popup-wrapper');
    if (wrapper) {
      if (wrapper._escHandler) document.removeEventListener('keydown', wrapper._escHandler);
      document.body.style.overflow = '';  // release scroll-lock
      var lastFocus = wrapper._lastFocus;
      wrapper.style.opacity = '0';
      wrapper.style.transition = 'opacity 200ms ease';
      setTimeout(function () {
        wrapper.remove();
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
      }, 200);
    }
    markDismissed(popup);
    activePopup = null;

    // Show next queued popup after a brief delay
    setTimeout(processQueue, 500);
  }

  // ── Queue processing ─────────────────────────────────────────

  var popupQueue = [];

  function processQueue() {
    if (activePopup) return;
    if (popupQueue.length === 0) return;

    var popup = popupQueue.shift();
    var delay = parseInt(popup.show_delay, 10) || 0;

    if (delay > 0) {
      setTimeout(function () { showPopup(popup); }, delay * 1000);
    } else {
      showPopup(popup);
    }
  }

  // ── Trigger handlers ─────────────────────────────────────────

  function setupTriggers(popups) {
    popups.forEach(function (popup) {
      if (isDismissed(popup) || !matchesTarget(popup)) return;

      var trigger = popup.trigger_type;

      if (trigger === 'page_load' || trigger === 'time_delay') {
        popupQueue.push(popup);
      } else if (trigger === 'scroll_percent') {
        var pct = parseInt(popup.trigger_value, 10) || 50;
        var fired = false;
        window.addEventListener('scroll', function () {
          if (fired) return;
          var scrollPct = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
          if (scrollPct >= pct) {
            fired = true;
            popupQueue.push(popup);
            processQueue();
          }
        });
      } else if (trigger === 'exit_intent') {
        var fired2 = false;
        document.addEventListener('mouseout', function (e) {
          if (fired2 || e.clientY > 10) return;
          fired2 = true;
          popupQueue.push(popup);
          processQueue();
        });
      } else if (trigger === 'page_views') {
        var needed = parseInt(popup.trigger_value, 10) || 3;
        if (getVisitCount() >= needed) {
          popupQueue.push(popup);
        }
      }
    });

    // Process page_load / time_delay popups
    processQueue();
  }

  // ── Escape HTML ──────────────────────────────────────────────

  function escH(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function escColor(val) {
    if (!val) return '';
    // Only allow safe CSS color values (hex, rgb, hsl, named colors)
    if (/^#[0-9a-fA-F]{3,8}$/.test(val)) return val;
    if (/^(rgb|hsl)a?\([^)]*\)$/.test(val)) return val;
    if (/^[a-zA-Z]{1,30}$/.test(val)) return val;
    return '#333';
  }

  // ── Init ─────────────────────────────────────────────────────

  function init() {
    incrementVisitCount();

    fetch(API_URL)
      .then(function (res) {
        if (!res.ok) return [];
        return res.json();
      })
      .then(function (popups) {
        if (!Array.isArray(popups) || popups.length === 0) return;
        // Already sorted by priority from server
        setupTriggers(popups);
      })
      .catch(function () {});
  }

  // Run after DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
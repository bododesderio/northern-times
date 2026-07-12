/**
 * Northern Times — Cookie / consent banner.
 * Dependency-free. Shows once until the visitor chooses; choice persists in
 * localStorage ('nt_cookie_consent' = 'accepted' | 'declined'). Dispatches a
 * 'nt:consent' CustomEvent so analytics/ads can gate on the decision.
 */
(function () {
  'use strict';
  var KEY = 'nt_cookie_consent';

  function stored() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }
  function save(v) {
    try { localStorage.setItem(KEY, v); } catch (e) {}
    document.dispatchEvent(new CustomEvent('nt:consent', { detail: v }));
  }
  if (stored()) return; // already decided

  function build() {
    var bar = document.createElement('div');
    bar.className = 'nt-consent';
    bar.setAttribute('role', 'dialog');
    bar.setAttribute('aria-label', 'Cookie consent');
    bar.innerHTML =
      '<div class="nt-consent-inner">' +
        '<p class="nt-consent-text">We use cookies to understand how readers use ' +
          'the site and to improve your experience. You can accept or decline ' +
          'non-essential cookies.</p>' +
        '<div class="nt-consent-actions">' +
          '<button type="button" class="nt-consent-btn nt-consent-decline">Decline</button>' +
          '<button type="button" class="nt-consent-btn nt-consent-accept">Accept</button>' +
        '</div>' +
      '</div>';
    return bar;
  }

  function mount() {
    var bar = build();
    document.body.appendChild(bar);
    requestAnimationFrame(function () { bar.classList.add('open'); });

    function dismiss(choice) {
      save(choice);
      bar.classList.remove('open');
      setTimeout(function () { bar.remove(); }, 300);
    }
    bar.querySelector('.nt-consent-accept').addEventListener('click', function () { dismiss('accepted'); });
    bar.querySelector('.nt-consent-decline').addEventListener('click', function () { dismiss('declined'); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();

/**
 * Northern Times — article image lightbox.
 * Click any content image to view it full-size in an overlay.
 * Esc / click-outside / close button dismiss it. Focus is restored to the
 * opener. Dependency-free; only active when article images are present.
 */
(function () {
  'use strict';

  function init() {
    var imgs = document.querySelectorAll('.article-body img, .np-article-body img');
    if (!imgs.length) return;

    var overlay = null;
    var lastFocus = null;

    function close() {
      if (!overlay) return;
      document.body.style.overflow = '';
      overlay.classList.remove('open');
      var o = overlay;
      overlay = null;
      setTimeout(function () { o.remove(); }, 200);
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    function open(src, alt) {
      lastFocus = document.activeElement;
      overlay = document.createElement('div');
      overlay.className = 'nt-lightbox';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-label', alt || 'Image');
      overlay.innerHTML =
        '<button type="button" class="nt-lightbox-close" aria-label="Close image">&times;</button>' +
        '<img class="nt-lightbox-img" src="' + src + '" alt="' + (alt || '').replace(/"/g, '&quot;') + '" />';
      document.body.appendChild(overlay);
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(function () { overlay.classList.add('open'); });

      var closeBtn = overlay.querySelector('.nt-lightbox-close');
      closeBtn.focus();
      closeBtn.addEventListener('click', close);
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay) close();
    });

    imgs.forEach(function (img) {
      // Skip tiny/icon images
      if (img.closest('a')) return;  // already a link — leave it
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', function () {
        open(img.currentSrc || img.src, img.getAttribute('alt') || '');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// public/assets/app.js
// Northern Times – Enhanced frontend behaviors (progress, newsletter, share, drawer, TOC)

(function () {
  "use strict";

  // ── Helpers ────────────────────────────────────────────────────────────────
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  // Toast (stackable, clickable dismiss)
  function toast(message, type = "info", duration = 2200) {
    let container = $("#nt-toasts");
    if (!container) {
      container = document.createElement("div");
      container.id = "nt-toasts";
      container.style.position = "fixed";
      container.style.left = "50%";
      container.style.bottom = "24px";
      container.style.transform = "translateX(-50%)";
      container.style.zIndex = "999999";
      container.style.maxWidth = "92vw";
      container.style.pointerEvents = "none";
      document.body.appendChild(container);
    }

    const t = document.createElement("div");
    t.textContent = message;
    t.style.pointerEvents = "auto";
    t.style.marginTop = container.children.length ? "8px" : "0";
    t.style.padding = "10px 16px";
    t.style.borderRadius = "12px";
    t.style.border = "1px solid var(--np-border, #333)";
    t.style.background = "var(--np-surface-1, #1c1917)";
    t.style.color = "var(--color-text-primary, #fafafa)";
    t.style.boxShadow = "0 12px 40px rgba(0,0,0,0.25)";
    t.style.fontFamily = 'system-ui, sans-serif';
    t.style.fontSize = "14px";
    t.style.cursor = "pointer";
    t.style.userSelect = "none";

    if (type === "ok")   t.style.borderColor = "#a8e6cf";
    if (type === "bad")  { t.style.borderColor = "#ffcccc"; t.style.color = "#c00"; }

    container.appendChild(t);

    const dismiss = () => {
      t.style.opacity = "0";
      t.style.transform = "translateY(8px)";
      t.style.transition = "all 0.22s ease";
      setTimeout(() => t.remove(), 240);
    };

    t.addEventListener("click", dismiss);
    setTimeout(dismiss, duration);
  }

  // Safe clipboard copy with fallback
  async function copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.left = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      try {
        return document.execCommand("copy");
      } finally {
        ta.remove();
      }
    }
  }

  function getCsrfToken() {
    return $('meta[name="x-csrf-token"]')?.content || "";
  }

  // ── Share / Copy ───────────────────────────────────────────────────────────
  document.addEventListener("click", async e => {
    const btn = e.target.closest("[data-share], [data-copy]");
    if (!btn) return;

    const isShare = btn.hasAttribute("data-share");
    const url = (isShare ? btn.dataset.share : btn.dataset.copy) || window.location.href;
    const title = document.title;

    try {
      if (isShare && navigator.share) {
        await navigator.share({ title, url });
        toast("Shared!", "ok", 1400);
        return;
      }

      const success = await copyToClipboard(url);
      if (success) {
        const origText = btn.textContent;
        btn.textContent = "Copied!";
        toast("Link copied to clipboard", "ok");
        setTimeout(() => { btn.textContent = origText; }, 1800);
      } else {
        toast("Copy failed – please copy manually", "bad");
      }
    } catch (err) {
      // user cancelled or not supported
    }
  });

  // ── Newsletter forms ───────────────────────────────────────────────────────
  function initNewsletterForm(form, msgEl = null) {
    if (!form) return;

    const submitBtn = form.querySelector("button[type='submit']");
    const csrf = getCsrfToken();

    form.addEventListener("submit", async e => {
      e.preventDefault();

      if (msgEl) {
        msgEl.textContent = "Submitting…";
        msgEl.style.color = "";
      }
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting…";
      }

      const formData = new FormData(form);
      if (csrf && !formData.has("_csrf")) formData.append("_csrf", csrf);

      try {
        const res = await fetch(form.action, {
          method: "POST",
          body: formData,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-Token": csrf
          }
        });

        let data;
        try { data = await res.json(); } catch {}

        if (!res.ok || !data?.ok) {
          const errMsg = data?.message || "Subscription failed. Try again.";
          if (msgEl) msgEl.textContent = errMsg;
          toast(errMsg, "bad", 3000);
          return;
        }

        const successMsg = data.message || "Thank you — you're subscribed!";
        if (msgEl) {
          msgEl.textContent = successMsg;
          msgEl.style.color = "#006600";
        }
        toast(successMsg, "ok");
        form.reset();
      } catch {
        const netErr = "Network error — please check connection.";
        if (msgEl) msgEl.textContent = netErr;
        toast(netErr, "bad", 3000);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = submitBtn.dataset.origText || "Subscribe";
        }
      }
    });
  }

  // Bind all newsletter forms
  initNewsletterForm($("#newsletterForm"), $("#newsletterMsg"));
  initNewsletterForm($("form[data-mini-newsletter]"), $("[data-mini-msg]"));

  // Bind inline newsletter forms (article sidebar, homepage banner, etc.)
  document.querySelectorAll("form[data-newsletter-form]").forEach(form => {
    const msg = form.parentElement?.querySelector(".np-newsletter-msg");
    initNewsletterForm(form, msg);
  });

  // ── Mobile Drawer ──────────────────────────────────────────────────────────
  const openDrawerBtn  = $("#openDrawer");
  const closeDrawerBtn = $("#closeDrawer");
  const drawer         = $("#drawer");
  const backdrop       = $("#drawerBackdrop");

  if (openDrawerBtn && drawer && backdrop) {
    const open = () => {
      drawer.classList.add("open");
      backdrop.classList.add("open");
      document.body.style.overflow = "hidden";
      openDrawerBtn.setAttribute("aria-expanded", "true");
    };

    const close = () => {
      drawer.classList.remove("open");
      backdrop.classList.remove("open");
      document.body.style.overflow = "";
      openDrawerBtn.setAttribute("aria-expanded", "false");
    };

    openDrawerBtn.addEventListener("click", open);
    closeDrawerBtn.addEventListener("click", close);
    backdrop.addEventListener("click", close);

    // Escape key closes drawer
    document.addEventListener("keydown", e => {
      if (e.key === "Escape" && drawer.classList.contains("open")) close();
    });
  }

  // ── Reading Progress Bar (article pages only) ──────────────────────────────
  const articleContent = $(".article-body") || $("#articleBody");
  if (articleContent) {
    let rafScheduled = false;

    const progressWrap = document.createElement("div");
    progressWrap.id = "ntProgressWrap";
    Object.assign(progressWrap.style, {
      position: "fixed", top: "0", left: "0", width: "100%", height: "4px",
      zIndex: "9998", background: "rgba(0,0,0,0.05)", pointerEvents: "none"
    });

    const progressBar = document.createElement("div");
    progressBar.id = "ntProgress";
    Object.assign(progressBar.style, {
      height: "100%", width: "0%", background: "var(--np-accent, #e67e22)", transition: "width 0.08s linear"
    });

    progressWrap.appendChild(progressBar);
    document.body.prepend(progressWrap);

    const updateProgress = () => {
      rafScheduled = false;
      const scrollTop = window.scrollY;
      const docHeight = document.documentElement.scrollHeight - window.innerHeight;
      const percent = docHeight > 0 ? Math.min(100, (scrollTop / docHeight) * 100) : 0;
      progressBar.style.width = percent.toFixed(2) + "%";
    };

    const onScroll = () => {
      if (!rafScheduled) {
        rafScheduled = true;
        requestAnimationFrame(updateProgress);
      }
    };

    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll, { passive: true });
    updateProgress(); // initial
  }

  // ── Table of Contents (TOC) enhancements ──────────────────────────────────
  const tocContainer = $("#toc");
  if (tocContainer) {
    const articleBody = $(".article-body") || $("#articleBody") || $("#content");
    if (!articleBody) return;

    // Smooth scroll to headings
    tocContainer.addEventListener("click", e => {
      const link = e.target.closest("a[href^='#']");
      if (!link) return;
      e.preventDefault();

      const targetId = link.getAttribute("href");
      const target = document.querySelector(targetId);
      if (!target) return;

      const offset = target.getBoundingClientRect().top + window.scrollY - 100;
      window.scrollTo({ top: offset, behavior: "smooth" });
    });

    // Active section highlight with IntersectionObserver
    const headings = $$("h2[id], h3[id]", articleBody);
    if (headings.length < 1) return;

    const observer = new IntersectionObserver(entries => {
      let visibleId = null;
      for (const entry of entries) {
        if (entry.isIntersecting) {
          visibleId = entry.target.id;
          break; // take the first (top-most) visible
        }
      }
      if (!visibleId) return;

      $$("a.is-active", tocContainer).forEach(a => a.classList.remove("is-active"));
      const activeLink = tocContainer.querySelector(`a[href="#${CSS.escape(visibleId)}"]`);
      if (activeLink) activeLink.classList.add("is-active");
    }, {
      rootMargin: "-80px 0px -60% 0px",
      threshold: 0.1
    });

    headings.forEach(h => observer.observe(h));
  }

  // Optional: listen for dark mode change (if you add toggle later)
  // window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", e => {
  //   document.documentElement.classList.toggle("dark", e.matches);
  // });

})();
# NEXT SESSION — execute these (recorded 2026-07-12)

Resume trigger: "continue from where we stopped". User reviewed the build in the
browser (localhost:8080) and gave the fixes below. **Nothing here is done yet.**
After ALL of them: rebuild the image fresh, run the FULL test suite, confirm
localhost works for the user to test.

State so far this session (committed): b03096a redesign, e62cd6a email core+mailpit,
548410d email 2B, 1ee28a7 UI hardening, 8791f61 geo. 575+ tests green. Mailpit overlay
(`-f docker-compose.django.yml -f docker-compose.mailpit.yml`, UI :8026) is up.

---

## 1. Fix the three flagged items "comprehensively"
1a. **Playwright MCP won't launch** — MCP expects `/opt/google/chrome/chrome`; the
    bundled chromium is at `~/.cache/ms-playwright/chromium-1228/chrome-linux64/chrome`.
    Options: (a) ask user to run `! sudo npx playwright install-deps && sudo npx
    playwright install chrome` (interactive), OR (b) repoint the playwright MCP config
    (executablePath/channel) to the bundled chromium. Prefer (b) if sudo unavailable.
1b. **Test/runtime deps keep getting dropped on container recreate** — bake
    `pytest pytest-django bleach[css] tinycss2` into the image
    (docker/django/Dockerfile or a requirements/dev.txt COPY'd in) so rebuild/recreate
    is clean. Keep the mailpit overlay; add a short README note on how to run it.
1c. **Geo scale caveats** — (i) replace the in-Python `sorted()` haversine in
    `articles.views.category()` Local News with a DB-side haversine (annotate distance,
    ORDER BY in SQL, then paginate); (ii) improve geocoding coverage beyond 47/217:
    expand `apps/articles/services/geo.py` GAZETTEER and/or add a celery-beat task that
    geocodes ungeocoded articles periodically.

## 2. Article inline images — REMOVE them all (revert the inline-image work)
Screenshots (Museveni article) show a messy vertical stack of small inline thumbnails
crammed into the body plus oversized inline photos. **User wants ONLY the featured/hero
image; strip ALL inline `<img>`/`<figure>` from article body content.**
- This REVERTS the earlier "preserve inline images" work (obs S345): the trafilatura
  `<graphic>`→`<img>` conversion + the image-rescue in `content_cleaner.py` Phase 3, and
  the image-aware merge. Disable/remove those so body content carries no images.
- Simplest robust spot: strip `<img>`, `<figure>`, `<picture>` in the article body
  before render (in `content_cleaner` final pass, and/or in `views.article` where
  `article_content` is built). Keep `article.featured_image` (hero) untouched.
- Re-crawl or re-clean existing articles so stored content loses inline images (or strip
  at render time so no re-crawl needed — render-time strip is safer/idempotent).

## 3. Images full COLOR by default (remove grayscale-until-hover)
Everything is grayscale with color only on hover. **Make all images full color always.**
- Remove `filter: grayscale(1)` everywhere. Grep `grayscale` in
  `frontend/static/css/app.css`, `article.css`, templates, and the newsletter email
  templates (digest/topic_notify use `filter:grayscale(1)`).
- Known spots: `.np-article-body figure img`, `.np-author-card-avatar/-img`
  (app.css ~652), related thumbnails, home main-feature image, sidebar thumbs.
- Drop the `figure:hover img { filter: grayscale(0) }` hover pattern entirely.

## 4. "Local News" copy — make it generic, NOT Northern-Uganda-specific
Category desc currently: "Local reporting and community news from Northern Uganda and the
Lango sub-region." A reader in Kampala should feel included. **Make location-neutral.**
- Update: category row description (via a data migration + `seed_all.py` categories tuple
  + `migrations/0004` NEW_DESC), e.g. "Community news and reporting from your part of the
  country." Update the un-localized Local News banner copy too (category.html) so it
  doesn't imply the north. Keep the gazetteer/beat weighting, just fix reader-facing copy.

## 5. Footer — mobile fix
Mobile footer (screenshot) shows **"About Us" duplicated** in the Company column
(About Us, Contact, Privacy Policy, Terms of Service, Editorial Standards, About Us).
- Cause: likely a policy page with slug/title "About Us" coming through `footer_policies`
  in addition to the hard-coded About Us link (layout.html footer Company column).
  Dedupe / exclude, and polish mobile footer spacing/layout.

## 6. Mobile masthead artifact
Mobile masthead screenshot shows "something under the masthead/logo" — a faint stray
mark/line beneath "THE NORTHERN TIMES". Investigate `.header-masthead` /
`.header-accent-rule` / the new `#darkToggle`/search icons on mobile (possible overlap,
doubled accent rule, or an icon bleeding under the wordmark). Make the mobile masthead clean.

## 7. Editorial sidebar — make better use (it is user-facing)
Left sidebar shows only "EDITORIAL / GLOBAL EDITION" + HOME + category list. **Redesign to
add reader value**, e.g.: today's date/edition, a most-read / trending mini-list, a
"news near you" location chip, a compact newsletter CTA, live indicator. Also fix the
inconsistency: header says "EAST AFRICA EDITION" but sidebar says "GLOBAL EDITION" — pick
one. (layout.html `.editorial-sidebar`, ~line 210.)

## 8. Admin login page — left column white background
The admin login page's left column should have a **white background** (currently
dark/branded). Find the admin login template (likely `templates/admin/login.html` or an
accounts template) and its CSS; make the left panel white while keeping contrast/legibility.

## 9. System-wide silent failures — surface errors
"Things are failing silently" across the system. Do a pass to make failures VISIBLE:
- Frontend fetches (subscribe, comment, follow, reader-location, newsletter) — ensure
  non-2xx / network errors show a user-facing message, not a silent no-op.
- Backend `except Exception: pass` swallows (there are several — crawler, dispatch, mailer,
  geo, context processors) — keep the non-critical ones from breaking flow BUT log them
  (logger.warning/exception) so they're not invisible. Audit for bare excepts.
- Admin actions / Django messages framework — confirm error messages actually render in
  the admin + public templates (check the messages block exists in base templates).
- Add a global JS error/network handler + server-side logging review. Produce a short list
  of every silent-failure site found and fix or log each.

## 10. Email templates build (Brevo-bound, NOT yet wired) — see docs/EMAIL_TEMPLATES_BUILD.md
Build the 11 branded, CMS-driven, table-based HTML email templates (base + 10 components;
T-01..T-09, A-01, A-02) per `docs/EMAIL_TEMPLATES_BUILD.md`. Brevo is NOT configured — do
templates ONLY, no sending/API. RECONCILE with the existing double opt-in email work
(templates/newsletter/email_base.html, mailer.py site_context(), Subscriber model) instead
of duplicating; add missing CMS fields as `Setting` keys (logo_url, brand_primary_color,
address, editorial_email, footer_tagline, social_*) and expose them in site_context().
Render-test each to /tmp + eyeball via the Mailpit overlay (:8026).

## 11. Rebuild fresh + full final test suite
After 1–7: `docker compose -f docker-compose.django.yml [-f docker-compose.mailpit.yml]
build` (deps baked), bring up fresh, run the ENTIRE pytest suite (expect all green),
verify migrations apply cleanly on a fresh DB, and confirm localhost:8080 renders so the
user can test in the browser. Commit each coherent group; update CONTEXT.md.

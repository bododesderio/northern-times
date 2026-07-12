# Project Context
Last updated: 2026-07-12

## ⏭️ NEXT SESSION — "continue from where we stopped"
**See `NEXT_SESSION.md` (repo root) for the full to-do.** User reviewed in-browser and
wants (not yet done): (1) fix the 3 flagged items comprehensively — playwright MCP,
bake test deps into image, geo scale (SQL haversine + more geocoding); (2) REMOVE all
article inline images, keep only the featured image (reverts the inline-image work);
(3) make all images full COLOR (remove grayscale-until-hover everywhere); (4) make
"Local News" copy generic, not Northern-Uganda-specific; (5) fix mobile footer
(duplicate "About Us"); (6) fix mobile masthead stray artifact under the logo;
(7) make the Editorial sidebar genuinely useful (fix GLOBAL vs EAST AFRICA edition);
(8) admin login page left column → white background; (9) surface system-wide SILENT
failures (frontend fetches + bare `except: pass` → log/show); (10) build the 11 branded
CMS-driven email templates per `docs/EMAIL_TEMPLATES_BUILD.md` (Brevo NOT wired — templates
only, reconcile with existing newsletter templates); (11) rebuild fresh + FULL test suite +
verify localhost. Admin login: user `admin` / `admin@northerntimes.local` / pw `changeme123`
(default; set ADMIN_PASSWORD env to override). Execute all of this next.

## Current task
Big multi-phase pass: audit-driven fixes + geo feature. Phases:
- P1 Mailpit dev inbox — DONE. docker-compose.mailpit.yml overlay adds axllent/mailpit
  (UI :8026, SMTP mailpit:1025); overlay overrides MAIL_HOST/PORT for app services so
  ALL email is viewable/testable. NOT for VPS (postfix `mail` there). Run with
  `-f docker-compose.django.yml -f docker-compose.mailpit.yml`.
- P2A Newsletter double opt-in — DONE + tested (14 tests + mailpit e2e). Subscriber
  gains name/confirm_token/confirmed_at/unsubscribed_at + 'pending' status (mig 0003).
  subscribe→confirm email→confirm link (activates, single-use token)→welcome email;
  unsubscribe GET(confirm)→POST(opt-out); resubscribe (one-click via unsub_token);
  re-subscribe of unsubscribed re-opt-ins. Routes under articles_frontend. Email
  templates in templates/newsletter/ (email_base + confirm/welcome/digest/topic_notify);
  digest.html was MISSING (weekly_digest was crashing) — now created.
- P2B email — DONE + tested (5 more tests). Campaign issues now wrapped in email_base
  (branded + unsub footer, per-subscriber) via send_campaign; TopicFollow gains
  unfollow_token (mig 0005 + backfill) + notify_topic_followers task (dedup by email,
  category>tag, tokenized one-click unfollow at /topics/unfollow/<token>/) hooked into
  publish_scheduled + crawler emit_published; contact form queues an admin alert
  (Setting contact_alert_email → highest-role staff → DEFAULT_FROM_EMAIL); per-issue
  test email now actually sends (was a stub). Templates: campaign.html, contact_alert.html,
  topic_unfollow.html.
- P3 UI hardening — DONE. Added the #darkToggle button (moon/sun/system icons) wiring the
  already-complete dark-mode.js three-mode cycle; consolidated the drawer to ONE handler
  in app.js (backdrop+scroll-lock+aria+focus-trap+restore), removed the duplicate inline
  layout.html handler; fixed the dead #headerSearchInput search ref + added Esc/click-
  outside; popup a11y (role=dialog/aria-modal, body scroll-lock, focus-trap, focus-restore
  in popups.js); new cookie-consent.js banner + lightbox.js article-image lightbox (+ CSS).
  NOTE: static uses ManifestStaticFilesStorage+collectstatic on entrypoint → CSS/JS edits
  need `restart django` too (not live from source).
- P4 geo — DONE + tested (13 tests). Coordinate-proximity (haversine) Local News per reader.
  apps/articles/services/geo.py: Uganda GAZETTEER (place→lat/lon, NU-weighted), haversine_km,
  geocode_place/geocode_article/apply_geocode, reader_location(request) (session manual/GPS →
  IP GeoIP). Article gains latitude/longitude/geo_place (mig 0006); backfilled from GPE
  entities via `manage.py geocode_articles` (47/217) + crawler engine hook. New public
  endpoint POST /api/reader-location/ (csrf-exempt; {lat,lon}|{place}|{clear}) stores in
  session; layout.html GPS POST repointed here (was the dead /api/visitor-location). New
  `location` context processor exposes location_mode + reader_location. category() view:
  when slug==local-news and reader loc known, sorts by haversine (nearest first, coordless
  last, then recency); category.html shows a "News near you" banner + region picker.
  location_mode Setting defaults 'auto'.

Earlier same session (committed b03096a): public-pages UX redesign — Local News rename
(mig 0004 + 301 redirect), ai_summary "The Gist" card, compact author card, tightened
comments/spacing, removed "via {source}", italic captions, footer contact block, responsive.
Follow-ups since: removed source-info tooltip button; guarded The Gist to hide when it
duplicates the excerpt. GOTCHA: prod uses cached template loader (APP_DIRS+DEBUG=False)
— template edits need `restart django`; and `up -d` recreates django (new IP) so nginx
`web` 502s until `restart web`.

## Prior task
Comprehensive fix pass across the "5 challenges" (feature, refactor, separation
of concerns, scalability, live debug) on the Django news platform. COMPLETE and
validated at runtime + full test suite.

## Stack
- Backend: Django 5.1 (:8000, gunicorn) + Celery (worker/beat) + Redis
- DB: PostgreSQL 15 with pgvector + pg_trgm (pgvector/pgvector:pg15 image)
- Enrichment: sentence-transformers (all-MiniLM-L6-v2, 384-d), NER, summarizer,
  sentiment, quality, category classifier, dedup, quotes, keywords
- Frontend: Django templates + nginx (:8080)
- Compose file: docker-compose.django.yml (NOT the default filename)
- Tests: pytest + pytest-django (test deps not in prod image — pip install to run)

## Recent decisions (2026-07-11)
- Hybrid search = FTS (weighted STORED search_vector GeneratedField + GIN) +
  trigram (`%` operator, GIN gin_trgm_ops) + semantic (pgvector HNSW), fused via
  Reciprocal Rank Fusion. Semantic ranking is OFF by default on the web path
  (SEARCH_SEMANTIC_ENABLED) because it cold-loads torch (~25s) into gunicorn.
- Search indexes: switched ivfflat → HNSW (no training, healthy on empty table);
  built CONCURRENTLY in a non-atomic migration to avoid table locks.
- CrawlerEngine decomposed: classification.py (GeoClassifier), pipeline.py
  (CrawlPipeline stages), scheduling.py (pure back-off policy), dispatch.py
  (post-publish side-effects). Engine keeps source-level orchestration only.
- Sessions moved to a SEPARATE Redis DB/alias ('sessions', db 1) so the admin
  "flush cache" (FLUSHDBs the default cache) can't wipe sessions / log everyone
  out. SESSION_CACHE_ALIAS='sessions'.
- pgvector VectorExtension added to migration 0001 so the test DB (and any fresh
  DB) gets the `vector` type — previously only the entrypoint created it.
- bleach[css] (tinycss2) required; sanitizer now strips <script>/<style> content
  (not just tags) before bleach.

## Recent decisions (2026-07-11, pt 2 — content quality pass)
- Classification is now TOPIC-PRIMARY (apps.crawler.classification.GeoClassifier):
  Northern Uganda → universal topic (Sports/Business/Health/Tech/etc., +Politics
  for Ugandan) → residual (World/Politics). Fixed the "everything → World" bug
  (World 66→24, Sports 0→54, all topics populated).
- The CPU zero-shot model is unreliable + slow, so keyword hit-counts are the
  primary signal: trust the keyword leader; only call the AI to break a tie
  (~0.6s/article vs ~7-20s before). reclassify_articles reuses GeoClassifier.
- Related articles now rank by embedding cosine similarity (services/related.py),
  not same-category recency.
- Crawled articles are attributed to the admin ("Northern Times Newsroom /
  Editorial Desk"); written articles show the real author. Staff + admin bios
  are seeded (seed_dev_data._seed_test_users / _seed_admin_profile).
- Sources reconciled (manage.py configure_sources): 14 active (10 verified direct
  RSS + 2 allAfrica + Nile Post & Daily Monitor via stealth browser), rest disabled
  with reasons.
- STEALTH BROWSER: BrowserFetcher injects stealth JS (hides navigator.webdriver,
  spoofs WebGL/plugins) + page_load_strategy='eager' + images-off → defeats
  Cloudflare and renders SPAs. feed_type='web' sources use HTMLListingFetcher to
  scrape article links from the rendered index; article title comes from the page
  og:title (listing anchor text is a "news"-badge + headline mess).
- Empty categories (Environment/Opinion/Top Stories) are HIDDEN from nav
  dynamically (context_processors.navigation now requires published articles),
  not deleted — they reappear when populated.

## Security hardening (2026-07-11, audit pass)
- SSRF guard (crawler/fetchers/url_guard.py): all fetches (web/rss/browser) reject
  non-http(s) schemes + private/loopback/link-local/metadata IPs, per redirect hop.
- JSON-LD XSS fixed: views._ldjson_safe escapes < > & U+2028/9 before |safe render
  (crawled titles are untrusted).
- Crawled article body now gets a bleach whitelist pass (core.sanitizer, article
  profile) after the blacklist cleaner — it's rendered with |safe.
- comment_post validates parent_id belongs to the same article.
- Stealth browser reused across a source's items (engine.get_browser) instead of
  one Chrome per article.

## Post-audit fixes (2026-07-11)
- Rate limiting: apps/core/ratelimit.py (cache fixed-window) on comment_post
  (5/min), follow/unfollow (10/min).
- Typo search: _trigram_rank uses word-similarity (<%) with SET LOCAL
  word_similarity_threshold=0.35 — one-word typos now match long headlines
  (musveni→Museveni). Not GIN-index-accelerated (fine at current scale).
- Migration 0003 comment corrected re: the GeneratedField table-rewrite lock.
- Redis password mandatory in production.py (raises ImproperlyConfigured unless
  ALLOW_UNAUTHENTICATED_REDIS=true).
- Rewriter: was 401-spamming with a placeholder OPENAI_API_KEY and marking every
  article 'failed'. Added has_valid_openai_key() gate — process_queue now skips
  cleanly. Reset 183 bogus rewrite_status rows. Articles publish with original
  content regardless (rewrite is an optional enhancement).

## Article/automation status (verified)
- 172/172 published articles fully enriched (content, excerpt, ai_summary, image,
  embedding, category, sentiment, entities). Not truncated — short ones are AJ
  video briefs / BBC sport snippets (genuinely short).
- Automation live: run_crawler every 5min, rewriter every 10min, publish-scheduled
  every 1min (celery beat). Crawls fire automatically (CronRun every 5min).
- NOTE: container clock runs behind host — internally consistent, cosmetic.

## Known issues
- Enrichment model load is heavy (~25s) on cold start.
- OPENAI_API_KEY is a placeholder → rewriter is a no-op (by design now); set a
  real key to enable AI rewrites.
- Test DB needs pip install pytest pytest-django (not in prod image).
- allAfrica rate-limits rapid requests (fine at the 90-min crawl interval).
- Sports skews high (prolific BBC Sport feed) — throttled to max 12/90min.

## Next steps
- Commit the comprehensive fix pass (currently all uncommitted — 16 files).
- (Optional) Rebuild the Django image so bleach[css] is baked in (currently
  pip-installed into the running container).
- (Optional) Backfill embeddings / warm the HNSW index before enabling semantic
  search on the web path.

## Active branches
- main: stable (all changes uncommitted on main)

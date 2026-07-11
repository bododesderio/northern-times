# Project Context
Last updated: 2026-07-11

## Current task
Comprehensive fix pass across the "5 challenges" (feature, refactor, separation
of concerns, scalability, live debug) on the Django news platform. COMPLETE and
validated at runtime + full test suite (499 passed, 0 failed).

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

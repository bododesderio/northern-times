# Crawler refinement plan — dedup + anti-stale (recorded 2026-07-12)

Run on "continue from where we stopped". Decisions LOCKED by the user:
- First-run wipe: **ALL articles + crawl history**, then accept only items published
  in the last **48h**; set a flag so it never re-wipes.
- Best-version pick: **heuristic score + AI tie-break** (AI only for close calls,
  gated behind a valid OpenAI key; heuristic fallback if absent).
- Duplicate losers: **HARD-delete** from the DB (Article + cascaded entities/tags).

## Current state (grounded in code)
4-layer dedup already exists but only REJECTS the newcomer (first-seen wins, quality
ignored):
- `apps/crawler/engine.py` — `dedup_threshold` (CRAWLER_SEMANTIC_DEDUP_THRESHOLD=0.82),
  Layer 1 URL `source_hash` pre-filter (~line 201), gathers 3-day `recent_titles` (~248).
- `apps/crawler/pipeline.py` — `stage_title_dedup` (Layer 3 Jaccard vs recent_titles),
  `stage_embed_and_dedup` (Layer 4 `DuplicateChecker.semantic_check` pgvector cosine).
- `apps/enrichment/dedup.py` — `DuplicateChecker`: `jaccard_similarity`, `is_duplicate`,
  `semantic_check(embedding, threshold)` (returns bool; 3-day window,
  `CosineDistance` < 1-threshold).
- `Article`: `source_url`, `source_hash` (indexed), `embedding`, `quality_score`,
  `word_count`, `featured_image`, `entities`, soft-delete `deleted_at`.
- `apps/crawler/models.py`: `CrawlSource` (has priority?/last_crawled_at), `CrawlLog`.

## A. Cross-source dedup → keep-best, hard-delete-rest
1. `dedup.py`: add `find_duplicate(embedding, title, entities) -> Article|None` that
   RETURNS the matching existing article (widen window ~4 days; combine semantic cosine
   + title Jaccard + entity/GPE overlap so same-story-different-site is caught). Keep
   `semantic_check` as a thin wrapper for back-comrat.
2. New `apps/crawler/services/resolve.py` (or in engine): `resolve_duplicate(new_ctx,
   existing)`:
   - `quality(a)` heuristic = word_count + (has featured_image) + source trust
     (CrawlSource priority) + len(entities) + quotes + recency.
   - if |q(new)-q(existing)| small AND `has_valid_openai_key()` → AI picks the better
     text (reuse rewriter's key gate); else heuristic winner.
   - Winner canonical: take the better image, union tags, append the other outlet to an
     "also reported by" list/field; keep one `story_cluster_id`.
   - If NEW wins: replace existing (update in place OR hard-delete existing + insert new).
     If EXISTING wins: discard new.
3. `purge_duplicate(article)` — transactional HARD delete of the loser (Article.delete()
   cascades ArticleEntity/ArticleTag). Only on confirmed dupes.
4. Wire into `pipeline.stage_embed_and_dedup`: instead of "duplicate → discard", call
   find_duplicate → resolve_duplicate.
5. `manage.py dedupe_articles [--apply]` — one-time sweep of EXISTING rows: cluster by
   embedding cosine + title, keep best per cluster, hard-delete losers. Dry-run default.

## B. Anti-stale — first-run wipe + freshness gate
1. Freshness gate (ALWAYS on): in the pipeline, reject items whose published date is
   older than `CRAWLER_MAX_AGE_HOURS` (settings, default 48). Add near the pre-filter
   stage. This is the "from there, only fresh" rule (currently missing).
2. First-run wipe: `Setting` flag `crawler_initialized`. In `engine.run` (or crawler
   task) at the start: if flag absent → within a transaction hard-delete ALL Article +
   CrawlLog rows (+ any source_hash history), then set `crawler_initialized=1`. Never
   re-wipes. Log the counts.
3. `manage.py crawler_reset --first-run` — explicit wipe + set flag for controlled first
   deploy (so ops can trigger it deliberately); the auto-detect is the safety net.

## Tests to add
- dedup: find_duplicate returns the match; resolve picks higher quality; newcomer-wins
  replaces + hard-deletes old; loser hard-deleted (row gone, entities gone).
- freshness: item older than 48h rejected; within 48h accepted.
- first-run: flag absent → wipe happens once + flag set; flag present → no wipe.
- dedupe_articles command collapses a seeded duplicate pair to one.

## Gotchas
- Hard delete is irreversible + cascades — do it transactionally, only on confirmed
  dupes; log every purge.
- Static/template reload + django_staticfiles volume gotchas apply (see
  [[article-css-cascade-gotcha]]).

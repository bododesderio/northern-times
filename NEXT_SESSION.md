# NEXT SESSION — execute on "continue from where we stopped"

The previous 11-item in-browser review pass is DONE (commits a3c1d56 → 26c535c),
plus admin-credentials rule + light/dark-only theme toggle (9cc1dbe). 575 tests green
on a fresh rebuild.

## Pending: Crawler refinement — dedup + anti-stale
See **`docs/CRAWLER_REFINEMENT_PLAN.md`** for the full grounded plan. Decisions LOCKED:
- First-run: wipe ALL articles + crawl history once (Setting flag `crawler_initialized`),
  then accept only items published in the last **48h**; never re-wipe.
- Same-story-different-site: detect the existing match, keep the **best** version
  (heuristic score + AI tie-break gated behind a valid OpenAI key), **hard-delete** the
  loser (Article + cascaded entities/tags). Every article must be distinctly unique.
- Always-on freshness gate: reject items older than `CRAWLER_MAX_AGE_HOURS` (default 48).
- `manage.py dedupe_articles` (collapse existing dupes) + `crawler_reset --first-run`.

Key files: apps/enrichment/dedup.py (DuplicateChecker), apps/crawler/pipeline.py
(stage_title_dedup, stage_embed_and_dedup), apps/crawler/engine.py (dedup_threshold,
source_hash pre-filter, recent_titles), apps/crawler/models.py (CrawlSource, CrawlLog),
apps/articles/models.py (Article: source_hash, embedding, quality_score, deleted_at).

## Also deferred (from earlier)
- Wiring the new email templates (breaking/trending/account/password-reset/verify/
  dispatch/summary) to actual send paths — that's the Brevo step, intentionally not done.

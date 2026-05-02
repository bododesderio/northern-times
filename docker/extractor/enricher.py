"""
Unified article enrichment orchestrator for The Northern Times.

Single entry point that runs the full enrichment pipeline:
  extract → region-classify → category-classify → dedup → embed →
  summarize → NER → sentiment → quality

Supports both single-article and batch modes:
  - enrich()       → 1 article, returns 1 result
  - enrich_batch() → N articles concurrently, returns N results in 1 HTTP call

Shares parsed text across steps to avoid redundant processing.
"""

import logging
import os
import threading
import traceback
from concurrent.futures import ThreadPoolExecutor, as_completed

from extractor import extract_article
from category_classifier import classify_category
from dedup import check_duplicate

logger = logging.getLogger(__name__)

# Lazy-initialized thread pool for batch processing (fork-safe for multi-worker uvicorn)
_batch_executor = None
_batch_lock = threading.Lock()

def _get_batch_executor() -> ThreadPoolExecutor:
    global _batch_executor
    if _batch_executor is None:
        with _batch_lock:
            if _batch_executor is None:
                _batch_executor = ThreadPoolExecutor(max_workers=int(os.getenv("EXTRACTOR_BATCH_WORKERS", "4")))
    return _batch_executor


def enrich(
    url: str,
    source_selectors: str | None = None,
    strip_selectors: str | None = None,
    title: str | None = None,
    rss_categories: list[str] | None = None,
    system_categories: list[str] | None = None,
    source_region: str = "international",
    existing_titles: list[str] | None = None,
    dedup_threshold: float = 0.55,
    options: dict | None = None,
) -> dict:
    """
    Run the full enrichment pipeline for a single article URL.

    Options dict can contain feature flags:
        embed: bool (default True)
        summarize: bool (default True)
        ner: bool (default True)
        sentiment: bool (default True)
        quality: bool (default True)
        classify_region: bool (default True)
        classify_category: bool (default True)
        check_dedup: bool (default True)

    Returns a dict with all enrichment results.
    """
    rss_categories = rss_categories or []
    system_categories = system_categories or []
    existing_titles = existing_titles or []
    opts = options or {}

    result = {
        "success": False,
        "error": None,
        "extraction": None,
        "region_classify": None,
        "category": None,
        "dedup": None,
        "embedding": None,
        "summary": None,
        "entities": None,
        "sentiment": None,
        "sentiment_score": None,
        "quality_score": None,
    }

    # ── Step 1: Extract article ───────────────────────────────
    try:
        extracted = extract_article(url, source_selectors, strip_selectors)
        if extracted is None:
            result["error"] = "Extraction failed or content too short"
            return result
        result["extraction"] = extracted
        result["success"] = True
    except Exception as e:
        logger.error("Extraction error for %s: %s\n%s", url, e, traceback.format_exc())
        result["error"] = f"Extraction error: {type(e).__name__}"
        return result

    # Shared text for downstream steps
    article_text = extracted.get("text", "") or ""
    article_title = title or extracted.get("title", "") or ""
    article_content = extracted.get("content", "") or ""
    full_text = f"{article_title}. {article_text}"

    # ── Step 2: Region classification ─────────────────────────
    if opts.get("classify_region", True) and source_region != "ugandan":
        try:
            from classifier import classify_article

            region_result = classify_article(
                article_title,
                article_text[:500] if article_text else "",
                source_region,
            )
            result["region_classify"] = region_result
        except Exception as e:
            logger.warning("Region classification failed: %s", e)

    # ── Step 3: Category classification ───────────────────────
    if opts.get("classify_category", True) and system_categories:
        try:
            cat_result = classify_category(
                title=article_title,
                content=article_text[:500] if article_text else "",
                rss_categories=rss_categories,
                url=url,
                system_categories=system_categories,
            )
            result["category"] = cat_result
        except Exception as e:
            logger.warning("Category classification failed: %s", e)

    # ── Step 4: Dedup check ───────────────────────────────────
    if opts.get("check_dedup", True) and existing_titles:
        try:
            dedup_result = check_duplicate(
                article_title, existing_titles, dedup_threshold
            )
            result["dedup"] = dedup_result
        except Exception as e:
            logger.warning("Dedup check failed: %s", e)

    # ── Step 5: Generate embedding ────────────────────────────
    if opts.get("embed", True):
        try:
            from embedder import generate_embedding

            embedding = generate_embedding(full_text)
            result["embedding"] = embedding
        except Exception as e:
            logger.warning("Embedding generation failed: %s", e)

    # ── Step 6: Summarize ─────────────────────────────────────
    if opts.get("summarize", True) and len(article_text) > 500:
        try:
            from summarizer import summarize

            summary = summarize(article_text)
            result["summary"] = summary
        except Exception as e:
            logger.warning("Summarization failed: %s", e)

    # ── Step 7: NER ───────────────────────────────────────────
    entities = None
    if opts.get("ner", True):
        try:
            from ner import extract_entities

            entities = extract_entities(article_text)
            result["entities"] = entities
        except Exception as e:
            logger.warning("NER failed: %s", e)

    # ── Step 8: Sentiment ─────────────────────────────────────
    if opts.get("sentiment", True):
        try:
            from sentiment import analyze_sentiment

            sent_result = analyze_sentiment(full_text)
            result["sentiment"] = sent_result.get("sentiment")
            result["sentiment_score"] = sent_result.get("sentiment_score")
        except Exception as e:
            logger.warning("Sentiment analysis failed: %s", e)

    # ── Step 9: Quality score ─────────────────────────────────
    if opts.get("quality", True):
        try:
            from quality import score_quality

            entity_count = len(entities) if entities else 0
            image_count = len(extracted.get("images", []))
            q_score = score_quality(
                text=article_text,
                html=article_content,
                image_count=image_count,
                entity_count=entity_count,
            )
            result["quality_score"] = q_score
        except Exception as e:
            logger.warning("Quality scoring failed: %s", e)

    return result


def enrich_batch(
    articles: list[dict],
    source_selectors: str | None = None,
    strip_selectors: str | None = None,
    system_categories: list[str] | None = None,
    source_region: str = "international",
    existing_titles: list[str] | None = None,
    dedup_threshold: float = 0.55,
    options: dict | None = None,
) -> list[dict]:
    """
    Enrich multiple articles concurrently. One HTTP call from PHP → N results back.

    Each article dict should have:
        url: str (required)
        title: str
        rss_categories: list[str]

    Shared params (source_selectors, strip_selectors, system_categories, etc.)
    apply to all articles in the batch (they come from the same crawl source).

    The existing_titles list grows as articles are processed — each successfully
    extracted title is appended so later articles in the batch can dedup against
    earlier ones.

    Returns a list of enrichment result dicts in the same order as input.
    """
    system_categories = system_categories or []
    existing_titles = list(existing_titles or [])  # copy so we can mutate
    _titles_lock = threading.Lock()
    opts = options or {}

    results: list[dict | None] = [None] * len(articles)

    def _process_one(idx: int, article: dict) -> tuple[int, dict]:
        """Process a single article. Returns (index, result)."""
        # Thread-safe snapshot of existing_titles
        with _titles_lock:
            titles_snapshot = list(existing_titles)

        r = enrich(
            url=article["url"],
            source_selectors=source_selectors,
            strip_selectors=strip_selectors,
            title=article.get("title"),
            rss_categories=article.get("rss_categories", []),
            system_categories=system_categories,
            source_region=source_region,
            existing_titles=titles_snapshot,
            dedup_threshold=dedup_threshold,
            options=opts,
        )
        return (idx, r)

    # Submit all articles to thread pool
    futures = {}
    for i, article in enumerate(articles):
        future = _get_batch_executor().submit(_process_one, i, article)
        futures[future] = i

    # Collect results as they complete
    for future in as_completed(futures):
        try:
            idx, result = future.result(timeout=60)
            results[idx] = result

            # Thread-safe append of extracted title for cross-dedup within batch
            if result.get("success"):
                extracted_title = (
                    result.get("extraction", {}).get("title")
                    or articles[idx].get("title")
                )
                if extracted_title:
                    with _titles_lock:
                        existing_titles.append(extracted_title)
        except Exception as e:
            idx = futures[future]
            logger.error("Batch enrich failed for article %d: %s", idx, e)
            results[idx] = {
                "success": False,
                "error": f"Batch processing error: {type(e).__name__}",
                "extraction": None,
                "region_classify": None,
                "category": None,
                "dedup": None,
                "embedding": None,
                "summary": None,
                "entities": None,
                "sentiment": None,
                "sentiment_score": None,
                "quality_score": None,
            }

    # Post-completion intra-batch dedup: detect duplicates that slipped through
    # the concurrent race window (both processed before either appended its title)
    seen_titles: list[str] = []
    for idx, r in enumerate(results):
        if r and r.get("success"):
            title = (
                r.get("extraction", {}).get("title")
                or articles[idx].get("title", "")
            )
            if title:
                from dedup import titles_are_similar
                is_dup = False
                for seen in seen_titles:
                    if titles_are_similar(title, seen, dedup_threshold or 0.55):
                        is_dup = True
                        break
                if is_dup:
                    r["dedup"] = {"is_duplicate": True, "reason": "intra-batch-dedup"}
                else:
                    seen_titles.append(title)

    return results

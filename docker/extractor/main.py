"""
Article Extractor Microservice

FastAPI app providing endpoints:
  GET  /health          - Health check with model status
  POST /extract         - Extract a single article
  POST /extract-batch   - Extract multiple articles
  POST /classify        - Region-based relevance classification
  POST /classify-category - AI category classification
  POST /check-duplicate - Cross-source dedup
  POST /embed           - Generate sentence embedding
  POST /summarize       - Generate article summary
  POST /ner             - Named entity recognition
  POST /sentiment       - Sentiment analysis
  POST /quality-score   - Content quality scoring
  POST /enrich          - Unified enrichment pipeline (all-in-one)
"""

import logging
import os
import traceback

logging.basicConfig(
    level=getattr(logging, os.getenv("EXTRACTOR_LOG_LEVEL", "INFO").upper(), logging.INFO),
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)

from fastapi import FastAPI
from models import (
    ExtractRequest, BatchExtractRequest,
    ExtractResponse, BatchExtractResponse,
    ExtractedArticle,
    ClassifyRequest, ClassifyResponse,
    CategoryClassifyRequest, CategoryClassifyResponse,
    DedupRequest, DedupResponse,
    EmbedRequest, EmbedResponse,
    SummarizeRequest, SummarizeResponse,
    NERRequest, NERResponse, EntityItem,
    SentimentRequest, SentimentResponse,
    QualityRequest, QualityResponse,
    EnrichRequest, EnrichResponse,
    EnrichBatchRequest, EnrichBatchResponse,
)
from extractor import extract_article
from browser_extractor import extract_article_browser
from classifier import classify_article
from category_classifier import classify_category
from dedup import check_duplicate

logger = logging.getLogger(__name__)

app = FastAPI(title="Article Extractor", version="2.0.0")

# ── Track readiness for health checks ────────────────────────
_ready = False


@app.on_event("startup")
def preload_models():
    """Load all ML models at startup so first requests aren't slow."""
    global _ready
    from model_registry import get_model, _LOADERS
    for name in _LOADERS:
        try:
            get_model(name)
        except Exception as e:
            logger.error("Failed to preload model '%s': %s", name, e)
    _ready = True
    logger.info("All models preloaded — extractor ready.")


@app.on_event("shutdown")
def graceful_shutdown():
    """Log shutdown for observability; in-flight requests finish via uvicorn."""
    global _ready
    _ready = False
    logger.info("Extractor shutting down gracefully.")


@app.get("/health")
def health():
    from model_registry import _models, _LOADERS
    from fastapi.responses import JSONResponse

    model_status = {}
    all_loaded = True
    for name in _LOADERS:
        loaded = name in _models
        model_status[name] = "loaded" if loaded else "not_loaded"
        if not loaded:
            all_loaded = False

    if not _ready or not all_loaded:
        return JSONResponse(
            status_code=503,
            content={"status": "not_ready", "version": "2.0.0", "models": model_status},
        )

    return {"status": "ok", "version": "2.0.0", "models": model_status}


# ── Extraction ────────────────────────────────────────────────

@app.post("/extract-browser", response_model=ExtractResponse)
def extract_browser(req: ExtractRequest):
    """Extract article using headless Chromium (for Cloudflare/JS-protected sites)."""
    try:
        result = extract_article_browser(req.url, req.source_selectors, req.strip_selectors)
        if result is None:
            return ExtractResponse(success=False, error="Browser extraction failed or content too short")
        return ExtractResponse(success=True, data=ExtractedArticle(**result))
    except Exception as e:
        safe_url = str(req.url).replace('\n', '').replace('\r', '')[:500]
        logger.error("Browser extract failed for %s: %s\n%s", safe_url, e, traceback.format_exc())
        return ExtractResponse(success=False, error=f"Browser extraction error: {type(e).__name__}")


@app.post("/extract", response_model=ExtractResponse)
def extract(req: ExtractRequest):
    try:
        result = extract_article(req.url, req.source_selectors, req.strip_selectors)
        if result is None:
            return ExtractResponse(success=False, error="Extraction failed or content too short")
        return ExtractResponse(success=True, data=ExtractedArticle(**result))
    except Exception as e:
        safe_url = str(req.url).replace('\n', '').replace('\r', '')[:500]
        logger.error("Extract failed for %s: %s\n%s", safe_url, e, traceback.format_exc())
        return ExtractResponse(success=False, error=f"Extraction error: {type(e).__name__}")


@app.post("/extract-batch", response_model=BatchExtractResponse)
def extract_batch(req: BatchExtractRequest):
    results = []
    for url in req.urls:
        try:
            result = extract_article(url, req.source_selectors, req.strip_selectors)
            if result is None:
                results.append(ExtractResponse(success=False, error="Extraction failed"))
            else:
                results.append(ExtractResponse(success=True, data=ExtractedArticle(**result)))
        except Exception as e:
            safe_url = str(url).replace('\n', '').replace('\r', '')[:500]
            logger.error("Batch extract error for %s: %s", safe_url, e)
            results.append(ExtractResponse(success=False, error=f"Error: {type(e).__name__}"))
    return BatchExtractResponse(results=results)


# ── Classification ────────────────────────────────────────────

@app.post("/classify", response_model=ClassifyResponse)
def classify(req: ClassifyRequest):
    try:
        result = classify_article(req.title, req.excerpt, req.source_region)
        return ClassifyResponse(**result)
    except Exception as e:
        logger.error("Classify failed: %s\n%s", e, traceback.format_exc())
        return ClassifyResponse(dominated_region="unknown", relevance_score=0, is_sports=False, accept=False)


@app.post("/classify-category", response_model=CategoryClassifyResponse)
def classify_cat(req: CategoryClassifyRequest):
    try:
        result = classify_category(
            title=req.title,
            content=req.content,
            rss_categories=req.rss_categories,
            url=req.url,
            system_categories=req.system_categories if req.system_categories else None,
        )
        return CategoryClassifyResponse(**result)
    except Exception as e:
        logger.error("Classify-category failed: %s\n%s", e, traceback.format_exc())
        return CategoryClassifyResponse(category_slug="world", confidence=0, ai_scores={}, method="error", conflict_guard=False)


# ── Deduplication ─────────────────────────────────────────────

@app.post("/check-duplicate", response_model=DedupResponse)
def check_dup(req: DedupRequest):
    try:
        result = check_duplicate(req.title, req.existing_titles, req.threshold)
        return DedupResponse(**result)
    except Exception as e:
        logger.error("Check-duplicate failed: %s\n%s", e, traceback.format_exc())
        return DedupResponse(is_duplicate=False, matched_title=None, similarity=0.0, fingerprint="")


# ── Embedding ─────────────────────────────────────────────────

@app.post("/embed", response_model=EmbedResponse)
def embed(req: EmbedRequest):
    from embedder import generate_embedding
    embedding = generate_embedding(req.text)
    if embedding is None:
        return EmbedResponse(embedding=[])
    return EmbedResponse(embedding=embedding)


# ── Summarization ─────────────────────────────────────────────

@app.post("/summarize", response_model=SummarizeResponse)
def summarize_endpoint(req: SummarizeRequest):
    from summarizer import summarize
    summary = summarize(req.text, max_length=req.max_length, min_length=req.min_length)
    return SummarizeResponse(summary=summary)


# ── NER ───────────────────────────────────────────────────────

@app.post("/ner", response_model=NERResponse)
def ner_endpoint(req: NERRequest):
    from ner import extract_entities
    entities = extract_entities(req.text, max_entities=req.max_entities)
    if entities is None:
        return NERResponse(entities=[])
    return NERResponse(entities=[EntityItem(**e) for e in entities])


# ── Sentiment ─────────────────────────────────────────────────

@app.post("/sentiment", response_model=SentimentResponse)
def sentiment_endpoint(req: SentimentRequest):
    from sentiment import analyze_sentiment
    result = analyze_sentiment(req.text)
    return SentimentResponse(**result)


# ── Quality Score ─────────────────────────────────────────────

@app.post("/quality-score", response_model=QualityResponse)
def quality_endpoint(req: QualityRequest):
    from quality import score_quality
    score = score_quality(
        text=req.text,
        html=req.html,
        image_count=req.image_count,
        entity_count=req.entity_count,
    )
    return QualityResponse(quality_score=score)


# ── Unified Enrich ────────────────────────────────────────────

@app.post("/enrich", response_model=EnrichResponse)
def enrich_endpoint(req: EnrichRequest):
    from enricher import enrich
    result = enrich(
        url=req.url,
        source_selectors=req.source_selectors,
        strip_selectors=req.strip_selectors,
        title=req.title,
        rss_categories=req.rss_categories,
        system_categories=req.system_categories,
        source_region=req.source_region,
        existing_titles=req.existing_titles,
        dedup_threshold=req.dedup_threshold,
        options=req.options,
    )

    return _build_enrich_response(result)


# ── Batch Enrich ─────────────────────────────────────────────

def _build_enrich_response(result: dict) -> EnrichResponse:
    """Convert a raw enrichment result dict to an EnrichResponse model."""
    extraction = None
    if result.get("extraction"):
        extraction = ExtractedArticle(**result["extraction"])

    region_classify = None
    if result.get("region_classify"):
        region_classify = ClassifyResponse(**result["region_classify"])

    category = None
    if result.get("category"):
        category = CategoryClassifyResponse(**result["category"])

    dedup = None
    if result.get("dedup"):
        dedup = DedupResponse(**result["dedup"])

    entities = None
    if result.get("entities"):
        entities = [EntityItem(**e) for e in result["entities"]]

    return EnrichResponse(
        success=result.get("success", False),
        error=result.get("error"),
        extraction=extraction,
        region_classify=region_classify,
        category=category,
        dedup=dedup,
        embedding=result.get("embedding"),
        summary=result.get("summary"),
        entities=entities,
        sentiment=result.get("sentiment"),
        sentiment_score=result.get("sentiment_score"),
        quality_score=result.get("quality_score"),
    )


@app.post("/enrich-batch", response_model=EnrichBatchResponse)
def enrich_batch_endpoint(req: EnrichBatchRequest):
    from enricher import enrich_batch

    articles = [
        {"url": a.url, "title": a.title, "rss_categories": a.rss_categories}
        for a in req.articles
    ]

    raw_results = enrich_batch(
        articles=articles,
        source_selectors=req.source_selectors,
        strip_selectors=req.strip_selectors,
        system_categories=req.system_categories,
        source_region=req.source_region,
        existing_titles=req.existing_titles,
        dedup_threshold=req.dedup_threshold,
        options=req.options,
    )

    return EnrichBatchResponse(
        results=[_build_enrich_response(r) for r in raw_results]
    )

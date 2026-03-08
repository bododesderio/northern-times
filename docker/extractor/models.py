from pydantic import BaseModel, Field
from typing import Optional


# ── Extraction ────────────────────────────────────────────────

class ExtractRequest(BaseModel):
    url: str
    source_selectors: Optional[str] = None
    strip_selectors: Optional[str] = None


class BatchExtractRequest(BaseModel):
    urls: list[str] = Field(..., max_length=100)
    source_selectors: Optional[str] = None
    strip_selectors: Optional[str] = None


class ExtractedArticle(BaseModel):
    title: Optional[str] = None
    authors: list[str] = []
    published_date: Optional[str] = None
    content: Optional[str] = None
    text: Optional[str] = None
    excerpt: Optional[str] = None
    hero_image: Optional[str] = None
    images: list[str] = []
    text_length: int = 0
    source_domain: Optional[str] = None
    language: Optional[str] = None
    extraction_method: Optional[str] = None
    truncated: bool = False
    paywall_detected: bool = False


class ExtractResponse(BaseModel):
    success: bool
    data: Optional[ExtractedArticle] = None
    error: Optional[str] = None


class BatchExtractResponse(BaseModel):
    results: list[ExtractResponse]


# ── Region Classification ────────────────────────────────────

class ClassifyRequest(BaseModel):
    title: str
    excerpt: str = ""
    source_region: str = "international"


class ClassifyResponse(BaseModel):
    dominated_region: str
    relevance_score: int
    is_sports: bool
    accept: bool


# ── Category Classification ──────────────────────────────────

class CategoryClassifyRequest(BaseModel):
    title: str
    content: str = ""
    rss_categories: list[str] = []
    url: str = ""
    system_categories: list[str] = []


class CategoryClassifyResponse(BaseModel):
    category_slug: str
    confidence: int
    ai_scores: dict[str, float] = {}
    method: str = "ai+keywords+rss"
    conflict_guard: bool = False


# ── Deduplication ─────────────────────────────────────────────

class DedupRequest(BaseModel):
    title: str
    existing_titles: list[str]
    threshold: float = 0.55


class DedupResponse(BaseModel):
    is_duplicate: bool
    matched_title: Optional[str] = None
    similarity: float = 0.0
    fingerprint: str = ""


# ── Embedding ─────────────────────────────────────────────────

class EmbedRequest(BaseModel):
    text: str = Field(..., max_length=50000)


class EmbedResponse(BaseModel):
    embedding: list[float]


# ── Summarization ─────────────────────────────────────────────

class SummarizeRequest(BaseModel):
    text: str = Field(..., max_length=100000)
    max_length: int = Field(default=130, ge=10, le=500)
    min_length: int = Field(default=30, ge=10, le=200)


class SummarizeResponse(BaseModel):
    summary: Optional[str] = None


# ── NER ───────────────────────────────────────────────────────

class EntityItem(BaseModel):
    text: str
    type: str
    salience: float = 0.0


class NERRequest(BaseModel):
    text: str = Field(..., max_length=100000)
    max_entities: int = Field(default=20, ge=1, le=100)


class NERResponse(BaseModel):
    entities: list[EntityItem] = []


# ── Sentiment ─────────────────────────────────────────────────

class SentimentRequest(BaseModel):
    text: str = Field(..., max_length=50000)


class SentimentResponse(BaseModel):
    sentiment: str = "neutral"
    sentiment_score: float = 0.5


# ── Quality ───────────────────────────────────────────────────

class QualityRequest(BaseModel):
    text: str = Field(..., max_length=100000)
    html: str = Field(default="", max_length=500000)
    image_count: int = Field(default=0, ge=0, le=1000)
    entity_count: int = Field(default=0, ge=0, le=1000)


class QualityResponse(BaseModel):
    quality_score: int = 0


# ── Unified Enrich ────────────────────────────────────────────

class EnrichRequest(BaseModel):
    url: str
    source_selectors: Optional[str] = None
    strip_selectors: Optional[str] = None
    title: Optional[str] = None
    rss_categories: list[str] = []
    system_categories: list[str] = []
    source_region: str = "international"
    existing_titles: list[str] = []
    dedup_threshold: float = 0.55
    options: dict = {}


class EnrichBatchArticle(BaseModel):
    url: str
    title: Optional[str] = None
    rss_categories: list[str] = []


class EnrichBatchRequest(BaseModel):
    articles: list[EnrichBatchArticle] = Field(..., max_length=100)
    source_selectors: Optional[str] = None
    strip_selectors: Optional[str] = None
    system_categories: list[str] = []
    source_region: str = "international"
    existing_titles: list[str] = []
    dedup_threshold: float = 0.55
    options: dict = {}


class EnrichResponse(BaseModel):
    success: bool
    error: Optional[str] = None
    # Extraction
    extraction: Optional[ExtractedArticle] = None
    # Region classification
    region_classify: Optional[ClassifyResponse] = None
    # Category classification
    category: Optional[CategoryClassifyResponse] = None
    # Dedup
    dedup: Optional[DedupResponse] = None
    # Embedding (384-dim vector)
    embedding: Optional[list[float]] = None
    # Summary
    summary: Optional[str] = None
    # NER entities
    entities: Optional[list[EntityItem]] = None
    # Sentiment
    sentiment: Optional[str] = None
    sentiment_score: Optional[float] = None
    # Quality
    quality_score: Optional[int] = None


class EnrichBatchResponse(BaseModel):
    results: list[EnrichResponse]

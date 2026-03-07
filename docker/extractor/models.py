from pydantic import BaseModel, HttpUrl
from typing import Optional


class ExtractRequest(BaseModel):
    url: str
    source_selectors: Optional[str] = None


class BatchExtractRequest(BaseModel):
    urls: list[str]
    source_selectors: Optional[str] = None


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


class ExtractResponse(BaseModel):
    success: bool
    data: Optional[ExtractedArticle] = None
    error: Optional[str] = None


class BatchExtractResponse(BaseModel):
    results: list[ExtractResponse]


class ClassifyRequest(BaseModel):
    title: str
    excerpt: str = ""
    source_region: str = "international"


class ClassifyResponse(BaseModel):
    dominated_region: str
    relevance_score: int
    is_sports: bool
    accept: bool

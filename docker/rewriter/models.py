from pydantic import BaseModel
from typing import Optional


class RewriteRequest(BaseModel):
    title: str
    content: str
    excerpt: str = ""
    model: Optional[str] = None
    rules: Optional[str] = None
    max_chunk_words: Optional[int] = 4000


class RewriteResponse(BaseModel):
    success: bool
    title: Optional[str] = None
    content: Optional[str] = None
    excerpt: Optional[str] = None
    word_count: Optional[int] = None
    original_word_count: Optional[int] = None
    model_used: Optional[str] = None
    chunks_processed: Optional[int] = None
    error: Optional[str] = None

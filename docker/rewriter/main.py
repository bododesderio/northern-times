"""
AI Article Rewriter Microservice — The Northern Times

Endpoints:
  GET  /health   - Verify service + Ollama connectivity
  POST /rewrite  - Rewrite a single article via Ollama (supports chunking for long articles)
"""

import logging
import os
import traceback

logging.basicConfig(
    level=getattr(logging, os.getenv("REWRITER_LOG_LEVEL", "INFO").upper(), logging.INFO),
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)

import httpx
from fastapi import FastAPI
from fastapi.responses import JSONResponse

from models import RewriteRequest, RewriteResponse
from rewriter import rewrite_article

logger = logging.getLogger(__name__)

app = FastAPI(title="Article Rewriter", version="2.0.0")

OLLAMA_URL   = os.getenv("OLLAMA_URL", "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "llama3:8b")


@app.get("/health")
def health():
    try:
        resp = httpx.get(f"{OLLAMA_URL}/api/health", timeout=3)
        ollama_ok = resp.status_code == 200
    except Exception:
        ollama_ok = False

    if ollama_ok:
        return {"status": "ok", "version": "2.0.0", "model": OLLAMA_MODEL, "ollama": "connected"}

    return JSONResponse(
        status_code=503,
        content={"status": "degraded", "version": "2.0.0", "model": OLLAMA_MODEL, "ollama": "unreachable"},
    )


@app.post("/rewrite", response_model=RewriteResponse)
def rewrite_endpoint(req: RewriteRequest):
    try:
        result = rewrite_article(
            title=req.title,
            content=req.content,
            excerpt=req.excerpt,
            model=req.model,
            rules=req.rules,
            max_chunk_words=req.max_chunk_words or 4000,
        )
        return RewriteResponse(**result)
    except Exception as e:
        logger.error("Rewrite endpoint error: %s\n%s", e, traceback.format_exc())
        return RewriteResponse(success=False, error=f"Internal error: {type(e).__name__}")

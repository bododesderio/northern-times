"""
Article Extractor Microservice

FastAPI app providing endpoints:
  GET  /health         - Health check
  POST /extract        - Extract a single article
  POST /extract-batch  - Extract multiple articles
"""

from fastapi import FastAPI
from models import (
    ExtractRequest, BatchExtractRequest,
    ExtractResponse, BatchExtractResponse,
    ExtractedArticle,
    ClassifyRequest, ClassifyResponse,
)
from extractor import extract_article
from classifier import classify_article

app = FastAPI(title="Article Extractor", version="1.0.0")


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/extract", response_model=ExtractResponse)
def extract(req: ExtractRequest):
    try:
        result = extract_article(req.url, req.source_selectors)
        if result is None:
            return ExtractResponse(success=False, error="Extraction failed or content too short")
        return ExtractResponse(success=True, data=ExtractedArticle(**result))
    except Exception as e:
        import traceback
        return ExtractResponse(success=False, error=f"{e}\n{traceback.format_exc()}")


@app.post("/classify", response_model=ClassifyResponse)
def classify(req: ClassifyRequest):
    result = classify_article(req.title, req.excerpt, req.source_region)
    return ClassifyResponse(**result)


@app.post("/extract-batch", response_model=BatchExtractResponse)
def extract_batch(req: BatchExtractRequest):
    results = []
    for url in req.urls:
        try:
            result = extract_article(url, req.source_selectors)
            if result is None:
                results.append(ExtractResponse(success=False, error="Extraction failed"))
            else:
                results.append(ExtractResponse(success=True, data=ExtractedArticle(**result)))
        except Exception as e:
            results.append(ExtractResponse(success=False, error=str(e)))
    return BatchExtractResponse(results=results)

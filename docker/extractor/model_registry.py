"""
Thread-safe model registry for The Northern Times extractor.

Centralizes all AI model loading with proper locking to prevent
race conditions when multiple requests try to load models simultaneously.
"""

import logging
import threading
from typing import Any

logger = logging.getLogger(__name__)

_lock = threading.Lock()
_models: dict[str, Any] = {}


def get_model(name: str) -> Any:
    """Get a model by name, loading it on first access (thread-safe)."""
    if name in _models:
        return _models[name]

    with _lock:
        # Double-check after acquiring lock
        if name in _models:
            return _models[name]

        loader = _LOADERS.get(name)
        if not loader:
            raise ValueError(f"Unknown model: {name}")

        logger.info("Loading model '%s'...", name)
        _models[name] = loader()
        logger.info("Model '%s' loaded successfully.", name)
        return _models[name]


def _load_classifier():
    from transformers import pipeline
    return pipeline(
        "zero-shot-classification",
        model="MoritzLaurer/deberta-v3-base-mnli-fever-anli",
        device=-1,
    )


def _load_summarizer():
    from transformers import pipeline
    return pipeline(
        "summarization",
        model="sshleifer/distilbart-cnn-12-6",
        device=-1,
    )


def _load_embedder():
    from sentence_transformers import SentenceTransformer
    return SentenceTransformer("sentence-transformers/all-MiniLM-L6-v2")


def _load_spacy():
    import spacy
    return spacy.load("en_core_web_sm")


_LOADERS = {
    "classifier": _load_classifier,
    "summarizer": _load_summarizer,
    "embedder": _load_embedder,
    "spacy": _load_spacy,
}

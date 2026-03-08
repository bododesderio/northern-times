"""
Sentence embedding generator for The Northern Times.

Uses all-MiniLM-L6-v2 (~80MB) for 384-dimensional embeddings.
Used for semantic deduplication and story clustering via pgvector cosine similarity.
"""

import logging
from typing import Optional

from model_registry import get_model

logger = logging.getLogger(__name__)


def _get_model():
    """Get the sentence transformer via thread-safe model registry."""
    return get_model("embedder")


def generate_embedding(text: str) -> Optional[list[float]]:
    """
    Generate a 384-dimensional normalized embedding for the given text.

    Args:
        text: Input text (title + content recommended)

    Returns:
        List of 384 floats (unit-normalized), or None on failure.
    """
    try:
        model = _get_model()
        # Truncate to ~512 tokens worth of text for speed
        text = text[:2000]
        embedding = model.encode(text, normalize_embeddings=True)
        return embedding.tolist()
    except Exception as e:
        logger.warning("Embedding generation failed: %s", e)
        return None

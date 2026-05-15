import logging
import numpy as np
from sentence_transformers import SentenceTransformer

logger = logging.getLogger(__name__)

_model = None


def _get_model():
    global _model
    if _model is None:
        logger.info("Loading sentence-transformer model: all-MiniLM-L6-v2")
        _model = SentenceTransformer('all-MiniLM-L6-v2')
    return _model


class Embedder:
    """Sentence embedding using all-MiniLM-L6-v2 (384 dimensions)."""

    def embed(self, text: str) -> list[float]:
        """Generate embedding vector for text."""
        model = _get_model()
        # Truncate to first 512 tokens worth of text (~2000 chars)
        text = text[:2000]
        embedding = model.encode(text, normalize_embeddings=True)
        return embedding.tolist()

    def embed_batch(self, texts: list[str]) -> list[list[float]]:
        """Generate embeddings for multiple texts."""
        model = _get_model()
        truncated = [t[:2000] for t in texts]
        embeddings = model.encode(truncated, normalize_embeddings=True, batch_size=32)
        return embeddings.tolist()

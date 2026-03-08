"""
AI summarization for The Northern Times.

Uses distilbart-cnn-12-6 (~1.2GB) to generate 2-3 sentence summaries.
Lazy-loaded on first call to avoid startup delay.
"""

import logging
from typing import Optional

from model_registry import get_model

logger = logging.getLogger(__name__)


def _get_summarizer():
    """Get the summarization pipeline via thread-safe model registry."""
    return get_model("summarizer")


def summarize(text: str, max_length: int = 130, min_length: int = 30) -> Optional[str]:
    """
    Generate a 2-3 sentence summary of the given text.

    Args:
        text: Article text (plain text, not HTML)
        max_length: Maximum summary length in tokens
        min_length: Minimum summary length in tokens

    Returns:
        Summary string, or None on failure.
    """
    if not text or len(text.strip()) < 200:
        return None

    try:
        pipe = _get_summarizer()
        # Truncate input to ~1024 tokens (model limit)
        # Rough estimate: 1 token ≈ 4 chars
        input_text = text[:4000]

        result = pipe(
            input_text,
            max_length=max_length,
            min_length=min_length,
            do_sample=False,
            truncation=True,
        )
        summary = result[0]["summary_text"].strip()
        return summary if summary else None
    except Exception as e:
        logger.warning("Summarization failed: %s", e)
        return None

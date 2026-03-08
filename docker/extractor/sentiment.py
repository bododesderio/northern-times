"""
Sentiment analysis for The Northern Times.

Reuses the existing DeBERTa zero-shot NLI model from category_classifier.py,
so this adds zero additional memory overhead.
"""

import logging

logger = logging.getLogger(__name__)

SENTIMENT_LABELS = ["positive", "negative", "neutral", "alarming", "hopeful"]


def analyze_sentiment(text: str) -> dict:
    """
    Classify article sentiment using existing DeBERTa zero-shot model.

    Returns:
        {"sentiment": str, "sentiment_score": float}
    """
    try:
        from model_registry import get_model

        clf = get_model("classifier")
        result = clf(
            text[:500],
            candidate_labels=SENTIMENT_LABELS,
            hypothesis_template="The tone of this text is {}.",
            multi_label=False,
        )
        best_label = result["labels"][0]
        best_score = result["scores"][0]
        return {"sentiment": best_label, "sentiment_score": round(best_score, 4)}
    except Exception as e:
        logger.warning("Sentiment analysis failed: %s", e)
        return {"sentiment": "neutral", "sentiment_score": 0.5}

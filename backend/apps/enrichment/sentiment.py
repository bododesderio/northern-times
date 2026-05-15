import logging
from transformers import pipeline

logger = logging.getLogger(__name__)

_pipeline = None
_load_failed = False


def _get_pipeline():
    global _pipeline, _load_failed
    if _load_failed:
        return None
    if _pipeline is None:
        logger.info("Loading zero-shot classification model for sentiment")
        try:
            _pipeline = pipeline(
                "zero-shot-classification",
                model="MoritzLaurer/DeBERTa-v3-base-mnli-fever-anli",
                device=-1,
            )
        except Exception as e:
            logger.warning("Sentiment model unavailable (will use neutral fallback): %s", e)
            _load_failed = True
            return None
    return _pipeline


class SentimentAnalyzer:
    """Sentiment analysis using DeBERTa zero-shot classification."""

    LABELS = ['positive', 'negative', 'neutral']

    def analyze(self, text: str) -> dict:
        """Analyze sentiment. Returns {sentiment: str, score: float}."""
        pipe = _get_pipeline()
        if pipe is None:
            return {'sentiment': 'neutral', 'score': 0.5}
        # Use first 500 chars for efficiency
        text = text[:500]
        try:
            result = pipe(text, candidate_labels=self.LABELS)
            top_label = result['labels'][0]
            top_score = result['scores'][0]
            return {
                'sentiment': top_label,
                'score': round(top_score, 3),
            }
        except Exception as e:
            logger.error(f"Sentiment analysis failed: {e}")
            return {'sentiment': 'neutral', 'score': 0.5}

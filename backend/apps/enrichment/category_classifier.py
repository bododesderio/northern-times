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
        logger.info("Loading zero-shot classification model for categories")
        try:
            _pipeline = pipeline(
                "zero-shot-classification",
                model="MoritzLaurer/DeBERTa-v3-base-mnli-fever-anli",
                device=-1,
            )
        except Exception as e:
            logger.warning("Category classifier model unavailable (will use source default): %s", e)
            _load_failed = True
            return None
    return _pipeline


class CategoryClassifier:
    """AI zero-shot category classification using DeBERTa."""

    def classify(self, title: str, text: str, categories: list[str]) -> tuple[str, float]:
        """Classify article into one of the given categories.

        Returns (category_name, confidence_score).
        Uses title (3x weight) and first 500 chars of text.
        """
        if not categories:
            return ('', 0.0)

        pipe = _get_pipeline()
        if pipe is None:
            return ('', 0.0)
        # Weight title more heavily by repeating it. Keep the input short — CPU
        # zero-shot cost is ~O(tokens^2) per candidate label.
        combined = f"{title}. {title}. {text[:300]}"

        try:
            result = pipe(combined, candidate_labels=categories, multi_label=False)
            top_category = result['labels'][0]
            top_score = result['scores'][0]

            # Only return if confidence >= 0.3 (30%)
            if top_score >= 0.3:
                return (top_category, round(top_score, 3))
            return ('', 0.0)
        except Exception as e:
            logger.error(f"Category classification failed: {e}")
            return ('', 0.0)

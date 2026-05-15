import logging

import torch
from transformers import BartForConditionalGeneration, BartTokenizer

logger = logging.getLogger(__name__)

_model = None
_tokenizer = None


_load_failed = False


def _load_model():
    global _model, _tokenizer, _load_failed
    if _load_failed:
        return None, None
    if _model is None:
        model_name = "sshleifer/distilbart-cnn-12-6"
        logger.info("Loading summarization model: %s", model_name)
        try:
            _tokenizer = BartTokenizer.from_pretrained(model_name)
            _model = BartForConditionalGeneration.from_pretrained(model_name)
            _model.eval()
        except Exception as e:
            logger.warning("Summarization model unavailable (will use excerpt fallback): %s", e)
            _load_failed = True
            return None, None
    return _model, _tokenizer


class Summarizer:
    """Article summarization using DistilBART."""

    def summarize(self, text: str, max_length: int = 130, min_length: int = 30) -> str:
        """Generate summary of text."""
        model, tokenizer = _load_model()
        # DistilBART has 1024 token limit, truncate input
        text = text[:4000]
        if len(text.split()) < min_length:
            return text
        if model is None:
            # Fallback: return first ~130 words as excerpt
            words = text.split()[:max_length]
            return " ".join(words)
        try:
            inputs = tokenizer(text, return_tensors="pt", max_length=1024, truncation=True)
            with torch.no_grad():
                summary_ids = model.generate(
                    inputs["input_ids"],
                    max_length=max_length,
                    min_length=min_length,
                    num_beams=4,
                    length_penalty=2.0,
                )
            return tokenizer.decode(summary_ids[0], skip_special_tokens=True)
        except Exception as e:
            logger.error(f"Summarization failed: {e}")
            return ""

"""
Named Entity Recognition for The Northern Times.

Uses spaCy en_core_web_sm (~12MB) for PERSON, ORG, GPE, EVENT extraction.
Entities are scored by salience (frequency relative to total entity count).
"""

import logging
from typing import Optional

from model_registry import get_model

logger = logging.getLogger(__name__)


def _get_nlp():
    """Get the spaCy NLP model via thread-safe model registry."""
    return get_model("spacy")


ENTITY_TYPES = {"PERSON", "ORG", "GPE", "EVENT"}


def extract_entities(text: str, max_entities: int = 20) -> Optional[list[dict]]:
    """
    Extract named entities with salience scoring.

    Args:
        text: Article text (plain text)
        max_entities: Maximum number of entities to return

    Returns:
        List of {"text": str, "type": str, "salience": float}, or None on failure.
    """
    if not text or len(text.strip()) < 50:
        return None

    try:
        nlp = _get_nlp()
        # Limit input for speed
        doc = nlp(text[:5000])

        entity_counts: dict[tuple[str, str], int] = {}
        for ent in doc.ents:
            if ent.label_ not in ENTITY_TYPES:
                continue
            # Normalize entity text
            entity_text = ent.text.strip()
            if len(entity_text) < 2:
                continue
            key = (entity_text, ent.label_)
            entity_counts[key] = entity_counts.get(key, 0) + 1

        if not entity_counts:
            return []

        total = sum(entity_counts.values())
        entities = []
        for (entity_text, etype), count in entity_counts.items():
            entities.append(
                {
                    "text": entity_text,
                    "type": etype,
                    "salience": round(count / total, 4),
                }
            )

        entities.sort(key=lambda e: e["salience"], reverse=True)
        return entities[:max_entities]
    except Exception as e:
        logger.warning("NER extraction failed: %s", e)
        return None

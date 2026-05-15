import logging
import spacy

logger = logging.getLogger(__name__)

_nlp = None


def _get_nlp():
    global _nlp
    if _nlp is None:
        logger.info("Loading spaCy model: en_core_web_sm")
        _nlp = spacy.load("en_core_web_sm")
    return _nlp


class NERExtractor:
    """Named Entity Recognition using spaCy."""

    ENTITY_MAP = {
        'PERSON': 'person',
        'ORG': 'org',
        'GPE': 'gpe',
        'EVENT': 'event',
        'NORP': 'org',   # nationalities/groups -> org
        'FAC': 'gpe',    # facilities -> gpe
        'LOC': 'gpe',    # locations -> gpe
    }

    def extract(self, text: str) -> list[dict]:
        """Extract named entities from text. Returns list of {text, type, salience}."""
        nlp = _get_nlp()
        # Truncate for performance
        doc = nlp(text[:5000])

        entities = {}
        total_ents = len(doc.ents) or 1

        for ent in doc.ents:
            if ent.label_ not in self.ENTITY_MAP:
                continue
            key = (ent.text.strip(), self.ENTITY_MAP[ent.label_])
            if key in entities:
                entities[key]['count'] += 1
            else:
                entities[key] = {
                    'text': ent.text.strip(),
                    'type': self.ENTITY_MAP[ent.label_],
                    'count': 1,
                }

        # Calculate salience based on frequency
        results = []
        for key, ent in entities.items():
            salience = min(1.0, ent['count'] / total_ents * 3)
            results.append({
                'text': ent['text'],
                'type': ent['type'],
                'salience': round(salience, 3),
            })

        # Sort by salience descending
        results.sort(key=lambda x: x['salience'], reverse=True)
        return results[:20]  # Max 20 entities per article

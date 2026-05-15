"""
Keyword extractor — extracts top keywords/key phrases from article text.

Uses a lightweight TF-based approach (no external models):
1. Tokenize and normalize text
2. Remove stopwords
3. Score by frequency weighted by position (title words get 3x boost)
4. Extract 2-3 word phrases (bigrams/trigrams) that co-occur frequently
"""
import re
import logging
from collections import Counter

logger = logging.getLogger(__name__)

STOPWORDS = frozenset({
    'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
    'of', 'with', 'by', 'from', 'is', 'it', 'its', 'was', 'were', 'be',
    'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
    'would', 'could', 'should', 'may', 'might', 'can', 'shall', 'not',
    'no', 'nor', 'so', 'if', 'then', 'than', 'too', 'very', 'just',
    'about', 'above', 'after', 'again', 'all', 'also', 'any', 'are',
    'as', 'because', 'before', 'between', 'both', 'each', 'few', 'get',
    'got', 'he', 'her', 'here', 'him', 'his', 'how', 'i', 'into',
    'more', 'most', 'my', 'new', 'now', 'only', 'other', 'our', 'out',
    'over', 'own', 'said', 'same', 'she', 'some', 'such', 'that', 'their',
    'them', 'there', 'these', 'they', 'this', 'those', 'through', 'under',
    'up', 'us', 'use', 'want', 'way', 'we', 'well', 'what', 'when',
    'where', 'which', 'while', 'who', 'whom', 'why', 'you', 'your',
    'one', 'two', 'first', 'last', 'many', 'much', 'make', 'like',
    'still', 'even', 'back', 'also', 'made', 'after', 'year', 'years',
    'according', 'however', 'including', 'since', 'says', 'told',
})

WORD_RE = re.compile(r'[a-zA-Z]{3,}')


class KeywordExtractor:
    """Extract top keywords and key phrases from article text."""

    def extract(self, title: str, text: str, max_keywords: int = 10) -> list[dict]:
        """Extract keywords scored by frequency + title boost.

        Returns list of dicts: [{'keyword': '...', 'score': float}]
        """
        title_words = set(w.lower() for w in WORD_RE.findall(title) if w.lower() not in STOPWORDS)
        text_words = [w.lower() for w in WORD_RE.findall(text) if w.lower() not in STOPWORDS]

        if not text_words:
            return []

        # Single word frequencies
        word_freq = Counter(text_words)
        max_freq = max(word_freq.values()) if word_freq else 1

        # Score: normalized frequency + title boost
        scores = {}
        for word, freq in word_freq.items():
            score = freq / max_freq
            if word in title_words:
                score *= 3.0  # title words get 3x boost
            if len(word) >= 5:
                score *= 1.2  # longer words slightly preferred
            scores[word] = round(score, 3)

        # Bigram phrases (2-word combos)
        bigrams = Counter()
        for i in range(len(text_words) - 1):
            w1, w2 = text_words[i], text_words[i + 1]
            if w1 not in STOPWORDS and w2 not in STOPWORDS and len(w1) >= 3 and len(w2) >= 3:
                bigrams[(w1, w2)] += 1

        # Add frequent bigrams as phrases
        for (w1, w2), freq in bigrams.most_common(5):
            if freq >= 2:
                phrase = f'{w1} {w2}'
                phrase_score = (freq / max_freq) * 2.0  # boost phrases
                if w1 in title_words or w2 in title_words:
                    phrase_score *= 2.0
                scores[phrase] = round(phrase_score, 3)

        # Sort by score and return top N
        ranked = sorted(scores.items(), key=lambda x: x[1], reverse=True)
        return [{'keyword': kw, 'score': sc} for kw, sc in ranked[:max_keywords]]

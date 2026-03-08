"""
Cross-source duplicate detection for The Northern Times.

Detects when different sources publish the same story with different
headlines/URLs. Uses title similarity + keyword fingerprinting.

Fast approach (no ML): normalized title comparison + Jaccard similarity
on significant words.
"""

import re
import hashlib
from typing import Optional

# Common words to ignore when fingerprinting
STOP_WORDS = {
    "a", "an", "the", "is", "are", "was", "were", "be", "been", "being",
    "have", "has", "had", "do", "does", "did", "will", "would", "could",
    "should", "may", "might", "shall", "can", "need", "dare", "ought",
    "to", "of", "in", "for", "on", "with", "at", "by", "from", "as",
    "into", "through", "during", "before", "after", "above", "below",
    "between", "out", "off", "over", "under", "again", "further", "then",
    "once", "here", "there", "when", "where", "why", "how", "all", "both",
    "each", "few", "more", "most", "other", "some", "such", "no", "nor",
    "not", "only", "own", "same", "so", "than", "too", "very", "just",
    "but", "and", "or", "if", "it", "its", "this", "that", "these",
    "those", "he", "she", "they", "we", "you", "who", "what", "which",
    "up", "about", "says", "said", "new", "also", "get", "gets", "got",
    "set", "sets", "put", "make", "makes", "made", "take", "takes", "go",
    "goes", "come", "comes", "see", "saw", "know", "known", "think",
    "say", "tell", "told", "find", "give", "use", "report", "reports",
    "according", "amid", "over",
}


def normalize_title(title: str) -> str:
    """Normalize a title for comparison: lowercase, strip punctuation, collapse spaces."""
    t = title.lower().strip()
    # Remove common prefixes like "BREAKING:", "WATCH:", "UPDATED:", etc.
    t = re.sub(r'^(?:breaking|watch|update[d]?|exclusive|just in|alert|live|opinion|editorial|analysis)\s*[:\-|]\s*', '', t, flags=re.IGNORECASE)
    # Remove everything in brackets/parens at end (e.g., "(VIDEO)", "[PHOTOS]")
    t = re.sub(r'\s*[\[\(][^\]\)]*[\]\)]\s*$', '', t)
    # Strip punctuation
    t = re.sub(r'[^\w\s]', ' ', t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t


def _simple_stem(word: str) -> str:
    """Very basic suffix stripping for English news headlines."""
    if len(word) <= 4:
        return word
    # Common suffixes in news headlines
    for suffix in ("ying", "ting", "ning", "ring", "ling", "sing",
                    "ies", "ing", "ers", "ous", "ive", "ion", "ent",
                    "ial", "ful", "ism", "ist", "ity",
                    "ed", "er", "ly", "al", "es"):
        if word.endswith(suffix) and len(word) - len(suffix) >= 3:
            return word[:-len(suffix)]
    if word.endswith("s") and not word.endswith("ss") and len(word) > 4:
        return word[:-1]
    return word


# Common synonyms in news headlines
SYNONYM_MAP = {
    "beat": "defeat", "beats": "defeat", "beaten": "defeat",
    "defeats": "defeat", "defeated": "defeat",
    "wins": "win", "won": "win", "winning": "win",
    "loses": "lose", "lost": "lose", "losing": "lose",
    "falls": "fall", "fell": "fall", "fallen": "fall",
    "kills": "kill", "killed": "kill", "killing": "kill", "slain": "kill",
    "dies": "die", "died": "die", "dead": "die", "death": "die",
    "hits": "hit", "struck": "hit", "strike": "hit", "strikes": "hit",
    "says": "say", "said": "say", "tells": "say", "told": "say",
    "launches": "launch", "launched": "launch",
    "claims": "claim", "claimed": "claim",
    "signs": "sign", "signed": "sign", "signing": "sign",
    "three": "3", "four": "4", "five": "5", "six": "6",
    "seven": "7", "eight": "8", "nine": "9", "ten": "10",
    "nil": "0", "zero": "0",
}


def extract_keywords(text: str) -> set[str]:
    """Extract significant words from text, with basic stemming and synonym resolution."""
    words = re.findall(r'\b[a-z0-9]{2,}\b', text.lower())
    keywords = set()
    for w in words:
        if w in STOP_WORDS:
            continue
        # Apply synonym mapping first
        w = SYNONYM_MAP.get(w, w)
        # Then basic stemming
        w = _simple_stem(w)
        if len(w) >= 2:
            keywords.add(w)
    return keywords


def title_fingerprint(title: str) -> str:
    """Create a fingerprint hash from normalized title keywords."""
    norm = normalize_title(title)
    keywords = sorted(extract_keywords(norm))
    return hashlib.md5(" ".join(keywords).encode()).hexdigest()


def titles_are_similar(title1: str, title2: str, threshold: float = 0.55) -> bool:
    """
    Check if two titles describe the same story using Jaccard similarity
    on significant keywords.

    Default threshold: 0.55 (55% keyword overlap = same story)
    """
    norm1 = normalize_title(title1)
    norm2 = normalize_title(title2)

    # Exact match after normalization
    if norm1 == norm2:
        return True

    kw1 = extract_keywords(norm1)
    kw2 = extract_keywords(norm2)

    if not kw1 or not kw2:
        return False

    intersection = kw1 & kw2
    union = kw1 | kw2

    jaccard = len(intersection) / len(union)
    return jaccard >= threshold


def check_duplicate(
    title: str,
    existing_titles: list[str],
    threshold: float = 0.55,
) -> dict:
    """
    Check if a title is a duplicate of any existing titles.

    Returns:
        {
            "is_duplicate": bool,
            "matched_title": str or None,
            "similarity": float (0-1),
            "fingerprint": str,
        }
    """
    fp = title_fingerprint(title)
    norm = normalize_title(title)
    kw_new = extract_keywords(norm)

    best_similarity = 0.0
    best_match = None

    if not kw_new:
        return {
            "is_duplicate": False,
            "matched_title": None,
            "similarity": 0.0,
            "fingerprint": fp,
        }

    for existing in existing_titles:
        norm_ex = normalize_title(existing)

        # Fast exact match
        if norm == norm_ex:
            return {
                "is_duplicate": True,
                "matched_title": existing,
                "similarity": 1.0,
                "fingerprint": fp,
            }

        kw_ex = extract_keywords(norm_ex)
        if not kw_ex:
            continue

        intersection = kw_new & kw_ex
        union = kw_new | kw_ex
        jaccard = len(intersection) / len(union)

        if jaccard > best_similarity:
            best_similarity = jaccard
            best_match = existing

    return {
        "is_duplicate": best_similarity >= threshold,
        "matched_title": best_match if best_similarity >= threshold else None,
        "similarity": round(best_similarity, 4),
        "fingerprint": fp,
    }

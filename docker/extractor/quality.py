"""
Content quality scoring for The Northern Times.

Pure heuristic scoring (0-100) based on text length, paragraph count,
quote presence, source attribution, image count, readability, and entity density.
No ML models — zero additional memory.
"""

import re


def score_quality(
    text: str,
    html: str = "",
    image_count: int = 0,
    entity_count: int = 0,
) -> int:
    """
    Score article quality 0-100 based on heuristics.

    Components:
      - Text length: max 25 pts
      - Paragraph count: max 15 pts
      - Quote presence: 10 pts
      - Source attribution: 10 pts
      - Image count: max 10 pts
      - Readability (sentence length): max 15 pts
      - Entity density: max 15 pts
    """
    score = 0
    words = len(text.split())

    # Text length (max 25 pts): 500+ words = full marks
    score += min(25, words // 20)

    # Paragraph count (max 15 pts) — count <p> tags from HTML if available, else newlines
    if html:
        paragraphs = max(1, len(re.findall(r"<p[\s>]", html, re.IGNORECASE)))
    else:
        paragraphs = text.count("\n\n") + text.count("\n") // 2 + 1
    score += min(15, paragraphs * 2)

    # Quote presence (10 pts)
    if '"' in text or "\u201c" in text or "\u2018" in text:
        score += 10

    # Source attribution (10 pts)
    attribution_terms = [
        "according to",
        "told",
        "spokesperson",
        "statement",
        "official said",
        "confirmed that",
        "sources say",
    ]
    if any(t in text.lower() for t in attribution_terms):
        score += 10

    # Image count (max 10 pts)
    if image_count == 0 and html:
        image_count = len(re.findall(r"<img\b", html, re.IGNORECASE))
    score += min(10, image_count * 3)

    # Readability — sentence length proxy (max 15 pts)
    sentences = max(1, text.count(".") + text.count("!") + text.count("?"))
    avg_sentence_len = words / sentences
    if 15 <= avg_sentence_len <= 25:
        score += 15  # ideal range
    elif 10 <= avg_sentence_len <= 30:
        score += 10
    else:
        score += 5

    # Entity density bonus (max 15 pts)
    if entity_count > 0:
        density = entity_count / max(1, words / 100)
        score += min(15, int(density * 5))

    return min(100, max(0, score))

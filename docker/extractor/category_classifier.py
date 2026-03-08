"""
AI-powered article category classifier for The Northern Times.

Uses a zero-shot NLI model (DeBERTa) for semantic understanding,
combined with keyword boosts for Uganda-specific terms and
conflict-term guards to prevent war/sports misclassification.

Hybrid scoring: AI (60%) + Keywords (25%) + RSS tags (15%)
"""

import re
import logging
from typing import Optional

from model_registry import get_model

logger = logging.getLogger(__name__)


def _get_classifier():
    """Get the zero-shot classifier via thread-safe model registry."""
    return get_model("classifier")


# ── Category definitions ─────────────────────────────────────

# Maps our DB category slugs to natural language labels the NLI model understands
CATEGORY_LABELS = {
    "sports": "Sports, athletics, football, cricket, tournaments, and competitions",
    "politics": "Politics, government, elections, parliament, and legislation",
    "business": "Business, economy, finance, markets, trade, and investment",
    "health": "Health, medicine, hospitals, diseases, and public health",
    "technology": "Technology, software, AI, internet, gadgets, and innovation",
    "opinion": "Opinion, editorial, commentary, and analysis",
    "world": "World news, international affairs, war, conflicts, and diplomacy",
    "east-africa": "East African regional news from Kenya, Tanzania, Rwanda, and neighboring countries",
    "africa": "African continental news, African Union, and pan-African affairs",
    "northern-uganda": "Northern Uganda regional news, Acholi, Lango, Teso, and Karamoja subregions",
}

# Reverse lookup: label -> slug
_LABEL_TO_SLUG = {v: k for k, v in CATEGORY_LABELS.items()}

# ── Conflict-term guard ──────────────────────────────────────
# When these terms appear, forcefully suppress sports confidence

CONFLICT_TERMS = {
    "war", "troops", "soldiers", "military", "airstrike", "air strike",
    "missile", "ceasefire", "invasion", "casualties", "militia",
    "bombing", "shelling", "insurgent", "rebel", "combat", "battlefield",
    "warzone", "war zone", "artillery", "tank", "infantry", "drone strike",
    "hostage", "hostages", "siege", "ammunition", "weapons", "armed forces",
    "terrorist", "terrorism", "militant", "militants", "guerrilla",
    "hamas", "hezbollah", "isis", "al-qaeda", "al-shabab", "taliban",
    "nato forces", "pentagon", "defense ministry", "killed in action",
    "civilian casualties", "genocide", "ethnic cleansing", "war crime",
    "sanctions", "occupation", "annexed", "annexation", "blockade",
    "nuclear", "chemical weapons", "ballistic", "warhead",
}

# Sports terms that are commonly ambiguous in war contexts
AMBIGUOUS_SPORTS_TERMS = {
    "defeat", "victory", "win", "lost", "draw", "match", "score",
    "team", "coach", "cap", "camp", "final", "season", "injury",
    "attack", "defense", "offensive", "strike", "target", "shot",
    "formation", "division", "captain", "campaign",
}

# ── Uganda-specific keyword boosts ───────────────────────────
# These terms the AI model may not know, so we boost confidence

NORTHERN_UGANDA_TERMS = {
    # Districts (10 pts)
    "gulu", "lira", "soroti", "arua", "kitgum", "pader", "amuru", "nwoya",
    "adjumani", "moyo", "yumbe", "koboko", "nebbi", "zombo", "maracha",
    "pakwach", "apac", "alebtong", "dokolo", "amolatar", "otuke", "kole",
    "oyam", "kwania", "kaberamaido", "katakwi", "napak", "moroto", "kotido",
    "abim", "kaabong", "amudat", "nakapiripirit", "nabilatuk", "agago",
    "lamwo", "omoro",
    # Ethnic/cultural
    "acholi", "langi", "lango", "iteso", "karamojong", "lugbara", "alur", "madi",
    # Institutions
    "gulu university", "lira university", "lacor hospital",
    "ker kwaro", "won nyaci",
    # Key identifiers
    "northern uganda", "karamoja", "lra", "lord's resistance",
}

UGANDA_POLITICS_TERMS = {
    "museveni", "bobi wine", "besigye", "nrm", "fdc", "nup",
    "parliament of uganda", "electoral commission", "state house",
    "updf", "lc5", "lc3", "rdc", "resident district",
}

UGANDA_SPORTS_TERMS = {
    "fufa", "kcca fc", "vipers sc", "sc villa", "express fc",
    "uganda cranes", "she cranes", "usssa",
}


def _count_hits(text: str, terms: set) -> int:
    """Count how many terms from the set appear in text."""
    text_lower = text.lower()
    return sum(1 for t in terms if t in text_lower)


def _has_conflict_context(text: str) -> bool:
    """Check if the article has strong war/conflict context."""
    text_lower = text.lower()
    hits = sum(1 for t in CONFLICT_TERMS if t in text_lower)
    return hits >= 2


def _count_ambiguous_sports(text: str) -> int:
    """Count ambiguous sports terms that could also mean war."""
    text_lower = text.lower()
    return sum(1 for t in AMBIGUOUS_SPORTS_TERMS if t in text_lower)


# ── RSS tag matching ─────────────────────────────────────────

RSS_TAG_MAP = {
    # Direct mappings from common RSS category tags to our slugs
    "sport": "sports", "sports": "sports", "football": "sports",
    "soccer": "sports", "cricket": "sports", "athletics": "sports",
    "politics": "politics", "political": "politics", "government": "politics",
    "election": "politics", "parliament": "politics",
    "business": "business", "economy": "business", "finance": "business",
    "markets": "business", "money": "business",
    "health": "health", "medical": "health", "wellness": "health",
    "technology": "technology", "tech": "technology", "science": "technology",
    "opinion": "opinion", "editorial": "opinion", "commentary": "opinion",
    "op-ed": "opinion", "columns": "opinion",
    "world": "world", "international": "world", "global": "world",
    "africa": "africa", "african": "africa",
    "east africa": "east-africa", "east african": "east-africa",
    "regional": "east-africa",
}


def _rss_tag_score(rss_categories: list[str], system_categories: list[str]) -> dict[str, float]:
    """Score categories based on RSS tags. Returns slug -> confidence (0-1)."""
    scores: dict[str, float] = {}
    if not rss_categories:
        return scores

    for tag in rss_categories:
        tag_lower = tag.strip().lower()
        if not tag_lower:
            continue

        # Direct tag mapping
        if tag_lower in RSS_TAG_MAP:
            slug = RSS_TAG_MAP[tag_lower]
            scores[slug] = max(scores.get(slug, 0), 0.9)
            continue

        # Match against system category names/slugs
        for cat_slug in system_categories:
            cat_lower = cat_slug.lower()
            if tag_lower == cat_lower:
                scores[cat_slug] = max(scores.get(cat_slug, 0), 0.85)
            elif len(tag_lower) > 3 and (tag_lower in cat_lower or cat_lower in tag_lower):
                scores[cat_slug] = max(scores.get(cat_slug, 0), 0.7)

    return scores


# ── Keyword boost scoring ────────────────────────────────────

def _keyword_boost(text: str) -> dict[str, float]:
    """
    Boost scores for Uganda-specific terms the AI model won't know.
    Returns slug -> boost value (0-1).
    """
    boosts: dict[str, float] = {}
    text_lower = text.lower()

    # Northern Uganda detection
    nu_hits = _count_hits(text, NORTHERN_UGANDA_TERMS)
    if nu_hits >= 1:
        boosts["northern-uganda"] = min(1.0, 0.5 + nu_hits * 0.15)

    # Uganda politics
    pol_hits = _count_hits(text, UGANDA_POLITICS_TERMS)
    if pol_hits >= 1:
        boosts["politics"] = min(1.0, 0.3 + pol_hits * 0.15)

    # Uganda sports
    sport_hits = _count_hits(text, UGANDA_SPORTS_TERMS)
    if sport_hits >= 1:
        boosts["sports"] = min(1.0, 0.3 + sport_hits * 0.2)

    return boosts


# ── Main classifier ──────────────────────────────────────────

def classify_category(
    title: str,
    content: str = "",
    rss_categories: Optional[list[str]] = None,
    url: str = "",
    system_categories: Optional[list[str]] = None,
) -> dict:
    """
    Classify an article into one of the system categories.

    Uses hybrid scoring:
      - AI zero-shot classifier: 60% weight (semantic understanding)
      - Keyword boosts: 25% weight (Uganda-specific terms)
      - RSS tag matching: 15% weight (source's own labeling)

    Returns:
        {
            "category_slug": str,        # Best matching category slug
            "confidence": int,           # 0-100 confidence score
            "ai_scores": dict,           # Raw AI model scores per category
            "method": str,               # "ai+keywords+rss" or "keywords_only" (if AI unavailable)
            "conflict_guard": bool,      # Whether conflict guard was triggered
        }
    """
    if system_categories is None:
        system_categories = list(CATEGORY_LABELS.keys())

    rss_categories = rss_categories or []

    # Build input text: title (repeated for emphasis) + truncated content
    # Truncate content to ~500 chars to keep inference fast
    content_excerpt = content[:500] if content else ""
    input_text = f"{title}. {title}. {content_excerpt}".strip()

    if not input_text:
        return {
            "category_slug": "world",
            "confidence": 0,
            "ai_scores": {},
            "method": "fallback",
            "conflict_guard": False,
        }

    # ── Step 1: AI zero-shot classification ──────────────────
    ai_scores: dict[str, float] = {}
    method = "ai+keywords+rss"

    try:
        clf = _get_classifier()
        # Only classify against categories that exist in our system
        active_labels = [
            CATEGORY_LABELS[slug]
            for slug in system_categories
            if slug in CATEGORY_LABELS
        ]

        if active_labels:
            result = clf(
                input_text,
                candidate_labels=active_labels,
                hypothesis_template="This news article is about {}.",
                multi_label=False,
            )
            # Map labels back to slugs
            for label, score in zip(result["labels"], result["scores"]):
                slug = _LABEL_TO_SLUG.get(label, "")
                if slug:
                    ai_scores[slug] = score
    except Exception as e:
        logger.warning("AI classifier failed, falling back to keywords: %s", e)
        method = "keywords_only"

    # ── Step 2: Conflict-term guard ──────────────────────────
    conflict_guard = False
    full_text = f"{title} {content_excerpt}"

    if _has_conflict_context(full_text):
        ambiguous_count = _count_ambiguous_sports(full_text)
        if ambiguous_count >= 2:
            # War article using sports-like language — suppress sports
            conflict_guard = True
            if "sports" in ai_scores:
                ai_scores["sports"] *= 0.15  # Heavily penalize
            # Boost world news
            ai_scores["world"] = min(1.0, ai_scores.get("world", 0) + 0.3)
            logger.info(
                f"Conflict guard triggered for: {title[:80]}... "
                f"(conflict terms found + {ambiguous_count} ambiguous sports terms)"
            )

    # ── Step 3: Keyword boosts (Uganda-specific) ─────────────
    kw_boosts = _keyword_boost(full_text)

    # ── Step 4: RSS tag scores ───────────────────────────────
    rss_scores = _rss_tag_score(rss_categories, system_categories)

    # Apply conflict guard to RSS tags too
    if conflict_guard and "sports" in rss_scores:
        rss_scores["sports"] *= 0.2

    # ── Step 5: Hybrid scoring ───────────────────────────────
    # Weights: AI 60%, Keywords 25%, RSS 15%
    all_slugs = set(list(ai_scores.keys()) + list(kw_boosts.keys()) + list(rss_scores.keys()))
    if not all_slugs:
        all_slugs = set(system_categories)

    final_scores: dict[str, float] = {}
    for slug in all_slugs:
        ai = ai_scores.get(slug, 0.0)
        kw = kw_boosts.get(slug, 0.0)
        rss = rss_scores.get(slug, 0.0)

        if method == "keywords_only":
            # AI unavailable — rely on keywords (70%) + RSS (30%)
            final_scores[slug] = kw * 0.7 + rss * 0.3
        else:
            final_scores[slug] = ai * 0.60 + kw * 0.25 + rss * 0.15

    # ── Step 6: Pick winner ──────────────────────────────────
    if not final_scores:
        return {
            "category_slug": system_categories[0] if system_categories else "world",
            "confidence": 0,
            "ai_scores": ai_scores,
            "method": method,
            "conflict_guard": conflict_guard,
        }

    best_slug = max(final_scores, key=final_scores.get)
    best_score = final_scores[best_slug]

    # Convert to 0-100 confidence
    confidence = int(min(100, best_score * 100))

    return {
        "category_slug": best_slug,
        "confidence": confidence,
        "ai_scores": {k: round(v, 4) for k, v in ai_scores.items()},
        "method": method,
        "conflict_guard": conflict_guard,
    }

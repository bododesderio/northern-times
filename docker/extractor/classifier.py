"""
Article relevance classifier for The Northern Times.

Determines whether an article from a non-Ugandan source is relevant
to the publication's audience (Uganda, East Africa, Africa, world news).

Used by CrawlerEngine to filter out domestic foreign news (e.g. Nigerian
local politics, South African provincial stories) from international sources.
"""

import re

# ── Uganda / Northern Uganda keywords ──────────────────────────
UGANDA_KEYWORDS = {
    # Country & demonyms
    "uganda", "ugandan", "ugandans", "kampala", "entebbe",
    # Northern Uganda (high priority)
    "gulu", "lira", "acholi", "lango", "teso", "karamoja", "arua", "moyo",
    "kitgum", "pader", "amuru", "nwoya", "lamwo", "agago", "otuke", "alebtong",
    "dokolo", "apac", "kole", "oyam", "omoro", "kwania", "amolatar",
    "soroti", "serere", "katakwi", "amuria", "kapelebyong", "kalaki",
    "napak", "moroto", "kotido", "kaabong", "abim", "amudat", "nakapiripirit",
    "koboko", "maracha", "yumbe", "adjumani", "obongi", "madi-okollo",
    "nebbi", "pakwach", "zombo",
    # Major cities/regions
    "jinja", "mbale", "mbarara", "fort portal", "masaka", "hoima",
    "kabale", "kasese", "tororo", "iganga", "mukono", "wakiso",
    # Key institutions & people
    "museveni", "parliament of uganda", "makerere", "bank of uganda",
    "uganda revenue", "ura ", "updf", "nra", "nrm", "fdc",
    "uganda peoples", "uganda police", "iso uganda",
}

EAST_AFRICA_KEYWORDS = {
    "east africa", "east african", "eac ", "kenya", "kenyan", "tanzania",
    "tanzanian", "rwanda", "rwandan", "burundi", "south sudan", "sudanese",
    "congo", "congolese", "drc", "nairobi", "dar es salaam", "kigali",
    "bujumbura", "juba", "arusha", "mombasa", "kisumu",
    "lake victoria", "mt kenya", "kilimanjaro", "rift valley",
}

AFRICA_KEYWORDS = {
    "africa", "african", "african union", "au summit",
    "sahel", "sahara", "sub-saharan", "west africa", "southern africa",
    "horn of africa", "great lakes", "ecowas", "sadc", "igad",
    "ethiopia", "somalia", "eritrea", "sudan", "nigeria", "ghana",
    "south africa", "mozambique", "zimbabwe", "malawi", "zambia",
    "cameroon", "senegal", "ivory coast", "mali", "niger", "chad",
    "libya", "egypt", "morocco", "tunisia", "algeria",
}

WORLD_NEWS_KEYWORDS = {
    # Geopolitics / international affairs
    "united nations", "un ", "nato", "g7", "g20", "imf", "world bank",
    "who ", "unicef", "unesco", "security council",
    "climate change", "global warming", "pandemic", "epidemic",
    "international", "geopolitics", "diplomacy", "sanctions",
    "trade war", "nuclear", "ceasefire", "peace talks",
    # Major world powers
    "united states", "u.s.", "china", "russia", "european union",
    "britain", "france", "india", "japan", "middle east",
    # Global issues
    "refugee", "migration", "humanitarian", "famine", "drought",
    "terrorism", "isis", "al-qaeda", "al-shabab",
    # Sports international
    "world cup", "olympics", "champions league", "premier league",
    "la liga", "serie a", "bundesliga", "afcon",
    "fifa", "caf ", "uefa", "icc cricket",
}

SPORTS_KEYWORDS = {
    "football", "soccer", "cricket", "rugby", "athletics", "marathon",
    "basketball", "tennis", "boxing", "mma", "golf", "swimming",
    "olympics", "world cup", "champions league", "premier league",
    "afcon", "copa america", "euros", "world athletics",
    "fifa", "transfer", "signing", "match", "tournament", "championship",
    "medal", "gold medal", "world record",
}

# Domestic foreign news to reject (not relevant to Ugandan audience)
DOMESTIC_FOREIGN_PATTERNS = [
    # Nigerian domestic
    r"\b(lagos|abuja|enugu|anambra|ogun|oyo|kano|kaduna|plateau|borno)\b.*\b(state|governor|council|ward|lga)\b",
    r"\bnigeria(?:n)?\s+(?:police|army|navy|customs|immigration)\b",
    r"\b(?:pdp|apc|inec|efcc|nafdac)\b",
    # South African domestic
    r"\b(?:gauteng|kwazulu|limpopo|mpumalanga|free state|eastern cape|western cape|northern cape)\b",
    r"\b(?:anc|eff|da )\b.*\b(?:parliament|vote|elect|rally)\b",
    # Kenyan domestic (only filter for international sources, not EA)
    r"\b(?:nairobi county|mombasa county|kiambu|nakuru county)\b.*\b(?:governor|mca|ward)\b",
]
_domestic_patterns = [re.compile(p, re.IGNORECASE) for p in DOMESTIC_FOREIGN_PATTERNS]


def _count_keyword_hits(text: str, keyword_set: set) -> int:
    """Count how many keywords from the set appear in text."""
    text_lower = text.lower()
    return sum(1 for kw in keyword_set if kw in text_lower)


def classify_article(
    title: str,
    excerpt: str = "",
    source_region: str = "international",
) -> dict:
    """
    Classify article relevance for The Northern Times.

    Returns:
        {
            "dominated_region": "uganda" | "east_africa" | "africa" | "world" | "domestic_foreign",
            "relevance_score": 0-100,
            "is_sports": bool,
            "accept": bool,  # Should the crawler keep this article?
        }
    """
    text = f"{title} {excerpt}".strip()
    if not text:
        return {"dominated_region": "unknown", "relevance_score": 0, "is_sports": False, "accept": False}

    # Count hits in each category
    ug_hits = _count_keyword_hits(text, UGANDA_KEYWORDS)
    ea_hits = _count_keyword_hits(text, EAST_AFRICA_KEYWORDS)
    af_hits = _count_keyword_hits(text, AFRICA_KEYWORDS)
    world_hits = _count_keyword_hits(text, WORLD_NEWS_KEYWORDS)
    sports_hits = _count_keyword_hits(text, SPORTS_KEYWORDS)

    is_sports = sports_hits >= 2 or (sports_hits >= 1 and any(
        kw in text.lower() for kw in ("match", "tournament", "league", "cup", "championship", "goal", "score")
    ))

    # Check for domestic foreign content
    is_domestic_foreign = False
    if source_region == "international":
        for pattern in _domestic_patterns:
            if pattern.search(text):
                is_domestic_foreign = True
                break

    # Determine dominated region
    scores = {
        "uganda": ug_hits * 15,
        "east_africa": ea_hits * 8,
        "africa": af_hits * 5,
        "world": world_hits * 4,
    }

    if is_domestic_foreign and ug_hits == 0 and ea_hits == 0:
        dominated = "domestic_foreign"
        relevance = max(10, min(scores.values())) if any(scores.values()) else 0
    elif ug_hits > 0:
        dominated = "uganda"
        relevance = min(100, 50 + ug_hits * 15)
    elif ea_hits > 0:
        dominated = "east_africa"
        relevance = min(100, 40 + ea_hits * 10)
    elif af_hits > 0:
        dominated = "africa"
        relevance = min(100, 30 + af_hits * 8)
    elif world_hits > 0 or is_sports:
        dominated = "world"
        relevance = min(100, 25 + world_hits * 6 + (20 if is_sports else 0))
    else:
        dominated = "unknown"
        relevance = 10

    # Acceptance rules based on source region
    if source_region == "ugandan":
        accept = True  # Ugandan sources: accept everything
    elif source_region == "east_african":
        # EA sources: accept Uganda, EA, Africa, sports, world news
        accept = dominated in ("uganda", "east_africa", "africa", "world") or is_sports
        if dominated == "domestic_foreign":
            accept = False
    else:
        # International sources: reject domestic foreign, accept everything else
        if dominated == "domestic_foreign":
            accept = False
        elif dominated == "unknown" and not is_sports:
            accept = relevance >= 15  # Low-relevance unknown content — borderline
        else:
            accept = True

    # Sports always accepted from any source
    if is_sports:
        accept = True

    return {
        "dominated_region": dominated,
        "relevance_score": relevance,
        "is_sports": is_sports,
        "accept": accept,
    }

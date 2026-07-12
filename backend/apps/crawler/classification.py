"""
Geo-routing + topic classification for crawled articles.

Extracted verbatim from ``CrawlerEngine`` to decouple editorial routing rules
(which change often and are edited by non-pipeline hands) from crawl
orchestration. The keyword tables and pure matchers are module-level — a single
source of truth reused by the ``reclassify_articles`` management command — and
``GeoClassifier`` binds them to the AI topic classifier for full routing.

Behaviour is identical to the pre-refactor engine methods:
``_classify_article`` → ``GeoClassifier.classify``,
``_ai_classify_topic``  → ``GeoClassifier.ai_classify_topic``,
``_count_northern_matches`` → ``count_northern_matches``,
``_keyword_topic_hint`` → ``keyword_topic_hint``.

Django models are imported lazily inside methods so this module stays
importable without app-loading (consistent with ``enrichment/dedup.py``).
"""
import logging
import re

logger = logging.getLogger(__name__)

# Northern Uganda keywords — multi-word phrases first (matched exactly),
# then single-word district/town names (matched with word boundaries).
# Split into PHRASES (substring match safe) and WORDS (need word boundary).
NU_PHRASES = [
    # Unambiguous multi-word phrases (safe for substring match)
    'northern uganda', 'north uganda', 'acholi sub-region', 'lango sub-region',
    'teso sub-region', 'karamoja sub-region', 'west nile sub-region',
    'acholi quarter', 'acholiland',
    'st. mary\'s lacor', 'lacor hospital', 'karuma falls', 'karuma bridge',
    'murchison falls', 'kidepo valley', 'lake kwania', 'lake kyoga',
    'albert nile', 'victoria nile', 'agago river',
    'gulu university', 'lira university', 'soroti university',
    'gulu regional referral', 'lira regional referral',
    'ker kwaro acholi', 'lango cultural foundation', 'iteso cultural union',
    'alur kingdom', 'acholi paramount chief',
    'lord\'s resistance army', 'joseph kony', 'nodding syndrome',
    'nodding disease', 'idp camp', 'amuru land',
    'dokolo district', 'gulu district', 'lira district', 'kitgum district',
    'pader district', 'soroti district', 'moroto district', 'arua district',
]
# Single words — must be matched with word boundaries to avoid false positives
NU_WORDS = [
    # Districts (unique enough names)
    'gulu', 'kitgum', 'pader', 'agago', 'amuru', 'nwoya', 'lamwo', 'omoro',
    'dokolo', 'apac', 'oyam', 'alebtong', 'otuke', 'kole', 'amolatar',
    'soroti', 'serere', 'ngora', 'kumi', 'bukedea', 'katakwi', 'amuria',
    'kapelebyong', 'kalaki',
    'moroto', 'kotido', 'kaabong', 'abim', 'napak', 'amudat', 'nakapiripirit',
    'nabilatuk', 'karenga',
    'nebbi', 'adjumani', 'yumbe', 'koboko', 'maracha', 'pakwach', 'zombo',
    'obongi', 'terego',
    # Sub-region/ethnic (unique to Uganda)
    'acholi', 'lango', 'langi', 'karamoja', 'karimojong', 'karamojong',
    'iteso', 'ateso', 'lugbara',
    # Towns (unique enough)
    'lacor', 'patongo', 'kalongo', 'pajule', 'adilang', 'barlonyo',
    'minakulu', 'iceme', 'aduku', 'namasale', 'obalanga',
    'anaka', 'atiak', 'palabek',
]
# Compile word-boundary regex for single words
NU_WORD_PATTERN = re.compile(
    r'\b(?:' + '|'.join(re.escape(w) for w in NU_WORDS) + r')\b',
    re.IGNORECASE,
)

# Uganda national keywords (not northern-specific)
UGANDA_KEYWORDS = frozenset([
    'uganda', 'ugandan', 'kampala', 'entebbe', 'jinja', 'mbale', 'mbarara',
    'masaka', 'fort portal', 'kabale', 'mukono', 'wakiso', 'mityana',
    'museveni', 'parliament', 'state house', 'statehouse',
    'makerere', 'mulago', 'nakasero', 'kololo', 'buganda',
    'nrm', 'nup', 'fdc', 'dp', 'upc',
    'bobi wine', 'besigye', 'kyagulanyi',
    'updf', 'iso', 'kcca',
    'ugx', 'shillings', 'ushs',
])

# Topic keyword hints — fast keyword check before slow AI classifier
TOPIC_KEYWORDS = {
    'politics': [
        'election', 'parliament', 'president', 'minister', 'government', 'vote',
        'senator', 'congress', 'legislation', 'political', 'democracy', 'campaign',
        'opposition', 'ruling party', 'coalition', 'impeach', 'referendum', 'ballot',
        'diplomatic', 'embassy', 'sanctions', 'foreign affairs', 'treaty', 'summit',
        'cabinet', 'governor', 'mayor', 'constituency', 'manifesto', 'inaugurat',
    ],
    'sports': [
        'football', 'soccer', 'premier league', 'champions league', 'fifa', 'goal',
        'basketball', 'nba', 'cricket', 'rugby', 'athletics', 'olympic', 'marathon',
        'tennis', 'boxing', 'mma', 'ufc', 'wrestling', 'swimming', 'volleyball',
        'transfer', 'signing', 'coach', 'manager', 'stadium', 'playoff', 'semifinal',
        'final score', 'match', 'tournament', 'championship', 'medal', 'world cup',
        'serie a', 'la liga', 'bundesliga', 'epl', 'afcon', 'copa',
    ],
    'business': [
        'economy', 'market', 'stock', 'trade', 'investment', 'revenue', 'profit',
        'inflation', 'gdp', 'budget', 'tax', 'finance', 'banking', 'loan', 'debt',
        'startup', 'entrepreneur', 'ipo', 'merger', 'acquisition', 'corporate',
        'oil price', 'commodity', 'export', 'import', 'tariff', 'supply chain',
        'real estate', 'manufacturing', 'agriculture', 'farming', 'harvest',
        'shilling', 'dollar', 'forex', 'central bank', 'interest rate', 'bonds',
    ],
    'health': [
        'hospital', 'doctor', 'patient', 'disease', 'virus', 'vaccine', 'covid',
        'malaria', 'hiv', 'aids', 'ebola', 'cholera', 'outbreak', 'epidemic',
        'pandemic', 'medicine', 'surgery', 'treatment', 'mental health', 'cancer',
        'maternal', 'child mortality', 'immunization', 'healthcare', 'clinic',
        'pharmaceutical', 'drug', 'diagnosis', 'symptoms', 'public health', 'who',
    ],
    'technology': [
        'artificial intelligence', ' ai ', 'machine learning', 'software', 'tech',
        'app', 'digital', 'cyber', 'internet', 'social media', 'data',
        'innovation', 'robotics', 'blockchain', 'cryptocurrency', 'bitcoin',
        'silicon valley', 'google', 'apple', 'microsoft', 'meta', 'amazon', 'tesla',
        'smartphone', 'gadget', '5g', 'satellite', 'space', 'nasa', 'spacex',
    ],
    'entertainment': [
        'movie', 'film', 'actor', 'actress', 'celebrity', 'music', 'album', 'concert',
        'fashion', 'met gala', 'red carpet', 'designer', 'runway', 'vogue', 'style',
        'grammy', 'oscar', 'emmy', 'award show', 'netflix', 'streaming', 'series',
        'hollywood', 'bollywood', 'nollywood', 'tv show', 'reality tv', 'singer',
        'rapper', 'hip hop', 'pop star', 'k-pop', 'tiktok', 'viral', 'influencer',
        'beauty', 'cosmetics', 'makeup', 'hairstyle', 'modeling', 'supermodel',
        'art', 'gallery', 'exhibition', 'museum', 'theater', 'dance', 'festival',
    ],
    'education': [
        'school', 'university', 'college', 'student', 'teacher', 'education',
        'curriculum', 'exam', 'scholarship', 'graduation', 'literacy', 'enrollment',
        'makerere', 'academic', 'research', 'professor', 'lecture', 'campus',
        'primary school', 'secondary school', 'uce', 'uace', 'uneb',
    ],
    'environment': [
        'climate', 'global warming', 'carbon', 'emission', 'renewable', 'solar',
        'deforestation', 'conservation', 'wildlife', 'endangered', 'pollution',
        'flooding', 'drought', 'earthquake', 'hurricane', 'cyclone', 'wildfire',
        'ecosystem', 'biodiversity', 'sustainability', 'green energy', 'fossil fuel',
    ],
    'crime & security': [
        'murder', 'kill', 'arrest', 'police', 'crime', 'robbery', 'theft', 'fraud',
        'court', 'judge', 'sentence', 'prison', 'jail', 'terrorist', 'terrorism',
        'kidnap', 'suspect', 'investigation', 'detective', 'homicide', 'burglary',
        'drug trafficking', 'smuggling', 'gang', 'violence', 'assault', 'rape',
        'shooting', 'stabbing', 'extortion', 'embezzlement', 'corruption charge',
    ],
    'opinion': [
        'editorial', 'opinion', 'commentary', 'analysis', 'perspective', 'column',
        'op-ed', 'letter to editor', 'viewpoint', 'think tank', 'debate',
    ],
    'lifestyle': [
        'wellness', 'fitness', 'diet', 'recipe', 'cooking', 'food', 'restaurant',
        'travel', 'tourism', 'vacation', 'hotel', 'parenting', 'relationship',
        'wedding', 'real estate', 'home decor', 'gardening', 'pet',
    ],
}

# Map slug-style topic keys to actual Category names
_TOPIC_NAME_MAP = {
    'politics': 'Politics', 'sports': 'Sports', 'business': 'Business',
    'health': 'Health', 'technology': 'Technology', 'entertainment': 'Entertainment',
    'education': 'Education', 'environment': 'Environment',
    'crime & security': 'Crime & Security', 'opinion': 'Opinion',
    'lifestyle': 'Lifestyle',
}


def count_northern_matches(text_lower: str) -> int:
    """Count Northern Uganda keyword matches using word boundaries."""
    # Phrase matches (safe substring)
    phrase_hits = sum(1 for p in NU_PHRASES if p in text_lower)
    # Word matches (word boundary regex)
    word_hits = len(NU_WORD_PATTERN.findall(text_lower))
    return phrase_hits + word_hits


def topic_scores(text_lower: str) -> dict:
    """Return {CategoryName: keyword_hit_count} for every topic with >=1 hit.

    Used to shortlist candidate labels for the (expensive, per-label) zero-shot
    classifier — passing 4-5 plausible topics instead of all ~14 cuts AI latency
    several-fold and sharpens accuracy.
    """
    out = {}
    for slug, keywords in TOPIC_KEYWORDS.items():
        hits = sum(1 for kw in keywords if kw in text_lower)
        if hits:
            out[_TOPIC_NAME_MAP[slug]] = hits
    return out


def uganda_score(text_lower: str) -> int:
    """Count national Uganda keyword matches (word-boundary)."""
    return sum(
        1 for kw in UGANDA_KEYWORDS
        if re.search(r'\b' + re.escape(kw) + r'\b', text_lower)
    )


def keyword_topic_hint(text_lower: str) -> str | None:
    """Fast keyword-based topic detection. Returns Category name or None.

    Requires a clear winner: the top topic must have >=3 hits AND at least 2 more
    hits than the runner-up, so a story that merely mentions a rival topic in
    passing (e.g. a political story name-dropping a stadium) is not miscategorised.
    """
    scores = {}
    for cat_slug, keywords in TOPIC_KEYWORDS.items():
        hits = sum(1 for kw in keywords if kw in text_lower)
        if hits >= 3:
            scores[cat_slug] = hits
    if not scores:
        return None
    ranked = sorted(scores.items(), key=lambda kv: kv[1], reverse=True)
    best_slug, best_hits = ranked[0]
    runner_up = ranked[1][1] if len(ranked) > 1 else 0
    if best_hits - runner_up < 2:
        return None  # ambiguous — defer to the AI classifier
    return _TOPIC_NAME_MAP.get(best_slug)


# Topic categories that apply universally — independent of where a story is from.
# A Nigerian football match and a Ugandan one are both Sports. These are always
# eligible so international feeds (BBC Sport, TechCrunch, WHO...) populate topics
# instead of everything collapsing into "World".
UNIVERSAL_TOPICS = frozenset({
    'Sports', 'Business', 'Health', 'Technology', 'Entertainment',
    'Education', 'Environment', 'Crime & Security', 'Lifestyle',
})
# 'Politics' is treated as *national/African* politics: only eligible for Ugandan
# content, so international political news falls through to World instead.


class GeoClassifier:
    """Route a crawled article to a Category — topic-primary, region-aware.

    Order of precedence:
      1. Northern Uganda   — strong local signal (the site's identity lane).
      2. Universal topic   — Sports / Business / Health / Tech / Entertainment /
                             Education / Environment / Crime & Security / Lifestyle
                             (+ Politics for Ugandan content), via keyword hint
                             then AI zero-shot. Applied to EVERY article.
      3. Residual          — Ugandan-but-no-topic → Politics; international
                             residual (geopolitics/foreign affairs) → World.

    The AI topic classifier is injected so the crawl engine shares its already
    loaded model instance (no second model load).
    """

    def __init__(self, category_classifier):
        self.category_classifier = category_classifier

    def classify(self, source, title, plain_text, category_names):
        """Route article to the correct Category. Signature stable for callers."""
        from apps.articles.models import Category

        text_lower = f"{title} {plain_text[:2000]}".lower()

        # 1. Northern Uganda — a strong local signal wins outright.
        if count_northern_matches(text_lower) >= 3:
            cat = Category.objects.filter(slug='local-news').first()
            if cat:
                return cat

        ugandan = source.region == 'ugandan' or uganda_score(text_lower) >= 2

        # 2. Topic — ALWAYS attempted, so topic categories actually fill up.
        topic = self._classify_topic(title, plain_text, text_lower, category_names, ugandan)
        if topic:
            return topic

        # 3. Residual bucket.
        if ugandan:
            return Category.objects.filter(slug='politics').first() or Category.objects.first()
        return Category.objects.filter(slug='world').first() or Category.objects.first()

    def _classify_topic(self, title, plain_text, text_lower, category_names, ugandan):
        """Keyword hint first, then AI zero-shot. Returns a Category or None.

        Politics is only eligible for Ugandan/African content — an international
        political story returns None here and falls through to World.
        """
        from apps.articles.models import Category

        eligible = set(UNIVERSAL_TOPICS)
        if ugandan:
            eligible.add('Politics')

        # Fast keyword hint (only trust it if it names an eligible topic).
        hint = keyword_topic_hint(text_lower)
        if hint and hint in eligible:
            cat = Category.objects.filter(name=hint).first()
            if cat:
                return cat

        # No clear keyword winner from the strict hint. Fall back to raw keyword
        # scores. On CPU the small zero-shot model is unreliable (it will happily
        # call a football story "Technology"), whereas keyword hit-counts are a
        # sturdy topic signal — so we TRUST the keyword leader when it is ahead,
        # and only spend an AI call to break a genuine tie between the top topics.
        scores = {n: h for n, h in topic_scores(text_lower).items() if n in eligible}
        if not scores:
            return None

        ranked = sorted(scores.items(), key=lambda kv: -kv[1])
        best, best_hits = ranked[0]
        second_hits = ranked[1][1] if len(ranked) > 1 else 0

        if best_hits < 2:
            return None  # only a lone stray keyword — defer to the residual bucket

        if best_hits > second_hits:
            cat = Category.objects.filter(name=best).first()  # clear keyword leader
            if cat:
                return cat

        # Tie at the top → let the AI choose among only the tied topics.
        tied = [n for n, h in ranked if h == best_hits][:5]
        cat_name, confidence = self.category_classifier.classify(title, plain_text, tied)
        if cat_name and confidence >= 0.4:
            cat = Category.objects.filter(name=cat_name).first()
            if cat:
                return cat
        cat = Category.objects.filter(name=best).first()
        return cat

    # Backwards-compatible alias for older callers/tests.
    def ai_classify_topic(self, title, plain_text, category_names, ugandan=False):
        text_lower = f"{title} {plain_text[:2000]}".lower()
        return self._classify_topic(title, plain_text, text_lower, category_names, ugandan)

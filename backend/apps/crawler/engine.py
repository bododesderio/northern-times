"""
CrawlerEngine -- main orchestrator for the news crawling pipeline.

Pipeline: Fetch feeds -> Extract articles -> Clean content -> Enrich -> Dedup -> Store

Replaces the PHP CrawlerEngine (app/Services/CrawlerEngine.php).
"""
import gc
import hashlib
import logging
import re
import time
from datetime import timedelta

from django.conf import settings
from django.db import transaction
from django.utils import timezone
from django.utils.text import slugify

from apps.articles.models import Article, Category, Tag, ArticleEntity, StoryCluster
from apps.crawler.models import CrawlSource, CrawlLog
from apps.crawler.fetchers.rss import RSSFetcher, FeedItem
from apps.crawler.fetchers.web import WebFetcher
from apps.crawler.extractors.base import ExtractionResult

logger = logging.getLogger(__name__)


class CrawlerEngine:
    """Main crawler engine -- orchestrates the full pipeline."""

    def __init__(self):
        self.rss_fetcher = RSSFetcher()
        self.web_fetcher = WebFetcher()
        self.batch_workers = getattr(settings, 'CRAWLER_BATCH_WORKERS', 4)
        self.min_content_length = getattr(settings, 'CRAWLER_MIN_CONTENT_LENGTH', 400)
        self.dedup_threshold = getattr(settings, 'CRAWLER_SEMANTIC_DEDUP_THRESHOLD', 0.82)
        self.cluster_threshold = getattr(settings, 'CRAWLER_STORY_CLUSTER_THRESHOLD', 0.65)
        self._enrichment_loaded = False
        self._extractor = None
        self._content_cleaner = None
        self._html_sanitizer = None
        self._metadata_extractor = None

    # ------------------------------------------------------------------
    # Lazy loaders for heavy dependencies
    # ------------------------------------------------------------------

    def _get_extractor(self):
        """Lazy-load the BS4 multi-strategy extractor."""
        if self._extractor is None:
            try:
                from apps.crawler.extractors.bs4_extractor import BS4Extractor
                self._extractor = BS4Extractor()
            except ImportError:
                logger.warning("BS4Extractor not available, using base fallback")
                self._extractor = None
        return self._extractor

    def _get_content_cleaner(self):
        """Return content cleaning callable, or None if not available."""
        if self._content_cleaner is None:
            try:
                from apps.crawler.cleaners.content_cleaner import clean_content
                self._content_cleaner = clean_content
            except ImportError:
                self._content_cleaner = False  # Sentinel: tried and failed
        return self._content_cleaner if self._content_cleaner is not False else None

    def _get_html_sanitizer(self):
        """Return HTML sanitizer callable, or None if not available."""
        if self._html_sanitizer is None:
            try:
                from apps.crawler.cleaners.html_sanitizer import sanitize_html
                self._html_sanitizer = sanitize_html
            except ImportError:
                self._html_sanitizer = False
        return self._html_sanitizer if self._html_sanitizer is not False else None

    def _get_metadata_extractor(self):
        """Return metadata extraction callable, or None if not available."""
        if self._metadata_extractor is None:
            try:
                from apps.crawler.extractors.metadata import extract_metadata
                self._metadata_extractor = extract_metadata
            except ImportError:
                self._metadata_extractor = False
        return self._metadata_extractor if self._metadata_extractor is not False else None

    def _load_enrichment(self):
        """Lazy-load enrichment services (heavy ML models)."""
        if self._enrichment_loaded:
            return

        from apps.enrichment.embedder import Embedder
        from apps.enrichment.summarizer import Summarizer
        from apps.enrichment.ner import NERExtractor
        from apps.enrichment.sentiment import SentimentAnalyzer
        from apps.enrichment.quality import QualityScorer
        from apps.enrichment.category_classifier import CategoryClassifier
        from apps.enrichment.dedup import DuplicateChecker
        from apps.enrichment.quotes import QuoteExtractor
        from apps.enrichment.keywords import KeywordExtractor

        self.embedder = Embedder()
        self.summarizer = Summarizer()
        self.ner = NERExtractor()
        self.sentiment_analyzer = SentimentAnalyzer()
        self.quality_scorer = QualityScorer()
        self.category_classifier = CategoryClassifier()
        self.dedup_checker = DuplicateChecker()
        self.quote_extractor = QuoteExtractor()
        self.keyword_extractor = KeywordExtractor()
        self._enrichment_loaded = True

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def crawl_all(self, force: bool = False) -> dict:
        """Crawl all active sources that are due (or all if force=True)."""
        sources = CrawlSource.objects.filter(is_active=True)
        if force:
            due_sources = list(sources)
        else:
            due_sources = [s for s in sources if s.due_for_crawl()]

        if not due_sources:
            logger.info("No sources due for crawling")
            return {'sources': 0, 'new_articles': 0, 'errors': 0}

        logger.info(f"Crawling {len(due_sources)} {'(forced)' if force else 'due'} sources")

        total_new = 0
        total_errors = 0

        for source in due_sources:
            try:
                result = self.crawl_source(source)
                total_new += result['new_count']
                if result['status'] == 'error':
                    total_errors += 1
            except Exception as e:
                total_errors += 1
                logger.error(f"Fatal error crawling {source.name}: {e}", exc_info=True)
                self._log_crawl(source, 'error', error_message=str(e)[:2000])
                self._increment_failures(source)

        summary = {
            'sources': len(due_sources),
            'new_articles': total_new,
            'errors': total_errors,
        }
        logger.info(f"Crawl complete: {summary}")
        return summary

    def crawl_source(self, source: CrawlSource) -> dict:
        """Crawl a single source through the full pipeline. Returns result dict."""
        start_time = time.monotonic()
        logger.info(f"Crawling source: {source.name} ({source.url})")

        # Phase 1: Fetch feed items (with adaptive browser fallback)
        try:
            feed_items = self.rss_fetcher.fetch(
                source.url,
                max_items=source.max_articles,
                use_browser=source.use_browser,
            )
        except Exception as e:
            logger.error(f"Feed fetch failed for {source.name}: {e}")
            self._increment_failures(source)
            # Auto-switch to browser after 3 consecutive HTTP failures
            if source.consecutive_failures >= 2 and not source.use_browser:
                source.use_browser = True
                source.save(update_fields=['use_browser'])
                logger.info(f"Auto-enabled browser mode for {source.name} after {source.consecutive_failures + 1} failures")
            duration_ms = int((time.monotonic() - start_time) * 1000)
            self._log_crawl(
                source, 'error',
                error_message=f"Feed fetch failed: {e}",
                duration_ms=duration_ms,
            )
            return {'status': 'error', 'new_count': 0, 'found_count': 0}

        if not feed_items:
            # If HTTP returned empty but browser might work, try browser once
            if not source.use_browser and source.consecutive_failures >= 1:
                logger.info(f"Empty feed for {source.name}, trying browser fallback")
                feed_items = self.rss_fetcher.fetch(
                    source.url, max_items=source.max_articles, use_browser=True,
                )
                if feed_items:
                    source.use_browser = True
                    source.save(update_fields=['use_browser'])
                    logger.info(f"Browser mode succeeded for {source.name}, auto-enabled")

        if not feed_items:
            self._update_source_schedule(source, new_articles=0)
            self._log_crawl(source, 'ok', found_count=0)
            return {'status': 'ok', 'new_count': 0, 'found_count': 0}

        # Phase 2: Pre-filter -- remove already-seen URLs via source_hash
        item_hashes = {self._url_hash(item.url): item for item in feed_items}
        existing_hashes = set(
            Article.objects.with_deleted()
            .filter(source_hash__in=list(item_hashes.keys()))
            .values_list('source_hash', flat=True)
        )
        # Filter: remove already-seen + skip articles older than 48 hours
        cutoff = timezone.now() - timedelta(hours=48)
        new_items = []
        for item in feed_items:
            if self._url_hash(item.url) in existing_hashes:
                continue
            # Skip articles published more than 72 hours ago
            if item.published_at:
                from django.utils.timezone import is_naive, make_aware
                pub = item.published_at
                if is_naive(pub):
                    pub = make_aware(pub)
                if pub < cutoff:
                    logger.debug(f"Skipping old article (>72h): {item.title[:60]}")
                    continue
            new_items.append(item)

        if not new_items:
            self._update_source_schedule(source, new_articles=0)
            self._log_crawl(
                source, 'ok',
                found_count=len(feed_items),
                duplicate_count=len(feed_items),
            )
            return {
                'status': 'ok',
                'new_count': 0,
                'found_count': len(feed_items),
            }

        # Phase 3: Process each new item through extract -> clean -> enrich -> store
        self._load_enrichment()

        new_count = 0
        error_count = 0
        dupe_count = len(feed_items) - len(new_items)

        # Gather recent titles for fuzzy title dedup (3-day window)
        three_days_ago = timezone.now() - timedelta(days=3)
        recent_titles = list(
            Article.objects.with_deleted()
            .filter(created_at__gte=three_days_ago)
            .values_list('title', flat=True)
        )

        # Gather category names for the classifier
        category_names = list(Category.objects.values_list('name', flat=True))

        for item in new_items:
            try:
                article = self._process_item(
                    item, source, recent_titles, category_names,
                )
                if article:
                    new_count += 1
                    recent_titles.append(article.title)
                else:
                    dupe_count += 1
            except Exception as e:
                error_count += 1
                logger.error(f"Error processing {item.url}: {e}", exc_info=True)

        duration_ms = int((time.monotonic() - start_time) * 1000)
        status = 'ok' if error_count == 0 else 'error'

        self._update_source_schedule(source, new_articles=new_count)
        self._log_crawl(
            source, status,
            found_count=len(feed_items),
            new_count=new_count,
            duplicate_count=dupe_count,
            error_count=error_count,
            duration_ms=duration_ms,
        )

        logger.info(
            f"Source {source.name}: {new_count} new, {dupe_count} dupes, "
            f"{error_count} errors in {duration_ms}ms"
        )

        # Free memory after each source crawl cycle
        gc.collect()

        return {
            'status': status,
            'new_count': new_count,
            'found_count': len(feed_items),
            'dupe_count': dupe_count,
            'error_count': error_count,
        }

    # ------------------------------------------------------------------
    # Item processing pipeline
    # ------------------------------------------------------------------

    def _process_item(
        self,
        item: FeedItem,
        source: CrawlSource,
        recent_titles: list[str],
        category_names: list[str],
    ) -> Article | None:
        """Process a single feed item through the full pipeline.

        Returns the created Article, or None if filtered out (duplicate,
        too short, extraction failure, etc.).
        """
        # ---- Layer 3: Fuzzy title dedup (Jaccard) ----
        if self.dedup_checker.is_duplicate(item.title, recent_titles):
            logger.debug(f"Title duplicate: {item.title[:80]}")
            return None

        # ---- Fetch full article page (with browser fallback) ----
        html, status_code = self.web_fetcher.fetch(item.url)
        if not html and source.use_browser:
            # Try browser for article pages too if source needs it
            from apps.crawler.fetchers.browser import BrowserFetcher
            try:
                browser = BrowserFetcher()
                html = browser.fetch(item.url, wait_seconds=3)
                browser.close()
                status_code = 200 if html else 0
            except Exception:
                pass
        if not html:
            logger.warning(f"Failed to fetch article page: {item.url}")
            return None

        # ---- Extract metadata (OG tags, etc.) ----
        metadata_fn = self._get_metadata_extractor()
        metadata = metadata_fn(html, item.url) if metadata_fn else {}

        # ---- Extract content (BS4 multi-strategy) ----
        extractor = self._get_extractor()
        if extractor:
            extraction = extractor.extract(
                html, item.url,
                source_selectors=source.content_selector or None,
                strip_selectors=source.strip_selectors or None,
            )
        else:
            # Minimal fallback: use feed summary if extractor not available
            extraction = ExtractionResult(
                content=item.summary or '',
                title=item.title,
                author=item.author,
                word_count=len((item.summary or '').split()),
                strategy='feed_summary_fallback',
            )

        if not extraction.is_valid:
            logger.debug(
                f"Extraction too short for {item.url}: "
                f"{extraction.word_count} words ({extraction.strategy})"
            )
            return None

        # ---- Clean content ----
        cleaned = extraction.content
        content_cleaner = self._get_content_cleaner()
        if content_cleaner:
            cleaned = content_cleaner(
                cleaned,
                base_url=item.url,
                strip_selectors=source.strip_selectors or None,
            )
        html_sanitizer = self._get_html_sanitizer()
        if html_sanitizer:
            cleaned = html_sanitizer(cleaned)

        # ---- Check minimum content length ----
        plain_text = re.sub(r'<[^>]+>', '', cleaned)
        if len(plain_text) < self.min_content_length:
            logger.debug(
                f"Content too short after cleaning for {item.url}: "
                f"{len(plain_text)} chars (min {self.min_content_length})"
            )
            return None

        # ---- Enrichment pipeline ----
        # 1. Embedding
        embed_input = f"{item.title}. {plain_text[:1000]}"
        embedding = self.embedder.embed(embed_input)

        # 2. Layer 4: Semantic dedup via pgvector
        if self.dedup_checker.semantic_check(embedding, self.dedup_threshold):
            logger.debug(f"Semantic duplicate: {item.title[:80]}")
            return None

        # 3. Category classification — geo-routing + AI topic classification
        category = self._classify_article(
            source, item.title, plain_text, category_names,
        )

        # 4. Summarization
        summary = self.summarizer.summarize(plain_text)

        # 5. Named Entity Recognition
        entities = self.ner.extract(plain_text)

        # 6. Sentiment analysis
        sentiment_result = self.sentiment_analyzer.analyze(plain_text[:500])

        # 7. Quality scoring
        quality = self.quality_scorer.score(plain_text, cleaned)

        # 8. Quote extraction
        pull_quotes = self.quote_extractor.extract(cleaned)

        # 9. Keyword extraction
        keywords = self.keyword_extractor.extract(item.title, plain_text)

        # 10. Word count
        word_count = len(plain_text.split())

        # ---- Generate unique slug ----
        slug = slugify(item.title)[:300]
        if not slug:
            slug = slugify(item.title[:50]) or 'article'
        base_slug = slug
        counter = 1
        while Article.objects.with_deleted().filter(slug=slug).exists():
            slug = f"{base_slug[:290]}-{counter}"
            counter += 1

        # ---- Determine best image ----
        image_url = (
            item.image_url
            or metadata.get('best_image', '')
            or extraction.image_url
            or ''
        )

        # ---- Download image locally if configured ----
        if image_url and source.download_images:
            try:
                from apps.media.services.downloader import download_image
                local_path = download_image(image_url)
                if local_path:
                    image_url = local_path
            except Exception as e:
                logger.debug(f"Image download failed for {item.url}: {e}")

        # ---- Determine author ----
        author_name = (
            extraction.author
            or metadata.get('best_author', '')
            or item.author
            or source.name
        )

        # ---- Determine published date ----
        published_at = None
        if item.published_at:
            from django.utils.timezone import is_naive, make_aware
            published_at = item.published_at
            if is_naive(published_at):
                published_at = make_aware(published_at)

        # ---- Determine article status ----
        rewrite_status = None
        if source.auto_rewrite and getattr(settings, 'REWRITER_ENABLED', False):
            rewrite_status = 'queued'

        article_status = (
            'pending_review' if source.editorial_review else 'published'
        )

        # ---- Store article + entities + tags in single transaction ----
        with transaction.atomic():
            article = Article.objects.create(
                title=item.title,
                slug=slug,
                excerpt=summary or plain_text[:160],
                content=cleaned,
                featured_image=image_url,
                status=article_status,
                published_at=published_at or timezone.now(),
                is_crawled=True,
                source_url=item.url,
                source_name=source.name,
                source_hash=self._url_hash(item.url),
                display_author=author_name,
                category=category,
                embedding=embedding,
                ai_summary=summary,
                sentiment=sentiment_result.get('sentiment', ''),
                sentiment_score=sentiment_result.get('score'),
                quality_score=quality,
                word_count=word_count,
                pull_quotes=pull_quotes,
                keywords=keywords,
                rewrite_status=rewrite_status,
            )

            # Store NER entities and auto-create tags
            self._store_entities_and_tags(article, entities)

            # Story clustering
            self._assign_story_cluster(article)

        # ---- Fire webhooks + push notification (async, non-blocking) ----
        if article.status == 'published':
            self._dispatch_webhook(article)
            try:
                from apps.articles.tasks import notify_new_article
                notify_new_article.delay(str(article.id))
            except Exception:
                pass  # Non-critical — don't break crawl pipeline

        return article

    # ------------------------------------------------------------------
    # Geo-routing + AI category classification
    # ------------------------------------------------------------------

    # Northern Uganda keywords — multi-word phrases first (matched exactly),
    # then single-word district/town names (matched with word boundaries).
    # Split into PHRASES (substring match safe) and WORDS (need word boundary).
    _NU_PHRASES = [
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
    _NU_WORDS = [
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
    _NU_WORD_PATTERN = re.compile(
        r'\b(?:' + '|'.join(re.escape(w) for w in _NU_WORDS) + r')\b',
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

    def _count_northern_matches(self, text_lower: str) -> int:
        """Count Northern Uganda keyword matches using word boundaries."""
        # Phrase matches (safe substring)
        phrase_hits = sum(1 for p in self._NU_PHRASES if p in text_lower)
        # Word matches (word boundary regex)
        word_hits = len(self._NU_WORD_PATTERN.findall(text_lower))
        return phrase_hits + word_hits

    def _classify_article(
        self,
        source: CrawlSource,
        title: str,
        plain_text: str,
        category_names: list[str],
    ) -> Category:
        """Geo-route article to correct category using source region + keywords + AI."""
        text_lower = f"{title} {plain_text[:2000]}".lower()

        # Step 1: Northern Uganda — only for Ugandan sources, require strong signal
        if source.region == 'ugandan':
            northern_matches = self._count_northern_matches(text_lower)
            # Require 3+ matches for confidence (prevents "north" false positives)
            if northern_matches >= 3:
                cat = Category.objects.filter(slug='northern-uganda').first()
                if cat:
                    return cat

        # Step 2: International/East African sources → "World" unless Uganda content
        if source.region in ('international', 'east_african'):
            uganda_matches = sum(
                1 for kw in self.UGANDA_KEYWORDS
                if re.search(r'\b' + re.escape(kw) + r'\b', text_lower)
            )
            if uganda_matches >= 2:
                return self._ai_classify_topic(title, plain_text, category_names, ugandan=True)
            cat = Category.objects.filter(slug='world').first()
            if cat:
                return cat

        # Step 3: Ugandan source — verify content is actually about Uganda
        # (local outlets sometimes syndicate international stories)
        if source.region == 'ugandan':
            uganda_matches = sum(
                1 for kw in self.UGANDA_KEYWORDS
                if re.search(r'\b' + re.escape(kw) + r'\b', text_lower)
            )
            if uganda_matches < 1:
                # No Uganda keywords at all → likely syndicated international content
                cat = Category.objects.filter(slug='world').first()
                if cat:
                    return cat

        # Ugandan content → AI classify topic (Politics, Sports, Business, etc.)
        return self._ai_classify_topic(title, plain_text, category_names, ugandan=True)

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
            'startup', 'app', 'digital', 'cyber', 'internet', 'social media', 'data',
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
            'court', 'judge', 'sentence', 'prison', 'jail', 'terrorist', 'attack',
            'bomb', 'shooting', 'kidnap', 'suspect', 'investigation', 'detective',
            'military', 'army', 'soldier', 'war', 'conflict', 'ceasefire', 'militia',
            'drug trafficking', 'smuggling', 'gang', 'violence', 'assault', 'rape',
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

    def _keyword_topic_hint(self, text_lower: str) -> str | None:
        """Fast keyword-based topic detection. Returns category name or None."""
        scores = {}
        for cat_slug, keywords in self.TOPIC_KEYWORDS.items():
            hits = sum(1 for kw in keywords if kw in text_lower)
            if hits >= 3:
                scores[cat_slug] = hits
        if not scores:
            return None
        # Return the category with most keyword hits
        best = max(scores, key=scores.get)
        # Map slug-style names to actual category names
        name_map = {
            'politics': 'Politics', 'sports': 'Sports', 'business': 'Business',
            'health': 'Health', 'technology': 'Technology', 'entertainment': 'Entertainment',
            'education': 'Education', 'environment': 'Environment',
            'crime & security': 'Crime & Security', 'opinion': 'Opinion',
            'lifestyle': 'Lifestyle',
        }
        return name_map.get(best)

    def _ai_classify_topic(
        self,
        title: str,
        plain_text: str,
        category_names: list[str],
        ugandan: bool = False,
    ) -> Category:
        """Keyword hint first, then AI zero-shot classifier for topic."""
        text_lower = f"{title} {plain_text[:2000]}".lower()

        # Fast keyword check first
        hint = self._keyword_topic_hint(text_lower)
        if hint:
            cat = Category.objects.filter(name=hint).first()
            if cat:
                return cat

        # Fall back to AI classifier
        if ugandan:
            exclude = {'World', 'Northern Uganda', 'Top Stories'}
            topic_names = [c for c in category_names if c not in exclude]
        else:
            topic_names = category_names

        if topic_names:
            cat_name, confidence = self.category_classifier.classify(
                title, plain_text, topic_names,
            )
            if cat_name and confidence >= 0.3:
                cat = Category.objects.filter(name=cat_name).first()
                if cat:
                    return cat

        # Fallback: Politics for Ugandan, World for others
        if ugandan:
            return Category.objects.filter(slug='politics').first() or Category.objects.first()
        return Category.objects.filter(slug='world').first() or Category.objects.first()

    # ------------------------------------------------------------------
    # Entity and tag storage
    # ------------------------------------------------------------------

    def _store_entities_and_tags(
        self, article: Article, entities: list[dict],
    ) -> None:
        """Create ArticleEntity records and auto-tag from high-salience entities."""
        for ent in entities[:15]:
            ArticleEntity.objects.create(
                article=article,
                entity_text=ent['text'],
                entity_type=ent['type'],
                salience=ent['salience'],
            )
            # Auto-create tags from entities with salience >= 0.3
            if ent['salience'] >= 0.3:
                tag_slug = slugify(ent['text'])
                if tag_slug:
                    tag, _ = Tag.objects.get_or_create(
                        slug=tag_slug,
                        defaults={
                            'name': ent['text'],
                            'type': ent['type'],
                        },
                    )
                    article.tags.add(tag)

    # ------------------------------------------------------------------
    # Story clustering
    # ------------------------------------------------------------------

    def _assign_story_cluster(self, article: Article) -> None:
        """Attempt to assign article to an existing or new story cluster."""
        try:
            from apps.articles.services.clustering import StoryThreadDetector
            detector = StoryThreadDetector()
            detector.create_or_assign_cluster(article)
        except Exception as e:
            logger.debug(f"Story clustering failed for article {article.id}: {e}")

    # ------------------------------------------------------------------
    # Webhook dispatch
    # ------------------------------------------------------------------

    @staticmethod
    def _dispatch_webhook(article: Article) -> None:
        """Fire article.published webhook asynchronously."""
        try:
            from apps.webhooks.services.dispatcher import WebhookDispatcher
            WebhookDispatcher.dispatch_async('article.published', {
                'id': str(article.id),
                'title': article.title,
                'slug': article.slug,
                'source_name': article.source_name,
                'source_url': article.source_url,
            })
        except Exception:
            pass  # Webhook failure must never block the crawl

    # ------------------------------------------------------------------
    # Source scheduling
    # ------------------------------------------------------------------

    def _update_source_schedule(
        self, source: CrawlSource, new_articles: int,
    ) -> None:
        """Update source scheduling fields after a crawl."""
        source.last_crawled_at = timezone.now()

        if new_articles > 0:
            source.last_new_content_at = timezone.now()
            source.consecutive_empty = 0
            source.consecutive_failures = 0
        else:
            source.consecutive_empty += 1

        # Adaptive interval: back off if source keeps returning empty
        interval = source.crawl_interval
        if source.consecutive_empty >= 5:
            # Double the interval, but cap at 2 hours
            interval = min(interval * 2, 120)

        source.next_crawl_at = timezone.now() + timedelta(minutes=interval)
        source.save(update_fields=[
            'last_crawled_at',
            'last_new_content_at',
            'consecutive_empty',
            'consecutive_failures',
            'next_crawl_at',
        ])

    def _increment_failures(self, source: CrawlSource) -> None:
        """Increment failure counter with exponential backoff."""
        source.consecutive_failures += 1
        source.last_crawled_at = timezone.now()

        # Exponential backoff: 2^(failures-2) minutes, max 480 min (8 hours)
        backoff = min(2 ** max(0, source.consecutive_failures - 2), 480)
        source.next_crawl_at = timezone.now() + timedelta(
            minutes=max(source.crawl_interval, backoff),
        )
        source.save(update_fields=[
            'consecutive_failures',
            'last_crawled_at',
            'next_crawl_at',
        ])

    # ------------------------------------------------------------------
    # Crawl logging
    # ------------------------------------------------------------------

    @staticmethod
    def _log_crawl(source: CrawlSource, status: str, **kwargs) -> CrawlLog:
        """Create a CrawlLog entry."""
        return CrawlLog.objects.create(
            source=source,
            status=status,
            found_count=kwargs.get('found_count', 0),
            new_count=kwargs.get('new_count', 0),
            duplicate_count=kwargs.get('duplicate_count', 0),
            error_count=kwargs.get('error_count', 0),
            error_message=kwargs.get('error_message', ''),
            duration_ms=kwargs.get('duration_ms', 0),
        )

    # ------------------------------------------------------------------
    # Utilities
    # ------------------------------------------------------------------

    @staticmethod
    def _url_hash(url: str) -> str:
        """SHA256 hash of URL for dedup (Layer 1)."""
        return hashlib.sha256(url.encode()).hexdigest()

    def close(self) -> None:
        """Clean up resources (HTTP clients, etc.)."""
        self.web_fetcher.close()

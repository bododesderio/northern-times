"""
CrawlerEngine -- main orchestrator for the news crawling pipeline.

Pipeline: Fetch feeds -> Extract articles -> Clean content -> Enrich -> Dedup -> Store

Replaces the PHP CrawlerEngine (app/Services/CrawlerEngine.php).
"""
import gc
import hashlib
import logging
import time
from datetime import timedelta

from django.conf import settings
from django.utils import timezone
from django.utils.text import slugify

from apps.articles.models import Article, Category, Tag, ArticleEntity, StoryCluster
from apps.crawler.models import CrawlSource, CrawlLog
from apps.crawler.fetchers.rss import RSSFetcher, FeedItem
from apps.crawler.fetchers.web import WebFetcher
from apps.crawler.fetchers.html_listing import HTMLListingFetcher
from apps.crawler import classification, dispatch, scheduling
from apps.crawler.classification import GeoClassifier
from apps.crawler.pipeline import CrawlPipeline, PipelineContext

logger = logging.getLogger(__name__)


class CrawlerEngine:
    """Main crawler engine -- orchestrates the full pipeline."""

    def __init__(self):
        self.rss_fetcher = RSSFetcher()
        self.web_fetcher = WebFetcher()
        self.html_fetcher = HTMLListingFetcher()
        self._shared_browser = None
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
        # Geo-routing classifier shares the already-loaded AI topic classifier.
        self.geo_classifier = GeoClassifier(self.category_classifier)
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
                scheduling.increment_failures(source)

        summary = {
            'sources': len(due_sources),
            'new_articles': total_new,
            'errors': total_errors,
        }
        logger.info(f"Crawl complete: {summary}")
        return summary

    def _maybe_first_run_wipe(self) -> None:
        """One-time clean slate: on the very first crawl, hard-delete ALL articles
        and crawl history so the site starts fresh, then set a flag so it never
        re-wipes. From then on the always-on freshness gate keeps content current.

        Idempotent: guarded by the ``crawler_initialized`` Setting flag, so it is
        safe to call at the top of every crawl.
        """
        from apps.core.models import Setting
        if Setting.get('crawler_initialized', False):
            return

        from django.db import transaction
        with transaction.atomic():
            art_count = Article.objects.with_deleted().count()
            log_count = CrawlLog.objects.count()
            # Hard delete (cascades entities/tags); StoryCluster canonical FK is
            # SET_NULL so clusters clear cleanly.
            Article.objects.with_deleted().all().delete()
            CrawlLog.objects.all().delete()
            StoryCluster.objects.all().delete()
            Setting.set('crawler_initialized', '1', type='bool')
        logger.warning(
            "Crawler first-run wipe: purged %d articles + %d crawl logs; "
            "freshness gate now governs all new content.",
            art_count, log_count,
        )

    def crawl_source(self, source: CrawlSource) -> dict:
        """Crawl a single source through the full pipeline. Returns result dict."""
        start_time = time.monotonic()
        logger.info(f"Crawling source: {source.name} ({source.url})")

        # First-run clean slate (idempotent; no-op after the flag is set).
        self._maybe_first_run_wipe()

        # Phase 1: Fetch feed items (with adaptive browser fallback)
        try:
            feed_items = self._fetch_feed_items(source, use_browser=source.use_browser)
        except Exception as e:
            logger.error(f"Feed fetch failed for {source.name}: {e}")
            scheduling.increment_failures(source)
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
                feed_items = self._fetch_feed_items(source, use_browser=True)
                if feed_items:
                    source.use_browser = True
                    source.save(update_fields=['use_browser'])
                    logger.info(f"Browser mode succeeded for {source.name}, auto-enabled")

        if not feed_items:
            scheduling.update_after_crawl(source, new_articles=0)
            self._log_crawl(source, 'ok', found_count=0)
            return {'status': 'ok', 'new_count': 0, 'found_count': 0}

        # Phase 2: Pre-filter -- remove already-seen URLs via source_hash
        item_hashes = {self._url_hash(item.url): item for item in feed_items}
        existing_hashes = set(
            Article.objects.with_deleted()
            .filter(source_hash__in=list(item_hashes.keys()))
            .values_list('source_hash', flat=True)
        )
        # Filter: remove already-seen + skip articles older than the configured
        # freshness window (CRAWLER_MAX_ARTICLE_AGE_HOURS, default 48h).
        max_age_hours = getattr(settings, 'CRAWLER_MAX_ARTICLE_AGE_HOURS', 48)
        cutoff = timezone.now() - timedelta(hours=max_age_hours)
        new_items = []
        for item in feed_items:
            if self._url_hash(item.url) in existing_hashes:
                continue
            if item.published_at:
                from django.utils.timezone import is_naive, make_aware
                pub = item.published_at
                if is_naive(pub):
                    pub = make_aware(pub)
                if pub < cutoff:
                    logger.debug(
                        f"Skipping old article (>{max_age_hours}h): {item.title[:60]}"
                    )
                    continue
            new_items.append(item)

        if not new_items:
            scheduling.update_after_crawl(source, new_articles=0)
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

        scheduling.update_after_crawl(source, new_articles=new_count)
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
        self._close_browser()
        gc.collect()

        return {
            'status': status,
            'new_count': new_count,
            'found_count': len(feed_items),
            'dupe_count': dupe_count,
            'error_count': error_count,
        }

    def get_browser(self):
        """Shared stealth browser for the current crawl, reused across items.

        Launching a headless Chrome per article (index page + one per story)
        dominated crawl time and memory; one session is created lazily and
        closed once per source crawl (``_close_browser``).
        """
        if self._shared_browser is None:
            from apps.crawler.fetchers.browser import BrowserFetcher
            self._shared_browser = BrowserFetcher()
        return self._shared_browser

    def _close_browser(self) -> None:
        if self._shared_browser is not None:
            try:
                self._shared_browser.close()
            except Exception:
                pass
            self._shared_browser = None

    def _fetch_feed_items(self, source: CrawlSource, use_browser: bool) -> list:
        """Fetch feed items, dispatching by source type.

        ``feed_type='web'`` sources have no RSS — they are JS-SPA / Cloudflare
        pages, so we render the index in the stealth browser and scrape article
        links (``content_selector`` may hold comma-separated required path
        fragments). Everything else goes through the RSS/Atom fetcher.
        """
        if source.feed_type == 'web':
            fragments = [f.strip() for f in (source.content_selector or '').split(',') if f.strip()]
            return self.html_fetcher.fetch(
                source.url, max_items=source.max_articles,
                required_fragments=fragments or None,
                browser=self.get_browser(),
            )
        return self.rss_fetcher.fetch(
            source.url, max_items=source.max_articles, use_browser=use_browser,
        )

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
        ctx = PipelineContext(
            item=item,
            source=source,
            recent_titles=recent_titles,
            category_names=category_names,
        )
        return CrawlPipeline(self).run(ctx)

    # ------------------------------------------------------------------
    # Geo-routing + AI category classification
    #
    # Routing logic and keyword tables now live in ``crawler.classification``
    # (single source of truth, editable without touching the pipeline). These
    # shims remain only because the ``reclassify_articles`` management command
    # calls them directly.
    # ------------------------------------------------------------------

    UGANDA_KEYWORDS = classification.UGANDA_KEYWORDS

    def _count_northern_matches(self, text_lower: str) -> int:
        return classification.count_northern_matches(text_lower)

    def _keyword_topic_hint(self, text_lower: str) -> str | None:
        return classification.keyword_topic_hint(text_lower)

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

        # Geocode from the freshly stored GPE entities (best-effort).
        try:
            from apps.articles.services.geo import apply_geocode
            apply_geocode(article)
        except Exception:
            pass  # geocoding is non-critical to the crawl

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
        """Clean up resources (HTTP clients, browser, etc.)."""
        self.web_fetcher.close()
        self._close_browser()

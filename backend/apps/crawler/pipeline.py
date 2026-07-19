"""
Per-item crawl pipeline — the stages a single feed item passes through.

Extracted verbatim from ``CrawlerEngine._process_item`` (a 200-line method with
~18 responsibilities) into an explicit, ordered sequence of small stages driven
by a shared ``PipelineContext``. Behaviour is identical: same stage order, same
early-abort points (a stage returning ``False`` filters the item → the pipeline
returns ``None``), same fields, same single persistence transaction.

The engine still owns the heavy service instances (embedder, dedup checker,
classifier, …) and the low-level helpers (``_url_hash``,
``_store_entities_and_tags``, ``_assign_story_cluster``); stages reach them via
``self.engine`` so no model or service is loaded twice. This keeps the engine
focused on *source-level* orchestration and makes each stage unit-testable in
isolation with a stub engine + context.
"""
import logging
import re
from dataclasses import dataclass, field

from django.conf import settings
from django.db import transaction
from django.utils import timezone
from django.utils.text import slugify

from apps.articles.models import Article
from apps.crawler import dispatch
from apps.crawler.extractors.base import ExtractionResult

logger = logging.getLogger(__name__)


@dataclass
class PipelineContext:
    """Mutable state threaded through the pipeline stages for one feed item."""

    item: object
    source: object
    recent_titles: list
    category_names: list

    # ---- accumulated by stages ----
    title: str = ''
    html: str = ''
    status_code: int = 0
    metadata: dict = field(default_factory=dict)
    extraction: object = None
    cleaned: str = ''
    plain_text: str = ''
    embedding: object = None
    category: object = None
    summary: str = ''
    entities: list = field(default_factory=list)
    sentiment_result: dict = field(default_factory=dict)
    quality: object = None
    pull_quotes: list = field(default_factory=list)
    keywords: list = field(default_factory=list)
    word_count: int = 0
    slug: str = ''
    image_url: str = ''
    author_name: str = ''
    published_at: object = None
    rewrite_status: object = None
    article_status: str = ''
    article: object = None
    # Keep-best: the existing article this item duplicates (stashed at the embed
    # stage, resolved just before persist once the newcomer is fully enriched).
    duplicate_of: object = None


class CrawlPipeline:
    """Runs the ordered stages for one item. A stage returning ``False`` aborts."""

    def __init__(self, engine):
        self.engine = engine
        self.stages = (
            self.stage_title_dedup,        # Layer 3: fuzzy title pre-check (non-fatal)
            self.stage_fetch_page,         # fetch full article HTML (+browser fallback)
            self.stage_extract,            # metadata + content extraction
            self.stage_clean,              # clean/sanitize + min-length gate
            self.stage_embed_and_dedup,    # embedding + Layer 4 semantic match (stash)
            self.stage_enrich,             # classify/summarize/NER/sentiment/quality/quotes/keywords
            self.stage_build_fields,       # slug/image/author/dates/status
            self.stage_resolve_duplicate,  # keep-best: purge loser or discard newcomer
            self.stage_persist,            # atomic create + entities/tags + clustering
            self.stage_dispatch,           # webhook + push (published only)
        )

    def run(self, ctx: PipelineContext):
        """Execute stages in order. Returns the Article, or None if filtered."""
        for stage in self.stages:
            if stage(ctx) is False:
                return None
        return ctx.article

    # ------------------------------------------------------------------
    # Stages — each verbatim from the former _process_item block.
    # ------------------------------------------------------------------

    def stage_title_dedup(self, ctx):
        # ---- Layer 3: Fuzzy title pre-check (Jaccard) — NON-FATAL ----
        # Under keep-best a same-story-different-site item must survive to the
        # resolution stage rather than being discarded first-seen-wins. So a
        # title match is only logged here; the authoritative catch is the
        # semantic ``find_duplicate`` at the embed stage, resolved before persist.
        if self.engine.dedup_checker.is_duplicate(ctx.item.title, ctx.recent_titles):
            logger.debug(f"Title pre-match (deferred to keep-best): {ctx.item.title[:80]}")
        return True

    def stage_fetch_page(self, ctx):
        # ---- Fetch full article page ----
        item = ctx.item
        source = ctx.source
        html, status_code = '', 0
        # JS-SPA / Cloudflare sources: render with the stealth browser FIRST — a
        # plain HTTP GET of these article pages only returns the app shell or the
        # "just a moment" challenge, never the article body.
        if source.use_browser:
            # Reuse the engine's shared browser session (see get_browser) instead
            # of launching a fresh Chrome per article.
            try:
                html = self.engine.get_browser().fetch(item.url, wait_seconds=4)
                status_code = 200 if html else 0
            except Exception:
                html = ''
        if not html:
            html, status_code = self.engine.web_fetcher.fetch(item.url)
        if not html:
            logger.warning(f"Failed to fetch article page: {item.url}")
            return False
        ctx.html = html
        ctx.status_code = status_code
        return True

    def stage_extract(self, ctx):
        item = ctx.item
        source = ctx.source
        # ---- Extract metadata (OG tags, etc.) ----
        metadata_fn = self.engine._get_metadata_extractor()
        metadata = metadata_fn(ctx.html, item.url) if metadata_fn else {}

        # ---- Extract content (BS4 multi-strategy) ----
        extractor = self.engine._get_extractor()
        if extractor:
            extraction = extractor.extract(
                ctx.html, item.url,
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
            return False
        ctx.metadata = metadata
        ctx.extraction = extraction

        # Resolve the authoritative title. Collapse whitespace (scraped anchor
        # text arrives with newlines/indentation) and prefer the article page's
        # own og:title / JSON-LD headline for web-listing sources — their feed
        # "title" is anchor text that prepends a category badge (e.g. "news")
        # to the headline. RSS feed titles are trustworthy, so keep those.
        feed_title = ' '.join((item.title or '').split())
        page_title = (
            metadata.get('og_title') or metadata.get('ld_headline')
            or (extraction.title or '') or metadata.get('page_title') or ''
        ).strip()
        # Drop a trailing " - Site Name" suffix from the <title>-derived form.
        if page_title and ' - ' in page_title and len(page_title) > 40:
            page_title = page_title.rsplit(' - ', 1)[0].strip()

        if ctx.source.feed_type == 'web':
            ctx.title = page_title if len(page_title.split()) >= 3 else feed_title
        else:
            ctx.title = feed_title or page_title
        ctx.title = ctx.title.strip()

        # A real article needs a real headline. If neither the feed nor the page
        # yielded one (e.g. an article render failed and we only have scraped nav
        # text like "news"), drop it rather than store a garbage-titled row.
        if len(ctx.title.split()) < 3:
            logger.debug(f"Rejecting weak-title article {ctx.title!r}: {item.url}")
            return False
        return True

    def stage_clean(self, ctx):
        item = ctx.item
        source = ctx.source
        # ---- Clean content ----
        cleaned = ctx.extraction.content
        content_cleaner = self.engine._get_content_cleaner()
        if content_cleaner:
            cleaned = content_cleaner(
                cleaned,
                base_url=item.url,
                strip_selectors=source.strip_selectors or None,
            )
        html_sanitizer = self.engine._get_html_sanitizer()
        if html_sanitizer:
            cleaned = html_sanitizer(cleaned)

        # Whitelist pass (defense in depth). The cleaner above is a blacklist;
        # the article body is rendered with |safe, so enforce a bleach allow-list
        # (tags/attrs + iframe-domain allowlist) to stop a hostile source page
        # smuggling <iframe>/data:/javascript: markup into the page.
        try:
            from apps.core.sanitizer import sanitize as _whitelist_sanitize
            cleaned = _whitelist_sanitize(cleaned, profile='article')
        except Exception as exc:
            logger.debug("whitelist sanitize skipped: %s", exc)

        # ---- Check minimum content length ----
        plain_text = re.sub(r'<[^>]+>', '', cleaned)
        if len(plain_text) < self.engine.min_content_length:
            logger.debug(
                f"Content too short after cleaning for {item.url}: "
                f"{len(plain_text)} chars (min {self.engine.min_content_length})"
            )
            return False
        ctx.cleaned = cleaned
        ctx.plain_text = plain_text
        return True

    def stage_embed_and_dedup(self, ctx):
        # ---- Enrichment pipeline ----
        # 1. Embedding
        embed_input = f"{ctx.title}. {ctx.plain_text[:1000]}"
        embedding = self.engine.embedder.embed(embed_input)

        # 2. Layer 4: Semantic match via pgvector — STASH, don't discard.
        # Keep-best resolution (stage_resolve_duplicate) runs after enrichment,
        # once the newcomer's quality signals (word_count/entities/quotes/image)
        # are known, so the better version of a cross-source story is kept.
        ctx.embedding = embedding
        ctx.duplicate_of = self.engine.dedup_checker.find_duplicate(
            embedding, title=ctx.title, threshold=self.engine.dedup_threshold,
        )
        if ctx.duplicate_of is not None:
            logger.debug(
                f"Semantic match vs {ctx.duplicate_of.id}: {ctx.item.title[:80]}"
            )
        return True

    def stage_enrich(self, ctx):
        item = ctx.item
        plain_text = ctx.plain_text
        cleaned = ctx.cleaned

        # 3. Category classification — geo-routing + AI topic classification
        ctx.category = self.engine.geo_classifier.classify(
            ctx.source, ctx.title, plain_text, ctx.category_names,
        )
        # 4. Summarization
        ctx.summary = self.engine.summarizer.summarize(plain_text)
        # 5. Named Entity Recognition
        ctx.entities = self.engine.ner.extract(plain_text)
        # 6. Sentiment analysis
        ctx.sentiment_result = self.engine.sentiment_analyzer.analyze(plain_text[:500])
        # 7. Quality scoring
        ctx.quality = self.engine.quality_scorer.score(plain_text, cleaned)
        # 8. Quote extraction
        ctx.pull_quotes = self.engine.quote_extractor.extract(cleaned)
        # 9. Keyword extraction
        ctx.keywords = self.engine.keyword_extractor.extract(ctx.title, plain_text)
        # 10. Word count
        ctx.word_count = len(plain_text.split())
        return True

    def stage_build_fields(self, ctx):
        item = ctx.item
        source = ctx.source

        # ---- Generate unique slug ----
        slug = slugify(ctx.title)[:300]
        if not slug:
            slug = slugify(ctx.title[:50]) or 'article'
        base_slug = slug
        counter = 1
        while Article.objects.with_deleted().filter(slug=slug).exists():
            slug = f"{base_slug[:290]}-{counter}"
            counter += 1
        ctx.slug = slug

        # ---- Determine best image ----
        image_url = (
            item.image_url
            or ctx.metadata.get('best_image', '')
            or ctx.extraction.image_url
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
        ctx.image_url = image_url

        # ---- Determine author ----
        ctx.author_name = (
            ctx.extraction.author
            or ctx.metadata.get('best_author', '')
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
        ctx.published_at = published_at

        # ---- Determine article status ----
        rewrite_status = None
        if source.auto_rewrite and getattr(settings, 'REWRITER_ENABLED', False):
            rewrite_status = 'queued'
        ctx.rewrite_status = rewrite_status

        ctx.article_status = (
            'pending_review' if source.editorial_review else 'published'
        )
        return True

    def stage_resolve_duplicate(self, ctx):
        # ---- Keep-best: the newcomer is fully enriched; decide the winner ----
        if ctx.duplicate_of is None:
            return True

        from apps.crawler.services import resolve

        # Re-confirm the match still exists (a concurrent crawl may have purged it).
        from apps.articles.models import Article
        existing = Article.objects.with_deleted().filter(
            pk=ctx.duplicate_of.pk, deleted_at__isnull=True,
        ).first()
        if existing is None:
            return True

        winner = resolve.resolve_duplicate(ctx, existing)
        if winner == 'existing':
            logger.debug(f"Keep-best: existing wins, discarding {ctx.item.title[:80]}")
            return False

        # Newcomer wins → inherit the loser's assets, then hard-delete the loser.
        resolve.merge_into_new(ctx, existing)
        resolve.purge_duplicate(existing)
        return True

    def stage_persist(self, ctx):
        item = ctx.item
        source = ctx.source
        # ---- Store article + entities + tags in single transaction ----
        with transaction.atomic():
            article = Article.objects.create(
                title=ctx.title,
                slug=ctx.slug,
                excerpt=ctx.summary or ctx.plain_text[:160],
                content=ctx.cleaned,
                featured_image=ctx.image_url,
                status=ctx.article_status,
                published_at=ctx.published_at or timezone.now(),
                is_crawled=True,
                source_url=item.url,
                source_name=source.name,
                source_hash=self.engine._url_hash(item.url),
                display_author=ctx.author_name,
                category=ctx.category,
                embedding=ctx.embedding,
                ai_summary=ctx.summary,
                sentiment=ctx.sentiment_result.get('sentiment', ''),
                sentiment_score=ctx.sentiment_result.get('score'),
                quality_score=ctx.quality,
                word_count=ctx.word_count,
                pull_quotes=ctx.pull_quotes,
                keywords=ctx.keywords,
                rewrite_status=ctx.rewrite_status,
            )

            # Store NER entities and auto-create tags
            self.engine._store_entities_and_tags(article, ctx.entities)

            # Story clustering
            self.engine._assign_story_cluster(article)

            # Keep-best: preserve the purged loser's canonical story thread so the
            # replacement stays part of the same cluster.
            inherited = (ctx.metadata or {}).get('inherit_story_cluster_id')
            if inherited and not article.story_cluster_id:
                article.story_cluster_id = inherited
                article.save(update_fields=['story_cluster_id'])
        ctx.article = article
        return True

    def stage_dispatch(self, ctx):
        # ---- Fire webhooks + push notification (async, non-blocking) ----
        if ctx.article.status == 'published':
            dispatch.emit_published(ctx.article)
        return True

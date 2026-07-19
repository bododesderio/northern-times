"""Keep-best resolution for cross-source duplicate stories.

When a newcomer is found to be the same story as an existing article (see
``DuplicateChecker.find_duplicate``), the crawler no longer blindly discards the
newcomer (first-seen-wins). Instead it scores both versions and keeps the better
one, HARD-deleting the loser so every stored article is distinctly unique.

Scoring is a transparent heuristic (length, image, source trust, entities,
quotes, recency). AI is consulted ONLY for close calls and ONLY when a valid
OpenAI key is configured — otherwise the heuristic decides.
"""
import logging

from django.db import transaction

logger = logging.getLogger(__name__)

# Region trust weights — a Northern-Uganda paper trusts local outlets most.
_REGION_TRUST = {'ugandan': 3.0, 'east_african': 2.0, 'international': 1.0}

# Below this heuristic margin the two versions are "close" → AI tie-break (if a
# key is present); above it the heuristic winner stands.
AI_TIEBREAK_MARGIN = 2.0


def _quality_score(*, word_count, has_image, region, entity_count, quote_count, published_at):
    """Transparent quality heuristic. Higher is better."""
    score = 0.0
    score += min(word_count or 0, 1500) / 100.0          # up to +15 for depth
    score += 4.0 if has_image else 0.0                   # a featured image matters
    score += _REGION_TRUST.get(region, 1.0)              # source trust
    score += min(entity_count or 0, 10) * 0.3            # richer entity coverage
    score += min(quote_count or 0, 5) * 0.4              # direct quotes = reporting
    if published_at:
        from django.utils import timezone
        age_h = max(0.0, (timezone.now() - published_at).total_seconds() / 3600.0)
        score += max(0.0, 2.0 - age_h / 24.0)            # mild freshness tie-break
    return score


def score_new(ctx) -> float:
    """Quality of the newcomer, from the pipeline context (post-enrich)."""
    return _quality_score(
        word_count=ctx.word_count,
        has_image=bool(ctx.image_url),
        region=getattr(ctx.source, 'region', 'international'),
        entity_count=len(ctx.entities or []),
        quote_count=len(ctx.pull_quotes or []),
        published_at=ctx.published_at,
    )


def score_existing(article) -> float:
    """Quality of an already-stored article."""
    from apps.crawler.models import CrawlSource
    src = CrawlSource.objects.filter(name=article.source_name).only('region').first()
    region = src.region if src else 'international'
    return _quality_score(
        word_count=article.word_count,
        has_image=bool(article.featured_image),
        region=region,
        entity_count=article.entities.count(),
        quote_count=len(article.pull_quotes or []),
        published_at=article.published_at,
    )


def _ai_tiebreak(ctx, existing):
    """Ask the model which version is the better article. None on any failure."""
    try:
        from apps.rewriter.service import has_valid_openai_key
    except Exception:
        return None
    if not has_valid_openai_key():
        return None
    try:
        from django.conf import settings
        from openai import OpenAI

        client = OpenAI(api_key=settings.OPENAI_API_KEY)
        prompt = (
            "Two news articles cover the same story. Reply with exactly one word: "
            "'NEW' or 'EXISTING' — whichever is the more complete, better-sourced, "
            "more readable report.\n\n"
            f"=== NEW ({getattr(ctx.source, 'name', '')}) ===\n"
            f"{ctx.title}\n{(ctx.plain_text or '')[:1500]}\n\n"
            f"=== EXISTING ({existing.source_name}) ===\n"
            f"{existing.title}\n{(existing.content or '')[:1500]}"
        )
        resp = client.chat.completions.create(
            model=getattr(settings, 'OPENAI_MODEL', 'gpt-4o-mini'),
            messages=[{'role': 'user', 'content': prompt}],
            max_tokens=4,
            temperature=0,
        )
        answer = (resp.choices[0].message.content or '').strip().upper()
        if 'NEW' in answer:
            return 'new'
        if 'EXIST' in answer:
            return 'existing'
    except Exception as exc:  # pragma: no cover - network path
        logger.debug("AI tie-break failed, falling back to heuristic: %s", exc)
    return None


def resolve_duplicate(ctx, existing) -> str:
    """Decide the winner between the newcomer (``ctx``) and ``existing``.

    Returns ``'new'`` if the newcomer should replace ``existing``, else
    ``'existing'``. Close calls defer to AI when a valid key is configured.
    """
    q_new = score_new(ctx)
    q_old = score_existing(existing)
    margin = abs(q_new - q_old)
    winner = 'new' if q_new > q_old else 'existing'

    if margin < AI_TIEBREAK_MARGIN:
        ai = _ai_tiebreak(ctx, existing)
        if ai in ('new', 'existing'):
            winner = ai

    logger.info(
        "dedup keep-best: new=%.2f old=%.2f margin=%.2f -> %s (existing_id=%s '%s')",
        q_new, q_old, margin, winner, existing.id, existing.title[:60],
    )
    return winner


def purge_duplicate(article) -> None:
    """Transactional HARD delete of a confirmed duplicate loser.

    ``Article.delete()`` cascades to ArticleEntity / ArticleTag; StoryCluster
    canonical FK is SET_NULL. Irreversible — callers must only pass a confirmed
    duplicate. Every purge is logged.
    """
    aid, title, src = article.id, article.title[:80], article.source_name
    with transaction.atomic():
        article.delete()
    logger.warning("dedup purge: hard-deleted duplicate %s '%s' (%s)", aid, title, src)


def merge_into_new(ctx, existing) -> None:
    """Carry the loser's assets onto the winning newcomer before the old row dies.

    Keeps the better image and preserves story-cluster identity so the canonical
    thread survives the swap.
    """
    if not ctx.image_url and existing.featured_image:
        ctx.image_url = existing.featured_image
    if getattr(existing, 'story_cluster_id', None):
        ctx.metadata = ctx.metadata or {}
        ctx.metadata['inherit_story_cluster_id'] = existing.story_cluster_id

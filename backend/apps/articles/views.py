from datetime import timedelta
from xml.sax.saxutils import escape as xml_escape

from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.db.models import Count, Q
from django.http import HttpResponse, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone
from django.views.decorators.csrf import csrf_protect
from django.views.decorators.http import require_GET, require_POST

from apps.core.helpers import get_client_ip
from apps.core.ratelimit import rate_limit
from apps.core.models import Setting
from apps.system.models import ContactMessage, PolicyPage

from .models import Article, Category, Comment, Tag, TopicFollow

# How many relevance-ranked results the full search page will page through.
# Fusion needs a bounded candidate pool; the true match total is reported
# separately via HybridSearch.count().
SEARCH_BROWSE_LIMIT = 100


def _meta(request, **kwargs):
    """Build SEO meta dict for templates. All keys optional, has sensible defaults."""
    site_name = Setting.get('site_name', 'Northern Times')
    base_url = request.build_absolute_uri('/').rstrip('/')
    meta = {
        'title': kwargs.get('title', site_name),
        'description': kwargs.get('description', Setting.get('site_tagline', '')),
        'canonical': kwargs.get('canonical', request.build_absolute_uri()),
        'og_image': kwargs.get('og_image', Setting.get('og_default_image', '')),
        'type': kwargs.get('type', 'website'),
    }
    # Article-specific OG fields
    for key in ('published_time', 'modified_time', 'section', 'tags', 'author_name'):
        if key in kwargs:
            meta[key] = kwargs[key]
    return meta


import json as _json


def _ldjson_safe(s):
    """Escape a JSON string for safe embedding inside a
    <script type="application/ld+json">...</script> block. json.dumps does
    NOT escape < > & so an untrusted (crawled) title could otherwise close
    the script element and inject markup.
    """
    for _ch, _code in (('<', '003c'), ('>', '003e'), ('&', '0026'),
                       ('\u2028', '2028'), ('\u2029', '2029')):
        s = s.replace(_ch, chr(92) + 'u' + _code)
    return s


def _article_ld(article, request):
    """Generate NewsArticle JSON-LD for an article."""
    url = request.build_absolute_uri(f'/article/{article.slug}/')
    ld = {
        '@context': 'https://schema.org',
        '@type': 'NewsArticle',
        'headline': article.title,
        'url': url,
        'mainEntityOfPage': url,
        'description': article.excerpt or '',
        'author': {
            '@type': 'Organization',
            'name': article.display_author or Setting.get('site_name', 'Northern Times'),
        },
        'publisher': {
            '@type': 'Organization',
            'name': Setting.get('site_name', 'Northern Times'),
            'logo': {
                '@type': 'ImageObject',
                'url': Setting.get('site_logo', ''),
            },
        },
    }
    if article.featured_image:
        ld['image'] = article.featured_image
    if article.published_at:
        ld['datePublished'] = article.published_at.isoformat()
    if article.updated_at:
        ld['dateModified'] = article.updated_at.isoformat()
    if article.category:
        ld['articleSection'] = str(article.category)
    # Escape for embedding inside <script type="application/ld+json">…</script>:
    # crawled titles are untrusted, and json.dumps does NOT escape < > & — a
    # title like `</script><img onerror=…>` would otherwise break out and run.
    # (Same escaping Django's json_script applies.)
    return _ldjson_safe(_json.dumps(ld))


# ---------------------------------------------------------------------------
# Frontend views (public, no auth required)
# ---------------------------------------------------------------------------

def home(request):
    """Homepage -- hero article, latest articles by category, breaking news."""
    from apps.articles.services.breaking import BreakingNewsEngine

    # ── Breaking news: use the 4-tier scoring engine ──
    breaking_engine = BreakingNewsEngine()
    breaking = list(breaking_engine.get_breaking_articles(limit=5))
    has_manual_breaking = any(getattr(b, 'is_breaking_manual', False) for b in breaking)

    # If no breaking news scores high enough, show trending instead
    if not breaking:
        breaking = list(Article.objects.published().order_by('-quality_score', '-published_at')[:5])
        has_manual_breaking = False
    breaking_doubled = breaking + breaking

    # ── Hero: random Ugandan headline from last 48h, rotates on each reload ──
    from apps.crawler.models import CrawlSource
    ugandan_source_names = list(
        CrawlSource.objects.filter(region='ugandan', is_active=True)
        .values_list('name', flat=True)
    )
    ugandan_recent = Article.objects.published().filter(
        published_at__gte=timezone.now() - timedelta(hours=48),
        source_name__in=ugandan_source_names,
    )
    import random
    ugandan_count = ugandan_recent.count()
    if ugandan_count:
        hero = ugandan_recent[random.randint(0, ugandan_count - 1)]
    else:
        # Fallback: any Ugandan article, then any article
        fallback_qs = Article.objects.published().filter(source_name__in=ugandan_source_names)
        fb_count = fallback_qs.count()
        hero = fallback_qs[random.randint(0, fb_count - 1)] if fb_count else Article.objects.published().first()

    # ── Latest sidebar (exclude hero) ──
    latest_sidebar = Article.objects.published().exclude(
        id=hero.id if hero else None,
    )[:8]

    # ── Top Stories section: best articles across all categories (last 48h) ──
    top_stories_cutoff = timezone.now() - timedelta(hours=48)
    top_stories = list(
        Article.objects.published()
        .filter(published_at__gte=top_stories_cutoff)
        .order_by('-quality_score', '-published_at')[:9]
    )

    # ── Category sections ──
    layout_variants = ['card', 'thumbnail', 'bento']
    sections = []

    # First section: Top Stories (auto-curated from best articles)
    if top_stories:
        sections.append({
            'name': 'Top Stories',
            'slug': 'top-stories',
            'layout_variant': 'card',
            'articles': top_stories,
        })

    # Remaining sections: all categories with actual articles (skip Top Stories)
    categories = Category.objects.filter(
        show_in_nav=True,
    ).exclude(slug='top-stories').order_by('sort_order')

    for i, cat in enumerate(categories):
        cat_articles = list(Article.objects.published().filter(category=cat)[:9])
        if cat_articles:
            sections.append({
                'name': cat.name,
                'slug': cat.slug,
                'layout_variant': layout_variants[(i + 1) % len(layout_variants)],
                'articles': cat_articles,
            })

    site_name = Setting.get('site_name', 'Northern Times')
    context = {
        'breaking': breaking,
        'breaking_doubled': breaking_doubled,
        'has_manual_breaking': has_manual_breaking,
        'hero': hero,
        'latest_sidebar': latest_sidebar,
        'sections': sections,
        'meta': _meta(request,
            title=f'{site_name} — Independent journalism from Northern Uganda',
            description=Setting.get('site_description', Setting.get('site_tagline', '')),
            canonical=request.build_absolute_uri('/'),
        ),
    }
    return render(request, 'frontend/home.html', context)


def category(request, slug):
    """Category page with paginated articles."""
    # Legacy slug redirect: the "Northern Uganda" lane is now "Local News".
    if slug == 'northern-uganda':
        return redirect('articles_frontend:category', slug='local-news', permanent=True)
    cat = get_object_or_404(Category, slug=slug)
    if slug == 'top-stories':
        base_qs = Article.objects.published()
    else:
        base_qs = Article.objects.published().filter(category=cat)

    # Trending (highest quality) — always from the queryset, before any localization.
    trending = list(base_qs.order_by('-quality_score')[:5])

    localized = False
    reader_city = ''
    articles_qs = base_qs
    if slug == 'local-news':
        from apps.articles.services.geo import reader_location
        loc = reader_location(request)
        if loc:
            reader_city = loc.get('city', '') or ''
            rlat, rlon = loc['lat'], loc['lon']

            # DB-side haversine so ordering + pagination stay in Postgres (scales
            # past the old in-Python sorted()). NULL coords sort last, then recency.
            from django.db.models import F
            from django.db.models.expressions import RawSQL
            distance_sql = (
                '6371 * 2 * asin(sqrt('
                'power(sin(radians(%s - latitude) / 2), 2) + '
                'cos(radians(%s)) * cos(radians(latitude)) * '
                'power(sin(radians(%s - longitude) / 2), 2)))'
            )
            articles_qs = (
                base_qs
                .annotate(distance=RawSQL(distance_sql, (rlat, rlat, rlon)))
                .order_by(F('distance').asc(nulls_last=True), '-published_at')
            )
            localized = True

    paginator = Paginator(articles_qs, 12)
    page = paginator.get_page(request.GET.get('page'))

    context = {
        'category': cat,
        'articles': page,
        'most': trending,
        'localized': localized,
        'reader_city': reader_city,
        'meta': _meta(request,
            title=f'{cat.name} — {Setting.get("site_name", "Northern Times")}',
            description=cat.description or f'Latest {cat.name} news and analysis.',
            canonical=request.build_absolute_uri(),
        ),
    }
    return render(request, 'frontend/category.html', context)


def article(request, slug):
    """Article detail page with comments, related articles, and share buttons."""
    art = get_object_or_404(
        Article.objects.published().select_related('category', 'author'),
        slug=slug,
    )

    # Track the page view
    from apps.analytics.models import ArticleView
    ArticleView.objects.create(
        article=art,
        visitor_ip=get_client_ip(request),
    )

    # Approved comments (threaded)
    comments = (
        art.comments
        .filter(status='approved')
        .select_related('parent')
        .order_by('created_at')
    )

    # Tags
    tags = art.tags.all()

    # Related articles — story cluster, then semantic (embedding) similarity,
    # then same-category recency as a top-up. See services.related.
    from apps.articles.services.related import related_articles
    related = related_articles(art, limit=6)

    # Continuing-story block (same cluster) — rendered as its own section.
    cluster_articles = []
    if art.story_cluster_id:
        cluster_articles = list(
            Article.objects.published()
            .filter(story_cluster_id=art.story_cluster_id)
            .exclude(id=art.id)
            .order_by('-published_at')[:5]
        )

    # Named entities
    entities = art.entities.all()[:10]

    # AI summary
    ai_summary = art.ai_summary

    article_url = request.build_absolute_uri(f'/article/{art.slug}/')

    # Use rewritten content if available, otherwise original
    article_content = art.rewritten_content or art.content or ''

    # Strip "Recommended Stories" junk from crawled content
    import re as _re
    article_content = _re.sub(
        r'<h2[^>]*>\s*(?:Recommended\s+Stories|More\s+(?:from|on|stories)|'
        r'Related\s+(?:Stories|Articles|Coverage)).*',
        '', article_content, flags=_re.I | _re.S,
    )
    # Strip "list of N items" / "list X of Y" noise
    article_content = _re.sub(r'list\s+of\s+\d+\s+items?\s*', '', article_content, flags=_re.I)
    article_content = _re.sub(r'list\s+\d+\s+of\s+\d+', '', article_content, flags=_re.I)
    article_content = _re.sub(r'end\s+of\s+list\s*', '', article_content, flags=_re.I)

    # Drop ALL inline images/figures from the body — the article carries only its
    # featured (hero) image. Crawled bodies otherwise stack loose thumbnails that
    # read as a mess. Render-time + idempotent, so no re-crawl needed.
    article_content = _re.sub(r'<figure\b[^>]*>.*?</figure>', '', article_content, flags=_re.I | _re.S)
    article_content = _re.sub(r'<picture\b[^>]*>.*?</picture>', '', article_content, flags=_re.I | _re.S)
    article_content = _re.sub(r'<img\b[^>]*>', '', article_content, flags=_re.I)
    article_content = _re.sub(r'<figcaption\b[^>]*>.*?</figcaption>', '', article_content, flags=_re.I | _re.S)

    # Format published date
    pub_date = ''
    pub_time = ''
    if art.published_at:
        pub_date = art.published_at.strftime('%B %d, %Y')
        pub_time = art.published_at.strftime('%I:%M %p')

    # Reading time
    read_mins = art.reading_time or 1

    # Author card data — crawled/aggregated articles are attributed to the site
    # admin ("Editorial Desk"); human-written articles show the actual author so
    # their byline and profile bio appear.
    from apps.accounts.models import User

    def _best_name(u):
        if not u:
            return ''
        return ((u.display_name or '').strip()
                or (u.get_full_name() or '').strip()
                or u.username)

    if art.is_crawled:
        author = User.objects.filter(is_superuser=True).order_by('id').first()
        author_name = _best_name(author) or 'The Northern Times'
        author_role = 'Editorial Desk'
    else:
        author = art.author
        author_name = _best_name(author) or art.display_author or 'Staff'
        author_role = ''
        if author and author.role:
            author_role = author.role.name.replace('_', ' ').title()

    # SEO meta
    article_desc = art.excerpt or art.ai_summary or ''
    if len(article_desc) > 160:
        article_desc = article_desc[:157] + '...'
    tag_names = [t.name for t in tags]

    context = {
        'article': art,
        'article_url': article_url,
        'article_content': article_content,
        'article_tags': tags,
        'pub_date': pub_date,
        'pub_time': pub_time,
        'has_sidebar_ad': True,
        'read_mins': read_mins,
        'author': author,
        'author_name': author_name,
        'author_role': author_role,
        'comments': comments,
        'tags': tags,
        'related': related,
        'cluster_articles': cluster_articles,
        'entities': entities,
        'ai_summary': ai_summary,
        'article_ld_json': _article_ld(art, request),
        'meta': _meta(request,
            title=f'{art.title} — {Setting.get("site_name", "Northern Times")}',
            description=article_desc,
            canonical=article_url,
            og_image=art.featured_image or '',
            type='article',
            published_time=art.published_at.isoformat() if art.published_at else '',
            modified_time=art.updated_at.isoformat() if art.updated_at else '',
            section=str(art.category) if art.category else '',
            tags=tag_names,
            author_name=author_name,
        ),
    }
    return render(request, 'frontend/article.html', context)


def author(request, username):
    """Author page with their published articles."""
    from apps.accounts.models import User

    user = get_object_or_404(User, username=username)
    articles_qs = Article.objects.published().filter(author=user)
    display_name = user.get_full_name() or user.username
    author_url = request.build_absolute_uri()

    paginator = Paginator(articles_qs, 12)
    page = paginator.get_page(request.GET.get('page'))

    context = {
        'author_user': user,
        'author': user,
        'display_name': display_name,
        'author_url': author_url,
        'author_role': getattr(user.role, 'name', 'Writer').replace('_', ' ').title() if user.role else 'Writer',
        'articles': page,
        'result': {'total': articles_qs.count(), 'pages': paginator.num_pages, 'page': page.number},
        'page_range': paginator.page_range,
        'meta': _meta(request,
            title=f'{display_name} — {Setting.get("site_name", "Northern Times")}',
            description=f'Articles by {display_name}. {articles_qs.count()} articles published.',
            canonical=author_url,
            type='profile',
        ),
    }
    return render(request, 'frontend/author.html', context)


def tag(request, slug):
    """Tag page with paginated articles."""
    t = get_object_or_404(Tag, slug=slug)
    articles_qs = Article.objects.published().filter(tags=t)

    paginator = Paginator(articles_qs, 12)
    page = paginator.get_page(request.GET.get('page'))

    # Related tags
    related = Tag.objects.exclude(pk=t.pk).order_by('?')[:12]

    context = {
        'tag': t,
        'articles': page,
        'related': related,
        'result': {'total': articles_qs.count(), 'pages': paginator.num_pages, 'page': page.number},
        'page_range': paginator.page_range,
        'meta': _meta(request,
            title=f'#{t.name} — {Setting.get("site_name", "Northern Times")}',
            description=f'{articles_qs.count()} articles tagged "{t.name}".',
            canonical=request.build_absolute_uri(),
        ),
    }
    return render(request, 'frontend/tag.html', context)


def search(request):
    """Full-text search results page."""
    query = request.GET.get('q', '').strip()
    results = []
    # True match total for the header. May exceed len(results): the browsable set
    # is a bounded, relevance-ranked top-N (fusion needs a candidate cap), so we
    # report the honest total but only page through the top results.
    result_count = 0

    def _legacy_keyword_search():
        return list(
            Article.objects.published()
            .filter(
                Q(title__icontains=query)
                | Q(content__icontains=query)
                | Q(excerpt__icontains=query)
                | Q(ai_summary__icontains=query)
            )
            .select_related('category')
        )

    if query:
        try:
            from apps.articles.services.search import HybridSearch
            hybrid = HybridSearch()
            results = hybrid.search(query, limit=SEARCH_BROWSE_LIMIT)
            result_count = hybrid.count(query)
        except Exception:
            results = []
        # Fall back to substring search when the hybrid ranker returns nothing.
        # Each ranker swallows its own errors and returns [], so a misconfigured
        # index degrades to [] without raising — this recovers real matches the
        # token-based FTS/trigram missed (and covers total ranker failure).
        if not results:
            results = _legacy_keyword_search()
            result_count = len(results)

    paginator = Paginator(results, 12)
    page = paginator.get_page(request.GET.get('page'))

    # Trending articles for empty search
    trending = Article.objects.published().order_by('-quality_score', '-published_at')[:5]
    popular_tags = Tag.objects.annotate(count=Count('article_tags')).order_by('-count')[:20]
    desc = f'Search results for "{query}" — {result_count} articles found.' if query else 'Search articles.'
    context = {
        'query': query,
        'articles': page,
        'result_count': result_count,
        'trending': trending,
        'tags': popular_tags,
        'meta': _meta(request,
            title=f'Search: {query} — {Setting.get("site_name", "Northern Times")}' if query else f'Search — {Setting.get("site_name", "Northern Times")}',
            description=desc,
        ),
    }
    return render(request, 'frontend/search.html', context)


def about(request):
    """Static about page."""
    site_name = Setting.get('site_name', 'Northern Times')
    return render(request, 'frontend/about.html', {
        'meta': _meta(request,
            title=f'About — {site_name}',
            description=f'{site_name} is an independent digital news platform delivering reporting from Northern Uganda and East Africa.',
        ),
    })


def contact(request):
    """Contact page with form submission."""
    site_name = Setting.get('site_name', 'Northern Times')
    meta = _meta(request,
        title=f'Contact Us — {site_name}',
        description=f'Get in touch with {site_name}. Send us news tips, feedback, or inquiries.',
    )
    if request.method == 'POST':
        msg = ContactMessage.objects.create(
            name=request.POST.get('name', ''),
            email=request.POST.get('email', ''),
            subject=request.POST.get('subject', ''),
            message=request.POST.get('message', ''),
            ip_address=get_client_ip(request),
        )
        try:
            from apps.newsletter.services.mailer import send_contact_alert
            send_contact_alert(msg)
        except Exception:
            pass  # An alert failure must never break the user's submission.
        return render(request, 'frontend/contact.html', {'success': True, 'meta': meta})

    return render(request, 'frontend/contact.html', {'meta': meta})


def policy(request, slug):
    """Policy page (privacy, terms, etc.)."""
    page = get_object_or_404(PolicyPage, slug=slug, is_published=True)
    return render(request, 'frontend/policy.html', {
        'policy': page,
        'page': page,  # template compatibility
        'meta': _meta(request,
            title=f'{page.title} — {Setting.get("site_name", "Northern Times")}',
            description=f'{page.title} for {Setting.get("site_name", "Northern Times")}.',
        ),
    })


def robots_txt(request):
    """Serve robots.txt for search engine crawlers."""
    base_url = request.build_absolute_uri('/')
    content = (
        'User-agent: *\n'
        'Allow: /\n'
        'Disallow: /admin/\n'
        'Disallow: /django-admin/\n'
        'Disallow: /api/\n'
        '\n'
        f'Sitemap: {base_url}sitemap.xml\n'
    )
    return HttpResponse(content, content_type='text/plain')


def rss_feed(request):
    """RSS feed of latest published articles."""
    articles = Article.objects.published()[:20]

    items = []
    for a in articles:
        pub_date = ''
        if a.published_at:
            pub_date = a.published_at.strftime('%a, %d %b %Y %H:%M:%S +0000')
        items.append(
            f'<item>'
            f'<title>{xml_escape(a.title or "")}</title>'
            f'<link>{xml_escape(request.build_absolute_uri(f"/article/{a.slug}/"))}</link>'
            f'<description>{xml_escape(a.excerpt or "")}</description>'
            f'<pubDate>{pub_date}</pubDate>'
            f'</item>'
        )

    site_name = Setting.get('site_title', 'News')
    site_desc = Setting.get('site_description', '')

    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>'
        '<rss version="2.0">'
        '<channel>'
        f'<title>{xml_escape(site_name)}</title>'
        f'<description>{xml_escape(site_desc)}</description>'
        f'<link>{request.build_absolute_uri("/")}</link>'
        f'{"".join(items)}'
        '</channel>'
        '</rss>'
    )
    return HttpResponse(xml, content_type='application/rss+xml')


def sitemap(request):
    """Paginated sitemap XML."""
    from datetime import datetime

    month = request.GET.get('month')
    articles = Article.objects.published()

    if month:
        try:
            dt = datetime.strptime(month, '%Y-%m')
            articles = articles.filter(
                published_at__year=dt.year,
                published_at__month=dt.month,
            )
        except ValueError:
            pass

    urls = []
    for a in articles[:5000]:
        urls.append(
            f'<url>'
            f'<loc>{xml_escape(request.build_absolute_uri(f"/article/{a.slug}/"))}</loc>'
            f'<lastmod>{a.updated_at.strftime("%Y-%m-%d")}</lastmod>'
            f'</url>'
        )

    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        f'{"".join(urls)}'
        '</urlset>'
    )
    return HttpResponse(xml, content_type='application/xml')


# ---------------------------------------------------------------------------
# API views (JSON responses)
# ---------------------------------------------------------------------------

@require_GET
def search_api(request):
    """AJAX search endpoint."""
    query = request.GET.get('q', '').strip()
    if not query or len(query) < 2:
        return JsonResponse({'results': []})

    try:
        # Keyword + trigram only — skip the embedder on the low-latency
        # autocomplete path. Semantic blending is reserved for the full page.
        from apps.articles.services.search import HybridSearch
        articles = HybridSearch(use_semantic=False).search(query, limit=10)
    except Exception:
        articles = Article.objects.published().filter(
            Q(title__icontains=query) | Q(excerpt__icontains=query) | Q(ai_summary__icontains=query)
        ).select_related('category')[:10]

    results = [
        {
            'title': a.title,
            'slug': a.slug,
            'excerpt': a.excerpt[:100],
            'category': a.category.name if a.category else '',
            'reading_time': a.reading_time,
            'image': a.featured_image,
        }
        for a in articles
    ]
    return JsonResponse({'results': results})


@require_GET
def tags_api(request):
    """JSON list of tags."""
    tags = list(Tag.objects.values('name', 'slug', 'type')[:100])
    return JsonResponse({'tags': tags})


@require_GET
def tag_search_api(request):
    """JSON tag search / autocomplete."""
    q = request.GET.get('q', '')
    tags = list(
        Tag.objects.filter(name__icontains=q).values('name', 'slug')[:20]
    )
    return JsonResponse({'tags': tags})


@require_GET
def trending_api(request):
    """JSON trending articles."""
    articles = (
        Article.objects.published()
        .select_related('category')
        .order_by('-quality_score', '-published_at')[:10]
    )
    results = [
        {
            'title': a.title,
            'slug': a.slug,
            'category': a.category.name if a.category else '',
        }
        for a in articles
    ]
    return JsonResponse({'articles': results})


@require_POST
@csrf_protect
@rate_limit('comment', limit=5, window=60)
def comment_post(request):
    """Submit a comment via AJAX."""
    article_id = request.POST.get('article_id')
    name = request.POST.get('name', '').strip()
    email = request.POST.get('email', '').strip()
    content = request.POST.get('content', '').strip()

    # Validate required fields before touching the DB — a missing name/email/
    # content previously created a blank comment and still returned success.
    errors = {}
    if not name:
        errors['name'] = 'Name is required.'
    if not email or '@' not in email:
        errors['email'] = 'A valid email is required.'
    if not content:
        errors['content'] = 'Comment cannot be empty.'
    if errors:
        return JsonResponse({'success': False, 'errors': errors}, status=400)

    art = get_object_or_404(Article, id=article_id)

    # Validate parent (reply target): must be a comment ON THIS ARTICLE. An
    # unchecked id could thread a reply onto another article or 500 on a bad id.
    parent_id = request.POST.get('parent_id') or None
    if parent_id and not Comment.objects.filter(id=parent_id, article=art).exists():
        parent_id = None

    Comment.objects.create(
        article=art,
        name=name,
        email=email,
        content=content,
        ip_address=get_client_ip(request),
        parent_id=parent_id,
    )
    return JsonResponse({'success': True})


@require_POST
@csrf_protect
@rate_limit('follow', limit=10, window=60)
def follow_topic(request):
    """Subscribe to a topic (category or tag) by email."""
    email = request.POST.get('email', '')
    follow_type = request.POST.get('type', '')
    follow_id = request.POST.get('id', '')

    if email and follow_type and follow_id:
        TopicFollow.objects.get_or_create(
            email=email,
            follow_type=follow_type,
            follow_id=follow_id,
        )
    return JsonResponse({'success': True})


@require_POST
@csrf_protect
@rate_limit('unfollow', limit=10, window=60)
def unfollow_topic(request):
    """Unsubscribe from a topic."""
    email = request.POST.get('email', '')
    follow_type = request.POST.get('type', '')
    follow_id = request.POST.get('id', '')

    TopicFollow.objects.filter(
        email=email,
        follow_type=follow_type,
        follow_id=follow_id,
    ).delete()
    return JsonResponse({'success': True})


def topic_unfollow(request, token):
    """One-click unfollow from a topic-notification email (tokenized)."""
    follow = TopicFollow.objects.filter(unfollow_token=token).first() if token else None
    if not follow:
        return render(request, 'frontend/topic_unfollow.html',
                      {'status': 'error', 'message': 'This link is invalid or has expired.'})
    label = ''
    if follow.follow_type == 'category':
        cat = Category.objects.filter(id=follow.follow_id).first()
        label = cat.name if cat else ''
    else:
        t = Tag.objects.filter(id=follow.follow_id).first()
        label = t.name if t else ''
    email = follow.email
    follow.delete()
    return render(request, 'frontend/topic_unfollow.html',
                  {'status': 'success', 'email': email, 'topic': label})


@require_POST
def share_track(request):
    """Track a social share event (fire-and-forget)."""
    return JsonResponse({'success': True})


@require_POST
def engagement_track(request):
    """Track article engagement (scroll depth, time on page)."""
    return JsonResponse({'success': True})


@require_GET
def syndication_list(request):
    """API v1: paginated list of published articles for syndication partners."""
    articles_qs = Article.objects.published().select_related('category')
    paginator = Paginator(articles_qs, 20)
    page = paginator.get_page(request.GET.get('page'))

    results = [
        {
            'id': str(a.id),
            'title': a.title,
            'slug': a.slug,
            'excerpt': a.excerpt,
            'featured_image': a.featured_image,
            'published_at': a.published_at.isoformat() if a.published_at else None,
            'category': a.category.name if a.category else None,
            'source_url': a.source_url,
            'source_name': a.source_name,
        }
        for a in page
    ]
    return JsonResponse({
        'articles': results,
        'page': page.number,
        'total_pages': paginator.num_pages,
    })


@require_GET
def syndication_detail(request, slug):
    """API v1: single article detail for syndication."""
    a = get_object_or_404(
        Article.objects.published().select_related('category', 'author'),
        slug=slug,
    )
    data = {
        'id': str(a.id),
        'title': a.title,
        'slug': a.slug,
        'excerpt': a.excerpt,
        'content': a.content,
        'featured_image': a.featured_image,
        'published_at': a.published_at.isoformat() if a.published_at else None,
        'category': a.category.name if a.category else None,
        'author': a.display_author or (a.author.display_name if a.author else ''),
        'tags': list(a.tags.values_list('name', flat=True)),
    }
    return JsonResponse(data)


# ---------------------------------------------------------------------------
# Admin views (stubs -- will be fully implemented in admin views agent)
# ---------------------------------------------------------------------------

@login_required
def admin_dashboard(request):
    """Admin dashboard with stats overview."""
    return render(request, 'admin/dashboard.html')


@login_required
def article_list(request):
    """Admin article listing with filters."""
    return render(request, 'admin/articles/list.html')


@login_required
def article_create(request):
    """Create a new article."""
    return render(request, 'admin/articles/create.html')


@login_required
def article_edit(request, pk):
    """Edit an existing article."""
    return render(request, 'admin/articles/edit.html', {'pk': pk})


@login_required
def article_delete(request, pk):
    """Soft-delete an article."""
    return render(request, 'admin/articles/delete.html', {'pk': pk})


@login_required
def article_restore(request, pk):
    """Restore a soft-deleted article."""
    return render(request, 'admin/articles/restore.html', {'pk': pk})


@login_required
def archive(request):
    """View archived/soft-deleted articles."""
    return render(request, 'admin/articles/archive.html')


@login_required
def bulk_archive(request):
    """Bulk archive selected articles."""
    return render(request, 'admin/articles/bulk_archive.html')


@login_required
def category_list(request):
    """Admin category listing."""
    return render(request, 'admin/categories/list.html')


@login_required
def category_create(request):
    """Create a new category."""
    return render(request, 'admin/categories/create.html')


@login_required
def category_edit(request, pk):
    """Edit a category."""
    return render(request, 'admin/categories/edit.html', {'pk': pk})


@login_required
def comment_list(request):
    """Admin comment listing."""
    return render(request, 'admin/comments/list.html')


@login_required
def comment_moderate(request, pk):
    """Moderate (approve/reject) a comment."""
    return render(request, 'admin/comments/moderate.html', {'pk': pk})


@login_required
def review_list(request):
    """List articles pending editorial review."""
    return render(request, 'admin/review/list.html')


@login_required
def review_show(request, pk):
    """Show a single article for review."""
    return render(request, 'admin/review/show.html', {'pk': pk})

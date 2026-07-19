# THE NORTHERN TIMES

**Django-powered digital newsroom platform for Northern Uganda and East Africa.**

**Version:** 2.0 (Django) | **Stack:** Django 5.1 / PostgreSQL + pgvector / Redis / Celery / Nginx / Docker
**Codebase:** 210 Python files, 81 templates, 43 static assets | **Status:** Production-ready

---

## Overview

The Northern Times is a full-featured news platform with automated content aggregation, AI-powered enrichment, and a custom editorial admin panel. It crawls 60+ news sources, classifies articles by category using ML, rewrites content via OpenAI, and serves a responsive frontend for readers.

### Key Capabilities

- **Automated News Crawler** -- 60+ RSS and Selenium-scraped sources across Uganda, East Africa, and international outlets
- **AI Content Pipeline** -- Category classification (DeBERTa), sentiment analysis, NER, summarization, embeddings, quality scoring, keyword extraction, quote extraction
- **4-Layer Deduplication** -- URL hash, exact title, Jaccard fuzzy title (0.55), pgvector semantic similarity (0.82)
- **OpenAI Rewriter** -- GPT-4o-mini rewrites with auto-enrich mode for short/low-quality articles
- **CKEditor 5 Editor** -- 35-plugin rich text editor on articles, policies, and popups
- **Custom Admin Panel** -- Dark/light theme, system-style modals, profile dropdown, CKEditor everywhere
- **Security Hardened** -- Open redirect protection, XML escaping, CSRF enforcement, postMessage origin checks, safe DOM construction, authenticated API endpoints
- **Newsletter System** -- Compose, schedule, queue, SMTP delivery with Postfix + DKIM
- **SEO Audit Engine** -- 26-point site audit with 0-100 scoring (async via Celery)
- **Social Monitor** -- Track brand mentions across Google News, Reddit, Bing, Hacker News
- **Popup/Banner System** -- 5 types, A/B testing, live preview, device targeting
- **Analytics** -- Visitor tracking (GeoIP, UA parsing), article views, daily aggregation, device/geo breakdown, CSV export
- **PWA** -- Service worker, push notifications, offline support

---

## Architecture

```
                    +-----------+
                    |  Nginx    |  :8080 (dev) / :443 (prod)
                    +-----+-----+
                          |
              +-----------+-----------+
              |                       |
        +-----+-----+         +------+------+
        |  Django    |         | Static/Media|
        |  Gunicorn  |         | (nginx)     |
        +-----+-----+         +-------------+
              |
    +---------+---------+
    |         |         |
+---+---+ +--+--+ +----+----+
|Postgres| |Redis| | Selenium |
|pgvector| |     | | Chromium |
+--------+ +-----+ +---------+
              |
    +---------+---------+
    |                   |
+---+------+    +-------+-----+
| Celery   |    | Celery      |
| Worker   |    | Beat        |
+----------+    +-------------+
```

### Docker Services (9 containers)

| Service | Image | Purpose |
|---------|-------|---------|
| **django** | python:3.12-slim + gunicorn | Web app (4 workers) |
| **web** | nginx:1.27-alpine | Reverse proxy, static files |
| **db** | pgvector/pgvector:pg15 | PostgreSQL + vector extensions |
| **redis** | redis:7-alpine | Cache + Celery broker |
| **celery-worker** | same as django | Background tasks (6GB, 2 workers, max 50 tasks) |
| **celery-beat** | same as django | Task scheduler |
| **mail** | boky/postfix:v4.3.0 | SMTP with DKIM signing |
| **selenium** | selenium/standalone-chromium | Browser automation for scraping |
| **pgadmin** | dpage/pgadmin4 | PostgreSQL admin UI (:5050, localhost only) |

---

## Project Structure

```
backend/
  manage.py
  gunicorn.conf.py
  config/
    settings/          # base.py, development.py, production.py
    urls.py            # Top-level URL routing
    celery.py          # 14 periodic tasks
    wsgi.py / asgi.py
  apps/
    accounts/          # User, Role, ActiveSession, auth, RBAC (3 permission levels)
    articles/          # Article, Category, Tag, Comment, Revision, StoryCluster, Entity, TopicFollow
    crawler/           # CrawlSource, CrawlLog, engine, fetchers, extractors, cleaners
    enrichment/        # 10 AI services (embedder, summarizer, NER, sentiment, category, quality, dedup, keywords, quotes)
    rewriter/          # OpenAI GPT-4o-mini rewriter with enrich mode
    media/             # MediaItem, image downloader, optimizer, health checker
    newsletter/        # Subscriber, Issue, EmailQueue, PushSubscription, push notifications
    analytics/         # SiteVisitor, ArticleView, DailyStats, GeoIP, UA parser, aggregator
    ads/               # AdSlot, Popup (5 types, A/B testing, targeting)
    seo/               # SEOAudit, SEOIssue, 26-point audit engine
    social/            # SocialKeyword, SocialMention, multi-platform monitor
    webhooks/          # Webhook, WebhookLog, event dispatcher
    system/            # SystemLog, CronRun, DbBackup, PolicyPage, LoginQuote, Notification, ContactMessage
    core/              # Setting, context processors, template tags, helpers, error handlers
  templates/
    admin/             # 64 admin templates (layout, dashboard, forms, lists)
    frontend/          # 11 public templates (home, article, category, search, etc.)
    errors/            # Branded 403, 404, 500 error pages
  requirements/
    base.txt           # 60+ packages
    development.txt
    production.txt

frontend/
  static/
    css/               # 7 stylesheets (custom design system, dark mode)
    js/                # 7 scripts (vanilla JS, service worker, CKEditor integration)
    fonts/             # Custom web fonts
  robots.txt
  sitemap.xml
```

### Models (42 across 14 apps)

| App | Models | Key Features |
|-----|--------|-------------|
| accounts | User, Role, ActiveSession | Auth, RBAC (3 levels), password reset, session tracking |
| articles | Article, Category, Tag, Comment, Revision, StoryCluster, Entity, TopicFollow | pgvector embeddings, soft delete, breaking news scoring |
| crawler | CrawlSource, CrawlLog | RSS + Selenium fetchers, 5-strategy BS4 extractor, 11-phase content cleaner |
| enrichment | (service-only) | DeBERTa classifier, all-MiniLM embedder, distilBART summarizer, spaCy NER |
| rewriter | (uses Article) | GPT-4o-mini rewrite + enrich mode for quality < 50 or words < 300 |
| media | MediaItem | Upload, download, hash dedup, health check |
| newsletter | Subscriber, Issue, EmailQueue, PushSubscription | Compose, schedule, queue, digest, web push |
| analytics | SiteVisitor, ArticleView, DailyStats | GeoIP, UA parsing, hourly aggregation, CSV export |
| ads | AdSlot, Popup | 5 popup types, device targeting, A/B testing |
| seo | SEOAudit, SEOIssue | 26-check audit, async via Celery |
| social | SocialKeyword, SocialMention | Google News, Reddit, Bing, HN monitoring |
| webhooks | Webhook, WebhookLog | Event dispatch, delivery logging |
| system | SystemLog, CronRun, DbBackup, PolicyPage, LoginQuote, Notification, ContactMessage, PasswordReset | Admin tools, backups, factory reset |
| core | Setting | Context processors, template tags, sanitizer, helpers |

---

## AI Models (loaded lazily in-process)

| Model | Size | Purpose |
|-------|------|---------|
| DeBERTa v3 (MoritzLaurer) | ~1.5GB | Category + sentiment classification |
| all-MiniLM-L6-v2 | ~80MB | 384-dim embeddings for dedup + similarity |
| distilbart-cnn-12-6 | ~1.2GB | Article summarization |
| spaCy en_core_web_sm | ~12MB | Named entity recognition |
| OpenAI GPT-4o-mini | API | Article rewriting + enrichment |

### Enrichment Pipeline (10 services)

| Service | Module | Description |
|---------|--------|-------------|
| Category Classifier | `category_classifier.py` | Zero-shot DeBERTa-v3 classification |
| Sentiment Analyzer | `sentiment.py` | Zero-shot sentiment scoring |
| Embedder | `embedder.py` | all-MiniLM-L6-v2 384-dim vectors |
| Summarizer | `summarizer.py` | Abstractive summaries via distilBART |
| NER | `ner.py` | Named entity extraction via spaCy |
| Quality Scorer | `quality.py` | Heuristic 0-100 content quality score |
| Keyword Extractor | `keywords.py` | Keyword ranking and extraction |
| Quote Extractor | `quotes.py` | Pull quote extraction from content |
| Deduplicator | `dedup.py` | 4-layer duplicate detection |
| Async Tasks | `tasks.py` | Celery tasks for AI reclassification + cleanup |

---

## Crawler Pipeline

```
CrawlSource (60+ sources, RSS/Web/Selenium/Social)
  -> Fetcher (rss.py, web.py, browser.py, social.py)
    -> BS4 Extractor (5 strategies: trafilatura, newspaper3k, readability, text-density, OG meta)
      -> Content Cleaner (11-phase DOM cleaning pipeline)
        -> Enrichment (category, sentiment, embeddings, summary, NER, quality, keywords, quotes)
          -> Dedup (URL hash -> exact title -> Jaccard fuzzy -> pgvector semantic)
            -> Store (Article with 384-dim VectorField)
```

### Deduplication (4 layers)

1. **URL Hash** -- SHA-256 of normalized URL
2. **Exact Title** -- Case-insensitive title match
3. **Fuzzy Title** -- Jaccard similarity > 0.55
4. **Semantic** -- pgvector cosine similarity > 0.82 (384-dim embeddings)

---

## Analytics System

### Visitor Tracking (middleware)
- Records one `SiteVisitor` per IP per calendar day
- Extracts device type, browser, OS via regex-based UA parser
- GeoIP lookup via MaxMind GeoLite2 or ip-api.com fallback (cached 24h in Redis)
- Skips static files, AJAX, non-GET, bots
- All errors caught -- never breaks the response

### Article Views
- `ArticleView` created on each article detail page visit
- No per-view deduplication (raw event log)
- Used for top articles ranking and performance metrics

### Daily Aggregation
- Celery Beat runs `aggregate_stats` every hour
- Aggregates `SiteVisitor` + `ArticleView` into `DailyStats`
- Dashboard shows live today data from raw tables (no Celery delay)

### Dashboard Features
- Live today counters (visitors + views)
- 30-day and 7-day summary cards
- Chart.js line chart (views + visitors over time)
- Performance dashboard (top 20 articles by views)
- Engagement dashboard (device breakdown, top countries, visitor map)
- Achievement badges (content milestones)
- CSV export with date range filtering

---

## Celery Beat Schedule (14 tasks)

| Task | Schedule | Description |
|------|----------|-------------|
| crawl-news-sources | every 5 min | Crawl all due sources |
| process-rewrite-queue | every 10 min | Rewrite queued articles |
| publish-scheduled-articles | every 1 min | Auto-publish scheduled articles |
| send-due-newsletters | every 1 min | Send scheduled newsletters |
| process-email-queue | every 2 min | Deliver queued emails |
| aggregate-daily-stats | every hour | Aggregate daily analytics |
| ai-reclassify-articles | daily 5am | AI category reclassification |
| cleanup-duplicates | every 12 hours | Archive semantic duplicates |
| check-image-health | daily 4am | Verify image URLs |
| run-seo-audit | Sunday 6am | Full site SEO audit |
| scan-social-mentions | every 6 hours | Monitor social mentions |
| daily-maintenance | daily 2am | Cleanup, optimize |
| daily-db-backup | daily 3am | PostgreSQL backup |
| weekly-digest | Sunday 8am | Weekly newsletter digest |

---

## Frontend

- **Static site** with Django templates (no SPA framework)
- **CSS** -- Custom design system with CSS variables, dark mode support
- **JS** -- Vanilla JavaScript (no jQuery/React), CKEditor 5 CDN for admin
- **PWA** -- Service worker, web app manifest, push notifications
- **Responsive** -- Mobile-first with drawer navigation

### Public Pages (11)
Home, Category, Article, Author, Tag, Search, About, Contact, Policy, Unsubscribe, RSS/Sitemap

### Admin Pages (42+)
Dashboard, Articles (CRUD + archive), Categories, Comments, Review Queue, Media Library, Newsletter (compose + queue), Subscribers, Analytics (performance + engagement), Ad Placements, Popups (live preview), Social Monitor, Crawler (sources + logs + settings), AI Rewriter, SEO Audit, Settings, Users, Roles (section-grouped permissions), System (backups + logs + mail queue + resets), Webhooks, Profile

---

## Quick Start

### Prerequisites
- Docker and Docker Compose
- WSL 2 (Windows) or Linux

### Setup

```bash
# Clone
git clone https://github.com/bododesderio/northern-times.git
cd northern-times

# Copy environment file
cp .env.example .env
# Edit .env with your settings (DJANGO_SECRET_KEY, OPENAI_API_KEY, etc.)

# Build and start (use WSL on Windows)
# Prefer ./deploy.sh — it runs the compose up AND restarts nginx afterwards so
# edits to docker/nginx/*.conf take effect (single-file bind mounts pin the
# inode, so a plain `up -d` keeps serving the old nginx config).
./deploy.sh                # dev stack
# ./deploy.sh --prod       # production stack (docker-compose.django.prod.yml)

# Equivalent manual steps:
#   docker compose -f docker-compose.django.yml up -d --build
#   docker compose -f docker-compose.django.yml restart web

# The entrypoint automatically:
# - Runs migrations
# - Collects static files
# - Seeds roles, categories, sources, ad slots, policies
# - Creates admin user (admin/admin)

# Seed development data (optional)
docker compose -f docker-compose.django.yml exec -w /app/backend django python manage.py seed_dev_data

# Access
# Frontend:  http://localhost:8080
# Admin:     http://localhost:8080/admin/login/
# API:       http://localhost:8080/api/health/
# pgAdmin:   http://localhost:5050
```

### Management Commands (9)

```bash
# Run inside container:
docker compose -f docker-compose.django.yml exec -w /app/backend django python manage.py <command>

seed_all                       # Seed default data (roles, categories, sources, ad slots, policies)
seed_roles                     # Seed roles only
seed_dev_data                  # Seed test data (users, articles, comments, analytics)
deduplicate_articles           # Remove duplicate articles by title (case-insensitive)
reclassify_articles            # Re-categorize articles via keyword + AI geo-routing
reclassify_articles --dry-run  # Preview reclassification without changes
fix_broken_images --limit=500  # Download external images locally
crawl                          # Trigger manual crawl
db_backup                      # Create database backup
migrate_from_php               # Migrate data from legacy PHP tables
```

---

## Crawl Sources (30 seeded, 60+ via admin)

### By Region
- **Ugandan (7):** Daily Monitor, New Vision, Nile Post, The Observer, The Independent, Uganda Radio Network, Dokolo Post
- **East African (2):** The East African, Nation Africa
- **International (5):** BBC Africa, Al Jazeera Africa, Reuters Africa, AP News Africa, VOA Africa

### By Category
- **Sports (5):** BBC Sport Africa, ESPN, Goal.com, KickOff, SuperSport
- **Entertainment (3):** Pulse Uganda, Sqoop Entertainment, BBC Culture
- **Lifestyle (2):** Monitor Lifestyle, BBC Travel
- **Business (1):** Business Daily Africa
- **Health (1):** WHO Africa
- **Technology (1):** TechCrunch Africa

*Additional sources can be added via the admin panel (Crawler > Sources).*

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DJANGO_SECRET_KEY` | (required) | Django secret key |
| `DJANGO_SETTINGS_MODULE` | `config.settings.production` | Settings module |
| `DB_HOST` | `db` | PostgreSQL host |
| `DB_NAME` | `northern_times` | Database name |
| `DB_USER` | `northern_times` | Database user |
| `DB_PASSWORD` | (required) | Database password |
| `REDIS_URL` | `redis://redis:6379/0` | Redis connection |
| `OPENAI_API_KEY` | (optional) | For article rewriting |
| `OPENAI_MODEL` | `gpt-4o-mini` | OpenAI model |
| `REWRITER_ENABLED` | `true` | Enable/disable rewriter |
| `APP_DOMAIN` | `localhost` | Site domain |
| `APP_NAME` | `The Northern Times` | Site name |
| `SECURE_SSL_REDIRECT` | `false` | Enable HTTPS redirect |
| `CRAWLER_FETCH_TIMEOUT` | `15` | Crawler HTTP timeout (seconds) |
| `CRAWLER_DEDUP_THRESHOLD` | `0.82` | Semantic dedup threshold |

---

## Admin Features

### Article Editor
- CKEditor 5 with 35 plugins (headings, formatting, tables, images, code, media embeds)
- Single save button with status dropdown (Draft, Pending Review, Published, Scheduled)
- 2-column layout: content editor + metadata sidebar
- Image upload from device, media library, or URL
- Tag chip input, word count, revision history

### System-Style UI
- Custom confirm/prompt modals (no browser defaults)
- Toast notifications
- Profile dropdown in topbar
- Custom styled select dropdowns
- Mobile-responsive admin with scrollable drawer

### Roles and Permissions
| Level | Role | Capabilities |
|-------|------|-------------|
| 1 | Author | Write articles, submit for review |
| 2 | Editor | Publish, manage content, moderate comments |
| 3 | Super Admin | Full system access, users, settings, danger zone |

Permissions are grouped by section (Articles, Newsletter, System, etc.) with checkboxes.

---

## License

All rights reserved. The Northern Times.

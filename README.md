# THE NORTHERN TIMES

**Django-powered digital newsroom platform for Northern Uganda and East Africa.**

**Version:** 2.0 (Django) — May 2026
**Stack:** Django 5.1 / PostgreSQL + pgvector / Redis / Celery / Nginx / Docker
**Status:** Production-ready

---

## Overview

The Northern Times is a full-featured news platform with automated content aggregation, AI-powered enrichment, and a custom editorial admin panel. It crawls 60+ news sources, classifies articles by category using ML, rewrites content via OpenAI, and serves a responsive frontend for readers.

### Key Capabilities

- **Automated News Crawler** — 60+ RSS and Selenium-scraped sources across Uganda, East Africa, and international outlets
- **AI Content Pipeline** — Category classification (DeBERTa), sentiment analysis, NER, summarization, embeddings, quality scoring
- **4-Layer Deduplication** — URL hash, exact title, Jaccard fuzzy title (0.55), pgvector semantic similarity (0.82)
- **OpenAI Rewriter** — GPT-4o-mini rewrites with auto-enrich mode for short/low-quality articles
- **CKEditor 5 Editor** — 35-plugin rich text editor on articles, policies, and popups
- **Custom Admin Panel** — Dark/light theme, system-style modals, profile dropdown, CKEditor everywhere
- **Newsletter System** — Compose, schedule, queue, SMTP delivery with Postfix + DKIM
- **SEO Audit Engine** — 26-point site audit with 0-100 scoring
- **Social Monitor** — Track brand mentions across Google News, Reddit, Bing, Hacker News
- **Popup/Banner System** — 5 types, A/B testing, live preview, device targeting
- **Analytics** — Visitor tracking, daily stats, GeoIP, article performance
- **PWA** — Service worker, push notifications, offline support

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

### Docker Services (8 containers)

| Service | Image | Purpose |
|---------|-------|---------|
| **django** | python:3.12-slim + gunicorn | Web app (4 workers) |
| **web** | nginx:1.27-alpine | Reverse proxy, static files |
| **db** | pgvector/pgvector:pg15 | PostgreSQL + vector extensions |
| **redis** | redis:7-alpine | Cache + Celery broker |
| **celery-worker** | same as django | Background tasks (6GB, 2 workers) |
| **celery-beat** | same as django | Task scheduler |
| **mail** | boky/postfix:v4.3.0 | SMTP with DKIM signing |
| **selenium** | selenium/standalone-chromium | Browser automation for scraping |

---

## Backend Structure

```
backend/
  manage.py
  gunicorn.conf.py
  config/
    settings/          # base.py, development.py, production.py
    urls.py            # Admin + API + Frontend routing
    celery.py          # 13 periodic tasks
    wsgi.py / asgi.py
  apps/
    accounts/          # User, Role, auth, permissions (level 1/2/3)
    articles/          # Article, Category, Tag, Comment, Revision, Entity
    crawler/           # CrawlSource, CrawlLog, engine, fetchers, extractors, cleaners
    enrichment/        # AI services: embedder, summarizer, NER, sentiment, category, quality, dedup
    rewriter/          # OpenAI GPT-4o-mini rewriter with enrich mode
    media/             # MediaItem, image downloader, optimizer, health checker
    newsletter/        # Subscriber, Issue, EmailQueue, push notifications
    analytics/         # SiteVisitor, ArticleView, DailyStats, GeoIP
    ads/               # AdSlot, Popup (5 types, A/B testing, targeting)
    seo/               # SEOAudit, SEOIssue, 26-point audit engine
    social/            # SocialKeyword, SocialMention, multi-platform monitor
    webhooks/          # Webhook, WebhookLog, event dispatcher
    system/            # Setting, SystemLog, CronRun, DBBackup, PolicyPage, LoginQuote
    core/              # Context processors, template tags, helpers, error handlers
  templates/
    admin/             # 80+ admin templates (layout, dashboard, forms, lists)
    frontend/          # 12 public templates (home, article, category, search, etc.)
    errors/            # Branded 403, 404, 500 error pages
  requirements/
    base.txt           # 60+ packages
    development.txt
    production.txt
```

### Django Apps (14)

| App | Models | Key Features |
|-----|--------|-------------|
| accounts | User, Role | Auth, RBAC (3 levels), password reset |
| articles | Article, Category, Tag, Comment, Revision, StoryCluster, Entity | pgvector embeddings, soft delete, breaking news scoring |
| crawler | CrawlSource, CrawlLog | RSS + Selenium fetchers, 5-strategy BS4 extractor, 11-phase content cleaner |
| enrichment | (service-only) | DeBERTa classifier, all-MiniLM embedder, distilBART summarizer, spaCy NER |
| rewriter | (uses Article) | GPT-4o-mini rewrite + enrich mode for quality < 50 or words < 300 |
| media | MediaItem | Upload, download, hash dedup, health check |
| newsletter | Subscriber, Issue, EmailQueue, PushSubscription | Compose, schedule, queue, digest, web push |
| analytics | SiteVisitor, ArticleView, DailyStats | GeoIP, UA parsing, hourly aggregation |
| ads | AdSlot, Popup | 5 popup types, device targeting, A/B testing, analytics |
| seo | SEOAudit, SEOIssue | 26-check audit, async via Celery |
| social | SocialKeyword, SocialMention | Google News, Reddit, Bing, HN monitoring |
| webhooks | Webhook, WebhookLog | Event dispatch, delivery logging |
| system | Setting, SystemLog, CronRun, DBBackup, PolicyPage, LoginQuote, Notification | Admin tools, backups, factory reset |
| core | Setting | Context processors, template tags, sanitizer, helpers |

---

## AI Models (loaded lazily in-process)

| Model | Size | Purpose |
|-------|------|---------|
| DeBERTa v3 (MoritzLaurer) | ~1.5GB | Category + sentiment classification |
| all-MiniLM-L6-v2 | ~80MB | 384-dim embeddings for dedup |
| distilbart-cnn-12-6 | ~1.2GB | Article summarization |
| spaCy en_core_web_sm | ~12MB | Named entity recognition |
| OpenAI GPT-4o-mini | API | Article rewriting + enrichment |

---

## Celery Beat Schedule (13 tasks)

| Task | Schedule | Description |
|------|----------|-------------|
| run_crawler | every 5 min | Crawl all due sources |
| process_queue (rewriter) | every 10 min | Rewrite queued articles |
| publish_scheduled | every 1 min | Auto-publish scheduled articles |
| send_due (newsletters) | every 1 min | Send scheduled newsletters |
| process_email_queue | every 2 min | Deliver queued emails |
| aggregate_stats | every hour | Aggregate daily analytics |
| ai_reclassify_articles | daily 5am | AI category reclassification |
| cleanup_duplicates | every 12 hours | Archive semantic duplicates |
| check_image_health | daily 4am | Verify image URLs |
| run_seo_audit | Sunday 6am | Full site SEO audit |
| scan_social_mentions | every 6 hours | Monitor social mentions |
| daily_maintenance | daily 2am | Cleanup, optimize |
| daily_db_backup | daily 3am | PostgreSQL backup |

---

## Deduplication (4 layers)

1. **URL Hash** — SHA-256 of normalized URL
2. **Exact Title** — Case-insensitive title match
3. **Fuzzy Title** — Jaccard similarity > 0.55
4. **Semantic** — pgvector cosine similarity > 0.82 (384-dim embeddings)

---

## Frontend

- **Static site** with Django templates (no SPA framework)
- **CSS** — Custom design system with CSS variables, dark mode
- **JS** — Vanilla JavaScript (no jQuery/React), CKEditor 5 CDN for admin
- **PWA** — Service worker, web app manifest, push notifications
- **Responsive** — Mobile-first with drawer navigation, 2-column footer

### Public Pages
Home, Category, Article, Author, Tag, Search, About, Contact, Policy, Unsubscribe, RSS, Sitemap

### Admin Pages (42+)
Dashboard, Articles (CRUD + archive), Categories, Comments, Review Queue, Media Library, Newsletter (compose + queue), Subscribers, Analytics (performance + engagement), Ad Placements, Popups (with live preview), Social Monitor, Crawler (sources + logs + settings), AI Rewriter, SEO Audit, Settings, Users, Roles (with permissions), System (backups + logs + mail queue + resets), Webhooks, Profile

---

## Quick Start

### Prerequisites
- Docker & Docker Compose
- WSL 2 (Windows) or Linux

### Development

```bash
# Clone
git clone https://github.com/bododesderio/northern-times.git
cd northern-times

# Copy environment file
cp .env.example .env
# Edit .env with your settings (DJANGO_SECRET_KEY, OPENAI_API_KEY, etc.)

# Build and start
docker compose -f docker-compose.django.yml up -d --build

# The entrypoint automatically:
# - Runs migrations
# - Collects static files
# - Seeds roles, categories, sources, ad slots, policies
# - Creates admin user (admin/admin)

# Access
# Frontend: http://localhost:8080
# Admin:    http://localhost:8080/admin/login/
# API:      http://localhost:8080/api/health/
# pgAdmin:  http://localhost:5050
```

### Management Commands

```bash
# Run inside container
docker compose -f docker-compose.django.yml exec -w /app/backend django python manage.py <command>

# Available commands:
seed_all                    # Seed all default data
reclassify_articles         # Re-categorize articles (keyword-based)
reclassify_articles --dry-run  # Preview reclassification
fix_broken_images --limit=500  # Download external images locally
crawl                       # Trigger manual crawl
db_backup                   # Create database backup
```

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

## Crawl Sources (60+)

### By Region
- **Ugandan (7):** Daily Monitor, New Vision, Nile Post, The Observer, The Independent, Uganda Radio Network, Dokolo Post
- **East African (5):** The East African, Nation Africa, Business Daily Africa, Capital FM Kenya, KT Press Rwanda
- **International (15+):** BBC Africa, Al Jazeera, Reuters, AP News, France24, VOA Africa, DW Africa, RFI Africa, etc.

### By Category
- **Sports:** BBC Sport, ESPN, Goal.com, KickOff, SuperSport
- **Entertainment:** Pulse Uganda, Sqoop, BBC Culture
- **Lifestyle:** Monitor Lifestyle, BBC Travel
- **Business:** Business Daily Africa, Bloomberg Africa, CNBC
- **Technology:** TechCrunch Africa
- **Health:** WHO Africa

---

## Admin Features

### Article Editor
- CKEditor 5 with 35 plugins (headings, formatting, tables, images, code, media embeds)
- 2-column layout: content editor + metadata sidebar
- Image upload from device, media library, or URL
- Tag chip input, word count, revision history
- Auto-save badge, schedule publishing

### System-Style UI
- Custom confirm/prompt modals (no browser defaults)
- Toast notifications
- Profile dropdown in topbar
- Custom styled select dropdowns
- Scrollable dashboard activity
- Mobile-responsive admin with scrollable drawer

### Roles & Permissions
| Level | Role | Capabilities |
|-------|------|-------------|
| 1 | Author | Write articles, submit for review |
| 2 | Editor | Publish, manage content, moderate comments |
| 3 | Super Admin | Full system access, users, settings, danger zone |

---

## License

All rights reserved. The Northern Times.

# THE NORTHERN TIMES

## Comprehensive Digital Platform & Editorial System Documentation

**Version:** 6.0 — March 2026
**Status:** Production-ready, All phases (1-12) complete

---

# 1. Platform Overview

**The Northern Times** is a fully structured digital newsroom platform designed to deliver credible journalism from Northern Uganda and beyond.

The system combines:

* A scalable content management system (CMS) with PHP/PostgreSQL
* Structured editorial workflows with role-based review and approval
* Media asset management with automatic resizing, WebP conversion, and responsive images
* Advertisement management with device targeting and placement maps
* Newsletter automation with SMTP integration and email queuing
* Subscriber management with source tracking
* Comment system with AJAX posting and admin moderation
* Search and category architecture
* SEO-ready framework with JSON-LD structured data, sitemap.xml, and RSS feeds
* GeoIP location detection with flag emoji rendering
* Theme engine with live customization from admin, including dark mode toggle
* Secure admin control panel with RBAC (Role-Based Access Control)
* Comprehensive error handling with branded error pages
* Popup & banner system with 10 types, analytics, A/B testing, and frontend engine
* Multi-purpose crawler system (news aggregation, SEO audit, social monitoring)
* System administration panel with resets, purges, database tools, and backups
* Tag system, author pages, article revisions, and breaking news ticker
* Social auto-posting (Facebook, X, WhatsApp, Telegram, LinkedIn)
* Browser push notifications via Web Push (VAPID)
* Article scheduling with cron-based auto-publish
* Dark mode with CSS variable system
* Reader heatmap with Leaflet satellite view and heat overlay
* White-label architecture — fully rebrandable from admin settings
* Automated test suite
* Docker containerization (Nginx + PHP-FPM + PostgreSQL + Redis + pgAdmin)

The platform is designed for:

* Editorial integrity
* Operational efficiency
* Technical stability
* Long-term institutional growth

---

# 2. Core Objectives

1. Deliver accurate, independent journalism.
2. Provide structured editorial publishing tools with review workflows.
3. Centralize media asset management with responsive image delivery.
4. Manage categories dynamically with hierarchical support.
5. Capture and manage subscribers with multi-source tracking.
6. Support advertisement placements with device targeting and analytics.
7. Maintain secure administrative control with role-based permissions.
8. Enable long-term scalability with modular, phase-based architecture.
9. Automate newsletter campaigns with SMTP email delivery.
10. Engage readers through comments, social sharing, and author profiles.
11. Aggregate news from external sources via automated crawling.
12. Monitor SEO health and social media mentions.
13. Distribute content via social auto-posting and push notifications.

---

# 3. System Architecture

## 3.1 Backend

* **Language:** PHP 8.2 (Modular MVC architecture)
* **Database:** PostgreSQL 15 with UUID primary keys
* **Routing:** Symfony Routing component
* **Authentication:** Secure session-based with Redis storage (file-based fallback)
* **Security:** CSRF protection, RBAC permissions, rate limiting, input sanitization, security headers middleware
* **Email:** Direct SMTP client with TLS/SSL, supports Gmail/Outlook/SendGrid/Mailgun/SES
* **Services (26):** Auth, ArticleScraper, BreakingNewsEngine, Cache, CategoryMatcher, ContentNormalizer, CrawlerEngine, Csrf, DB, Flash, GeoIP, Image, ImageDownloader, ImageHealthChecker, Mailer, Media, RBAC, RateLimiter, Sanitizer, SeoAuditEngine, SeoReportPdf, Slug, SocialMonitorService, SocialPoster, StoryThreadDetector, WebPush
* **Middleware (8):** AuthMiddleware, CsrfMiddleware, MiddlewareInterface, PermissionMiddleware, Pipeline, RoleMiddleware, SecurityHeadersMiddleware, VisitorMiddleware

## 3.2 Frontend

* Semantic HTML5 with responsive CSS
* Vanilla JavaScript (no framework dependency)
* CKEditor 5 rich text integration
* Responsive images with `srcset` and multiple breakpoints (320, 640, 960, 1280, 1920px)
* Social share buttons (X/Twitter, Facebook, WhatsApp, LinkedIn, Email, Copy Link)
* GeoIP-powered location display with country flag emoji
* AJAX comment submission with real-time updates
* Dark mode toggle with localStorage persistence
* Accessible design structure

## 3.3 Infrastructure

* **Docker Compose** containerization (5 services)
  * `northern_times_web` — Nginx reverse proxy with gzip, static asset caching (30d), real-IP forwarding
  * `northern_times_app` — PHP-FPM with custom php.ini
  * `northern_times_db` — PostgreSQL 15 Alpine
  * `northern_times_redis` — Redis 7 Alpine (session storage + caching)
  * `northern_times_pgadmin` — pgAdmin 4 (optional, tools profile)
* **Nginx** configs for development (`default.conf`) and production SSL (`production-ssl.conf`)
* Controlled upload directories with public serving via Nginx alias
* Image resizing pipeline with WebP conversion and thumbnail generation
* Secure environment configuration via `.env`
* Cron jobs: `schedule.php` (article scheduling), `backup.php` (database backups), `cron/crawl.php` (news crawling)

---

# 4. Public Website Structure

## 4.1 Homepage

The homepage includes:

* Hero story carousel with responsive images (`fetchpriority="high"` on first slide)
* Latest articles grid
* Category-based segmentation
* Top stories section
* Sidebar with trending/most-read articles
* Breaking news ticker (when active)
* Newsletter subscription block
* Homepage spotlight ad slot
* Dark mode toggle in header
* Structured footer with site links

Optimized for readability, hierarchy, and mobile-first responsiveness.

## 4.2 Category Pages

Each category page:

* Has a clean slug (`/category/{slug}`)
* Displays published articles only with pagination
* Supports description metadata
* Follows consistent layout with responsive thumbnails
* Designed for SEO indexing

Categories are dynamically managed from the admin panel with sort order and navigation visibility.

## 4.3 Article Pages

Each article contains:

* Title with category kicker link
* Author name with role label (Editor-in-Chief, Editor, Staff Writer, Contributor)
* Author card with avatar, bio, and social links (X, Facebook, Instagram, LinkedIn, WhatsApp, Website)
* Author name links to author page (`/author/{username}`)
* Category badge
* Publish date & time with relative time display (e.g., "6 hours ago", "yesterday", then full date)
* Featured image with responsive `srcset` (5 breakpoints)
* Rich HTML content
* Tag pills below article body (clickable, link to `/tag/{slug}`)
* Social share buttons (centered, top and bottom of article)
* In-article and sidebar ad slots (sidebar only renders when ad is active)
* Story thread banner (linking related articles in a series)
* Comment section with AJAX posting (name, optional email, rate-limited)
* Related stories grid (4 articles, same category)
* JSON-LD structured data for SEO
* Copy link button with clipboard API + fallback

## 4.4 Author Pages (`/author/{username}`)

* Profile header with avatar, name, role, bio, social links
* All published articles by the author with pagination
* JSON-LD Person schema
* Crawled articles appear under configured display author

## 4.5 Tag Pages (`/tag/{slug}`)

* Lists all articles with the given tag
* Pagination support
* SEO meta descriptions

## 4.6 Search Pages

* Keyword search across article titles and content
* Category filtering
* Clean result pages with pagination
* Search-optimized queries

## 4.7 Policy Pages

* Privacy Policy, Terms of Service, and custom pages
* Managed via admin with rich text editor
* Clean URLs (`/policy/{slug}`)

## 4.8 RSS Feed (`/feed`)

* RSS 2.0 with Dublin Core and Media RSS extensions
* Latest published articles
* Per-category feeds available
* Auto-discovery link in HTML `<head>`

## 4.9 Sitemap & Robots

* Dynamic `sitemap.xml` with published articles, categories, and policy pages
* `robots.txt` with sitemap reference

## 4.10 Unsubscribe Page

* Token-based secure unsubscribe
* One-click unsubscribe from newsletter emails
* Confirmation message

---

# 5. Admin Panel System

The admin system is the operational core of The Northern Times. It uses a grouped collapsible sidebar for navigation and a comprehensive dashboard for at-a-glance monitoring.

## 5.1 Sidebar Navigation (Grouped & Collapsible)

The sidebar organizes 30+ admin pages into logical groups. Each group collapses/expands on click. The active group auto-expands. Collapsed state persists in localStorage.

```
+------------------------------+
|  THE NORTHERN TIMES          |
|  --------------------------  |
|                              |
|  Dashboard                   |
|                              |
|  > CONTENT                   |
|     Articles            (24) |
|     Categories               |
|     Media Library            |
|     Policy Pages             |
|                              |
|  > EDITORIAL                 |
|     Review Queue          (3)|
|     Notifications         (5)|
|     Comments             (12)|
|                              |
|  > GROWTH                    |
|     Popups                (4)|
|     Newsletter               |
|     Subscribers              |
|     Ads                      |
|                              |
|  > CRAWLER                   |
|     Sources               (8)|
|     Crawl Log                |
|     SEO Audit                |
|     Social Monitor           |
|     Crawler Settings         |
|                              |
|  > ADMIN                     |
|     Users                    |
|     Roles                    |
|     Settings                 |
|     Profile                  |
|     Login Quotes             |
|                              |
|  > SYSTEM (Super Admin)      |
|     System Admin             |
|     Safe Resets              |
|     Content Purges           |
|     Media Management         |
|     Danger Zone              |
|                              |
|  --------------------------  |
|  View Live Site ->           |
|  Logout                      |
+------------------------------+
```

### Sidebar Behavior

| Feature                         | Behavior                                                                                                                            |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| **Collapse/expand**       | Click group header to toggle. Smooth CSS animation.                                                                                 |
| **Active page highlight** | Current page highlighted with accent color bar on left edge.                                                                        |
| **Auto-expand**           | Group containing current page auto-expands on load.                                                                                 |
| **Remember state**        | Collapsed/expanded state saved in localStorage per user.                                                                            |
| **Badges**                | Red/orange count badges on items with pending actions (Review Queue, Notifications, Comments). Green badge on active crawl sources. |
| **Role-based visibility** | System group only visible to Super Admin. Review Queue hidden from Contributors. Users/Roles hidden from non-admins.                |
| **Mobile**                | Sidebar collapses to hamburger menu. Full-screen overlay on tap.                                                                    |
| **Compact mode**          | Double-click logo to toggle icon-only mode (just icons, no labels). Hover shows tooltip with label.                                 |

### Group Breakdown

| Group               | Contains                                                        | Who Sees It                      |
| ------------------- | --------------------------------------------------------------- | -------------------------------- |
| **CONTENT**   | Articles, Categories, Media Library, Policy Pages               | All roles                        |
| **EDITORIAL** | Review Queue, Notifications, Comments                           | Editors, Admins                  |
| **GROWTH**    | Popups, Newsletter, Subscribers, Ads                            | Editors, Admins                  |
| **CRAWLER**   | Sources, Crawl Log, SEO Audit, Social Monitor, Settings         | Editors, Admins                  |
| **ADMIN**     | Users, Roles, Settings, Profile, Login Quotes                   | Profile: all. Rest: Admins only. |
| **SYSTEM**    | System Admin (with sub-pages)                                   | Super Admin only                 |

## 5.2 Main Dashboard (`/admin`)

The dashboard is a comprehensive command center with 8 widget sections. Widgets use a responsive grid that adapts from 1 column (mobile) to 4 columns (desktop).

### Dashboard Widgets

| Widget                    | Data Source                                                            | Refresh                      |
| ------------------------- | ---------------------------------------------------------------------- | ---------------------------- |
| **Quick Actions**   | Static buttons linking to create pages                                 | --                           |
| **Content**         | `articles` COUNT by status + GROUP BY date                             | Page load                    |
| **Audience**        | `site_visitors` + `article_views` aggregated                           | Page load + auto-refresh 60s |
| **Reader Heatmap**  | Leaflet satellite map with heat layer + city markers. Period selector.  | AJAX via `/admin/api/reader-map` |
| **Crawler**         | `crawl_sources` + `crawled_articles` + source health                   | Page load + auto-refresh 30s |
| **Popups**          | `popup_events` aggregated + `popups` active count                      | Page load                    |
| **Revenue & Ads**   | `ad_slots` stats + configured CPM                                      | Page load                    |
| **Subscribers**     | `newsletter_subscribers` COUNT + GROUP BY source/date                  | Page load                    |
| **System Health**   | PostgreSQL `pg_database_size()` + Redis `PING` + `df` disk            | Page load                    |
| **Recent Activity** | Composite: latest from crawl log, articles, notifications, subscribers | Page load + auto-refresh 30s |
| **Today's Summary** | Aggregated totals from all widgets                                     | Page load                    |

### Dashboard Layout — Responsive Grid

| Screen                         | Layout                                                                        |
| ------------------------------ | ----------------------------------------------------------------------------- |
| **Desktop (>=1200px)**  | 2 columns, widgets side-by-side                                               |
| **Tablet (768-1199px)** | 2 columns, narrower widgets                                                   |
| **Mobile (<768px)**      | 1 column, widgets stacked vertically. Quick Actions become horizontal scroll. |

### Auto-Refresh

| Widget              | Interval          | Method                                               |
| ------------------- | ----------------- | ---------------------------------------------------- |
| Audience (visitors) | Every 60 seconds  | AJAX fetch, update numbers with fade animation       |
| Crawler activity    | Every 30 seconds  | AJAX fetch, update source health indicators          |
| Recent Activity     | Every 30 seconds  | AJAX fetch, prepend new entries with slide animation |
| All others          | On page load only | Full render, no auto-refresh                         |

Admin can disable auto-refresh via a toggle in top-right corner.

### Role-Based Dashboard

Not all roles see all widgets:

| Widget          | Super Admin                | Editor                   | Author                     | Contributor      |
| --------------- | -------------------------- | ------------------------ | -------------------------- | ---------------- |
| Quick Actions   | All 6 buttons              | 4 buttons (no crawl/SEO) | 2 buttons (write, profile) | 1 button (write) |
| Content         | Full stats                 | Full stats               | Own articles only          | Own drafts only  |
| Audience        | Full                       | Full                     | Simplified (top articles)  | Hidden           |
| Crawler         | Full with source health    | Summary only             | Hidden                     | Hidden           |
| Popups          | Full                       | Summary only             | Hidden                     | Hidden           |
| Revenue & Ads   | Full with revenue          | Impressions/CTR only     | Hidden                     | Hidden           |
| Subscribers     | Full with source breakdown | Total count only         | Hidden                     | Hidden           |
| System Health   | Full                       | Hidden                   | Hidden                     | Hidden           |
| Recent Activity | Full                       | Editorial actions only   | Own actions only           | Hidden           |

## 5.3 Admin Modules (Complete)

| Group     | Module           | Route                           | Description                            |
| --------- | ---------------- | ------------------------------- | -------------------------------------- |
| --        | Dashboard        | `/admin`                        | Command center with 8 widget sections  |
| CONTENT   | Articles         | `/admin/articles`               | Full CRUD with status management       |
| CONTENT   | Categories       | `/admin/categories`             | Hierarchical category management       |
| CONTENT   | Media Library    | `/admin/media`                  | Upload, organize, pick images          |
| CONTENT   | Policy Pages     | `/admin/policies`               | Legal/policy page editor               |
| EDITORIAL | Review Queue     | `/admin/review`                 | Editorial review and approval          |
| EDITORIAL | Notifications    | `/admin/review/notifications`   | Editor notification inbox              |
| EDITORIAL | Comments         | `/admin/comments`               | Moderation, bulk actions               |
| GROWTH    | Popups           | `/admin/popups`                 | Popup/banner management + analytics    |
| GROWTH    | Newsletter       | `/admin/newsletter`             | Compose, preview, send campaigns       |
| GROWTH    | Subscribers      | `/admin/subscribers`            | View, search, manage subscribers       |
| GROWTH    | Ads              | `/admin/ads`                    | Ad slot management with targeting      |
| CRAWLER   | Sources          | `/admin/crawler/sources`        | Configure crawl sources                |
| CRAWLER   | Crawl Log        | `/admin/crawler/log`            | Per-run crawl results                  |
| CRAWLER   | SEO Audit        | `/admin/crawler/seo`            | Site health + image monitoring         |
| CRAWLER   | Social Monitor   | `/admin/crawler/social`         | Mention tracking + sentiment           |
| CRAWLER   | Crawler Settings | `/admin/crawler/settings`       | Global crawl configuration             |
| ADMIN     | Users            | `/admin/users`                  | User management (Super Admin)          |
| ADMIN     | Roles            | `/admin/roles`                  | Role and permission management         |
| ADMIN     | Settings         | `/admin/settings`               | Site settings, branding, theme, SMTP   |
| ADMIN     | Profile          | `/admin/profile`                | Personal profile with social links     |
| ADMIN     | Login Quotes     | `/admin/login-quotes`           | Manage inspirational login quotes      |
| SYSTEM    | System Admin     | `/admin/system`                 | Resets, purges, DB tools (Super Admin) |

---

# 6. Article Management System

Editors can:

* Create, edit, delete, and archive articles
* Assign categories and tags
* Set article status (Draft / Published / Scheduled / Archived / Pending Review)
* Add excerpts and featured images
* Add custom display author names
* Schedule articles for future publication
* View and restore revision history
* Automatically generate publish timestamps

## 6.1 Rich Text Editor

Integrated CKEditor 5 (Classic Build v41) provides:

* Headings (H1-H4), paragraphs
* Ordered and unordered lists
* Bold, italic, underline, strikethrough
* Block quotes and tables
* Inline image upload via custom upload adapter (drag-drop, paste, toolbar)
* Media picker integration (browse existing library, insert with alt text/caption)
* Image insertion via CKEditor's native model API (`imageBlock` elements)
* Structured formatting

## 6.2 Autosave Protection

* Drafts stored in localStorage
* Restore prompt on page reload
* Protection against power outages and accidental navigation
* Manual draft clearing option

## 6.3 Editorial Workflow

* Authors can submit articles for review (`pending_review` status)
* Editors and Super Admins see pending articles in Review Queue
* Editors can approve (publish) or reject with feedback notes
* Rejected articles show feedback banner with reviewer notes and timestamp
* Authors can revise and resubmit rejected articles
* Editor notifications triggered on new submissions (`Notification::notifyEditors()`)

## 6.4 Article Scheduling

* Set `published_at` to a future datetime on the article form
* Article saved with `status = 'scheduled'`
* Cron job (`schedule.php`) runs periodically, finds articles where `published_at <= NOW() AND status = 'scheduled'`, sets `status = 'published'`
* Scheduled articles show countdown badge in admin list
* Change status back to Draft to cancel scheduled publish

## 6.5 Revision History

* `article_revisions` table tracks every edit
* View revision history from article edit form
* Diff view to compare versions
* Restore previous versions (non-destructive — creates new revision)
* Crawled articles preserve original crawled content as first revision

## 6.6 Relative Time Display

Articles show human-friendly relative timestamps:

| Age          | Display                          |
| ------------ | -------------------------------- |
| < 60 seconds | "just now"                       |
| < 60 minutes | "X minutes ago"                  |
| < 24 hours   | "X hours ago"                    |
| Yesterday    | "yesterday"                      |
| Older        | Full date (e.g., "Feb 15, 2026") |

Applied on: homepage cards, category listings, search results, article page meta, comment timestamps.

## 6.7 Tag System

* Tags work alongside categories for cross-cutting topics
* Tag input field on article form with auto-suggest
* Tags displayed as clickable pills below article body
* Tag pages at `/tag/{slug}` with pagination
* `tags` + `article_tags` pivot tables
* Trending tags based on article count

---

# 7. Category Management System

Categories are fully manageable via admin.

Each category includes:

* Name, slug, description
* Sort order for navigation
* Navigation visibility control (show/hide in header)
* Optional parent category (hierarchical support)

System safeguards:

* Prevent deletion if articles exist in category
* Unique slug enforcement
* Clean URL generation (`/category/{slug}`)

---

# 8. Media Library Management

The Media Library is centralized and structured.

## 8.1 Features

* Drag-and-drop uploads with progress indicators
* Automatic image resizing: 1920px max main image, 360px thumbnail
* WebP conversion for optimized delivery
* SHA256 duplicate detection
* Folder organization
* Public URL and thumbnail URL generation
* Inline media picker modal (used in articles, profile, settings)
* Featured image assignment for articles
* Responsive image generation with `srcset` (320, 640, 960, 1280, 1920px breakpoints)

## 8.2 Storage

* Secure upload directory (`storage/uploads/`)
* Public serving via Nginx alias
* Organized by folder (Articles, General, etc.)
* Controlled access through admin panel

---

# 9. Advertisement Management System

## 9.1 Ad Slot Types

| Slot                   | Placement             | Description              |
| ---------------------- | --------------------- | ------------------------ |
| `top-banner`           | Above article content | Full-width leaderboard   |
| `article-sidebar`      | Right column (sticky) | Only renders when active |
| `in-article`           | Within article body   | Mid-content placement    |
| `below-article`        | After article content | Post-read placement      |
| `homepage-spotlight`   | Homepage hero area    | Premium placement        |
| Category-specific      | Category pages        | Targeted by category     |

## 9.2 Admin Controls

Admins can:

* Upload ad creatives (images) via media picker
* Set target URL with nofollow option
* Assign placement location from placement map
* Set device targeting (All / Mobile only / Desktop only)
* Set max dimensions and custom CSS
* Add alt text for accessibility
* Enable/disable ads with toggle
* Set start and end dates
* View impression and click stats
* Live preview of ad rendering

## 9.3 Enhanced Ad Features

* Device targeting: `all`, `mobile`, `desktop`
* Max width/height constraints
* Custom CSS per ad slot
* Nofollow link option
* `decoding="async"` for performance
* Placement map visual guide in admin

---

# 10. Subscriber Management System

## 10.1 Subscriber Capture Sources

* Newsletter signup forms (footer, homepage)
* Comment section opt-in checkbox
* Popup signup forms
* Manual admin import

Each subscriber record tracks:

* Name (optional), email address
* Subscription timestamp
* Source (`manual`, `comment`, `popup`, `footer_form`)
* Unsubscribe token for one-click removal
* CSRF-secured submission

## 10.2 Admin Subscriber Panel

Admins can:

* View all subscribers with pagination
* Sort by newest
* Search by email or name
* Delete individual subscribers
* View subscription date and source

## 10.3 Newsletter System

* Compose newsletters with rich text editor
* Preview before sending
* Send test emails to verify rendering
* Process email queue in batches (50 per batch)
* Campaign management with sent/draft/trash tabs
* Scheduled newsletter sending
* Stats: sent count, open tracking readiness
* Soft delete and restore for campaign management

---

# 11. Comment System

## 11.1 Frontend

* Comment form on every article page (Name required, Email optional, Comment required)
* AJAX submission via `POST /api/comment` (no page reload)
* Real-time comment append after posting
* Dynamic comment count update
* Rate limiting: 5 comments per IP per hour
* "Be the first to comment" prompt when no comments exist
* Avatar initials, timestamps, and author name display

## 11.2 Admin Moderation (`/admin/comments`)

* View all comments with article links
* Filter by status: Visible, Hidden, Deleted
* Individual actions: Show, Hide, Soft Delete, Permanent Delete
* Bulk actions: Hide, Restore, Delete, Destroy
* Status counts dashboard

---

# 12. User & Role Management

## 12.1 User Roles

| Role        | Level            | Permissions                                     |
| ----------- | ---------------- | ----------------------------------------------- |
| Super Admin | Full access      | All system features, user management, settings  |
| Editor      | Editorial access | Publish, review, manage articles and comments   |
| Author      | Content creation | Create and edit own articles, submit for review |
| Contributor | Limited          | Create drafts only                              |

## 12.2 RBAC (Role-Based Access Control)

* Permission-based access control via `RBAC` service
* Role definitions stored in database with customizable permissions
* Helper functions: `user_can()`, `user_can_any()`, `user_is_editor()`, `user_is_admin()`
* Route-level middleware enforcement
* Super Admin has unrestricted access

## 12.3 User Profile (`/admin/profile`)

Each user can manage:

* Username, display name, and email
* Bio (displayed on article author cards)
* Avatar (via media picker or URL)
* Social media links:
  * Twitter/X handle
  * Facebook URL
  * Instagram handle
  * LinkedIn URL
  * WhatsApp number (international format)
  * Website URL
* Password change with current password verification
* All social links display on the profile header card with SVG icons
* Role badge (read-only, set by Super Admin)

---

# 13. Editorial Workflow & Notifications

## 13.1 Review Queue (`/admin/review`)

* Lists all articles with `pending_review` status
* Editors can view full article preview
* Approve (sets to published) or reject with written feedback
* Rejection notes visible to author on article edit form

## 13.2 Notifications (`/admin/review/notifications`)

* Editors receive notifications when authors submit articles
* Notification types: Article Submitted, Article Approved, Article Rejected
* Read/unread status tracking
* Notification count badge in admin sidebar

---

# 14. Email & SMTP System

## 14.1 Mailer Service

* Direct SMTP client using `fsockopen()` with TLS/SSL
* STARTTLS encryption support
* AUTH LOGIN authentication
* Multi-part MIME messages (HTML + plain text)
* Driver selection: `smtp` or `mail` (via `MAIL_DRIVER` env)
* Compatible with Gmail, Outlook, Yahoo, Mailgun, SendGrid, Amazon SES

## 14.2 Admin SMTP Configuration (`/admin/settings`)

* Mail driver selector (SMTP / PHP mail)
* Encryption dropdown (TLS / SSL / None)
* SMTP host, port, username, password fields
* From address and from name
* Quick setup buttons for Gmail, Outlook, Zoho, SendGrid, Mailgun
* Settings stored in `site_settings` table with `.env` fallback
* Test email functionality

## 14.3 Email Queue

* Queue emails for batch processing
* Process queue in configurable batch sizes
* Queue stats: pending, sent, failed counts
* Used by newsletter campaigns

---

# 15. Theme & Branding System

## 15.1 Theme Engine

* CSS custom properties (variables) driven from database
* Tokens: `--ink`, `--paper`, `--surface`, `--border`, `--muted`, `--accent`, `--radius`, etc.
* `get_theme_css()` renders theme as inline `<style>` block
* Changes apply immediately across admin and frontend
* Dark/light theme support via variable overrides

## 15.2 Dark Mode Toggle

* Moon/sun icon toggle in site header
* Persists in `localStorage` key `nt-theme`
* Respects `prefers-color-scheme: dark` media query
* Toggles `dark` class on `<html>` element
* Smooth 200ms transition on colors
* Frontend only (admin has its own theme)
* Implemented via `dark-mode.js` and `dark-mode.css`

## 15.3 Branding Settings (`/admin/settings`)

* Site title, tagline, description
* Site logo URL (via media picker)
* Favicon URL (renders in both frontend and admin)
* Default OG image for social sharing
* Footer text customization

---

# 16. GeoIP & Performance

## 16.1 GeoIP Location Detection & Reader Heatmap

* `GeoIP::clientIP()` extracts real client IP from proxy headers
* `GeoIP::lookup()` returns country, city, timezone, lat/lon coordinates
* Timezone-to-country fallback mapping (20+ regions including East/West Africa)
* Server-side rendering of country flag emoji + location label
* File-based cache in `storage/cache/geoip/` with 24-hour TTL
* IP caching to reduce API calls (ip-api.com free tier, 45 req/min)
* **Reader Heatmap** on admin dashboard:
  * Leaflet satellite tiles (ESRI World Imagery — free, no API key)
  * Heat overlay at low zoom, individual city markers at high zoom
  * Live ping animation for real-time reader activity
  * Period selector (today, 7d, 30d, 90d, all time)
  * Top cities list with click-to-fly navigation
  * Zoom controls and responsive resize

## 16.2 Performance Optimizations

* **Nginx:** Gzip compression (level 5, min 256 bytes), static asset cache (30 days, immutable), `Vary: Accept` headers
* **Images:** Responsive `srcset` generation, WebP conversion, thumbnail pre-generation, `fetchpriority="high"` on hero images, `decoding="async"` on below-fold images
* **Frontend:** DNS prefetch for external APIs, font preload, CSS/JS preload, critical inline styles
* **Database:** Indexed columns on all foreign keys, status fields, and timestamps

---

# 17. Error Handling

Custom branded error pages with newspaper theme:

| Code | Page            | Description         |
| ---- | --------------- | ------------------- |
| 403  | Forbidden       | Access denied       |
| 404  | Not Found       | Page doesn't exist  |
| 419  | Session Expired | CSRF token expired  |
| 500  | Server Error    | Internal error      |
| 503  | Maintenance     | Service unavailable |

Features:

* Shared base template (`_base.php`) with Playfair Display typography
* Dark newspaper editorial layout with animated fade-in
* Debug panel shows stack traces when `APP_DEBUG=true`
* Configurable error messages and action buttons

---

# 18. Search System

* Keyword search across article titles and content
* Category filtering
* Clean result pages with pagination
* Responsive article cards with thumbnails
* Search-optimized database queries

---

# 19. Security Measures

* CSRF token protection on all POST routes
* Secure session storage via Redis (file-based fallback)
* Input validation and sanitization
* XSS prevention via `h()` helper and `safe_html()` sanitizer
* Security headers middleware (Content-Security-Policy, X-Frame-Options, etc.)
* Unique slug enforcement
* SHA256 duplicate media detection
* Protected upload directories
* Restricted admin routes with middleware
* RBAC permission checks
* Rate limiting on comments and login attempts
* Login attempt tracking and lockout
* Parameterized queries (PDO prepared statements) throughout — no SQL injection vectors

---

# 20. SEO & Metadata Framework

* Clean URL structure (`/article/{slug}`, `/category/{slug}`, `/tag/{slug}`, `/author/{username}`)
* Canonical links
* Meta title and description per page
* Open Graph tags (title, description, image, type)
* Default OG image fallback from settings
* JSON-LD structured data on article pages (NewsArticle schema)
* JSON-LD Person schema on author pages
* Article schema with author, publisher, datePublished, wordCount
* Dynamic `sitemap.xml` generation
* RSS feed at `/feed` with auto-discovery in `<head>`
* `robots.txt` with sitemap reference

---

# 21. Testing

## Automated Test Suite

* **Framework:** PHPUnit
* **Test files:** 27
* **Coverage areas:**
  * Unit tests: Models (Article, Category, User, Subscriber, Notification, Tag, ArticleRevision, SystemLog, ReaderHeatmap), Services (Slug, Sanitizer, Flash, Csrf), Dashboard intelligence
  * Integration tests: Middleware (Pipeline, Csrf, Auth)
  * Feature tests: Auth flow, Login flow, Mailer, Phase 10 flow, Phase 11 distribution, Phase 0 white-label (2 suites), Docker entrypoint
* **Test database:** Separate test configuration with migrations

---

# 22. Popup & Banner System (Phase 6)

A comprehensive popup engine managed entirely from the admin portal.

## 22.1 Banner Styles (6 Types)

| Style               | Layout                    | Best For                  |
| ------------------- | ------------------------- | ------------------------- |
| Minimal Bar         | Horizontal row            | Cookie consent, alerts    |
| Card Modal          | Vertical stack, image top | Newsletter signup, promos |
| Split Image Modal   | Two-column, image left    | Promotions, ads           |
| Full-Screen Overlay | Background image + card   | Breaking news, launches   |
| Slide-In Panel      | Corner card               | Welcome back, surveys     |
| Floating Banner     | Horizontal card           | Ad popups, sponsors       |

## 22.2 Popup Types (10)

| Type                 | Trigger               | Purpose               |
| -------------------- | --------------------- | --------------------- |
| Cookie Consent       | First visit           | Legal compliance      |
| Newsletter Signup    | 30s / 50% scroll      | List building         |
| Breaking News Alert  | Admin-triggered       | Engagement            |
| Welcome Back         | Returning visitor     | Retention             |
| Exit Intent          | Mouse leaves viewport | Last-chance capture   |
| Ad Popup             | Time/page views       | Sponsor monetization  |
| Promotion / Campaign | Time/scroll           | Own events/offers     |
| App Download / PWA   | Mobile, 3rd visit     | Mobile retention      |
| Paywall / Metered    | After N free articles | Monetization          |
| Survey / Feedback    | After 3+ articles     | Reader insights       |

## 22.3 Admin Controls

* Full CRUD: create, edit, duplicate, toggle, delete popups
* Live preview panel (real-time, no save needed)
* Full-page preview with position simulation
* Color pickers: background, text, button, button text
* Image upload via media picker
* Targeting: audience, device, page, category
* Triggers: page load, scroll %, exit intent, manual, page view count
* Configurable intervals: once ever, once per session, daily, custom, weekly, monthly
* Show delay, close delay
* Schedule: start date, end date, priority ranking
* Status management: Active, Inactive, Draft, Scheduled, Expired

## 22.4 Analytics

* Dashboard widget: impressions, conversions, conversion rate, top performers
* Dedicated analytics page (`/admin/popups/analytics`)
* Per-popup performance tracking
* Period selector and CSV export

## 22.5 A/B Testing

* Create split tests between two popup variants (A/B)
* 50/50 traffic split with sticky visitor assignment (hashed IP + user-agent)
* Per-variant metrics: impressions, clicks, conversions, conversion rate, click rate
* Side-by-side comparison dashboard with visual bars
* Configurable success metric: conversion rate, click rate, or impressions
* Start, pause, resume, and declare winner actions
* Winner declaration auto-activates the winner and deactivates the loser
* Admin views: `/admin/popups/ab` (list), `/admin/popups/ab/create`, `/admin/popups/ab/{id}` (detail)
* A/B variant badge on popup listing page

## 22.6 Frontend Engine

* Lightweight `popups.js` script
* Fetches active popups via `/api/popups`
* A/B test variant selection happens server-side (visitor sees only their assigned variant)
* Queue system: never shows more than 1 popup at a time
* Priority-based ordering
* Interval checking via localStorage/sessionStorage
* Tracks events via `POST /api/popup-track`

---

# 23. Multi-Purpose Crawler System (Phase 7)

A crawler engine with three modules, all managed from the admin portal.

## 23.1 Module 1: News Aggregator (Full Auto-Publish)

Automatically crawls configured source websites, extracts content, and publishes articles.

| Feature                       | Details                                                                         |
| ----------------------------- | ------------------------------------------------------------------------------- |
| **Sources**             | Admin adds RSS feeds or websites as crawl sources                               |
| **Schedule**            | Configurable from dashboard (default every 30 minutes)                          |
| **Extraction**          | Content-only: strips CSS/styling, keeps text, images, embeds                    |
| **Images**              | Hotlinked from source with `loading="lazy"`, `referrerpolicy="no-referrer"`     |
| **Category matching**   | 5-step: RSS tag -> URL path -> meta tag -> keyword rules -> source default      |
| **Story threads**       | Auto-detects continuing stories via title/entity overlap                        |
| **Publishing**          | Direct INSERT into `articles` table with `status = 'published'`                 |
| **Attribution**         | Tooltip with source data, subtle icon on hover                                  |
| **Editing**             | Crawled articles fully editable — same form as original articles                |
| **Duplicate detection** | URL hash + title exact match + slug collision handling                           |
| **Takedown**            | One-click archive or bulk-remove all articles from a source                     |

Cron entry: `cron/crawl.php`

## 23.2 Module 2: SEO Crawler + Image Health Monitor

| Feature                | Details                                                                          |
| ---------------------- | -------------------------------------------------------------------------------- |
| **SEO Audit**    | Crawls own site for 26 checks: broken links, missing meta, thin content, etc.    |
| **Health Score** | 0-100 with critical/warning/passed breakdown and trend tracking                  |
| **Image Health** | Scans hotlinked images via HEAD requests. Auto-replaces broken with placeholder. |
| **Reports**      | Dashboard with drill-down, detail views per issue                                |

Admin views: `/admin/crawler/seo`, `/admin/crawler/seo/{id}` (detail), image health page.

## 23.3 Module 3: Social Media Monitor

| Feature              | Details                                                    |
| -------------------- | ---------------------------------------------------------- |
| **Platforms**  | Twitter/X, Facebook, Google News RSS, Reddit, web search   |
| **Tracking**   | Mentions of Northern Times and configured keywords         |
| **Sentiment**  | Positive / neutral / negative classification               |
| **Alerts**     | Email notifications for negative spikes or trending topics |
| **Competitor** | Monitor what other outlets are covering                    |

Admin view: `/admin/crawler/social`

---

# 24. System Administration Panel (Phase 8)

A dedicated system management interface for the Super Admin.

## 24.1 Safe Resets (Analytics & Counters)

Reset counters to zero without deleting content:
* Article views
* Site visitors
* Ad impressions & clicks
* Popup analytics & dismissals
* Newsletter stats
* Crawler stats
* Reset ALL analytics at once

## 24.2 Content Purges

Permanently delete data:
* Comments
* Subscribers
* Notifications
* Email queue
* Crawl history
* SEO audit history
* Social mentions
* Login attempts

## 24.3 Media Management

* Purge all media
* Clean orphan media (unused files only)
* Regenerate thumbnails

## 24.4 Danger Zone

Requires typing confirmation phrases:
* Delete all articles
* Delete all crawled articles
* Reset all popups
* Reset crawler system
* Factory reset (preserves user accounts, roles, settings, theme, SMTP config)

## 24.5 Database Tools

* Export database as `.sql` dump
* View table sizes
* Run pending migrations
* Vacuum database (PostgreSQL VACUUM ANALYZE)
* Clear cache (flush Redis)
* Automated daily backups via `backup.php` cron

## 24.6 System Log

All reset actions are logged to a permanent `system_log` table that cannot be cleared, even by factory reset. Provides audit trail of who did what, when, from which IP.

---

# 25. Social Auto-Posting (Phase 11)

When an article is published, automatically share to configured social platforms.

## 25.1 Supported Platforms

| Platform                   | Method                        | What Gets Posted                        |
| -------------------------- | ----------------------------- | --------------------------------------- |
| **Facebook Page**    | Graph API (`/page-id/feed`)   | Title + excerpt + link + featured image |
| **Twitter/X**        | API v2 (`POST /tweets`)       | Title (truncated to 250 chars) + link   |
| **WhatsApp Channel** | WhatsApp Business API         | Title + excerpt + link                  |
| **Telegram Channel** | Bot API (`sendMessage`)       | Title + excerpt + link                  |
| **LinkedIn Page**    | API (`/ugcPosts`)             | Title + excerpt + link + image          |

## 25.2 Admin Configuration

* Master ON/OFF switch
* Per-platform enable/disable with API keys
* Customizable message templates per platform
* Post rules: original articles only, skip categories, delay after publish
* Social post log at `/admin/settings` -> Social Log showing per-article post status

Service: `app/Services/SocialPoster.php`
Admin view: `app/Views/admin/social_post_log.php`

---

# 26. Browser Push Notifications (Phase 11)

Push notifications to readers' devices via Web Push protocol.

## 26.1 How It Works

1. Reader visits site, sees custom soft prompt
2. Reader clicks "Enable Notifications"
3. Browser sends push subscription to server (stored in `push_subscriptions` table)
4. On article publish or admin trigger: notification sent to all subscribers via VAPID

## 26.2 Notification Types

| Type                    | Trigger                                          |
| ----------------------- | ------------------------------------------------ |
| **New article**   | On publish (original articles only, not crawled) |
| **Breaking news** | Admin manual trigger                             |
| **Weekly digest** | Scheduled (configurable)                         |

## 26.3 Technical

* Service: `app/Services/WebPush.php`
* Service worker: `public/service-worker.js`
* Admin config: `/admin/push-settings`
* VAPID authentication — self-hosted, no third-party service
* Works on Chrome, Firefox, Edge, Safari (iOS 16.4+)

---

# 27. Breaking News System

## 27.1 Breaking News Ticker

* Scrolling horizontal bar on homepage above hero section
* Red background, white text, "BREAKING" label
* Admin sets ticker text + optional link to article
* Toggle ON/OFF from admin
* Auto-expire: set duration (1hr, 2hr, 6hr, 12hr, 24hr)
* Support 1-5 ticker items, scroll sequentially
* CSS animation with `translateX`, smooth and performant

Service: `app/Services/BreakingNewsEngine.php`

---

# 28. White-Label Architecture

The system is designed as a white-label platform. Every reference to "Northern Times" is configurable from admin settings — a buyer changes settings once and the entire system rebrands.

## 28.1 What's Configurable (All From Admin Settings)

| Category           | Settings                                               |
| ------------------ | ------------------------------------------------------ |
| **Identity** | Site name, abbreviation, tagline, description, copyright text |
| **Branding** | Logo URL, admin logo, favicon URL, default OG image    |
| **Colors**   | Full theme engine via CSS variables                    |
| **Domain**   | Site URL (`APP_URL`)                                   |
| **Email**    | From name, from address, reply-to, SMTP config         |
| **Social**   | Facebook page URL, Twitter handle, Instagram, WhatsApp |
| **Legal**    | Publisher name, registration number, contact address   |
| **Crawler**  | User-Agent string, attribution template                |
| **PWA/Push** | App name, short name, theme color, icon                |

## 28.2 White-Label Handoff

1. Install system (Docker compose up)
2. Log in as Super Admin
3. Settings -> Brand Identity: change name, upload logo, set domain
4. Settings -> Theme: adjust colors
5. System is fully rebranded — zero code changes needed

Migrations: `0047_white_label_settings.sql`, `0048_white_label_week2.sql`, `0049_security_and_whitelabel_completion.sql`

---

# 29. Database Schema

## 29.1 Migrations (58 files)

| Migration | Table/Change                    | Purpose                                      |
| --------- | ------------------------------- | -------------------------------------------- |
| 0001      | Extensions                      | UUID, pgcrypto                               |
| 0002      | `users`                         | User accounts                                |
| 0003      | `categories`                    | Article categories                           |
| 0004      | `articles`                      | Article content                              |
| 0005      | `media_library`                 | Media assets                                 |
| 0006      | `site_settings`                 | Key-value settings                           |
| 0007      | Seed                            | Default admin user                           |
| 0008      | Categories update               | Navigation columns                           |
| 0009      | `newsletter_subscribers`        | Subscriber list                              |
| 0010      | Users update                    | Profile columns (bio, avatar, twitter)       |
| 0011      | Settings update                 | Theme settings                               |
| 0012      | Settings update                 | Branding settings                            |
| 0013      | `policy_pages`                  | Legal/policy content                         |
| 0014      | Settings fix                    | Setting group corrections                    |
| 0015      | `comments`                      | Article comments                             |
| 0016      | `article_views`                 | View tracking                                |
| 0017      | `site_visitors`                 | Visitor analytics                            |
| 0018      | `story_threads`                 | Article series/threads + comments update     |
| 0019      | Breaking news & socials         | Social columns + breaking news               |
| 0020      | `ad_slots`                      | Advertisement system + scores                |
| 0021      | Roles & FK fixes                | Role-based access control                    |
| 0022      | Subscribers fix                 | Column corrections                           |
| 0023      | `login_attempts`                | Security: login tracking                     |
| 0024      | Ad slots update                 | Additional ad placements                     |
| 0025      | Articles update                 | Editorial workflow fields                    |
| 0026      | `notifications`                 | Editor notification system                   |
| 0027      | `email_queue`                   | Email queuing system                         |
| 0028      | `newsletter_issues`             | Newsletter campaign management               |
| 0029      | Subscribers update              | Unsubscribe tokens                           |
| 0030      | Ad slots update                 | Device targeting, dimensions, custom CSS     |
| 0031      | Users update                    | WhatsApp number, website URL columns         |
| 0032      | Article scheduling              | Scheduled article support                    |
| 0033      | `popups`                        | Popup & banner system                        |
| 0034      | Crawler system                  | Crawl sources, crawled articles, logs        |
| 0035      | SEO audit                       | SEO audit tables and image health            |
| 0036      | Social monitor                  | Social mentions and keywords                 |
| 0037      | Breaking news engine v2         | Enhanced breaking news                       |
| 0038      | Full page scrape                | Extended crawler extraction                  |
| 0039      | Masthead ad slots v2            | Updated ad placements                        |
| 0040      | `system_log`                    | Permanent system audit log                   |
| 0041      | System admin expansion v2       | Extended system admin features               |
| 0042      | Visitor coordinates v2          | Lat/lng for reader heatmap                   |
| 0043      | `article_revisions`             | Article revision history                     |
| 0044      | Users display name              | Display name column                          |
| 0045      | `social_posts_log`              | Social auto-posting log                      |
| 0046      | `push_subscriptions`            | Web push notification subscriptions          |
| 0047      | White-label settings            | White-label configuration                    |
| 0048      | White-label week 2              | Extended white-label                         |
| 0049      | Security & white-label complete | Security hardening + white-label completion  |
| 0050      | Newsletter scheduled_at         | Newsletter scheduling support                |
| 0051      | `login_quotes`                  | Inspirational login quotes                   |
| 0052      | Media library full              | Extended media library                       |
| 0053      | Popup A/B testing               | A/B test tables + variant columns on popups  |

Plus seed/maintenance migrations: `seed_crawl_sources.sql`, `seed_dokolo_post.sql`, `cleanup_source_names.sql`, `reset_and_upgrade_crawler.sql`, `2026_02_20_000001_add_display_author_to_articles.sql`

## 29.2 Key Tables

* `users` — Accounts with roles, bio, avatar, display name, 6 social link fields
* `articles` — Content with status workflow (draft/published/scheduled/archived/pending_review), SEO fields, editorial metadata
* `article_revisions` — Edit history with full content snapshots
* `categories` — Hierarchical with navigation controls
* `tags` + `article_tags` — Tag system with pivot table
* `media_library` — Assets with WebP, thumbnails, folder organization
* `comments` — Comments with moderation statuses
* `ad_slots` — Ads with device targeting and scheduling
* `newsletter_subscribers` — With source tracking and unsubscribe tokens
* `newsletter_issues` — Campaign management with soft delete and scheduling
* `notifications` — Editor notification queue
* `email_queue` — SMTP email batch processing
* `site_settings` — Grouped key-value configuration (including white-label)
* `policy_pages` — Legal/policy content pages
* `story_threads` — Article series grouping
* `article_views` — View count tracking
* `site_visitors` — Visitor analytics with coordinates
* `login_attempts` — Security: rate limiting
* `roles` — Custom role definitions with permission sets
* `popups` — Popup configuration (40+ columns, A/B variant support)
* `popup_events` — Impression/click/conversion tracking
* `popup_dismissals` — Visitor dismissal records
* `popup_ab_tests` — A/B test containers with winner tracking
* `popup_ab_assignments` — Sticky visitor-to-variant assignments
* `crawl_sources` — Configured crawl source feeds
* `crawled_articles` — Crawl tracking records
* `crawl_logs` — Per-run crawl results
* `cron_runs` — Cron execution history
* `seo_audits` — SEO audit results with scores
* `seo_issues` — Individual SEO issues found
* `image_health_logs` — Broken image tracking
* `social_mentions` — Social media mention records
* `social_keywords` — Monitored keywords
* `social_posts_log` — Social auto-posting results
* `push_subscriptions` — Web push notification subscribers
* `system_log` — Permanent audit trail (cannot be deleted)
* `login_quotes` — Inspirational quotes for login page
* `active_sessions` — Active user session tracking

---

# 30. Current Completion Status

## All Phases Complete (1-12)

### Phase 1-5: Core CMS
* Article CRUD with full editorial workflow (draft -> review -> publish/reject)
* Category system with hierarchical support and navigation visibility
* Media library with WebP resizing, thumbnails, responsive srcset, duplicate detection
* Newsletter system with compose, preview, send, queue processing, campaign management
* Comment system with AJAX posting, rate limiting, admin moderation, bulk actions
* Subscriber management with source tracking and unsubscribe tokens
* User & role management with RBAC permissions
* Search with keyword matching and category filtering
* Authentication with Redis sessions, rate-limited login, CSRF protection
* Responsive frontend with GeoIP location, flag emoji, social sharing
* Author profiles with bio, avatar, and 6 social media links
* Ad management with device targeting, placement map, live preview
* SMTP email system with TLS/SSL, queue, and admin configuration
* Theme engine with live customization
* Branded error pages (403, 404, 419, 500, 503)
* Docker infrastructure (Nginx + PHP-FPM + PostgreSQL + Redis)

### Phase 6: Popup & Banner System
* 10 popup types with 6 banner styles
* Admin CRUD with live preview
* Targeting by audience, device, page, category
* Analytics dashboard with impression/conversion tracking
* Frontend popup engine with priority queue

### Phase 7: Multi-Purpose Crawler
* News aggregator with auto-publish from RSS feeds
* 5-step category matching
* Story thread detection
* SEO audit engine (26 checks, health score 0-100)
* Image health monitor
* Social media monitor with sentiment analysis
* Crawl log and source management

### Phase 8: System Administration
* Safe resets (analytics counters)
* Content purges (comments, subscribers, etc.)
* Media management (orphan cleanup, thumbnail regeneration)
* Danger zone with typed confirmations (factory reset)
* Database tools (export, vacuum, migrations, cache clear)
* Automated daily backups via `backup.php`
* Permanent system audit log

### Phase 9: Quick Wins
* Dynamic `sitemap.xml` generation
* RSS feed at `/feed`
* `robots.txt` with sitemap reference
* Dark mode toggle with localStorage persistence
* Article scheduling with cron-based auto-publish
* Automated database backups

### Phase 10: Content Features
* Tag system with auto-suggest and trending tags
* Author pages at `/author/{username}` with profile + article grid
* Article revision history with diff and restore
* Breaking news ticker on homepage

### Phase 11: Distribution & Reach
* Social auto-posting to Facebook, X, WhatsApp, Telegram, LinkedIn
* Social post log in admin
* Browser push notifications via VAPID (Web Push)
* Service worker for push notification delivery
* Push notification settings in admin

### Phase 12: Enhancements & A/B Testing
* CKEditor inline image insertion fixed to use native model API (imageBlock elements)
* Reader heatmap upgraded from D3.js vector map to Leaflet satellite view (ESRI World Imagery)
* Heatmap auto-switches between heat overlay (low zoom) and city markers (high zoom)
* Popup A/B testing system with 50/50 traffic split and sticky visitor assignment
* A/B test admin UI: create, manage, compare variants, declare winner
* Server-side variant selection integrated into popup delivery API

### Phase 0: White-Label Architecture
* All hardcoded strings replaced with `get_site_setting()` calls
* Full brand identity configurable from admin
* 10-minute rebrand process with zero code changes

---

# 31. Scalability Design

The platform is designed to:

* Support high article volume with efficient PostgreSQL queries and indexing
* Scale multi-admin operations with RBAC role system
* Support additional content types (video, podcast, galleries — future)
* Scale media storage with folder organization and responsive variants
* Integrate third-party services (analytics, payment, push notifications)
* Handle traffic spikes with Nginx caching and Redis sessions

Its modular, phase-based architecture allows expansion without full system rewrites.

---

# 32. File Structure

```
northern-times/
|-- app/
|   |-- Controllers/           # 22 controllers
|   |   |-- Controller.php            # Base controller
|   |   |-- AdminController.php       # Dashboard, articles, main admin
|   |   |-- AdminAdController.php     # Ad slot management
|   |   |-- AdminCategoryController.php
|   |   |-- AdminCommentController.php
|   |   |-- AdminCrawlerController.php  # Crawler sources, logs, settings
|   |   |-- AdminLoginQuoteController.php
|   |   |-- AdminMediaController.php
|   |   |-- AdminNewsletterController.php
|   |   |-- AdminPolicyController.php
|   |   |-- AdminPopupController.php    # Popup management + analytics + A/B testing
|   |   |-- AdminProfileController.php
|   |   |-- AdminReviewController.php
|   |   |-- AdminRoleController.php
|   |   |-- AdminSeoController.php      # SEO audit views
|   |   |-- AdminSettingsController.php
|   |   |-- AdminSocialController.php   # Social monitor
|   |   |-- AdminSubscriberController.php
|   |   |-- AdminSystemController.php   # System admin, resets, backups
|   |   |-- AdminUserController.php
|   |   |-- FrontendController.php      # Public pages, RSS, sitemap
|   |   +-- FrontendPolicyController.php
|   |-- Models/                # 31 models
|   |   |-- ActiveSession.php
|   |   |-- AdSlot.php
|   |   |-- Article.php
|   |   |-- ArticleRevision.php
|   |   |-- ArticleView.php
|   |   |-- BaseModel.php
|   |   |-- Category.php
|   |   |-- Comment.php
|   |   |-- CrawlLog.php
|   |   |-- CrawlSource.php
|   |   |-- CronRun.php
|   |   |-- DbBackup.php
|   |   |-- ImageHealthLog.php
|   |   |-- LoginQuote.php
|   |   |-- MediaItem.php
|   |   |-- NewsletterIssue.php
|   |   |-- Notification.php
|   |   |-- PolicyPage.php
|   |   |-- Popup.php
|   |   |-- Role.php
|   |   |-- SeoAudit.php
|   |   |-- SeoIssue.php
|   |   |-- Setting.php
|   |   |-- SiteVisitor.php
|   |   |-- SocialKeyword.php
|   |   |-- SocialMention.php
|   |   |-- StoryThread.php
|   |   |-- Subscriber.php
|   |   |-- SystemLog.php
|   |   |-- Tag.php
|   |   +-- User.php
|   |-- Services/              # 26 services
|   |   |-- ArticleScraper.php
|   |   |-- Auth.php
|   |   |-- BreakingNewsEngine.php
|   |   |-- Cache.php
|   |   |-- CategoryMatcher.php
|   |   |-- ContentNormalizer.php
|   |   |-- CrawlerEngine.php
|   |   |-- Csrf.php
|   |   |-- DB.php
|   |   |-- Flash.php
|   |   |-- GeoIP.php
|   |   |-- Image.php
|   |   |-- ImageDownloader.php
|   |   |-- ImageHealthChecker.php
|   |   |-- Mailer.php
|   |   |-- Media.php
|   |   |-- RBAC.php
|   |   |-- RateLimiter.php
|   |   |-- Sanitizer.php
|   |   |-- SeoAuditEngine.php
|   |   |-- SeoReportPdf.php
|   |   |-- Slug.php
|   |   |-- SocialMonitorService.php
|   |   |-- SocialPoster.php
|   |   |-- StoryThreadDetector.php
|   |   +-- WebPush.php
|   |-- Middleware/             # 8 middleware classes
|   |   |-- AuthMiddleware.php
|   |   |-- CsrfMiddleware.php
|   |   |-- MiddlewareInterface.php
|   |   |-- PermissionMiddleware.php
|   |   |-- Pipeline.php
|   |   |-- RoleMiddleware.php
|   |   |-- SecurityHeadersMiddleware.php
|   |   +-- VisitorMiddleware.php
|   |-- Support/
|   |   +-- helpers.php        # Global helper functions
|   +-- Views/
|       |-- admin/             # 46 admin views
|       |   |-- dashboard.php, layout.php, login.php
|       |   |-- articles.php, article_form.php, articles_archive.php
|       |   |-- analytics.php, profile.php, settings.php
|       |   |-- social_post_log.php, push_settings.php
|       |   |-- ads/, categories/, comments/, crawler/
|       |   |-- login-quotes/, media/, newsletter/
|       |   |-- policies/, popups/, review/, roles/
|       |   |-- subscribers/, system/, users/
|       |-- errors/            # 5 error pages (403, 404, 419, 500, 503)
|       +-- frontend/          # 9 frontend views
|           |-- home.php, article.php, category.php
|           |-- search.php, author.php, tag.php
|           |-- policy.php, unsubscribe.php
|           +-- layout.php
|-- database/
|   +-- migrations/            # 58 migration files
|-- docker/
|   |-- nginx/                 # default.conf + production-ssl.conf
|   +-- php/                   # Dockerfile + php.ini
|-- public/
|   |-- assets/                # CSS, JS, images (including dark-mode.css/js, admin/reader-map.js, admin/dashboard.css)
|   |-- index.php              # Application entry point
|   |-- robots.txt
|   +-- service-worker.js      # Push notification service worker
|-- routes/
|   |-- admin.php              # Admin routes with middleware
|   +-- web.php                # Public routes
|-- storage/
|   |-- backups/               # Database backups
|   |-- uploads/               # Media uploads (Docker volume)
|   |-- cache/                 # Application cache
|   +-- logs/                  # Application logs
|-- cron/
|   |-- crawl.php              # News crawler cron
|   +-- seed_sources.php       # Seed crawl sources
|-- tests/                     # 27 test files
|   |-- Unit/
|   |-- Integration/
|   |-- Feature/
|   +-- bootstrap.php
|-- schedule.php               # Article scheduling cron
|-- backup.php                 # Database backup cron
|-- migrate.php                # Migration runner
|-- docker-compose.yml
|-- composer.json
+-- README.md
```

---

# 33. Strategic Positioning

The Northern Times platform is:

* A digital newsroom infrastructure for Northern Uganda
* A regional information hub with credible journalism
* A scalable publishing engine with modern architecture
* A structured editorial system with review workflows
* A white-label platform sellable to other news organizations
* A long-term digital institution built for growth

It balances old-school newsroom standards with modern digital architecture.

---

# 34. Requirements

* Docker + Docker Compose
* PHP 8.2 + Nginx + PostgreSQL 15 (provided via Docker)
* Redis 7 (provided via Docker)

---

# 35. Setup

1. **Clone and build:**
   ```bash
   git clone https://github.com/bododesderio/northern-times.git
   cd northern-times
   docker compose up -d --build
   ```
2. **Install dependencies:**
   ```bash
   docker compose exec app composer install
   ```
3. **Run migrations:**
   ```bash
   docker compose exec app php migrate.php
   ```
4. **Open:**
   * Frontend: http://localhost:8080
   * Admin: http://localhost:8080/admin

## Optional Tools

* **pgAdmin** (database browser):

  ```bash
  docker compose --profile tools up -d
  ```

  Open: http://localhost:5050
* **Redis** (session/cache layer):

  ```bash
  docker compose --profile cache up -d
  ```

## Running Tests

```bash
docker compose exec app php vendor/bin/phpunit
```

## Cron Jobs

```bash
# Article scheduling (every minute)
* * * * * php /var/www/html/schedule.php

# News crawler (every 30 minutes)
*/30 * * * * php /var/www/html/cron/crawl.php

# Database backup (daily at 2 AM)
0 2 * * * php /var/www/html/backup.php
```

---

*End of documentation. Last updated: March 7, 2026 (v6.0 -- All phases 1-12 complete, satellite heatmap + popup A/B testing).*

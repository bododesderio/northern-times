# THE NORTHERN TIMES

## Comprehensive Digital Platform & Editorial System Documentation

**Version:** 2.0 — February 2026
**Status:** Production-ready, Phase 5 complete, Phase 6 (Popup & Banner System) planned

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
* SEO-ready framework with JSON-LD structured data
* GeoIP location detection with flag emoji rendering
* Theme engine with live customization from admin
* Secure admin control panel with RBAC (Role-Based Access Control)
* Comprehensive error handling with branded error pages
* Automated test suite (88 tests passing)
* Future-ready modular expansion (Popup & Banner System planned)

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

---

# 3. System Architecture

## 3.1 Backend

* **Language:** PHP 8.2 (Modular MVC architecture)
* **Database:** PostgreSQL 15 with UUID primary keys
* **Routing:** Symfony Routing component
* **Authentication:** Secure session-based with Redis storage (file-based fallback)
* **Security:** CSRF protection, RBAC permissions, rate limiting, input sanitization
* **Email:** Direct SMTP client with TLS/SSL, supports Gmail/Outlook/SendGrid/Mailgun/SES
* **Services:** Auth, CSRF, DB, Flash, GeoIP, Image, Mailer, Media, RBAC, RateLimiter, Sanitizer, Slug

## 3.2 Frontend

* Semantic HTML5 with responsive CSS
* Vanilla JavaScript (no framework dependency)
* CKEditor 5 rich text integration
* Responsive images with `srcset` and multiple breakpoints (320, 640, 960, 1280, 1920px)
* Social share buttons (X/Twitter, Facebook, WhatsApp, LinkedIn, Email, Copy Link)
* GeoIP-powered location display with country flag emoji
* AJAX comment submission with real-time updates
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

---

# 4. Public Website Structure

## 4.1 Homepage

The homepage includes:

* Hero story carousel with responsive images (`fetchpriority="high"` on first slide)
* Latest articles grid
* Category-based segmentation
* Top stories section
* Sidebar with trending/most-read articles
* Newsletter subscription block
* Homepage spotlight ad slot
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
* Category badge
* Publish date & time with relative time display (e.g., "6 hours ago", "yesterday", then full date)
* Featured image with responsive `srcset` (5 breakpoints)
* Rich HTML content
* Social share buttons (centered, top and bottom of article)
* In-article and sidebar ad slots (sidebar only renders when ad is active)
* Story thread banner (linking related articles in a series)
* Comment section with AJAX posting (name, optional email, rate-limited)
* Related stories grid (4 articles, same category)
* JSON-LD structured data for SEO
* Copy link button with clipboard API + fallback

## 4.4 Search Pages

* Keyword search across article titles and content
* Category filtering
* Clean result pages with pagination
* Search-optimized queries

## 4.5 Policy Pages

* Privacy Policy, Terms of Service, and custom pages
* Managed via admin with rich text editor
* Clean URLs (`/policy/{slug}`)

## 4.6 Unsubscribe Page

* Token-based secure unsubscribe
* One-click unsubscribe from newsletter emails
* Confirmation message

---

# 5. Admin Panel System

The admin system is the operational core of The Northern Times. It uses a grouped collapsible sidebar for navigation and a comprehensive dashboard for at-a-glance monitoring.

## 5.1 Sidebar Navigation (Grouped & Collapsible)

The sidebar organizes 25+ admin pages into logical groups. Each group collapses/expands on click. The active group auto-expands. Collapsed state persists in localStorage.

```
┌──────────────────────────────┐
│  🗞  THE NORTHERN TIMES       │
│  ──────────────────────────  │
│                              │
│  📊  Dashboard               │
│                              │
│  ▼ CONTENT                   │
│     📝  Articles        (24) │
│     📁  Categories           │
│     🖼  Media Library        │
│     📄  Policy Pages         │
│                              │
│  ▼ EDITORIAL                 │
│     🔍  Review Queue     (3) │
│     🔔  Notifications    (5) │
│     💬  Comments        (12) │
│                              │
│  ▼ GROWTH                    │
│     📢  Popups           (4) │
│     📰  Newsletter           │
│     👥  Subscribers          │
│     📣  Ads                  │
│                              │
│  ▼ CRAWLER                   │
│     📡  Sources          (8) │
│     📋  Crawl Log            │
│     🔍  SEO Audit            │
│     📊  Social Monitor       │
│     ⚙️  Crawler Settings     │
│                              │
│  ▼ ADMIN                     │
│     👤  Users                │
│     🔐  Roles                │
│     ⚙️  Settings             │
│     🎨  Profile              │
│                              │
│  ▼ SYSTEM ⚠️  (Super Admin)   │
│     🔧  System Admin         │
│     🟢  Safe Resets          │
│     🟡  Content Purges       │
│     🟠  Media Management     │
│     🔴  Danger Zone          │
│                              │
│  ──────────────────────────  │
│  🌐  View Live Site  →       │
│  🚪  Logout                  │
└──────────────────────────────┘
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

| Group               | Contains                                                | Who Sees It                      |
| ------------------- | ------------------------------------------------------- | -------------------------------- |
| **CONTENT**   | Articles, Categories, Media Library, Policy Pages       | All roles                        |
| **EDITORIAL** | Review Queue, Notifications, Comments                   | Editors, Admins                  |
| **GROWTH**    | Popups, Newsletter, Subscribers, Ads                    | Editors, Admins                  |
| **CRAWLER**   | Sources, Crawl Log, SEO Audit, Social Monitor, Settings | Editors, Admins                  |
| **ADMIN**     | Users, Roles, Settings, Profile                         | Profile: all. Rest: Admins only. |
| **SYSTEM**    | System Admin (with sub-pages)                           | Super Admin only                 |

## 5.2 Main Dashboard (`/admin`)

The dashboard is a comprehensive command center with 8 widget sections. Widgets use a responsive grid that adapts from 1 column (mobile) to 4 columns (desktop).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Dashboard                                    Feb 28, 2026 · 2:34 PM       │
│                                                                             │
│  ┌── Quick Actions ─────────────────────────────────────────────────────┐  │
│  │                                                                       │  │
│  │  [✏️ Write Article]  [📰 Compose Newsletter]  [📡 Run Crawl Now]     │  │
│  │  [📢 Create Popup]   [📤 Export Subscribers]   [🔍 Run SEO Audit]    │  │
│  │                                                                       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ═══════════════════════════════════════════════════════════════════════    │
│                                                                             │
│  ┌── 📝 Content ────────────────┐  ┌── 👥 Audience ────────────────────┐  │
│  │                              │  │                                    │  │
│  │  Published    1,842          │  │  Visitors today     1,247          │  │
│  │  Drafts          24          │  │  Page views today   4,832          │  │
│  │  Pending review    3  ⚡     │  │  Avg. time on site  2m 34s         │  │
│  │  Archived         67         │  │  Bounce rate       42.3%           │  │
│  │                              │  │                                    │  │
│  │  Today:     8 published      │  │  ┌─ Top Articles Today ────────┐  │  │
│  │  This week: 47 published     │  │  │ 1. Uganda WB Grant    847   │  │  │
│  │  Crawled:   34 auto-posted   │  │  │ 2. EAC Trade Summit   623   │  │  │
│  │  Original:  13 by team       │  │  │ 3. Football Results   412   │  │  │
│  │                              │  │  │ 4. Health Minister    389   │  │  │
│  │  [View Articles →]           │  │  │ 5. Road Construction  274   │  │  │
│  │                              │  │  └──────────────────────────────┘  │  │
│  └──────────────────────────────┘  │                                    │  │
│                                    │  ┌─ Visitor Locations ──────────┐  │  │
│                                    │  │  🇺🇬 Uganda       72.3%      │  │  │
│                                    │  │  🇰🇪 Kenya         8.1%      │  │  │
│                                    │  │  🇹🇿 Tanzania      4.2%      │  │  │
│                                    │  │  🇬🇧 UK            3.8%      │  │  │
│                                    │  │  🇺🇸 USA           2.9%      │  │  │
│                                    │  │  🌍 Other          8.7%      │  │  │
│                                    │  └──────────────────────────────┘  │  │
│                                    │  [View Full Analytics →]           │  │
│                                    └────────────────────────────────────┘  │
│                                                                             │
│  ┌── 📡 Crawler ────────────────┐  ┌── 📢 Popups ──────────────────────┐  │
│  │                              │  │                                    │  │
│  │  Sources active    8  🟢     │  │  Active popups       4             │  │
│  │  Last crawl     4 min ago    │  │  Impressions today   3,241         │  │
│  │  Next crawl     26 min       │  │  Conversions today   187           │  │
│  │                              │  │  Conversion rate     5.8%          │  │
│  │  Today:                      │  │                                    │  │
│  │  Auto-published   34         │  │  ┌─ Top Performers ─────────────┐ │  │
│  │  Skipped (dupes)   7         │  │  │ Newsletter Signup   8.4% ✅  │ │  │
│  │  Errors            0  ✅     │  │  │ Cookie Consent    97.0% ✅  │ │  │
│  │                              │  │  │ MTN Sponsor Ad     2.3%     │ │  │
│  │  This month: 1,247 total     │  │  │ Exit Intent       12.1% ✅  │ │  │
│  │                              │  │  └───────────────────────────────┘ │  │
│  │  ┌─ Source Health ─────────┐ │  │                                    │  │
│  │  │ Daily Monitor    ✅ OK  │ │  │  [Manage Popups →]                │  │
│  │  │ New Vision       ✅ OK  │ │  └────────────────────────────────────┘  │
│  │  │ Reuters Africa   ✅ OK  │ │                                          │
│  │  │ NBS TV           ⏸ Pause│ │                                          │
│  │  │ The Observer     ⚠️ Err │ │                                          │
│  │  └─────────────────────────┘ │                                          │
│  │                              │                                          │
│  │  [View Sources →]            │                                          │
│  └──────────────────────────────┘                                          │
│                                                                             │
│  ┌── 📣 Revenue & Ads ─────────┐  ┌── 📰 Subscribers ─────────────────┐  │
│  │                              │  │                                    │  │
│  │  Impressions today  5,120    │  │  Total subscribers   2,847         │  │
│  │  Clicks today          47    │  │  New this week       +34           │  │
│  │  CTR               0.92%    │  │  Unsubscribed           2          │  │
│  │  Active ad slots       6    │  │  Growth rate        +1.2%          │  │
│  │                              │  │                                    │  │
│  │  ┌─ Top Performing Ads ────┐ │  │  ┌─ Source Breakdown ───────────┐ │  │
│  │  │ Top Banner      1.4% CTR│ │  │  │ Popup signup      18  ████  │ │  │
│  │  │ Sidebar         0.8% CTR│ │  │  │ Footer form        9  ██    │ │  │
│  │  │ In-Article      1.1% CTR│ │  │  │ Comment opt-in     4  █     │ │  │
│  │  │ Homepage Spot   2.1% CTR│ │  │  │ Manual import      3  █     │ │  │
│  │  └─────────────────────────┘ │  │  └───────────────────────────────┘ │  │
│  │                              │  │                                    │  │
│  │  Estimated revenue: $24/day  │  │  [View Subscribers →]              │  │
│  │  (based on configured CPM)   │  │  [Compose Newsletter →]            │  │
│  │                              │  └────────────────────────────────────┘  │
│  │  [Manage Ads →]              │                                          │
│  └──────────────────────────────┘                                          │
│                                                                             │
│  ┌── 💻 System Health ──────────────────────────────────────────────────┐  │
│  │                                                                       │  │
│  │  Database    247 MB  ████████░░ 62%     Redis     Connected ✅        │  │
│  │  Disk       1.2 GB  ██░░░░░░░░  8%     PHP       8.2.14 ✅           │  │
│  │  Articles   1,842 (1,247 crawled)      PostgreSQL 15.4 ✅             │  │
│  │  Media      3,204 files                Uptime     34 days ✅          │  │
│  │                                                                       │  │
│  │  ┌─ Recent Activity ─────────────────────────────────────────────┐   │  │
│  │  │ 2:30 PM  Crawler published 4 articles from Daily Monitor      │   │  │
│  │  │ 2:15 PM  @editor approved "Infrastructure Bill Analysis"     │   │  │
│  │  │ 2:00 PM  Crawler published 3 articles from Reuters Africa     │   │  │
│  │  │ 1:45 PM  New subscriber via popup (popup_newsletter)         │   │  │
│  │  │ 1:30 PM  Crawler published 6 articles from New Vision         │   │  │
│  │  └───────────────────────────────────────────────────────────────┘   │  │
│  │                                                                       │  │
│  │  [System Admin →]  (Super Admin only)                                │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ─── Today's Summary ───────────────────────────────────────────────────   │
│  8 articles published · 34 auto-crawled · 1,247 visitors · 187 popup      │
│  conversions · 47 ad clicks · 34 new subscribers · 0 system errors        │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2.1 Dashboard Widgets — Data Sources

| Widget                    | Data Source                                                            | Refresh                      |
| ------------------------- | ---------------------------------------------------------------------- | ---------------------------- |
| **Quick Actions**   | Static buttons → links to create pages                                | —                           |
| **Content**         | `articles`COUNT by status + GROUP BY date                            | Page load                    |
| **Audience**        | `site_visitors`+`article_views`aggregated                          | Page load + auto-refresh 60s |
| **Crawler**         | `crawl_sources`+`crawled_articles`+ source health                  | Page load + auto-refresh 30s |
| **Popups**          | `popup_events`aggregated +`popups`active count                     | Page load                    |
| **Revenue & Ads**   | `ad_slots`stats + configured CPM                                     | Page load                    |
| **Subscribers**     | `newsletter_subscribers`COUNT + GROUP BY source/date                 | Page load                    |
| **System Health**   | PostgreSQL `pg_database_size()`+ Redis `PING`+`df`disk           | Page load                    |
| **Recent Activity** | Composite: latest from crawl log, articles, notifications, subscribers | Page load + auto-refresh 30s |
| **Today's Summary** | Aggregated totals from all widgets                                     | Page load                    |

### 5.2.2 Dashboard Layout — Responsive Grid

| Screen                         | Layout                                                                        |
| ------------------------------ | ----------------------------------------------------------------------------- |
| **Desktop (≥1200px)**   | 2 columns, widgets side-by-side as shown above                                |
| **Tablet (768–1199px)** | 2 columns, narrower widgets                                                   |
| **Mobile (<768px)**      | 1 column, widgets stacked vertically. Quick Actions become horizontal scroll. |

### 5.2.3 Auto-Refresh

| Widget              | Interval          | Method                                               |
| ------------------- | ----------------- | ---------------------------------------------------- |
| Audience (visitors) | Every 60 seconds  | AJAX fetch, update numbers with fade animation       |
| Crawler activity    | Every 30 seconds  | AJAX fetch, update source health indicators          |
| Recent Activity     | Every 30 seconds  | AJAX fetch, prepend new entries with slide animation |
| All others          | On page load only | Full render, no auto-refresh                         |

Admin can disable auto-refresh via a toggle: 🔄 Live (green) / ⏸ Paused (gray) in top-right corner.

### 5.2.4 Role-Based Dashboard

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
| —        | Dashboard        | `/admin`                      | Command center with 8 widget sections  |
| CONTENT   | Articles         | `/admin/articles`             | Full CRUD with status management       |
| CONTENT   | Categories       | `/admin/categories`           | Hierarchical category management       |
| CONTENT   | Media Library    | `/admin/media`                | Upload, organize, pick images          |
| CONTENT   | Policy Pages     | `/admin/policies`             | Legal/policy page editor               |
| EDITORIAL | Review Queue     | `/admin/review`               | Editorial review and approval          |
| EDITORIAL | Notifications    | `/admin/review/notifications` | Editor notification inbox              |
| EDITORIAL | Comments         | `/admin/comments`             | Moderation, bulk actions               |
| GROWTH    | Popups           | `/admin/popups`               | Popup/banner management + analytics    |
| GROWTH    | Newsletter       | `/admin/newsletter`           | Compose, preview, send campaigns       |
| GROWTH    | Subscribers      | `/admin/subscribers`          | View, search, manage subscribers       |
| GROWTH    | Ads              | `/admin/ads`                  | Ad slot management with targeting      |
| CRAWLER   | Sources          | `/admin/crawler/sources`      | Configure crawl sources                |
| CRAWLER   | Crawl Log        | `/admin/crawler/log`          | Per-run crawl results                  |
| CRAWLER   | SEO Audit        | `/admin/crawler/seo`          | Site health + image monitoring         |
| CRAWLER   | Social Monitor   | `/admin/crawler/social`       | Mention tracking + sentiment           |
| CRAWLER   | Crawler Settings | `/admin/crawler/settings`     | Global crawl configuration             |
| ADMIN     | Users            | `/admin/users`                | User management (Super Admin)          |
| ADMIN     | Roles            | `/admin/roles`                | Role and permission management         |
| ADMIN     | Settings         | `/admin/settings`             | Site settings, branding, theme, SMTP   |
| ADMIN     | Profile          | `/admin/profile`              | Personal profile with social links     |
| SYSTEM    | System Admin     | `/admin/system`               | Resets, purges, DB tools (Super Admin) |

---

# 6. Article Management System

Editors can:

* Create, edit, delete, and archive articles
* Assign categories
* Set article status (Draft / Published / Archived / Pending Review)
* Add excerpts and featured images
* Add custom display author names
* Automatically generate publish timestamps

## 6.1 Rich Text Editor

Integrated CKEditor 5 provides:

* Headings (H1–H4), paragraphs
* Ordered and unordered lists
* Bold, italic, underline, strikethrough
* Block quotes and tables
* Image embedding and link insertion
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

## 6.4 Relative Time Display (Planned)

Articles will show human-friendly timestamps:

* Under 60 seconds: "just now"
* Under 60 minutes: "X minutes ago"
* Under 24 hours: "X hours ago"
* Yesterday: "yesterday"
* Older: full date (e.g., "February 15, 2026")

Applies to article cards on homepage, category pages, search results, and article page metadata.

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
| `top-banner`         | Above article content | Full-width leaderboard   |
| `article-sidebar`    | Right column (sticky) | Only renders when active |
| `in-article`         | Within article body   | Mid-content placement    |
| `below-article`      | After article content | Post-read placement      |
| `homepage-spotlight` | Homepage hero area    | Premium placement        |
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
* Comment section opt-in checkbox (planned)
* Popup signup forms (planned)
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

* Username and email
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

## 15.2 Branding Settings (`/admin/settings`)

* Site title, tagline, description
* Site logo URL (via media picker)
* Favicon URL (renders in both frontend and admin)
* Default OG image for social sharing
* Footer text customization

---

# 16. GeoIP & Performance

## 16.1 GeoIP Location Detection

* `GeoIP::clientIP()` extracts real client IP from proxy headers
* `GeoIP::lookup()` returns country, city, timezone, coordinates
* Timezone-to-country fallback mapping (20+ regions including East/West Africa)
* Server-side rendering of country flag emoji + location label
* JavaScript upgrade with client-side API for better accuracy
* IP caching to reduce API calls

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
* Unique slug enforcement
* SHA256 duplicate media detection
* Protected upload directories
* Restricted admin routes with middleware
* RBAC permission checks
* Rate limiting on comments and login attempts
* Login attempt tracking and lockout

---

# 20. SEO & Metadata Framework

* Clean URL structure (`/article/{slug}`, `/category/{slug}`)
* Canonical links
* Meta title and description per page
* Open Graph tags (title, description, image, type)
* Default OG image fallback from settings
* JSON-LD structured data on article pages (NewsArticle schema)
* Article schema with author, publisher, datePublished, wordCount
* Sitemap readiness
* RSS readiness

---

# 21. Testing

## Automated Test Suite

* **Framework:** PHPUnit
* **Total tests:** 88 (all passing)
* **Coverage areas:**
  * Unit tests: Models, services, helpers
  * Integration tests: Controllers, middleware, routing
  * Feature tests: Authentication, CSRF, article publishing, comment posting
* **Test database:** Separate test configuration with migrations

---

# 22. Database Schema

## 22.1 Migrations (30 files)

| Migration | Table/Change               | Purpose                                                        |
| --------- | -------------------------- | -------------------------------------------------------------- |
| 0001      | Extensions                 | UUID, pgcrypto                                                 |
| 0002      | `users`                  | User accounts                                                  |
| 0003      | `categories`             | Article categories                                             |
| 0004      | `articles`               | Article content                                                |
| 0005      | `media_library`          | Media assets                                                   |
| 0006      | `site_settings`          | Key-value settings                                             |
| 0007      | Seed                       | Default admin user                                             |
| 0008      | Categories update          | Navigation columns                                             |
| 0009      | `newsletter_subscribers` | Subscriber list                                                |
| 0010      | Users update               | Profile columns (bio, avatar, twitter)                         |
| 0011      | Settings update            | Theme settings                                                 |
| 0012      | Settings update            | Branding settings                                              |
| 0013      | `policy_pages`           | Legal/policy content                                           |
| 0014      | Settings fix               | Setting group corrections                                      |
| 0015      | `comments`               | Article comments                                               |
| 0016      | `article_views`          | View tracking                                                  |
| 0017      | `site_visitors`          | Visitor analytics                                              |
| 0018      | `story_threads`          | Article series/threads                                         |
| 0019      | Users update               | Social columns (Facebook, LinkedIn, Instagram) + breaking news |
| 0020      | `ad_slots`               | Advertisement system                                           |
| 0021      | Roles & FK fixes           | Role-based access control                                      |
| 0022      | Subscribers fix            | Column corrections                                             |
| 0023      | `login_attempts`         | Security: login tracking                                       |
| 0024      | Ad slots update            | Additional ad placements                                       |
| 0025      | Articles update            | Editorial workflow fields                                      |
| 0026      | `notifications`          | Editor notification system                                     |
| 0027      | `email_queue`            | Email queuing system                                           |
| 0028      | `newsletter_issues`      | Newsletter campaign management                                 |
| 0029      | Subscribers update         | Unsubscribe tokens                                             |
| 0030      | Ad slots update            | Device targeting, dimensions, custom CSS                       |
| 0031      | Users update               | WhatsApp number, website URL columns                           |

## 22.2 Key Tables

* `users` — Accounts with roles, bio, avatar, 6 social link fields
* `articles` — Content with status workflow, SEO fields, editorial metadata
* `categories` — Hierarchical with navigation controls
* `media_library` — Assets with WebP, thumbnails, folder organization
* `comments` — Threaded comments with moderation statuses
* `ad_slots` — Ads with device targeting and scheduling
* `newsletter_subscribers` — With source tracking and unsubscribe tokens
* `newsletter_issues` — Campaign management with soft delete
* `notifications` — Editor notification queue
* `email_queue` — SMTP email batch processing
* `site_settings` — Grouped key-value configuration
* `policy_pages` — Legal/policy content pages
* `story_threads` — Article series grouping
* `article_views` — View count tracking
* `site_visitors` — Visitor analytics
* `login_attempts` — Security: rate limiting
* `roles` — Custom role definitions with permission sets

---

# 23. Current Completion Status

## Fully Operational (Phases 1–5 Complete)

* Article CRUD with full editorial workflow (draft → review → publish/reject)
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
* Favicon rendering in both frontend and admin
* 88 automated tests passing
* Docker infrastructure (Nginx + PHP-FPM + PostgreSQL + Redis)

## Recently Fixed

* Media picker pagination wrapper bug (was iterating metadata instead of rows)
* Article author card missing bio/avatar/role (query not selecting from users table)
* Share button alignment (centered with flex justify-content)
* Copy link button SVG escaping issue
* Flag emoji rendering on PHP builds without supplementary plane support
* CSRF token field mismatch (`_token` vs `_csrf`)
* Redis dependency in Docker causing session failures
* Related articles parameter order bug
* Mailer using PHP `mail()` which requires local MTA (replaced with direct SMTP)
* Article sidebar grid showing blank space when ad slot is inactive

## Planned — Relative Time Display

Articles will show human-friendly relative timestamps:

| Age          | Display                          |
| ------------ | -------------------------------- |
| < 60 seconds | "just now"                       |
| < 60 minutes | "X minutes ago"                  |
| < 24 hours   | "X hours ago"                    |
| Yesterday    | "yesterday"                      |
| Older        | Full date (e.g., "Feb 15, 2026") |

Implementation:

* New helper function `relative_time(string $datetime): string`
* Applied on: homepage cards, category listings, search results, article page meta, comment timestamps
* Server-side PHP rendering (no JS dependency)

---

# 24. Planned — Popup & Banner System (Phase 6)

A comprehensive popup engine managed entirely from the admin portal.

## 24.1 Banner Styles (6 Types)

| Style               | Layout                    | Image Size    | Best For                  |
| ------------------- | ------------------------- | ------------- | ------------------------- |
| Minimal Bar         | Horizontal row            | 32×32px icon | Cookie consent, alerts    |
| Card Modal          | Vertical stack, image top | 600×300px    | Newsletter signup, promos |
| Split Image Modal   | Two-column, image left    | 400×500px    | Promotions, ads           |
| Full-Screen Overlay | Background image + card   | 1920×1080px  | Breaking news, launches   |
| Slide-In Panel      | Corner card               | 300×200px    | Welcome back, surveys     |
| Floating Banner     | Horizontal card           | 120×120px    | Ad popups, sponsors       |

## 24.2 Popup Types (10)

| Type                 | Position              | Trigger               | Purpose               |
| -------------------- | --------------------- | --------------------- | --------------------- |
| Cookie Consent       | Bottom bar            | First visit           | Legal compliance      |
| Newsletter Signup    | Center modal          | 30s / 50% scroll      | List building         |
| Breaking News Alert  | Top bar (sticky)      | Admin-triggered       | Engagement            |
| Welcome Back         | Bottom-right slide-in | Returning visitor     | Retention             |
| Exit Intent          | Center modal          | Mouse leaves viewport | Last-chance capture   |
| Ad Popup             | Center or floating    | Time/page views       | Sponsor monetization  |
| Promotion / Campaign | Center modal          | Time/scroll           | Own events/offers     |
| App Download / PWA   | Bottom bar            | Mobile, 3rd visit     | Mobile retention      |
| Paywall / Metered    | Center overlay        | After N free articles | Monetization (future) |
| Survey / Feedback    | Bottom-right slide-in | After 3+ articles     | Reader insights       |

## 24.3 Admin Controls

* Full CRUD: create, edit, duplicate, toggle, delete popups
* Live preview panel (real-time, no save needed)
* Full-page preview with position simulation
* Mobile/Desktop device preview toggle
* Visual banner style selector (6 cards with wireframes)
* Visual position selector (page diagram with clickable zones)
* Color pickers: background, text, button, button text
* Image upload via media picker with recommended sizes
* Targeting: audience (all/first-time/returning/subscribers/non-subscribers), device, page, category
* Triggers: page load, scroll %, exit intent, manual, page view count
* Configurable intervals: once ever, once per session, daily, custom X days, weekly, monthly, every visit
* Show delay (seconds before popup appears)
* Close delay (seconds before X button appears)
* Schedule: start date, end date, priority ranking
* Status management: Active, Inactive, Draft, Scheduled, Expired
* Recalibrate panel: shows impact of changes, reset dismissals, reset analytics
* Ad-specific: sponsor name, click URL, nofollow, CPM tracking
* Promo-specific: promo code, campaign name, CTA URL
* Newsletter: email field, subscriber source tag

## 24.4 Analytics Dashboard

* **Main dashboard widget** : impressions, conversions, conversion rate, top performers, subscriber source breakdown
* **Dedicated analytics page** (`/admin/popups/analytics`):
  * Summary cards with % change vs previous period
  * Impressions & conversions line chart over time
  * Per-popup performance table
  * Conversion by position, type, device, and page
  * Subscriber acquisition source breakdown
  * Hourly heatmap (best time to show popups)
  * Period selector (today, yesterday, 7/30/90 days, custom)
  * CSV export
  * Compare popups side-by-side

## 24.5 Database Tables

* `popups` — Full configuration (40+ columns)
* `popup_events` — Impression/click/conversion/close tracking
* `popup_dismissals` — Visitor dismissal records
* `subscribers.source` — Updated to track acquisition channel

## 24.6 Frontend Engine

* Lightweight `popups.js` script
* Fetches active popups via `/api/popups`
* Queue system: never shows more than 1 popup at a time
* Priority-based ordering
* Interval checking via localStorage/sessionStorage
* Tracks events via `POST /api/popup-track`
* Version-based dismissal reset

## 24.7 Comment → Newsletter Pipeline (Planned)

* Opt-in checkbox on comment form: "Subscribe me to the newsletter"
* Auto-insert into subscribers table with `source = 'comment'`
* Soft prompt for anonymous commenters after posting

## 24.8 Execution Phases

* **Phase A (Foundation):** Migrations, models, admin CRUD, analytics, preview, frontend engine, cookie consent
* **Phase B (Growth):** Newsletter popup, comment opt-in, exit intent, breaking news, ad popup, promotions
* **Phase C (Intelligence):** Welcome back, A/B testing, surveys, PWA install, paywall, automated reports

**Estimated: 17–20 files across all phases.**

---

---

# 25. Planned — Multi-Purpose Crawler System (Phase 7)

A crawler engine with three modules, all managed from the admin portal.

## 25.1 Module 1: News Aggregator (Full Auto-Publish)

Automatically crawls configured source websites, extracts content, and publishes articles with zero manual involvement.

| Feature                       | Details                                                                                           |
| ----------------------------- | ------------------------------------------------------------------------------------------------- |
| **Sources**             | Admin adds RSS feeds or websites as crawl sources                                                 |
| **Schedule**            | Configurable from dashboard (default every 30 minutes)                                            |
| **Extraction**          | Content-only: strips all CSS/styling, keeps text, images, embeds (tweets, YouTube, Instagram)     |
| **Images**              | Hotlinked from source server (not downloaded).`loading="lazy"`,`referrerpolicy="no-referrer"` |
| **Category matching**   | 5-step: RSS tag → URL path → meta tag → keyword rules → source default                        |
| **Story threads**       | Auto-detects continuing stories via title/entity overlap, assigns to threads                      |
| **Publishing**          | Direct INSERT into `articles`table with `status = 'published'`— live immediately             |
| **Attribution**         | ⓘ tooltip (12px gray icon, visible only on hover). Full source data in database.                 |
| **Editing**             | Crawled articles fully editable — same form, same CKEditor, same everything as original articles |
| **Dashboard controls**  | Master ON/OFF, crawl interval, keywords to avoid/prioritize, category mapping, thread settings    |
| **Duplicate detection** | URL hash + title exact match + slug collision handling                                            |
| **Takedown**            | One-click archive single article or bulk-remove all articles from a source                        |

## 25.2 Module 2: SEO Crawler + Image Health Monitor

| Feature                | Details                                                                                             |
| ---------------------- | --------------------------------------------------------------------------------------------------- |
| **SEO Audit**    | Crawls own site for 26 checks: broken links, missing meta, thin content, missing alt text, etc.     |
| **Health Score** | 0–100 with critical/warning/passed breakdown and trend tracking                                    |
| **Image Health** | Daily scan of all hotlinked images via HEAD requests. Auto-replaces broken images with placeholder. |
| **Reports**      | Dashboard with drill-down, CSV/PDF export                                                           |
| **Schedule**     | Manual, weekly, or daily                                                                            |

## 25.3 Module 3: Social Media Monitor

| Feature              | Details                                                    |
| -------------------- | ---------------------------------------------------------- |
| **Platforms**  | Twitter/X, Facebook, Google News RSS, Reddit, web search   |
| **Tracking**   | Mentions of Northern Times and configured keywords         |
| **Sentiment**  | Positive / neutral / negative classification               |
| **Alerts**     | Email notifications for negative spikes or trending topics |
| **Competitor** | Monitor what other outlets are covering                    |

## 25.4 Execution

* **Phase A (Priority):** News Aggregator — 20 files
* **Phase B:** SEO Crawler + Image Health — 5 files
* **Phase C:** Social Monitor — 4 files

---

# 26. Planned — System Administration Panel (Phase 8)

A dedicated system management interface for the Super Admin to reset counters, purge data, manage database health, and perform factory resets. **Super Admin only** — no other roles can see or access this section.

## 26.1 Admin Sidebar

System Admin appears as a collapsible group in the sidebar, visible only to Super Admin:

```
▼ SYSTEM ⚠️  (Super Admin only)
   🔧  System Admin        → /admin/system
   🟢  Safe Resets          → /admin/system/safe
   🟡  Content Purges       → /admin/system/purge
   🟠  Media Management     → /admin/system/media
   🔴  Danger Zone          → /admin/system/danger
```

Each sub-page handles a specific danger level. The main System Admin page (`/admin/system`) shows the full dashboard with all sections, system health, and database tools. The sub-pages provide focused views for each reset category.

## 26.2 System Administration Dashboard (`/admin/system`)

```
┌─────────────────────────────────────────────────────────────────────┐
│  🔧 System Administration                    Super Admin Only       │
│                                                                     │
│  ⚠️ Actions on this page are irreversible. Use with extreme care.   │
│                                                                     │
│  ┌── System Health ──────────────────────────────────────────────┐  │
│  │  Database size: 247 MB    │  Redis: Connected ✅              │  │
│  │  Upload storage: 1.2 GB   │  PHP: 8.2.14 ✅                  │  │
│  │  Articles: 1,842          │  PostgreSQL: 15.4 ✅              │  │
│  │  Media files: 3,204       │  Disk free: 14.2 GB              │  │
│  │  Users: 8                 │  Uptime: 34 days                  │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ════════════════════════════════════════════════════════════════    │
│                                                                     │
│  ┌── 🟢 Safe Resets (Analytics & Counters) ──────────────────────┐  │
│  │                                                               │  │
│  │  These reset counters to zero. No content is deleted.         │  │
│  │                                                               │  │
│  │  Article Views                    12,847 total views          │  │
│  │  Reset all article view counts to zero.                       │  │
│  │  Articles keep their content — only view numbers reset.       │  │
│  │                                              [ Reset Views ]  │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Site Visitors                    48,203 total records        │  │
│  │  Clear all visitor tracking records.                          │  │
│  │  Visitor analytics dashboard will show zero.                  │  │
│  │                                           [ Reset Visitors ]  │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Ad Impressions & Clicks          5,120 impressions           │  │
│  │  Reset all ad slot counters to zero.                          │  │
│  │  Ads remain active — only stats reset.                        │  │
│  │                                          [ Reset Ad Stats ]   │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Popup Analytics                  72,103 impressions          │  │
│  │  Reset all popup impressions, clicks, conversions, closes.    │  │
│  │  Popups remain active — only analytics reset.                 │  │
│  │  ☐ Also reset all visitor dismissals (everyone sees           │  │
│  │    popups again as if new)                                    │  │
│  │                                        [ Reset Popup Stats ]  │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Newsletter Stats                 4 campaigns tracked         │  │
│  │  Reset send counts and open tracking on all campaigns.        │  │
│  │  Campaigns and subscriber list are preserved.                 │  │
│  │                                    [ Reset Newsletter Stats ] │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Crawler Stats                    1,247 articles tracked      │  │
│  │  Reset source fetch counts and crawl log.                     │  │
│  │  Sources stay configured. Published articles stay live.       │  │
│  │                                      [ Reset Crawler Stats ]  │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  🔄 Reset ALL Analytics                                      │  │
│  │  Resets everything above in one action.                       │  │
│  │                                    [ Reset All Analytics ]    │  │
│  │                                                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌── 🟡 Content Purges (Deletes Data) ──────────────────────────┐  │
│  │                                                               │  │
│  │  These DELETE content permanently. Cannot be undone.          │  │
│  │                                                               │  │
│  │  Comments                         2,341 comments              │  │
│  │  Permanently delete ALL comments across all articles.         │  │
│  │  Articles are preserved.                                      │  │
│  │                                        [ Purge Comments ]     │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Subscribers                      2,847 subscribers           │  │
│  │  Permanently delete ALL newsletter subscribers.               │  │
│  │  Newsletter campaigns are preserved.                          │  │
│  │                                      [ Purge Subscribers ]    │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Notifications                    156 notifications           │  │
│  │  Clear all editor notifications.                              │  │
│  │                                     [ Purge Notifications ]   │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Email Queue                      23 pending emails           │  │
│  │  Clear all pending and failed emails from queue.              │  │
│  │                                      [ Purge Email Queue ]    │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Crawled Articles (tracking only) 1,247 records               │  │
│  │  Delete crawl tracking records. Published articles stay       │  │
│  │  live — only the crawl history is removed.                    │  │
│  │                                     [ Purge Crawl History ]   │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  SEO Audit History                12 audit reports            │  │
│  │  Delete all past SEO audit results.                           │  │
│  │                                    [ Purge SEO History ]      │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Social Mentions                  342 mentions tracked        │  │
│  │  Delete all social media mention records.                     │  │
│  │                                    [ Purge Social Data ]      │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Login Attempts                   1,204 records               │  │
│  │  Clear login attempt tracking history.                        │  │
│  │                                  [ Purge Login History ]      │  │
│  │                                                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌── 🟠 Media Management ───────────────────────────────────────┐  │
│  │                                                               │  │
│  │  Media Library                    3,204 files (1.2 GB)        │  │
│  │  Delete ALL uploaded media files (images, documents).         │  │
│  │  Articles will show broken images. Featured images cleared.   │  │
│  │                                       [ Purge All Media ]     │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Orphan Media                     127 files (89 MB)           │  │
│  │  Delete media files not used by any article or setting.       │  │
│  │  Safe cleanup — only removes unused files.                    │  │
│  │                                     [ Clean Orphan Media ]    │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Thumbnail Regeneration                                       │  │
│  │  Regenerate all thumbnails and responsive image variants.     │  │
│  │  Useful after changing image size settings.                   │  │
│  │                                 [ Regenerate Thumbnails ]     │  │
│  │                                                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌── 🔴 DANGER ZONE ────────────────────────────────────────────┐  │
│  │                                                               │  │
│  │  ⚠️  These actions are CATASTROPHIC and IRREVERSIBLE.         │  │
│  │  Require typing "CONFIRM DELETE" to proceed.                  │  │
│  │                                                               │  │
│  │  Delete All Articles              1,842 articles              │  │
│  │  Permanently delete every article (originals + crawled).      │  │
│  │  Categories, users, settings preserved.                       │  │
│  │                                     [ Delete All Articles ]   │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Delete All Crawled Articles      1,247 crawled articles      │  │
│  │  Remove only auto-crawled articles. Original content stays.   │  │
│  │  Crawl sources preserved. Tracking records deleted.           │  │
│  │                                  [ Delete Crawled Only ]      │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Reset All Popups                 6 popups configured         │  │
│  │  Delete all popup configurations, events, dismissals.         │  │
│  │  Start popup system from scratch.                             │  │
│  │                                      [ Delete All Popups ]    │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Reset Crawler System                                         │  │
│  │  Delete all sources, crawled articles, crawl logs.            │  │
│  │  Published crawled articles stay (but source link removed).   │  │
│  │                                    [ Reset Crawler System ]   │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  ⚠️⚠️⚠️ FACTORY RESET ⚠️⚠️⚠️                                │  │
│  │                                                               │  │
│  │  Reset the ENTIRE system to fresh install state.              │  │
│  │  Deletes: All articles, categories, media, comments,         │  │
│  │  subscribers, newsletters, popups, crawler data, analytics,   │  │
│  │  notifications, email queue, SEO audits, social mentions.     │  │
│  │                                                               │  │
│  │  Preserves: User accounts, roles, site settings, theme,      │  │
│  │  SMTP configuration. (You keep your login and branding.)     │  │
│  │                                                               │  │
│  │  Requires: Type "FACTORY RESET NORTHERN TIMES" to confirm.   │  │
│  │                                                               │  │
│  │  [ __________________________________ ]                       │  │
│  │                                  [ 🔴 Execute Factory Reset ] │  │
│  │                                                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌── 📋 Reset Log ──────────────────────────────────────────────┐  │
│  │                                                               │  │
│  │  All reset actions are logged permanently (even after         │  │
│  │  factory reset). This log cannot be deleted.                  │  │
│  │                                                               │  │
│  │  Feb 28, 14:30  admin@nt.co.ug  Reset article views          │  │
│  │  Feb 25, 09:15  admin@nt.co.ug  Purged email queue (23)      │  │
│  │  Feb 20, 11:00  admin@nt.co.ug  Cleaned orphan media (89MB)  │  │
│  │  Feb 15, 16:45  admin@nt.co.ug  Reset all analytics          │  │
│  │                                                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌── 💾 Database Tools ─────────────────────────────────────────┐  │
│  │                                                               │  │
│  │  Export Database                                              │  │
│  │  Download full PostgreSQL dump as .sql file.                  │  │
│  │  Includes all tables, data, and indexes.                      │  │
│  │                                         [ Export .sql Dump ]  │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Table Sizes                                                  │  │
│  │  ┌─────────────────────┬──────────┬────────────┐             │  │
│  │  │ Table               │ Rows     │ Size       │             │  │
│  │  ├─────────────────────┼──────────┼────────────┤             │  │
│  │  │ articles            │ 1,842    │ 45.2 MB    │             │  │
│  │  │ media_library       │ 3,204    │ 2.1 MB     │             │  │
│  │  │ comments            │ 2,341    │ 1.8 MB     │             │  │
│  │  │ article_views       │ 12,847   │ 890 KB     │             │  │
│  │  │ site_visitors       │ 48,203   │ 12.4 MB    │             │  │
│  │  │ crawled_articles    │ 1,247    │ 8.3 MB     │             │  │
│  │  │ popup_events        │ 72,103   │ 15.1 MB    │             │  │
│  │  │ subscribers         │ 2,847    │ 420 KB     │             │  │
│  │  │ email_queue         │ 23       │ 12 KB      │             │  │
│  │  │ social_mentions     │ 342      │ 245 KB     │             │  │
│  │  │ ... (all tables)    │          │            │             │  │
│  │  ├─────────────────────┼──────────┼────────────┤             │  │
│  │  │ TOTAL               │          │ 247 MB     │             │  │
│  │  └─────────────────────┴──────────┴────────────┘             │  │
│  │                                                               │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Run Migrations                                               │  │
│  │  Execute any pending database migrations.                     │  │
│  │  Current: 35 migrations applied.                              │  │
│  │                                        [ Run Migrations ]     │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Vacuum Database                                              │  │
│  │  Reclaim disk space and optimize query performance.           │  │
│  │  Safe to run anytime. PostgreSQL VACUUM ANALYZE.              │  │
│  │                                       [ Vacuum Database ]     │  │
│  │  ─────────────────────────────────────────────────────────── │  │
│  │                                                               │  │
│  │  Clear Cache                                                  │  │
│  │  Flush Redis cache and all application caches.                │  │
│  │  Sessions will be cleared (all users logged out).             │  │
│  │                                          [ Clear Cache ]      │  │
│  │                                                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

## 26.3 Confirmation System

Every action requires confirmation. Danger level determines the confirmation type:

| Danger Level | Color              | Confirmation Required                                  | Example             |
| ------------ | ------------------ | ------------------------------------------------------ | ------------------- |
| 🟢 Safe      | Green button       | Single click + "Are you sure?" modal                   | Reset article views |
| 🟡 Content   | Yellow button      | Click + type table name (e.g., "comments")             | Purge comments      |
| 🟠 Media     | Orange button      | Click + type "DELETE MEDIA"                            | Purge all media     |
| 🔴 Danger    | Red button         | Click + type full phrase (e.g., "DELETE ALL ARTICLES") | Delete all articles |
| 🔴🔴 Factory | Red pulsing button | Type "FACTORY RESET NORTHERN TIMES" exactly            | Full system reset   |

**Confirmation modal example (Danger level):**

```
┌─────────────────────────────────────────────────────┐
│  ⚠️ Delete All Articles                              │
│                                                     │
│  This will permanently delete 1,842 articles.       │
│  This action CANNOT be undone.                      │
│                                                     │
│  Type "DELETE ALL ARTICLES" to confirm:             │
│                                                     │
│  [ ______________________________________ ]         │
│                                                     │
│           [ Cancel ]    [ Delete Forever ]           │
│                          (disabled until typed)      │
└─────────────────────────────────────────────────────┘
```

The confirm button stays **disabled** until the exact phrase is typed correctly. No accidental clicks.

## 26.4 Reset Log (Permanent & Undeletable)

Every reset action is logged to a `system_log` table that  **cannot be cleared** , even by factory reset. This provides a permanent audit trail.

```sql
CREATE TABLE system_log (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     UUID NOT NULL REFERENCES users(id),
    user_email  VARCHAR(255) NOT NULL,
    action      VARCHAR(100) NOT NULL,
    details     TEXT,
    ip_address  INET,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

Logged actions include: what was reset, how many records affected, who did it, when, from which IP.

## 26.5 Factory Reset — What Gets Deleted vs Preserved

| Deleted (reset to zero)              | Preserved (kept intact)             |
| ------------------------------------ | ----------------------------------- |
| All articles (original + crawled)    | User accounts and passwords         |
| All categories                       | Role definitions and permissions    |
| All media files + uploads folder     | Site settings (name, tagline, logo) |
| All comments                         | Theme and branding config           |
| All subscribers                      | SMTP/email configuration            |
| All newsletter campaigns + queue     | Crawler source configurations       |
| All popup configurations + events    | System log (permanent)              |
| All crawler data + logs              | Docker infrastructure               |
| All analytics (views, visitors, ads) | Database structure (tables stay)    |
| All notifications                    | `.env`configuration               |
| All SEO audits                       |                                     |
| All social mentions                  |                                     |
| All login attempt records            |                                     |
| All story threads                    |                                     |

After factory reset, the system is a blank newsroom with your admin account, branding, and email config intact — ready to start fresh.

## 26.6 Execution Plan

| # | File                                          | Purpose                                     |
| - | --------------------------------------------- | ------------------------------------------- |
| 1 | `database/migrations/0036_system_log.sql`   | Permanent system log table                  |
| 2 | `app/Models/SystemLog.php`                  | Log model (insert only — no delete method) |
| 3 | `app/Controllers/AdminSystemController.php` | All reset actions, confirmations, DB tools  |
| 4 | `app/Views/admin/system/index.php`          | System admin dashboard (full page)          |
| 5 | `routes/admin.php`                          | Add system routes (Super Admin middleware)  |
| 6 | `app/Views/admin/layout.php`                | Add System menu item (Super Admin only)     |

# 27. Planned — Quick Wins (Phase 9)

Features that are small in effort but high in impact. Most can be built in a single session.

## 27.1 Sitemap.xml Auto-Generation

Dynamically generates `/sitemap.xml` on request with all published articles, category pages, and policy pages.

| Detail               | Spec                                                                  |
| -------------------- | --------------------------------------------------------------------- |
| **URL**        | `/sitemap.xml`                                                      |
| **Format**     | XML Sitemap Protocol (sitemaps.org)                                   |
| **Includes**   | Homepage, all published articles, all active categories, policy pages |
| **Excludes**   | Drafts, archived, admin pages, search pages                           |
| **Priority**   | Homepage: 1.0, Articles: 0.8, Categories: 0.6, Policies: 0.4          |
| **changefreq** | Homepage: hourly, Articles: weekly, Categories: daily                 |
| **lastmod**    | Article `updated_at`timestamp, category `updated_at`              |
| **Max URLs**   | 50,000 per file (split into sitemap index if exceeded)                |
| **Cache**      | Regenerate every 30 minutes or on article publish (whichever first)   |
| **robots.txt** | Auto-add `Sitemap: https://northerntimes.co.ug/sitemap.xml`         |

**Google Search Console:** After deploying, submit sitemap URL to Google Search Console for immediate indexing.

**Files:** `routes/web.php` (add route), `app/Controllers/FrontendController.php` (add `sitemap()` method), `public/robots.txt`.

## 27.2 RSS Feed Output

Let other sites, apps, and aggregators consume Northern Times content. **Required for Google News inclusion.**

| Detail                   | Spec                                                                         |
| ------------------------ | ---------------------------------------------------------------------------- |
| **URL**            | `/feed`(main),`/feed/category/{slug}`(per category)                      |
| **Format**         | RSS 2.0 with Dublin Core and Media RSS extensions                            |
| **Items**          | Latest 50 published articles (configurable from admin settings)              |
| **Content**        | Title, excerpt, author, pubDate, category, featured image as `<enclosure>` |
| **Full text**      | Optional `<content:encoded>`with full article body (toggled in settings)   |
| **Auto-discovery** | `<link rel="alternate" type="application/rss+xml">`in HTML `<head>`      |
| **Cache**          | Regenerate every 15 minutes                                                  |

Readers can subscribe in any RSS reader (Feedly, Inoreader, etc.). Other news aggregators can pull your content — making Northern Times a content source, not just a consumer.

**Files:** `app/Controllers/FrontendController.php` (add `feed()` method), `app/Views/frontend/feed.xml.php` (RSS template), `app/Views/frontend/layout.php` (add auto-discovery link).

## 27.3 Reading Time

Display estimated reading time on every article: "4 min read".

| Detail                     | Spec                                                                 |
| -------------------------- | -------------------------------------------------------------------- |
| **Calculation**      | `ceil(word_count / 238)`minutes (238 wpm is average reading speed) |
| **Display**          | Next to publish date on article page and article cards               |
| **Format**           | "1 min read", "4 min read", "12 min read"                            |
| **Storage**          | Computed on save, stored in `articles.reading_time`(SMALLINT)      |
| **Crawled articles** | Auto-calculated from extracted body word count                       |

**Implementation:** Add `reading_time` column to articles, compute in Article model `save()` method, display in article page + card templates.

**Files:** `database/migrations/0036_article_reading_time.sql`, update `Article.php`, update article views.

## 27.4 Dark Mode Toggle

Reader-facing dark/light mode switch. Leverages the existing theme CSS variable system.

| Detail                   | Spec                                                                         |
| ------------------------ | ---------------------------------------------------------------------------- |
| **Toggle**         | Moon/sun icon in site header, next to search icon                            |
| **Storage**        | `localStorage`key `nt-theme`=`light`or `dark`                        |
| **Default**        | Light mode, or respect `prefers-color-scheme: dark`media query             |
| **Implementation** | Toggle adds class `dark`on `<html>`element                               |
| **Variables**      | Override CSS custom properties:`--paper: #1a1a1a`,`--ink: #e0e0e0`, etc. |
| **Scope**          | Frontend only (admin stays in its own theme)                                 |
| **Animation**      | Smooth 200ms transition on background and text colors                        |

**Dark mode palette:**

| Variable      | Light       | Dark        |
| ------------- | ----------- | ----------- |
| `--paper`   | `#ffffff` | `#1a1a1a` |
| `--ink`     | `#1a1a1a` | `#e0e0e0` |
| `--surface` | `#f5f5f5` | `#2a2a2a` |
| `--border`  | `#e0e0e0` | `#3a3a3a` |
| `--muted`   | `#666666` | `#999999` |
| `--accent`  | (unchanged) | (unchanged) |

**Files:** `public/assets/dark-mode.js`, `public/assets/dark-mode.css`, update `app/Views/frontend/layout.php`.

## 27.5 Article Scheduling

Write now, auto-publish at a future date/time. Essential for newsroom planning.

| Detail               | Spec                                                                                                                               |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| **How**        | Set `published_at`to a future datetime on the article form                                                                       |
| **Status**     | Article saved with `status = 'scheduled'`                                                                                        |
| **Display**    | Article form shows: "Scheduled for Mar 5, 2026 at 6:00 AM"                                                                         |
| **Execution**  | Cron job runs every minute, finds articles where `published_at <= NOW() AND status = 'scheduled'`, sets `status = 'published'` |
| **Admin list** | Scheduled articles show ⏰ badge with countdown                                                                                    |
| **Cancel**     | Change status back to Draft to cancel scheduled publish                                                                            |
| **Timezone**   | Uses server timezone (configurable in settings)                                                                                    |

**Cron entry:** `* * * * * php /var/www/html/schedule.php`

**Files:** `schedule.php` (cron entry), update `Article.php` model, update article form view, update articles list view.

## 27.6 Automated Database Backups

Daily automatic `pg_dump` with download from System Admin.

| Detail              | Spec                                                              |
| ------------------- | ----------------------------------------------------------------- |
| **Schedule**  | Daily at 2:00 AM via cron                                         |
| **Format**    | Compressed SQL dump (`.sql.gz`)                                 |
| **Retention** | Keep last 7 daily backups, auto-delete older                      |
| **Storage**   | `storage/backups/`directory (not publicly accessible)           |
| **Naming**    | `nt_backup_2026-02-28_020000.sql.gz`                            |
| **Size**      | ~50MB compressed for a 250MB database                             |
| **Download**  | System Admin → Database Tools → "Download Latest Backup" button |
| **Manual**    | "Backup Now" button for on-demand dumps                           |

**Cron entry:** `0 2 * * * php /var/www/html/backup.php`

**Files:** `backup.php` (cron entry), update `AdminSystemController.php` (download/manual trigger), update System Admin view.

---

# 28. Planned — Content Features (Phase 10)

Deeper content management capabilities for a mature newsroom.

## 28.1 Tag System

Tags work alongside categories for cross-cutting topics. An article in "Politics" can be tagged `#Elections2026`, `#Infrastructure`, `#NorthernUganda`.

| Detail                     | Spec                                                                             |
| -------------------------- | -------------------------------------------------------------------------------- |
| **URL**              | `/tag/{slug}`— shows all articles with this tag                               |
| **Input**            | Tag input field on article form — type and press Enter, shows as chips/pills    |
| **Auto-suggest**     | Dropdown suggests existing tags as you type                                      |
| **Storage**          | `tags`table (id, name, slug) +`article_tags`pivot table (article_id, tag_id) |
| **Display**          | Tags shown below article body as clickable pills                                 |
| **Trending**         | Dashboard widget: "Trending Tags" based on article count this week               |
| **Crawled articles** | Auto-tag from extracted keywords (top 3-5 significant terms)                     |
| **Limit**            | Max 10 tags per article                                                          |
| **SEO**              | Tag pages have meta description: "Articles tagged {tag} on The Northern Times"   |

**Frontend display on article page:**

```
Tags:  [Elections 2026]  [Infrastructure]  [Northern Uganda]  [World Bank]
```

**Files:** `database/migrations/0037_tags.sql`, `app/Models/Tag.php`, `app/Controllers/FrontendController.php` (tag page), `app/Views/frontend/tag.php`, update article form + article page views.

## 28.2 Author Pages

Dedicated page for each author/writer showing their profile and all their articles.

| Detail                       | Spec                                                                                                                        |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **URL**                | `/author/{username}`                                                                                                      |
| **Content**            | Avatar, name, role, bio, social links (reuses existing profile data), all published articles by this author with pagination |
| **Layout**             | Profile header card (like article author card but larger) + article grid below                                              |
| **SEO**                | JSON-LD Person schema, meta description from bio                                                                            |
| **Crawled articles**   | Author = configured display author (e.g., "Northern Times Desk") — all crawled articles appear under that author page      |
| **Link from articles** | Author name on article page links to author page                                                                            |

**Files:** `app/Controllers/FrontendController.php` (add `author()` method), `app/Views/frontend/author.php`, update article page (link author name).

## 28.3 Revision History

Track every edit to an article. View diffs. Restore previous versions.

| Detail                     | Spec                                                                                                                                              |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Storage**          | `article_revisions`table: id, article_id, user_id, title, content, excerpt, revision_number, created_at                                         |
| **When**             | New revision created on every Save (not on autosave drafts)                                                                                       |
| **View**             | Article edit form → "Revision History" tab → list of all revisions with date, editor name, word count change                                    |
| **Diff**             | Click a revision to see side-by-side diff (old vs new) with highlighted additions/deletions                                                       |
| **Restore**          | "Restore this version" button creates a new revision with the old content (non-destructive)                                                       |
| **Limit**            | Keep last 50 revisions per article. Auto-prune older.                                                                                             |
| **Crawled articles** | First revision = original crawled content. Subsequent revisions = editor changes. Always possible to see "what was the original crawled version." |

**Files:** `database/migrations/0038_article_revisions.sql`, `app/Models/ArticleRevision.php`, update article form view (add revisions tab), update `AdminController.php`.

## 28.4 Breaking News Ticker

Scrolling horizontal bar on the homepage for urgent/breaking stories.

| Detail                   | Spec                                                                            |
| ------------------------ | ------------------------------------------------------------------------------- |
| **Position**       | Above the hero section on homepage, below the site header                       |
| **Style**          | Red background, white text, "BREAKING" label, scrolling left-to-right           |
| **Content**        | Admin sets ticker text + optional link to article                               |
| **Activation**     | Toggle ON/OFF from admin dashboard quick action or Settings                     |
| **Auto-expire**    | Set duration (1hr, 2hr, 6hr, 12hr, 24hr) — auto-deactivates                    |
| **Multiple items** | Support 1-5 ticker items, scroll sequentially                                   |
| **Animation**      | CSS `marquee`alternative using `translateX`animation, smooth and performant |
| **Mobile**         | Single line, smaller font, same scrolling behavior                              |

**Admin control:**

```
┌─ Breaking News Ticker ────────────────────────────────────┐
│  Status: [● ON  ○ OFF]     Auto-expire: [ 6 hours ▼ ]    │
│                                                           │
│  Item 1: [ BREAKING: Parliament approves infrastructure ] │
│  Link:   [ /article/parliament-infrastructure-bill    ]   │
│                                                           │
│  Item 2: [ Flash floods reported in Gulu district      ]  │
│  Link:   [ /article/gulu-flash-floods                 ]   │
│                                                           │
│  [+ Add item]                    [Save & Activate]        │
└───────────────────────────────────────────────────────────┘
```

**Note:** This is separate from the Popup System breaking news type. The ticker is always-visible on homepage, not a dismissible popup.

**Files:** `database/migrations/0039_breaking_ticker.sql` (or use `site_settings`), update `FrontendController.php`, `app/Views/frontend/home.php`, `public/assets/ticker.css`.

---

# 29. Planned — Distribution & Reach (Phase 11)

Extend Northern Times beyond the website — push content to readers wherever they are.

## 29.1 Auto-Post to Social Media

When an article is published, automatically share it to configured social platforms.

### Supported Platforms

| Platform                   | Method                        | What Gets Posted                           |
| -------------------------- | ----------------------------- | ------------------------------------------ |
| **Facebook Page**    | Graph API (`/page-id/feed`) | Title + excerpt + link + featured image    |
| **Twitter/X**        | API v2 (`POST /tweets`)     | Title (truncated to 250 chars) + link      |
| **WhatsApp Channel** | WhatsApp Business API         | Title + excerpt + link (formatted message) |
| **Telegram Channel** | Bot API (`sendMessage`)     | Title + excerpt + link                     |
| **LinkedIn Page**    | API (`/ugcPosts`)           | Title + excerpt + link + image             |

### How It Works

```
Article published (manual or crawled)
    ↓
Post-publish hook fires
    ↓
For each enabled platform:
    ├── Format message using platform-specific template
    ├── Attach featured image (if supported)
    ├── POST to platform API
    ├── Log result (success/failure + post URL)
    └── Store social post IDs in article record
    ↓
Admin can view social post links from article edit form
```

### Admin Configuration (`/admin/settings` → Social Posting)

```
┌─ Social Auto-Posting ─────────────────────────────────────────────┐
│                                                                   │
│  Master switch: [● ON  ○ OFF]                                     │
│                                                                   │
│  ┌─ Facebook ─────────────────────────────────────────────────┐  │
│  │  Status:    [● Enabled  ○ Disabled]                        │  │
│  │  Page ID:   [ 123456789012345          ]                   │  │
│  │  Token:     [ EAABx...                 ] [Refresh Token]   │  │
│  │  Template:  [ {title}\n\n{excerpt}\n\nRead more: {url} ]   │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌─ Twitter/X ────────────────────────────────────────────────┐  │
│  │  Status:    [● Enabled  ○ Disabled]                        │  │
│  │  API Key:   [ xai_...                  ]                   │  │
│  │  Secret:    [ ••••••••                 ]                   │  │
│  │  Template:  [ {title} — {url} ]                            │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌─ WhatsApp Channel ─────────────────────────────────────────┐  │
│  │  Status:    [○ Enabled  ● Disabled]                        │  │
│  │  Phone ID:  [                          ]                   │  │
│  │  Token:     [                          ]                   │  │
│  │  Template:  [ 📰 *{title}*\n\n{excerpt}\n\n🔗 {url} ]     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌─ Telegram ─────────────────────────────────────────────────┐  │
│  │  Status:    [○ Enabled  ● Disabled]                        │  │
│  │  Bot Token: [                          ]                   │  │
│  │  Channel:   [ @northerntimes           ]                   │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ── Post Rules ─────────────────────────────────────────────     │
│                                                                   │
│  Post original articles:     [☑]                                 │
│  Post crawled articles:      [☐] (avoid reposting others' work) │
│  Post scheduled articles:    [☑] (when they go live)             │
│  Skip categories:            [ Opinion, Editorial ▼ ]            │
│  Delay after publish:        [ 0 ] minutes (0 = immediate)      │
│                                                                   │
│              [Save Settings]   [Test Post →]                      │
└───────────────────────────────────────────────────────────────────┘
```

**"Test Post" button:** Sends a test message to each enabled platform using the most recent article. Verifies API keys work.

**Key setting:** "Post crawled articles" defaults to OFF — avoids automatically resharing other outlets' stories to your social channels, which looks bad and could trigger copyright complaints.

### Social Post Log (`/admin/settings` → Social Log)

```
┌─ Social Post Log ────────────────────────────────────────────────┐
│                                                                   │
│  Feb 28 14:30  Uganda WB Grant       ✅ FB  ✅ X  ⏸ WA  ⏸ TG  │
│  Feb 28 14:15  EAC Trade Summit      ✅ FB  ✅ X  ⏸ WA  ⏸ TG  │
│  Feb 28 13:00  Editor Weekend Column  ✅ FB  ❌ X (rate limit)   │
│  Feb 28 12:30  Football Results       ✅ FB  ✅ X  ⏸ WA  ⏸ TG  │
│                                                                   │
│  ✅ = Posted   ❌ = Failed (hover for error)   ⏸ = Disabled      │
└───────────────────────────────────────────────────────────────────┘
```

**Files:** `app/Services/SocialPoster.php`, `database/migrations/0040_social_posts_log.sql`, update `Article.php` (post-publish hook), update admin settings view.

## 29.2 Browser Push Notifications

Send push notifications to readers' devices even when they're not on the site.

### How It Works

```
Reader visits site → browser asks "Allow notifications?" → Reader clicks Allow
    ↓
Browser sends push subscription (endpoint + keys) to our server
    ↓
Stored in `push_subscriptions` table
    ↓
When article publishes (or admin triggers manually):
    ├── Format notification: title, body (excerpt), icon (site logo), image (featured)
    ├── Send to all subscribers via Web Push Protocol (VAPID)
    └── Reader sees notification on phone/desktop → clicks → opens article
```

### Notification Types

| Type                    | Trigger                                          | Content                                       |
| ----------------------- | ------------------------------------------------ | --------------------------------------------- |
| **New article**   | On publish (original articles only, not crawled) | Article title + excerpt + featured image      |
| **Breaking news** | Admin manual trigger                             | Custom title + message                        |
| **Weekly digest** | Scheduled (Saturday morning)                     | "This week's top 5 stories on Northern Times" |

### Admin Configuration

```
┌─ Push Notifications ──────────────────────────────────────────────┐
│                                                                   │
│  Status:    [● Enabled  ○ Disabled]                               │
│  Subscribers: 4,821 devices                                       │
│                                                                   │
│  Auto-push on article publish:  [☑]                               │
│  Only original articles:        [☑] (skip crawled)                │
│  Only these categories:         [ All ▼ ]                         │
│  Weekly digest:                 [☑] Saturday at 9:00 AM           │
│                                                                   │
│  ── Manual Push ──────────────────────────────────────────────    │
│  Title: [ BREAKING: Major development in Northern Uganda     ]    │
│  Body:  [ Parliament approves $500M infrastructure package   ]    │
│  Link:  [ /article/infrastructure-package               ]        │
│  [Send to 4,821 devices]                                          │
│                                                                   │
│  VAPID Public Key:  [ BPz8... ] (auto-generated on first setup)   │
│  VAPID Private Key: [ ••••••• ] (stored securely in .env)         │
└───────────────────────────────────────────────────────────────────┘
```

### Reader-Facing Prompt

Instead of the browser's ugly default prompt, show a custom soft prompt first:

```
┌──────────────────────────────────────────────────────┐
│  🔔 Stay informed!                                    │
│  Get breaking news from Northern Uganda               │
│  delivered to your device.                            │
│                                                       │
│  [No thanks]              [Enable Notifications]      │
└──────────────────────────────────────────────────────┘
```

Shows after 30 seconds or 50% scroll (configurable). Only shows once if dismissed.

**Technical:** Uses Web Push API with VAPID authentication. No third-party service needed — self-hosted. Works on Chrome, Firefox, Edge, Safari (iOS 16.4+).

**Files:** `app/Services/WebPush.php`, `database/migrations/0041_push_subscriptions.sql`, `public/service-worker.js`, `public/assets/push-prompt.js`, update settings view, `push.php` (cron for digest).

---

# 30. Reader Heatmap — World Map Dashboard Widget

An interactive world map on the admin dashboard showing glowing dots on cities where readers are located. Full world visible, zoom into any region for finer detail.

## 30.1 Visual Design

```
┌─ 🌍 Readers Worldwide ──────── Last 30 days ▼ ── 12,847 visitors ──┐
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │      ·          ·                                              │  │
│  │    · · ·    · ·              ·                                 │  │
│  │   · · · ·    · ·         ·· · ·                               │  │
│  │    · · ·      · ·       · · · · ·                             │  │
│  │     · ·        · ·     ·  · ●●  ·                             │  │
│  │      ·          · ·   ·   ●●●●●  ·                            │  │
│  │                  · · ·   ●●●●●●●                              │  │
│  │                   · ·     ●●●●●     ● = East Africa hot zone  │  │
│  │                    · ·     ●●●                                 │  │
│  │                     · ·     ·                                  │  │
│  │                      · · ·                                     │  │
│  │                                               [ 🔍+ ] [ 🔍- ] │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌─ Top Cities ──────────────────────────────────────────────────┐  │
│  │  🔴 Kampala    4,203  32.7%    🟠 Gulu       1,847  14.4%    │  │
│  │  🟠 Nairobi      621   4.8%    🟡 Lira         589   4.6%    │  │
│  │  🟡 London        234   1.8%    🟡 Dar es Salaam 187  1.5%   │  │
│  │  🟢 New York      156   1.2%    🟢 Kigali       142   1.1%   │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  [View Full Analytics →]                                             │
└──────────────────────────────────────────────────────────────────────┘
```

## 30.2 Zoom & Navigation

The map supports both free zoom/pan AND click-to-zoom for guided drill-down.

**Free zoom/pan (like Google Maps):**

| Action   | Desktop                 | Mobile                |
| -------- | ----------------------- | --------------------- |
| Zoom in  | Scroll wheel up         | Pinch out             |
| Zoom out | Scroll wheel down       | Pinch in              |
| Pan      | Click + drag            | Touch + drag          |
| Reset    | Double-click empty area | Double-tap empty area |

**Click-to-zoom (guided drill-down):**

```
Full world view (default)
    ↓ click Africa
Africa view (countries visible, dots sized by country)
    ↓ click East Africa region
East Africa view (Uganda, Kenya, Tanzania, Rwanda visible)
    ↓ click Uganda
Uganda view (cities clearly visible: Kampala, Gulu, Lira, Kitgum, Arua)
    ↓ click Kampala dot
Tooltip: "Kampala — 4,203 visitors (32.7%) — 2,891 unique"
```

**Zoom level behavior:**

| Zoom Level          | What's Visible                         | Dots Shown                                     |
| ------------------- | -------------------------------------- | ---------------------------------------------- |
| Level 1 (world)     | All continents, country outlines faint | Top 50 cities globally, dots sized by count    |
| Level 2 (continent) | Countries clearly labeled              | All cities in visible region with >10 visitors |
| Level 3 (region)    | Country borders, major geography       | All cities with >1 visitor                     |
| Level 4 (country)   | Full country detail                    | All cities including small towns               |

**Zoom controls:** `+` and `−` buttons in bottom-right corner of map. "Reset" button returns to default world view centered on Africa.

**D3.js zoom implementation:**

```javascript
const zoom = d3.zoom()
    .scaleExtent([1, 12])          // min zoom: world, max zoom: city level
    .on('zoom', (event) => {
        mapGroup.attr('transform', event.transform);
        // Scale dots inversely so they don't grow with zoom
        dots.attr('r', d => dotRadius(d.visitors) / event.transform.k);
    });

svg.call(zoom);

// Click-to-zoom on countries
countries.on('click', (event, d) => {
    const bounds = path.bounds(d);
    const dx = bounds[1][0] - bounds[0][0];
    const dy = bounds[1][1] - bounds[0][1];
    const x = (bounds[0][0] + bounds[1][0]) / 2;
    const y = (bounds[0][1] + bounds[1][1]) / 2;
    const scale = Math.min(8, 0.9 / Math.max(dx / width, dy / height));
    svg.transition().duration(750).call(
        zoom.transform,
        d3.zoomIdentity.translate(width/2, height/2).scale(scale).translate(-x, -y)
    );
});
```

## 30.3 Dot Visual Tiers

| Visitors  | Radius | Color               | Effect                 |
| --------- | ------ | ------------------- | ---------------------- |
| 1–50     | 3px    | `#4ade80`(green)  | Static                 |
| 51–200   | 5px    | `#facc15`(yellow) | Subtle glow            |
| 201–1000 | 8px    | `#f97316`(orange) | Medium glow            |
| 1000+     | 12px   | `#ef4444`(red)    | Pulsing glow animation |

Dots scale inversely with zoom — so a 12px dot at world level becomes 3px when zoomed to city level, keeping the map readable at every zoom.

## 30.4 Period Selector & Data

Dropdown: Today | 7 days | 30 days (default) | 90 days | All time

**API endpoint:** `GET /admin/api/reader-map?period=30d`

Returns aggregated city data (grouped from `site_visitors`):

```json
{
    "total_visitors": 12847,
    "cities": [
        {"city": "Kampala", "country": "Uganda", "lat": 0.3476, "lng": 32.5825, "visitors": 4203, "percentage": 32.7},
        {"city": "Gulu", "country": "Uganda", "lat": 2.7747, "lng": 32.2990, "visitors": 1847, "percentage": 14.4}
    ]
}
```

Period change triggers AJAX fetch → D3 transitions smoothly resize/recolor dots (500ms).

## 30.5 Database Changes

```sql
ALTER TABLE site_visitors
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(9,6),
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(9,6),
    ADD COLUMN IF NOT EXISTS region    VARCHAR(100);

CREATE INDEX idx_visitors_geo ON site_visitors(created_at DESC, city, country)
    WHERE latitude IS NOT NULL;
```

GeoIP service updated to store coordinates on every visit recording.

## 30.6 Files Required

| # | File                                                 | Purpose                                 |
| - | ---------------------------------------------------- | --------------------------------------- |
| 1 | `database/migrations/0042_visitor_coordinates.sql` | Add lat, lng, region columns            |
| 2 | `app/Services/GeoIP.php`                           | Update to store coordinates             |
| 3 | `app/Controllers/AdminController.php`              | Add `/admin/api/reader-map`endpoint   |
| 4 | `public/assets/admin/reader-map.js`                | D3.js map with zoom/pan, dots, tooltips |
| 5 | `public/assets/admin/world-110m.json`              | Natural Earth GeoJSON                   |
| 6 | `app/Views/admin/dashboard.php`                    | Add map widget card                     |

---

# 31. White-Label Architecture (Foundational)

The system is designed from the ground up as a white-label platform. Every reference to "Northern Times" is a configurable variable, not a hardcoded string. A buyer changes settings once — the entire system rebrands.

## 31.1 The Principle

**Zero hardcoded identity.** The name "The Northern Times", the logo, the tagline, the colors, the domain, the email addresses — everything that makes this "The Northern Times" lives in configuration, not in code.

```
❌ WRONG (hardcoded):
<h1>The Northern Times</h1>
<meta name="author" content="Northern Times">
$from = "news@northerntimes.co.ug";

✅ RIGHT (white-label):
<h1><?= get_site_setting('site_name') ?></h1>
<meta name="author" content="<?= get_site_setting('site_name') ?>">
$from = get_site_setting('mail_from_address');
```

## 31.2 What's Configurable (All From Admin Settings)

| Category           | Settings                                               | Where Used                                                                         |
| ------------------ | ------------------------------------------------------ | ---------------------------------------------------------------------------------- |
| **Identity** | Site name, tagline, description, copyright text        | Header, footer, meta tags, emails, RSS, sitemap, JSON-LD, error pages, admin login |
| **Branding** | Logo URL, favicon URL, default OG image, admin logo    | Frontend header, admin sidebar, email templates, social sharing                    |
| **Colors**   | Accent color, primary, secondary (full theme engine)   | Entire frontend + admin via CSS variables                                          |
| **Domain**   | Site URL (`APP_URL`)                                 | Canonical links, RSS, sitemap, social posts, email links, push notifications       |
| **Email**    | From name, from address, reply-to, SMTP config         | All outgoing emails: newsletters, notifications, queue                             |
| **Social**   | Facebook page URL, Twitter handle, Instagram, WhatsApp | Footer, article sharing, social auto-post                                          |
| **Legal**    | Publisher name, registration number, contact address   | Policy pages, JSON-LD publisher, email footer                                      |
| **Crawler**  | User-Agent string, attribution template                | Crawler HTTP requests, source attribution tooltip                                  |
| **PWA/Push** | App name, short name, theme color, icon                | Push notifications, manifest.json                                                  |

## 31.3 Hardcoded String Audit

Every file in the codebase must be scanned for hardcoded references. Here's the complete list of strings to eliminate:

| Hardcoded String                          | Replace With                                                     |
| ----------------------------------------- | ---------------------------------------------------------------- |
| `"The Northern Times"`                  | `get_site_setting('site_name')`                                |
| `"Northern Times"`                      | `get_site_setting('site_name')`                                |
| `"northerntimes.co.ug"`                 | `get_site_setting('site_url')`/`APP_URL`env                  |
| `"news@northerntimes.co.ug"`            | `get_site_setting('mail_from_address')`                        |
| `"Northern Times Desk"`(crawler author) | `get_site_setting('default_crawl_author')`                     |
| `"NorthernTimesCrawler/1.0"`            | `get_site_setting('site_name') . 'Crawler/1.0'`                |
| `"The Northern Times"`in error pages    | `get_site_setting('site_name')`                                |
| `"Northern Times"`in email templates    | `get_site_setting('site_name')`                                |
| `"NT"`abbreviations                     | `get_site_setting('site_abbreviation')`                        |
| Any logo file path                        | `get_site_setting('site_logo')`                                |
| Favicon path                              | `get_site_setting('site_favicon')`                             |
| RSS feed title                            | `get_site_setting('site_name') . ' RSS Feed'`                  |
| Sitemap references                        | Dynamic from `APP_URL`                                         |
| JSON-LD publisher name                    | `get_site_setting('site_name')`                                |
| `FACTORY RESET NORTHERN TIMES`          | `'FACTORY RESET ' . strtoupper(get_site_setting('site_name'))` |
| Push notification app name                | `get_site_setting('site_name')`                                |
| Social post templates                     | Use `{site_name}`placeholder                                   |

## 31.4 Settings Page — Brand Identity Section

```
┌─ Brand Identity ──────────────────────────────────────────────────┐
│                                                                   │
│  Site Name:        [ The Northern Times               ]           │
│  Abbreviation:     [ NT                               ]           │
│  Tagline:          [ Independent journalism from Northern Uganda ] │
│  Description:      [ A digital newsroom platform delivering      ]│
│                    [ credible journalism from Northern Uganda     ]│
│                                                                   │
│  ── Visual Identity ──────────────────────────────────────        │
│                                                                   │
│  Logo:             [ /uploads/branding/logo.png ] [Pick Image]    │
│  Admin Logo:       [ /uploads/branding/admin-logo.png ] [Pick]    │
│  Favicon:          [ /uploads/branding/favicon.ico ] [Pick]       │
│  Default OG Image: [ /uploads/branding/og-default.jpg ] [Pick]    │
│                                                                   │
│  ── Legal Identity ───────────────────────────────────────        │
│                                                                   │
│  Publisher Name:   [ Northern Times Media Ltd         ]           │
│  Registration #:   [ UG-2026-MEDIA-0042              ]           │
│  Contact Address:  [ P.O. Box 1234, Gulu, Uganda     ]           │
│  Contact Email:    [ info@northerntimes.co.ug         ]           │
│  Copyright Text:   [ © 2026 {site_name}. All rights reserved. ]  │
│                                                                   │
│  ── Domain ───────────────────────────────────────────────        │
│                                                                   │
│  Site URL:         [ https://northerntimes.co.ug ] (also in .env) │
│                                                                   │
│                              [Save Brand Settings]                 │
└───────────────────────────────────────────────────────────────────┘
```

## 31.5 White-Label Handoff Process

When selling the system to another company:

```
Step 1: Buyer installs the system (Docker + compose up)
    ↓
Step 2: Buyer logs in as Super Admin (/admin)
    ↓
Step 3: Settings → Brand Identity
    ├── Change site name: "The Northern Times" → "Lagos Daily"
    ├── Upload their logo, favicon, OG image
    ├── Set their tagline, description, legal details
    ├── Set their domain in .env: APP_URL=https://lagosdaily.ng
    └── Configure their SMTP, social accounts
    ↓
Step 4: Settings → Theme
    ├── Change accent color to their brand color
    ├── Adjust typography if desired
    └── Light/dark default
    ↓
Step 5: System is fully rebranded. Zero code changes.
    └── "Lagos Daily" everywhere — header, footer, emails,
        RSS, sitemap, error pages, push notifications,
        crawler user-agent, social posts, JSON-LD, admin panel
```

**Time to rebrand: 10 minutes.** No developer needed. No code editing. No redeployment.

## 31.6 Files Affected

This is NOT a separate phase — it's a refactoring pass across the existing codebase:

| Area            | Files to Audit                                                               | What Changes                                                |
| --------------- | ---------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Frontend views  | `layout.php`,`home.php`,`article.php`,`search.php`,`category.php`  | Replace any hardcoded name/logo with `get_site_setting()` |
| Admin views     | `layout.php`,`login.php`,`dashboard.php`, all admin views              | Replace sidebar title, login page branding                  |
| Error pages     | `403.php`,`404.php`,`419.php`,`500.php`,`503.php`                  | Replace newspaper name                                      |
| Email templates | Newsletter compose, notification emails, queue templates                     | Replace sender name, footer                                 |
| Services        | `Mailer.php`,`GeoIP.php`(user-agent), RSS controller, Sitemap controller | Use config for names/URLs                                   |
| SEO             | JSON-LD in article view, meta tags, canonical links, OG tags                 | All from settings                                           |
| Crawler         | `CrawlerFetcher.php`user-agent, attribution tooltip                        | Dynamic from settings                                       |
| Social          | `SocialPoster.php`templates                                                | Use `{site_name}`variable                                 |
| Push            | `service-worker.js`, notification title                                    | From settings                                               |
| Config          | `.env.example`                                                             | Document all white-label env vars                           |

**New settings added to `site_settings`:**

```
site_abbreviation, publisher_name, registration_number,
contact_address, contact_email, copyright_template,
admin_logo, default_crawl_author, crawler_user_agent_name
```

---

# 32. Media Upload Refinement Plan

A comprehensive audit and fix of every media upload touchpoint in the system, with special focus on inline image insertion within articles.

## 32.1 The Problem

The CKEditor image insertion within article body content is failing. Images can be uploaded to the media library and set as featured images, but inserting them inline within the article body (between paragraphs) doesn't work reliably.

## 32.2 Every Media Upload Touchpoint

| #  | Location                                 | Feature                             | Current Status                |
| -- | ---------------------------------------- | ----------------------------------- | ----------------------------- |
| 1  | Article form → Featured Image           | Media picker assigns featured image | ✅ Working                    |
| 2  | Article form → CKEditor → Insert Image | Inline image within article body    | ❌ Failing                    |
| 3  | Media Library page → Upload             | Direct upload to media library      | ✅ Working                    |
| 4  | Media Library page → Drag & Drop        | Bulk upload via drag                | ✅ Working                    |
| 5  | Admin Profile → Avatar                  | Profile picture upload              | ✅ Working (via media picker) |
| 6  | Settings → Logo                         | Site logo upload                    | ✅ Working (via media picker) |
| 7  | Settings → Favicon                      | Favicon upload                      | ✅ Working (via media picker) |
| 8  | Settings → OG Image                     | Default social image                | ✅ Working (via media picker) |
| 9  | Ad Slots → Creative                     | Ad image upload                     | ✅ Working (via media picker) |
| 10 | Newsletter → Compose                    | Images in newsletter body           | ⚠️ Untested                 |
| 11 | Policy Pages → Body                     | Images in policy content            | ⚠️ Untested                 |
| 12 | Crawler → Source Logo                   | Source icon upload                  | 🔜 Planned                    |

## 32.3 Fixing CKEditor Inline Image Insertion

### The Root Cause (Most Likely)

CKEditor 5 image insertion can fail for several reasons:

| Issue                                     | Symptom                                               | Fix                                                                                     |
| ----------------------------------------- | ----------------------------------------------------- | --------------------------------------------------------------------------------------- |
| **Missing upload adapter**          | CKEditor upload button does nothing or shows error    | Implement custom upload adapter that POSTs to `/admin/media/upload-inline`            |
| **CSRF token not passed**           | Upload request blocked by server with 419 error       | Include CSRF token in upload adapter headers                                            |
| **Wrong response format**           | Image uploads but doesn't appear in editor            | Return `{ "url": "/uploads/articles/image.webp" }`in exact CKEditor-expected format   |
| **Media picker → CKEditor bridge** | Can browse media library but can't insert into editor | Add "Insert into editor" button in media picker that calls CKEditor `insertContent()` |
| **Content Security Policy**         | Inline images blocked by CSP headers                  | Whitelist media domain in CSP `img-src`                                               |

### Solution: Two Ways to Insert Images

**Method 1 — Direct Upload (drag & drop into CKEditor)**

```
User drags image file into CKEditor text area
    ↓
CKEditor fires upload adapter
    ↓
Custom adapter POSTs file to /admin/media/upload-inline
    ├── Receives file
    ├── Runs through Image::saveResizedWebp() (same pipeline as media library)
    ├── Saves to media_library table
    ├── Returns JSON: { "url": "/uploads/articles/img_abc123.webp" }
    └── CSRF token included in request header
    ↓
CKEditor inserts <img src="/uploads/articles/img_abc123.webp"> at cursor position
    ↓
Image visible in editor immediately
```

**Method 2 — Media Picker Browse (select existing image)**

```
User clicks "Insert Image" button in CKEditor toolbar
    ↓
Custom button opens Media Picker modal (same modal used for featured image)
    ↓
User browses/searches existing media library
    ↓
User clicks an image → clicks "Insert into Article"
    ↓
Media picker calls callback: CKEditor.insertContent('<img src="...">')
    ↓
Image appears at cursor position in editor
```

### CKEditor Upload Adapter Code

```javascript
class NTUploadAdapter {
    constructor(loader) {
        this.loader = loader;
    }

    upload() {
        return this.loader.file.then(file => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder', 'Articles');
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            return fetch('/admin/media/upload-inline', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => ({ default: data.url }));
        });
    }

    abort() {}
}

// Register adapter with CKEditor
function CustomUploadPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
        return new NTUploadAdapter(loader);
    };
}
```

### Server-Side Endpoint

```
POST /admin/media/upload-inline
```

* Accepts multipart file upload
* Runs same image pipeline (resize, WebP, thumbnail, SHA256 dedup)
* Saves to `media_library` table with folder = "Articles"
* Returns: `{ "url": "/uploads/articles/img_abc123.webp", "width": 960, "height": 640 }`
* CSRF protected

### Media Picker "Insert into Article" Button

Currently the media picker has "Select" which sets the featured image. For CKEditor, add a second mode:

```
┌─ Media Library ──────────────────────────────────────────┐
│                                                          │
│  [Upload New]  [Search...]  [Folder: All ▼]             │
│                                                          │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐          │
│  │ IMG  │ │ IMG  │ │ IMG  │ │ IMG  │ │ IMG  │          │
│  │      │ │      │ │      │ │      │ │      │          │
│  └──────┘ └──────┘ └──────┘ └──────┘ └──────┘          │
│                                                          │
│  Selected: bridge-construction.webp (960×640, 124KB)     │
│                                                          │
│  Alt text: [ Bridge construction in Gulu district    ]   │
│  Size:     [● Full width  ○ Half width  ○ Original ]    │
│  Align:    [○ Left  ● Center  ○ Right ]                 │
│                                                          │
│         [Cancel]   [Insert into Article]                 │
└──────────────────────────────────────────────────────────┘
```

The "Insert into Article" button generates the `<img>` tag with selected size/alignment and inserts it at the CKEditor cursor position.

### Inserted Image HTML

```html
<!-- Full width (default) -->
<figure class="article-image article-image-full">
    <img src="/uploads/articles/bridge.webp"
         alt="Bridge construction in Gulu district"
         loading="lazy">
    <figcaption>Bridge construction in Gulu district</figcaption>
</figure>

<!-- Half width, float left -->
<figure class="article-image article-image-half article-image-left">
    <img src="/uploads/articles/bridge.webp"
         alt="Bridge construction"
         loading="lazy">
</figure>
```

### Frontend CSS for Inline Images

```css
.article-image { margin: 1.5rem 0; }
.article-image img { width: 100%; height: auto; border-radius: 4px; }
.article-image-full { max-width: 100%; }
.article-image-half { max-width: 50%; }
.article-image-left { float: left; margin-right: 1.5rem; }
.article-image-right { float: right; margin-left: 1.5rem; }
.article-image figcaption {
    font-size: 0.85rem;
    color: var(--muted);
    margin-top: 0.5rem;
    text-align: center;
}
```

## 32.4 Full Media Refinement Checklist

| #  | Fix                                                                 | Priority        |
| -- | ------------------------------------------------------------------- | --------------- |
| 1  | CKEditor upload adapter (drag & drop images into article body)      | 🔴 Critical     |
| 2  | Media picker "Insert into Article" mode with size/alignment options | 🔴 Critical     |
| 3  | `POST /admin/media/upload-inline`endpoint                         | 🔴 Critical     |
| 4  | Alt text prompt on every image insertion (accessibility)            | 🟡 High         |
| 5  | Caption support via `<figure>`+`<figcaption>`                   | 🟡 High         |
| 6  | Image size options (full width, half, original)                     | 🟡 High         |
| 7  | Image alignment (left, center, right)                               | 🟡 High         |
| 8  | Test newsletter compose inline images                               | 🟡 High         |
| 9  | Test policy page inline images                                      | 🟡 High         |
| 10 | Image paste from clipboard (screenshot paste)                       | 🟢 Nice-to-have |
| 11 | Image reordering via drag in editor                                 | 🟢 Nice-to-have |
| 12 | Responsive `srcset`on inline images (not just featured)           | 🟢 Nice-to-have |

## 32.5 Files Affected

| # | File                                         | Change                                                      |
| - | -------------------------------------------- | ----------------------------------------------------------- |
| 1 | `app/Controllers/AdminMediaController.php` | Add `uploadInline()`endpoint                              |
| 2 | `routes/admin.php`                         | Add `/admin/media/upload-inline`route                     |
| 3 | `app/Views/admin/article_form.php`         | Register CKEditor upload adapter + media picker insert mode |
| 4 | `app/Views/admin/media/picker.php`         | Add "Insert into Article" mode with size/alignment          |
| 5 | `public/assets/ckeditor-adapter.js`        | Custom upload adapter                                       |
| 6 | `public/assets/article.css`                | Inline image styles (figure, figcaption, sizes, alignment)  |
| 7 | `app/Views/admin/newsletter/compose.php`   | Test + fix inline images                                    |
| 8 | `app/Views/admin/policies/form.php`        | Test + fix inline images                                    |

---

# 33. Planned Features — Execution Roadmap

Complete phase list with all planned features:

| Phase | Name                  | Features                                                                                         | Est. Files        | Priority                 |
| ----- | --------------------- | ------------------------------------------------------------------------------------------------ | ----------------- | ------------------------ |
| 0     | White-Label Refactor  | Eliminate all hardcoded "Northern Times" strings, make everything configurable from settings     | ~30 files touched | Foundational — do first |
| 1–5  | Core CMS              | ✅ Complete (articles, categories, media, auth, RBAC, editorial, newsletter, comments, ads, SEO) | Done              | Done                     |
| 6     | Popup & Banner System | 10 popup types, 6 styles, live preview, analytics, frontend engine                               | 17–20            | High                     |
| 7     | Multi-Purpose Crawler | News aggregator (auto-publish), SEO crawler, social monitor                                      | 29                | High                     |
| 8     | System Administration | Reset tools (5 levels), DB tools, backup download, audit log                                     | 6                 | Medium                   |
| 9     | Quick Wins            | Sitemap.xml, RSS feed, reading time, dark mode, article scheduling, automated backups            | 12                | High                     |
| 9.5   | Media Refinement      | CKEditor inline images, upload adapter, media picker insert mode, captions, sizing               | 8                 | Critical                 |
| 10    | Content Features      | Tag system, author pages, revision history, breaking news ticker                                 | 10                | Medium                   |
| 11    | Distribution & Reach  | Social auto-post (FB/X/WA/TG/LI), browser push notifications                                     | 10                | High                     |
| 12    | Reader Heatmap        | D3.js world map, city-level dots, zoom/pan, period selector                                      | 6                 | Medium                   |

**Recommended build order:**

1. **Phase 0** — White-label refactor (foundational, do before everything else)
2. **Phase 9.5** — Media refinement (critical bug fix for inline images)
3. **Phase 9** — Quick wins (sitemap, RSS, reading time — biggest SEO impact)
4. **Phase 6** — Popup system
5. **Phase 7** — Crawler system
6. **Phase 11** — Distribution (social auto-post, push notifications)
7. **Phase 10** — Content features (tags, author pages, revisions, ticker)
8. **Phase 12** — Reader heatmap
9. **Phase 8** — System admin (lower urgency, admin convenience)

**Total planned: ~130 files across Phases 0–12.**

# 34. Scalability Design

The platform is designed to:

* Support high article volume with efficient PostgreSQL queries and indexing
* Scale multi-admin operations with RBAC role system
* Support additional content types (video, podcast, galleries — future)
* Scale media storage with folder organization and responsive variants
* Integrate third-party services (analytics, payment, push notifications)
* Handle traffic spikes with Nginx caching and Redis sessions

Its modular, phase-based architecture allows expansion without full system rewrites.

---

# 35. File Structure

```
northern-times/
├── app/
│   ├── Controllers/           # 16 controllers (admin + frontend)
│   │   ├── AdminController.php
│   │   ├── AdminAdController.php
│   │   ├── AdminCategoryController.php
│   │   ├── AdminCommentController.php
│   │   ├── AdminMediaController.php
│   │   ├── AdminNewsletterController.php
│   │   ├── AdminPolicyController.php
│   │   ├── AdminProfileController.php
│   │   ├── AdminReviewController.php
│   │   ├── AdminRoleController.php
│   │   ├── AdminSettingsController.php
│   │   ├── AdminSubscriberController.php
│   │   ├── AdminUserController.php
│   │   ├── Controller.php
│   │   ├── FrontendController.php
│   │   └── FrontendPolicyController.php
│   ├── Models/                # 16 models
│   │   ├── AdSlot.php
│   │   ├── Article.php
│   │   ├── ArticleView.php
│   │   ├── BaseModel.php
│   │   ├── Category.php
│   │   ├── Comment.php
│   │   ├── MediaItem.php
│   │   ├── NewsletterIssue.php
│   │   ├── Notification.php
│   │   ├── PolicyPage.php
│   │   ├── Role.php
│   │   ├── Setting.php
│   │   ├── SiteVisitor.php
│   │   ├── StoryThread.php
│   │   ├── Subscriber.php
│   │   └── User.php
│   ├── Services/              # 12 services
│   │   ├── Auth.php
│   │   ├── Csrf.php
│   │   ├── DB.php
│   │   ├── Flash.php
│   │   ├── GeoIP.php
│   │   ├── Image.php
│   │   ├── Mailer.php
│   │   ├── Media.php
│   │   ├── RBAC.php
│   │   ├── RateLimiter.php
│   │   ├── Sanitizer.php
│   │   └── Slug.php
│   ├── Support/
│   │   └── helpers.php        # Global helper functions
│   └── Views/
│       ├── admin/             # 20+ admin views
│       ├── errors/            # 5 error pages (403, 404, 419, 500, 503)
│       └── frontend/          # 6 frontend views
├── database/
│   └── migrations/            # 31 migration files
├── docker/
│   ├── nginx/                 # default.conf + production-ssl.conf
│   └── php/                   # Dockerfile + php.ini
├── public/
│   ├── assets/                # CSS, JS, images
│   └── index.php              # Application entry point
├── routes/
│   ├── admin.php              # Admin routes with middleware
│   └── web.php                # Public routes
├── storage/
│   └── uploads/               # Media uploads (Docker volume)
├── tests/                     # 88 PHPUnit tests
│   ├── Unit/
│   ├── Integration/
│   ├── Feature/
│   └── bootstrap.php
├── docker-compose.yml
├── composer.json
└── README.md
```

---

# 36. Strategic Positioning

The Northern Times platform is:

* A digital newsroom infrastructure for Northern Uganda
* A regional information hub with credible journalism
* A scalable publishing engine with modern architecture
* A structured editorial system with review workflows
* A long-term digital institution built for growth

It balances old-school newsroom standards with modern digital architecture.

---

# 37. Requirements

* Docker + Docker Compose
* PHP 8.2 + Nginx + PostgreSQL 15 (provided via Docker)
* Redis 7 (provided via Docker)

---

# 38. Setup

1. **Clone and build:**
   ```bash
   git clone <repository-url>
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

All 88 tests should pass.

---

*End of documentation. Last updated: March 1, 2026 (v4.0 — Complete platform blueprint).*

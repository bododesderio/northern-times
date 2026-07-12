# Northern Times — Email Templates Build Prompt

> Recorded 2026-07-12 for a future session. Brevo is NOT configured yet — this
> is templates only. Note: this scope OVERLAPS/EXTENDS the double opt-in email
> work already shipped (templates/newsletter/*). When executing, reconcile with
> the existing `email_base.html`, `mailer.py` site_context(), and the Subscriber
> model rather than duplicating. The existing send path uses Django templates +
> an EmailQueue; keep that, just build these richer templates + add the missing
> SiteSettings fields.

## Context

**Brevo has NOT yet been configured for this project.** Do not implement
any Brevo API calls, SDK integration, webhook handling, or sending logic.
That is a separate step done after templates are complete.

This prompt is exclusively about building the HTML template files that
Brevo will eventually send. Every template must be:

- **Branded** — Northern Times' visual identity throughout
- **Well structured** — proper email HTML that renders correctly in
  Gmail, Outlook, and Apple Mail
- **CMS-driven** — content that the editorial team would reasonably
  want to change (logo, colors, contact details, social links, footer
  text, publication address) must be pulled from the site settings
  model, not hardcoded in the template

**Scope:** These templates cover **reader engagement only** — subscription
flow, newsletter delivery, content alerts, and reader account management.
There are no HR, service-application, or organizational-program templates
in this build. Do not implement anything outside the template list in
Step 4.

---

## Step 1 — Read the Codebase Before Writing Anything

Before creating a single template file, search the codebase and answer
every question below. Record your findings. Do not guess.

```bash
find . -type d -name "emails" 2>/dev/null
find . -path "*/templates/emails*" -type f 2>/dev/null
find . -name "base*.html" -path "*email*" 2>/dev/null
find . -name "base_email*" 2>/dev/null
grep -r "SiteSettings\|site_settings\|SiteSetting\|PublicationSettings" --include="*.py" -rn . 2>/dev/null | head -30
grep -rA 40 "class SiteSettings\|class PublicationSettings" --include="*.py" . 2>/dev/null | head -60
grep -r "render_to_string\|get_template\|render_template\|htmlContent\|html_content" --include="*.py" --include="*.ts" --include="*.js" -rn . 2>/dev/null
grep -r "BRAND_PRIMARY\|BRAND_ACCENT\|brand_color\|primary_color\|PRIMARY_COLOR" --include="*.py" --include="*.ts" --include="*.js" --include="*.env*" -rn . 2>/dev/null
grep -r "logo\|LOGO" --include="*.py" --include="*.ts" --include="*.env*" -rn . 2>/dev/null | grep -i "url\|path\|src" | head -20
grep -r "Subscriber\|Newsletter\|NewsletterSubscriber\|subscription" --include="*.py" -rn . 2>/dev/null | grep "class\|models" | head -20
```

**Answer these before writing code:**

```
Template engine in use:       [Django / Jinja2 / React Email / Handlebars / plain HTML]
Template directory:           [exact path where new templates should be created]
Variable syntax:              [{{ variable }} / {variable} / <%=variable%>]
Base template exists:         [Yes — path: X / No — create it]
Global context fields:        [list what's auto-injected into every template]
Brand primary color:          [hex value found in codebase or env]
Brand accent color:           [hex value found in codebase or env]
Logo URL source:              [SiteSettings model / env var / static file / unknown]
SiteSettings fields found:    [list all relevant fields]
Subscriber model fields:      [list relevant fields on the subscriber model]
```

Do not create any file until these are answered.

> KNOWN ANSWERS (as of 2026-07-12, verify on resume): Django templates,
> `{{ }}` syntax, dir `backend/templates/newsletter/`, base exists
> `newsletter/email_base.html`, rendered via `render_to_string` +
> `apps/newsletter/services/mailer.py` `site_context()`. Brand accent
> `#D32F2F`/`theme_accent` Setting. Logo/name/tagline via `apps/core/models.Setting`
> (KV store) surfaced by `site_settings` context processor. Subscriber model:
> `apps/newsletter/models.Subscriber` (email, name, status pending/active/unsubscribed,
> unsub_token, confirm_token, confirmed_at, unsubscribed_at). There is NO
> `SiteSettings` class — settings are the `Setting` key/value model; add the new
> email fields as Setting keys (logo_url, brand_primary_color, address,
> editorial_email, footer_tagline, social_*) and expose them in site_context().

---

## Step 2 — Understand What CMS-Driven Means for Email

Two kinds of content:

**Hardcoded (editorial identity, never changes):** HTML/table structure, fonts,
spacing, article-card columns, badge shapes, dividers.

**Pulled from settings (CMS-driven):** logo URL, brand primary color, brand accent
color, publication name, physical address, office phone, editorial email, website
URL, social links (Facebook/WhatsApp/X/Instagram/YouTube/TikTok), footer tagline.
Current year is auto, never from settings.

**Template-specific dynamic content:** passed at send time from the triggering
event (listed per template in Step 4). Never from settings.

If any settings field is missing, add it (here: add Setting keys) before building.

---

## Step 3 — Base Email Template

Build first; every other template extends it.

### Design Rules for Email HTML
```
Layout:  HTML tables only — no CSS Grid/Flexbox
Fonts:   Georgia/'Times New Roman'/Times/serif for headlines;
         'Segoe UI'/Tahoma/Geneva/Verdana/Arial/sans-serif for body/UI.
         No Google Fonts.
CSS:     Inline only — no <style>/<link>. Gmail strips them.
Images:  alt="" on all; absolute https:// src; no CSS background-image (use bgcolor).
Links:   absolute URLs.
Max width: 600px container, centered.
Background: #F3F4F6 outer wrapper; #FFFFFF content card.
JavaScript: none.
```

### Base Structure
- OUTER WRAPPER bg #F3F4F6, 100% width.
- CENTERED CONTAINER max-width 600px.
- HEADER BAND: bg `{{ settings.brand_primary_color }}`, padding 28px 48px 24px,
  centered. Edition Label (BLOCK: edition_label) 11px uppercase ls 2px rgba white .65,
  mb 14px. Logo from `{{ settings.logo_url }}` h36px centered mb 14px. BLOCK: header_content.
- BODY CARD: bg #FFFFFF padding 40px 48px. BLOCK: body.
- FOOTER BAND: bg #111827 padding 28px 40px centered. publication_name 14px white 600.
  footer_tagline 12px rgba white .5 italic. Social icon row (only non-empty platforms,
  inline table, 28px icons, 10px spacing) margin 16px 0. address + editorial_email 11px
  rgba white .4. BLOCK: footer_links (unsubscribe · privacy · view in browser) 11px.
  © `{{ current_year }} {{ settings.publication_name }}` 11px rgba white .3.

### Reusable Components (build as partials/includes)
- **Alert Circle** — 96×96 circle, radius 50%, bg rgba white .15, border 2px rgba white .5;
  contains ✉ (subscribe), ✓ (confirm), 🔔 (breaking), 🔑 (security). White.
- **CTA Button** — pill (`border-radius:9999px`), primary bg brand_primary color #fff
  700 15px pad 15px 36px; outlined variant transparent + 2px brand border, brand text.
  Outer td text-align center pad 28px 0. Include `mso-padding-alt:0`.
- **Section Divider** — full-width `border-top:1px solid #E5E7EB`; optional 10px diamond
  (square rotated 45°) in brand color centered above.
- **Article Card** — FEATURED: 600px image max-h 240px flush; category badge pill (bg
  brand_accent #fff 10px uppercase ls1.5 radius 9999 pad 4px10px); headline Georgia 22px
  bold #111827 lh1.3; excerpt 14px #6B7280 lh1.7; byline 12px #9CA3AF; "Read Full Story →"
  brand 700 14px. COMPACT: 2-col, left 96×72 thumb radius4, right badge+headline(15px bold
  serif)+2-line excerpt(12px grey)+"Read →". 16px between cards.
- **Breaking Alert Banner** — full-width top of body: bg brand (or #DC2626 via
  `{{ alert_color }}`), radius4, pad 10px16px, mb24px; "⚡ BREAKING" white bold uppercase
  11px ls2. Solid color, no gradient.
- **Stats Row** — 3–4 equal centered cols; value 28px 800 brand serif; label 12px #9CA3AF
  uppercase ls.5; 1px #E5E7EB vertical separator td.
- **Summary Box** — bg #F9FAFB border 1px #E5E7EB radius8 pad 20px24px; 2-col rows,
  label left bold 13px #374151, value right 13px #6B7280, 8px between rows. No shadow.
- **Circular Bullet List** — each row: 16×16 circle bg brand radius50% white 10px center;
  10px spacer; text 14px #374151 lh1.6; 10px between items.
- **Social Icon Row** — centered table of 28px circular PNG icons (rgba white .1 bg),
  10px spacing, order FB·WhatsApp·X·Instagram·YouTube·TikTok, only non-empty. Hosted PNGs,
  no icon fonts, no inline SVG.

---

## Step 4 — All Email Templates (exhaustive; nothing outside this list)

### GROUP 1 — SUBSCRIBER FLOW
- **T-01 t01_subscribe_confirm.html** — double opt-in. Header: Alert Circle ✉, "One step
  left.", subline confirm. Body: intro para; CTA "Confirm My Subscription →" →
  `{{ confirmation_url }}`; "expires in 48 hours"; divider; "What you'll receive" 3-item
  bullet list; divider; reassurance line. Footer: Privacy · site_url. Vars: confirmation_url,
  subscriber_name?, subscriber_email.
- **T-02 t02_subscribe_welcome.html** — label WELCOME TO NORTHERN TIMES. Header ✓, "You're in.".
  Body: greeting; para; Stats Row [subscriber_count][editions_per_week][founded_year];
  divider; "what to expect" bullets; CTA "Read Today's Latest →" → site_url. Footer:
  Unsubscribe · Privacy. Vars: subscriber_name?, unsubscribe_url, subscriber_count,
  editions_per_week, founded_year.
- **T-03 t03_unsubscribe.html** — no circle; header bg #4B5563 muted; "You've been
  unsubscribed.". Body: removed {{subscriber_email}}; quiet re-subscribe text link →
  {{resubscribe_url}}; still-follow social as text links. Footer: Privacy · site_url.
  Vars: subscriber_email, resubscribe_url.

### GROUP 2 — NEWSLETTER & CONTENT
- **T-04 t04_digest.html** — label `{{ edition_type|upper }}`. Header: logo, edition_date ·
  edition_number. Body: LEAD STORY featured Article Card + CTA; secondary_stories (0–4)
  compact cards; SHORT TAKES (0–6) text rows; EDITOR'S NOTE box (if editors_note); outlined
  CTA "Visit Northern Times →". Footer: Unsubscribe · View in Browser · Privacy. Vars:
  edition_type, edition_date, edition_number, lead_story{}, secondary_stories[],
  short_takes[], editors_note?, editor_name?, unsubscribe_url, view_in_browser_url.
- **T-05 t05_breaking.html** — label BREAKING NEWS. Header no circle, logo on brand (or red
  if alert_is_critical), alert_headline 22px 800 serif, alert_time·alert_date. Body:
  Breaking Alert Banner "⚡ BREAKING — {{alert_category}}"; optional image; alert_summary
  16px; full-width CTA "Read the Full Story →" → story_url; RELATED COVERAGE (0–2); credit
  line + "Developing story". Footer: Unsubscribe · View in Browser · Privacy. Vars:
  alert_headline, alert_summary, alert_category, alert_time, alert_date, alert_image_url?,
  alert_image_alt, alert_is_critical, story_url, byline?, related_items[].
- **T-06 t06_trending.html** — label TRENDING THIS WEEK. Header "This Week's Most-Read
  Stories", "Week of {{week_label}}". Body: optional intro_note; ranked list (3–8): rank
  number (22px 800 brand serif) + badge + headline link + excerpt + "Read →"; 1px #F3F4F6
  between; CTA "See All Stories This Week →" → site_url. Footer: Unsubscribe · View in
  Browser · Privacy. Vars: week_label, intro_note?, trending_stories[].

### GROUP 3 — READER ACCOUNT
- **T-07 t07_account_welcome.html** — label YOUR NORTHERN TIMES ACCOUNT. Header ✓, "Your
  account is ready.". Body: greeting; para; Summary Box (Email/Account Type/Created); CTA
  "Go to My Account →" → account_url; bullet list of account features; security note.
  Footer: Privacy · site_url. Vars: first_name, account_email, account_type, creation_date,
  account_url.
- **T-08 t08_password_reset.html** — no label. Header no circle, bg #1F2937 dark, "Password
  Reset". Body: greeting; para; DARK CTA (#1F2937) "Reset My Password →" → reset_url;
  "expires in 1 hour"; amber security callout (#FEF3C7/#F59E0B); "Request received at
  {{request_timestamp}}". Footer: NO social, NO unsubscribe (security), "automated security
  email. Do not reply." Vars: first_name, reset_url, request_timestamp.
- **T-09 t09_verify_email.html** — label VERIFY YOUR EMAIL. Header ✉, "Verify your email
  address.". Body: greeting; para; new_email display box; CTA "Verify Email Address →" →
  verification_url; "expires in 24 hours"; security note + old_email stays active. Footer:
  Privacy · site_url. Vars: first_name, new_email, old_email, verification_url.

### GROUP 4 — ADMIN / INTERNAL (functional > decorative; no circles/big CTAs)
Shared admin header: narrow #111827 band, "NORTHERN TIMES — INTERNAL" 11px uppercase
white .5, logo white .6.
- **A-01 a01_dispatch_report.html** — label DISPATCH REPORT. Summary Box (Edition/Type/Sent
  At/Recipients/Dispatched By); optional Delivery Stats box (Delivered/Bounced/Opened/
  Unsubscribed — from Brevo webhook later); Stats Row (total_subscribers/active_rate/
  avg_open_rate); text link "View in Admin Portal →". Vars: edition_label, edition_type,
  sent_timestamp, recipient_count, admin_user_name, delivered/bounced/opened/unsubscribed_count?,
  total_subscribers, active_rate, avg_open_rate, admin_newsletter_url.
- **A-02 a02_subscriber_summary.html** — label SUBSCRIBER SUMMARY (daily batch). Summary Box
  (Period/New/Pending/Unsubscribed Today/Net Change); Stats Row (total_confirmed/week_growth/
  month_growth); TOP REFERRAL SOURCES plain rows (0–5); "View Subscriber List →". Vars:
  report_date, new_count, pending_count, unsubscribed_today, net_change, total_confirmed,
  week_growth, month_growth, top_referral_sources[], admin_subscribers_url.

---

## Step 5 — CMS Integration
Confirm the global context supplies (add missing as Setting keys + expose in
site_context()): settings.publication_name, logo_url (absolute), brand_primary_color,
brand_accent_color, site_url, address, editorial_email, footer_tagline, social_facebook,
social_whatsapp, social_x, social_instagram, social_youtube, social_tiktok; current_year.
Footer social icons = hosted 28×28 PNGs (absolute URLs), not inline SVG, not icon fonts.

## Step 6 — Testing
Render every template with sample data (see the shell script in the original prompt paste
in chat / git history) writing to /tmp/nt_email_*.html; manually check: logo loads, brand
colors applied, alert circle is round, article cards render, CTA is pill, all links
absolute, footer from settings, social only non-empty, legal links present, no unfilled
`{{ }}`, no template errors. Cross-client: Gmail web, Apple Mail, Outlook, mobile Gmail.
(Use the Mailpit overlay inbox at :8026 to eyeball real sends.)

## Deliverables
base.html + 10 components; T-01..T-09 (9) + A-01, A-02 (2) = 11 templates; all previewed
no-error; no hardcoded CMS values; absolute URLs; alt on images; checked in Gmail + Apple Mail.

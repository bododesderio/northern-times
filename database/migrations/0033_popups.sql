-- 0033_popups.sql
-- Phase 6A: Popup & Banner System foundation tables

-- ── Main popups table ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS popups (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),

  -- Identity
  name          VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) UNIQUE NOT NULL,

  -- Type & Style
  popup_type    VARCHAR(50)  NOT NULL DEFAULT 'newsletter_signup',
  banner_style  VARCHAR(50)  NOT NULL DEFAULT 'card_modal',
  position      VARCHAR(30)  NOT NULL DEFAULT 'center',

  -- Content
  title         VARCHAR(255) NULL,
  body          TEXT         NULL,
  image_url     TEXT         NULL,
  button_text   VARCHAR(100) NULL DEFAULT 'Subscribe',
  button_url    TEXT         NULL,
  secondary_btn_text VARCHAR(100) NULL,
  secondary_btn_url  TEXT    NULL,

  -- Appearance
  bg_color      VARCHAR(20)  NULL DEFAULT '#ffffff',
  text_color    VARCHAR(20)  NULL DEFAULT '#1a1a1a',
  btn_bg_color  VARCHAR(20)  NULL DEFAULT '#cc0000',
  btn_text_color VARCHAR(20) NULL DEFAULT '#ffffff',
  overlay_opacity DECIMAL(3,2) NULL DEFAULT 0.50,
  custom_css    TEXT         NULL,

  -- Behavior
  trigger_type  VARCHAR(30)  NOT NULL DEFAULT 'page_load',
  trigger_value VARCHAR(50)  NULL,
  show_delay    INT          NOT NULL DEFAULT 0,
  close_delay   INT          NOT NULL DEFAULT 0,
  frequency     VARCHAR(30)  NOT NULL DEFAULT 'once_session',
  frequency_days INT         NULL,
  priority      INT          NOT NULL DEFAULT 10,
  version       INT          NOT NULL DEFAULT 1,

  -- Targeting
  target_audience VARCHAR(30) NOT NULL DEFAULT 'all',
  target_device   VARCHAR(20) NOT NULL DEFAULT 'all',
  target_pages    TEXT        NULL,
  target_categories TEXT     NULL,

  -- Schedule
  status        VARCHAR(20)  NOT NULL DEFAULT 'draft',
  start_date    TIMESTAMPTZ  NULL,
  end_date      TIMESTAMPTZ  NULL,

  -- Feature-specific fields
  has_email_field   BOOLEAN NOT NULL DEFAULT FALSE,
  subscriber_source VARCHAR(50) NULL,
  sponsor_name      VARCHAR(255) NULL,
  click_url         TEXT    NULL,
  nofollow          BOOLEAN NOT NULL DEFAULT TRUE,
  promo_code        VARCHAR(100) NULL,
  campaign_name     VARCHAR(255) NULL,

  -- Tracking
  impressions   BIGINT  NOT NULL DEFAULT 0,
  clicks        BIGINT  NOT NULL DEFAULT 0,
  conversions   BIGINT  NOT NULL DEFAULT 0,
  closes        BIGINT  NOT NULL DEFAULT 0,

  -- Metadata
  created_by    UUID    NULL REFERENCES users(id) ON DELETE SET NULL,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_popups_status     ON popups(status);
CREATE INDEX IF NOT EXISTS idx_popups_type       ON popups(popup_type);
CREATE INDEX IF NOT EXISTS idx_popups_priority   ON popups(priority);
CREATE INDEX IF NOT EXISTS idx_popups_schedule   ON popups(status, start_date, end_date);

-- ── Event tracking ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS popup_events (
  id          BIGSERIAL PRIMARY KEY,
  popup_id    UUID NOT NULL REFERENCES popups(id) ON DELETE CASCADE,
  event_type  VARCHAR(20) NOT NULL,
  visitor_ip  VARCHAR(45) NULL,
  user_agent  TEXT        NULL,
  page_url    TEXT        NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_popup_events_popup   ON popup_events(popup_id);
CREATE INDEX IF NOT EXISTS idx_popup_events_type    ON popup_events(event_type);
CREATE INDEX IF NOT EXISTS idx_popup_events_date    ON popup_events(created_at);

-- ── Visitor dismissal records ───────────────────────────────────
CREATE TABLE IF NOT EXISTS popup_dismissals (
  id          BIGSERIAL PRIMARY KEY,
  popup_id    UUID        NOT NULL REFERENCES popups(id) ON DELETE CASCADE,
  visitor_ip  VARCHAR(45) NOT NULL,
  version     INT         NOT NULL DEFAULT 1,
  dismissed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_popup_dismiss_lookup
  ON popup_dismissals(popup_id, visitor_ip, version);

-- ── Constraints ─────────────────────────────────────────────────
DO $$
BEGIN
  ALTER TABLE popups ADD CONSTRAINT chk_popup_type CHECK (
    popup_type IN ('cookie_consent','newsletter_signup','breaking_news','welcome_back',
                   'exit_intent','ad_popup','promotion','pwa_install','paywall','survey')
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
  ALTER TABLE popups ADD CONSTRAINT chk_banner_style CHECK (
    banner_style IN ('minimal_bar','card_modal','split_image','fullscreen','slide_in','floating')
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
  ALTER TABLE popups ADD CONSTRAINT chk_popup_status CHECK (
    status IN ('draft','active','inactive','scheduled','expired')
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
  ALTER TABLE popups ADD CONSTRAINT chk_trigger_type CHECK (
    trigger_type IN ('page_load','scroll_percent','exit_intent','manual','page_views','time_delay')
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
  ALTER TABLE popups ADD CONSTRAINT chk_frequency CHECK (
    frequency IN ('once_ever','once_session','daily','weekly','monthly','custom_days','every_visit')
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
  ALTER TABLE popup_events ADD CONSTRAINT chk_event_type CHECK (
    event_type IN ('impression','click','conversion','close','email_submit')
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

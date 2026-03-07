-- Ad events: per-visitor impression and click tracking
CREATE TABLE IF NOT EXISTS ad_events (
    id          BIGSERIAL PRIMARY KEY,
    ad_slot_id  UUID NOT NULL REFERENCES ad_slots(id) ON DELETE CASCADE,
    ip_address  INET NOT NULL,
    event_type  VARCHAR(20) NOT NULL DEFAULT 'impression',
    page_url    VARCHAR(500),
    created_at  TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_ad_event_type CHECK (event_type IN ('impression', 'click'))
);

CREATE INDEX IF NOT EXISTS idx_ad_events_slot      ON ad_events (ad_slot_id);
CREATE INDEX IF NOT EXISTS idx_ad_events_ip_date   ON ad_events (ip_address, created_at);
CREATE INDEX IF NOT EXISTS idx_ad_events_type_date ON ad_events (event_type, created_at);

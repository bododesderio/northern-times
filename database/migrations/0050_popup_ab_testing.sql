-- Phase 13: Popup A/B Testing
-- Allows splitting popup traffic between variants to measure performance.

-- A/B test container — groups popup variants together
CREATE TABLE IF NOT EXISTS popup_ab_tests (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'draft'
                  CHECK (status IN ('draft', 'running', 'paused', 'completed')),
    winner_id     BIGINT,               -- popup_id of the winning variant (set on completion)
    metric        VARCHAR(30) NOT NULL DEFAULT 'conversion_rate'
                  CHECK (metric IN ('conversion_rate', 'click_rate', 'impressions')),
    confidence    DECIMAL(5,2),          -- statistical confidence % when winner declared
    started_at    TIMESTAMP,
    ended_at      TIMESTAMP,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Link popups to A/B tests as variants
ALTER TABLE popups
    ADD COLUMN IF NOT EXISTS ab_test_id   BIGINT REFERENCES popup_ab_tests(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS ab_variant   VARCHAR(5) CHECK (ab_variant IN ('A', 'B'));

CREATE INDEX IF NOT EXISTS idx_popups_ab_test ON popups(ab_test_id) WHERE ab_test_id IS NOT NULL;

-- Track which variant a visitor was assigned to (sticky assignment)
CREATE TABLE IF NOT EXISTS popup_ab_assignments (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ab_test_id    BIGINT NOT NULL REFERENCES popup_ab_tests(id) ON DELETE CASCADE,
    visitor_hash  VARCHAR(64) NOT NULL,  -- hashed IP + user-agent for privacy
    variant       VARCHAR(5) NOT NULL CHECK (variant IN ('A', 'B')),
    assigned_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (ab_test_id, visitor_hash)
);

CREATE INDEX IF NOT EXISTS idx_ab_assign_test ON popup_ab_assignments(ab_test_id);

-- Migration: 0013_policy_pages.sql

CREATE TABLE IF NOT EXISTS policy_pages (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title       VARCHAR(255)  NOT NULL,
    slug        VARCHAR(255)  NOT NULL UNIQUE,
    content     TEXT          NOT NULL DEFAULT '',
    show_in_footer BOOLEAN    NOT NULL DEFAULT TRUE,
    sort_order  INTEGER       NOT NULL DEFAULT 0,
    is_published BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_policy_pages_slug        ON policy_pages (slug);
CREATE INDEX IF NOT EXISTS idx_policy_pages_footer      ON policy_pages (show_in_footer, is_published);
CREATE INDEX IF NOT EXISTS idx_policy_pages_sort        ON policy_pages (sort_order ASC);

-- Seed the 4 standard pages (unpublished — editors activate them when ready)
INSERT INTO policy_pages (title, slug, show_in_footer, sort_order, is_published) VALUES
    ('Editorial Standards', 'editorial-standards', TRUE, 1, FALSE),
    ('Corrections',         'corrections',         TRUE, 2, FALSE),
    ('Privacy Policy',      'privacy',             TRUE, 3, FALSE),
    ('Terms of Use',        'terms',               TRUE, 4, FALSE)
ON CONFLICT (slug) DO NOTHING;

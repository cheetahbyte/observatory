CREATE TABLE update_checks (
    id BIGSERIAL PRIMARY KEY,
    checked_at TIMESTAMPTZ NOT NULL DEFAULT now(),

    app_version TEXT,
    app_build TEXT,
    sparkle_version TEXT,

    os_version TEXT,
    os_build TEXT,
    arch TEXT,
    language TEXT,

    ip_hash TEXT,
    user_agent TEXT,

    raw_query JSONB NOT NULL DEFAULT '{}'::jsonb
);

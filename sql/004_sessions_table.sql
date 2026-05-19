-- NexusSync - 004_sessions_table.sql
-- PostgreSQL-backed PHP session storage

BEGIN;

CREATE TABLE IF NOT EXISTS php_sessions (
    id          VARCHAR(128) PRIMARY KEY,
    data        TEXT NOT NULL DEFAULT '',
    last_access TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    lifetime    INT NOT NULL DEFAULT 1440
);

CREATE INDEX IF NOT EXISTS idx_sessions_last_access ON php_sessions(last_access);

COMMIT;

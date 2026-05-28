-- psis-z3950-schema.sql
-- SQL migration: create Z39.50 operational tables for PSIS ahgLibraryPlugin
-- Issue: atom-ahg-plugins#92
--
-- These tables mirror Heratio's ahg-z3950 migration exactly.
-- Run this BEFORE enabling the module, against the PSIS database.
--
-- The library_biblio_* tables (work, instance, agent, work_agent)
-- should ALREADY exist in PSIS if PSIS has been running ahgLibraryPlugin —
-- if they don't, run ahgLibraryPlugin's own schema first.
--
-- psis$ psql -U postgres -d qtatomo < /path/to/psis-z3950-schema.sql

-- ─── Z39.50 operational tables ────────────────────────────────────────

CREATE TABLE IF NOT EXISTS z3950_targets (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    host            VARCHAR(255) NOT NULL,
    port            SMALLINT     NOT NULL DEFAULT 210,
    database_name   VARCHAR(255) NOT NULL,
    syntax          VARCHAR(50)  NOT NULL DEFAULT 'USmarc',
    element_set     VARCHAR(5)   NOT NULL DEFAULT 'F',
    charset         VARCHAR(50)  NOT NULL DEFAULT 'UTF-8',
    authentication  VARCHAR(255) NULL,
    active          BOOLEAN      NOT NULL DEFAULT TRUE,
    notes           TEXT         NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE z3950_targets IS 'Remote Z39.50 server profiles (hosts to search against)';

CREATE TABLE IF NOT EXISTS z3950_query_log (
    id              SERIAL PRIMARY KEY,
    target_id       INTEGER NULL REFERENCES z3950_targets(id) ON DELETE SET NULL,
    query           VARCHAR(1000) NOT NULL,
    syntax          VARCHAR(50)   NOT NULL,
    result_count    INTEGER       NOT NULL DEFAULT 0,
    elapsed_ms      INTEGER        NOT NULL DEFAULT 0,
    error           TEXT          NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE z3950_query_log IS 'Audit log of Z39.50 search queries';

-- Index for slow/all-errors queries (for ops monitoring)
CREATE INDEX IF NOT EXISTS idx_qlog_created_at ON z3950_query_log (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_qlog_target_id  ON z3950_query_log (target_id);

CREATE TABLE IF NOT EXISTS z3950_import_log (
    id                  SERIAL PRIMARY KEY,
    target_id           INTEGER NULL REFERENCES z3950_targets(id) ON DELETE SET NULL,
    result_set          VARCHAR(64) NOT NULL,
    record_number       INTEGER     NOT NULL DEFAULT 0,
    marc_content        TEXT        NOT NULL,
    works_created       INTEGER      NOT NULL DEFAULT 0,
    instances_created   INTEGER     NOT NULL DEFAULT 0,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE z3950_import_log IS 'Audit log of imported MARC records from Z39.50 searches';

CREATE INDEX IF NOT EXISTS idx_import_result_set ON z3950_import_log (result_set);
CREATE INDEX IF NOT EXISTS idx_import_created_at  ON z3950_import_log (created_at DESC);

-- ─── Pre-seeded default targets (LoC sample / OCLC dev) ───────────────

-- Only seed if table is empty (idempotent; run each time)
INSERT INTO z3950_targets (name, host, port, database_name, syntax, element_set, active, notes)
SELECT 'Library of Congress (test)', 'lx2.loc.gov', 210, 'LCDB', 'USmarc', 'F', FALSE,
       'Production target — flip active=true when ready. Requires ip-based auth.'
WHERE NOT EXISTS (SELECT 1 FROM z3950_targets WHERE host = 'lx2.loc.gov');

INSERT INTO z3950_targets (name, host, port, database_name, syntax, element_set, active, notes)
SELECT 'BL (test)', 'corporate.bl.uk', 210, 'BLDB', 'USmarc', 'F', FALSE,
       'British Library Z39.50. Requires registration.'
WHERE NOT EXISTS (SELECT 1 FROM z3950_targets WHERE host = 'corporate.bl.uk');

INSERT INTO z3950_targets (name, host, port, database_name, syntax, element_set, active, notes)
SELECT 'WorldCat (dev)', 'zcat.oclc.org', 210, 'WorldCat', 'USmarc', 'F', FALSE,
       'OCLC WorldCat. Requires OCLC authorisation.'
WHERE NOT EXISTS (SELECT 1 FROM z3950_targets WHERE host = 'zcat.oclc.org');

-- schema.sql — minimal SQLite schema for ahg-library unit tests.
-- Mirrors the Heratio MySQL library schema columns used by
-- LibraryCirculationService and LibraryPatronService.

CREATE TABLE IF NOT EXISTS library_patron (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    card_number              TEXT    UNIQUE,
    patron_type              TEXT    NOT NULL DEFAULT 'student',
    first_name               TEXT,
    last_name                TEXT,
    email                    TEXT,
    phone                    TEXT,
    address                  TEXT,
    institution              TEXT,
    department               TEXT,
    id_number                TEXT,
    date_of_birth            TEXT,
    membership_start         TEXT,
    membership_expiry        TEXT,
    max_checkouts            INTEGER DEFAULT 5,
    max_renewals             INTEGER DEFAULT 2,
    max_holds                INTEGER DEFAULT 3,
    borrowing_status         TEXT    DEFAULT 'active',
    suspension_reason        TEXT,
    suspension_until         TEXT,
    total_checkouts          INTEGER DEFAULT 0,
    total_fines_owed         REAL    DEFAULT 0.00,
    actor_id                 INTEGER,
    created_at               TEXT,
    updated_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_copy (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    library_item_id          INTEGER NOT NULL,
    barcode                  TEXT    UNIQUE,
    classification_scheme   TEXT,
    call_number              TEXT,
    shelf_location           TEXT,
    copy_number              INTEGER DEFAULT 1,
    status                   TEXT    DEFAULT 'available',
    condition_code           TEXT,
    created_at               TEXT,
    updated_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_item (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    information_object_id    INTEGER,
    isbn                     TEXT,
    issn                     TEXT,
    material_type            TEXT,
    created_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_checkout (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    copy_id                  INTEGER NOT NULL,
    patron_id                INTEGER NOT NULL,
    checkout_date            TEXT,
    due_date                 TEXT,
    return_date              TEXT,
    renewed_count            INTEGER DEFAULT 0,
    status                   TEXT    DEFAULT 'active',
    return_condition         TEXT,
    return_notes             TEXT,
    checked_out_by           INTEGER,
    checked_in_by            INTEGER,
    created_at               TEXT,
    updated_at               TEXT,
    FOREIGN KEY (patron_id) REFERENCES library_patron(id),
    FOREIGN KEY (copy_id)   REFERENCES library_copy(id)
);

CREATE TABLE IF NOT EXISTS library_hold (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    library_item_id          INTEGER NOT NULL,
    patron_id                INTEGER NOT NULL,
    hold_date                TEXT,
    expiry_date              TEXT,
    notification_sent        INTEGER DEFAULT 0,
    queue_position           INTEGER DEFAULT 1,
    status                   TEXT    DEFAULT 'pending',
    pickup_branch            TEXT,
    cancelled_date           TEXT,
    cancel_reason            TEXT,
    created_at               TEXT,
    updated_at               TEXT,
    FOREIGN KEY (patron_id)  REFERENCES library_patron(id)
);

CREATE TABLE IF NOT EXISTS library_fine (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    patron_id                INTEGER NOT NULL,
    checkout_id              INTEGER,
    fine_type                TEXT    NOT NULL,
    amount                   REAL    NOT NULL,
    paid_amount              REAL    DEFAULT 0.00,
    currency                 TEXT    DEFAULT 'ZAR',
    status                   TEXT    DEFAULT 'outstanding',
    description              TEXT,
    fine_date                TEXT,
    paid_date                TEXT,
    created_at               TEXT,
    updated_at               TEXT,
    FOREIGN KEY (patron_id)  REFERENCES library_patron(id)
);

CREATE TABLE IF NOT EXISTS library_loan_rule (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    material_type            TEXT    NOT NULL,
    patron_type              TEXT    NOT NULL,
    loan_period_days         INTEGER NOT NULL,
    max_renewals             INTEGER DEFAULT 1,
    fine_per_day             REAL    DEFAULT 0.00,
    fine_cap                 REAL,
    grace_period_days        INTEGER DEFAULT 0,
    is_loanable              INTEGER DEFAULT 1
);

-- i18n stub (library_item has FK to information_object_i18n via IO relation)
CREATE TABLE IF NOT EXISTS information_object_i18n (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    title                    TEXT,
    culture                  TEXT    DEFAULT 'en'
);

CREATE TABLE IF NOT EXISTS information_object (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    slug                     TEXT
);

-- COUNTER R5 aggregate counters (heratio#766) + per-event log (heratio#1096).
CREATE TABLE IF NOT EXISTS library_usage_stats (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    stat_date                TEXT    NOT NULL,
    library_item_id          INTEGER,
    patron_id                INTEGER,
    metric_type              TEXT    NOT NULL,
    count                    INTEGER NOT NULL DEFAULT 0,
    partner_code             TEXT    NOT NULL DEFAULT 'heratio',
    reporting_period         TEXT    DEFAULT '',
    created_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_counter_log (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id               TEXT,
    user_id                  INTEGER,
    resource_id              INTEGER,
    resource_type            TEXT    NOT NULL DEFAULT 'item',
    access_type              TEXT    NOT NULL DEFAULT 'Controlled',
    event                    TEXT    NOT NULL DEFAULT 'investigation',
    event_date               TEXT    NOT NULL,
    status                   TEXT    NOT NULL DEFAULT 'success',
    created_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_sushi_subscription (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    partner_code             TEXT    NOT NULL,
    api_key                  TEXT    NOT NULL,
    base_url                 TEXT    NOT NULL,
    report_types             TEXT    NOT NULL,
    contact_email            TEXT    DEFAULT '',
    active                   INTEGER NOT NULL DEFAULT 1,
    created_at               TEXT,
    updated_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_sushi_consumer (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id              TEXT    NOT NULL,
    requestor_id             TEXT    NOT NULL,
    api_key_hash             TEXT    NOT NULL,
    name                     TEXT,
    contact_email            TEXT,
    active                   INTEGER NOT NULL DEFAULT 1,
    created_at               TEXT,
    updated_at               TEXT
);

CREATE TABLE IF NOT EXISTS library_sushi_audit_log (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id              TEXT,
    requestor_id             TEXT,
    report_id                TEXT,
    begin_date               TEXT,
    end_date                 TEXT,
    ip                       TEXT,
    user_agent               TEXT,
    authorised               INTEGER NOT NULL DEFAULT 1,
    requested_at             TEXT
);
-- ── Serials (heratio#1092) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS library_serial (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT    NOT NULL,
    issn        TEXT    NOT NULL DEFAULT '',
    frequency   TEXT    NOT NULL DEFAULT '',
    publisher   TEXT    NOT NULL DEFAULT '',
    status      TEXT    NOT NULL DEFAULT 'active',
    notes       TEXT,
    created_at  TEXT,
    updated_at  TEXT
);

CREATE TABLE IF NOT EXISTS library_serial_issue (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    serial_id      INTEGER NOT NULL,
    volume         TEXT    NOT NULL DEFAULT '',
    issue_number   TEXT    NOT NULL DEFAULT '',
    issue_date     TEXT,
    received_at    TEXT,
    status         TEXT    NOT NULL DEFAULT 'received',
    binding_id     INTEGER,
    shelf_location TEXT,
    bound_at       TEXT,
    notes          TEXT,
    created_at     TEXT,
    updated_at     TEXT
);

CREATE TABLE IF NOT EXISTS library_serial_subscription (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    serial_id           INTEGER NOT NULL UNIQUE,
    subscription_start  TEXT,
    subscription_end    TEXT,
    subscription_cost   REAL,
    notification_email  TEXT,
    auto_claim_max      INTEGER DEFAULT 3,
    notes               TEXT,
    created_at          TEXT,
    updated_at          TEXT
);

CREATE TABLE IF NOT EXISTS library_serial_prediction (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    serial_id     INTEGER NOT NULL,
    volume        TEXT    NOT NULL DEFAULT '',
    issue_number  TEXT    NOT NULL DEFAULT '',
    expected_date TEXT,
    days_until    INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT
);

CREATE TABLE IF NOT EXISTS library_claim (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    serial_id   INTEGER NOT NULL,
    issue_id    INTEGER,
    claimed_at  TEXT,
    claimed_by  TEXT,
    reason      TEXT,
    status      TEXT    NOT NULL DEFAULT 'open',
    created_at  TEXT,
    updated_at  TEXT
);

CREATE TABLE IF NOT EXISTS library_binding (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    serial_id    INTEGER NOT NULL,
    volume_range TEXT    NOT NULL DEFAULT '',
    status       TEXT    NOT NULL DEFAULT 'pending',
    bound_at     TEXT,
    location     TEXT,
    created_at   TEXT,
    updated_at   TEXT
);

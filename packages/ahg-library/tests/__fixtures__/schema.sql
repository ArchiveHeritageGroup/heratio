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
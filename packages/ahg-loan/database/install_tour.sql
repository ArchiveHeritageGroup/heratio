-- ============================================================================
-- ahg-loan — touring-exhibition scheduling schema (#1190 first slice)
-- ============================================================================
-- Digital twin: cross-institution loan / touring-exhibition planning.
--
-- A tour booking commits a single object to a venue for a date range. The
-- scheduling service checks each new booking against:
--   * other tour bookings for the same object (overlapping date ranges)
--   * committed outgoing loans for the same object (ahg_loan + ahg_loan_object)
--   * on-display windows in the digital-twin exhibition (ahg_exhibition_placement)
-- and only persists when the requested window is clear.
--
-- Jurisdiction-neutral: venue is a free-text field so any institution in any
-- market can be named; no country-specific assumptions.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS ahg_loan_tour_booking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Object being toured (local information object). external_object_id keeps
    -- the door open for federated/remote objects per the #1190 federation track.
    information_object_id INT,
    external_object_id VARCHAR(255),

    -- Cached object descriptors (so the schedule renders without a join when
    -- the object is remote/federated).
    object_title VARCHAR(500),
    object_identifier VARCHAR(255),

    -- Optional link to a parent loan agreement (a tour is a sequence of stops).
    loan_id BIGINT UNSIGNED,

    -- Venue / hosting institution for this stop (free text — any market).
    venue_name VARCHAR(500) NOT NULL,
    venue_city VARCHAR(255),
    venue_country VARCHAR(255),

    -- The committed date window for this stop.
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,

    -- Lifecycle: tentative (pencilled in) or committed (firm).
    status VARCHAR(40) NOT NULL DEFAULT 'committed' COMMENT 'tentative, committed, cancelled',

    notes TEXT,

    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tour_booking_object (information_object_id),
    INDEX idx_tour_booking_loan (loan_id),
    INDEX idx_tour_booking_dates (start_date, end_date),
    INDEX idx_tour_booking_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

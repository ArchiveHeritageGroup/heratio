-- ============================================================================
-- ahg-provenance — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgProvenancePlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Provenance Plugin Database Schema
-- Tracks chain of custody and ownership history for archival materials

-- Provenance Agent (who owned/held the item)
CREATE TABLE IF NOT EXISTS provenance_agent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL COMMENT 'Link to AtoM actor if exists',
    agent_type VARCHAR(49) COMMENT 'person, organization, family, unknown' DEFAULT 'person',
    name VARCHAR(500) NOT NULL,
    contact_info TEXT NULL,
    location VARCHAR(500) NULL,
    country_code VARCHAR(3) NULL,
    verified TINYINT(1) DEFAULT 0,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_id),
    INDEX idx_agent_type (agent_type),
    INDEX idx_name (name(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Agent i18n
CREATE TABLE IF NOT EXISTS provenance_agent_i18n (
    id INT NOT NULL,
    culture VARCHAR(16) NOT NULL DEFAULT 'en',
    name VARCHAR(500) NULL,
    biographical_note TEXT NULL,
    notes TEXT NULL,
    PRIMARY KEY (id, culture),
    FOREIGN KEY (id) REFERENCES provenance_agent(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main Provenance Record (links object to chain of custody)
CREATE TABLE IF NOT EXISTS provenance_record (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    provenance_agent_id INT NULL COMMENT 'Current/last known owner',
    donor_id INT NULL COMMENT 'Link to donor if applicable',
    donor_agreement_id INT NULL COMMENT 'Link to donor agreement if applicable',
    
    -- Current status
    current_status VARCHAR(56) COMMENT 'owned, on_loan, deposited, unknown, disputed' DEFAULT 'owned',
    custody_type VARCHAR(47) COMMENT 'permanent, temporary, loan, deposit' DEFAULT 'permanent',
    
    -- Acquisition info
    acquisition_type VARCHAR(101) COMMENT 'donation, purchase, bequest, transfer, loan, deposit, exchange, field_collection, unknown' DEFAULT 'unknown',
    acquisition_date DATE NULL,
    acquisition_date_text VARCHAR(255) NULL COMMENT 'For imprecise dates like "circa 1950"',
    acquisition_price DECIMAL(15,2) NULL,
    acquisition_currency VARCHAR(3) NULL,
    
    -- Provenance certainty
    certainty_level VARCHAR(59) COMMENT 'certain, probable, possible, uncertain, unknown' DEFAULT 'unknown',
    has_gaps TINYINT(1) DEFAULT 0 COMMENT 'Are there gaps in provenance chain?',
    gap_description TEXT NULL,
    
    -- Research status
    research_status VARCHAR(60) COMMENT 'not_started, in_progress, complete, inconclusive' DEFAULT 'not_started',
    research_notes TEXT NULL,
    
    -- Nazi-era / WWII provenance (important for museums)
    nazi_era_provenance_checked TINYINT(1) DEFAULT 0,
    nazi_era_provenance_clear TINYINT(1) NULL,
    nazi_era_notes TEXT NULL,
    
    -- Cultural property
    cultural_property_status VARCHAR(57) COMMENT 'none, claimed, disputed, repatriated, cleared' DEFAULT 'none',
    cultural_property_notes TEXT NULL,
    
    -- Summary
    provenance_summary TEXT NULL COMMENT 'Human-readable provenance statement',
    
    is_complete TINYINT(1) DEFAULT 0,
    is_public TINYINT(1) DEFAULT 1,
    
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_info_object (information_object_id),
    INDEX idx_agent (provenance_agent_id),
    INDEX idx_donor (donor_id),
    INDEX idx_status (current_status),
    INDEX idx_acquisition_type (acquisition_type),
    INDEX idx_certainty (certainty_level),
    INDEX idx_nazi_era (nazi_era_provenance_checked, nazi_era_provenance_clear),
    FOREIGN KEY (provenance_agent_id) REFERENCES provenance_agent(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Record i18n
CREATE TABLE IF NOT EXISTS provenance_record_i18n (
    id INT NOT NULL,
    culture VARCHAR(16) NOT NULL DEFAULT 'en',
    provenance_summary TEXT NULL,
    acquisition_notes TEXT NULL,
    gap_description TEXT NULL,
    research_notes TEXT NULL,
    nazi_era_notes TEXT NULL,
    cultural_property_notes TEXT NULL,
    PRIMARY KEY (id, culture),
    FOREIGN KEY (id) REFERENCES provenance_record(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Events (the chain of custody history)
CREATE TABLE IF NOT EXISTS provenance_event (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provenance_record_id INT NOT NULL,
    
    -- Event participants
    from_agent_id INT NULL COMMENT 'Previous owner/holder',
    to_agent_id INT NULL COMMENT 'New owner/holder',
    
    -- Event details
    event_type VARCHAR(365) COMMENT 'creation, commission, sale, purchase, auction, gift, donation, bequest, inheritance, descent, loan_out, loan_return, deposit, withdrawal, transfer, exchange, theft, recovery, confiscation, restitution, repatriation, discovery, excavation, import, export, authentication, appraisal, conservation, restoration, accessioning, deaccessioning, unknown, other' NOT NULL DEFAULT 'unknown',
    
    -- Date (can be precise or imprecise)
    event_date DATE NULL,
    event_date_start DATE NULL,
    event_date_end DATE NULL,
    event_date_text VARCHAR(255) NULL COMMENT 'For display like "circa 1920" or "before 1945"',
    date_certainty VARCHAR(50) COMMENT 'exact, approximate, estimated, unknown' DEFAULT 'unknown',
    
    -- Location
    event_location VARCHAR(500) NULL,
    event_city VARCHAR(255) NULL,
    event_country VARCHAR(3) NULL,
    
    -- Financial (for sales/purchases/auctions)
    price DECIMAL(15,2) NULL,
    currency VARCHAR(3) NULL,
    sale_reference VARCHAR(255) NULL COMMENT 'Auction lot number, invoice, etc.',
    
    -- Evidence/Documentation
    evidence_type VARCHAR(61) COMMENT 'documentary, physical, oral, circumstantial, none' DEFAULT 'none',
    evidence_description TEXT NULL,
    source_reference TEXT NULL COMMENT 'Bibliography, archive reference, etc.',
    
    -- Certainty
    certainty VARCHAR(50) COMMENT 'certain, probable, possible, uncertain' DEFAULT 'uncertain',
    
    -- Sequence
    sequence_number INT DEFAULT 0 COMMENT 'Order in provenance chain',
    
    notes TEXT NULL,
    is_public TINYINT(1) DEFAULT 1,
    
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_record (provenance_record_id),
    INDEX idx_from_agent (from_agent_id),
    INDEX idx_to_agent (to_agent_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_date (event_date),
    INDEX idx_sequence (provenance_record_id, sequence_number),
    FOREIGN KEY (provenance_record_id) REFERENCES provenance_record(id) ON DELETE CASCADE,
    FOREIGN KEY (from_agent_id) REFERENCES provenance_agent(id) ON DELETE SET NULL,
    FOREIGN KEY (to_agent_id) REFERENCES provenance_agent(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Event i18n
CREATE TABLE IF NOT EXISTS provenance_event_i18n (
    id INT NOT NULL,
    culture VARCHAR(16) NOT NULL DEFAULT 'en',
    event_description TEXT NULL,
    evidence_description TEXT NULL,
    source_reference TEXT NULL,
    notes TEXT NULL,
    PRIMARY KEY (id, culture),
    FOREIGN KEY (id) REFERENCES provenance_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supporting Documents
CREATE TABLE IF NOT EXISTS provenance_document (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provenance_record_id INT NULL,
    provenance_event_id INT NULL,
    
    document_type VARCHAR(322) COMMENT 'deed_of_gift, bill_of_sale, invoice, receipt, auction_catalog, exhibition_catalog, inventory, insurance_record, photograph, correspondence, certificate, customs_document, export_license, import_permit, appraisal, condition_report, newspaper_clipping, publication, oral_history, affidavit, legal_document, other' NOT NULL DEFAULT 'other',
    
    title VARCHAR(500) NULL,
    description TEXT NULL,
    document_date DATE NULL,
    document_date_text VARCHAR(255) NULL,
    
    -- File storage
    filename VARCHAR(500) NULL,
    original_filename VARCHAR(500) NULL,
    file_path VARCHAR(1000) NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT NULL,
    
    -- External reference
    external_url VARCHAR(1000) NULL,
    archive_reference VARCHAR(500) NULL COMMENT 'Reference in external archive',
    
    is_public TINYINT(1) DEFAULT 0,
    
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_record (provenance_record_id),
    INDEX idx_event (provenance_event_id),
    INDEX idx_doc_type (document_type),
    FOREIGN KEY (provenance_record_id) REFERENCES provenance_record(id) ON DELETE CASCADE,
    FOREIGN KEY (provenance_event_id) REFERENCES provenance_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default event types as taxonomy terms (optional, for dropdowns)
-- These would integrate with AtoM's taxonomy system

-- Add foreign key for cascade delete when information_object is deleted (skip if already exists)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_provenance_record_info_object' AND TABLE_NAME = 'provenance_record');

SET @fk_sql = IF(@fk_exists = 0,
    'ALTER TABLE provenance_record ADD CONSTRAINT fk_provenance_record_info_object FOREIGN KEY (information_object_id) REFERENCES information_object(id) ON DELETE CASCADE',
    'SELECT 1'
);

PREPARE fk_stmt FROM @fk_sql;
EXECUTE fk_stmt;
DEALLOCATE PREPARE fk_stmt;

SET FOREIGN_KEY_CHECKS = 1;

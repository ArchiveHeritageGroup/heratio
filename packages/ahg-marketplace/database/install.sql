-- ============================================================================
-- ahg-marketplace — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgMarketplacePlugin/database/install.sql
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

-- ============================================================================
-- ahgMarketplacePlugin - Database Schema
-- Version: 1.0.0
-- Tables: 16 core tables + seed data
-- ============================================================================

-- 1. MARKETPLACE SETTINGS (platform-wide configuration)
CREATE TABLE IF NOT EXISTS marketplace_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    setting_type VARCHAR(49) COMMENT 'text, number, boolean, json, currency' DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. MARKETPLACE CURRENCY (supported currencies with exchange rates)
CREATE TABLE IF NOT EXISTS marketplace_currency (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) NOT NULL,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    exchange_rate_to_zar DECIMAL(12,6) DEFAULT 1.000000,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. MARKETPLACE CATEGORY (item categories per GLAM sector)
CREATE TABLE IF NOT EXISTS marketplace_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector VARCHAR(50) COMMENT 'gallery, museum, archive, library, dam' NOT NULL,
    parent_id INT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uk_sector_slug (sector, slug),
    INDEX idx_parent (parent_id),
    INDEX idx_sector (sector),
    CONSTRAINT fk_marketplace_category_parent FOREIGN KEY (parent_id) REFERENCES marketplace_category(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MARKETPLACE SELLER (seller/gallery/institution profiles)
CREATE TABLE IF NOT EXISTS marketplace_seller (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_type VARCHAR(59) COMMENT 'artist, gallery, institution, collector, estate' NOT NULL,
    actor_id INT NULL,
    gallery_artist_id INT NULL,
    repository_id INT NULL,
    heritage_contributor_id INT NULL,
    display_name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    bio TEXT NULL,
    avatar_path VARCHAR(500) NULL,
    banner_path VARCHAR(500) NULL,
    country VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    website VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    payout_method VARCHAR(50) COMMENT 'bank_transfer, paypal, payfast, manual' DEFAULT 'bank_transfer',
    payout_details JSON NULL,
    payout_currency VARCHAR(3) DEFAULT 'ZAR',
    stripe_account_id VARCHAR(255) NULL,
    verification_status VARCHAR(52) COMMENT 'unverified, pending, verified, suspended' DEFAULT 'unverified',
    verification_documents JSON NULL,
    trust_level VARCHAR(41) COMMENT 'new, active, trusted, premium' DEFAULT 'new',
    total_sales INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    rating_count INT DEFAULT 0,
    sectors JSON NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    terms_accepted_at DATETIME NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_seller_type (seller_type),
    INDEX idx_actor (actor_id),
    INDEX idx_gallery_artist (gallery_artist_id),
    INDEX idx_repository (repository_id),
    INDEX idx_heritage_contributor (heritage_contributor_id),
    INDEX idx_verification (verification_status),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. MARKETPLACE LISTING (items for sale)
CREATE TABLE IF NOT EXISTS marketplace_listing (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_number VARCHAR(50) NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    information_object_id INT NULL,
    sector VARCHAR(50) COMMENT 'gallery, museum, archive, library, dam' NOT NULL,
    listing_type VARCHAR(44) COMMENT 'fixed_price, auction, offer_only' NOT NULL,
    status VARCHAR(88) COMMENT 'draft, pending_review, active, reserved, sold, expired, withdrawn, suspended' DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL,
    description TEXT NULL,
    short_description VARCHAR(1000) NULL,
    category_id INT NULL,
    medium VARCHAR(255) NULL,
    dimensions VARCHAR(255) NULL,
    weight_kg DECIMAL(8,2) NULL,
    year_created VARCHAR(50) NULL,
    artist_name VARCHAR(255) NULL,
    provenance TEXT NULL,
    condition_description TEXT NULL,
    condition_rating VARCHAR(45) COMMENT 'mint, excellent, good, fair, poor' NULL,
    is_framed TINYINT(1) DEFAULT 0,
    frame_description VARCHAR(255) NULL,
    edition_info VARCHAR(255) NULL,
    is_signed TINYINT(1) DEFAULT 0,
    certificate_of_authenticity TINYINT(1) DEFAULT 0,
    coa_path VARCHAR(500) NULL,
    is_digital TINYINT(1) DEFAULT 0,
    is_physical TINYINT(1) DEFAULT 1,
    requires_shipping TINYINT(1) DEFAULT 1,
    price DECIMAL(12,2) NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    price_on_request TINYINT(1) DEFAULT 0,
    minimum_offer DECIMAL(12,2) NULL,
    reserve_price DECIMAL(12,2) NULL,
    starting_bid DECIMAL(12,2) NULL,
    buy_now_price DECIMAL(12,2) NULL,
    shipping_from_country VARCHAR(100) NULL,
    shipping_from_city VARCHAR(100) NULL,
    shipping_domestic_price DECIMAL(10,2) NULL,
    shipping_international_price DECIMAL(10,2) NULL,
    shipping_notes TEXT NULL,
    free_shipping_domestic TINYINT(1) DEFAULT 0,
    insurance_value DECIMAL(12,2) NULL,
    gallery_valuation_id INT NULL,
    tags JSON NULL,
    featured_image_path VARCHAR(500) NULL,
    view_count INT DEFAULT 0,
    favourite_count INT DEFAULT 0,
    enquiry_count INT DEFAULT 0,
    listed_at DATETIME NULL,
    expires_at DATETIME NULL,
    sold_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_listing_number (listing_number),
    INDEX idx_seller (seller_id),
    INDEX idx_sector (sector),
    INDEX idx_status (status),
    INDEX idx_type (listing_type),
    INDEX idx_price (price),
    INDEX idx_listed (listed_at),
    INDEX idx_category (category_id),
    INDEX idx_io (information_object_id),
    FULLTEXT idx_search (title, description, artist_name, medium),
    CONSTRAINT fk_listing_seller FOREIGN KEY (seller_id) REFERENCES marketplace_seller(id) ON DELETE CASCADE,
    CONSTRAINT fk_listing_category FOREIGN KEY (category_id) REFERENCES marketplace_category(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MARKETPLACE LISTING IMAGE (multiple images per listing)
CREATE TABLE IF NOT EXISTS marketplace_listing_image (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    caption VARCHAR(500) NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id),
    CONSTRAINT fk_image_listing FOREIGN KEY (listing_id) REFERENCES marketplace_listing(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. MARKETPLACE AUCTION (auction configuration per listing)
CREATE TABLE IF NOT EXISTS marketplace_auction (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    auction_type VARCHAR(38) COMMENT 'english, sealed_bid, dutch' DEFAULT 'english',
    status VARCHAR(46) COMMENT 'upcoming, active, ended, cancelled' DEFAULT 'upcoming',
    starting_bid DECIMAL(12,2) NOT NULL,
    reserve_price DECIMAL(12,2) NULL,
    bid_increment DECIMAL(10,2) DEFAULT 1.00,
    buy_now_price DECIMAL(12,2) NULL,
    current_bid DECIMAL(12,2) NULL,
    current_bidder_id INT NULL,
    bid_count INT DEFAULT 0,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    auto_extend_minutes INT DEFAULT 5,
    extension_count INT DEFAULT 0,
    max_extensions INT DEFAULT 10,
    winner_id INT NULL,
    winning_bid DECIMAL(12,2) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_listing (listing_id),
    INDEX idx_status (status),
    INDEX idx_end_time (end_time),
    CONSTRAINT fk_auction_listing FOREIGN KEY (listing_id) REFERENCES marketplace_listing(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. MARKETPLACE BID (individual bids on auctions)
CREATE TABLE IF NOT EXISTS marketplace_bid (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    bid_amount DECIMAL(12,2) NOT NULL,
    max_bid DECIMAL(12,2) NULL,
    is_auto_bid TINYINT(1) DEFAULT 0,
    is_winning TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auction (auction_id),
    INDEX idx_user (user_id),
    INDEX idx_amount (bid_amount DESC),
    CONSTRAINT fk_bid_auction FOREIGN KEY (auction_id) REFERENCES marketplace_auction(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. MARKETPLACE OFFER (make an offer negotiations)
CREATE TABLE IF NOT EXISTS marketplace_offer (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    buyer_id INT NOT NULL,
    status VARCHAR(70) COMMENT 'pending, accepted, rejected, countered, withdrawn, expired' DEFAULT 'pending',
    offer_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    message TEXT NULL,
    seller_response TEXT NULL,
    counter_amount DECIMAL(12,2) NULL,
    expires_at DATETIME NULL,
    responded_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_status (status),
    CONSTRAINT fk_offer_listing FOREIGN KEY (listing_id) REFERENCES marketplace_listing(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. MARKETPLACE TRANSACTION (completed sales)
CREATE TABLE IF NOT EXISTS marketplace_transaction (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    buyer_id INT NOT NULL,
    source VARCHAR(39) COMMENT 'fixed_price, auction, offer' NOT NULL,
    offer_id BIGINT UNSIGNED NULL,
    auction_id BIGINT UNSIGNED NULL,
    sale_price DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    platform_commission_rate DECIMAL(5,2) NOT NULL,
    platform_commission_amount DECIMAL(12,2) NOT NULL,
    seller_amount DECIMAL(12,2) NOT NULL,
    vat_amount DECIMAL(10,2) DEFAULT 0,
    total_with_vat DECIMAL(12,2) NOT NULL,
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    insurance_cost DECIMAL(10,2) DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL,
    payment_status VARCHAR(53) COMMENT 'pending, paid, failed, refunded, disputed' DEFAULT 'pending',
    payment_gateway VARCHAR(50) NULL,
    payment_transaction_id VARCHAR(255) NULL,
    gateway_response JSON NULL,
    paid_at DATETIME NULL,
    shipping_status VARCHAR(72) COMMENT 'pending, preparing, shipped, in_transit, delivered, returned' DEFAULT 'pending',
    tracking_number VARCHAR(255) NULL,
    courier VARCHAR(255) NULL,
    shipped_at DATETIME NULL,
    delivered_at DATETIME NULL,
    buyer_confirmed_receipt TINYINT(1) DEFAULT 0,
    receipt_confirmed_at DATETIME NULL,
    status VARCHAR(96) COMMENT 'pending_payment, paid, shipping, delivered, completed, cancelled, disputed, refunded' DEFAULT 'pending_payment',
    completed_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_transaction_number (transaction_number),
    INDEX idx_seller (seller_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_listing (listing_id),
    INDEX idx_status (status),
    INDEX idx_payment (payment_status),
    CONSTRAINT fk_txn_listing FOREIGN KEY (listing_id) REFERENCES marketplace_listing(id),
    CONSTRAINT fk_txn_seller FOREIGN KEY (seller_id) REFERENCES marketplace_seller(id),
    CONSTRAINT fk_txn_offer FOREIGN KEY (offer_id) REFERENCES marketplace_offer(id) ON DELETE SET NULL,
    CONSTRAINT fk_txn_auction FOREIGN KEY (auction_id) REFERENCES marketplace_auction(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. MARKETPLACE PAYOUT (seller payouts)
CREATE TABLE IF NOT EXISTS marketplace_payout (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NULL,
    payout_number VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    method VARCHAR(66) COMMENT 'bank_transfer, paypal, payfast, manual, stripe_connect' NOT NULL,
    status VARCHAR(61) COMMENT 'pending, processing, completed, failed, cancelled' DEFAULT 'pending',
    reference VARCHAR(255) NULL,
    payout_details JSON NULL,
    processed_by INT NULL,
    processed_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_payout_number (payout_number),
    INDEX idx_seller (seller_id),
    INDEX idx_status (status),
    CONSTRAINT fk_payout_seller FOREIGN KEY (seller_id) REFERENCES marketplace_seller(id),
    CONSTRAINT fk_payout_txn FOREIGN KEY (transaction_id) REFERENCES marketplace_transaction(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. MARKETPLACE REVIEW (buyer/seller ratings)
CREATE TABLE IF NOT EXISTS marketplace_review (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT UNSIGNED NOT NULL,
    reviewer_id INT NOT NULL,
    reviewed_seller_id BIGINT UNSIGNED NOT NULL,
    review_type VARCHAR(44) COMMENT 'buyer_to_seller, seller_to_buyer' NOT NULL,
    rating INT NOT NULL,
    title VARCHAR(255) NULL,
    comment TEXT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    flagged TINYINT(1) DEFAULT 0,
    flagged_reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reviewed (reviewed_seller_id),
    INDEX idx_transaction (transaction_id),
    INDEX idx_reviewer (reviewer_id),
    CONSTRAINT fk_review_txn FOREIGN KEY (transaction_id) REFERENCES marketplace_transaction(id),
    CONSTRAINT fk_review_seller FOREIGN KEY (reviewed_seller_id) REFERENCES marketplace_seller(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. MARKETPLACE ENQUIRY (item enquiries)
CREATE TABLE IF NOT EXISTS marketplace_enquiry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    user_id INT NULL,
    name VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    status VARCHAR(38) COMMENT 'new, read, replied, closed' DEFAULT 'new',
    reply TEXT NULL,
    replied_by BIGINT UNSIGNED NULL,
    replied_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id),
    INDEX idx_status (status),
    CONSTRAINT fk_enquiry_listing FOREIGN KEY (listing_id) REFERENCES marketplace_listing(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. MARKETPLACE FOLLOW (follow sellers)
CREATE TABLE IF NOT EXISTS marketplace_follow (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_seller (user_id, seller_id),
    INDEX idx_seller (seller_id),
    CONSTRAINT fk_follow_seller FOREIGN KEY (seller_id) REFERENCES marketplace_seller(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. MARKETPLACE COLLECTION (curated collections / storefronts)
CREATE TABLE IF NOT EXISTS marketplace_collection (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    cover_image_path VARCHAR(500) NULL,
    collection_type VARCHAR(64) COMMENT 'curated, exhibition, seasonal, featured, genre, sale' DEFAULT 'curated',
    is_public TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_seller (seller_id),
    INDEX idx_type (collection_type),
    INDEX idx_featured (is_featured),
    CONSTRAINT fk_collection_seller FOREIGN KEY (seller_id) REFERENCES marketplace_seller(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. MARKETPLACE COLLECTION ITEM (listings in a collection)
CREATE TABLE IF NOT EXISTS marketplace_collection_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collection_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    sort_order INT DEFAULT 0,
    curator_note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_collection_listing (collection_id, listing_id),
    INDEX idx_listing (listing_id),
    CONSTRAINT fk_ci_collection FOREIGN KEY (collection_id) REFERENCES marketplace_collection(id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_listing FOREIGN KEY (listing_id) REFERENCES marketplace_listing(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Platform settings
INSERT IGNORE INTO marketplace_settings (setting_key, setting_value, setting_type, setting_group, description) VALUES
('platform_name', 'Heratio Marketplace', 'text', 'general', 'Platform display name'),
('default_commission_rate', '10.00', 'number', 'general', 'Default platform commission percentage'),
('listing_moderation_enabled', '1', 'boolean', 'general', 'Require admin approval for new listings'),
('listing_duration_days', '90', 'number', 'general', 'Default listing duration in days'),
('offer_expiry_days', '7', 'number', 'offers', 'Days before an offer expires'),
('auction_auto_extend_minutes', '5', 'number', 'auctions', 'Minutes to extend auction on late bid'),
('auction_max_extensions', '10', 'number', 'auctions', 'Maximum number of auction extensions'),
('payout_cooling_period_days', '5', 'number', 'payouts', 'Days after delivery before payout release'),
('min_listing_price', '1.00', 'number', 'general', 'Minimum listing price'),
('max_listing_images', '20', 'number', 'general', 'Maximum images per listing'),
('featured_listing_fee', '0', 'number', 'general', 'Fee for featuring a listing'),
('vat_rate', '15.00', 'number', 'general', 'VAT rate percentage'),
('default_currency', 'ZAR', 'currency', 'general', 'Default platform currency'),
('supported_payment_gateways', '["payfast"]', 'json', 'general', 'Enabled payment gateways'),
('terms_url', '/marketplace/terms', 'text', 'general', 'Terms and conditions URL'),
('seller_registration_open', '1', 'boolean', 'general', 'Allow new seller registrations'),
('guest_enquiries_enabled', '1', 'boolean', 'general', 'Allow guest enquiries without login')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Currencies
INSERT IGNORE INTO marketplace_currency (code, name, symbol, exchange_rate_to_zar, is_active, sort_order) VALUES
('ZAR', 'South African Rand', 'R', 1.000000, 1, 1),
('USD', 'US Dollar', '$', 0.054000, 1, 2),
('EUR', 'Euro', '€', 0.050000, 1, 3),
('GBP', 'British Pound', '£', 0.043000, 1, 4),
('AUD', 'Australian Dollar', 'A$', 0.084000, 1, 5)
ON DUPLICATE KEY UPDATE code = code;

-- Categories: Gallery sector
INSERT IGNORE INTO marketplace_category (sector, name, slug, sort_order) VALUES
('gallery', 'Painting', 'painting', 1),
('gallery', 'Sculpture', 'sculpture', 2),
('gallery', 'Drawing', 'drawing', 3),
('gallery', 'Print', 'print', 4),
('gallery', 'Photography', 'photography', 5),
('gallery', 'Mixed Media', 'mixed-media', 6),
('gallery', 'Textile Art', 'textile-art', 7),
('gallery', 'Ceramics', 'ceramics', 8),
('gallery', 'Glass', 'glass', 9),
('gallery', 'Installation', 'installation', 10),
('gallery', 'Digital Art', 'digital-art', 11),
('gallery', 'Video Art', 'video-art', 12),
('gallery', 'Performance Documentation', 'performance-documentation', 13);

-- Categories: Museum sector
INSERT IGNORE INTO marketplace_category (sector, name, slug, sort_order) VALUES
('museum', 'Reproduction', 'reproduction', 1),
('museum', 'Merchandise', 'merchandise', 2),
('museum', 'Catalog', 'catalog', 3),
('museum', 'Educational Material', 'educational-material', 4),
('museum', 'Deaccessioned Object', 'deaccessioned-object', 5),
('museum', 'Artifact Replica', 'artifact-replica', 6);

-- Categories: Archive sector
INSERT IGNORE INTO marketplace_category (sector, name, slug, sort_order) VALUES
('archive', 'Digital Scan', 'digital-scan', 1),
('archive', 'Research Package', 'research-package', 2),
('archive', 'Publication', 'publication', 3),
('archive', 'Facsimile', 'facsimile', 4),
('archive', 'Image License', 'image-license', 5),
('archive', 'Dataset', 'dataset', 6);

-- Categories: Library sector
INSERT IGNORE INTO marketplace_category (sector, name, slug, sort_order) VALUES
('library', 'Rare Book', 'rare-book', 1),
('library', 'Special Collection', 'special-collection', 2),
('library', 'E-Book', 'e-book', 3),
('library', 'Manuscript Facsimile', 'manuscript-facsimile', 4),
('library', 'Map Reproduction', 'map-reproduction', 5);

-- Categories: DAM sector
INSERT IGNORE INTO marketplace_category (sector, name, slug, sort_order) VALUES
('dam', 'Stock Image', 'stock-image', 1),
('dam', 'Video Clip', 'video-clip', 2),
('dam', 'Audio Recording', 'audio-recording', 3),
('dam', '3D Model', '3d-model', 4),
('dam', 'Design Asset', 'design-asset', 5),
('dam', 'Font License', 'font-license', 6);

SET FOREIGN_KEY_CHECKS = 1;

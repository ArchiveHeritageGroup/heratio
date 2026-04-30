-- ============================================================================
-- ahg-statistics — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgStatisticsPlugin/database/install.sql
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

-- ============================================================
-- ahgStatisticsPlugin - Database Schema
-- Usage Statistics Tracking System
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================







-- ============================================================
-- Table: ahg_usage_event
-- Raw event log for views and downloads
-- Partitioned by date for efficient cleanup
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_usage_event` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(46) NOT NULL DEFAULT 'view' COMMENT 'view, download, search, login, api',
    `object_type` VARCHAR(50) NOT NULL DEFAULT 'information_object',
    `object_id` INT UNSIGNED NOT NULL,
    `digital_object_id` INT UNSIGNED DEFAULT NULL COMMENT 'For download events',
    `repository_id` INT UNSIGNED DEFAULT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL for anonymous',
    `session_id` VARCHAR(64) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6',
    `ip_hash` VARCHAR(64) DEFAULT NULL COMMENT 'Hashed IP for privacy',
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `referer` VARCHAR(1000) DEFAULT NULL,
    `country_code` CHAR(2) DEFAULT NULL,
    `country_name` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `region` VARCHAR(100) DEFAULT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
    `bot_name` VARCHAR(100) DEFAULT NULL,
    `search_query` VARCHAR(500) DEFAULT NULL COMMENT 'For search events',
    `response_time_ms` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `event_date` DATE GENERATED ALWAYS AS (DATE(created_at)) STORED,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_object` (`object_type`, `object_id`),
    KEY `idx_repository` (`repository_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_date` (`event_date`),
    KEY `idx_created` (`created_at`),
    KEY `idx_country` (`country_code`),
    KEY `idx_is_bot` (`is_bot`),
    KEY `idx_daily_agg` (`event_date`, `event_type`, `object_id`, `is_bot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_statistics_daily
-- Pre-aggregated daily statistics for performance
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_statistics_daily` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `event_type` VARCHAR(46) NOT NULL DEFAULT 'view' COMMENT 'view, download, search, login, api',
    `object_type` VARCHAR(50) NOT NULL DEFAULT 'information_object',
    `object_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL for global stats',
    `repository_id` INT UNSIGNED DEFAULT NULL,
    `country_code` CHAR(2) DEFAULT NULL,
    `total_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unique IPs',
    `authenticated_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `bot_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_daily_stat` (`stat_date`, `event_type`, `object_type`, `object_id`, `country_code`),
    KEY `idx_date` (`stat_date`),
    KEY `idx_object` (`object_type`, `object_id`),
    KEY `idx_repository` (`repository_id`),
    KEY `idx_country` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_statistics_monthly
-- Pre-aggregated monthly statistics for long-term trends
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_statistics_monthly` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_year` SMALLINT UNSIGNED NOT NULL,
    `stat_month` TINYINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(46) NOT NULL DEFAULT 'view' COMMENT 'view, download, search, login, api',
    `object_type` VARCHAR(50) NOT NULL DEFAULT 'information_object',
    `object_id` INT UNSIGNED DEFAULT NULL,
    `repository_id` INT UNSIGNED DEFAULT NULL,
    `country_code` CHAR(2) DEFAULT NULL,
    `total_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `peak_day` DATE DEFAULT NULL,
    `peak_count` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_monthly_stat` (`stat_year`, `stat_month`, `event_type`, `object_type`, `object_id`, `country_code`),
    KEY `idx_year_month` (`stat_year`, `stat_month`),
    KEY `idx_object` (`object_type`, `object_id`),
    KEY `idx_repository` (`repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_bot_list
-- Configurable list of bots/spiders to filter
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_bot_list` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `pattern` VARCHAR(255) NOT NULL COMMENT 'Regex pattern to match user agent',
    `category` VARCHAR(67) NOT NULL DEFAULT 'crawler' COMMENT 'search_engine, social, monitoring, crawler, spam, other',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `exclude_from_stats` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exclude from main statistics',
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_statistics_config
-- Plugin configuration settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_statistics_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_name` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` VARCHAR(42) NOT NULL DEFAULT 'string' COMMENT 'string, integer, boolean, json',
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Insert default configuration
-- ============================================================
INSERT IGNORE INTO `ahg_statistics_config` (`setting_name`, `setting_value`, `setting_type`, `description`) VALUES
('retention_days', '90', 'integer', 'Days to keep raw events before cleanup'),
('geoip_enabled', '1', 'boolean', 'Enable GeoIP lookup for location data'),
('geoip_database_path', '/usr/share/GeoIP/GeoLite2-City.mmdb', 'string', 'Path to MaxMind GeoLite2 database'),
('bot_filtering_enabled', '1', 'boolean', 'Enable bot filtering'),
('track_authenticated_only', '0', 'boolean', 'Only track authenticated users'),
('anonymize_ip', '1', 'boolean', 'Hash IP addresses for privacy'),
('exclude_admin_views', '1', 'boolean', 'Exclude admin page views'),
('session_timeout_minutes', '30', 'integer', 'Session timeout for unique visitor counting');

-- ============================================================
-- Insert common bot patterns
-- ============================================================
INSERT IGNORE INTO `ahg_bot_list` (`name`, `pattern`, `category`) VALUES
('Googlebot', 'Googlebot|Google-Site-Verification', 'search_engine'),
('Bingbot', 'bingbot|msnbot', 'search_engine'),
('Yahoo Slurp', 'Yahoo! Slurp|Slurp', 'search_engine'),
('DuckDuckBot', 'DuckDuckBot', 'search_engine'),
('Baiduspider', 'Baiduspider', 'search_engine'),
('Yandex', 'YandexBot|YandexImages', 'search_engine'),
('Facebook', 'facebookexternalhit|Facebot', 'social'),
('Twitter', 'Twitterbot', 'social'),
('LinkedIn', 'LinkedInBot', 'social'),
('Pinterest', 'Pinterest', 'social'),
('Slack', 'Slackbot', 'social'),
('WhatsApp', 'WhatsApp', 'social'),
('Telegram', 'TelegramBot', 'social'),
('Uptime Robot', 'UptimeRobot', 'monitoring'),
('Pingdom', 'Pingdom', 'monitoring'),
('StatusCake', 'StatusCake', 'monitoring'),
('New Relic', 'NewRelicPinger', 'monitoring'),
('Ahrefs', 'AhrefsBot', 'crawler'),
('SEMrush', 'SemrushBot', 'crawler'),
('Majestic', 'MJ12bot', 'crawler'),
('Screaming Frog', 'Screaming Frog', 'crawler'),
('Archive.org', 'archive.org_bot|ia_archiver', 'crawler'),
('Common Crawl', 'CCBot', 'crawler'),
('Python Requests', 'python-requests', 'crawler'),
('curl', '^curl/', 'crawler'),
('wget', '^Wget/', 'crawler'),
('Java', '^Java/', 'crawler'),
('Go http', 'Go-http-client', 'crawler'),
('Scrapy', 'Scrapy', 'crawler'),
('HeadlessChrome', 'HeadlessChrome', 'crawler');






SET FOREIGN_KEY_CHECKS = 1;

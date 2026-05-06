-- Per-user daily cloud-LLM call counter. Closes the
-- voice_daily_cloud_limit half of issue #99.
--
-- One row per (user_id, call_date) - VoiceLLMService::incrementCloudUsage
-- bumps call_count via ON DUPLICATE KEY UPDATE so the upsert is atomic.
-- enforceDailyCloudLimit reads the current day's row and 429s when
-- call_count >= voice_daily_cloud_limit.
--
-- user_id is nullable to allow anonymous / CLI / system calls to share a
-- single bucket (less common, but keeps the limit consistent across
-- session-less entry points).

CREATE TABLE IF NOT EXISTS `voice_usage` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT NULL,
    `call_date`   DATE NOT NULL,
    `call_count`  INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Composite uniqueness so the ON DUPLICATE KEY UPDATE upsert pivots on
    -- the (user, day) pair. user_id of NULL collapses to a single anon row
    -- per day under MySQL's "one NULL" rule for unique indexes — accepted.
    UNIQUE KEY `user_date_unique` (`user_id`, `call_date`),
    KEY `call_date_idx` (`call_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

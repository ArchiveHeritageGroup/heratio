-- ============================================================================
-- ahg-core - cron run tracking (issue #673 Phase 2)
-- ============================================================================
-- Two tables back the cron monitoring layer:
--
--   ahg_cron_run         - one row per scheduled-command invocation. Inserted
--                          at started_at by the tracking decorator, updated
--                          at finished_at by after()/onFailure(). lock_token +
--                          hostname capture the distributed-lock owner when
--                          ->onOneServer() granted execution rights.
--   ahg_cron_missed_run  - flagged by cron:check-missed-runs when the gap
--                          between expected and actual run timestamps
--                          exceeds 2x the cron-expression interval. Used to
--                          drive Workbench notifications + Prometheus
--                          counters; resolved_at is set when a subsequent
--                          successful run lands.
--
-- Both tables are auto-installed by AhgCoreServiceProvider via a
-- Schema::hasTable() probe wrapped in a single outer try/catch (the
-- CI-safe pattern documented in reference_ci_schema_hastable.md).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ahg_cron_run` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `command`       VARCHAR(191) NOT NULL COMMENT 'Artisan command string (full token incl. flags) as scheduled.',
    `started_at`    DATETIME NOT NULL,
    `finished_at`   DATETIME NULL,
    `exit_code`     INT NULL COMMENT 'Symfony exit code; 0 = success, non-zero = failure, NULL = still running.',
    `duration_ms`   INT UNSIGNED NULL,
    `status`        VARCHAR(16) NOT NULL DEFAULT 'running' COMMENT 'running|success|failed|skipped',
    `lock_token`    VARCHAR(64) NULL COMMENT 'Atomic-cache lock token if ->onOneServer() granted the run; NULL when no lock support.',
    `hostname`      VARCHAR(191) NULL,
    `output`        TEXT NULL COMMENT 'Truncated trailing 5000 chars of artisan output (failures only, for diagnosis).',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Idempotency guard: same command twice within the same minute is
    -- treated as a single run (double-fire from overlapping schedulers).
    UNIQUE KEY `command_started_minute_uniq` (`command`, `started_at`),
    KEY `command_finished_idx` (`command`, `finished_at`),
    KEY `started_at_idx` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ahg_cron_missed_run` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `command`       VARCHAR(191) NOT NULL,
    `expected_at`   DATETIME NOT NULL COMMENT 'Most recent expected run derived from the cron expression.',
    `gap_seconds`   INT UNSIGNED NOT NULL COMMENT 'Seconds between expected_at and the latest ahg_cron_run.finished_at for this command.',
    `detected_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at`   DATETIME NULL COMMENT 'Set when a successful run lands after the miss; NULL while still missing.',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- One open miss row per (command, expected_at) — detector is idempotent
    -- across re-runs of cron:check-missed-runs every 5 minutes.
    UNIQUE KEY `command_expected_uniq` (`command`, `expected_at`),
    KEY `unresolved_idx` (`resolved_at`),
    KEY `command_idx` (`command`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

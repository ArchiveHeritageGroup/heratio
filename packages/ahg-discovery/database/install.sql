-- ahg-discovery package install — schemas owned by Discovery.
-- Idempotent: re-run on every boot via the ServiceProvider's seed step.
-- Issue #11.

-- ahg_discovery_simulated_run — one row per (simulated query, ablation config)
-- pair. Lets the paper's Run #1 / Run #2 ablation tables pivot over a stable
-- ground-truth corpus instead of the noisy real-user log.
CREATE TABLE IF NOT EXISTS ahg_discovery_simulated_run (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id               VARCHAR(64)     NOT NULL,
  query_id             VARCHAR(32)     NOT NULL,
  query_text           VARCHAR(500)    NOT NULL,
  query_type           VARCHAR(32)     NOT NULL,
  expected_object_ids  JSON            NOT NULL,
  config               VARCHAR(32)         NULL,
  log_id               BIGINT UNSIGNED     NULL,
  hit_at_k             JSON                NULL,
  result_count         INT                 NULL,
  response_ms          INT                 NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_run         (run_id),
  KEY idx_qtype       (query_type),
  KEY idx_log         (log_id),
  KEY idx_created     (created_at),
  KEY idx_run_config  (run_id, config)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

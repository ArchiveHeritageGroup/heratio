-- ============================================================================
-- ahg-gis — install schema
-- ============================================================================
-- Phase 1 #3 — Heratio standalone install
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- The idempotent index-add helper that this file used to define as a stored
-- procedure now lives in AhgGis\Services\IndexHelper (PHP). The two
-- contact_information indexes that this file used to add via CALL are now
-- added from AhgGisServiceProvider::boot(). PDO can't parse the DELIMITER
-- directive that the previous CREATE PROCEDURE wrapper used. See issue #105.

SET FOREIGN_KEY_CHECKS = 1;

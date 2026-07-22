-- ============================================================================
-- ahg-display - browse title-sort sidecar
-- ============================================================================
-- Sidecar per ADR 0001 (atom base schema read-only sidecar pattern). Nothing
-- here touches a base AtoM table.
--
-- WHY THIS TABLE EXISTS
-- ---------------------
-- Browse sorts alphabetically by information_object_i18n.title. That column is
-- varchar(1024), and the only index covering it (idx_io_i18n_culture_title_id)
-- stores a 191-character PREFIX. MySQL will not use a prefix index to satisfy
-- an ORDER BY - at any prefix length - so every alphabetical browse filesorted
-- the full 454,393-row table. Measured on atom.theahg.co.za: ~5-10s for the
-- main result query, which is essentially the whole cost of the page.
--
-- Widening the existing index cannot fix it: a prefix index is unusable for
-- ordering no matter how long the prefix, and utf8mb4 caps an index key at
-- 3072 bytes / 768 chars, short of the column's 1024. Altering the base table
-- is off-limits by project rule.
--
-- So the sort key is projected into this sidecar at a length that CAN be
-- indexed in full, and browse orders by that instead.
--
-- SHAPE
-- -----
-- Keyed (object_id, culture) because title is per-culture and browse resolves
-- the current culture with a fallback to the object's source_culture - so the
-- stored value is the already-resolved COALESCE, not the raw column.
--
-- idx_iots_culture_title is the whole point: (culture, title_sort, object_id)
-- with NO prefix, so ORDER BY title_sort inside one culture is an index scan.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `information_object_title_sort` (
  `object_id`  INT NOT NULL,
  `culture`    VARCHAR(16) NOT NULL,
  `title_sort` VARCHAR(191) DEFAULT NULL COMMENT 'Resolved title (current culture, falling back to source_culture), truncated to an indexable 191 chars',
  PRIMARY KEY (`object_id`, `culture`),
  KEY `idx_iots_culture_title` (`culture`, `title_sort`, `object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

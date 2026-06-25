-- heratio#1333 - sibling-ordering sidecar for the closure-table migration.
--
-- The Nested Set used lft for sibling order. Closure tables don't carry order,
-- and the AtoM base tables (information_object/term/menu) are read-only, so the
-- replacement ordering lives here in an ahg_* sidecar rather than a new column
-- on the base tables. One row per node, keyed by (entity, node_id); rebuilt
-- from lft by `ahg:build-closure` and maintained going forward by the closure
-- maintenance layer. Regenerable derived data - safe to drop and rebuild.

CREATE TABLE IF NOT EXISTS `ahg_node_sibling_order` (
  `entity` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'information_object | term | menu',
  `node_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `sibling_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`entity`,`node_id`),
  KEY `idx_nso_parent` (`entity`,`parent_id`,`sibling_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

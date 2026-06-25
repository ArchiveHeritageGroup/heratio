-- heratio#1333 - Closure tables for hierarchy (information_object / term / menu).
--
-- Canonical closure-table shape (matches Xercode's published AtoM design so a
-- shared AtoM-schema DB stays portable across the Symfony and Laravel stacks):
--   (ancestor, descendant, depth), with the self-reference row (X, X, 0) as an
--   invariant, UNIQUE(ancestor, descendant) (enforced by the PK), a covering
--   index (ancestor, depth, descendant) for descendant/subtree reads and
--   (descendant, depth) for ancestor/breadcrumb reads, and FKs ON DELETE CASCADE
--   to the base table so node deletes clean up their closure rows automatically.
--
-- These are NEW tables only - the AtoM base tables (information_object, term,
-- menu) are NOT altered (base tables are read-only; lft/rgt stay authoritative
-- until the read/write swap phase). Rebuilt from parent_id by `ahg:build-closure`.

CREATE TABLE IF NOT EXISTS `information_object_closure` (
  `ancestor` int NOT NULL,
  `descendant` int NOT NULL,
  `depth` int NOT NULL,
  PRIMARY KEY (`ancestor`,`descendant`),
  KEY `idx_ioc_anc_depth_desc` (`ancestor`,`depth`,`descendant`),
  KEY `idx_ioc_desc_depth` (`descendant`,`depth`),
  CONSTRAINT `fk_ioc_ancestor` FOREIGN KEY (`ancestor`) REFERENCES `information_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ioc_descendant` FOREIGN KEY (`descendant`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `term_closure` (
  `ancestor` int NOT NULL,
  `descendant` int NOT NULL,
  `depth` int NOT NULL,
  PRIMARY KEY (`ancestor`,`descendant`),
  KEY `idx_tc_anc_depth_desc` (`ancestor`,`depth`,`descendant`),
  KEY `idx_tc_desc_depth` (`descendant`,`depth`),
  CONSTRAINT `fk_tc_ancestor` FOREIGN KEY (`ancestor`) REFERENCES `term` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tc_descendant` FOREIGN KEY (`descendant`) REFERENCES `term` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_closure` (
  `ancestor` int NOT NULL,
  `descendant` int NOT NULL,
  `depth` int NOT NULL,
  PRIMARY KEY (`ancestor`,`descendant`),
  KEY `idx_mc_anc_depth_desc` (`ancestor`,`depth`,`descendant`),
  KEY `idx_mc_desc_depth` (`descendant`,`depth`),
  CONSTRAINT `fk_mc_ancestor` FOREIGN KEY (`ancestor`) REFERENCES `menu` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mc_descendant` FOREIGN KEY (`descendant`) REFERENCES `menu` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

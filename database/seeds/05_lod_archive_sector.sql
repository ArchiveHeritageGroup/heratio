-- Archive-sector Level of Description mapping.
--
-- The archival IO create/edit form (InformationObjectController::
-- getFormDropdowns) restricts the "Level of description" dropdown to the nine
-- canonical ISAD levels via an INNER JOIN on level_of_description_sector WHERE
-- sector = 'archive'. The sector packages (museum/library/dam/gallery) seed
-- their own rows in their install.sql, but NOTHING seeded the ARCHIVE rows, so
-- on a fresh install the archival LoD dropdown was EMPTY (Required field, no
-- options) — blocking IO creation.
--
-- The nine level terms live in taxonomy 34 (seeded by 00_taxonomies.sql, which
-- loads first). We map by NAME, not by id, because term ids are not stable
-- across installs; and we pick MIN(id) per name so that a duplicate term
-- sharing a level name (a sector install.sql can create one before
-- 00_taxonomies runs) does not get a second archive row and double the
-- dropdown. UNIQUE (term_id, sector) + INSERT IGNORE make re-runs no-ops.

INSERT IGNORE INTO `level_of_description_sector` (`term_id`, `sector`, `display_order`)
SELECT m.tid, 'archive', x.display_order
FROM (
            SELECT 'Collection'   AS name, 10 AS display_order
    UNION ALL SELECT 'File',         20
    UNION ALL SELECT 'Fonds',        30
    UNION ALL SELECT 'Item',         40
    UNION ALL SELECT 'Part',         50
    UNION ALL SELECT 'Record group', 60
    UNION ALL SELECT 'Series',       70
    UNION ALL SELECT 'Subfonds',     80
    UNION ALL SELECT 'Subseries',    90
) AS x
JOIN (
    SELECT ti.`name` AS nm, MIN(t.`id`) AS tid
    FROM `term` t
    JOIN `term_i18n` ti ON ti.`id` = t.`id` AND ti.`culture` = 'en'
    WHERE t.`taxonomy_id` = 34
    GROUP BY ti.`name`
) AS m ON m.nm = x.name;

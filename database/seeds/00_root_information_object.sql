-- Root information_object node (AtoM convention: id = 1, the tree root).
--
-- Every InformationObject factory / real child IO sets parent_id =
-- InformationObject::ROOT_ID (= 1) and the information_object_FK_5 FK requires
-- that parent row to exist. The base schema + package installs + other seeds do
-- NOT create it, so in the CI test DB every IO insert failed with
-- "1452 ... information_object_FK_5 (parent_id) REFERENCES information_object(id)"
-- (the dominant remaining #1137 failure cluster). This seeds the root node the
-- same way the live database has it (object 1 = QubitInformationObject;
-- information_object 1 with parent_id NULL, lft=1, rgt large, NULL i18n titles).
--
-- Loaded early (00_) and idempotent (INSERT IGNORE) so it is a no-op where the
-- row already exists. Heratio-wide; needed by any environment built from
-- schema + seeds rather than a full data import.

-- object row (class-table-inheritance parent of information_object)
INSERT IGNORE INTO `object` (`id`, `class_name`, `serial_number`, `created_at`, `updated_at`)
VALUES (1, 'QubitInformationObject', 0, NOW(), NOW());

-- the root information_object: no parent, MPTT root bounds, no level/repository
INSERT IGNORE INTO `information_object`
    (`id`, `parent_id`, `lft`, `rgt`, `source_culture`)
VALUES (1, NULL, 1, 1542, 'en');

-- i18n shells (titles intentionally NULL, mirroring the live root node)
INSERT IGNORE INTO `information_object_i18n` (`id`, `culture`, `title`)
VALUES
    (1, 'en', NULL),
    (1, 'fr', NULL),
    (1, 'nl', NULL),
    (1, 'pt', NULL);

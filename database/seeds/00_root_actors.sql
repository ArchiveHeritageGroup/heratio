-- Root actor + repository nodes (AtoM convention: QubitActor root id=3, nested
-- under the QubitRepository root id=6).
--
-- Every donor / actor / authority-record insert sets actor.parent_id =
-- QubitActor::ROOT_ID (= 3); every repository sets parent_id = 6. The actor_FK_5
-- self-FK requires those parent rows to exist, so without them a fresh install
-- 500s on the first /donor/add, /actor/add or /repository/add with
-- "1452 ... actor_FK_5 (parent_id) REFERENCES actor(id)".
--
-- Mirrors the live database: object 3 = QubitActor (parent = actor 6), object 6
-- = QubitRepository (tree root, parent NULL). Loaded at 00_ (before 02_menus) and
-- idempotent (INSERT IGNORE) so the canonical class_name wins the object id and
-- the seed is a no-op where the rows already exist. Heratio-wide; needed by any
-- environment built from schema + seeds rather than a full data import.

-- object rows (class-table-inheritance parents of actor / repository)
INSERT IGNORE INTO `object` (`id`, `class_name`, `serial_number`, `created_at`, `updated_at`)
VALUES
    (6, 'QubitRepository', 0, NOW(), NOW()),
    (3, 'QubitActor', 0, NOW(), NOW());

-- actor tree: repository root (6, no parent) first, then the actor root (3, child
-- of 6) so the self-FK on parent_id is satisfied row-by-row.
INSERT IGNORE INTO `actor` (`id`, `parent_id`, `source_culture`)
VALUES
    (6, NULL, 'en'),
    (3, 6, 'en');

-- i18n shells (names intentionally NULL, mirroring the live root nodes)
INSERT IGNORE INTO `actor_i18n` (`id`, `culture`, `authorized_form_of_name`)
VALUES
    (6, 'en', NULL),
    (3, 'en', NULL);

-- repository root detail row
INSERT IGNORE INTO `repository` (`id`, `source_culture`)
VALUES (6, 'en');

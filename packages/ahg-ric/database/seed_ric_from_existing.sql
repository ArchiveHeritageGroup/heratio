-- ============================================================
-- Seed RiC entities from existing AtoM data
-- Run AFTER install_ric_entities.sql
-- Uses stored procedures for reliable ID tracking
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //

-- ============================================================
-- 1. Events → RiC Activities
-- ============================================================
DROP PROCEDURE IF EXISTS `_seed_ric_activities`//
CREATE PROCEDURE `_seed_ric_activities`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_event_id INT;
    DECLARE v_type_id INT;
    DECLARE v_start_date DATE;
    DECLARE v_end_date DATE;
    DECLARE v_source_culture VARCHAR(16);
    DECLARE v_object_id INT;
    DECLARE v_io_id INT;
    DECLARE v_actor_id INT;
    DECLARE v_name VARCHAR(1024);
    DECLARE v_description TEXT;
    DECLARE v_date_display VARCHAR(1024);
    DECLARE v_activity_type VARCHAR(50);
    DECLARE v_rel_object_id INT;

    DECLARE cur CURSOR FOR
        SELECT e.id, e.type_id, e.start_date, e.end_date,
               COALESCE(e.source_culture, 'en'), e.object_id, e.actor_id,
               ei.name, ei.description, ei.date
        FROM `event` e
        LEFT JOIN `event_i18n` ei ON e.id = ei.id AND ei.culture = COALESCE(e.source_culture, 'en')
        WHERE NOT EXISTS (SELECT 1 FROM `ric_activity` ra
            JOIN `slug` s ON s.object_id = ra.id
            WHERE s.slug = CONCAT('ric-activity-from-event-', e.id));
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_event_id, v_type_id, v_start_date, v_end_date,
                        v_source_culture, v_io_id, v_actor_id,
                        v_name, v_description, v_date_display;
        IF done THEN LEAVE read_loop; END IF;

        -- Map event type to RiC activity type
        SET v_activity_type = CASE v_type_id
            WHEN 111 THEN 'production'
            WHEN 118 THEN 'accumulation'
            ELSE 'custody'
        END;

        -- Create object row
        INSERT INTO `object` (`class_name`, `created_at`, `updated_at`, `serial_number`)
        VALUES ('RicActivity', NOW(), NOW(), 0);
        SET v_object_id = LAST_INSERT_ID();

        -- Create ric_activity
        INSERT INTO `ric_activity` (`id`, `type_id`, `start_date`, `end_date`, `place_id`, `source_culture`)
        VALUES (v_object_id, v_activity_type, v_start_date, v_end_date, NULL, v_source_culture);

        -- Create ric_activity_i18n
        INSERT INTO `ric_activity_i18n` (`id`, `culture`, `name`, `description`, `date_display`)
        VALUES (v_object_id, v_source_culture,
                COALESCE(v_name, CASE v_type_id WHEN 111 THEN 'Creation' WHEN 118 THEN 'Accumulation' ELSE 'Activity' END),
                v_description, v_date_display);

        -- Create slug (traceable back to source event)
        INSERT INTO `slug` (`object_id`, `slug`, `serial_number`)
        VALUES (v_object_id, CONCAT('ric-activity-from-event-', v_event_id), 0);

        -- Create relation: activity → IO (if event has object_id)
        IF v_io_id IS NOT NULL THEN
            INSERT INTO `object` (`class_name`, `created_at`, `updated_at`, `serial_number`)
            VALUES ('QubitRelation', NOW(), NOW(), 0);
            SET v_rel_object_id = LAST_INSERT_ID();

            INSERT INTO `relation` (`id`, `subject_id`, `object_id`, `type_id`, `start_date`, `end_date`, `source_culture`)
            VALUES (v_rel_object_id, v_object_id, v_io_id, NULL, v_start_date, v_end_date, 'en');

            INSERT INTO `ric_relation_meta` (`relation_id`, `rico_predicate`, `inverse_predicate`, `domain_class`, `range_class`, `dropdown_code`)
            VALUES (v_rel_object_id, 'rico:resultsOrResultedIn', 'rico:resultsOrResultedFrom', 'Activity', 'Record', 'results_from');
        END IF;

        -- Create relation: activity → actor (if event has actor_id)
        IF v_actor_id IS NOT NULL THEN
            INSERT INTO `object` (`class_name`, `created_at`, `updated_at`, `serial_number`)
            VALUES ('QubitRelation', NOW(), NOW(), 0);
            SET v_rel_object_id = LAST_INSERT_ID();

            INSERT INTO `relation` (`id`, `subject_id`, `object_id`, `type_id`, `start_date`, `end_date`, `source_culture`)
            VALUES (v_rel_object_id, v_object_id, v_actor_id, NULL, v_start_date, v_end_date, 'en');

            INSERT INTO `ric_relation_meta` (`relation_id`, `rico_predicate`, `inverse_predicate`, `domain_class`, `range_class`, `dropdown_code`)
            VALUES (v_rel_object_id, 'rico:isOrWasPerformedBy', 'rico:performsOrPerformed', 'Activity', 'Agent', 'performed_by');
        END IF;

    END LOOP;
    CLOSE cur;
END//

-- ============================================================
-- 2. Digital Objects → RiC Instantiations
-- ============================================================
DROP PROCEDURE IF EXISTS `_seed_ric_instantiations`//
CREATE PROCEDURE `_seed_ric_instantiations`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_do_id INT;
    DECLARE v_io_id INT;
    DECLARE v_mime_type VARCHAR(255);
    DECLARE v_name VARCHAR(1024);
    DECLARE v_byte_size BIGINT;
    DECLARE v_checksum VARCHAR(255);
    DECLARE v_checksum_type VARCHAR(50);
    DECLARE v_object_id INT;
    DECLARE v_carrier VARCHAR(50);

    DECLARE cur CURSOR FOR
        SELECT d.id, d.object_id, d.mime_type, d.name, d.byte_size, d.checksum, d.checksum_type
        FROM `digital_object` d
        WHERE NOT EXISTS (SELECT 1 FROM `ric_instantiation` ri WHERE ri.digital_object_id = d.id);
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_do_id, v_io_id, v_mime_type, v_name, v_byte_size, v_checksum, v_checksum_type;
        IF done THEN LEAVE read_loop; END IF;

        -- Determine carrier type from MIME
        SET v_carrier = CASE
            WHEN v_mime_type LIKE 'video/%' THEN 'video'
            WHEN v_mime_type LIKE 'audio/%' THEN 'audio'
            ELSE 'digital'
        END;

        -- Create object row
        INSERT INTO `object` (`class_name`, `created_at`, `updated_at`, `serial_number`)
        VALUES ('RicInstantiation', NOW(), NOW(), 0);
        SET v_object_id = LAST_INSERT_ID();

        -- Create ric_instantiation
        INSERT INTO `ric_instantiation` (`id`, `record_id`, `carrier_type`, `mime_type`, `extent_value`, `extent_unit`, `digital_object_id`, `source_culture`)
        VALUES (v_object_id, v_io_id, v_carrier, v_mime_type, v_byte_size, 'bytes', v_do_id, 'en');

        -- Create ric_instantiation_i18n
        INSERT INTO `ric_instantiation_i18n` (`id`, `culture`, `title`, `description`, `technical_characteristics`, `production_technical_characteristics`)
        VALUES (v_object_id, 'en', v_name,
                CONCAT('MIME: ', COALESCE(v_mime_type, 'unknown'),
                       IF(v_byte_size IS NOT NULL, CONCAT(' | Size: ', ROUND(v_byte_size / 1024, 1), ' KB'), '')),
                IF(v_checksum IS NOT NULL,
                   CONCAT('Checksum (', COALESCE(v_checksum_type, 'unknown'), '): ', v_checksum),
                   NULL),
                NULL);

        -- Create slug
        INSERT INTO `slug` (`object_id`, `slug`, `serial_number`)
        VALUES (v_object_id, CONCAT('ric-instantiation-', v_object_id), 0);

    END LOOP;
    CLOSE cur;
END//

DELIMITER ;

-- Execute the procedures
CALL `_seed_ric_activities`();
CALL `_seed_ric_instantiations`();

-- Clean up procedures
DROP PROCEDURE IF EXISTS `_seed_ric_activities`;
DROP PROCEDURE IF EXISTS `_seed_ric_instantiations`;

-- ============================================================
-- 3. Relation Meta Overlay on existing AtoM relations
-- ============================================================
INSERT IGNORE INTO `ric_relation_meta` (`relation_id`, `rico_predicate`, `inverse_predicate`, `domain_class`, `range_class`, `dropdown_code`)
SELECT
    r.id,
    CASE r.type_id
        WHEN 147 THEN 'rico:hasInstantiation'
        WHEN 161 THEN 'rico:hasOrHadSubject'
        WHEN 167 THEN 'rico:isAssociatedWith'
        WHEN 168 THEN 'rico:isOrWasRegulatedBy'
        WHEN 176 THEN 'rico:hasGeneticLinkToRecordResource'
        WHEN 177 THEN 'rico:isAssociatedWith'
        ELSE 'rico:isAssociatedWith'
    END,
    CASE r.type_id
        WHEN 147 THEN 'rico:isInstantiationOf'
        WHEN 161 THEN 'rico:isOrWasSubjectOf'
        WHEN 167 THEN 'rico:isAssociatedWith'
        WHEN 168 THEN 'rico:regulatesOrRegulated'
        WHEN 176 THEN 'rico:hasGeneticLinkToRecordResource'
        WHEN 177 THEN 'rico:isAssociatedWith'
        ELSE 'rico:isAssociatedWith'
    END,
    NULL,
    NULL,
    CASE r.type_id
        WHEN 147 THEN 'has_instantiation'
        WHEN 161 THEN 'has_or_had_subject'
        WHEN 167 THEN 'is_associated_with'
        WHEN 168 THEN 'is_regulated_by'
        WHEN 176 THEN 'has_genetic_link_to'
        WHEN 177 THEN 'is_associated_with'
        ELSE 'is_associated_with'
    END
FROM `relation` r
WHERE NOT EXISTS (
    SELECT 1 FROM `ric_relation_meta` rm WHERE rm.relation_id = r.id
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Verify:
--   SELECT 'activities' AS t, COUNT(*) FROM ric_activity
--   UNION ALL SELECT 'instantiations', COUNT(*) FROM ric_instantiation
--   UNION ALL SELECT 'relation_meta', COUNT(*) FROM ric_relation_meta;
-- ============================================================

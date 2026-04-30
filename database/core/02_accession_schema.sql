-- ============================================================================
-- Heratio standalone — accession schema (qtAccessionPlugin)
-- ============================================================================
-- Captured from /usr/share/nginx/archive/data/sql/plugins.qtAccessionPlugin.lib.model.schema.sql (AtoM 2.7.x) at 2026-04-30.
-- Heratio-owned standalone install schema. Idempotent — safe to re-run.
-- All DROPs removed; every CREATE TABLE is IF NOT EXISTS so overlay
-- deployments (where AtoM already created these tables) re-run as no-ops.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;


# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.

#-----------------------------------------------------------------------------
#-- accession
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `accession`
(
	`id` INTEGER  NOT NULL,
	`acquisition_type_id` INTEGER,
	`date` DATE,
	`identifier` VARCHAR(255),
	`processing_priority_id` INTEGER,
	`processing_status_id` INTEGER,
	`resource_type_id` INTEGER,
	`created_at` DATETIME  NOT NULL,
	`updated_at` DATETIME  NOT NULL,
	`source_culture` VARCHAR(16)  NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `accession_U_1` (`identifier`),
	CONSTRAINT `accession_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `object` (`id`)
		ON DELETE CASCADE,
	INDEX `accession_FI_2` (`acquisition_type_id`),
	CONSTRAINT `accession_FK_2`
		FOREIGN KEY (`acquisition_type_id`)
		REFERENCES `term` (`id`)
		ON DELETE SET NULL,
	INDEX `accession_FI_3` (`processing_priority_id`),
	CONSTRAINT `accession_FK_3`
		FOREIGN KEY (`processing_priority_id`)
		REFERENCES `term` (`id`)
		ON DELETE SET NULL,
	INDEX `accession_FI_4` (`processing_status_id`),
	CONSTRAINT `accession_FK_4`
		FOREIGN KEY (`processing_status_id`)
		REFERENCES `term` (`id`)
		ON DELETE SET NULL,
	INDEX `accession_FI_5` (`resource_type_id`),
	CONSTRAINT `accession_FK_5`
		FOREIGN KEY (`resource_type_id`)
		REFERENCES `term` (`id`)
		ON DELETE SET NULL
)Engine=InnoDB;

#-----------------------------------------------------------------------------
#-- accession_i18n
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `accession_i18n`
(
	`appraisal` TEXT,
	`archival_history` TEXT,
	`location_information` TEXT,
	`physical_characteristics` TEXT,
	`processing_notes` TEXT,
	`received_extent_units` TEXT,
	`scope_and_content` TEXT,
	`source_of_acquisition` TEXT,
	`title` VARCHAR(255),
	`id` INTEGER  NOT NULL,
	`culture` VARCHAR(16)  NOT NULL,
	PRIMARY KEY (`id`,`culture`),
	CONSTRAINT `accession_i18n_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `accession` (`id`)
		ON DELETE CASCADE
)Engine=InnoDB;

#-----------------------------------------------------------------------------
#-- accession_event
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `accession_event`
(
	`id` INTEGER  NOT NULL,
	`type_id` INTEGER,
	`accession_id` INTEGER,
	`date` DATE,
	`source_culture` VARCHAR(16)  NOT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `accession_event_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `object` (`id`)
		ON DELETE CASCADE,
	INDEX `accession_event_FI_2` (`type_id`),
	CONSTRAINT `accession_event_FK_2`
		FOREIGN KEY (`type_id`)
		REFERENCES `term` (`id`)
		ON DELETE SET NULL,
	INDEX `accession_event_FI_3` (`accession_id`),
	CONSTRAINT `accession_event_FK_3`
		FOREIGN KEY (`accession_id`)
		REFERENCES `accession` (`id`)
		ON DELETE CASCADE
)Engine=InnoDB;

#-----------------------------------------------------------------------------
#-- accession_event_i18n
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `accession_event_i18n`
(
	`agent` VARCHAR(255),
	`id` INTEGER  NOT NULL,
	`culture` VARCHAR(16)  NOT NULL,
	PRIMARY KEY (`id`,`culture`),
	CONSTRAINT `accession_event_i18n_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `accession_event` (`id`)
		ON DELETE CASCADE
)Engine=InnoDB;

#-----------------------------------------------------------------------------
#-- deaccession
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `deaccession`
(
	`id` INTEGER  NOT NULL,
	`accession_id` INTEGER,
	`date` DATE,
	`identifier` VARCHAR(255),
	`scope_id` INTEGER,
	`created_at` DATETIME  NOT NULL,
	`updated_at` DATETIME  NOT NULL,
	`source_culture` VARCHAR(16)  NOT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `deaccession_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `object` (`id`)
		ON DELETE CASCADE,
	INDEX `deaccession_FI_2` (`accession_id`),
	CONSTRAINT `deaccession_FK_2`
		FOREIGN KEY (`accession_id`)
		REFERENCES `accession` (`id`)
		ON DELETE CASCADE,
	INDEX `deaccession_FI_3` (`scope_id`),
	CONSTRAINT `deaccession_FK_3`
		FOREIGN KEY (`scope_id`)
		REFERENCES `term` (`id`)
		ON DELETE SET NULL
)Engine=InnoDB;

#-----------------------------------------------------------------------------
#-- deaccession_i18n
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `deaccession_i18n`
(
	`description` TEXT,
	`extent` TEXT,
	`reason` TEXT,
	`id` INTEGER  NOT NULL,
	`culture` VARCHAR(16)  NOT NULL,
	PRIMARY KEY (`id`,`culture`),
	CONSTRAINT `deaccession_i18n_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `deaccession` (`id`)
		ON DELETE CASCADE
)Engine=InnoDB;

#-----------------------------------------------------------------------------
#-- donor
#-----------------------------------------------------------------------------



CREATE TABLE IF NOT EXISTS `donor`
(
	`id` INTEGER  NOT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `donor_FK_1`
		FOREIGN KEY (`id`)
		REFERENCES `actor` (`id`)
		ON DELETE CASCADE
)Engine=InnoDB;

# This restores the fkey checks, after having unset them earlier

SET FOREIGN_KEY_CHECKS = 1;

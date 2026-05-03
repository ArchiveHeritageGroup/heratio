-- ahg_dropdown_i18n — per-culture label table for AHG sidecar dropdown values.
--
-- Issue #59 Phase 1.1. Mirrors the museum_metadata_i18n pattern from #56.
-- Parent table ahg_dropdown is unchanged (its `label` column stays as the en
-- source-culture cache and safety-net fallback). Read-paths use a COALESCE
-- across i18n[current_culture] -> i18n['en'] -> ahg_dropdown.label so installs
-- without this table render the parent label unchanged.
--
-- AGPL — Johan Pieterse / Plain Sailing Information Systems

CREATE TABLE IF NOT EXISTS ahg_dropdown_i18n (
    id      INT          NOT NULL,
    culture VARCHAR(16)  NOT NULL,
    label   VARCHAR(255) NOT NULL,
    PRIMARY KEY (id, culture),
    CONSTRAINT ahg_dropdown_i18n_FK_1
        FOREIGN KEY (id) REFERENCES ahg_dropdown(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

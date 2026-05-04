-- registry_dropdown_i18n - per-culture label table for the Registry dropdown
-- values (vendor / institution / software / standard / blog / discussion / etc.).
--
-- Issue #59 Phase 1.1 (registry side). Mirrors ahg_dropdown_i18n which mirrors
-- the museum_metadata_i18n pattern from #56. Parent table registry_dropdown is
-- unchanged (its `label` column stays as the en source-culture cache and
-- safety-net fallback). Read-paths use a COALESCE across i18n[current_culture]
-- -> i18n[en] -> registry_dropdown.label so installs without this table render
-- the parent label unchanged.
--
-- AGPL - Johan Pieterse / Plain Sailing Information Systems

CREATE TABLE IF NOT EXISTS registry_dropdown_i18n (
    id      BIGINT UNSIGNED NOT NULL,
    culture VARCHAR(16)     NOT NULL,
    label   VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id, culture),
    CONSTRAINT registry_dropdown_i18n_FK_1
        FOREIGN KEY (id) REFERENCES registry_dropdown(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

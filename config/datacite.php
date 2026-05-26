<?php

/*
 * DataCite integration config.
 *
 * Issue #654 Phase 3. Sets test_mode for the Events API client; when true,
 * DataciteEventsService submits to https://api.test.datacite.org/events
 * (sandbox), otherwise https://api.datacite.org/events (production).
 *
 * test_mode also short-circuits the legacy ahg_doi_config.environment
 * column on first-read, so an operator can flip the whole DOI integration
 * to sandbox via env() without touching the DB.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

return [
    'test_mode' => (bool) env('DATACITE_TEST_MODE', false),
];

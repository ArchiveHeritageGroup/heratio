-- ==========================================================================
-- AHG Authority Resolution Engine - lookup source settings seed (Task 6).
-- Seven external authority sources for pre-fill on "Create new authority":
--   viaf, wikidata, geonames, tgn, gnd, isni, sagnc
-- Per source we seed five rows: enabled, rate_limit, cache_ttl,
-- license_note, license_url. Plus a single precedence list and a few
-- ancillary keys (geonames username, http timeout, etc).
--
-- DEFAULT POSTURE: every source defaults to DISABLED. Admin must opt in via
-- the settings page (or by editing ahg_settings). External HTTP only fires
-- when enabled=1. SAGNC and GND/ISNI/TGN ship as adapters but are STUBS -
-- enabling them is harmless; they return [] until a real endpoint is wired.
--
-- INSERT IGNORE so re-running the seed is idempotent. setting_key is UNIQUE.
-- ==========================================================================

-- ----- VIAF ---------------------------------------------------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.viaf.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                        'VIAF lookup on (1) or off (0). Default off; admin opts in.', 0, 0, NOW(), NOW()),
('lookup.viaf.rate_limit',    'authority_resolution_lookup', 'int',    '60',                                                                       'VIAF max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.viaf.cache_ttl',     'authority_resolution_lookup', 'int',    '604800',                                                                   'VIAF cache TTL (seconds). Default 7 days.', 0, 0, NOW(), NOW()),
('lookup.viaf.license_note',  'authority_resolution_lookup', 'string', 'VIAF data is dedicated to the public domain under CC0 1.0 Universal.',     'Licence note shown next to pre-filled fields sourced from VIAF.', 0, 0, NOW(), NOW()),
('lookup.viaf.license_url',   'authority_resolution_lookup', 'string', 'https://creativecommons.org/publicdomain/zero/1.0/',                       'URL for the VIAF licence.', 0, 0, NOW(), NOW());

-- ----- Wikidata -----------------------------------------------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.wikidata.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                'Wikidata lookup on/off. Default off.', 0, 0, NOW(), NOW()),
('lookup.wikidata.rate_limit',    'authority_resolution_lookup', 'int',    '120',                                                              'Wikidata max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.wikidata.cache_ttl',     'authority_resolution_lookup', 'int',    '604800',                                                           'Wikidata cache TTL (seconds). Default 7 days.', 0, 0, NOW(), NOW()),
('lookup.wikidata.license_note',  'authority_resolution_lookup', 'string', 'Wikidata structured data is released under CC0 1.0 Universal.',    'Licence note for pre-filled fields sourced from Wikidata.', 0, 0, NOW(), NOW()),
('lookup.wikidata.license_url',   'authority_resolution_lookup', 'string', 'https://creativecommons.org/publicdomain/zero/1.0/',               'URL for the Wikidata licence.', 0, 0, NOW(), NOW());

-- ----- GeoNames -----------------------------------------------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.geonames.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                'GeoNames lookup on/off. Default off.', 0, 0, NOW(), NOW()),
('lookup.geonames.rate_limit',    'authority_resolution_lookup', 'int',    '60',                                                               'GeoNames max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.geonames.cache_ttl',     'authority_resolution_lookup', 'int',    '604800',                                                           'GeoNames cache TTL (seconds). Default 7 days.', 0, 0, NOW(), NOW()),
('lookup.geonames.license_note',  'authority_resolution_lookup', 'string', 'GeoNames data is licensed under CC BY 4.0.',                       'Licence note for pre-filled fields sourced from GeoNames.', 0, 0, NOW(), NOW()),
('lookup.geonames.license_url',   'authority_resolution_lookup', 'string', 'https://creativecommons.org/licenses/by/4.0/',                     'URL for the GeoNames licence.', 0, 0, NOW(), NOW()),
('lookup.geonames.username',      'authority_resolution_lookup', 'string', 'demo',                                                             'GeoNames username (required by the API). Replace ''demo'' with your own registered username before going live.', 0, 0, NOW(), NOW());

-- ----- TGN (Getty Thesaurus of Geographic Names) - STUB -------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.tgn.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                  'Getty TGN lookup on/off. Default off. Adapter is currently a stub.', 0, 0, NOW(), NOW()),
('lookup.tgn.rate_limit',    'authority_resolution_lookup', 'int',    '30',                                                                 'Getty TGN max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.tgn.cache_ttl',     'authority_resolution_lookup', 'int',    '1209600',                                                            'Getty TGN cache TTL (seconds). Default 14 days.', 0, 0, NOW(), NOW()),
('lookup.tgn.license_note',  'authority_resolution_lookup', 'string', 'Getty TGN is licensed under ODC-BY 1.0; attribution to the Getty Research Institute required.', 'Licence note for Getty TGN.', 0, 0, NOW(), NOW()),
('lookup.tgn.license_url',   'authority_resolution_lookup', 'string', 'https://www.getty.edu/research/tools/vocabularies/obtain/license.html', 'URL for the Getty TGN licence.', 0, 0, NOW(), NOW());

-- ----- GND (Integrated Authority File, DNB) - STUB ------------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.gnd.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                  'GND (Deutsche Nationalbibliothek) lookup on/off. Default off. Adapter is currently a stub.', 0, 0, NOW(), NOW()),
('lookup.gnd.rate_limit',    'authority_resolution_lookup', 'int',    '60',                                                                 'GND max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.gnd.cache_ttl',     'authority_resolution_lookup', 'int',    '604800',                                                             'GND cache TTL (seconds). Default 7 days.', 0, 0, NOW(), NOW()),
('lookup.gnd.license_note',  'authority_resolution_lookup', 'string', 'GND metadata is released under CC0 1.0 Universal by the DNB.',       'Licence note for GND.', 0, 0, NOW(), NOW()),
('lookup.gnd.license_url',   'authority_resolution_lookup', 'string', 'https://creativecommons.org/publicdomain/zero/1.0/',                 'URL for the GND licence.', 0, 0, NOW(), NOW());

-- ----- ISNI - STUB --------------------------------------------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.isni.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                 'ISNI lookup on/off. Default off. Adapter is currently a stub.', 0, 0, NOW(), NOW()),
('lookup.isni.rate_limit',    'authority_resolution_lookup', 'int',    '60',                                                                'ISNI max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.isni.cache_ttl',     'authority_resolution_lookup', 'int',    '604800',                                                            'ISNI cache TTL (seconds). Default 7 days.', 0, 0, NOW(), NOW()),
('lookup.isni.license_note',  'authority_resolution_lookup', 'string', 'ISNI records carry the ODC-BY 1.0 Attribution Licence.',            'Licence note for ISNI.', 0, 0, NOW(), NOW()),
('lookup.isni.license_url',   'authority_resolution_lookup', 'string', 'https://opendatacommons.org/licenses/by/1-0/',                      'URL for the ISNI licence.', 0, 0, NOW(), NOW());

-- ----- SAGNC (South African Geographical Names Council) - STUB -----------
-- Placeholder for South African jurisdictional source. Other markets
-- (e.g. Brazil IBGE, Australia Gazetteer) can add their own adapter classes
-- following the same pattern under src/Services/Lookup/Adapters/.
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.sagnc.enabled',       'authority_resolution_lookup', 'bool',   '0',                                                                 'SAGNC lookup on/off. Default off. Adapter is a stub; endpoint TBD.', 0, 0, NOW(), NOW()),
('lookup.sagnc.rate_limit',    'authority_resolution_lookup', 'int',    '30',                                                                'SAGNC max calls per minute.', 0, 0, NOW(), NOW()),
('lookup.sagnc.cache_ttl',     'authority_resolution_lookup', 'int',    '2592000',                                                           'SAGNC cache TTL (seconds). Default 30 days.', 0, 0, NOW(), NOW()),
('lookup.sagnc.license_note',  'authority_resolution_lookup', 'string', 'SAGNC Crown Copyright (Republic of South Africa). Re-use terms TBD.', 'Licence note for SAGNC. Heratio is international; SAGNC is one of many jurisdictional gazetteers.', 0, 0, NOW(), NOW()),
('lookup.sagnc.license_url',   'authority_resolution_lookup', 'string', 'https://www.gov.za/services/services-organisations/south-african-geographical-names-council', 'URL for SAGNC.', 0, 0, NOW(), NOW());

-- ----- Cross-source settings ---------------------------------------------
INSERT IGNORE INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description, is_sensitive, is_locked, created_at, updated_at) VALUES
('lookup.precedence', 'authority_resolution_lookup', 'json', '["viaf","wikidata","geonames","tgn","gnd","isni","sagnc"]', 'JSON array. When several sources contribute to the same field, the highest-precedence source wins. Edit to re-rank.', 0, 0, NOW(), NOW()),
('lookup.http_timeout', 'authority_resolution_lookup', 'int', '8', 'HTTP timeout (seconds) for any external lookup call. Adapter wraps in try/catch; timeouts produce empty results, not 500s.', 0, 0, NOW(), NOW()),
('lookup.field_provenance_graph_uri', 'authority_resolution_lookup', 'string', 'urn:heratio:auth-res:graph:field-provenance', 'Fuseki named graph URI for per-field source provenance triples on newly created authorities.', 0, 0, NOW(), NOW());

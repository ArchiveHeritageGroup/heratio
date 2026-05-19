# External authority sources

The lookup engine ships with **seven** external adapters. Heratio targets
the **international** GLAM market: SAGNC is one regional adapter among
many. All adapters share the same interface and respect the same rate-limit
+ cache contract.

## Adapter table

| Source       | Provider                           | Entity types       | Licence                                              | Status | Endpoint                                                       |
|-------------|------------------------------------|--------------------|------------------------------------------------------|--------|----------------------------------------------------------------|
| **VIAF**     | OCLC Virtual Internet Auth. File   | PERSON, ORG        | OCLC ODC-By                                          | live   | `https://viaf.org/viaf/search`                                 |
| **Wikidata** | Wikimedia Foundation               | PERSON, ORG, PLACE | CC0                                                  | live   | `https://www.wikidata.org/w/api.php`                           |
| **GeoNames** | GeoNames.org                       | PLACE              | CC BY 4.0 (free tier: 1000 req/hr/user)              | live   | `http://api.geonames.org/search` (requires `username`)         |
| **TGN**      | Getty Thesaurus of Geographic Names| PLACE              | ODC-By 1.0                                           | live   | `http://vocabsservices.getty.edu/TGNService.asmx/TGNSearch`    |
| **GND**      | Deutsche Nationalbibliothek        | PERSON, ORG, PLACE | CC0                                                  | live   | `https://lobid.org/gnd/search`                                 |
| **ISNI**     | ISO 27729 / OCLC                   | PERSON, ORG        | Free for non-commercial; commercial use needs licence| live   | `https://isni.oclc.org/sru/`                                   |
| **SAGNC**    | South African Geographical Names Council | PLACE        | Crown copyright (request access)                     | stub   | (no public API; planned scrape adapter)                        |

## What each adapter returns

The `LookupAdapterInterface` contract:

```php
public function supports(string $entityType): bool;
public function search(string $query, string $entityType): array;
public function getName(): string;
public function getRateLimit(): int;       // per minute
public function getTtlSeconds(): int;      // cache TTL
public function getLicenseNote(): string;
```

`search()` returns a normalised array shape:

```php
[
    'source'        => 'viaf',
    'authority_id'  => 'https://viaf.org/viaf/123456789',
    'display_name'  => 'Mzilikazi, ca. 1790-1868',
    'dates'         => ['birth' => '1790', 'death' => '1868'],
    'places'        => ['Zululand', 'Matabeleland'],
    'identifiers'   => ['viaf' => '123456789', 'wikidata' => 'Q1234567'],
    'raw_payload'   => [...],   // adapter-specific
    'license_note'  => 'OCLC ODC-By',
]
```

## Enabling an adapter

Each adapter is **disabled** by default. Flip on per deployment:

```sql
INSERT INTO ahg_settings (setting_key, setting_group, setting_type, setting_value, description)
VALUES
  ('lookup.viaf.enabled',    'lookup', 'bool', '1', 'Enable VIAF lookup adapter')
ON DUPLICATE KEY UPDATE setting_value='1';
```

Or via the admin UI:

- Heratio: `/admin/authority-resolution/settings` (lookup tab)
- AtoM:    `/;authorityResolution/settings`

After flipping, the adapter is picked up on the next request - no restart
needed.

## SAGNC notes

The SA Geographical Names Council does not (yet) publish an open API. The
`SagncAdapter` exists as a stub so the provenance graph URIs and the UI
copy line up; in production the adapter is wired to a periodic scrape of
the public gazetteer, cached in `ahg_authority_lookup_cache` like every
other source. **Do not** treat SAGNC as the canonical source for non-SA
place names - prefer GeoNames + TGN for international coverage.

## Adding a new adapter

1. Create `src/Services/Lookup/Adapters/<Name>Adapter.php` implementing
   `LookupAdapterInterface` (extend `AbstractLookupAdapter` for the
   rate-limit + cache wiring).
2. Register the singleton in `AhgAuthorityResolutionServiceProvider::register()`.
3. Add to the `PrefillEngine` adapter list (same provider).
4. Seed default `ahg_settings` rows in `database/seed_lookup_settings.sql`.
5. Document in this file.

The same pattern works for the AtoM side
(`ahgAuthorityResolutionPlugin/lib/Lookup/Adapters/`).

## Precedence

`PrefillEngine` walks the precedence list (`lookup.precedence`) field by
field. For each field on the new-authority form:

1. Ask adapter 1 (VIAF by default).
2. If it has a value for this field, use it; record the source URI.
3. Otherwise fall through to adapter 2 (Wikidata).
4. ... etc.

The chosen source URI is recorded **per field** in the field-provenance
graph (`prov:wasDerivedFrom`). See
[Field provenance graph](../provenance/field-provenance.md).

> Heratio Help Center article. Category: Integration.

# DataCite Metadata Enrichment

When Heratio mints a DOI for an archival record, it sends a metadata payload to DataCite. The richer that payload, the better the record is indexed in DataCite Commons, Google Scholar, OpenAIRE, and the academic citation graph. As of Heratio v1.98.0 the DOI minter automatically populates four additional DataCite blocks beyond the minimum required (title / publisher / year / type).

This article explains what's emitted, where it comes from, and what an archivist can do to enrich a record before minting.

---

## What gets sent to DataCite

| Block               | Sourced from                                                                                                                  | What to do to enrich it                                                                                                |
|---------------------|-------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|
| **Creators**        | Actors linked to the IO through events (creator, author, photographer, etc.)                                                  | Use **Add new event** on the IO edit page and pick an actor record.                                                    |
| **Creator ORCIDs**  | `ahg_actor_identifier` rows where `identifier_type = orcid`                                                                   | Open the actor record, add an **External authority identifier** with type **ORCID** and the 16-digit value.            |
| **RelatedIdentifier - IsPartOf**       | The IO's parent fonds / series / subseries (`parent_id`)                                                          | Make sure the record sits under the right fonds/series in the tree. Minted parent DOIs are preferred over plain URLs.  |
| **RelatedIdentifier - IsVariantFormOf**| Digital-object derivatives (master + access copies + thumbnails)                                                  | Attach the original master plus its derivatives via the **Digital object** panel - emitted automatically when > 1 present. |
| **RelatedIdentifier - IsReferencedBy** | Exhibition placements (`exhibition_object`)                                                                       | Add the record to an exhibition via the Exhibition module.                                                             |
| **GeoLocations**    | Place access points (taxonomy 42) on the IO                                                                                   | Use the **Place** access-point picker on the IO edit page. Coordinates flow through when configured in the place term. |
| **FundingReferences** | New `ahg_io_funding` table                                                                                                  | Import via `php artisan doi:funding-import funding.csv` (see below).                                                  |

---

## ORCIDs on actors

An ORCID is a 16-digit identifier (last digit may be `X`) globally unique to a person. To attach one:

1. Open the actor record.
2. Go to **External authority identifiers** (or **Linked data** depending on theme).
3. Add a new identifier, type **ORCID**, paste the value (any of `0000-0002-1825-0097`, `0000000218250097`, or `https://orcid.org/0000-0002-1825-0097` works).
4. Save. The next DOI mint or DOI update will pick it up automatically.

Malformed ORCIDs are silently dropped (we never emit invalid DataCite XML), so it is safe to paste something and re-check later.

---

## Funding references - CSV import

DataCite supports `<fundingReferences>` with funder, identifier (ROR / ISNI / Crossref Funder ID / GRID), award number, award URI, and award title. Heratio captures these in the `ahg_io_funding` table.

Because the IO edit page currently does not expose a Funding panel, the operator path is a CSV bulk-import. Build a CSV like:

```csv
information_object_id,funder_name,funder_identifier,funder_identifier_type,award_number,award_uri,award_title
12345,National Research Foundation,https://ror.org/05bjb6e90,ROR,NRF-2026-001,https://nrf.example.org/grants/NRF-2026-001,Digital preservation of southern African archives
12346,Mellon Foundation,https://ror.org/04bdffz58,ROR,M-2025-7782,,Archival access for the global south
```

Only `information_object_id` and `funder_name` are mandatory. Run:

```bash
php artisan doi:funding-import /path/to/funding.csv --dry-run
# review the output, then drop --dry-run to actually insert
php artisan doi:funding-import /path/to/funding.csv
```

The import is idempotent on `(information_object_id, funder_name, award_number)` so re-running the same CSV will skip duplicates. Existing DOIs need a manual update to pick up the new funding rows - use **Update DOI** on the DOI dashboard or `php artisan ahg:doi-process-queue` after enqueuing.

---

## When does the enrichment fire?

- New DOIs: every mint via `DoiService::mint()` automatically includes whichever blocks have data.
- Existing DOIs: queue an update via **Update DOI** on `/admin/doi/view/{id}` or via the queue command - the same enriched payload is PUT to DataCite.
- Defensive: if a sidecar table is missing (e.g. `ahg_actor_identifier`, `ahg_place_coords`, `ahg_io_funding`) the build silently skips that block. Nothing breaks; the DOI just goes out with less detail.

---

## DataCite XML form

For consumers that ingest raw Kernel-4 XML (OAI-PMH harvesters, some institutional repositories), `DoiService::buildXml($payload)` renders the same enriched record as schema-valid XML against `kernel-4.5/metadata.xsd`. The DataCite REST API itself uses JSON:API by default, so most operators will never need the XML form directly.

---

## Related

- **DOI Management - User Guide** - lifecycle, states, dashboard.
- **DOI Manage - User Guide** - configuration and queue ops.
- Issue **#654** on GitHub tracks the rolling DataCite v4.5 enrichment work; Phase 2 closes the four metadata blocks above. Phase 3 (Events API client + per-collection accuracy) remains open.

# RiC Linked Data export: portability, dataset descriptor, deprecation (#1321)

The governed, self-describing Linked Data surface for the RiC-O dataset - what a
consumer (or the AtoM Foundation / an enterprise ontology platform) reads to
discover, harvest, and trust Heratio's records-in-contexts data.

## Endpoints (all under `/api/ric/v1`, public GET, throttled)

| Route | Returns |
|---|---|
| `/dataset` | DCAT + VoID descriptor (JSON-LD): title, license, publisher, version, `dcterms:conformsTo` the pinned standards, `void:sparqlEndpoint`, and every `dcat:distribution` |
| `/changelog` | Versioned change feed + the pinned standard versions + link to the governance pin |
| `/records/{slug}/export?format=jsonld\|ttl\|rdf` | Per-record export (existing) |
| `/sparql`, `/oai` | SPARQL query + OAI-PMH harvest (existing) |

## Round-trip portability guarantee (increment 1)

A record exported to **JSON-LD or Turtle** re-imports via `RdfImportService`
with title / identifier / description intact. Proven by
`packages/ahg-ric/tests/Feature/RicRoundTripTest.php`. The test surfaced and
fixed a real gap: the serializer emits `rico:title`, but the importer only
mapped `rico:name`/`dc:title`, so titles were silently dropped on re-import -
`rico:title` is now in `RdfImportService::IO_PREDICATE_MAP`.

## Deprecate, don't delete (increment 2)

The pin guarantees stable IRIs: a superseded entity is marked `owl:deprecated`,
never deleted and its IRI recycled.

- Register: `ric_deprecated_entity` (entity_type, entity_id, reason,
  superseded_by_iri, deprecated_at). Auto-created at provider boot (idempotent).
- Service: `AhgRic\Services\RicDeprecationService` - `markDeprecated()`,
  `isDeprecated()`, `info()`, `reinstate()`, `all()`.
- Emission: `RicSerializationService` stamps `owl:deprecated: true` (+
  `dcterms:isReplacedBy` when a successor IRI is recorded, + `rdfs:comment` for
  the reason) on `serializeRecord()` and `serializePlace()`.
- This is also where a destroyed/vanished place is flagged for the Lost Places
  POC (#1323) - the node persists, deprecated, never deleted.

## Code

- `AhgRic\Services\RicDatasetService` - descriptor() + changelog().
- `AhgRic\Http\Controllers\DatasetController` - the two endpoints.
- Tests: `packages/ahg-ric/tests/Feature/RicDatasetTest.php`,
  `RicRoundTripTest.php`.

## Validate-on-export (final increment)

`GET /api/ric/v1/records/{slug}/export?validate=1` runs the published output
through the RiC-O SHACL shapes (`ShaclValidationService`) and reports
conformance via response headers - `X-SHACL-Validated` (did the validator run),
`X-SHACL-Conformant` (`true`/`false`/`unknown`), `X-SHACL-Violations` (count).
Fails open: when pyshacl is unavailable, validated=false / conformant=unknown
and the export still returns.

## PROV-O: AI-asserted vs documented fact (final increment)

The pin (section 6) forbids passing AI inference off as documented fact.

- Register: `ric_inferred_assertion` (entity_type, entity_id, predicate, model,
  confidence, receipt_id, human_confirmed). Auto-created at provider boot.
- Service: `AhgRic\Services\RicProvenanceService` - `markInferred()`,
  `isInferred()`, `forEntity()`, `all()`.
- Emission: a registered entity exports with `ahg:assertionStatus: "inferred"`
  and `prov:wasGeneratedBy` -> a `prov:SoftwareAgent` (the model) carrying the
  confidence + AhgInferenceReceipt id. An entity with NO register row carries no
  prov: block - **absence = asserted fact**. That asymmetry is the
  distinguishability guarantee.

#1321 is complete with these two increments.

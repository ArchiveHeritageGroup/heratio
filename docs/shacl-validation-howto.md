# SHACL Validation in Heratio - How-To

Heratio validates RiC-shaped archival data against a curated **SHACL shape set** so editors catch incomplete or invalid records *before* they reach Elasticsearch, OAI-PMH harvest, or the OpenRiC API.

The shape set lives at `packages/ahg-ric/tools/ric_shacl_shapes.ttl` and is aligned with the [OpenRiC](https://openric.org) mapping.

---

## What gets validated

Two layers run on every "Validate" click:

1. **Mandatory-fields check (pure PHP, always runs)** - covers the ISAD/ISAAR/ISDIAH/ISDF mandatory fields per entity type:
   - `Agent` / `Person` / `CorporateBody` / `Family` → `rico:name` (ISAAR 5.1.2 - authorized form of name)
   - `Function` → `rico:name` (ISDF 5.1.2)
   - `Record` / `RecordSet` → `rico:identifier` (ISAD 3.1.1)
   - `Repository` → `rico:name` (ISDIAH 5.1.2)

2. **SHACL graph-shape evaluation (optional, requires Python)** - runs the [pyshacl](https://github.com/RDFLib/pySHACL) engine against the full shape set. Constraint types in current use:

   | SHACL constraint | What it catches |
   | --- | --- |
   | `sh:minCount`, `sh:maxCount` | Exactly-N cardinalities (e.g. exactly one title) |
   | `sh:datatype` | xsd type checks |
   | `sh:minLength` | Empty-string detection |
   | `sh:pattern` | Regex format validation (identifiers, dates) |
   | `sh:class` | Referential type check (target node is the right RDF class) |
   | `sh:in` | Value-enumeration check |
   | `sh:or` | Alternation between shape branches |

The shape set today defines 68 constraints across `RecordSetShape`, `RecordShape`, `AgentShape` (Person, CorporateBody, Family), `FunctionShape`, `RepositoryShape`, `PlaceShape`, `RuleShape`, `ActivityShape`, `InstantiationShape`.

---

## Where it runs

| Surface | URL |
| --- | --- |
| Per-entity validation page | `/admin/ric/validate/{type}/{id}` |
| Inline "Validate" link on every dual-view entity show page | rendered by `_ric-entities-panel.blade.php` |
| Global SHACL run (validates entire Fuseki graph) | `/admin/ric/shacl-validate` |
| Shape source file (editable) | `packages/ahg-ric/tools/ric_shacl_shapes.ttl` |
| Python validator (optional) | `packages/ahg-ric/tools/ric_shacl_validator.py` |

The "Validate" link appears in the RiC Context panel header on every major entity type: actor, IO, accession, donor, function, repository, rights-holder, storage, term, dam, gallery, library, loan, museum.

---

## Enabling the full SHACL pipeline

Without `pyshacl`, only mandatory-fields and referential-integrity checks run. To enable full graph-shape evaluation:

```bash
# Install Python dependencies (system-wide or in a venv)
sudo apt install python3-pip
pip install pyshacl rdflib
```

Verify:

```bash
python3 -c "import pyshacl; print(pyshacl.__version__)"
python3 -c "import rdflib; print(rdflib.__version__)"
```

Once available, the existing `ShaclValidationService::validateAgainstShapes()` method auto-detects them and the "Validate" page surfaces SHACL violations alongside mandatory-fields errors.

---

## Editing the shape set

The shape set is plain Turtle. To add a new constraint (e.g. require `rico:beginningDate` on every `rico:Person`):

```turtle
:PersonShape a sh:NodeShape ;
    sh:targetClass rico:Person ;
    sh:property [
        sh:path rico:beginningDate ;
        sh:minCount 1 ;
        sh:datatype xsd:date ;
        sh:message "Person must record a date of birth or earliest known date"
    ] .
```

Edit `packages/ahg-ric/tools/ric_shacl_shapes.ttl`, save, and re-run the Validate link - no service restart needed (the file is read on each request).

---

## Validation entry points (programmatic)

```php
use AhgRic\Services\RicSerializationService;
use AhgRic\Services\ShaclValidationService;

$entity = app(RicSerializationService::class)->serializeAgent($actorId);
$result = app(ShaclValidationService::class)->validateBeforeSave($entity, 'Agent');

if (! $result['valid']) {
    foreach ($result['errors'] as $err) {
        // ...
    }
}
```

Supported entity types in `ShaclValidationService::validateBeforeSave($entity, $type)`:
- `Record`, `RecordSet`
- `Agent`, `Person`, `CorporateBody`, `Family`
- `Function`, `Repository`, `Place`, `Rule`, `Activity`, `Instantiation`

---

## What's not yet automatic

- **Pre-save validation hooks**: the Validate link is a manual editor tool today. Wiring `ShaclValidationService::validateBeforeSave()` into each `Controller::update()` save path (warn / block / off setting) is a follow-on task.
- **Violation persistence**: results are computed live on each click; nothing is cached or queued. Adding a `shacl_validation_log` table to record per-entity status is a follow-on task.
- **Bulk dashboard**: a "Show me every Person record with missing dates of existence" cross-cutting view is on the roadmap.
- **Custom shapes per institution**: the shape set is global today. Per-repository shape sets (e.g. one institution requires `rico:scopeAndContent` for every fonds; another doesn't) is a roadmap item.

---

## Related

- [`packages/ahg-ric/src/Services/ShaclValidationService.php`](../packages/ahg-ric/src/Services/ShaclValidationService.php) - the service
- [`packages/ahg-ric/tools/ric_shacl_shapes.ttl`](../packages/ahg-ric/tools/ric_shacl_shapes.ttl) - the shape set
- [`packages/ahg-ric/tools/ric_shacl_validator.py`](../packages/ahg-ric/tools/ric_shacl_validator.py) - the Python validator wrapper
- [OpenRiC mapping](https://openric.org/spec/mapping.html) - canonical RiC-CM / RiC-O mapping the shapes encode
- [W3C SHACL recommendation](https://www.w3.org/TR/shacl/) - the standard

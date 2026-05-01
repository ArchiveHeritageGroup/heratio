# Magic-number term IDs - refactor audit

The Heratio codebase has many literal integers passed to `relation.type_id`,
`status.type_id`, `event.type_id`, `note.type_id` and `actor.entity_type_id`
columns. These are all references to AtoM term IDs (rows in the `term` table).

The canonical IDs are defined in `AhgCore\Constants\TermId`. Mirroring AtoM's
upstream `lib/model/QubitTerm.php`.

## Refactored (call sites now use `TermId::*`)

| File | Constants used |
|---|---|
| `ahg-donor-manage/src/Services/DonorService.php` | `RELATION_DONOR` |
| `ahg-storage-manage/src/Services/StorageService.php` | `RELATION_HAS_PHYSICAL_OBJECT` |
| `ahg-actor-manage/src/Services/ActorService.php` | `RELATION_NAME_ACCESS_POINT` |
| `ahg-actor-manage/src/Services/ActorBrowseService.php` | `RELATION_NAME_ACCESS_POINT` |
| `ahg-accession-manage/src/Services/AccessionService.php` | `RELATION_RIGHT` (other call sites flagged FIXME) |
| `ahg-core/src/Services/AclService.php` | `STATUS_TYPE_PUBLICATION` |
| `ahg-reports/src/Services/ReportService.php` | `STATUS_TYPE_PUBLICATION`, `PUBLICATION_STATUS_*` |
| `ahg-api/src/Controllers/V2/SearchController.php` | `STATUS_TYPE_PUBLICATION`, `PUBLICATION_STATUS_PUBLISHED` |
| `ahg-api/src/Controllers/V2/PublishController.php` | `STATUS_TYPE_PUBLICATION`, `PUBLICATION_STATUS_PUBLISHED` |
| `ahg-api/src/Controllers/V2/DescriptionController.php` | `STATUS_TYPE_PUBLICATION`, `PUBLICATION_STATUS_*`, `EVENT_TYPE_CREATION`, `RELATION_NAME_ACCESS_POINT` |
| `ahg-repository-manage/src/Controllers/RepositoryController.php` | `OTHER_NAME_PARALLEL`, `OTHER_NAME_OTHER_FORM` |
| `ahg-repository-manage/src/Services/RepositoryService.php` | `ACTOR_ENTITY_CORPORATE_BODY` |
| `ahg-repository-manage/resources/views/show.blade.php` | `OTHER_NAME_*` (FQN) |
| `ahg-repository-manage/resources/views/print.blade.php` | `OTHER_NAME_*` (FQN) |
| `ahg-research/src/Services/BibliographyService.php` | `EVENT_TYPE_CREATION` |
| `ahg-discovery/src/Services/PageIndexService.php` | `EVENT_TYPE_CREATION` |
| `ahg-discovery/src/Controllers/DiscoveryController.php` | `EVENT_TYPE_CREATION` |
| `ahg-ric/src/Services/RicSerializationService.php` | `EVENT_TYPE_CREATION` |
| `ahg-gallery/src/Controllers/GalleryController.php` | `EVENT_TYPE_CREATION`, `STATUS_TYPE_PUBLICATION` |
| `ahg-gallery/src/Services/GalleryService.php` | `STATUS_TYPE_PUBLICATION`, `PUBLICATION_STATUS_DRAFT` |
| `ahg-data-migration/src/Services/DataMigrationService.php` | `STATUS_TYPE_PUBLICATION`, `PUBLICATION_STATUS_DRAFT` |

## Bugs fixed during refactor (behaviour changes)

These call sites used literal IDs that diverged from AtoM canonical values
and were returning wrong/empty data. Verified against AtoM source code in
`/usr/share/nginx/archive/plugins/qtAccessionPlugin/` and corrected.

| File | Bug → Fix |
|---|---|
| `AccessionService::getDonors` | was `relation.object_id=accession, type=167` → now `subject=accession, object=donor, type=RELATION_DONOR` |
| `AccessionService::getCreators` | was `type=168` → now `type=RELATION_CREATION` (111). Per AtoM `addInformationObjectAction.class.php` accession creators ARE relation rows (subject=actor, object=accession, type=CREATION_ID=111) - same term ID as event creation, different context. |
| `AccessionService::getInformationObjects` | was `type=174` (NOTE_LANGUAGE) → now `type=RELATION_ACCESSION` (167) per AtoM `addInformationObjectAction` |
| `AccessionService::getPhysicalObjects` | was `subject=physical_object, type=179` (AIP_ARTWORK_COMPONENT) → now `object=accession, type=RELATION_HAS_PHYSICAL_OBJECT` (subject=physical_object stays the same) |
| `AccessionService::getAccruals` / `getAccrualTo` | was `type=173` (RIGHT_BASIS_POLICY) → now `type=RELATION_ACCRUAL` (175) |
| `ActorController` (maintaining repository) | inserted `type=160` (PUBLICATION_STATUS_PUBLISHED) → now `type=RELATION_MAINTAINING_REPOSITORY` (187). Verified no existing rows with the old value, so no migration needed. |
| `GalleryController` physical objects query | inverted direction + `type=151` (ACTOR_TEMPORAL) → now `subject=physical_object, object=artwork, type=RELATION_HAS_PHYSICAL_OBJECT` (147) |

## Not yet touched (lower priority)

Many other call sites still use literals - `note.type_id`, `term.taxonomy_id`,
event/notes/term flow controllers in `ahg-dacs-manage`, `ahg-ai-services`,
`ahg-ric/src/Controllers/RicController.php`, `ahg-information-object-manage/src/Controllers/InformationObjectController.php`,
etc. These can be migrated incrementally; each one needs the same audit step
(confirm the literal matches the canonical AtoM ID before swapping in the
constant).

Taxonomy IDs (`taxonomy_id` columns: 35 subject, 42 place, 60 publication
status, 78 occupation/genre, etc.) are a separate namespace and are not yet
covered by `TermId` - that would warrant a sibling `AhgCore\Constants\TaxonomyId`
class.

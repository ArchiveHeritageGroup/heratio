# i18n inner-join inventory (heratio#1608)

Read queries that inner-join a `*_i18n` table filtered to one culture drop every record, actor or term that has no row in that culture. An English catalogue used with the interface in Afrikaans loses creators, subjects, places and children, and titles read "Untitled". This file lists what is left after the first pass, regenerated from the tree on 2026-11-20.

## The pattern

Every fix uses `AhgCore\Support\I18nFallback` (the static twin of the `WithCultureFallback` trait). The inner join and its culture `where` become two LEFT JOINs on the (id, culture) key - current culture under the original alias, `config('app.fallback_locale')` under `{alias}_fb` - and each column read becomes `COALESCE(alias.col, alias_fb.col)`:

```php
->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'event.actor_id', $culture, required: true))
->select('event.actor_id as id', I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'name'))
```

`required: true` keeps the old inner-join row filter, satisfied by either culture; it is used for access points, notes and other named rows. It is left off for descriptions, events, rights and accessions, where a row without text is still a real row. Scalar look-ups (`term_i18n` by id) use `I18nFallback::value()` / `I18nFallback::pluck()`, one query for all ids.

## Counts

The regex counts `->join('*_i18n' ...)` (not `leftJoin`) with a `culture` filter on that alias in the same statement, over `app/` and `packages/*/src`. It is broader than the regex in the issue (654 at the start of this pass against the 579 reported on v1.165.3), mainly because it also counts joins pinned to a literal culture.

| | Sites |
| --- | ---: |
| At the start of this pass | 654 |
| Remaining | 233 |
| Removed by this pass | 421 |

: Inventory totals

Remaining sites by kind:

| Kind | Sites | What to do |
| --- | ---: | --- |
| write path | 102 | Inside a save/edit/import method. Out of scope: an edit form must not prefill one culture with another culture's text. |
| hardcoded culture | 91 | Joined on a literal culture such as 'en'. Does not drop rows on an English catalogue, but breaks CONTRIBUTING; replace with the current culture plus fallback. |
| manual | 27 | Query built over several statements, unqualified column names, or a join closure with extra conditions; rewrite by hand. |
| name lookup | 10 | Matches a term or actor by its name in one culture. Data, not display: change to match by id, as #1609 did. |
| mechanical | 3 | Same rewrite as the fixed sites; left for a later pass (outside the read paths covered here). |

: Remaining sites by kind

## Per package

| Package | Start | Remaining |
| --- | ---: | ---: |
| ahg-information-object-manage | 86 | 24 |
| ahg-core | 43 | 20 |
| ahg-term-taxonomy | 37 | 14 |
| ahg-dacs-manage | 20 | 11 |
| ahg-exhibition | 12 | 11 |
| ahg-rad-manage | 21 | 11 |
| ahg-ric | 28 | 9 |
| ahg-dc-manage | 13 | 7 |
| ahg-mods-manage | 14 | 7 |
| ahg-oai | 7 | 7 |
| ahg-ric-manage | 12 | 7 |
| ahg-actor-manage | 18 | 6 |
| ahg-heritage-manage | 13 | 6 |
| ahg-research | 10 | 6 |
| ahg-settings | 6 | 6 |
| ahg-dedupe | 10 | 5 |
| ahg-graphql | 5 | 5 |
| ahg-feedback | 7 | 4 |
| ahg-rights-holder-manage | 11 | 4 |
| ahg-api | 67 | 3 |
| ahg-archaeology | 5 | 3 |
| ahg-authority-resolution | 3 | 3 |
| ahg-export | 3 | 3 |
| ahg-media-processing | 3 | 3 |
| ahg-metadata-export | 24 | 3 |
| ahg-storage-manage | 6 | 3 |
| ahg-display | 17 | 2 |
| ahg-doi-manage | 2 | 2 |
| ahg-extended-rights | 3 | 2 |
| ahg-federation | 4 | 2 |
| ahg-integrity | 6 | 2 |
| ahg-loan | 2 | 2 |
| ahg-metadata-extraction | 2 | 2 |
| ahg-records-manage | 3 | 2 |
| ahg-request-publish | 6 | 2 |
| ahg-user-manage | 3 | 2 |
| app/Http | 2 | 2 |
| app/Jobs | 8 | 2 |
| ahg-access-request | 1 | 1 |
| ahg-accession-manage | 19 | 1 |
| ahg-ai-services | 5 | 1 |
| ahg-api-plugin | 1 | 1 |
| ahg-discovery | 8 | 1 |
| ahg-function-manage | 3 | 1 |
| ahg-gallery | 17 | 1 |
| ahg-ingest | 1 | 1 |
| ahg-marketplace | 1 | 1 |
| ahg-museum | 10 | 1 |
| ahg-preservation | 1 | 1 |
| ahg-repository-manage | 14 | 1 |
| ahg-researcher-manage | 2 | 1 |
| ahg-resourcesync | 1 | 1 |
| ahg-sharepoint | 1 | 1 |
| ahg-spectrum | 1 | 1 |
| ahg-translation | 1 | 1 |
| ahg-z3950 | 1 | 1 |
| ahg-biblio-bf | 2 | 0 |
| ahg-dam | 4 | 0 |
| ahg-donor-manage | 1 | 0 |
| ahg-iiif-collection | 1 | 0 |
| ahg-library | 4 | 0 |
| ahg-portable-export | 1 | 0 |
| ahg-reports | 6 | 0 |
| ahg-search | 3 | 0 |
| ahg-static-page | 2 | 0 |

: Sites per package

## Remaining sites

Line numbers are as of this pass and drift with edits.

| File:line | Table | Kind |
| --- | --- | --- |
| `app/Http/Controllers/ContactController.php:24` | static_page_i18n | manual |
| `app/Http/Controllers/HomeController.php:35` | static_page_i18n | manual |
| `app/Jobs/ImportJob.php:758` | term_i18n | write path (resolveLevelId) |
| `app/Jobs/ImportJob.php:895` | term_i18n | manual |
| `packages/ahg-access-request/src/Controllers/AccessRequestController.php:115` | term_i18n | name lookup (match by id) |
| `packages/ahg-accession-manage/src/Controllers/AccessionController.php:1315` | accession_i18n | write path (attachments) |
| `packages/ahg-actor-manage/src/Controllers/ActorController.php:1025` | actor_i18n | hardcoded culture |
| `packages/ahg-actor-manage/src/Services/AuthorityCompletenessService.php:259` | actor_i18n | hardcoded culture |
| `packages/ahg-actor-manage/src/Services/AuthorityFunctionService.php:138` | information_object_i18n | hardcoded culture |
| `packages/ahg-actor-manage/src/Services/AuthorityFunctionService.php:158` | information_object_i18n | hardcoded culture |
| `packages/ahg-actor-manage/src/Services/AuthorityFunctionService.php:78` | actor_i18n | hardcoded culture |
| `packages/ahg-actor-manage/src/Services/AuthorityOccupationService.php:140` | actor_i18n | hardcoded culture |
| `packages/ahg-ai-services/src/Controllers/AiController.php:1661` | actor_i18n | write path (batchCreate) |
| `packages/ahg-api-plugin/src/Controllers/ApiPluginController.php:52` | information_object_i18n | hardcoded culture |
| `packages/ahg-api/src/Controllers/OaiPmhController.php:810` | setting_i18n | hardcoded culture |
| `packages/ahg-api/src/Controllers/V2/DescriptionController.php:277` | property_i18n | manual |
| `packages/ahg-api/src/Controllers/V2/DescriptionController.php:391` | information_object_i18n | write path (store) |
| `packages/ahg-archaeology/src/Console/Commands/ArchaeologySeedVocabulariesCommand.php:195` | term_i18n | mechanical |
| `packages/ahg-archaeology/src/Console/Commands/ArchaeologySeedVocabulariesCommand.php:269` | taxonomy_i18n | write path (repairTree) |
| `packages/ahg-archaeology/src/Console/Commands/ArchaeologySeedVocabulariesCommand.php:325` | taxonomy_i18n | mechanical |
| `packages/ahg-authority-resolution/src/Services/AssignmentService.php:240` | acl_group_i18n | hardcoded culture |
| `packages/ahg-authority-resolution/src/Services/Evidence/CoOccurringPersonEvaluator.php:81` | actor_i18n | hardcoded culture |
| `packages/ahg-authority-resolution/src/Services/Evidence/GeographicEvaluator.php:163` | term_i18n | hardcoded culture |
| `packages/ahg-core/src/Commands/AuthorityCompletenessScanCommand.php:44` | actor_i18n | hardcoded culture |
| `packages/ahg-core/src/Commands/DoiMintCommand.php:58` | term_i18n | name lookup (match by id) |
| `packages/ahg-core/src/Commands/ExportBulkCommand.php:45` | term_i18n | name lookup (match by id) |
| `packages/ahg-core/src/Repositories/MysqlDescriptionRepository.php:114` | term_i18n | hardcoded culture |
| `packages/ahg-core/src/Repositories/MysqlFunctionRepository.php:34` | information_object_i18n | manual |
| `packages/ahg-core/src/Repositories/MysqlPlaceRepository.php:25` | information_object_i18n | hardcoded culture |
| `packages/ahg-core/src/Repositories/MysqlPlaceRepository.php:39` | actor_i18n | hardcoded culture |
| `packages/ahg-core/src/Repositories/MysqlRelationRepository.php:66` | term_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/Import/SectorCsvImporter.php:617` | term_i18n | write path (resolveLevelId) |
| `packages/ahg-core/src/Services/Import/SectorCsvImporter.php:680` | term_i18n | write path (findOrCreateTerm) |
| `packages/ahg-core/src/Services/SectorIdentifierService.php:170` | setting_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/SectorIdentifierService.php:204` | setting_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/SectorIdentifierService.php:334` | setting_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/SectorSampleService.php:276` | term_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/StorytellingService.php:129` | information_object_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/StorytellingService.php:359` | information_object_i18n | hardcoded culture |
| `packages/ahg-core/src/Services/SubjectAccessPointService.php:174` | term_i18n | write path (findOrCreateTerm) |
| `packages/ahg-core/src/Support/DisplayToggles.php:48` | setting_i18n | hardcoded culture |
| `packages/ahg-core/src/Support/EventRow.php:36` | term_i18n | hardcoded culture |
| `packages/ahg-core/src/Support/EventRow.php:50` | term_i18n | hardcoded culture |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:118` | event_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:133` | actor_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:144` | note_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:155` | term_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:165` | term_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:175` | term_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:185` | actor_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:194` | property_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:221` | information_object_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:236` | information_object_i18n | write path (edit) |
| `packages/ahg-dacs-manage/src/Controllers/DacsManageController.php:51` | information_object_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:120` | event_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:135` | actor_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:146` | term_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:156` | term_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:166` | term_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:189` | information_object_i18n | write path (edit) |
| `packages/ahg-dc-manage/src/Controllers/DcManageController.php:55` | information_object_i18n | write path (edit) |
| `packages/ahg-dedupe/src/Controllers/DedupeController.php:407` | information_object_i18n | write path (merge) |
| `packages/ahg-dedupe/src/Controllers/DedupeController.php:432` | information_object_i18n | write path (merge) |
| `packages/ahg-dedupe/src/Controllers/DedupeController.php:574` | actor_i18n | write path (ruleEdit) |
| `packages/ahg-dedupe/src/Controllers/DedupeController.php:720` | actor_i18n | hardcoded culture |
| `packages/ahg-dedupe/src/Controllers/DedupeController.php:752` | actor_i18n | hardcoded culture |
| `packages/ahg-discovery/src/Controllers/DiscoveryController.php:107` | term_i18n | hardcoded culture |
| `packages/ahg-display/src/Services/SearchVocabularyService.php:147` | information_object_i18n | manual |
| `packages/ahg-display/src/Services/SearchVocabularyService.php:160` | term_i18n | manual |
| `packages/ahg-doi-manage/src/Services/DoiService.php:213` | term_i18n | hardcoded culture |
| `packages/ahg-doi-manage/src/Services/DoiService.php:513` | term_i18n | hardcoded culture |
| `packages/ahg-exhibition/src/Console/Commands/CrystalPalaceShellCommand.php:57` | information_object_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceBuildSpaceCommand.php:150` | information_object_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceBuildSpaceCommand.php:62` | information_object_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceBuildSpaceCommand.php:76` | term_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceDemoCommand.php:246` | term_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceDemoCommand.php:269` | information_object_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceDemoCommand.php:342` | information_object_i18n | manual |
| `packages/ahg-exhibition/src/Console/Commands/LostPlaceReconstruct3dCommand.php:63` | information_object_i18n | manual |
| `packages/ahg-exhibition/src/Services/ExhibitionRicService.php:276` | actor_i18n | name lookup (match by id) |
| `packages/ahg-exhibition/src/Services/ExhibitionRicService.php:297` | ric_place_i18n | name lookup (match by id) |
| `packages/ahg-exhibition/src/Services/GenerativeExhibitionService.php:320` | information_object_i18n | hardcoded culture |
| `packages/ahg-export/src/Services/ExportService.php:40` | actor_i18n | hardcoded culture |
| `packages/ahg-export/src/Services/ExportService.php:541` | actor_i18n | manual |
| `packages/ahg-export/src/Services/ExportService.php:56` | term_i18n | hardcoded culture |
| `packages/ahg-extended-rights/src/Services/EmbargoNotificationService.php:351` | term_i18n | name lookup (match by id) |
| `packages/ahg-extended-rights/src/Services/EmbargoNotificationService.php:356` | taxonomy_i18n | name lookup (match by id) |
| `packages/ahg-federation/src/Edm/EdmSerializer.php:402` | setting_i18n | hardcoded culture |
| `packages/ahg-federation/src/Services/VocabSyncService.php:259` | term_i18n | write path (importTerm) |
| `packages/ahg-feedback/src/Controllers/FeedbackController.php:137` | feedback_i18n | manual |
| `packages/ahg-feedback/src/Controllers/FeedbackController.php:237` | feedback_i18n | write path (edit) |
| `packages/ahg-feedback/src/Controllers/FeedbackController.php:333` | feedback_i18n | manual |
| `packages/ahg-feedback/src/Controllers/FeedbackController.php:42` | term_i18n | manual |
| `packages/ahg-function-manage/src/Services/FunctionService.php:174` | term_i18n | name lookup (match by id) |
| `packages/ahg-gallery/src/Services/GalleryService.php:968` | term_i18n | write path (getEditExtras) |
| `packages/ahg-graphql/src/Controllers/GraphqlController.php:489` | information_object_i18n | hardcoded culture |
| `packages/ahg-graphql/src/Controllers/GraphqlController.php:511` | information_object_i18n | hardcoded culture |
| `packages/ahg-graphql/src/Controllers/GraphqlController.php:531` | actor_i18n | hardcoded culture |
| `packages/ahg-graphql/src/Controllers/GraphqlController.php:546` | actor_i18n | hardcoded culture |
| `packages/ahg-graphql/src/Controllers/GraphqlController.php:562` | actor_i18n | hardcoded culture |
| `packages/ahg-heritage-manage/src/Controllers/HeritageController.php:1200` | information_object_i18n | hardcoded culture |
| `packages/ahg-heritage-manage/src/Controllers/HeritageController.php:2160` | term_i18n | name lookup (match by id) |
| `packages/ahg-heritage-manage/src/Controllers/HeritageController.php:2417` | information_object_i18n | hardcoded culture |
| `packages/ahg-heritage-manage/src/Controllers/HeritageController.php:492` | term_i18n | manual |
| `packages/ahg-heritage-manage/src/Services/HeritageSearchService.php:392` | term_i18n | hardcoded culture |
| `packages/ahg-heritage-manage/src/Services/HeritageSearchService.php:400` | actor_i18n | hardcoded culture |
| `packages/ahg-information-object-manage/src/Controllers/DigitalObjectController.php:514` | term_i18n | hardcoded culture |
| `packages/ahg-information-object-manage/src/Controllers/DigitalObjectController.php:520` | term_i18n | hardcoded culture |
| `packages/ahg-information-object-manage/src/Controllers/DigitalObjectController.php:860` | term_i18n | mechanical |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:149` | actor_i18n | write path (findOrCreateActor) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1877` | event_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1893` | actor_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1904` | note_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1919` | term_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1929` | term_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1939` | term_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1949` | actor_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1958` | property_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1973` | property_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1978` | property_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1984` | property_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1989` | property_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:1998` | information_object_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:2006` | information_object_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:2019` | property_i18n | write path (edit) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:3199` | information_object_i18n | write path (loadIoForFields) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:3493` | property_i18n | write path (duplicateMultiRowFromSource) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:3584` | property_i18n | write path (duplicateMultiRowFromSource) |
| `packages/ahg-information-object-manage/src/Controllers/InformationObjectController.php:80` | term_i18n | write path (findOrCreateTerm) |
| `packages/ahg-information-object-manage/src/Services/InformationObjectBrowseService.php:167` | term_i18n | hardcoded culture |
| `packages/ahg-ingest/src/Services/IngestCommitRunner.php:516` | term_i18n | hardcoded culture |
| `packages/ahg-integrity/src/Services/LegalHoldService.php:35` | actor_i18n | hardcoded culture |
| `packages/ahg-integrity/src/Services/LegalHoldService.php:69` | actor_i18n | hardcoded culture |
| `packages/ahg-loan/src/Controllers/LoanController.php:138` | information_object_i18n | write path (create) |
| `packages/ahg-loan/src/Services/LoanService.php:679` | information_object_i18n | hardcoded culture |
| `packages/ahg-marketplace/src/Console/Commands/AssignGalleryItemsCommand.php:56` | information_object_i18n | hardcoded culture |
| `packages/ahg-media-processing/src/Controllers/MediaProcessingController.php:387` | setting_i18n | hardcoded culture |
| `packages/ahg-media-processing/src/Controllers/MediaProcessingController.php:393` | setting_i18n | hardcoded culture |
| `packages/ahg-media-processing/src/Services/DerivativeService.php:146` | setting_i18n | hardcoded culture |
| `packages/ahg-metadata-export/src/Controllers/MetadataExportController.php:100` | actor_i18n | hardcoded culture |
| `packages/ahg-metadata-export/src/Controllers/MetadataExportController.php:69` | actor_i18n | hardcoded culture |
| `packages/ahg-metadata-export/src/Services/IptcFallbackResolver.php:392` | term_i18n | hardcoded culture |
| `packages/ahg-metadata-extraction/src/Services/MetadataExtractionService.php:1109` | term_i18n | manual |
| `packages/ahg-metadata-extraction/src/Services/MetadataExtractionService.php:1136` | actor_i18n | manual |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:163` | term_i18n | write path (edit) |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:180` | term_i18n | write path (edit) |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:190` | term_i18n | write path (edit) |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:200` | actor_i18n | write path (edit) |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:209` | term_i18n | write path (edit) |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:232` | information_object_i18n | write path (edit) |
| `packages/ahg-mods-manage/src/Controllers/ModsManageController.php:55` | information_object_i18n | write path (edit) |
| `packages/ahg-museum/src/Services/MuseumService.php:1253` | term_i18n | write path (getEditExtras) |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:1188` | term_i18n | hardcoded culture |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:1272` | event_i18n | hardcoded culture |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:1351` | term_i18n | hardcoded culture |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:138` | setting_i18n | hardcoded culture |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:589` | information_object_i18n | hardcoded culture |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:904` | information_object_i18n | hardcoded culture |
| `packages/ahg-oai/src/Controllers/OaiPmhController.php:978` | information_object_i18n | hardcoded culture |
| `packages/ahg-preservation/src/Services/PreservationTriageService.php:43` | information_object_i18n | hardcoded culture |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:139` | event_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:152` | note_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:163` | term_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:173` | term_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:183` | term_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:193` | actor_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:202` | term_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:212` | property_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:244` | information_object_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:259` | information_object_i18n | write path (edit) |
| `packages/ahg-rad-manage/src/Controllers/RadManageController.php:74` | information_object_i18n | write path (edit) |
| `packages/ahg-records-manage/src/Services/ClassificationRuleService.php:455` | term_i18n | hardcoded culture |
| `packages/ahg-records-manage/src/Services/FilePlanService.php:228` | information_object_i18n | hardcoded culture |
| `packages/ahg-repository-manage/src/Services/RepositoryBrowseService.php:359` | contact_information_i18n | manual |
| `packages/ahg-request-publish/src/Controllers/RequestPublishController.php:195` | request_to_publish_i18n | write path (edit) |
| `packages/ahg-request-publish/src/Controllers/RequestPublishController.php:68` | request_to_publish_i18n | manual |
| `packages/ahg-research/src/Controllers/ResearchBookingsController.php:123` | information_object_i18n | hardcoded culture |
| `packages/ahg-research/src/Controllers/ResearchProjectOutputsController.php:360` | information_object_i18n | hardcoded culture |
| `packages/ahg-research/src/Controllers/ResearchSavedSearchesController.php:153` | information_object_i18n | hardcoded culture |
| `packages/ahg-research/src/Controllers/ResearchSavedSearchesController.php:195` | information_object_i18n | hardcoded culture |
| `packages/ahg-research/src/Services/BibliographyService.php:230` | actor_i18n | hardcoded culture |
| `packages/ahg-research/src/Services/BibliographyService.php:240` | event_i18n | hardcoded culture |
| `packages/ahg-researcher-manage/src/Controllers/ResearcherSubmissionController.php:249` | actor_i18n | write path (importExchange) |
| `packages/ahg-resourcesync/src/Controllers/ResourceSyncController.php:509` | setting_i18n | hardcoded culture |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:110` | actor_i18n | write path (edit) |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:120` | note_i18n | write path (edit) |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:129` | term_i18n | write path (edit) |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:139` | actor_i18n | write path (edit) |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:161` | information_object_i18n | write path (edit) |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:33` | information_object_i18n | write path (edit) |
| `packages/ahg-ric-manage/src/Controllers/RicManageController.php:96` | event_i18n | write path (edit) |
| `packages/ahg-ric/src/Console/Commands/FusekiInstanceLoadCommand.php:134` | actor_i18n | manual |
| `packages/ahg-ric/src/Http/Controllers/LinkedDataApiController.php:1659` | ric_place_i18n | hardcoded culture |
| `packages/ahg-ric/src/Http/Controllers/LinkedDataApiController.php:1666` | ric_rule_i18n | hardcoded culture |
| `packages/ahg-ric/src/Http/Controllers/LinkedDataApiController.php:1673` | ric_activity_i18n | hardcoded culture |
| `packages/ahg-ric/src/Http/Controllers/LinkedDataApiController.php:1680` | ric_instantiation_i18n | hardcoded culture |
| `packages/ahg-ric/src/Services/RelationshipService.php:52` | setting_i18n | hardcoded culture |
| `packages/ahg-ric/src/Services/RicSerializationService.php:1015` | term_i18n | hardcoded culture |
| `packages/ahg-ric/src/Services/RicSerializationService.php:1034` | actor_i18n | hardcoded culture |
| `packages/ahg-ric/src/Services/RicSerializationService.php:977` | term_i18n | hardcoded culture |
| `packages/ahg-rights-holder-manage/src/Controllers/EmbargoController.php:139` | information_object_i18n | write path (liftForm) |
| `packages/ahg-rights-holder-manage/src/Controllers/EmbargoController.php:65` | information_object_i18n | write path (create) |
| `packages/ahg-rights-holder-manage/src/Controllers/ExtendedRightsController.php:128` | actor_i18n | write path (batch) |
| `packages/ahg-rights-holder-manage/src/Controllers/ExtendedRightsController.php:85` | information_object_i18n | write path (batch) |
| `packages/ahg-settings/src/Commands/SecretsEncryptAtRestCommand.php:54` | setting_i18n | manual |
| `packages/ahg-settings/src/Controllers/SettingsController.php:1372` | term_i18n | hardcoded culture |
| `packages/ahg-settings/src/Controllers/SettingsController.php:1429` | term_i18n | hardcoded culture |
| `packages/ahg-settings/src/Controllers/SettingsController.php:1441` | term_i18n | hardcoded culture |
| `packages/ahg-settings/src/Controllers/SettingsController.php:2439` | term_i18n | hardcoded culture |
| `packages/ahg-settings/src/Controllers/SettingsController.php:2450` | term_i18n | hardcoded culture |
| `packages/ahg-sharepoint/src/Controllers/SharePointController.php:152` | information_object_i18n | write path (ruleEdit) |
| `packages/ahg-spectrum/src/Controllers/SpectrumController.php:502` | actor_i18n | hardcoded culture |
| `packages/ahg-storage-manage/src/Controllers/StorageController.php:360` | physical_object_i18n | hardcoded culture |
| `packages/ahg-storage-manage/src/Controllers/StorageController.php:510` | information_object_i18n | write path (linkTo) |
| `packages/ahg-storage-manage/src/Controllers/StorageController.php:568` | term_i18n | write path (linkTo) |
| `packages/ahg-term-taxonomy/src/Console/SkosValidateCommand.php:121` | other_name_i18n | manual |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1428` | term_i18n | write path (importSkos) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1496` | term_i18n | write path (create) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1588` | other_name_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1592` | note_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1595` | note_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1598` | note_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1603` | term_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1609` | term_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1624` | term_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1639` | term_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1646` | term_i18n | write path (edit) |
| `packages/ahg-term-taxonomy/src/Controllers/TermController.php:1719` | term_i18n | write path (merge) |
| `packages/ahg-term-taxonomy/src/Services/TermService.php:397` | term_i18n | name lookup (match by id) |
| `packages/ahg-translation/src/Controllers/TranslationController.php:1446` | setting_i18n | hardcoded culture |
| `packages/ahg-user-manage/src/Controllers/UserController.php:793` | actor_i18n | write path (editActorAcl) |
| `packages/ahg-user-manage/src/Controllers/UserController.php:852` | actor_i18n | write path (editRepositoryAcl) |
| `packages/ahg-z3950/src/Services/SruService.php:334` | term_i18n | hardcoded culture |

: Remaining sites

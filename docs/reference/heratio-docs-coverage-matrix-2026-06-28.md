# Heratio docs coverage matrix (#1375 phase 1)

_Generated 2026-06-28. One row per `packages/ahg-*` package. Columns derived programmatically: **article** from `docs/help/*.md`; **wired** by cross-referencing `packages/ahg-help/config/help-context.php` path prefixes against `artisan route:list` action namespaces; **legacy** from `docs/help/ahg*plugin.md` (AtoM/Symfony-era)._

## Summary

- **Total `ahg-*` packages:** 115
- **With a user-facing help article:** 113
- **With NO article at all:** 2 (`ahg-inference-receipts`, `ahg-rdm`* — *rdm has adjacent DataCite/dataset articles but no dedicated guide)
- **Wired into help-context.php:** 11 (clean direct: dam, ingest, library, reports, research, search, settings; `ahg-dropdown-manage` present but mismapped to settings guide; `ahg-biblio-frbr` / `ahg-information-object-manage` indirect)
- **With a legacy AtoM/Symfony-era `ahg*plugin.md`:** 52

Help-context.php currently has 18 path entries. The large majority of admin modules below have a **published article but are not wired** — the core finding for #1375 / #1350.

| package | user-article? (docs/help match) | help-context wired? (prefix→slug) | legacy-AtoM doc? | notes |
|---------|----------------------------------|-----------------------------------|------------------|-------|
| `ahg-3d-model` | `3d-model-user-guide.md` | — | `ahg3dmodelplugin.md` |  |
| `ahg-accession-manage` | `accession-manage-user-guide.md` | — | — |  |
| `ahg-access-request` | `access-request-user-guide.md` | — | `ahgaccessrequestplugin.md` |  |
| `ahg-acl` | `acl-user-guide.md` | — | — |  |
| `ahg-actor-manage` | `actor-manage-user-guide.md` | — | — |  |
| `ahg-ai-chatbot` | `ai-chatbot-user-guide.md` | — | — |  |
| `ahg-ai-compliance` | `ai-compliance-article-9.md`, `ai-compliance-article-11.md`, `ai-compliance-article-12.md`, `ai-compliance-article-14.md` | — | — |  |
| `ahg-ai-services` | `ai-services-user-guide.md` | — | — |  |
| `ahg-annotations` | `annotations-user-guide.md` | — | — |  |
| `ahg-api` | `api-user-guide.md` | — | `ahgapiplugin.md` |  |
| `ahg-api-plugin` | `api-plugin-user-guide.md` | — | — |  |
| `ahg-articles` | `articles-authoring-user-guide.md` | — | — |  |
| `ahg-audit-trail` | `audit-trail-user-guide.md` | — | `ahgaudittrailplugin.md` |  |
| `ahg-authority-resolution` | `authority-resolution-user-guide.md` | — | — |  |
| `ahg-backup` | `backup-user-guide.md` | — | `ahgbackupplugin.md` |  |
| `ahg-biblio-bf` | `bibframe-user-guide.md` | — | — |  |
| `ahg-biblio-frbr` | `frbr-user-guide.md` | `library`→`library-user-guide` | — | /library wired match is indirect (FRBR work views under library) |
| `ahg-c2pa` | `c2pa-metadata-assertions.md`, `c2pa-provenance.md` | — | — |  |
| `ahg-cart` | `cart-user-guide.md` | — | `ahgcartplugin.md` |  |
| `ahg-cdpa` | `cdpa-user-guide.md` | — | — |  |
| `ahg-condition` | `condition-user-guide.md` | — | `ahgconditionplugin.md` |  |
| `ahg-core` | `core-user-guide.md` | — | `ahgcoreplugin.md` |  |
| `ahg-custom-fields` | `custom-fields-user-guide.md` | — | `ahgcustomfieldsplugin.md` |  |
| `ahg-dacs-manage` | `dacs-manage-user-guide.md` | — | — |  |
| `ahg-dam` | `dam-module-user-guide.md`, `dam-user-guide.md` | `dam`→`dam-user-guide` | `ahgdamplugin.md` |  |
| `ahg-data-migration` | `data-migration-user-guide.md` | — | `ahgdatamigrationplugin.md` |  |
| `ahg-dc-manage` | `dc-manage-user-guide.md` | — | — |  |
| `ahg-dedupe` | `dedupe-user-guide.md` | — | `ahgdedupeplugin.md` |  |
| `ahg-discovery` | `discovery-user-guide.md` | — | `ahgdiscoveryplugin.md` |  |
| `ahg-display` | `display-user-guide.md` | — | `ahgdisplayplugin.md` |  |
| `ahg-doi` | `doi-user-guide.md` | — | `ahgdoiplugin.md` |  |
| `ahg-doi-manage` | `doi-manage-user-guide.md` | — | — |  |
| `ahg-donor-manage` | `donor-manage-user-guide.md` | — | — |  |
| `ahg-dropdown-manage` | `dropdown-manage-user-guide.md` | `admin/dropdowns`→`ahg-settings-user-guide` | — | prefix admin/dropdowns present but resolves to ahg-settings-user-guide (mismapped); dedicated guide should re-point it |
| `ahg-exhibition` | `exhibition-user-guide.md` | — | `ahgexhibitionplugin.md` |  |
| `ahg-export` | `export-user-guide.md` | — | `ahgexportplugin.md` |  |
| `ahg-extended-rights` | `extended-rights-user-guide.md` | — | `ahgextendedrightsplugin.md` |  |
| `ahg-favorites` | `favorites-user-guide.md` | — | `ahgfavoritesplugin.md` |  |
| `ahg-federation` | `federation-user-guide.md` | — | `ahgfederationplugin.md` |  |
| `ahg-feedback` | `feedback-user-guide.md` | — | `ahgfeedbackplugin.md` |  |
| `ahg-forms` | `forms-user-guide.md` | — | `ahgformsplugin.md` |  |
| `ahg-ftp-upload` | `ftp-upload-user-guide.md` | — | — |  |
| `ahg-function-manage` | `function-manage-user-guide.md` | — | — |  |
| `ahg-functions-docs` | `functions-docs-user-guide.md` | — | — |  |
| `ahg-gallery` | `gallery-module-user-guide.md`, `gallery-user-guide.md` | — | `ahggalleryplugin.md` |  |
| `ahg-gis` | `gis-user-guide.md` | — | — |  |
| `ahg-graphql` | `graphql-user-guide.md` | — | `ahggraphqlplugin.md` |  |
| `ahg-help` | `help-user-guide.md` | — | — |  |
| `ahg-heritage-manage` | `heritage-manage-user-guide.md` | — | — |  |
| `ahg-icip` | `icip-user-guide.md` | — | `ahgicipplugin.md` |  |
| `ahg-iiif-collection` | `iiif-collection-user-guide.md` | — | — |  |
| `ahg-image-ar` | `image-ar-user-guide.md` | — | — |  |
| `ahg-inference-receipts` | — **none** | — | — | NO article |
| `ahg-information-object-manage` | `information-object-manage-user-guide.md` | `research`→`ahgresearchplugin` | — | /research prefix match is indirect |
| `ahg-ingest` | `ingest-user-guide.md` | `ingest`→`ingest-user-guide` | `ahgingestplugin.md` |  |
| `ahg-integrity` | `integrity-user-guide.md` | — | — |  |
| `ahg-ipsas` | `ipsas-user-guide.md` | — | — |  |
| `ahg-jobs` | `jobs-user-guide.md` | — | — |  |
| `ahg-jobs-manage` | `jobs-manage-user-guide.md` | — | — |  |
| `ahg-label` | `label-user-guide.md` | — | `ahglabelplugin.md` |  |
| `ahg-landing-page` | `landing-page-user-guide.md` | — | `ahglandingpageplugin.md` |  |
| `ahg-library` | `library-module-user-guide.md`, `library-user-guide.md` | `library`→`library-user-guide` | `ahglibraryplugin.md` |  |
| `ahg-loan` | `loan-module-user-guide.md`, `loan-user-guide.md` | — | `ahgloanplugin.md` |  |
| `ahg-marketplace` | `marketplace-user-guide.md` | — | `ahgmarketplaceplugin.md` |  |
| `ahg-media-processing` | `media-processing-user-guide.md` | — | — |  |
| `ahg-media-streaming` | `media-streaming-user-guide.md` | — | — |  |
| `ahg-menu-manage` | `menu-manage-user-guide.md` | — | — |  |
| `ahg-metadata-export` | `metadata-export-user-guide.md` | — | `ahgmetadataexportplugin.md` |  |
| `ahg-metadata-extraction` | `metadata-extraction-user-guide.md` | — | `ahgmetadataextractionplugin.md` |  |
| `ahg-mods-manage` | `mods-manage-user-guide.md` | — | — |  |
| `ahg-multi-tenant` | `multi-tenant-user-guide.md` | — | `ahgmultitenantplugin.md` |  |
| `ahg-museum` | `museum-module-user-guide.md`, `museum-user-guide.md` | — | `ahgmuseumplugin.md` |  |
| `ahg-narssa` | `narssa-user-guide.md` | — | — |  |
| `ahg-naz` | `naz-user-guide.md` | — | — |  |
| `ahg-nmmz` | `nmmz-user-guide.md` | — | — |  |
| `ahg-oai` | `oai-user-guide.md` | — | — |  |
| `ahg-observability` | `observability-alerting.md`, `observability-tracing.md` | — | — |  |
| `ahg-ocfl` | `ocfl-storage.md` | — | — |  |
| `ahg-pdf-tools` | `pdf-tools-user-guide.md` | — | — |  |
| `ahg-portable-export` | `portable-export-user-guide.md` | — | `ahgportableexportplugin.md` |  |
| `ahg-preservation` | `preservation-user-guide.md` | — | `ahgpreservationplugin.md` |  |
| `ahg-privacy` | `privacy-user-guide.md` | — | `ahgprivacyplugin.md` |  |
| `ahg-provenance` | `provenance-user-guide.md` | — | `ahgprovenanceplugin.md` |  |
| `ahg-provenance-ai` | `provenance-ai-user-guide.md` | — | — |  |
| `ahg-rad-manage` | `rad-manage-user-guide.md` | — | — |  |
| `ahg-rdm` | — **none** | `research`→`ahgresearchplugin` | — | no dedicated RDM guide; adjacent: datacite-enrichment.md, open-data-schema-org-dataset-user-guide.md; pages under /research |
| `ahg-records-manage` | `records-manage-user-guide.md` | — | — |  |
| `ahg-reports` | `reports-user-guide.md` | `admin/reports`→`reports-dashboard-user-guide`, `reports`→`reports-user-guide` | `ahgreportsplugin.md` |  |
| `ahg-repository-manage` | `repository-manage-user-guide.md` | — | — |  |
| `ahg-request-publish` | `request-publish-user-guide.md` | — | — |  |
| `ahg-research` | `research-user-guide.md` | `research`→`ahgresearchplugin` | `ahgresearchplugin.md` |  |
| `ahg-researcher-manage` | `researcher-manage-user-guide.md` | — | — |  |
| `ahg-resourcesync` | `open-data-resourcesync-user-guide.md` | — | — |  |
| `ahg-ric` | `ric-user-guide.md` | — | — |  |
| `ahg-rights` | `rights-user-guide.md` | — | `ahgrightsplugin.md` |  |
| `ahg-rights-holder-manage` | `rights-holder-manage-user-guide.md` | — | — |  |
| `ahg-scan` | `scan-user-guide.md` | — | — |  |
| `ahg-search` | `search-user-guide.md` | `search`→`advanced-search-user-guide` | — |  |
| `ahg-security-clearance` | `security-clearance-user-guide.md` | — | `ahgsecurityclearanceplugin.md` |  |
| `ahg-semantic-search` | `semantic-search-user-guide.md` | — | `ahgsemanticsearchplugin.md` |  |
| `ahg-settings` | `settings-user-guide.md` | `admin/settings`→`ahg-settings-user-guide` | `ahgsettingsplugin.md` |  |
| `ahg-share-link` | `share-link-user-guide.md` | — | — |  |
| `ahg-sharepoint` | `sharepoint-user-guide.md` | — | — |  |
| `ahg-spectrum` | `spectrum-user-guide.md` | — | `ahgspectrumplugin.md` |  |
| `ahg-static-page` | `static-page-user-guide.md` | — | — |  |
| `ahg-statistics` | `statistics-user-guide.md` | — | `ahgstatisticsplugin.md` |  |
| `ahg-storage-manage` | `storage-manage-user-guide.md` | — | — |  |
| `ahg-term-taxonomy` | `term-taxonomy-user-guide.md` | — | `ahgtermtaxonomyplugin.md` |  |
| `ahg-theme-b5` | `theme-b5-user-guide.md` | — | `ahgthemeb5plugin.md` |  |
| `ahg-translation` | `translation-user-guide.md` | — | `ahgtranslationplugin.md` |  |
| `ahg-user-manage` | `user-manage-user-guide.md` | — | — |  |
| `ahg-vendor` | `vendor-user-guide.md` | — | `ahgvendorplugin.md` |  |
| `ahg-version-control` | `version-control-user-guide.md` | — | — |  |
| `ahg-workflow` | `workflow-user-guide.md` | — | `ahgworkflowplugin.md` |  |
| `ahg-z3950` | `z3950-user-guide.md` | — | — |  |

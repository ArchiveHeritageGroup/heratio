# Heratio Legacy (AtoM/Symfony-era) Help-Doc Triage — 2026-06-28

Feeds the **#1375** umbrella (legacy-doc cleanup).

## Scope

The 68 `docs/help/ahg*plugin.md` files are the **AtoM / Symfony 1.4-era** plugin reference docs.
Every one of them carries the legacy signature `**Dependencies:** atom-framework` and a
`# ahgXxxPlugin - Technical Documentation` heading. They describe the *old* Symfony plugins,
not the current Laravel 12 packages under `packages/ahg-*`.

This triage matches each legacy plugin doc to a modern, non-plugin `*-user-guide.md`
covering the same module, and recommends an action:

- **DELETE-redundant** — a current `*-user-guide.md` already covers the module; the legacy
  plugin doc is pure duplication of stale Symfony detail.
- **REWRITE** — no modern doc exists; the legacy doc is the only coverage of the module but
  describes Symfony and must be rewritten for Laravel (or formally retired).
- **KEEP** — still accurate or genuinely historical.

> Nothing is deleted here. This is the triage table + counts only.

## Triage table

| legacy doc | modern replacement exists? (`*-user-guide.md` for same module) | recommendation |
| --- | --- | --- |
| ahg3dmodelplugin.md | Yes — 3d-model-user-guide.md (+ 3d-model-viewer-user-guide.md) | DELETE-redundant |
| ahgaccessrequestplugin.md | Yes — access-requests-user-guide.md (+ access-request-user-guide.md) | DELETE-redundant |
| ahgaiplugin.md | Yes — ai-services-user-guide.md (+ ai-tools-user-guide.md) | DELETE-redundant |
| ahgapiplugin.md | Yes — api-user-guide.md (+ api-plugin-user-guide.md) | DELETE-redundant |
| ahgaudittrailplugin.md | Yes — audit-trail-user-guide.md | DELETE-redundant |
| ahgbackupplugin.md | Yes — backup-user-guide.md (+ backup-restore-user-guide.md) | DELETE-redundant |
| ahgcartplugin.md | Yes — cart-user-guide.md (+ cart-ecommerce-user-guide.md) | DELETE-redundant |
| ahgconditionplugin.md | Yes — condition-user-guide.md | DELETE-redundant |
| ahgcontactplugin.md | Yes — contact-management-user-guide.md | DELETE-redundant |
| ahgcoreplugin.md | Yes — core-user-guide.md | DELETE-redundant |
| ahgcustomfieldsplugin.md | Yes — custom-fields-user-guide.md | DELETE-redundant |
| ahgdamplugin.md | Yes — dam-user-guide.md (+ dam-module-user-guide.md) | DELETE-redundant |
| ahgdatamigrationplugin.md | Yes — data-migration-user-guide.md | DELETE-redundant |
| ahgdedupeplugin.md | Yes — dedupe-user-guide.md (+ duplicate-detection-user-guide.md) | DELETE-redundant |
| ahgdiscoveryplugin.md | Yes — discovery-user-guide.md (+ discoveries-user-guide.md) | DELETE-redundant |
| ahgdisplayplugin.md | Yes — display-user-guide.md (+ glam-browse-user-guide.md) | DELETE-redundant |
| ahgdoiplugin.md | Yes — doi-user-guide.md (+ doi-manage-user-guide.md) | DELETE-redundant |
| ahgdonoragreementplugin.md | Yes — donor-agreement-user-guide.md | DELETE-redundant |
| ahgembargoplugin.md | Yes — embargo-user-guide.md | DELETE-redundant |
| ahgexhibitionplugin.md | Yes — exhibition-user-guide.md (+ exhibition-spaces-user-guide.md) | DELETE-redundant |
| ahgexportplugin.md | Yes — export-user-guide.md (+ export-data-user-guide.md) | DELETE-redundant |
| ahgextendedrightsplugin.md | Yes — extended-rights-user-guide.md | DELETE-redundant |
| ahgfavoritesplugin.md | Yes — favorites-user-guide.md | DELETE-redundant |
| ahgfederationplugin.md | Yes — federation-user-guide.md | DELETE-redundant |
| ahgfeedbackplugin.md | Yes — feedback-user-guide.md | DELETE-redundant |
| ahgformsplugin.md | Yes — forms-user-guide.md (+ forms-builder-user-guide.md) | DELETE-redundant |
| ahggalleryplugin.md | Yes — gallery-user-guide.md (+ gallery-module-user-guide.md) | DELETE-redundant |
| ahggraphqlplugin.md | Yes — graphql-user-guide.md | DELETE-redundant |
| ahggrapplugin.md | Yes — heritage-accounting-user-guide.md (GRAP 103 heritage-asset accounting) | DELETE-redundant |
| ahgheritageplugin.md | Yes — heritage-sites-user-guide.md ("Heritage Discovery Platform") | DELETE-redundant |
| ahgicipplugin.md | Yes — icip-user-guide.md | DELETE-redundant |
| ahgiiifplugin.md | Yes — iiif-integration-user-guide.md (+ iiif-collection / mirador guides) | DELETE-redundant |
| ahgingestplugin.md | Yes — ingest-user-guide.md (+ data-ingest-user-guide.md) | DELETE-redundant |
| ahglabelplugin.md | Yes — label-user-guide.md (+ label-printing-user-guide.md) | DELETE-redundant |
| ahglandingpageplugin.md | Yes — landing-page-user-guide.md | DELETE-redundant |
| ahglibraryplugin.md | Yes — library-user-guide.md (+ library-module-user-guide.md) | DELETE-redundant |
| ahgloanplugin.md | Yes — loan-user-guide.md (+ loan-module-user-guide.md) | DELETE-redundant |
| ahgmarketplaceplugin.md | Yes — marketplace-user-guide.md | DELETE-redundant |
| ahgmetadataexportplugin.md | Yes — metadata-export-user-guide.md | DELETE-redundant |
| ahgmetadataextractionplugin.md | Yes — metadata-extraction-user-guide.md | DELETE-redundant |
| ahgmigrationplugin.md | Yes — migration-tools-user-guide.md | DELETE-redundant |
| ahgmultitenantplugin.md | Yes — multi-tenant-user-guide.md | DELETE-redundant |
| ahgmuseumplugin.md | Yes — museum-user-guide.md (+ museum-module-user-guide.md) | DELETE-redundant |
| ahgnerplugin.md | Yes — ner-user-guide.md | DELETE-redundant |
| ahgportableexportplugin.md | Yes — portable-export-user-guide.md | DELETE-redundant |
| ahgpreservationplugin.md | Yes — preservation-user-guide.md | DELETE-redundant |
| ahgprivacyplugin.md | Yes — privacy-user-guide.md (+ privacy-compliance-user-guide.md) | DELETE-redundant |
| ahgprovenanceplugin.md | Yes — provenance-user-guide.md | DELETE-redundant |
| ahgregistryplugin.md | Yes — registry-community-hub-user-guide.md (names ahgRegistryPlugin) | DELETE-redundant |
| ahgreportbuilderplugin.md | Yes — report-builder-user-guide.md | DELETE-redundant |
| ahgreportsplugin.md | Yes — reports-user-guide.md (+ reports-dashboard-user-guide.md) | DELETE-redundant |
| ahgrepositorybrowseplugin.md | Yes — repository-browse-user-guide.md | DELETE-redundant |
| ahgrequesttopublishplugin.md | Yes — request-publish-user-guide.md (+ publish-gates-user-guide.md) | DELETE-redundant |
| ahgresearchplugin.md | Yes — research-user-guide.md | DELETE-redundant |
| ahgricexplorerplugin.md | Yes — ric-user-guide.md (+ graph-explorer-user-guide.md) | DELETE-redundant |
| ahgrightsplugin.md | Yes — rights-user-guide.md (+ rights-management-user-guide.md) | DELETE-redundant |
| ahgsecurityclearanceplugin.md | Yes — security-clearance-user-guide.md | DELETE-redundant |
| ahgsemanticsearchplugin.md | Yes — semantic-search-user-guide.md | DELETE-redundant |
| ahgsettingsplugin.md | Yes — settings-user-guide.md (+ ahg-settings-user-guide.md) | DELETE-redundant |
| ahgspectrumplugin.md | Yes — spectrum-user-guide.md | DELETE-redundant |
| ahgstatisticsplugin.md | Yes — statistics-user-guide.md | DELETE-redundant |
| ahgtermtaxonomyplugin.md | Yes — term-taxonomy-user-guide.md (+ term-taxonomy-browse-user-guide.md) | DELETE-redundant |
| ahgthemeb5plugin.md | Yes — theme-b5-user-guide.md (names ahg-theme-b5) | DELETE-redundant |
| ahgtiffpdfmergeplugin.md | Yes — pdf-merge-user-guide.md (legacy doc itself marked DEPRECATED → ahgPreservationPlugin) | DELETE-redundant |
| ahgtranslationplugin.md | Yes — translation-user-guide.md (+ record-translation-user-guide.md) | DELETE-redundant |
| ahguioverridesplugin.md | No — no `*-user-guide.md` covers the UI-override / action-interception layer | REWRITE |
| ahgvendorplugin.md | Yes — vendor-user-guide.md | DELETE-redundant |
| ahgworkflowplugin.md | Yes — workflow-user-guide.md (+ visual-workflow-diagram-user-guide.md) | DELETE-redundant |

## Summary

| Recommendation | Count |
| --- | --- |
| DELETE-redundant | 67 |
| REWRITE | 1 |
| KEEP | 0 |
| **Total legacy plugin docs** | **68** |

### Notes

- **67 of 68** legacy plugin docs are pure duplication: a current, module-matched
  `*-user-guide.md` already exists. These are safe to retire once a redirect/index pass
  confirms no inbound links rely on the old slugs.
- **1 REWRITE — `ahguioverridesplugin.md`.** This describes the Symfony-era
  "UI action overrides / helper-function customization" layer (intercepting AtoM module
  actions without editing base files). There is **no** modern `*-user-guide.md` for it, and
  the override-interception pattern itself is Symfony/AtoM-specific — the Laravel platform
  achieves the same via first-class packages and view composers (see theme-b5-user-guide.md's
  `ThemeService`/view-composer description). The doc is the only coverage of "how Heratio
  customises UI" but is architecturally obsolete; it needs a Laravel-equivalent rewrite or
  formal retirement rather than a straight delete.
- **0 KEEP.** None of the legacy plugin docs are still accurate against the Laravel codebase,
  and none carry unique historical value beyond what a rewrite would capture.
- **Caveat for the delete pass:** several modules have *multiple* modern guides (e.g.
  dam-user-guide.md + dam-module-user-guide.md; library-user-guide.md + library-module-user-guide.md).
  That modern-side duplication is out of scope here but is worth a separate de-dupe sweep.

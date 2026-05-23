Heratio functionality inventory

Saved: 2026-05-23

Summary

This document is an inventoried snapshot of Heratio's functional surface as discovered during an audit of the workspace and packages/ folder. It captures the numbered capability list produced in conversation and is intended for KM ingestion (docs/reference/* watcher) and for project memory.

Usage

- Location: docs/reference/heratio-functionality-inventory.md
- Purpose: ground future design docs, issues and governance artifacts by providing a single canonical list of Heratio capabilities.

Full inventory (numbered capabilities)

1. dahg-3d-model — 3D model support (ingest, display, metadata for 3D assets)
2. dahg-access-request — Access-request/workflow management for restricted items
3. dahg-accession-manage — Accessioning module (register incoming material)
4. dahg-acl — Access control lists and permission primitives
5. dahg-actor-manage — Actor (people/organisations) management
6. dahg-ai-services — AI orchestration (LLM, NER, HTR, DONUT, translation, summarisation)
7. dahg-annotations — Annotation studio, view/edit annotations, annotation layers
8. dahg-api — Public/private API endpoints and API helpers
9. dahg-api-plugin — API plugin scaffolding (extensions to API)
10. dahg-audit-trail — Audit logging / change history for records and actions
11. dahg-authority-resolution — Authority file resolution and matching services
12. dahg-backup — Backup utilities and export/restore helpers
13. dahg-cart — “Cart” or basket for users to collect items (reproduction/order flows)
14. dahg-cdpa — CDPA (custom domain/process) or compliance helpers
15. dahg-condition — Condition assessment / preservation condition metadata
16. dahg-core — Core framework glue (shared helpers, base models, bootstrapping)
17. dahg-custom-fields — Dynamic/custom metadata fields per entity type
18. dahg-dacs-manage — DACS-related management tools (descriptive standard)
19. dahg-dam — Digital Asset Management features (storage, access, metadata)
20. dahg-data-migration — Data migration tooling and import pipelines
21. dahg-dc-manage — Dublin Core management/support utilities
22. dahg-dedupe — Deduplication utilities for records / metadata
23. dahg-discovery — Discovery UI / discovery configuration helpers
24. dahg-display — Presentation / front-end display components and templates
25. dahg-doi — DOI minting / DOI metadata handling
26. dahg-doi-manage — DOI management UI and flows
27. dahg-donor-manage — Donor / provenance management for collection donors
28. dahg-dropdown-manage — Manage dropdowns and controlled lists used across UI
29. dahg-exhibition — Exhibition builder / exhibition content management
30. dahg-export — Generic export features (CSV, XML, packaged exports)
31. dahg-extended-rights — Extended rights / special rights management features
32. dahg-favorites — User favorites / bookmarking of records & objects
33. dahg-federation — Federation / cross-instance search and sharing features
34. dahg-feedback — User feedback collection and management UI
35. dahg-forms — Form builder and form-management (input workflows)
36. dahg-ftp-upload — FTP upload adapter / ingestion helper via FTP
37. dahg-function-manage — Manage serverless/registered functions (app-level function registry)
38. dahg-functions-docs — Documentation and UI for functions (developer-facing)
39. dahg-gallery — Gallery display components for image-heavy assets
40. dahg-gis — GIS/geospatial features (maps, geo-metadata, georeferencing)
41. dahg-graphql — GraphQL API support (endpoints / schema)
42. dahg-help — Help system, contextual help pages and guides
43. dahg-heritage-manage — Heritage-specific management features (heritage metadata)
44. dahg-icip — ICIP (institutional/collection-specific) module — domain-specific connector
45. dahg-iiif-collection — IIIF collection support (manifests, collection endpoints)
46. dahg-image-ar — Image augmented-reality / advanced image display tooling
47. dahg-information-object-manage — Manage information objects (OA/record items)
48. dahg-ingest — Ingest pipelines and batch import services
49. dahg-integrity — Integrity checks, fixity verification, checksums
50. dahg-ipsas — IPSAS/accounting-related features (institutional financial metadata)
51. dahg-jobs — Background job primitives (queue workers, job handling)
52. dahg-jobs-manage — Job monitor / admin UI for queued/background jobs
53. dahg-label — Label generation or presentation labels (physical/digital)
54. dahg-landing-page — CMS-style landing pages and static page builder
55. dahg-library — Library-specific features (cataloguing, library metadata)
56. dahg-loan — Loan management (object loans, loan records, workflow)
57. dahg-marketplace — Marketplace/commerce features (sell licenses, services)
58. dahg-media-processing — Media processing pipeline (transcoding, derivatives)
59. dahg-media-streaming — Streaming delivery for audio/video assets
60. dahg-menu-manage — Manage navigation/menu structures across the site
61. dahg-metadata-export — Metadata export formats (MODS, MARC, XML outputs)
62. dahg-metadata-extraction — Metadata extraction (OCR, auto-extract metadata)
63. dahg-mods-manage — MODS-specific management and mapping tools
64. dahg-multi-tenant — Multi-tenant support (separate tenants/instances)
65. dahg-museum — Museum-specific module (exhibition, collections, loans tailored)
66. dahg-narssa — National archives integration (domain connector)
67. dahg-naz — Domain/partner-specific module (NAZ connector)
68. dahg-nmmz — Domain/partner-specific module (NMMZ connector)
69. dahg-oai — OAI-PMH provider / harvesting support
70. dahg-pdf-tools — PDF utilities (manipulation, OCR pipelines)
71. dahg-portable-export — Portable export packages for migration/sharing
72. dahg-preservation — Preservation workflows, preservation metadata, fixity policies
73. dahg-privacy — Privacy helper services (PII detection, redaction helpers)
74. dahg-provenance — Provenance capture & query (RDF / triplestore integration)
75. dahg-provenance-ai — AI-specific provenance pipeline (inference recording, RDF-Star)
76. dahg-rad-manage — RAD (Records, Appraisal, Disposition) management tools
77. dahg-records-manage — Core records management (file plans, archival descriptions)
78. dahg-reports — Reporting engine and scheduled reports
79. dahg-repository-manage — Repository administration features (repositories, storage zones)
80. dahg-request-publish — Request-to-publish workflow and approvals
81. dahg-research — Research workspace, project-level tools, evidence/snapshots, analysis helpers
82. dahg-researcher-manage — Researcher registration, bookings, researcher profiles, reading-room management
83. dahg-ric — RiC (Records in Context) ontology integration and semantic modelling tools
84. dahg-rights — Rights statement management, licensing support (ODRL etc.)
85. dahg-rights-holder-manage — Rights-holder contact and management utilities
86. dahg-scan — Scanning & capture workflows (scan job management)
87. dahg-search — Elasticsearch-backed full-text search, faceting and filters
88. dahg-security-clearance — Security clearance workflows and clearance metadata
89. dahg-semantic-search — Semantic / thesaurus-driven search, query expansion, ontology lookup
90. dahg-settings — System-wide settings and configuration UI (admin)
91. dahg-share-link — Short-lived share links, token-based public share generation
92. dahg-sharepoint — SharePoint connector / integration utilities
93. dahg-spectrum — Spectrum (collections lifecycle) support / actions mapping
94. dahg-static-page — Static page/flat content management (info pages)
95. dahg-statistics — Usage statistics, analytics exports, site metrics
96. dahg-storage-manage — Storage backends management, storage policies, tiers
97. dahg-term-taxonomy — Controlled vocabularies / taxonomy management UI
98. dahg-theme-b5 — Bootstrap 5 theme and UI components package
99. dahg-translation — Translation engine / multilingual UI support and content translation
100. dahg-user-manage — User management (accounts, roles, sessions)
101. dahg-vendor — Vendor records and supplier management
102. dahg-version-control — Versioning for records/metadata (change history)
103. dahg-workflow — Workflow engine (task definitions, approval flows, queues)

Additional repo-level/infra functionality (non-package items)

104. README.md / docs/ — Project documentation, deployment notes, AI policy references
105. .env / env.examples — Configuration and environment templates (MAIL_*, DB, AI settings)
106. artisan & Laravel bootstrap — Laravel app CLI entrypoint + scheduled tasks (artisan)
107. composer.json / package.json — backend & frontend dependency manifests (PHP/Node)
108. docker/ & docker-compose helpers — Containerisation, development stacks and images
109. tests/ & phpunit config — Unit and integration test suites
110. playwright config — End-to-end test config for front-end flows
111. storage/ & uploads/ — File storage for derivatives, original objects, and cached assets

Next steps & suggestions

- I recommend the KM ingestion watcher will pick this file up automatically; if you want an immediate KM index, run the km-ingest-watcher or open a PR to trigger a CI job.
- Suggested immediate follow-ups: expand items 6 (dahg-ai-services), 74–76 (provenance packages), and 81–83 (research modules) into per-file inventories.
- If you want this snapshot added to the project status summary I can also upsert that record.

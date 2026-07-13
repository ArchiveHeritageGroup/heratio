> Heratio Help Center article. Category: Reference.

# Heratio - Complete Function Reference

**Version:** 3.0
**Last Updated:** July 2026

Complete listing of the user-facing functionality across the Heratio platform and its 116 feature packages. Regenerated from a full audit of every package's routes, controllers, artisan commands, services and views.

---

## Table of Contents

1. [3D Model Viewer and Generation (ahg-3d-model)](#3d-model-viewer-and-generation-ahg-3d-model)
2. [Accession Management (ahg-accession-manage)](#accession-management-ahg-accession-manage)
3. [Access Requests (ahg-access-request)](#access-requests-ahg-access-request)
4. [Access Control and Security Classification (ahg-acl)](#access-control-and-security-classification-ahg-acl)
5. [Authority Records and Reconciliation (ahg-actor-manage)](#authority-records-and-reconciliation-ahg-actor-manage)
6. [AI Library Assistant (ahg-ai-chatbot)](#ai-library-assistant-ahg-ai-chatbot)
7. [AI Act Compliance and Governance (ahg-ai-compliance)](#ai-act-compliance-and-governance-ahg-ai-compliance)
8. [AI Services for Cataloguing (ahg-ai-services)](#ai-services-for-cataloguing-ahg-ai-services)
9. [IIIF Web Annotations (ahg-annotations)](#iiif-web-annotations-ahg-annotations)
10. [Public REST & Linked-Data API (ahg-api)](#public-rest-linked-data-api-ahg-api)
11. [Records Search Plugin (ahg-api-plugin)](#records-search-plugin-ahg-api-plugin)
12. [Archivematica Integration (ahg-archivematica)](#archivematica-integration-ahg-archivematica)
13. [Blog & Articles (ahg-articles)](#blog-articles-ahg-articles)
14. [Audit Trail (ahg-audit-trail)](#audit-trail-ahg-audit-trail)
15. [Authority Resolution & Entity Linking (ahg-authority-resolution)](#authority-resolution-entity-linking-ahg-authority-resolution)
16. [Backup & Disaster Recovery (ahg-backup)](#backup-disaster-recovery-ahg-backup)
17. [BIBFRAME Bibliographic Linked Data (ahg-biblio-bf)](#bibframe-bibliographic-linked-data-ahg-biblio-bf)
18. [FRBR Work Clustering (ahg-biblio-frbr)](#frbr-work-clustering-ahg-biblio-frbr)
19. [Content Authenticity & C2PA Provenance (ahg-c2pa)](#content-authenticity-c2pa-provenance-ahg-c2pa)
20. [Cart, Checkout and E-commerce (ahg-cart)](#cart-checkout-and-e-commerce-ahg-cart)
21. [Data Protection and Compliance Register (ahg-cdpa)](#data-protection-and-compliance-register-ahg-cdpa)
22. [Condition Assessment and Reporting (ahg-condition)](#condition-assessment-and-reporting-ahg-condition)
23. [Core Platform Framework (ahg-core)](#core-platform-framework-ahg-core)
24. [Custom Fields Administration (ahg-custom-fields)](#custom-fields-administration-ahg-custom-fields)
25. [DACS Archival Description Editor (ahg-dacs-manage)](#dacs-archival-description-editor-ahg-dacs-manage)
26. [Digital Asset Management (ahg-dam)](#digital-asset-management-ahg-dam)
27. [Data Migration and Interchange (ahg-data-migration)](#data-migration-and-interchange-ahg-data-migration)
28. [Dublin Core Description Editor (ahg-dc-manage)](#dublin-core-description-editor-ahg-dc-manage)
29. [Deduplication and Record Merging (ahg-dedupe)](#deduplication-and-record-merging-ahg-dedupe)
30. [Hybrid Discovery Search (ahg-discovery)](#hybrid-discovery-search-ahg-discovery)
31. [GLAM Browse and Display (ahg-display)](#glam-browse-and-display-ahg-display)
32. [Digital Object Identifiers (ahg-doi)](#digital-object-identifiers-ahg-doi)
33. [DOI Minting and DataCite Management (ahg-doi-manage)](#doi-minting-and-datacite-management-ahg-doi-manage)
34. [Donor and Deed-of-Gift Management (ahg-donor-manage)](#donor-and-deed-of-gift-management-ahg-donor-manage)
35. [Controlled Vocabulary and Dropdown Manager (ahg-dropdown-manage)](#controlled-vocabulary-and-dropdown-manager-ahg-dropdown-manage)
36. [Exhibitions, 3D Spaces and Reconstructions (ahg-exhibition)](#exhibitions-3d-spaces-and-reconstructions-ahg-exhibition)
37. [Metadata and Catalogue Export (ahg-export)](#metadata-and-catalogue-export-ahg-export)
38. [Extended Rights, Embargo and Traditional Knowledge Labels (ahg-extended-rights)](#extended-rights-embargo-and-traditional-knowledge-labels-ahg-extended-rights)
39. [Favourites and Research Folders (ahg-favorites)](#favourites-and-research-folders-ahg-favorites)
40. [Federation, Union Catalogue and Inter-Institution Loans (ahg-federation)](#federation-union-catalogue-and-inter-institution-loans-ahg-federation)
41. [Feedback & Corrections (ahg-feedback)](#feedback-corrections-ahg-feedback)
42. [Custom Forms & Templates (ahg-forms)](#custom-forms-templates-ahg-forms)
43. [FTP / Bulk File Upload (ahg-ftp-upload)](#ftp-bulk-file-upload-ahg-ftp-upload)
44. [Functions & Activities (ahg-function-manage)](#functions-activities-ahg-function-manage)
45. [Developer Function & Route Catalogues (ahg-functions-docs)](#developer-function-route-catalogues-ahg-functions-docs)
46. [Art Gallery Management (ahg-gallery)](#art-gallery-management-ahg-gallery)
47. [Geographic Search / GIS (ahg-gis)](#geographic-search-gis-ahg-gis)
48. [GraphQL API (ahg-graphql)](#graphql-api-ahg-graphql)
49. [Help & System Documentation (ahg-help)](#help-system-documentation-ahg-help)
50. [Heritage Asset Management & Accounting (ahg-heritage-manage)](#heritage-asset-management-accounting-ahg-heritage-manage)
51. [Indigenous Cultural & Intellectual Property (ahg-icip)](#indigenous-cultural-intellectual-property-ahg-icip)
52. [Archival Description Management (ahg-information-object-manage)](#archival-description-management-ahg-information-object-manage)
53. [IIIF Collections and Interoperability (ahg-iiif-collection)](#iiif-collections-and-interoperability-ahg-iiif-collection)
54. [AI Image Animation (ahg-image-ar)](#ai-image-animation-ahg-image-ar)
55. [Tamper-Evident AI Inference Receipts (ahg-inference-receipts)](#tamper-evident-ai-inference-receipts-ahg-inference-receipts)
56. [Guided Data Ingest (ahg-ingest)](#guided-data-ingest-ahg-ingest)
57. [Records Integrity and Retention (ahg-integrity)](#records-integrity-and-retention-ahg-integrity)
58. [IPSAS Heritage-Asset Accounting (ahg-ipsas)](#ipsas-heritage-asset-accounting-ahg-ipsas)
59. [Background Jobs (ahg-jobs)](#background-jobs-ahg-jobs)
60. [Job Administration and Queue (ahg-jobs-manage)](#job-administration-and-queue-ahg-jobs-manage)
61. [Labels & Barcodes (ahg-label)](#labels-barcodes-ahg-label)
62. [Landing Pages & Personal Dashboards (ahg-landing-page)](#landing-pages-personal-dashboards-ahg-landing-page)
63. [Integrated Library System (ahg-library)](#integrated-library-system-ahg-library)
64. [Loans & Touring Exhibitions (ahg-loan)](#loans-touring-exhibitions-ahg-loan)
65. [Art Marketplace (ahg-marketplace)](#art-marketplace-ahg-marketplace)
66. [Media Derivative Processing (ahg-media-processing)](#media-derivative-processing-ahg-media-processing)
67. [Media Streaming & Captions (ahg-media-streaming)](#media-streaming-captions-ahg-media-streaming)
68. [Navigation Menu Management (ahg-menu-manage)](#navigation-menu-management-ahg-menu-manage)
69. [Archival Metadata Export & Import (ahg-metadata-export)](#archival-metadata-export-import-ahg-metadata-export)
70. [Embedded Metadata Extraction (ahg-metadata-extraction)](#embedded-metadata-extraction-ahg-metadata-extraction)
71. [MODS Metadata Editing (ahg-mods-manage)](#mods-metadata-editing-ahg-mods-manage)
72. [Multi-Tenancy and Branding (ahg-multi-tenant)](#multi-tenancy-and-branding-ahg-multi-tenant)
73. [Museum Collections Management (ahg-museum)](#museum-collections-management-ahg-museum)
74. [MVA Accident Fund Claims (ahg-mva-claims)](#mva-accident-fund-claims-ahg-mva-claims)
75. [NARSSA Archival Transfer Packaging (ahg-narssa)](#narssa-archival-transfer-packaging-ahg-narssa)
76. [National Archives of Zimbabwe (ahg-naz)](#national-archives-of-zimbabwe-ahg-naz)
77. [National Museums and Monuments of Zimbabwe (ahg-nmmz)](#national-museums-and-monuments-of-zimbabwe-ahg-nmmz)
78. [OAI-PMH Metadata Harvesting (ahg-oai)](#oai-pmh-metadata-harvesting-ahg-oai)
79. [Metrics and Observability (ahg-observability)](#metrics-and-observability-ahg-observability)
80. [OCFL Object Storage (ahg-ocfl)](#ocfl-object-storage-ahg-ocfl)
81. [PDF and TIFF Tools (ahg-pdf-tools)](#pdf-and-tiff-tools-ahg-pdf-tools)
82. [Portable Export Packages (ahg-portable-export)](#portable-export-packages-ahg-portable-export)
83. [Digital Preservation and OAIS Repository (ahg-preservation)](#digital-preservation-and-oais-repository-ahg-preservation)
84. [Data Protection and Privacy Compliance (ahg-privacy)](#data-protection-and-privacy-compliance-ahg-privacy)
85. [Provenance and AI Governance (ahg-provenance-ai)](#provenance-and-ai-governance-ahg-provenance-ai)
86. [RAD Archival Description Editor (ahg-rad-manage)](#rad-archival-description-editor-ahg-rad-manage)
87. [Research Data Management (ahg-rdm)](#research-data-management-ahg-rdm)
88. [Records Management (ahg-records-manage)](#records-management-ahg-records-manage)
89. [Analytics, Reporting and Trust Dashboards (ahg-reports)](#analytics-reporting-and-trust-dashboards-ahg-reports)
90. [Repository / Archival Institution Management (ahg-repository-manage)](#repository-archival-institution-management-ahg-repository-manage)
91. [Request-to-Publish Workflow (ahg-request-publish)](#request-to-publish-workflow-ahg-request-publish)
92. [Research OS - The Researcher's Operating System (ahg-research)](#research-os---the-researchers-operating-system-ahg-research)
93. [Researcher Submissions & Contributor Portal (ahg-researcher-manage)](#researcher-submissions-contributor-portal-ahg-researcher-manage)
94. [ResourceSync Change Publishing (ahg-resourcesync)](#resourcesync-change-publishing-ahg-resourcesync)
95. [Records in Contexts (RiC-O) Knowledge Graph (ahg-ric)](#records-in-contexts-ric-o-knowledge-graph-ahg-ric)
96. [Rights (ahg-rights)](#rights-ahg-rights)
97. [Rights Holders, Embargoes & Rights Administration (ahg-rights-holder-manage)](#rights-holders-embargoes-rights-administration-ahg-rights-holder-manage)
98. [Digital Scanning, Ingest & Web Archiving (ahg-scan)](#digital-scanning-ingest-web-archiving-ahg-scan)
99. [Search and Discovery (ahg-search)](#search-and-discovery-ahg-search)
100. [Security Clearance and Multi-Factor Authentication (ahg-security-clearance)](#security-clearance-and-multi-factor-authentication-ahg-security-clearance)
101. [Semantic Search, Knowledge Graph and Heritage Programmes (ahg-semantic-search)](#semantic-search-knowledge-graph-and-heritage-programmes-ahg-semantic-search)
102. [Platform Settings and Administration (ahg-settings)](#platform-settings-and-administration-ahg-settings)
103. [Secure Share Links (ahg-share-link)](#secure-share-links-ahg-share-link)
104. [Microsoft 365 / SharePoint Integration (ahg-sharepoint)](#microsoft-365-sharepoint-integration-ahg-sharepoint)
105. [SPECTRUM Museum Collections Management (ahg-spectrum)](#spectrum-museum-collections-management-ahg-spectrum)
106. [Static Pages (ahg-static-page)](#static-pages-ahg-static-page)
107. [Usage Statistics and Analytics (ahg-statistics)](#usage-statistics-and-analytics-ahg-statistics)
108. [Physical Storage & Strongroom Management (ahg-storage-manage)](#physical-storage-strongroom-management-ahg-storage-manage)
109. [Terms and Taxonomy Management (ahg-term-taxonomy)](#terms-and-taxonomy-management-ahg-term-taxonomy)
110. [Site Theme and UI Shell (ahg-theme-b5)](#site-theme-and-ui-shell-ahg-theme-b5)
111. [Translation and Localization Workbench (ahg-translation)](#translation-and-localization-workbench-ahg-translation)
112. [User Account and Access Management (ahg-user-manage)](#user-account-and-access-management-ahg-user-manage)
113. [Record Version History and Restore (ahg-version-control)](#record-version-history-and-restore-ahg-version-control)
114. [Museum Collections Workflow and Spectrum Compliance (ahg-workflow)](#museum-collections-workflow-and-spectrum-compliance-ahg-workflow)
115. [Vendor and Procurement Management (ahg-vendor)](#vendor-and-procurement-management-ahg-vendor)
116. [Z39.50 / SRU Bibliographic Search and Copy Cataloguing (ahg-z3950)](#z3950-sru-bibliographic-search-and-copy-cataloguing-ahg-z3950)

---

## 3D Model Viewer and Generation (ahg-3d-model)
### 3D Model Viewing
- View interactive 3D models attached to archival objects, with orbit, zoom, auto-rotate, fullscreen and augmented-reality (AR) modes
- Switch between viewer types including the model-viewer (GLB), Gaussian-splat viewer and multi-angle image gallery
- View 3D models embedded in an external page via a standalone embed URL
- Read a public IIIF 3D manifest for any published model (`/iiif/3d/{id}/manifest.json`)
- Browse a public per-object model list and hotspot list through the JSON API
### Model Creation and Management
- Generate a 3D model from an object's image with one click ("Generate 3D"), preview it, then save or discard the result
- Upload your own 3D model files (GLB/GLTF and related formats) against an information object
- Generate a photorealistic 3D reconstruction from a single image via TripoSR (local or remote gateway)
- Edit model metadata, title and viewer defaults
- Delete a model derivative
- Browse and manage all 3D model derivatives in the admin catalogue
### Thumbnails and Annotation
- Generate a still thumbnail for a model
- Generate a multi-angle preview gallery of a model
- Batch-generate thumbnails across many models at once
- Add and delete interactive hotspots (annotations pinned to points on the 3D surface)
- Save, update and delete named camera bookmarks (viewpoints) on a model
### Configuration
- Set default viewer, background, exposure, shadow intensity, rotation speed and auto-rotate
- Toggle download, fullscreen, AR and annotations for viewers
- Configure a maximum upload file size
- Configure TripoSR generation (endpoint URL, API key, marching-cubes resolution, foreground ratio, background removal, texture baking, demo mode, timeout)
- Enable and set 3D model watermark text

## Accession Management (ahg-accession-manage)
### Accession Records
- Browse accession records with search and filtering
- Create a new accession record
- View an accession's full detail page (staff-only, holds donor PII)
- Edit and update an accession record
- Delete an accession record (admin)
- Export accession records to CSV
### Donors and Provenance
- Link an existing donor to an accession from the "Related donor" modal
- Unlink a donor from an accession
- Search donors via typeahead/autocomplete when editing
- Add a new donor inline
### Intake Workflow
- Work the intake queue and per-record queue detail view
- View an accession processing dashboard
- Manage an accession's intake checklist and record completion
- Upload and manage supporting attachments on an accession
- View an accession's activity timeline
- Finalise (accept) an accession, gated on donor-agreement and appraisal requirements
- Create an archival description (information object) directly from an accession, inheriting its rights
### Appraisal, Valuation and Rights
- Run and store an appraisal against an accession
- Manage reusable appraisal templates
- Record a monetary valuation and generate a valuation report
- Manage physical containers linked to an accession
- Manage rights statements on an accession
### Configuration
- Configure intake requirements (require donor agreement, require appraisal, etc.)
- Configure accession numbering scheme

## Access Requests (ahg-access-request)
### Requesting Access
- Submit a new access request for restricted material
- Request access to a specific archival object from its page
- View your own submitted requests and their status
- Cancel a pending request you submitted
- View the detail of a single request
### Approver Workflow
- Browse all access requests (admin)
- View the queue of pending requests awaiting decision
- Approve an access request
- Deny an access request
- Manage the list of designated approvers (add and remove)
### Notifications
- Automatic email notifications on submission, pending review, approval and denial

## Access Control and Security Classification (ahg-acl)
### Group and Permission Management
- Browse ACL groups
- Create and edit a group and its profile
- Add and remove members from a group
- Edit per-group ACL for information objects, actors, repositories and taxonomy terms
- Manage the term-permissions matrix (per-taxonomy access grants)
- Manage translate-permissions (who may translate content)
### Security Clearances and Classification
- View and manage security classifications
- View and set user security clearances, including bulk-grant clearance
- Classify and declassify individual objects
- Manage compartments and per-user compartment access
- View an object's classification detail and a user's clearance/security profile
### Access Requests and Two-Factor
- Submit a security access request and review incoming requests
- Review and decide access requests; manage approvers
- View your own requests and the pending-requests queue
- Set up two-factor authentication and verify a 2FA challenge
### Auditing and Watermarking
- View the ACL audit log and a security audit trail
- Use the security audit dashboard and per-object access audit
- View security dashboards, compliance and security reports
- Configure document watermark settings
- Trace a leaked watermark back to a user/session

## Authority Records and Reconciliation (ahg-actor-manage)
### Authority Record Management
- Browse authority (actor) records with search
- Create, edit, rename and delete an authority record
- View an authority record and print it
- Link a digital object to an authority record
- Autocomplete/lookup actors for AJAX pickers
- Export an authority record as EAC-CPF XML
### External Authority Reconciliation
- Reconcile an actor against external authorities and link a match
- Search Wikidata, VIAF, ULAN and LCNAF for matching identities
- Add, save, verify and delete external identifiers on a record
### Authority Quality and Curation
- Use the authority dashboard and curation workqueue
- Calculate and recalculate record completeness scores; batch-assign completeness work
- View an actor's relationship graph
- Merge two authority records (with preview) and split a conflated record
- Manage an actor's occupations and functions, and browse by function
- Manage an actor's contact information
### Deduplication and NER
- Scan for duplicate authority records
- Compare a suspected-duplicate pair side by side
- Dismiss a false-positive or merge a confirmed duplicate
- Review named-entity mentions and create authority stubs from them
- Promote or reject NER-suggested authority stubs
### Configuration
- Configure authority-management settings (admin)

## AI Library Assistant (ahg-ai-chatbot)
### Conversational Assistant
- Chat with a retrieval-augmented assistant grounded in the Heratio catalogue
- Send a message and receive a grounded, cited answer (rate-limited per user)
- View conversation history and reset the conversation
- Escalate a conversation to a human
- Read the chatbot usage policy before starting (public page)
- Use the embeddable chat widget
### Multilingual Support
- Get replies in the language you typed in (optional input-language detection)
- Benefit from controlled-vocabulary glossary injection and cross-language corpus blending for sparse languages
### WhatsApp Channel
- Interact with the assistant over WhatsApp via an inbound webhook (verify + receive)
### Preservation Knowledge
- Get digital-preservation answers grounded in a curated, deterministic knowledge corpus
### Admin and Review
- Manage the chatbot from an admin console
- Review flagged / low-grounding responses in a moderation dashboard
- Inspect the deterministic preservation-knowledge retrieval for debugging
### Configuration
- Toggle the chatbot, set conversation-history depth and max RAG context records
- Set the RAG grounding threshold that flags weak answers for review
- Set a custom system prompt, default model and temperature
- Toggle reply-in-input-language, glossary injection and cross-language blending
### CLI Commands
- `php artisan ahg:chatbot-test-multilang` - probe multilingual replies across locales (options: `--locales`, `--strict`, `--json`, `--detect` offline input-language self-check)

## AI Act Compliance and Governance (ahg-ai-compliance)
### Risk Register (Article 9)
- Browse the AI risk register
- Create, edit and update risk entries
- Sign off on a risk assessment
- Archive a risk entry
- Report an AI incident against a risk
### System Inventory and Risk Tiering (Articles 6 / 52)
- Maintain an inventory of AI systems in use
- Create, edit, update and delete system-inventory entries with EU AI Act risk tiering
### Model Registry and Annex IV Documentation
- Maintain the AI model registry (create, edit, update, delete models)
- Generate Annex IV technical-documentation bundles
- View generated documentation files
### Human Oversight (Article 14)
- View the human-oversight console
- Update oversight policies
- Halt or resume an individual AI service, or halt all AI services (kill switch)
- Record an oversight attestation and countersign attestations
### Tamper-Evident Inference Log (Article 12)
- Expose a public inference-signing key for external auditors (`/.well-known/ai-inference-pubkey`)
### CLI Commands
- `php artisan ai-compliance:halt {service?} {--resume} {--reason=}` - halt or resume an AI service (or all), recorded in the policy + hash chain
- `php artisan ai-compliance:risk-monitor {--days=} {--user=} {--quiet-empty}` - scan recent activity and send a risk notification
- `php artisan ai-compliance:annex-iv {--service=} {--out=} {--pdf}` - generate Annex IV technical documentation bundles (optionally as PDF)
- `php artisan ai-compliance:install-key {--rotate} {--force}` - install or rotate the inference-log signing keypair
- `php artisan ai-compliance:prune {--years=} {--dry-run}` - prune old inference-log rows while preserving hash/signature chain links
- `php artisan ai-compliance:verify-inference-log {--from=} {--to=} {--service=} {--quiet-pass}` - walk the inference-log chain, recompute hashes, validate signatures and report tampering

## AI Services for Cataloguing (ahg-ai-services)
### Text Intelligence (LLM)
- Summarize an object's description or arbitrary text
- Translate text between languages (with a translation-memory cache)
- Extract named entities from text
- Suggest an archival description for an object, with preview and accept/reject decision
- Spellcheck text
- Generate suggestions in bulk and review them in a suggestions dashboard
### Named Entity Recognition (NER)
- Extract persons, places and subjects from an object's text
- Review, edit and update extracted entities
- Create actor, place or subject authority records directly from an entity
- Bulk-save approved entities and view them on a PDF overlay
- Manage custom NER entity types and gazetteers
- Check NER service health
### Handwritten Text Recognition (HTR) and OCR
- Extract text from handwritten/manuscript images (single object or batch)
- View results and download in multiple formats
- Annotate and bulk-annotate training images; split rows and crop-OCR regions
- Manage HTR training sources and folders; serve and skip images
- Start HTR model training and monitor status
- Run the FamilySearch/FS-Scotland indexer over a folder + Data Safe CSV, correct field extractions and save
- Use the FS overlay annotator to position, recognise and manually crop fields
- Spellcheck HTR output and add custom words/town names to the dictionary
- Correct OCR output with an LLM
### Document Understanding (Donut)
- Extract structured fields from documents (single and batch)
- Prefill a catalogue form from extracted data and finalise it with a provenance link
- View field positions/overlays and download results
- Start and monitor Donut model training
### Condition Assessment
- Assess an object's physical condition (AI-assisted or manual)
- Browse, view and track condition-assessment history
- Run bulk condition assessment
- Manage condition-assessment clients and training data
- Use a condition-assessment dashboard
### Suggested Connections and Face Detection
- Get AI-suggested connections between records and generate an explanation for a suggestion
- Detect faces in images
### Batch, Cost and Governance
- Create, queue, process and monitor AI batch jobs; act on individual jobs
- Manage LLM configurations, test connections and check provider health
- Manage prompt templates
- Set per-user/service AI quotas
- View AI cost/usage reporting
- Manage translation memory (view and delete entries)
- Fine-tune a local LLM via gateway-fronted QLoRA (start + status)
- Text-to-speech synthesis of text
### Configuration
- Configure AI providers, endpoints and per-feature settings (with encrypted API keys)

## IIIF Web Annotations (ahg-annotations)
### Image Annotation
- Read annotations on a digital object (public, W3C Web Annotation / Annotot-shaped)
- Search annotations for an object or canvas
- Create a new annotation on an image (authenticated)
- Update an existing annotation (authenticated)
- Delete an annotation (authenticated)
- Consume annotations from the Mirador viewer's annotation editor, with W3C Web Annotation Protocol headers

## Public REST & Linked-Data API (ahg-api)
### REST API (v1 & v2)
- Browse, search, create, update and delete archival descriptions / information objects (records), including child listings and hierarchy trees
- Manage actors (authority records), taxonomies and terms, repositories, accessions, donors, and functions
- Manage research projects, research outputs, and research bibliographies with their entries
- Manage exhibitions and their object placements (including remote placements across federated sites)
- Manage physical objects, digital objects, and per-object embedded metadata
- Manage conservation conditions with photo attachments, and manage assets with valuations
- Handle privacy workflows over the API: raise and track DSARs (data-subject access requests) and log data breaches
- Publish records: check publish readiness then execute publication
- Upload files for a description, run batch operations, and run federated cross-site search
- Pull incremental sync changes and push batched sync updates for offline/mobile clients
- Look up, validate, and detect identifiers (ISBN, barcode, etc.) and list an object's identifier types
- Search the marketplace, place bids, check auction status, favourite lots, and list currencies/categories
- Read Spectrum museum-workflow statistics, events, and per-object activity
- Retrieve event streams (with correlation chains) and audit records over the API
### API Access & Integration
- Self-service API key management (list, issue, revoke)
- Register and manage webhooks, inspect delivery history, and regenerate signing secrets
- Browse an interactive Swagger UI and fetch the auto-generated OpenAPI spec
### Linked Open Data & Semantic Web
- Resolve stable entity URIs (`id/{slug}`, `data/{slug}`, actor and term URIs) with content negotiation
- Retrieve records as RDF/JSON-LD graphs with a JSON-LD context, seed lists, and a graph sitemap
- Publish a VoID dataset description and export CIDOC-CRM graph data
- Explore the knowledge graph visually via the Graph Explorer (by entity type and slug)
- Serve a federated view of a record joining data from partner institutions
### Harvesting & Interoperability
- Expose an OAI-PMH endpoint for metadata harvesting (GET and POST)
- Serve IIIF Presentation manifests for digital objects
- Serve METS XML for records
- Provide Atom and RSS feeds, plus public and data-specific XML sitemaps (records, actors, terms) and robots.txt
- Broadcast and receive "endangered heritage" federation notices
### Open Data Portal & Citation
- Publish a data catalog, live statistics dashboard, and machine-readable dataset schema (HTML + JSON/JSON-LD)
- Publish open-data protocol, maturity self-assessment, federation index, and an API cookbook
- Browse published vocabularies and resolve SKOS concept/scheme URIs with content negotiation
- Generate citations for any record as BibTeX (.bib), RIS (.ris), CSL-JSON (.json), and Dublin Core XML (.dc.xml)

## Records Search Plugin (ahg-api-plugin)
### Embedded Search
- Search information objects (archival descriptions) from an embeddable plugin page for use in external sites/widgets

## Archivematica Integration (ahg-archivematica)
### Digital Preservation Transfers
- Trigger an Archivematica transfer for a specific archival description from the admin UI
- Poll and display the live status of a running transfer via the transfer panel
- Receive DIP (Dissemination Information Package) callbacks from Archivematica and ingest them back against the matching record
### DIP Ingest & Matching
- Match returned DIPs to source records by identifier or other configured strategy
- Parse METS packages to extract preservation metadata during ingest
### Configuration
- Configure Storage Service and Dashboard credentials, default pipeline UUID, transfer source-location UUID, and staging path from the settings screen
- Choose the DIP-to-record matching strategy (e.g. identifier-based)

## Blog & Articles (ahg-articles)
### Public Reading
- Browse the article index and read individual articles by slug
- Post comments on an article
### Authoring & Admin
- Create, edit, update, and delete articles from the admin console
- Protect/unprotect an article from further edits
- Upload inline images for article bodies
- Add, remove, and reorder related-article links between posts
### Attachments
- Attach files to an article, edit attachment metadata (type/title/description/order), replace the file, reorder attachments, and delete them
### Comment Moderation
- Review submitted comments, change comment status (approve/reject), and delete comments

## Audit Trail (ahg-audit-trail)
### Audit Browsing & Investigation
- Browse the full audit log with filters and view individual audit entries
- View the complete change history of any entity, or all activity by a specific user
- Compare before/after data for a change via a side-by-side diff modal
- Review authentication events, a security-access report, and a user-activity report
### Statistics & Export
- View audit statistics dashboards
- Export audit entries (with date/tenant/user/entity/action filters)
### Retention
- Prune old audit entries on demand from the admin UI
### CLI Commands
- `php artisan audit:prune {--dry-run} {--days=}` - Prune audit records past the retention window (dry-run and per-run override supported)
- `php artisan auditlog:report {--from=} {--to=} {--tenant=} {--user=} {--entity-type=} {--entity-id=} {--action=} {--format=csv} {--out=} {--limit=}` - Generate a filtered audit report as CSV, JSON, or Markdown
- `php artisan auditlog:verify-chain {--from=} {--to=} {--limit=} {--quiet-pass}` - Verify the tamper-evident hash chain of audit receipts for integrity

## Authority Resolution & Entity Linking (ahg-authority-resolution)
### Review Queue & Decisions
- Work a review queue of detected name/place mentions awaiting authority linking
- Open a mention for review with derived context, link it to an existing authority, or link to a different authority
- Create a brand-new authority record from a mention (with pre-filled fields from external sources)
- Park a mention for later, reject it, or unpark it from the park queue
- View a park-queue dashboard widget and per-mention park history
### Assignment & Workload
- Assign mentions to specific archivists from the review screen or in bulk from the queue
- Fetch the archivist roster as JSON for assignment pickers
### External Lookup Sources
- Pre-fill authority data from VIAF, Wikidata, GeoNames, TGN, GND, ISNI, and SAGNC adapters
- Check live status of each lookup source and configure lookup settings
### Evidence Scoring
- Score candidate matches using temporal, geographic, relational, role, hierarchical, scale, co-occurrence, and prior-based evidence evaluators
### CLI Commands
- `php artisan auth-res:promote-sample {object_id} {--show} {--limit=5}` - Promote mentions on an information object into the review workflow
- `php artisan auth-res:scan-parked {--dry-run}` - Flag parked mentions that now have new candidates available
- `php artisan auth-res:reprocess {--mention-id=} {--all-pending} {--limit=0}` - Reprocess pending mentions through candidate generation
- `php artisan auth-res:reprocess-parked {--since=} {--limit=0} {--user-id=0}` - Re-review previously parked mentions
- `php artisan auth-res:generate-candidates {mention_id?} {--object-id=} {--show} {--top=}` - Generate authority candidates for a mention or all mentions on an object
- `php artisan auth-res:score-evidence {mention_id?} {--candidate-id=} {--object-id=} {--show} {--async}` - Score candidate evidence signals and re-rank
- `php artisan auth-res:write-provenance {decision_id?} {--simulate-link=} {--actor-id=} {--show}` - Write a linking decision's provenance to the Fuseki triplestore
- `php artisan auth-res:export-ner-feedback {--format=jsonl}` - Export reviewer decisions as NER training feedback (JSONL or CoNLL)
- `php artisan auth-res:cache-clear {--source=} {--all} {--force}` - Purge cached lookup results for one or all external sources
- `php artisan auth-res:cache-stats` - Show lookup-cache statistics
- `php artisan auth-res:status` - Show authority-resolution pipeline status

## Backup & Disaster Recovery (ahg-backup)
### Backup Management
- View existing backups, create a new backup on demand, download a backup, and delete backups from the admin UI
- Configure backup settings (schedule, off-site replication driver, encryption) from the settings screen
- Email notifications on backup completion or failure
### Restore
- Restore from the admin UI, including uploading a backup file to restore from
- Granular restore of a single information object or a single table from a full backup
- Point-in-time recovery by replaying binary logs to a target wall-clock time
### Off-site Replication
- Replicate backups off-site via S3, rsync, or local-filesystem drivers, with optional encryption
### CLI Commands
- `php artisan backup:verify-integrity {--all} {--from=} {--driver=}` - Verify integrity of replicated backup files
- `php artisan backup:restore-io {id} {backup} {--yes}` - Restore a single information object from a full backup
- `php artisan backup:restore-table {table} {backup} {--where=} {--yes}` - Restore a single table (optionally a WHERE-filtered subset)
- `php artisan backup:pitr {target} {--dry-run} {--binlog-dir=} {--skip-full} {--database=}` - Point-in-time recovery to a target timestamp via binlog replay
- `php artisan backup:archive-binlogs {--dest=}` - Archive MySQL binary logs for PITR
- `php artisan backup:replicate {--driver=} {--force} {--no-encryption}` - Push backups to the configured off-site destination

## BIBFRAME Bibliographic Linked Data (ahg-biblio-bf)
### BIBFRAME Views & Editing
- Browse a BIBFRAME index and view a work as BIBFRAME (with Turtle and JSON-LD serialisations via the LOD/SPARQL endpoints)
- Edit a work's graph: update work fields, add/remove contributors, and add/remove subjects
- Edit-as-agent view for agent-driven graph editing
### Import / Export / Validate
- Export library records to BIBFRAME
- Import BIBFRAME data (create-gated by ACL)
- Validate BIBFRAME data before ingest
### Configuration
- Configurable serialisation formats (Turtle enabled) and default culture

## FRBR Work Clustering (ahg-biblio-frbr)
### FRBR Views
- Browse the FRBR index and view a work's FRBR structure
- View a public work-cluster page grouping editions/expressions of the same work
- Agent view for agent-driven FRBR work
### Import / Export / Validate
- Export records in FRBR form
- Import FRBR data (create-gated by ACL)
- Validate FRBR data before ingest
### Work Override Administration
- List, create, and delete manual work-key overrides (admin-gated)
- Manually cluster records into a shared work (admin-gated)
### Configuration
- Configurable default culture
### CLI Commands
- `php artisan ahg:frbr-backfill-work-keys {--batch=500}` - Backfill FRBR work keys across existing records in chunks

## Content Authenticity & C2PA Provenance (ahg-c2pa)
### Public Verification & Content Credentials
- Verify a record's authenticity by id or slug, and use a public "check" form to verify uploaded content credentials
- Inspect per-digital-object content credentials: detail view, verification badge (SVG + JSON), signed download, and downloadable `.c2pa` credentials
- View a per-record provenance trace (page + JSON)
- Browse the collection-wide "Trust at a glance" page, verified-records list, transparency report, and a content-credentials explainer (all with `.json` variants)
### Provenance Records (Admin)
- View content-credentials coverage across the collection
- List, create, store, and view provenance records per object, and fetch a signed C2PA manifest JSON
- View inference-provenance (AI-generation lineage), a preservation timeline, an authenticity report (with badge + JSON), and a per-object trust dossier
### CLI Commands
- `php artisan ahg:c2pa-embed {--id=} {--commit}` - Embed C2PA content credentials into master files (dry-run unless committed)
- `php artisan ahg:c2pa-provenance-backfill {--id=} {--limit=0} {--commit}` - Backfill provenance records for existing masters
- `php artisan ahg:c2pa-reverify {--limit=0} {--dry-run}` - Re-verify signed records and update sign status
- `php artisan c2pa:smoke {ioId} {output-text?} {--action=ai-generated} {--model=qwen3:14b} {--model-version=} {--no-write} {--sidecar-dir=}` - Emit a simulated AI-output manifest to smoke-test the C2PA pipeline
- `php artisan c2pa:verify {manifest-path} {--public-key=}` - Verify a C2PA sidecar/manifest against a stored or supplied Ed25519 public key

## Cart, Checkout and E-commerce (ahg-cart)
### Shopping Cart
- Browse a personal cart of collection items and see live item counts in the header
- Add any collection record to the cart from its page by slug
- Remove individual items or clear the entire cart in one action
- Merge a guest cart into the user's cart automatically on login
### Marketplace and Listings
- Add and remove marketplace listings to a separate marketplace cart
- Check out marketplace purchases, with a demo-checkout path for testing
### Checkout, Orders and Fulfilment
- Complete checkout for digital/physical products and reach a thank-you confirmation
- View a personal order history and per-order confirmation pages (authenticated)
- Download purchased digital deliverables via secure, tokenised download links
- Receive payment-gateway callbacks (payment notify) to confirm transactions
### Admin and Pricing
- Manage all customer orders and view order statistics from an admin console
- Configure e-commerce settings (enable/disable store, product types, pricing) under Admin Settings
- Set and save per-product-type pricing used to calculate cart totals

## Data Protection and Compliance Register (ahg-cdpa)
### Governance Setup
- Configure compliance plugin settings for the Cyber and Data Protection Act
- Appoint and edit a Data Protection Officer, with regulator-registration guidance
- Record and edit the data-protection licence held with the regulator
### Processing and Consent
- Register, create and edit records of personal-data processing activities
- Track data-subject consent captured across the platform
### Data-Subject Requests and Assessments
- Log, create and view data-subject access/erasure requests with auto-generated reference numbers
- Create and review Data Protection Impact Assessments (DPIA)
- Record and review personal-data breaches with a breach register
### Oversight
- View a compliance reports dashboard with deadline reminders
- See an audit view of compliance activity

## Condition Assessment and Reporting (ahg-condition)
### Condition Checks
- Start a condition check against any collection object by slug
- View a condition check and browse all checks recorded for an object
- Complete/close a condition check and record its condition rating
- Work from condition-assessment templates (list and view templates)
### Photo Documentation
- Upload condition photographs against a check (ACL-gated create)
- Browse the photo set for a check and delete photos (ACL-gated delete)
- Annotate condition photos (draw/mark damage) and save annotations (ACL-gated update)
### Admin and Risk
- View an admin condition dashboard with overall statistics and recent checks
- See a condition-by-rating breakdown and a risk view of at-risk objects
- Export a condition report for a check
### CLI Commands
- (no artisan commands; ships an AI dropdown seeder for condition vocabularies)

## Core Platform Framework (ahg-core)
### Access Control, Security and Encryption
- Enforce role-based access (administrator, editor, contributor, translator) with per-repository access checks
- Gate published-vs-draft visibility and redact ICIP/ODRL-restricted records through a disclosure gate
- Apply field-level encryption to configured sensitive columns, and encrypt/stream-decrypt files at rest
- Store and retrieve encrypted secrets (API keys, credentials) via a secret-crypto helper
- Guard outbound fetches against SSRF (private-range/host allow-listing)
### Digital Objects and Derivatives
- Ingest master digital objects and auto-generate image, PDF, audio, video, TIFF and 3D derivatives (thumbnails, previews, waveforms)
- Extract and re-extract embedded IPTC/XMP/EXIF metadata, GPS coordinates and resolution from masters
- Classify uploads by MIME, enforce max upload size and blocked file extensions
- Detect and store faces found in images (optional face-detection backend)
- Serve IIIF viewer settings and oEmbed embeds for records
### 3D, Point Clouds and Gaussian Splats
- Publish and view interactive 3D point clouds, converting .las/.laz/.ply sources to web format
- Publish and view Gaussian-splat scenes for records and digital objects
- Optimise/compress large 3D models to load faster in the browser
### Accessibility and Alt-Text ("every museum for everyone")
- Run a digital-accessibility coverage report (alt text, captions, transcripts, language reach) over the published collection
- Curate human-authored alternative text per image, with optional AI-drafted suggestions
- Publish an outward, human-readable accessibility statement
- Let visitors set accessibility/reading preferences
### Multilingual Access and Voice
- Translate any record's key metadata on demand into the visitor's language (display-only), including a "read in language" surface
- Detect the language a question was typed in and localise AI answers to it
- Show a language-coverage analytic - which languages the catalogue is readable in, and how much
- Provide text-to-speech (TTS) reading of records and PDFs, plus a voice-AI assistant
### Corpus-Grounded AI and Storytelling
- Let the public "Ask the Collection" and get answers grounded in the institution's own corpus via the sanctioned KM RAG gateway
- Generate curated stories/narratives from collection records, including "on this day" items, and publish story pages
### Preservation, Integrity and Capture Priority
- Score preservation maturity against the NDSA Levels using real evidence in the running instance
- Run a human-entered preservation self-assessment (start, edit, profile, export)
- Report fixity/integrity coverage (checksums) across holdings
- Capture web pages of records into WARC 1.1 files and replay them Wayback-style, entirely from the archive
- Rank records most in need of digitisation / most at risk of loss ("race against loss"), with a public board and a working capture queue (assign, status, export)
### Data Quality and Collection Insight
- Audit metadata completeness - which published descriptions are missing key fields
- Show visitor-facing "Collection at a glance", "Explore", and "Recently added" (HTML, JSON and Atom) overviews
- Publish open-data exports and an OpenSearch description + suggestions endpoint
### Sector Profiles and Identifiers
- Apply a sector site profile (archive/museum/gallery/library/dam/research) - theme, identifier mask, defaults - optionally with sample content
- Auto-generate sector-aware identifiers from configured numbering masks
- Bridge sector-local subjects into the cross-cutting AHG subject taxonomy for global browse/search
### Platform Services and Operations
- Drive site navigation menus, quick links and per-user plugin hide/show
- Resolve settings and dropdown vocabularies (with i18n, strict/valid-value enforcement) platform-wide
- Maintain hierarchy closure tables and answer ancestor/descendant queries at scale
- Resolve controlled-vocabulary labels (SKOS prefLabel/altLabel) from a triplestore behind a MySQL cache
- Run a built-in cron scheduler with due-schedule execution, run tracking and Prometheus metrics
- Sync errors/heartbeat/ping to an AHG Central monitoring hub
- Manage a GPU endpoint pool (register, health, endpoint selection) for AI workloads
- Enforce per-repository storage quotas and deliver in-app notifications to users and admins
- Maintain a personal clipboard (add/remove/save/load/sync/export CSV) of records
- Auto-install package database SQL idempotently, and monitor the storage NAS mount
### CLI Commands
- `php artisan ahg:pointcloud-convert {source} {--title=}` - convert a .las/.laz/.ply file into a web-viewable point cloud
- `php artisan ahg:pointcloud-setup` - set up point-cloud conversion tooling
- `php artisan ahg:splat-setup` - set up Gaussian-splat tooling
- `php artisan ahg:apply-sector-profile {sector} {--with-sample}` - apply a sector site profile (theme + identifier mask + defaults), optionally with sample content
- `php artisan ahg:optimize-models {--commit} {--min-mb=20} {--limit=0} {--id=}` - compress oversized 3D models (dry-run by default)
- `php artisan ahg:optimize-pdfs {--commit} {--min-mb=20} {--dpi=200} {--max-ratio=0.8} {--limit=0} {--id=}` - generate web-optimised PDF derivatives so large documents load fast
- `php artisan ahg:backfill-embedded-metadata {--limit=0} {--id=}` - re-extract full embedded metadata for master digital objects
- `php artisan ahg:capture-priority {--limit=0}` - list records most in need of digitisation / most at risk, with reasons
- `php artisan ahg:build-closure {--table=} {--all} {--verify} {--dry-run}` - build/rebuild hierarchy closure tables from parent_id
- `php artisan ahg:seed-scale-corpus {count=340000} {--chunk=2000} {--purge} {--build-closure} {--verify}` - seed a production-scale synthetic corpus for stress testing (dev only)
- `php artisan ahg:nas-watchdog {--force-notify} {--quiet-ok}` - probe the storage NAS mount and notify on state transitions
- `php artisan ahg:central-ping` - ping the AHG Central monitoring hub
- `php artisan ahg:central-heartbeat` - send a heartbeat to AHG Central
- `php artisan ahg:central-sync-errors {--batch=500}` - push open error rows to AHG Central

## Custom Fields Administration (ahg-custom-fields)
### Field Definition Management
- Create, edit and delete custom field definitions for different entity types
- Choose from multiple field types when defining a custom field
- Reorder custom fields to control their display order
### Data and Portability
- Store and retrieve custom-field values per entity
- Export custom-field definitions and import them back in

## DACS Archival Description Editor (ahg-dacs-manage)
### Standards-Based Cataloguing
- Edit an archival description against the DACS (Describing Archives: A Content Standard, 2nd edition) element set by slug (ACL-gated update)
- Fill DACS fields from managed dropdown vocabularies
- Save both simple and serialized (multi-value/structured) DACS properties

## Digital Asset Management (ahg-dam)
### Asset Catalogue
- Browse and view DAM assets, with slugged public asset pages
- Create, bulk-create, edit, update and delete assets (ACL-gated)
- View an asset's digital objects, related items, external links and version links
### IPTC and Metadata
- Edit embedded IPTC metadata on an asset (get/post edit-iptc)
- Manage format holdings, external links and version links per asset
- Take an audit snapshot of asset metadata
### Dashboards and Reports
- View a DAM dashboard with statistics and recent assets
- Run reports on assets, IPTC coverage, metadata completeness and storage usage
### CLI Commands
- `php artisan sector:dam-csv-import {filename} {--validate-only} {--mapping=} {--repository=} {--update=legacyId} {--update-mode=skip} {--culture=en} {--limit=} {--skip=0}` - import DAM CSV data with Dublin Core/IPTC validation (validate-only, mapping profiles, update/merge modes)

## Data Migration and Interchange (ahg-data-migration)
### Import
- Upload spreadsheets/CSV, auto-detect sheets, and preview mapped rows before importing
- Map source columns to target fields, save/rename/delete reusable mapping profiles, and import/export mappings
- Import information objects, accessions, actors and repositories with per-record validation
- Run and validate an "AHG import" path and review import/AHG results
### Export
- Export the catalogue to CSV, sector-specific CSV, AHG CSV and EAD XML
- Run batch exports and per-sector exports, and download generated files
### Jobs
- Queue migration jobs, watch live job progress, cancel jobs, and browse job history/status
### Preservica Integration
- Import from and export to Preservica (by job id)

## Dublin Core Description Editor (ahg-dc-manage)
### Standards-Based Cataloguing
- Edit an archival description against the Dublin Core Simple 1.1 element set by slug (ACL-gated update)
- Populate Dublin Core elements from managed dropdowns
- Save both simple and serialized Dublin Core properties

## Deduplication and Record Merging (ahg-dedupe)
### Duplicate Detection
- Scan the catalogue for probable duplicates (start scans, view scan status)
- Browse candidate duplicate pairs and compare two records side by side
- Get real-time duplicate hints via an API endpoint
### Resolution
- Dismiss single candidates or bulk-dismiss, and merge duplicate records (preview then execute)
- Split a previously-merged/conflated record back apart
- Work a dedupe work-queue of outstanding candidates
### Rules and Configuration
- Create, edit and delete matching rules (by rule type) and configure dedupe settings
- Browse duplicates by dimension - functions, identifiers, occupations, contacts
### Reporting
- View a dedupe dashboard and a duplicate report

## Hybrid Discovery Search (ahg-discovery)
### Multi-Strategy Search
- Search across information objects, actors and repositories with a hybrid engine
- Combine keyword, hierarchical (fonds-aware), vector/semantic and image-similarity strategies, fused with reciprocal-rank fusion
- Search by image (upload or by existing object) using image embeddings
- Expand queries with keyword/phrase/date-range extraction and synonym lookup
### Suggestions and Popularity
- Offer autocomplete suggestions as the user types
- Surface popular topics and log clicks/queries to improve relevance
### PageIndex Deep Content
- Build and query a PageIndex over deep document content (PDF/EAD/RiCO), extracting text via OCR and SPARQL
- Navigate an indexed document as a tree and jump to matching pages
- Build the discovery/page index from an authenticated build screen (GET/POST)

## GLAM Browse and Display (ahg-display)

### Public Browse and Discovery
- Browse the whole GLAM collection through a unified public browse page with faceted filtering
- Switch between display layouts - grid, list, card, catalog, masonry, gallery, hierarchy, and detail views
- Load browse results incrementally over AJAX for fast paging and filter refresh
- Explore records in a hierarchical tree view of the archival structure
- View a coverflow / imageflow carousel of digitised objects
- Print a formatted view of browse results
- Export the current browse result set to CSV
- Embed the GLAM browse experience in an external page via an embeddable browse frame

### Display Types and Profiles
- Auto-detect an object's display context (archive, museum, gallery, library, or DAM) from its level of description
- Assign display profiles to control how each record type is presented
- Set or change the display type of an individual record
- Bulk-assign display types across many records at once
- Manage the field sets and levels that drive each display profile

### Admin Configuration
- Toggle the GLAM browse feature on or off site-wide
- Configure, save, retrieve, and reset browse settings and default facets
- Rebuild the browse/search index from the admin display console
- Set per-user display preferences for the browse experience

### CLI Commands
- `php artisan ahg:populate-io-facet-denorm {--taxonomy=35,42,78} {--repository=} {--truncate} {--chunk=20000}` - Populate the denormalised facet sidecar table from object-term relations to speed up faceted browse

## Digital Object Identifiers (ahg-doi)
- (no user-facing functionality found)

## DOI Minting and DataCite Management (ahg-doi-manage)

### DOI Minting
- Mint a DataCite DOI for an individual record
- Deactivate (tombstone) a previously minted DOI
- Batch-mint DOIs across a selection of eligible records
- Synchronise DOI state and metadata with DataCite

### Queue and Monitoring
- View the pending DOI mint/registration queue
- Browse all records and their current DOI status
- Inspect an individual DOI record with its full metadata and history

### Configuration and Reporting
- Configure DataCite credentials, prefix, and minting behaviour
- Generate DOI activity and coverage reports
- Track DataCite Events (views, downloads, citations) against minted DOIs
- Email notifications on successful mint and on mint failure

### CLI Commands
- `php artisan doi:events-flush {--limit=200} {--max-attempts=5} {--dry-run}` - Retry pending or failed DataCite Events API submissions
- `php artisan metrics:back-fill {file} {--dry-run}` - Back-fill per-collection accuracy metrics into the AI model registry from a CSV
- `php artisan doi:funding-import {path} {--dry-run} {--delimiter=,}` - Bulk-import funding-reference rows for DOI metadata from a CSV file

## Donor and Deed-of-Gift Management (ahg-donor-manage)

### Donor Records
- Browse donor records (staff-only, since donors carry contact PII)
- Add, edit, view, and delete donor records
- View a donor's profile with linked accessions and agreements
- Autocomplete donor names when linking records

### Deed-of-Gift Agreements
- Manage a dashboard of all donor agreements and their status
- Add, view, edit, and delete deed-of-gift / donation agreements
- Link agreements to accessions and information-object records via autocomplete
- Track agreement reminders and surface agreements needing follow-up

### Data Protection
- Donor contact PII (email, city) is stored encrypted at rest

### CLI Commands
- `php artisan ahg:donor-encrypt-backfill {--dry-run}` - Encrypt existing donor contact PII for legacy donor actors (idempotent)

## Controlled Vocabulary and Dropdown Manager (ahg-dropdown-manage)

### Taxonomy Management
- Manage all controlled-vocabulary dropdowns from a single admin console
- Create, rename, and delete taxonomies
- Edit values sourced from three backends - the ahg_dropdown store, AtoM terms, and settings
- Move a taxonomy between sections of the manager

### Term Management
- Add, update, and delete individual terms within a taxonomy
- Reorder terms via drag-and-drop
- Set the default value for a dropdown

### Translations (i18n)
- Edit per-term translations inline; admins apply immediately while editors queue a translation draft for review

## Exhibitions, 3D Spaces and Reconstructions (ahg-exhibition)

### Exhibition Planning
- Create, edit, and manage exhibitions with a planning dashboard
- Organise exhibition objects, storylines, sections, and checklists
- Produce an object list and export it to CSV
- Manage exhibition events tied to an exhibition

### 3D Exhibition Space Builder
- Build a walkable 3D exhibition space with a visual floor-plan and builder editor
- Lay out rooms, doors, windows, stairs, corridors, walls, and floor shapes
- Place objects on walls, floors, and in display cases; adjust size, tilt, spotlight, z-order, and view spots
- Add furniture, poles, and custom furniture assets; upload floorplans, ceilings, wall/floor images, and colours
- Attach a 3D scan shell (GLB/splat/USDZ) and set scan metadata as the room backdrop
- Define a guided-tour walkthrough path with uploaded tour audio
- Place records held by federated peer institutions (remote scene placement) and sync objects to RIC

### Public Walkthrough and AI Docent
- Take a first-person walkthrough of a published exhibition space (standard, WebGPU, and next-gen renderers)
- Use a mobile companion view and an accessibility-oriented accessible tour
- Ask an AI docent grounded questions about an object or the whole room, with suggested questions
- Hold a multi-turn conversation with the room docent
- Auto-describe an object that has no metadata
- Hear narration via neural text-to-speech through the AI gateway
- Follow wayfinding floor plans with a "take me to X" object directory
- Leave wall annotations / graffiti and see live multi-user presence of other visitors
- Generate object recommendations for visitors

### Openings, Events and Visitor Analytics
- Publish exhibition openings with a public tokenised RSVP page and "join" link
- Manage opening status, mark RSVPs as paid, and delete openings
- Browse a public "What's on" listing of upcoming and live openings
- Capture visitor analytics events and view space analytics, forecasts, and attendance
- Ingest IoT sensor readings via a per-space token, or simulate readings

### Generative and Reconstructed Exhibitions
- Generate an AI-curated draft exhibition from a theme (suggest then build, two-step curator)
- Browse a public gallery of reconstructions of lost / destroyed places
- Play a reconstruction assembly montage and step through reconstruction stages
- Manage reconstruction records, stages, styles, annotations, and stage metadata

### Interoperability Exports
- Publish an open, CORS-enabled IIIF manifest, scene JSON, and JSON-LD for each exhibition space
- Print or produce a PDF-ready exhibition catalogue

### CLI Commands
- `php artisan ahg:lost-place-gather {place} {--limit=0} {--discover} {--json}` - Gather archival evidence linked to a place plus a reconstruction-coverage metric
- `php artisan ahg:lost-place-demo {--place=Crystal Palace} {--category=} {--count=12} {--remove}` - Build a public-domain evidence pack from Wikimedia Commons
- `php artisan ahg:lost-place-build-space {--place=Crystal Palace} {--remove}` - Build a walkable reconstruction space from a gathered lost place
- `php artisan ahg:lost-place-reconstruct {place} {--dry-run}` - Generate a TripoSR 3D model from a place's seed photo, tagged as AI-inferred with provenance
- `php artisan ahg:lost-place-reconstruct-3d {--place=Crystal Palace} {--format=glb} {--scale=5} {--structure-image=} {--multi=0}` - Generate the 3D structure on the GPU backend and set it as the room scan shell
- `php artisan ahg:crystal-palace-shell {--place=Crystal Palace} {--outdoor=1}` - Build the parametric Crystal Palace shell as the walkable room scan shell

## Metadata and Catalogue Export (ahg-export)

### Catalogue Exports
- Export information-object descriptions to CSV, filtered by repository and level of description
- Export archival descriptions and full archival packages
- Export accession records to CSV
- Export authority records (actors) to CSV
- Export repository records to CSV

### Standards Formats
- Export descriptions as EAD (Encoded Archival Description) XML
- View counts of accessions, objects, authorities, and repositories, and pick from available export formats before exporting

## Extended Rights, Embargo and Traditional Knowledge Labels (ahg-extended-rights)

### Object-Level Rights
- View, add, edit, and delete extended rights records against any object
- Check an object's effective rights and embargo state via a JSON API

### Embargoes
- Set, edit, and release an embargo on an individual object
- Manage all embargoes from an admin dashboard - create, edit, lift, extend, and process expired embargoes
- See embargoes expiring soon and act before they lapse

### Traditional Knowledge (TK) Labels and Orphan Works
- Assign and remove Traditional Knowledge labels on objects (per-object and in bulk)
- Record and manage orphan-work diligent-search cases, adding search steps and completing the search
- Manage rights statements and Creative Commons licences

### Admin, Batch and Reporting
- Batch-assign rights across many records at once
- Browse all rights records and generate rights reports
- Export rights data as CSV and as JSON-LD

### CLI Commands
- `php artisan embargo:process {--dry-run} {--notify-only} {--lift-only} {--warn-days=30,7,1}` - Auto-lift expired embargoes and send expiry warning notifications
- `php artisan embargo:report {--active} {--expiring=} {--lifted} {--expired} {--format=table} {--days=30} {--output=}` - Generate embargo reports in table or CSV form

## Favourites and Research Folders (ahg-favorites)

### Favourites
- Add and remove records from a personal favourites list, with instant AJAX toggle
- Bulk-manage favourites and clear the whole list
- Attach personal notes to a favourited record
- Search and check favourite status across the collection

### Folders and Organisation
- Organise favourites into colour-coded folders (create, edit, delete)
- Move favourites between folders individually or in bulk
- View the contents of a folder

### Sharing
- Share a folder via a public tokenised link with an expiry window
- Revoke a shared folder link
- View another user's shared folder read-only without logging in

### Research Bridge
- Send favourites to a research collection, research project, or bibliography (with citation style)
- Pull the current user's researcher collections, projects, and bibliographies for targeting

### Import and Export
- Export all favourites to CSV or JSON
- Export a single folder in multiple formats
- Import favourites from a file

## Federation, Union Catalogue and Inter-Institution Loans (ahg-federation)

### Peer Federation and Harvesting
- Register and manage federation peer institutions, with per-peer connection testing
- Configure per-peer search settings and metadata formats
- Run and monitor OAI-PMH harvests against peers, with a harvest log
- Governed by a global federation_enabled toggle that disables the whole feature at once

### Federated and Union Search
- Run anonymous federated search across peers, embeddable in the GLAM browse page
- View cross-institution provenance for a federated object
- Browse a public union catalogue aggregating opt-in records from every member (HTML and JSON)
- Provide a public, CORS-open union harvest API (paginated JSON and OAI-DC-style XML)
- Publish this institution's opt-in records into the shared union index

### Network Directory and Join Workflow
- Browse a public GLAM-network directory of member institutions (HTML and JSON)
- Submit a public "Join the network" request via an anonymous form with a thank-you confirmation
- Moderate incoming join requests from an admin queue
- Manage the union member registry, opt-in sharing config, and publish triggers

### Inter-Institution Loans
- Create, view, and manage inter-institution loan requests
- Transition a loan request through its workflow states
- View a loan-analytics dashboard aggregating loan activity (HTML and JSON)

### Trust, Governance and Europeana
- Run peer capability discovery and manage per-peer trust levels and governance policy
- Set an instance-wide "require verified peers" trust threshold
- Clear a peer's pinned key (TOFU) so the next verified fetch re-pins after a peer rotates keys
- Publish records to Europeana as EDM RDF/XML, and download the packaged ingest bundle

### CLI Commands
- `php artisan ahg:federation-harvest {--peer=} {--all-active} {--full} {--metadata-prefix=} {--from=} {--until=} {--set=} {--dry-run}` - Run an OAI-PMH harvest against one or more peers
- `php artisan ahg:federation-discover {--enabled}` - Crawl peers and cache their advertised Federation Query Protocol capabilities
- `php artisan ahg:federation-publish {--batch=500}` - Publish this institution's opt-in records into the federated union index
- `php artisan ahg:federation-vocab-sync {--peer=} {--taxonomy=} {--direction=pull} {--dry-run}` - Run a vocabulary sync against one or more peers
- `php artisan ahg:federation-search-cache-clean` - Delete expired rows from the federation search cache
- `php artisan europeana:export {--out=storage/europeana/} {--since=} {--culture=en}` - Serialise every published object to EDM RDF/XML, build a sitemap, and pack a Europeana ingest zip

## Feedback & Corrections (ahg-feedback)
### Public Feedback Submission
- Submit general feedback or a correction from any page, optionally tied to a specific archival description (information object) by slug
- Submit feedback against a record via its legacy record URL, with a success confirmation page after sending
### Admin Feedback Management
- Browse all submitted feedback with filtering by status (all, pending, completed)
- Open and view the full detail of an individual feedback submission
- Edit a submission to update its status and add internal admin notes
- Mark feedback as pending or completed to track resolution
- Delete a feedback submission

## Custom Forms & Templates (ahg-forms)
### Form Templates
- Create, browse, and manage reusable form templates for different entity types
- Build a template visually in the form builder: add, update, reorder, and delete fields
- Preview a template as it will appear to end users
- Clone an existing template to start a new one from it
- Export a template to a portable file and import a template from file
### Form-Driven Entity Editing
- Edit an information object, actor, repository, or accession through a form-template-driven editor
- Submit form-driven edits back to the underlying entity
- Resolve which active template applies to a given form type and context automatically
### Assignments & Library
- Assign templates to contexts so the right form appears where expected
- Create and remove template assignments
- Browse a shared library of available forms and view usage statistics
### Autosave & Integration
- Autosave in-progress form entry so work is not lost
- Retrieve the resolved form/template for a context via the forms API for embedded JS widgets

## FTP / Bulk File Upload (ahg-ftp-upload)
### File Upload
- Upload digital files to a configured remote FTP/storage target from the browser
- Upload very large files in chunks for reliability
- Test the configured FTP connection and confirm it is set up correctly
### Remote File Management
- List files already present on the remote target
- Delete an individual remote file or clear all uploaded files at once
### PDF Assembly & Linking
- Combine an uploaded folder of images/pages into a single PDF/A document in the background
- List combined PDFs that are ready to be linked to a record
- Attach a combined PDF to an existing archival record by its slug

## Functions & Activities (ahg-function-manage)
### Browse & Discovery
- Browse the register of functions/activities (ISDF-style functions)
- View a single function with its related authority records, related functions, and related archival resources
- Use function autocomplete when linking functions elsewhere
### Function Records
- Create a new function record
- Edit an existing function record
- Delete a function record (admin), with a confirmation step before removal

## Developer Function & Route Catalogues (ahg-functions-docs)
### Code Reference Catalogues
- Browse an operator/developer reference index of five auto-generated catalogues with per-catalogue link counts and source freshness (last-modified) info
- View the PHP classes and methods catalogue (paginated)
- View the JavaScript modules catalogue
- View the Blade templates catalogue (paginated)
- View the Python functions catalogue
- View the application routes catalogue (paginated)
- Filter within a catalogue and page through large catalogues (admin-only surface)

## Art Gallery Management (ahg-gallery)
### Artworks & Artists
- Publicly browse gallery artworks and browse artists, and view an individual artist's page
- Add, view, edit, and delete artwork records
- Create artist records and view artwork detail pages
### Loans, Valuations & Venues
- Record and browse artwork loans, and view individual loan detail
- Record and browse valuations, and view individual valuation detail
- Manage venues, view venue detail, and create new venues
- Generate a facility report for a venue/loan context
### Dashboard & Reports
- View the gallery management dashboard and gallery index
- Run gallery reports for exhibitions, facility reports, loans, spaces, and valuations
### CLI Commands
- `php artisan gallery:seed-demo {--source=} {--force} {--dry-run}` - Seed the 22 AI-generated demo gallery items from the docs image set, with dry-run and force re-seed options
- `php artisan sector:gallery-csv-import {filename} {--validate-only} {--mapping=} {--repository=} {--update=legacyId} {--update-mode=skip} {--culture=en} {--limit=} {--skip=}` - Import gallery data from CSV with CCO validation, configurable field mapping, target repository, update/merge modes, culture, and row limits

## Geographic Search / GIS (ahg-gis)
### Spatial Queries
- Search records within a bounding box (bbox) on a map
- Search records within a radius of a point
- Export matching records as GeoJSON for mapping and external tools (admin-only)

## GraphQL API (ahg-graphql)
### Query Playground
- Open an interactive GraphQL playground to compose and run queries (requires the read ACL grant)
- Introspect the schema to discover available types and queries
### Data Access
- Query archival descriptions (information objects) singly or in lists with limit/offset
- Query actors/authority records singly or in lists, and query repositories
- Query research projects, research collections, research annotations, and a researcher view, with public/visibility filtering applied per row

## Help & System Documentation (ahg-help)
### Help Centre
- Browse the help home page and help articles by category
- Read an individual help article
- Search help content across the knowledge base
### System Overview
- View an interactive system map of the platform's areas and features
- View a system breakdown of components and capabilities
### Article Cross-Linking
- Manage cross-links on a help article: view, add, and remove links to related articles (logged-in users)
- Contextual help offcanvas/sidebar surfaces relevant help on each page

## Heritage Asset Management & Accounting (ahg-heritage-manage)
### Public Heritage Portal
- Browse the heritage landing page, search heritage items, and explore by category
- View a timeline of periods and drill into a period's items
- Browse creators (with autocomplete), collections, and individual collection detail
- View an entity page (by id or by type/value), a relationship graph, and trending items
- Consume public JSON APIs for landing, discovery, autocomplete, hero slides, featured collections, explore categories, timeline periods, tag suggestions, entity data/related/search, and graph stats
- Log click and dwell interactions to power analytics
### Contribution & Access Requests
- Register, verify, log in, and log out as a public contributor
- Contribute information to a heritage item and track your own contributions
- Request access to restricted items and track your access requests
- Maintain a contributor profile
### Custodian & Review Workflows
- Run a custodian dashboard with per-item custodian views, batch operations, and history
- Work a review queue, review individual contributions, and view a contributor leaderboard
### Admin & Analytics
- Admin dashboard with management of access requests, embargoes, featured collections, feature toggles, hero slides, POPIA settings, users, and config
- Analytics dashboards for content, search, and alerts
### Heritage Asset Accounting
- Maintain a heritage asset register: add, browse, view (including by object), edit, and update assets
- Record valuations, impairments, journals, and movements against assets
- Configure accounting settings
- Manage a valuer registry (create, edit, delete valuers)
- Maintain an OCI / revaluation reserve ledger recording revaluations, impairments, reversals, and disposals, with accumulated surplus/impairment tracking and period summaries
### GRAP Compliance & Statutory Reports
- Run GRAP compliance checks on a single item or in batch, with a compliance dashboard
- Generate the National Treasury GRAP report
- Administer accounting standards, compliance rules, and regions (add/edit/list)
- Produce heritage reports: asset register, movement, and valuation reports
### CLI Commands
- `php artisan heritage:disclosure-note {--standard=grap-103} {--period=} {--out=}` - Render a statutory disclosure note (GRAP 103, IPSAS 45, or transitional) populated from live heritage data, for a given fiscal period, to stdout or a file

## Indigenous Cultural & Intellectual Property (ahg-icip)
### Communities & Consent
- Maintain a register of source/originating communities: browse, view, add, edit, and delete community records
- Record and manage community consent: list, view, add, and edit consent records
- Manage consultations with communities: browse, view, add, and edit consultations
### Traditional Knowledge Labels & Notices
- Manage Traditional Knowledge (TK) Labels
- Manage cultural notices and configure notice types
- Manage access restrictions on culturally sensitive material
- Acknowledge a cultural notice before proceeding
### Object-Level ICIP
- View and manage ICIP for a specific object: consent, cultural notices, TK labels, access restrictions, and consultations (via query-param, AtoM-style path, and object/{slug}/icip URLs)
### OCAP Overlay
- Run an OCAP (Ownership, Control, Access, Possession) dashboard and configure OCAP settings
- Set an object's possession status and assess/roll up/aggregate OCAP indicators across the collection
- Integrate with the Local Contexts Hub to query external TK/BC label data when enabled
### Reports & API
- Run ICIP reports: overview, pending consultations, consent expiry, and per-community reports
- Query object ICIP summary and check-access via JSON API endpoints
- All actions run under an ICIP audit trail; write actions are ACL-gated

## Archival Description Management (ahg-information-object-manage)

### Description CRUD and Metadata Standards
- Create, edit, rename, move, and delete archival descriptions (information objects) with full hierarchy support
- Browse and search descriptions with card-view and table-view result layouts plus advanced search
- View records rendered per descriptive standard: ISAD(G), DACS, RAD, Dublin Core, MODS, and museum/CCO forms
- Switch a record's display standard from the administration area
- Generate and preview identifiers live (auto-generated reference codes) and preview URL slugs before saving
- Autocomplete for actors, repositories, and subject/term access points
- Calculate creation/accumulation dates across a hierarchy
- Manage name access points, genre access points, alternative identifiers, and related material links
- Update publication/draft status per record (ACL publish-gated)
- Fix missing slugs and repair descriptions from the admin area

### Hierarchy, Treeview and Finding Aids
- Navigate collections through an interactive treeview and a full-width jstree page
- Drag-and-drop move records within the hierarchy; re-sort siblings; re-sync a subtree on demand
- Serve jstree hierarchy data feeds for external consumers
- Generate, upload, download, and delete finding aids (EAD-based) per description
- View per-record modification/audit-trail history

### Digital Objects and Media
- Upload single or multiple digital objects; bulk folder upload that mirrors folder structure as child records each with its own master object
- Add or link a digital object via a dedicated page; batch title-review step after multi-file import
- Edit, replace, and delete digital objects and individual representations (reference/thumbnail) without cascading to the master
- Stream digital objects with decrypt-on-stream for encrypted derivatives, with optional forced download
- Extract and transcribe audio/video media; view transcriptions as VTT, SRT, or JSON
- Create, list, export, and delete media snippets (time-based clips)

### Collections Management (Provenance, Condition, Museum/Heritage)
- Record and manage provenance across all sectors (archival, museum, gallery, library, heritage) with timeline view and CSV export
- Attach and download provenance supporting documents (deeds, bills of sale, catalogues)
- Create condition reports with photos and image annotations; AI-assisted condition assessment
- Capture Spectrum collections-management and heritage-object metadata forms
- Record museum-specific metadata (identity, physical location, accession areas)

### Rights, Privacy and Preservation
- Manage unified rights (PREMIS, extended ODRL rights, and embargoes) per record; export rights as JSON-LD
- Set and lift embargoes with scheduled lift dates
- Scan records for PII and personal data; review and save scan results
- Define and save redaction regions; serve redacted asset renditions to non-admin viewers while admins see originals
- Privacy dashboard plus DSAR request/status/complaint entry points
- Build, update, and export OAIS digital-preservation packages per record

### AI-Assisted Tools
- Extract named entities (NER) from record text and review/confirm extracted entities
- Summarize and translate record descriptions
- Generate AI descriptions for a digital object (aiDescribe)

### Research Tools
- Generate formatted citations for a record
- Assess source reliability and compute a trust score
- Create and manage research annotations; research-tools dashboard

### Import, Export and Reports
- Import descriptions via XML and CSV, validate CSV before import, and import SKOS taxonomies
- Export a record as Dublin Core, EAD/EAD3/EAD4, MODS, MARCXML, MARC, METS, PROV-O, CSV, and RiC-O JSON-LD
- Export OCR text per record as plain text, ALTO, hOCR, and PAGE XML
- Generate reports: item/file lists, box labels, inventories, storage-location lists, and physical-object lists
- Print-friendly record view

### CLI Commands
- `php artisan sector:archives-csv-import {filename} {--validate-only} {--mapping=} {--repository=} {--update=legacyId} {--update-mode=skip} {--culture=en} {--limit=} {--skip=}` - Import an archives CSV with ISAD(G) validation, with mapping profiles, targeted repository, update/merge/skip modes, culture, and row limits

## IIIF Collections and Interoperability (ahg-iiif-collection)

### Manifests, Collections and Viewers
- Browse IIIF manifest collections and view individual collections
- Serve IIIF Presentation manifests per object and collection manifests
- Create, edit, update, and delete manifest collections; add, remove, and reorder items; collection autocomplete
- Open the IIIF (Mirador) viewer per object and a side-by-side compare view

### IIIF Standards Endpoints
- IIIF Content Search 2.0 - full-text search and autocomplete within a manifest
- IIIF Change Discovery 1.0 - Activity Streams feed of manifest lifecycle changes for harvesters
- IIIF Content State 1.0 - encode and decode content-state references
- IIIF Auth 2.0 - probe, access, and token flow endpoints plus Auth 1.0 support pages (clickthrough, token iframe, success/failure)
- Serve canvas-scoped AnnotationPages of NER-tagged entities (ODRL-gated), and ingest annotations from an external AI/NER service via API-key auth

### Mirador Workspaces
- Save, list, load, update, and delete per-user Mirador workspace layouts; anonymous load-probe returns an empty layout cleanly

### Administration and Reporting
- Configure IIIF carousel/homepage settings
- IIIF manifest validation dashboard
- Media derivative queue view and media-pipeline test runner
- 3D object reports: digital objects, hotspots, models, thumbnails, and 3D settings

## AI Image Animation (ahg-image-ar)

### Animation Generation and Delivery
- Generate an AI image-to-video animation for a record's image via Stable Video Diffusion (frames, fps, motion strength, seed, optional prompt), routed through the AHG AI gateway
- Rebuild or delete an animation and its MP4 (deletion admin-gated)
- Stream animation MP4s with publication-status and ODRL gating (internal nginx X-Accel-Redirect), so draft/restricted animations are not publicly fetchable
- Admin settings page to configure the animation service and defaults

## Tamper-Evident AI Inference Receipts (ahg-inference-receipts)

### Signed Audit Chains for AI Calls
- Record each AI inference call as an append-only receipt capturing service, model id/version, input/output fingerprints, request/user/tenant ids
- Chain receipts with a SHA-256 hash chain so any modification or deletion breaks the chain at a detectable point
- Canonicalize payloads with RFC 8785 JCS for deterministic, cross-implementation hashing
- Sign each receipt with an Ed25519 detached signature for offline authenticity verification
- Verify a whole chain and report PASS or the exact sequence where it fails
- Cross-verify against the nobulex reference vectors (EU AI Act Article 12 / NIST AI RMF / ISO 42001 record-keeping)

## Guided Data Ingest (ahg-ingest)

### Ingest Wizard
- Run a step-by-step ingest wizard: configure, upload, map fields, validate, preview, and commit
- Download sector-specific ingest templates
- Map source columns to target fields and enrich rows before commit; view per-row validation errors and row counts
- Commit a session to create information objects, attach files, and build packages, tracked as a job
- Build OAIS packages during ingest via the packager service

### Large-File and SharePoint Upload
- Resumable chunked web upload for large files (>1GB) with status, complete, and abort controls
- Browse a SharePoint path and import selected files into an ingest session

### CLI Commands
- `php artisan ahg:ingest-commit {session}` - Run the commit step for a wizard ingest session (create IOs, attach files, build packages) and report processed/created/error counts

## Records Integrity and Retention (ahg-integrity)

### Fixity, Ledger and Runs
- Integrity dashboard with fixity ledger, integrity report, alerts, and dead-letter queue view
- View integrity runs and per-run detail
- Manage fixity schedules and integrity policies (edit and update)
- Export integrity data

### Legal Holds
- Create, list, and release legal holds; view per-record hold history; check whether a record is under hold

### Disposition and Destruction Certificates
- Disposition view; generate, store, and view destruction certificates for disposition events

### Retention, Declarations and Vital Records
- Record retention events
- Declare records and approve record declarations
- Flag, unflag, and periodically review vital records; list vital records and overdue reviews

## IPSAS Heritage-Asset Accounting (ahg-ipsas)

### Asset Register and Valuation
- Maintain an IPSAS asset register: list, create, view, and edit heritage/financial assets
- Record and list asset valuations
- Track impairments and insurance
- Generate IPSAS financial reports and manage the financial-year and module configuration

## Background Jobs (ahg-jobs)

### Job Monitoring
- Browse and view background jobs with detail (output/download path)
- Clear inactive jobs and export the job list as CSV
- Admin-gated across the surface

## Job Administration and Queue (ahg-jobs-manage)

### Job and Queue Management
- Browse and view jobs from the admin area; delete a job
- Clear inactive jobs and export jobs as CSV
- Browse the queue: queue batches, queue browse, per-queue-item detail, and a queue report

## Labels & Barcodes (ahg-label)
### Label Generation
- Open a label workspace for any catalogued item by slug and pick from auto-detected barcode/identifier sources: system identifier, ISBN, ISSN, LCCN, OpenLibrary ID, item barcode, call number, accession number, or title (preferred order ISBN > ISSN > barcode > accession > identifier > title)
- Generate a printable label with an embedded barcode and/or QR code linking back to the record
- Batch-print multiple labels onto a single sheet for a run of items
### Label Template Designer (admin)
- Browse, create, edit, and delete configurable label/barcode sheet templates (dimensions, layout, positioning) used to lay out printed sheets

## Landing Pages & Personal Dashboards (ahg-landing-page)
### Public Landing Pages
- View a published landing page at a friendly slug URL
### Page Builder (admin)
- List all landing pages, create new ones, and edit page settings
- Compose pages from stackable content blocks: add, update, delete, reorder (drag), duplicate, and toggle the visibility of individual blocks
- Track page versions and delete pages
### Personal Dashboards
- View a signed-in user's personal "My Dashboard", list saved dashboards, and create a new personal dashboard

## Integrated Library System (ahg-library)
### OPAC & Patron Self-Service
- Browse and search the public library catalogue (OPAC), with an availability-aware discovery search service
- View a bibliographic record's public OPAC detail page by slug, including cover images fetched by ISBN
- Place holds on titles, renew individual loans, and view a personal OPAC account (self-service)
- Dedicated OPAC patron portal: patrons log in with their own credentials to view loans, holds (and cancel them), fines, renew one or all loans, and log out
- Submit interlibrary-loan requests from the public OPAC
### Cataloguing, MARC & Metadata Quality
- Create, edit, rename, and delete library bibliographic records with slug preview and subject suggestion helpers
- Full MARC editor: create/edit MARC records, import MARC (form-based) with preview-then-commit, import binary MARC (ISO 2709) with preview, and download records as MARCXML or binary MARC
- ONIX ingestion (publisher feeds): upload ONIX XML, review parsed lines, set per-line status, and commit to the catalogue; includes a raw-XML API ingest endpoint with one-call commit
- Authority control: maintain authority records (actors), link/unlink them to catalogue items, and search authorities
- Copy cataloguing over Z39.50: manage remote targets and search them, then import matched MARC records
- ISBN lookup against configurable external ISBN providers (add/edit/toggle/delete providers)
- MARC validation and merge-preview (diff two records) tools, plus an ODI (Open Discovery Initiative) quality scorecard that scores every collection and can be refreshed
- FRBR override metadata and raw MARC control fields captured per record
- Catalogue reports: catalogue summary, creators, publishers, subjects, and call-number breakdowns
### Circulation, Patrons, Holds & Fines
- Circulation desk: barcode scan, check out (with per-item-type loan-day and renewal-limit rules), return, and renew loans; view a patron's loan history and current loans
- Place and cancel holds; auto-expire holds whose pickup window has passed
- Overdue management: list overdue loans, calculate/refresh overdue fines, and view configurable loan rules
- Patron management: create, view, edit, suspend, and reactivate patrons; auto-expire memberships past their expiry date
- Manage overdue-notice templates (edit and preview rendered notices) with tiered escalation
### Serials, Bindery & Interlibrary Loan
- Serials control: create/edit/clone/delete serials, record issues, manage subscriptions, predict next-issue arrivals, and view coverage/holdings statistics and issue history
- Serial claiming: list overdue claims and raise a claim for a missing issue
- Serials bindery (vendor consignment): batch issues to a bindery, send and receive bindery batches
- Interlibrary Loan (ILL): create/view/update/delete requests, drive a status workflow with valid-transition enforcement, suppress from OPAC, configure ILL settings, and escalate overdue borrowings
- Phase 2.5 ILL request manager with EDI dispatch (send an ILL request as an EDI message to a trading partner)
### Acquisitions, Vendors & EDI
- Acquisition orders: create/edit orders, drive an order status workflow, add/update/remove order lines, receive lines (one or all), and write off orders (GRAP 103 / IPSAS 17 disposal)
- Multi-fund line splitting: split an order line's cost across several budget funds
- Budgets: create/edit/delete budgets with automatic recalculation and an acquisitions dashboard
- Vendor management: create/edit/delete acquisition vendors
- EDI trading partners: manage partners, test connections, and preview outbound EDI messages (EDIFACT/EDI encode-decode)
### E-Resources, Usage Analytics & Developer API
- KBART e-resource holdings: import (preview + commit), export (TSV/CSV), download a template, and manage remote KBART feeds (create/edit/toggle/delete, test a feed URL, view fetch log, manual refresh)
- OpenURL 1.0 link resolver (public) for discovery tools and citation managers, with ISBN/ISSN normalisation and COinS context-object XML
- COUNTER usage analytics: record usage events (JS beacon), harvest usage from external partners via SUSHI 5.0, manage subscriptions/credentials with a connection test, and build/export COUNTER reports (PR, TR, TR_J1, TR_J3, DR, IR) as CSV or XLSX
- SUSHI 5.0 server endpoints (status, members, reports) so external systems can harvest this library's usage
- Acquisitions & serials JSON:API (key-authenticated) for vendors, budgets, orders, order lines, serials, serial issues, and subscriptions
- MARC cataloguing API: validate, merge, export, and import MARC records over HTTP
### CLI Commands
- `php artisan ahg:library-overdue-notices {--dry-run}` - send tiered overdue notices to patrons with past-due loans and log each send
- `php artisan ahg:library-calculate-fines` - sweep all overdue active checkouts and ensure each has a current overdue fine row
- `php artisan ahg:library-auto-expire-holds {--dry-run}` - mark holds whose pickup window has passed as expired
- `php artisan ahg:library-auto-expire-patrons {--dry-run}` - mark patrons past their membership expiry (with grace) as expired
- `php artisan ahg:library-serial-claim-alerts` - raise and email claims for overdue serial issues (active subscriptions only)
- `php artisan ahg:library-serial-expiry-alerts` - warn subscription contacts N days before a serial subscription-end date
- `php artisan ahg:library-kbart-refresh {--once= : Override feed ID and fetch only that feed}` - fetch all active KBART remote feeds and commit to the catalogue
- `php artisan ahg:library-email-usage-reports` - email the prior period's usage reports
- `php artisan ahg:library-odi-refresh` - recompute the ODI quality scorecard for every collection
- `php artisan ahg:library-backfill-authors` - upsert an authority record for every creator with a null actor and link them
- `php artisan sector:library-csv-import` - import library CSV data with MARC/RDA validation

## Loans & Touring Exhibitions (ahg-loan)
### Outgoing Loan Management
- Browse, search, create, view, edit, and delete outgoing loans with generated loan numbers and a loans dashboard
- Add and remove objects on a loan by searching the object catalogue
- Drive a loan status workflow (valid-transition enforced), extend loan periods, and record returns
- Attach and upload loan documents; track condition reports, facility reports, shipments, extensions, costs, and status history
- Dashboard statistics plus overdue and due-soon loan views
### Touring Exhibition Scheduling
- View an object's touring schedule/calendar
- Check availability and book an object into a tour slot with conflict detection, and cancel a booking

## Art Marketplace (ahg-marketplace)
### Public Browsing & Discovery
- Browse and search listings, filter by category, sector, seller, or collection, view featured listings and auction listings, and open a single listing page
- Submit a public enquiry form; choose to register as a buyer or seller
### Buyer Experience
- Buyer dashboard, buy-now and auction checkout via PayFast, and payment return/cancel handling
- Place bids, submit and manage offers (with counter-offers), and post reviews
- My-bids, my-following, my-favourites, my-offers, my-purchases, and my-licences pages; toggle favourites via API
- Reserve a listing (12-hour hold, max 2 per user per 24h) and cancel a reservation
- Follow sellers/listings
### Seller Workspace
- Seller registration, profile, and analytics/payouts/reviews (read-only)
- Manage listings: create, edit, publish, withdraw, upload images, and pay to feature a listing (30-day promotion via PayFast)
- Manage collections, respond to offers, view and act on enquiries and transactions
- Broker/agent mode: manage multiple artists on whose behalf the seller acts (create/edit/delete artist profiles)
### Admin Console
- Admin dashboard, listing review/approval, category and currency management, seller verification, transactions, and reports
- Payouts: view, batch-process, and upload a CSV payout batch (two-phase commit), plus review moderation and marketplace settings
### Payments
- PayFast integration: signed checkout URLs, sandbox/live switching, and a server-verified ITN webhook (signature + IP + server-to-server validation)
### CLI Commands
- `php artisan marketplace:reservation-notify` - dispatch 6h / 1h / expiry reservation reminder emails
- `php artisan ahg:marketplace-feature-expire` - demote listings whose featured_until window has closed
- `php artisan marketplace:assign-gallery-items` - create one marketplace listing per gallery information object under a single seller

## Media Derivative Processing (ahg-media-processing)
### Derivative Generation (admin)
- View a media-processing dashboard listing masters, missing derivatives, recent derivatives, and stats
- Regenerate thumbnail and reference derivatives for a single digital object, or batch-regenerate across many
- Queue processing jobs and clear the queue
- Configure derivative settings (sizes/formats) and process photos through the pipeline
### Watermarking (admin)
- Configure watermark settings globally and per object, choose watermark types, and upload/delete custom watermark images
- Apply watermarks to images and refresh the Cantaloupe image-server cache

## Media Streaming & Captions (ahg-media-streaming)
### Adaptive Media Streaming
- Stream audio/video for a digital object with HTTP Range request support (206 Partial Content) for seeking
- On-the-fly transcoding to browser-playable formats (video to MP4, audio to MP3) when the source format needs it, with ffmpeg/ffprobe media-info and duration probing and video-thumbnail generation
### Caption & Subtitle Tracks
- Serve WebVTT caption tracks directly to video players (public endpoint)
- Manage caption tracks per digital object: list, create, edit, update, delete, and toggle active
- Fetch a caption track from a remote URL and store it locally

## Navigation Menu Management (ahg-menu-manage)
### Menu Administration (admin)
- Browse the site navigation menu as a tree, view a single menu node, and add, edit, or delete menu items (with delete confirmation)
- Reorder items by moving them up or down, with nested-set rebuild and protection for system-critical menus
### Legacy URL Aliases
- Redirect legacy AtoM authentication URLs (/cas/login, /oidc/login, /user/login, /user/logout, /donor/dashboard) and /admin/menus to the current Laravel routes

## Archival Metadata Export & Import (ahg-metadata-export)
### Standards-Based Export (admin)
- Export a record's descriptive metadata and preview it in many standards: Dublin Core Qualified (dcterms), MODS, RAD (Canadian), DACS (US), EAD 2002, EAD 3, EAD 4, EAC-CPF, EAC-CPF 2.0, EAC-F functions, EAG, MARCXML, binary MARC21, METS (with PREMIS), MODS, PROV-O, and RAD
- Bulk export screen plus per-standard XML download (dcterms / mods / rad / dacs)
- CIDOC-CRM (ISO 21127) RDF download for a record, an actor, or a term/place, negotiated as Turtle (default) or RDF/XML by query flag or file extension
- PREMIS 3.0 preservation-metadata download (fixity, size, format, original name per digital object; preservation events; responsible agents)
### Metadata Import (admin)
- MARCXML import with upload, preview, and commit
- Native EAD 2002 and EAD 3 XML import with upload, parse, preview, and commit
- RAD and DACS XML import (dry-run preview JSON by default, commit persists to ahg_io_rad / ahg_io_dacs)
### Linked Open Data
- SPARQL 1.1 query endpoint over a record's PROV-O graph, authenticated by session or a Bearer token
- Public, CORS-open, unauthenticated whole-collection CIDOC-CRM bulk dump at /data/cidoc-crm.ttl (published records only), streaming a pre-built dump or generating a bounded graph on demand (Open Memory Protocol open-data line)
### CLI Commands
- `php artisan ahg:export-cidoc-graph` - stream the whole published catalogue into one combined CIDOC-CRM (ISO 21127) Turtle dataset
- `php artisan ead:finding-aid {ioId} {--out=} {--culture=en}` - generate a print-friendly PDF finding aid (Library of Congress style) for an information object and its descendants

## Embedded Metadata Extraction (ahg-metadata-extraction)

### Extraction Dashboard
- View a dashboard of extraction-tool availability and statistics, with a browsable list of digital objects and their extraction status
- Check a status page reporting installed tool versions (exiftool, ffprobe, pdfinfo) and overall extraction counts

### Metadata Operations
- Extract embedded metadata from a single digital object on demand
- Batch-extract metadata for all digital objects that have no prior extraction
- View the full set of extracted metadata properties for a digital object
- Delete previously extracted metadata for a digital object

### Supported Formats
- Read EXIF, IPTC, and XMP from images
- Read PDF document info (title, author, keywords, creator, producer, page count and size)
- Read video technical metadata (duration, codec, resolution, frame rate, bitrate) via ffprobe
- Read audio technical metadata (duration, bitrate, sample rate, channels, codec) via ffprobe

## MODS Metadata Editing (ahg-mods-manage)

### MODS Record Editor
- Edit a record's MODS 3.3 descriptive fields: title, identifier, language, resource type, repository, access conditions, scope and content, and subject / place / name access points
- Manage a record's origin information as MODS creation and publication events, including creation and publication dates (display text plus ISO 8601), publisher (linked actor or free text), and place of publication
- All editing and publishing is permission-gated (ACL update) behind authenticated admin access

## Multi-Tenancy and Branding (ahg-multi-tenant)

### Tenant Administration
- Create, edit, and delete tenants
- List all tenants and view a tenant's assigned users
- Manage super-users who span tenants

### Per-Tenant Branding
- Configure per-tenant branding (look and feel) for a tenant
- Resolve the active tenant from the request domain, with friendly error pages for unknown domains or unknown tenants

### Tenant Switching and Access Control
- Switch the active tenant from an in-app tenant switcher
- Enforce combined access control where both the archival ACL and the tenant role must allow an action (Heratio admins bypass the tenant-role gate but still pass ACL)
- Store uploads, backups, and other storage under per-tenant scoped paths

### CLI Commands
- `php artisan multi-tenant:assign-rows {table} {tenant} {--where=} {--dry-run} {--force}` - bulk-assign a tenant id onto rows of an existing table, with optional SQL filter, dry-run row count, and a force flag for tables lacking a tenant_id column

## Museum Collections Management (ahg-museum)

### Object Cataloguing
- Browse, view, create, edit, and delete museum objects catalogued to the Spectrum 5.0 standard
- Upload multiple media files against a single object
- Compare two objects side by side

### Dashboards and Reports
- View a museum dashboard of collection statistics
- Run collection reports by objects, creators, condition, provenance, style/period, and materials
- View a per-object condition report, provenance history, GRAP (asset accounting) dashboard, and loan dashboard

### Data Quality and Standards Export
- View a data-quality dashboard and drill into objects missing a given field
- Export the collection as CIDOC CRM and download the export
- Link and unlink objects to authority records, and view an object's Getty links

### Vocabulary and Authority Lookup
- Autocomplete against the Getty Art and Architecture Thesaurus (AAT) via its SPARQL endpoint
- Search controlled vocabularies and authority records for form fields

### CLI Commands
- `php artisan sector:museum-csv-import {filename} {--validate-only} {--mapping=} {--repository=} {--update=legacyId} {--update-mode=skip} {--culture=en} {--limit=} {--skip=}` - import museum objects from CSV with Spectrum 5.0 validation, configurable field mapping, target repository, update/merge matching, culture, and row limits

## MVA Accident Fund Claims (ahg-mva-claims)

### Public Claimant Portal
- Register, log in, and reset password as a member of the public (claimant guard, separate from staff)
- Create, view, and submit motor-vehicle-accident claims and track their status by reference
- Upload supporting documents to a claim and download them back
- See a live completeness checklist telling the claimant exactly what the claim is still missing before assessment
- Grant and withdraw versioned POPIA consents (recorded, never deleted, so prior consent wording is provable later)
- Chat on a claim thread and ask an AI assistant grounded on the claim's own state, plus a general AI question channel

### Installable Mobile App (PWA)
- Install the claims app on a phone from a QR/install page (or download an APK), backed by a web manifest, service worker, offline page, and app icon/logo
- Use a JSON API (session-cookie auth on the claimant guard) for the mobile app to register, log in, and manage claims, documents, and consents

### Legal Representative and Practitioner Portals
- Attorneys register, log in, manage profile/password, create claims on behalf of clients, upload documents, grant/withdraw consent, submit claims, message, and ask AI
- Medical practitioners register, log in, and supply requested medical records through a dedicated portal

### Officer Case Management
- Log in as an Accident Fund officer (staff on the web guard with an mva_officer capability, distinct from claimants)
- Work a claims queue: view a claim, transition its stage, route it for assessment (medical vs general, role-gated), reassign, and escalate or clear escalation
- View, annotate, and download claim documents, and run OCR/HTR text extraction plus named-entity extraction on a document
- Message with the claimant and read a per-claim audit trail

### Operations and Management Reporting
- View an operations dashboard: claim volumes, the 120-day SLA clock with traffic-light aging, per-officer and per-unit workload, turnaround, and completeness
- Export dashboard reports as CSV and download a browsable management report as PDF (Dompdf) or Word .docx (PhpWord)

### Administration (Supervisors)
- Manage a settings hub, full cross-claim audit log, and a feedback inbox (with a public floating-feedback button)
- Configure branding, mail settings (with a test send), and AI settings (with a connectivity test)
- Administer users: add/revoke officers, set officer role and unit, toggle/delete claimants and legal reps
- Manage organisation structure (units), and in demo mode swap your own role

### Trust and Safety
- Every uploaded file is virus-scanned (ClamAV, scanner-absent fails closed for public uploads), format-identified against PRONOM (Siegfried), and written encrypted into a private claim store that never becomes a public digital object
- Electronic signatures (typed and/or drawn) are bound to a hash of the signed text, HMAC-sealed, and written to a tamper-evident, hash-chained audit log
- AI calls route only through the AHG AI gateway, never a GPU node directly

### CLI Commands
- `php artisan mva:make-officer {user_id}` - grant an existing Heratio staff user the MVA officer capability
- `php artisan mva:seed-requirements` - seed the Accident Fund document requirement catalogue
- `php artisan mva:seed-demo-rich` - seed a varied demo dataset (drafts, stages, legal-rep, hold, rejected, chat)
- `php artisan mva:seed-demo-medical {--count=2}` - seed complete lodged demo medical (injury/death) claims for routing demos

## NARSSA Archival Transfer Packaging (ahg-narssa)

### Transfer Package Builder
- Build a NARSSA transfer package as a .tar.gz containing a manifest.csv, a METS wrapper, per-item EAD2002 descriptions, and SHA-256 checksums (National Archives sector deployment; Phase A is CLI-only, with web transfer browse/status/retry/download planned for Phase B)
- Package either an explicit list of information objects or every approved retention/disposal action not yet packaged

### CLI Commands
- `php artisan narssa:transfer-package {--io-ids=} {--user-id=} {--title=} {--description=} {--from-approved}` - build a NARSSA transfer package from specified information-object ids or from all approved (unpackaged) disposals, with optional batch title/description and initiating user

## National Archives of Zimbabwe (ahg-naz)

### Compliance Dashboard
- View a NAZ dashboard summarising active/expiring closures, active/pending research permits, local vs foreign researchers, pending and year-to-date transfers, active schedules, and protected records
- See an automated compliance status flagging overdue closure periods, expired permits, and transfers past their proposed date

### Access Restriction and Protection
- Create, edit, and manage closure periods on records (with configurable default closure years)
- Maintain a register of protected records

### Retention and Disposal
- Create and update records retention schedules with disposal actions

### Researchers and Permits
- Register researchers (local and foreign) and create, view, and update research permits (with configurable validity and foreign permit fee)

### Transfers, Reports, and Audit
- Create, view, and update record transfers/accessions
- Run reports by closures, protected records, schedules, permits, researchers, and transfers with date filtering
- View a NAZ audit log and edit NAZ configuration settings

## National Museums and Monuments of Zimbabwe (ahg-nmmz)

### Heritage Registers
- Maintain a monuments register (create, list, view) with categories and inspection history
- Maintain an antiquities register (create, list, view)
- Maintain an archaeological sites register (create, list, view)

### Permits and Assessments
- Manage export permits for antiquities, including approving (with conditions) or rejecting (with reason) a permit
- Record Heritage Impact Assessments (HIA)

### Dashboard, Reports, and Config
- View a dashboard of heritage statistics and a compliance status (e.g. export permits awaiting review)
- Run reports and configure NMMZ settings (antiquity age threshold, export permit fee/validity, director and contact details)

## OAI-PMH Metadata Harvesting (ahg-oai)

### OAI-PMH 2.0 Provider
- Expose published records to external harvesters through a spec-compliant OAI-PMH 2.0 endpoint (GET and POST) implementing all six verbs: Identify, ListMetadataFormats, ListSets, ListIdentifiers, ListRecords, GetRecord
- Disseminate records in Dublin Core (oai_dc) and MODS 3.5 metadata formats
- Support selective harvesting by date range and set, with resumption tokens for large result pages and standard OAI error responses
- Serve a human-readable OAI documentation page, and rate-limit harvesting (120 requests/minute per IP)

### CLI Commands
- `php artisan oai:mark-deleted {oai_local_identifier?} {--reason=} {--all-unpublished} {--list}` - record an OAI-PMH tombstone so harvesters can clean up a deleted record; can tombstone one record, tombstone every unpublished record that has an OAI identifier, or list current tombstones

## Metrics and Observability (ahg-observability)

### Prometheus Metrics Endpoint
- Expose a Prometheus-format /metrics endpoint authenticated by bearer token and/or an IP allow-list (no session or CSRF, so a scraper needs no cookie)
- Publish HTTP request counters and latency histograms, database query counts and duration histograms, a queue-depth gauge, and an AI-compliance pass/fail gauge

### Tracing and Alerting
- Trace and record database queries and request timings for the metrics registry
- Ship alert rules (heratio.rules.yml) for a Prometheus alerting stack

### CLI Commands
- `php artisan observability:record-queue-depth` - sample queue depth for each configured connection/queue and set the heratio_queue_depth gauge
- `php artisan ai-compliance:emit-metrics {--dir=} {--dry-run}` - run the AI inference-log compliance check and write a Prometheus textfile gauge (1=PASS, 0=FAIL), with an optional output directory override and dry-run to stdout

## OCFL Object Storage (ahg-ocfl)
### Preservation Storage
- Initialise an OCFL v1.1 storage root (namaste declaration + layout descriptor) on a configured local, S3, or Wasabi disk
- Snapshot an information object's digital files into a new OCFL object, or add a new immutable version to an existing one
- Choose the object-root layout (flat-id, pairtree, or hashed-n-tuple) and digest algorithm (sha512 or sha256) to suit collection size
- Track the mapping between Heratio information objects and their OCFL objects via the object map table

### Fixity and Integrity
- Validate fixity and structure for a single OCFL object or verify the entire storage root in one pass
- Preserve content-addressed, versioned copies of masters so bytes and history survive format migration

### Embedded Metadata Preservation
- Capture intrinsic content metadata (EXIF camera make/model, IPTC byline, XMP title/rights, capture timestamp) into an ahg-embedded-metadata inventory extension at version-creation time
- Apply a PII gate to redact sensitive embedded metadata before it is written into the inventory
- Backfill the embedded-metadata extension into existing OCFL objects that predate the feature, with dry-run preview and per-object scoping

### Export
- Export any OCFL object to a self-contained tarball under storage/ocfl-exports for handoff or offsite copies

### CLI Commands
- `php artisan ocfl:init {path?}` - Initialise an OCFL v1.1 storage root (namaste + layout descriptor)
- `php artisan ocfl:ingest {ioId} {--message=}` - Snapshot an information object's digital files into OCFL as a new object or new version
- `php artisan ocfl:verify {ioId?}` - Validate fixity and structure for one OCFL object or the entire storage root
- `php artisan ocfl:export {ioId}` - Export an OCFL object to a tarball under storage/ocfl-exports/
- `php artisan ahg:ocfl:backfill-embedded-metadata-extension {--object=} {--dry-run} {--limit=0}` - Add the ahg-embedded-metadata extension to existing OCFL objects that lack it

## PDF and TIFF Tools (ahg-pdf-tools)
### PDF Assembly
- Merge multiple uploaded image/TIFF files into a single PDF from the admin PDF Tools page
- Choose the output page size and optionally produce an archival PDF/A (with selectable PDF/A version) during merge
- View which conversion engines (pdftotext, ImageMagick, Ghostscript) are available and which input formats are supported before merging

### Text Extraction
- Extract text from a single uploaded PDF on demand and view the result
- Run batch text extraction across up to 50 repository PDFs in one action
- Review text-extraction statistics (count of digital objects with extracted text) on the dashboard
- Store and retrieve extracted PDF text per digital object for search and reuse

### Bulk Folder Combine
- Combine a whole server folder of page TIFFs/images (in filename order) into one memory-safe PDF/A for large-volume digitisation runs
- Tune output DPI and JPEG quality, attach the resulting PDF/A to an information object as its master, auto-create a web-optimised derivative, and optionally clear source files (FTP-upload workflow)

### Retention and Cleanup
- Automatically quarantine combined source files and purge them after a configurable retention window (default 7 days, set via the pdf_combine_trash_days setting), with a daily scheduled sweep at 03:30 and a dry-run preview option

### CLI Commands
- `php artisan ahg:pdf-combine {folder} {--out=} {--dpi=200} {--quality=85} {--id=} {--no-web} {--clear-source}` - Combine a server folder of TIFFs into one memory-safe PDF/A, optionally attaching it to an information object
- `php artisan ahg:purge-combine-trash {--days=} {--dry-run}` - Purge quarantined combine source files past the retention window

## Portable Export Packages (ahg-portable-export)
### Build Offline Catalogue Bundles
- Generate a self-contained offline catalogue viewer that opens in any modern browser with no server or internet, via a 4-step wizard (Scope, Content, Configure, Generate)
- Scope an export to the entire catalogue, a specific fonds/collection (type-ahead search by title or identifier), a repository, or a clipboard selection
- Choose an export type: Viewer (read-only HTML browsing), Editable (viewer with offline notes, sources, suggestions and file capture), or Archive (re-importable JSON plus digital objects with selectable entity types)
- Pick which digital object derivatives to include - thumbnails, reference images, full-resolution masters, or metadata only
- Estimate export size, record counts and duration before generating
- Set the viewer title, language (English, French, Afrikaans, Portuguese) and optional custom branding (title, subtitle, footer)
- Choose a destination - downloadable ZIP, streamed large download (no size limit), or an uncompressed dump straight to a server folder / mounted drive

### Offline Research and Sync
- Work offline inside an editable package - add research notes, sources and citations, metadata correction suggestions, and files to any record
- Save all offline changes as a researcher-sync JSON file to bring the work back into Heratio under Research > Work Offline
- Open a shipped plain-language README and, for editable packages, a step-by-step researcher user manual

### Manage and Share Exports
- Track export progress in real time and download the finished bundle
- Review a history of past exports showing status, description counts, size, expiry date, and a badge for records withheld by confidentiality gates
- Mint a token-gated public share link (optional expiry in hours and optional download cap) so a recipient can download a bundle without logging in
- Import a previously exported archive package (ZIP upload)
- Delete an export and its generated files

### Disclosure and Access Safeguards
- Automatically withhold unpublished/draft records, ICIP/TK culturally restricted records, and ODRL access-gated records from offline packages
- Withhold derivatives of records carrying PII visual redaction
- Trim every export to the exporting operator's own role/ACL, dropping master, reference or thumbnail tiers and draft records they are not permitted to read
- Write a disclosure summary into each bundle recording what was included, what was withheld, and why
- Enforce an install-wide bundle size cap and a master kill-switch that disables the whole feature

### CLI Commands
- `php artisan ahg:portable-export-worker {--id=} {--all-pending}` - Process pending portable export requests: dump entities to JSON, copy digital-object assets, build the offline viewer, and zip the bundle (queue-driven, with a daily safety-net sweep)

## Digital Preservation and OAIS Repository (ahg-preservation)
### OAIS Information Packages
- Create a Submission Information Package (SIP) from an information object as the entry point to the preservation lifecycle
- Promote a SIP into an Archival Information Package (AIP), or build an AIP directly from an information object in one step
- Generate a Dissemination Information Package (DIP) from an AIP, filtered by file path or MIME type prefix, for access and delivery
- Trace the full lineage of any package (root-first ancestors and descendants) to see how SIP, AIP and DIP relate
- Browse all preservation packages, view package contents and payload/metadata roles, and edit package details

### BagIt Packaging and Fixity
- Build a BagIt v1.0 package from an information object with a sha256 payload manifest
- Validate a BagIt package to confirm its manifest and structure are intact
- Generate a checksum (sha256) for a digital object and verify its fixity on demand from the admin console
- Review the fixity log filtered by status, and surface stale objects whose last fixity check is older than a chosen threshold
- Define scheduled fixity workflows (cron-driven, with per-schedule batch limits and run history) and run due schedules automatically, gated by the global integrity toggle
- View live preservation statistics via a JSON stats endpoint

### Format Identification and Format Risk
- Identify file formats with a pure-PHP PRONOM engine (PUID, format name/version, MIME) and, when installed, the external Siegfried tool
- Classify each format by preservation risk (low/medium/high/critical) and whether it is a recognised preservation master format
- Batch-identify unidentified objects and review a risk distribution, confidence bands (certain/high/medium/low) and identification warnings
- Report on high-risk-format objects, objects without checksums, and stale-fixity objects

### Normalization and Format Migration (FPR)
- Maintain a normalization rule registry (Format Policy Registry) matching by PRONOM PUID or MIME type and purpose, with create, edit, enable/disable and delete
- Automatically generate preservation masters and access copies on ingest, choosing the conversion tool per rule, with idempotent skip when a derivative already exists
- Backfill preservation masters or access copies across the existing catalogue, filtered by MIME type, inline or queued
- Monitor conversions on a dashboard and re-queue a failed normalization

### Malware Scanning and Condition Triage
- Scan digital objects for malware with ClamAV and review scan posture (clean/infected/error counts, unscanned-object count, scanner and signature-database version)
- Rank the catalogue by preservation risk on a triage board, scoring condition-report rating, priority, overdue next-check date and assessment staleness into critical/high/medium/low bands
- Record and browse PREMIS preservation events (fixity checks, identifications, conversions) filtered by object and event type

### PREMIS and Preservation Rights
- Export a PREMIS 3.0 XML document for an information object, optionally validating it against the bundled PREMIS XSD
- Project ODRL rights into PREMIS rights statements so rights travel with the preservation metadata

### Backup, Replication and Policies
- Manage replication targets and review backup verifications and replication logs
- Maintain preservation policies (typed, cron-scheduled, with last-run/next-run tracking)

### TIFF/PDF Merge
- Create a TIFF/PDF merge job, upload page images, reorder or remove pages, then process them into a single merged PDF (also embedded in the add-digital-object workflow)
- Browse, view, download and delete merge jobs from the admin console

### CLI Commands
- `php artisan premis:export {ioId} {--out=} {--refresh-rights} {--validate}` - Export a PREMIS 3.0 XML document for an information object, optionally re-projecting ODRL rights and validating against the bundled XSD
- `php artisan ahg:normalize-existing {--purpose=preservation} {--limit=0} {--mime=} {--sync}` - Backfill preservation masters / access copies for existing digital objects
- `php artisan preservation:scan {ioId?} {--stale-days=90} {--limit=50} {--tools=}` - Run format identification + malware scan across one object or all stale objects (siegfried, clamav, null)
- `php artisan ahg:preservation-fixity-run {--force} {--schedule=} {--max-batch=}` - Run due fixity-check schedules from the preservation workflow scheduler

## Data Protection and Privacy Compliance (ahg-privacy)
### Data Subject and Access Requests (DSAR / PAIA)
- Let a data subject submit their own access request and check its status through a self-service surface (ownership scoped in-controller)
- Lodge, edit, list and view DSARs as a data protection officer or admin
- Scope a DSAR to specific archival records and pre-populate their field redaction profiles
- Build a single self-contained, redacted subject-access export package for a verified DSAR
- Manage PAIA (access to information) requests
- Receive a daily email alert about DSARs whose due date has passed and which are not yet closed

### Breaches, Complaints and Consent
- Record, edit, list and view personal-data breaches
- Capture privacy complaints via self-service submission and handle them as an admin
- Track consent records and withdraw consent with a logged reason
- Maintain data protection officers/contacts and per-jurisdiction configuration

### Records of Processing and Impact Assessments
- Maintain the GDPR Article 30 register (Records of Processing Activities) with full create/edit/delete and a regulator-ready export
- Screen processing activities for DPIA-required high-risk triggers (special category data, large-scale profiling/monitoring, biometric/genetic processing, non-adequate cross-border transfer)
- Run the DPIA workflow: draft, edit, move to review, tamper-evident sign-off, and archive
- Route ROPA entries through an approval workflow - submit for approval, approve, or reject

### PII Detection and Redaction
- Scan archival descriptions for PII (email, phone, national ID, credit card, IP address, date of birth) with per-jurisdiction sensitivity (GDPR, POPIA, UK GDPR, CCPA)
- Detect PII embedded in image metadata (EXIF / IPTC / XMP) and review or resolve those findings
- Apply field-level structured redaction so non-privileged viewers see masked metadata while admins see the original, logging each decision with field name and legal basis
- Mask sensitive regions of images in a visual redaction editor
- Surface a field-redaction panel injected onto the archival description detail page

### Compliance Autopilot and Control Catalog
- Scan the catalogue for personal data and auto-draft a ROPA / Article 30 entry, pre-filled with detected data categories, for a DPO to review and save
- Auto-draft retention schedules and DPIAs from scan results, with DPO accept
- Browse and search the vendor- and jurisdiction-agnostic Compliance Control Catalog (regime to obligation to control mapping) and query it as a JSON artefact

### Dashboard and Reporting
- View a privacy compliance dashboard, notifications and reports
- Gate the entire module behind a master data-protection enable/disable feature flag

### CLI Commands
- `php artisan privacy:article-30-export {--format=json} {--out=}` - Export the GDPR Article 30 register (record of processing activities) for regulator submission (csv / json / markdown)
- `php artisan privacy:check-overdue-dsars {--dry-run}` - Email the data-protection contact about DSARs whose due date has passed and which are not yet closed
- `php artisan privacy:dsar-package {dsar} {--out=} {--user=}` - Build a single subject-access export package for a verified DSAR with redaction applied
- `php artisan ahg:privacy:scan-embedded-backfill {--digital-object-id=} {--limit=500}` - Backfill embedded PII findings by scanning EXIF / IPTC / XMP sidecar tables
- `php artisan privacy:scan-io {ioId} {--jurisdiction=} {--no-persist}` - Run a PII scan against an information object and persist a scan report

## Provenance and AI Governance (ahg-provenance-ai)
### AI Inventory and Governance Dashboard
- Review an admin-only dashboard of every configured LLM (provider, name, model, max tokens, temperature, active/default status) at /admin/governance
- See headline stats: total and active LLM configs, total inferences, inferences in the last 7 days, and average AI confidence
- Inspect recent AI inference activity (service, model, target record and field, confidence, elapsed time, signed/unsigned status) without exposing model API keys
- Retrieve the LLM configuration list and recent-inference list as JSON for downstream tooling
- View the model-provenance manifest attached to each LLM configuration

### Provenance Trace and Audit
- Pull a full, FOIA-defensible trace for any record (information object, actor, repository, term, or museum metadata) showing every AI decision that touched it, grouped by field
- See, per field, the originating inference (model, version, confidence, standard, input/output hashes and excerpts, endpoint, timing) plus every reviewer override and the currently effective value
- Run a coverage diagnostic to check how many inferences each AI service produced over a chosen window (default 7 days), with average confidence and count of records still pending Fuseki sync

### Reviewer Corrections and Review Queue
- Record a reviewer's correction to an AI-suggested field as a new, non-destructive override event capturing who, why, and the before/after values (the original AI inference is never overwritten)
- Auto-detect overrides from an edit-form submission across all changed AI-touched fields
- Automatically queue low-confidence inferences for human review when they fall below a per-service confidence threshold

### Cryptographic Signing and Semantic Provenance
- Ed25519-sign each AI inference manifest so its authenticity can be independently verified
- Persist every inference and override to a Fuseki RDF-Star / PROV-O semantic store alongside the SQL record for defensible, standards-based provenance
- Survive Fuseki outages by writing SQL first and replaying deferred semantic writes later, with no loss of AI records

### CLI Commands
- `php artisan ahg:provenance-ai:replay {--batch=200} {--dry-run}` - Replay queued AI inference and override Fuseki writes for rows whose graph URIs are still NULL (idempotent, runs every 5 minutes on a self-gating schedule)
- `php artisan ahg:provenance-ai:keygen {--force}` - Generate the Ed25519 keypair used to sign AI inference manifests (private key stored gitignored under storage/, never in git or the database)

## RAD Archival Description Editor (ahg-rad-manage)
### Core Description Editing
- Edit an archival description under the RAD (Rules for Archival Description, Jul2008) standard via /admin/rad-manage/edit/{slug}, gated by update permission
- Set identity fields: identifier, level of description, collection type, repository, description status, description detail, and description identifier
- Edit multilingual content fields: title, alternate title, edition, extent and medium, archival history, acquisition, scope and content, appraisal, accruals, and arrangement
- Edit conditions and holdings fields: access conditions, reproduction conditions, physical characteristics, finding aids, location of originals, location of copies, and related units of description
- Maintain control-area fields: institution responsible identifier, rules, sources, and revision history
- Choose the display standard for the description and set its publication (draft/published) status
- Capture RAD-specific properties such as statements of responsibility, publisher's-series details, standard number, statements of coordinates/projection, and architectural and cartographic scale statements

### Access Points and Relationships
- Assign subject, place, and genre access points from their controlled taxonomies
- Link name access points (creators/contributors) to authority records
- Assign RAD material-type terms
- Link related material descriptions to other archival descriptions
- Add multiple alternative identifiers

### Events, Languages, Notes and Context
- Record events (creation/other dates) with event type, associated actor, start/end dates, and display date
- Set languages and scripts of the material, and languages and scripts of the description
- View existing publication, archivist, and general notes for the record
- See parent-description context (title and link) within the description hierarchy

## Research Data Management (ahg-rdm)
### Dataset Deposit and Curation
- Create a research dataset with title, description, and optional link to a research project
- Deposit files into a dataset (up to 256 MB per file), storing them via the platform ingest layer
- Browse your own datasets (researchers see only theirs; admins see all) and view a dataset's files, status, and findings
- Link or create a Data Management Plan (maDMP) for a dataset and detach it again without deleting the plan

### POPIA Sensitivity Scanning
- Run a POPIA sensitivity scan that runs deterministic PII detection first (SA ID with Luhn check, email, SA phone, passport), then a special-category lexicon (health, religion, biometric, orientation), then AI-suggested named entities via the AI gateway
- Scan runs off-thread as a queued job so large or OCR-heavy datasets do not time out; scanned PDFs with no text layer fall back to rasterise plus OCR
- Records every finding with its detection method and a dataset-level verdict for later human review

### Human Review Gate and Disposition
- Confirm a finding as real PII or dismiss it as a false positive, with a reviewer note, each decision logged for provenance
- Set a dataset disposition of restrict, embargo (with an embargo-until date), de-identify, or open release
- Open release is blocked while any finding is unresolved or any PERSONAL/SPECIAL finding is confirmed, enforcing the sensitivity gate

### Publication, Citation and Access
- Mint a DOI and apply access policies when a disposition is set
- Publish a public, no-login citable landing page that shows metadata, DOI citation, and access status while keeping the actual binaries gated

### Compliance Dashboard and Reporting
- View an admin-only RDM dashboard with KPI roll-ups, charts, and the review-gate backlog, filterable by deposit date range and institution
- Run an admin-only org-wide compliance report of datasets filterable by institution, verdict, and disposition

### CLI Commands
- `php artisan ahg:rdm-demo {--fresh}` - Run the full POPIA RDM demo pipeline (deposit, scan, human gate, restrict, DOI, landing, scoreboard) on a 100%-synthetic dataset; --fresh purges any prior demo dataset first

## Records Management (ahg-records-manage)
### Retention Schedules and Disposal Classes
- Create, edit, browse, and search retention schedules with authority, jurisdiction, and effective/review/expiry dates
- Approve a draft schedule to activate it
- Create, edit, and delete disposal classes under a schedule, setting retention period (years/months), retention trigger, disposal action, confirmation-required and review-required flags, and legal citation
- Assign a disposal class to a record (information object) with a retention start date, and view the class currently assigned to a record

### File Plan and Classification
- Build and browse an interactive hierarchical file plan tree (plan, series, sub-series, file group, volume nodes) with create, edit, move, and delete-when-empty operations
- Import a file plan from a spreadsheet (guided upload, auto-detected column mapping, validation preview, then commit), from a directory scan, or from XML (generic or EAD), and link imported records to file-plan nodes
- Define auto-classification rules (by folder path, workspace, department, MIME type, tags, or custom metadata) that route records to a file-plan node and disposal class; test a rule against sample metadata, classify a single record, or run a batch across many records

### Disposal Workflow and Destruction
- Initiate a disposal action on a record (destroy, transfer to archives, transfer external, retain permanently, or review) with a reason and transfer destination
- Move an action through a controlled workflow: recommend, approve, clear legal hold, reject, then execute, with a full action timeline
- Execute destruction, transfer, or permanent retention; destruction generates a numbered destruction certificate (DC-YYYY-NNNNN) with a content hash as a four-eyes act
- Run DoD 5015.2 verification on a disposal action to confirm the certificate exists, has a valid number format, and carries a content hash
- Browse the disposal queue with filters and a history of completed, cancelled, rejected, and retained actions

### Review Queue and Vital Records
- Schedule a review on a record with a due date and assigned reviewer, browse the review queue (pending, overdue, due-soon), assign a reviewer, and complete a review with a decision, notes, and next review date
- Flag records as vital with a review cycle, list vital records, surface overdue vital-record reviews, and mark a vital record reviewed

### Email Capture and Record Declaration
- Upload and capture an .eml email as a record, with Message-ID de-duplication that reopens an existing capture instead of duplicating
- Classify a captured email against a file-plan node and disposal class, then declare it as a formal record (information object)
- Declare records and approve declarations, and query a record's declaration status

### Compliance Assessments
- Create a compliance assessment against a framework (ISO 15489, ISO 16175, ISO 30300, ISO 23081, and other configured frameworks) with a reference, scope, and reporting period
- Run automated checks against the live records data plane, re-run checks, review findings and recommendations, and finalise the assessment with a sign-off

## Analytics, Reporting and Trust Dashboards (ahg-reports)
### Standard Archival Reports
- Generate an archival descriptions report filtered by culture, date range, level of description and publication status, and download it as CSV
- Generate authority-record reports filtered by entity type and date, with CSV export
- Report on repositories, accessions, donors and physical storage, each with date filters and CSV export
- Run a spatial analysis report over records carrying map coordinates, filtering by place term, level of description and subject keywords, and export the results as CSV or GeoJSON
- Report on taxonomies and terms, and list recently created or updated records across every record class
- View a user-activity report of who did what and when, filtered by user, action and date
- Pick a record type from a report selector to jump straight to the matching report
- Run reports "as an anonymous user" so figures reflect only publicly published records, with an honest notice when a report has no public equivalent

### Custom Report Builder
- Build custom reports over a curated set of catalogue tables, choosing columns, filters, joins, sorting and row limits
- Preview, edit, clone, archive and delete custom reports, and organise them by category
- Save reusable report templates and apply them to new reports
- Version reports, snapshot their state, and restore an earlier version
- Schedule a report to run daily, weekly or monthly and email results as CSV, PDF or XLSX to named recipients
- Share a report via a time-limited public token link, and deactivate the link later
- Export report output as JSON or CSV, and embed reports as dashboard widgets
- Attach files, add comments, and save reference links (with Open Graph previews) against a report
- View a report builder dashboard summarising all reports and their data sources

### Audit Trail Reports
- Review the audit trail of changes to actors, archival descriptions, donors, physical storage, repositories and taxonomy terms
- Audit permission and access-control group changes
- Filter any audit report by date range and page through the history

### Collection Health and Data Quality
- View a cross-collection health dashboard showing record counts per GLAM domain, publication coverage (published / draft / unassessed), digital-object coverage and preservation-assessment coverage
- Measure ISAD(G) descriptive completeness across the published catalogue, seeing how many records are missing each core element (title, reference code, dates, creator, scope, extent, level, repository) with an overall completeness gauge and top-gap ranking
- Track catalogue growth and composition: headline totals, records-created-per-month trend (shown only when a real creation timestamp exists), and breakdowns by level of description, holding repository and digital-surrogate presence

### Preservation and Integrity
- Monitor preservation health: fixity pass / fail / never-checked, objects with missing files, format-identification (PUID) coverage, virus-scan posture, and a list of the most recent preservation failures and warnings
- View checksum and TIFF-to-PDF merge-job integrity statistics with recent job status and per-job file counts

### Trust, Rights and AI Transparency
- Open a Trust and Transparency Console that links together content-credential, provenance, preservation, accessibility and open-data surfaces, each shown only when its feature is installed, with live metric badges
- Break down the catalogue by access status, rights-statement and copyright-status coverage, and ODRL policy governance (records governed vs open-by-default, by action verb)
- View an AI usage transparency report: total inferences logged, distinct records touched, human-reviewed share, breakdown by inference type and model, and a per-month trend
- Open a North Star Cockpit giving a demo-ready overview of the platform's vision capabilities (discover, reconstruct, trust and open-access), each with a live link and best-effort metric

## Repository / Archival Institution Management (ahg-repository-manage)
### Browse & Discovery
- Browse archival repositories (institutions) with faceted filtering by thematic area, geographic region, archive type, subregion, language and locality
- Search repositories and sort the results, with an advanced browse mode
- View a repository's full profile: contacts, holdings, digital objects, other names, repository types, languages, scripts, maintenance notes, thematic areas and geographic subregions
- Page through a repository's holdings and its maintained authority records (actors)
- Print a repository profile and autocomplete repositories by name for AJAX lookups

### Create & Edit
- Add a new repository and edit existing ones (with audit snapshots taken before and after changes)
- Manage repository contacts, alternate names and maintenance notes
- Delete a repository (admin only, with a confirmation step)

### Theme & Upload Settings
- Customise a repository's public theme / presentation settings
- Set a per-repository upload size limit, with a dedicated "upload limit exceeded" page shown to contributors who breach it

## Request-to-Publish Workflow (ahg-request-publish)
### Public Submission
- Submit a public request to have a described record published (token-anchored flow, no login required)
- Receive an anonymous receipt page addressed by a secure token so a requester can track their own submission

### Curator Inbox & Decisions
- Review incoming publish requests in a curator inbox (admin only)
- Open a per-request panel and record a publish / decline decision on each request
- Manage the legacy AtoM-ported request queue: browse, edit, update the request details, and delete requests
- Submit a publish request against a specific record by slug (authenticated legacy flow)
- See human-readable status labels and colour-coded status badges for each request

## Research OS - The Researcher's Operating System (ahg-research)
### Research Projects & Personal Workspace
- Create, browse, view and manage research projects with a full project dashboard
- Set your self-declared research mode (beginning / intermediate / advanced) to tune the experience
- Work in a personal research workspace and manage its saved items
- Upload, download and delete workspace files under an enforced storage quota
- Maintain a personal research profile and manage personal API keys
- View a personal research analytics dashboard

### Quick Capture Inbox
- Capture a one-tap note, mobile jot, email-in item or web-clipper clip into a personal inbox
- Triage, archive, restore and move captured items into a project
- List and filter your own inbox

### Question Builder (Research Design Brief)
- Build a versioned Research Design Brief per project (appends a new version with a change reason)
- Get live heuristic and optional AI-backed diagnosis of your research question
- Review the full version history of the brief

### Source Triage
- Triage a project's sources on a board: set category, set read status and add notes
- Get an optional AI preview/summary of a source before reading it

### Claim Ledger & Evidence
- Record, edit and delete research claims per project and set a claim's status
- Attach and detach supporting evidence on each claim
- View a single claim with its linked evidence

### Analysis Bridge
- Log analysis results per project with provenance and downloadable artifacts
- Add and delete lightweight thematic-coding tags and memos
- Link and unlink analysis results to Claim Ledger claims

### Argument Builder
- Build one structured argument per project (title + central thesis)
- Add, reorder, annotate and delete argument steps
- Attach a Claim Ledger claim to any step; see a live warnings panel for weak links

### Contradiction Engine
- Scan a project's Claim Ledger for contradictions (heuristic scan or AI scan)
- Dismiss, resolve and reopen individual contradiction findings

### Writing Studio
- Create, edit, version and delete writing documents per project with a section-by-section editor
- Write as you go: add, save and delete document sections
- Cite a claim or pull a read-only source directly into a chosen section
- Snapshot and browse document versions; export a document as Markdown
- Optional per-section AI drafting (gateway-only, labelled, never auto-applied)

### Review Studio
- Run supervisor / co-author comment threads (works fully without AI): add, resolve and delete comments
- Run an adversarial "reviewer-twin" simulation and browse past run history (always AI-labelled)

### Method Design Studio
- Author, edit and version method protocols per project from a discipline template gallery
- Export a protocol as structured JSON for reuse in a thesis methodology chapter, grant or ethics application

### Publication Studio & Target-Journal Directory
- Track manuscript submissions against matched or free-text venues, with a state-machine workflow
- Manage a per-submission compliance checklist and record response-to-reviewers / revision history
- Get an optional AI venue-fit suggestion
- Maintain a target-journal directory (where to publish, scope + rules), seedable with the DHET-accredited set

### Journal Builder (Institutional Publishing)
- Build institutional journals with issues, articles and a manuscript workspace, with publish status control

### Lecture Builder & Training/LMS
- Author lectures/talks with sections and resources (curriculum content or standalone)
- Build training courses with modules and an assessment; enrol learners
- Learner flow: work through modules, take the assessment and download a completion certificate

### Grant Engine (Drafting)
- Draft grant applications per project from a funder template gallery, section by section
- Track funder calls / opportunities (create, update, delete)
- Optional labelled AI drafting per section (never submits on your behalf)

### Research Funding (Awarded Ledger)
- Track awarded funding per project (create, edit, delete) with machine-readable JSON export

### Data Management Plans (DMP)
- Build, edit and delete per-project Data Management Plans
- Export a machine-readable maDMP (RDA / Science Europe aligned) as JSON

### Research Ethics & Consent Register
- Maintain per-project ethics and consent records (create, edit, delete)
- Export the project's ethics register as JSON

### Milestones, Outputs & Team Registers
- Track planned milestones and deliverables (due dates, status, progress) with JSON export
- Maintain a research outputs register with JSON export
- Maintain a research team / collaborators register (co-investigators, students, partners) with JSON export

### Decision Log & Research Memory
- Keep a per-project Decision Log timeline (create, edit, delete recorded decisions)
- Curate per-project Research Memory items and accept read-only suggestions into memory
- Carry memory forward across projects (done / carry-forward / dropped / reopen) via a cross-project pool

### Living Field Alerts
- Watch the works a project cites and get alerts on retractions, updates and new related work
- Manage a per-project watch list; mark alerts read individually or all at once

### Impact Tracking
- See downstream citations, mentions and dataset reuse of a project's published outputs
- Manually refresh impact data on demand (polls public OpenAlex / Crossref Event Data)

### Time Machine
- View a merged, month-grouped project timeline reconstructing how the research developed
- Scrub to a date and see the project's "state as of" that moment

### AI Disclosure ("AI Containment")
- View detected + manually logged AI usage per project and a generated disclosure statement
- Add and delete manual AI-interaction log entries
- Download the generated AI-use statement as plain text

### Research Studio (NotebookLM-style Artefact Generator)
- Generate artefacts from project sources: Briefing Document, Study Guide, FAQ, Timeline, Mermaid Diagram, Video Script, Spreadsheet and two-voice Audio Overview
- View, download and delete generated artefacts

### Research Copilot
- Ask a question and get a cited synthesis drawn from the catalogue
- Save an answer with its citations into a research workspace; browse past answers

### Project Analysis, Visualization & Reproducibility Tools
- Build knowledge graphs, timelines, maps and network graphs from a project
- Manage assertions, hypotheses, extraction jobs and snapshots; run assertion batch review
- Generate an RO-Crate, build a reproducibility pack and mint a DOI for a project
- View a compliance dashboard and ethics-milestones view per project

### Project Export & Replication Pack ("The exit door is always open")
- One-click full-fidelity project export as a ZIP bundle or individual open formats: Markdown, JSON, BibTeX, RIS, CSL
- Build and download a replication pack ZIP for a project

### Collections, Annotations, Notebooks, Bibliographies & Citations
- Build evidence-set Collections; add/remove items and manage them
- Create, edit and delete annotations on catalogue items
- Keep private research notebooks and promote notebook content
- Build bibliographies and export them (or a single entry) as BibTeX / RIS / CSL-JSON
- Generate a public citation page for a record and export citations in RIS, BibTeX, EndNote, APA, MLA or Chicago
- Use an Evidence Viewer and search catalogue items

### Saved Searches & Cross-Fonds Query
- Save searches, run them, diff results over time and snapshot result sets
- Run cross-fonds reasoning queries across collections

### Real-time Collaboration & Sharing
- Invite collaborators, share a project and manage project collaborators
- Use a real-time collaboration panel (polling fallback): join, poll, post comments and resolve them

### Offline & Mobile
- Use the mobile / PWA home and sync offline changes back to the server
- Take a project, collection, workspace or your favourites offline as an editable package; check status and download it
- Build a "Work Offline" package from selected groups and upload the researcher-sync file to bring work back

### ORCID Integration
- Link, authorize and unlink an ORCID iD; save or clear ORCID credentials
- Pull your ORCID profile and sync your ORCID Works
- Auto-populate a registration form from a public ORCID record (no account needed)

### Researcher Registration & Renewal
- Self-register as a researcher (staff or public register form) and complete registration
- Submit a membership renewal

### Reading Room & Reference Services
- Book reading-room visits; view, confirm, cancel bookings and check in / check out / mark no-show
- Reproduction requests: submit and view reproductions
- Receive researcher notifications

### Source Assessments & Reports
- View source assessments
- Generate, view and template research reports; export finding aids and notes; generate a finding aid

### Journal (Personal Diary)
- Keep a personal research diary with dated entries

### Administration (staff/admin)
- Manage researchers: approve, reject, suspend, verify, reset password, view detail
- Administer bookings, rooms, seats, equipment (with history), retrieval queue and walk-ins
- Manage reference data: types, institutions, statistics and activity log
- Administer researcher storage quotas (usage vs limit + policy CRUD)
- Manage the Validation Queue (validate results, bulk-validate), Entity Resolution (resolve, view conflicts), ODRL policies, and document templates
- View an audit trail by record, table, user or entry

### CLI Commands
- `php artisan ahg:seed-target-journals` - Seed the target-journal directory with the DHET-accredited starter set
- `php artisan ahg:seed-research-dropdowns` - Seed research plugin dropdown values into the ahg_dropdown table (safe to re-run)
- `php artisan ahg:orcid-sync {--limit=500} {--researcher=} {--force} {--json}` - Pull ORCID Works for researchers who have linked their ORCID iD
- `php artisan ahg:research-impact-refresh {--project=} {--limit=500} {--json}` - Poll OpenAlex / Crossref Event Data for citations, mentions and dataset reuse of each project's published outputs
- `php artisan ahg:research-field-alerts {--project=} {--limit=500} {--json}` - Poll Crossref / OpenAlex for retractions, updates and new-related work on each project's cited DOIs (Living Field Alerts)

## Researcher Submissions & Contributor Portal (ahg-researcher-manage)
### Researcher Dashboard
- View a personal researcher dashboard summarising your profile, research projects, collections and annotations
- See your submissions and a list of pending (awaiting-review) submissions
- Open a single submission to view its detail

### Submitting & Importing
- Start a new submission via a guided form
- Import a research exchange package into the platform (upload flow gated by the create permission)
- Use the AJAX helper surfaces for API-driven file upload, file deletion and autocomplete

### Researcher Directory (staff)
- Browse the researcher directory and view an individual researcher record
- Add and edit researcher records (permission-gated)
- Delete a researcher record (admin only)
- Runs on a seeded submission workflow (states/transitions) provisioned by the package

## ResourceSync Change Publishing (ahg-resourcesync)
### Standards-Compliant Sync Feeds
- Publish a `.well-known/resourcesync` Source Description so aggregators can auto-discover the sync feeds (ResourceSync spec section 11)
- Serve a Capability List advertising the available Resource List and Change List
- Serve a Resource List: the full inventory of published records as paged XML
- Serve a Change List: recent record updates plus tombstones for deletions, paged, over a configurable time horizon
- All feeds are rate-limited (120 requests/minute/IP) to allow a polite aggregator to walk the full chain while blocking abusive scrapers

## Records in Contexts (RiC-O) Knowledge Graph (ahg-ric)
### RiC Entities & Relationships
- Browse, view, create, edit and delete RiC-native entities - Places, Rules, Activities and Instantiations - through standalone admin pages
- Create any RiC entity (Agent, Record, Place, Function, etc.) inline from the explorer with a name, identifier, description and optional parent link
- Manage rico:Occupation records - the role or profession an actor held over a time-span (ISAAR(CPF) 5.2.6) - with full create/edit/delete
- Browse all relationships across the collection and filter by relation type, with domain/range-aware relation-type pickers
- Create, update and delete typed relations between any two entities via the embedded relation editor
- Look up related entities, cross-collection neighbours and hierarchy walks for any entity
- Autocomplete records by title, identifier or slug, and cross-entity autocomplete across every RiC type

### Explorer, Graph & Discovery
- Explore any record as an interactive RiC-O graph ("View in RiC Explorer"), with fullscreen and node-detail panels
- View a per-record graph summary, timeline of contextual events, and a plain-language explanation of how two entities are related
- See cross-collection connections for a record - everything related to it grouped by Gallery/Library/Archive/Museum collection domain
- Run semantic search over records with natural-language queries (e.g. "records created by Hennie Pieterse"), with type and decade facets
- Toggle the viewer between graph and list view modes (session-remembered)

### RDF, SPARQL & Fuseki Triplestore
- Import inbound RDF (Turtle, JSON-LD or RDF/XML) with a dry-run preview before committing to the graph
- Query Heratio's RiC graph over a read-only SPARQL proxy (SELECT / ASK / CONSTRUCT / DESCRIBE) for federated linked-data clients
- Sync entities into the Fuseki triplestore on save/delete, with cascade delete and RDF-star support
- Monitor sync status, the sync queue, orphaned graphs and operator logs from admin dashboards
- Trigger a manual resync, integrity check or orphan cleanup, and clear individual queue items, from the dashboard
- Check sync readiness and watch live sync progress before running a bulk load

### Linked Data API & Harvesting
- Publish and browse agents, records, functions, repositories, places, instantiations, activities and rules as RiC-O linked data over a versioned `/api/ric/v1` REST API
- Export a record set, request entity revisions/audit history, and retrieve an entity info card
- Harvest the collection over standard OAI-PMH v2.0, and consume a DCAT/VoID dataset descriptor plus a diffable change log
- Retrieve a machine-readable OpenAPI 3.0 spec and try any endpoint live via an inlined Swagger UI
- Upload files/content and fetch cached thumbnails for embedding in UIs and IIIF manifests
- Request an API key self-service (write/delete scope) via an HTML form; admins approve or deny the request
- Create, update and delete agents, records, repositories, functions, places, rules, activities, instantiations and relations over the write API (API-key gated), including bulk CSV/JSON import with dry-run
- Resolve stable OpenRiC IRIs (`/{instance}/{type}/{key}`) with content negotiation, 303-redirecting machines to JSON-LD and browsers to the record page

### CRM / CIDOC-CRM Export
- Export any record as CIDOC-CRM v7.1.3 by slug or id, with format chosen via Accept header or `?format=turtle`
- Serialize records, agents, functions, repositories, places, rules, activities and instantiations to RiC-O JSON-LD, with ISCAP compliance annotations
- Sync museum and information-object records into CIDOC-CRM named graphs in Fuseki (single, by type, or bulk)

### SHACL Validation, Conformance & Provenance
- Validate any entity against the RiC-O SHACL shapes from the admin UI and see conformance results before saving
- Run a whole-collection SHACL validation report and export conforming JSON-LD
- Link entities to external authority records via live Wikidata and VIAF lookup
- Mark entities as deprecated (with reinstate) and record inferred/provenance data on entities
- Consult the RiC-O vocabulary, including per-taxonomy term listings

### CLI Commands
- `php artisan ahg:crm-graph-sync {--id=} {--type=museum} {--limit=0} {--culture=en} {--dry-run}` - push CIDOC-CRM named graphs (museum or information-object records) into Fuseki, single or bulk
- `php artisan ahg:ric:fuseki-load {--agents-only} {--places-only} {--limit=} {--batch=200} {--dry-run}` - bulk-load rico:Agent and rico:Place instances into the Fuseki triplestore
- `php artisan ahg:fuseki-integrity-check {--quiet-success}` - compare Fuseki graph state to the ric_* relational tables and report drift (read-only, deletes nothing)
- `php artisan ahg:fuseki-orphan-cleanup {--dry-run}` - detect and purge Fuseki graphs whose entity row no longer exists
- `php artisan ahg:ric-conformance {--sample=25} {--soft}` - validate serialized RiC entities against the RiC-O SHACL shapes as a governance gate
- `php artisan openric:issue-key {id} {--deny} {--note=} {--rate-limit=1000} {--expires=}` - approve or deny a pending API key request, setting its per-hour rate limit and expiry
- `php artisan openric:rebuild-nested-set {--dry-run} {--verify} {--table=information_object}` - rebuild (or verify) the MPTT lft/rgt nested-set columns for the hierarchy tree
- `php artisan openric:seed-demo {--drop} {--dry-run}` - seed a coherent demo mini-fonds into a fresh OpenRiC server
- `php artisan ric:verify-split {--base=} {--key=} {--no-writes}` - smoke-test the split RiC API end-to-end (reads, plus optional create/delete write tests)

## Rights (ahg-rights)
- (no user-facing functionality found - schema-only package; rights UI is delivered by ahg-rights-holder-manage)

## Rights Holders, Embargoes & Rights Administration (ahg-rights-holder-manage)
### Rights-Holder Directory
- Browse rights-holders (auth-gated because records carry contact PII) and view an individual rights-holder profile
- Add, edit and delete rights-holder records (create/update/delete permission-gated)

### Record-Level Rights (PREMIS)
- View and add PREMIS rights statements against an individual record (by slug)
- Record the rights basis, act, restriction and related rights details for a described item

### Embargoes
- Place a time-limited embargo on a described object, view an embargo, and lift it (privileged, permission-gated, POST-only)
- List all embargoes; see which are active, expiring soon, or blocking access
- Check an object's embargo status and see the "embargo blocked" surface shown to users denied access

### Extended Rights
- Use the extended-rights dashboard and per-record extended-rights view
- Apply rights to many records at once via a batch operation
- Clear/reset the rights on a record (delete-gated)
- Export the extended-rights data set

### Rights Administration (admin)
- Run the rights-admin console: edit and update embargoes centrally
- Manage orphan works (edit and update orphan-work status/details)
- Generate a rights report, view aggregated rights statements, and manage Traditional Knowledge (TK) labels

## Digital Scanning, Ingest & Web Archiving (ahg-scan)
### Watched-Folder Ingest Pipeline
- Configure watched scan folders bound to a sector, standard, derivative/OCR/packaging settings; create, edit, delete and run a folder on demand
- Auto-detect new files dropped in a watched folder and enqueue them for ingest, resolving each file's destination from its path layout
- Process staged files through the full ingest pipeline (virus check, format identification, fixity, ingestion, derivation, replication)
- Ingest BagIt containers (RFC 8493): unpack the bag, apply bag-info.txt metadata and verify the manifest checksums before ingest

### Scan Inbox & Error Recovery
- Monitor an operator scan dashboard summarising pipeline activity
- Work the scan inbox: view a file's detail, retry a failed file, discard, restore, or release rights on it
- Run bulk actions across many inbox items at once
- Receive an automatic failure email when a file exhausts all retries

### Metadata, Rights & Preservation
- Parse a heratioScan XML sidecar into normalised ingest metadata
- Accept non-heratioScan sidecars (EAD, MARC21-XML, MODS, LIDO) and transform them into the canonical envelope via XSLT
- Route sector-specific metadata into the right tables (archive / library / gallery / museum)
- Apply sidecar-declared rights at ingest: rights statements, embargoes, ODRL policies and TK labels
- Write PREMIS-style preservation events (virus check, format ID, fixity, ingestion, derivation, replication) so the preservation record is complete at ingest time
- Identify technical formats against the PRONOM vocabulary via siegfried

### Scanner Upload API (v2)
- Drive scanning from an external scanner/app over the `/api/v2/scan` REST API (API-key scoped to scan:write)
- List ingest destinations, open a token-anchored upload session, stream files into it, commit the session, or abandon it
- Sessions are token-bound and expire (default 24h)

### Web Archiving (WARC)
- Archive an operator-submitted URL, or snapshot a published record's own page, to a WARC 1.1 file (single-page capture, no crawl)
- List, view, replay in-browser, download the WARC, and serve individual captured assets from one admin surface

### CLI Commands
- `php artisan ahg:scan-process {--file=} {--folder=} {--limit=50}` - Process pending scan files through the ingest pipeline (synchronous)
- `php artisan ahg:scan-watch {--once} {--interval=30} {--folder=}` - Watch scan folders and enqueue new files for ingest
- `php artisan ahg:scan-retry-failed {--limit=20} {--dry-run}` - Re-dispatch failed scan files whose backoff window has elapsed
- `php artisan ahg:scan-install` - Apply the ahg-scan schema and dropdown seed (idempotent)
- `php artisan ahg:web-capture {url}` - Capture a single web page to a WARC 1.1 file (url mode; no crawl)

## Search and Discovery (ahg-search)

### Full-Text and Advanced Search
- Search archival records from a single search box, with results drawn from Elasticsearch
- Run an advanced search with multiple field-scoped criteria and filters
- Get type-ahead autocomplete suggestions as you type a query
- Follow legacy search URLs (`/search/index`, `/search/semantic`) that redirect to the current search

### Semantic and Vector Similarity
- Search by meaning using Qdrant-backed vector similarity (`/api/search/semantic`)
- Find records similar to a given record via "more like this" vector lookups (`/api/search/semantic/similar/{ioId}`)
- Blend keyword and vector results into a single ranked list with tunable vector weighting
- Check the health of the semantic-search vector backend

### Discovery API (external / server-to-server)
- Query a public, rate-limited JSON discovery search endpoint scoped to published records only
- Request record recommendations from the discovery API
- Optionally expand natural-language queries through an Ollama-backed model (thesaurus fallback), toggled per deployment
- Optionally re-rank results for a signed-in user based on their recent search history

### Search Analytics and Bulk Editing (admin)
- Track which result a searcher clicks (click-through) to feed relevance analytics - open to anonymous searchers too
- View a search analytics dashboard (query volume, click-through rates)
- Review recent description updates surfaced through search
- Run a global find-and-replace across record descriptions
- Save searches, manage search history, manage admin search templates, and test query expansion from the search-enhancement pages

## Security Clearance and Multi-Factor Authentication (ahg-security-clearance)

### Clearance Administration (admin)
- View a security-clearance dashboard summarising users, clearances and compliance
- List every user with their current clearance level
- Grant, bulk-grant, and revoke security clearances
- Classify and declassify individual records/objects at a security level
- Manage compartments and compartment-based access
- Grant and revoke per-object access, and review who has access to a record
- Run clearance reports and a security-compliance overview
- Manage document watermark settings and trace a leaked watermark back to the user who received it

### Access Requests
- Submit a request for access to a restricted record (authenticated users)
- Track the status of your own access requests
- Review, approve, or deny pending access requests (admin)

### Multi-Factor Authentication (MFA)
- Enrol and confirm TOTP (authenticator-app) two-factor authentication
- Enrol WebAuthn / FIDO2 passkeys as a second factor, and delete enrolled passkeys
- Enrol email or SMS one-time-passcode (OTP) factors, with resend during enrolment
- Choose which factor to use at login when more than one is enrolled
- Generate, view, and regenerate single-use recovery codes
- Disable your own two-factor authentication; brute-force-throttled TOTP verification
- Have an admin remove 2FA from a user who is locked out

### MFA Policy and Audit (admin)
- Set and reset a per-tenant MFA enforcement policy (require MFA per organisation)
- View a security audit dashboard and browse the audit log
- Export audit records and review per-object access history

## Semantic Search, Knowledge Graph and Heritage Programmes (ahg-semantic-search)

### Semantic Search Administration (admin)
- Configure semantic-search behaviour (synonym/term expansion settings)
- Manage the thesaurus of expansion terms (add, view, list)
- Review search logs and index sync logs
- Manage admin search templates and edit templates
- Trigger a manual re-sync of the semantic index
- Test query expansion live from the browse-page semantic modal (public test endpoint)
- Save semantic searches and review your semantic-search history (authenticated)

### Cross-Collection Discovery and Explore Surfaces (public)
- Browse people/actors, places, subjects/themes, and genres as faceted entity pages with JSON feeds
- Explore a collection through a guided cross-collection view
- View an interactive timeline of records and a per-record "related records" panel
- Browse "Discoveries" - non-obvious, graph-grounded connections between records - as an index and per-item detail
- Read a per-record generative-scholarship report of discovered connections (admin)

### Knowledge-Graph Grounding and KM Export
- Serve RiC-graph disambiguation facts to the KM/agent for grounding prompts via a public throttled endpoint (`/api/ric/ground`)
- Export a quality-filtered digest of the unified cross-collection graph as KM-ingestable markdown

### Research Leads (North Star: generative scholarship)
- Publish a public "Research Leads" feed and per-lead detail pages
- Generate, publish, dismiss, or re-pend research leads from an admin worklist (admin)

### Repatriation and Displaced Heritage (North Star)
- Maintain a "potentially displaced heritage" review register flagging origin-vs-holding mismatches (admin)
- Run a structured repatriation-claim workflow: create, edit, update, and change claim status (admin)
- Work a claim in a staff dialogue workspace: threaded two-way messages plus internal notes, a status audit trail (who/when/from-to with a note), provenance-trace links, and minting/revoking shared-record access tokens for claimants (admin)
- Let the public lodge a repatriation claim against an item (throttled) with a thank-you confirmation
- Publish a public repatriation dashboard with a JSON feed, and a public "virtual return" space with a guided walkthrough per item
- Let claimants view a shared record via a capability token and post messages back
- Collect and moderate community knowledge contributions (oral history, provenance, corrections) about displaced items (public submit; admin moderation)

### Endangered Heritage (North Star: race against loss)
- Maintain an endangered-heritage capture-priority worklist and flag records as at-risk (admin)
- Update capture status as at-risk items are digitised (admin)
- Review at-risk flags pushed in by federation peers and accept them onto the board (admin)
- Publish a public read-only "at-risk" register (local and global/cross-institutional views) and an endangered-heritage dashboard with a JSON feed

### Language Revival Corpus (public + admin)
- Browse a community language-revival corpus by culture, with per-culture glossary pages
- Contribute glossary entries and translations to a culture's corpus (public)
- Submit transcription, correction, and translation contributions against an item (public)
- Moderate glossary and transcription/translation contributions (admin)

### CLI Commands
- `php artisan ahg:generate-discoveries {--limit=25} {--dry-run}` - Refresh the persisted generative-scholarship discovery set, most-connected records first
- `php artisan ahg:generate-research-leads {--limit=25} {--enrich} {--dry-run}` - Promote the strongest discoveries into pending research leads (optionally enrich the "why it matters" text via the AHG AI gateway)
- `php artisan ahg:scholarship-discover {--id=}` - Surface graph-grounded connections and research leads for one record
- `php artisan ahg:refresh-federated-discoveries {--object=} {--limit=200} {--stale-only}` - Refresh the cross-institutional (federated) discovery cache
- `php artisan ahg:displaced-heritage-scan {--limit=0}` - Scan museum records for origin-vs-holding mismatches into the curatorial review register
- `php artisan ahg:km-export-graph {--limit=25} {--dir=km-graph} {--dry-run}` - Export a bounded, quality-filtered digest of the cross-collection graph to KM-ingestable markdown
- `php artisan ahg:km-graph-sync {--id=} {--limit=0} {--dry-run}` - Render cross-collection RiC graph connections to KM-ingestable markdown (one record or many)

## Platform Settings and Administration (ahg-settings)

### Site, Interface and Appearance
- Configure site information, global settings, header customizations, and page/interface labels
- Manage themes and serve a dynamically generated theme stylesheet (`/css/ahg-theme-dynamic.css`), plus carousel and page-element settings
- Configure interface labels, languages, diacritics handling, and Markdown rendering
- Manage the treeview, finding-aid, digital-object, and visible-element display settings

### Records, Numbering and Metadata
- Configure the identifier mask, levels of description, and authority-record behaviour
- Manage sector numbering and custom numbering schemes (create/edit named schemes)
- Manage the dropdown/controlled-value manager used across record forms
- Configure inventory, accession, metadata, and finding-aid settings
- Set default record templates and CSV validator rules

### AI, Media and Integrations
- Configure AI services and run same-origin "Test Connection" checks for AI and machine-translation endpoints (routed through the AHG AI gateway)
- Manage AI Condition-reporting client keys: save, revoke, toggle training consent, and run an API self-test
- Configure voice AI, text-to-speech (TTS), face detection/recognition, and IIIF image settings
- Configure ICIP (Indigenous Cultural and Intellectual Property) settings, media, photos, and DIP upload
- Configure SharePoint, FTP, Fuseki (triple store), storage service, and AHG Central / AHG integration
- Manage OAI, web analytics, webhooks, LDAP, encryption, and email settings

### Governance, Jobs and Diagnostics
- Configure security, permissions, privacy-notification, data-protection, compliance, audit, and integrity settings
- Manage plugin enable/disable toggles
- View and manage scheduled cron jobs: list, toggle on/off, edit schedule, run-now, and seed the default set
- Import settings in bulk (AHG import), manage portable-export and preservation settings
- View system info and a services overview, and browse the application error log
- Manage clipboard, DAM tools, and jobs settings

## Secure Share Links (ahg-share-link)

### Issuing and Receiving Links
- Issue a time-limited, tokenised share link to a specific record from an inline modal or a bookmarkable form (authenticated curators)
- Land on a per-record share form directly from a saved link, and see a post-issue success page
- Open a shared record anonymously with only the bearer token as the credential (no login), with all validation guards applied

### Access Control and Guards
- Enforce access on each visit: token validity, expiry, per-recipient limits, ACL checks, and minimum security-clearance checks
- Reject links that exceed the configured maximum expiry cap or fail clearance requirements

### Administration
- List all issued share links and view per-link detail (ACL-gated, admin bypass)
- Revoke an active share link

### CLI Commands
- `php artisan share-link:prune {--dry-run}` - Apply retention rules to share-link tokens and the access log (dry-run reports what would be pruned)

## Microsoft 365 / SharePoint Integration (ahg-sharepoint)

### Tenant, Drive and Connection Management (admin)
- Configure Microsoft 365 / Azure AD tenants and test Graph connectivity per tenant
- Browse configured SharePoint document drives and their folders
- Map a drive to a Heratio destination, and view/manage per-drive column mappings

### Ingest Rules and Content Sync (admin)
- Define auto-ingest rules with a schedule, and edit, save, delete, or run a rule on demand
- Manage mapping templates that translate SharePoint columns into Heratio metadata
- Delta-poll drives to pull new and changed content into Heratio

### Webhooks and Events
- Receive Microsoft Graph change notifications on a public, clientState-gated webhook (CSRF-exempt)
- Review the inbound event queue and per-event detail (admin)
- Map SharePoint users to Heratio users (admin)

### Federated Search and Push
- Run a self-contained SharePoint federated search (HTML and JSON) using the package's own tenant config, independent of the general federation dispatcher
- Accept manual push submissions over AAD bearer auth: preview a projection, commit a push, and poll push job status (`/api/v2/sharepoint/push/*`)

### CLI Commands
- `php artisan sharepoint:install` - Install the ahg-sharepoint schema (idempotent) and run package migrations
- `php artisan sharepoint:test-connection {--tenant=}` - Test Microsoft Graph connectivity for a configured tenant
- `php artisan sharepoint:subscribe {--drive=} {--webhook-url=}` - Create Graph webhook subscriptions (driveItem + list) for a drive
- `php artisan sharepoint:renew-subscriptions` - Renew Graph webhook subscriptions expiring within 12 hours
- `php artisan sharepoint:sync {--drive=} {--full} {--limit=0}` - Delta-poll one or all ingest-enabled drives
- `php artisan sharepoint:auto-ingest {--rule=} {--dry-run} {--force}` - Cron-driven SharePoint-to-Heratio ingest (one rule or all; force ignores the schedule)
- `php artisan sharepoint:ingest-event {--event-id=}` - Process one inbound SharePoint webhook event
- `php artisan sharepoint:status` - Print integration health (tenants, drives, subscriptions, queue depth)

## SPECTRUM Museum Collections Management (ahg-spectrum)

### SPECTRUM Procedures
- Manage the core SPECTRUM procedures: object entry, acquisitions, loans, movements, condition checking, conservation, and valuations
- Edit a single procedure field in place via inline PATCH (`/spectrum/procedure/{id}`)
- View a collections-management dashboard and a per-user "my tasks" list
- Run a data-quality review across collection records

### Workflow and Standard Operating Procedures
- Drive records through a configurable Spectrum workflow with state transitions (ACL-gated)
- Attach standard-operating-procedure (SOP) guidance to workflow steps
- Use the general workflow configuration screen to define the workflow

### Condition, Barcodes and Labels
- Run condition administration and review condition photos and condition-risk views
- Annotate condition photos, save/retrieve annotations, and export an annotated photo
- Scan and assign barcodes to objects (only visible when barcodes are enabled)
- Generate object labels

### Insurance, Valuations and Reminders
- Track object valuations and insurance, with a valuations report and reminders when valuations go stale
- Remind curators when condition checks fall overdue

### Reporting, Compliance and Notifications
- Run Spectrum reports for object entry, acquisitions, loans, movements, conditions, conservation, and valuations
- Export collection data (general export and Spectrum export)
- View a GRAP (accounting-standard) dashboard for heritage-asset compliance
- Manage POPIA/privacy compliance: privacy admin, compliance overview, breaches register, DSAR handling, ROPA, and privacy templates (admin-gated writes)
- View a security-compliance screen
- Receive in-app notifications and mark them read individually or all at once
- Guard publishing of collection records behind Spectrum completeness rules

### CLI Commands
- `php artisan ahg:spectrum-valuation-reminder` - Email curators when valuations are older than the configured reminder threshold
- `php artisan ahg:spectrum-condition-check-reminder` - Email curators when condition checks are overdue per the configured interval

## Static Pages (ahg-static-page)

### Public Pages
- View published static pages by slug (`/pages/{slug}`)
- View the built-in About, Privacy, and Terms pages at friendly URLs

### Page Management (admin)
- Browse and list all static pages
- Create, store, edit, and update static pages
- Delete a static page with a confirmation step

## Usage Statistics and Analytics (ahg-statistics)

### Usage Dashboards (admin)
- View an overall usage/statistics dashboard
- Review record views and digital-object downloads over time
- See top items and per-item usage detail
- View geographic distribution of usage
- Drill into per-repository statistics

### Administration and Export (admin)
- Configure statistics settings and manage bot/crawler filtering so automated traffic is excluded
- Export statistics data

## Physical Storage & Strongroom Management (ahg-storage-manage)

### Physical Storage Locations
- Browse physical storage objects with pagination, sorting (name / date modified), and keyword search
- View a storage location with its type, extended location detail, and linked archival descriptions and accessions
- Create and edit storage locations, capturing name, type, location, and description
- Record detailed extended attributes: building, floor, room, aisle, bay, rack, shelf, position, barcode and reference code, physical dimensions (width/height/depth), capacity and linear-metre usage, climate control (temperature and humidity min/max), security level, access restrictions, status, and notes
- Delete a storage location (admin only), with a confirmation page listing the archival records still held in it
- Autocomplete storage locations by name for linking widgets

### Container Linking and Box Lists
- Link an archival description to an existing physical container, or create a new container inline and link it in one step
- Unlink a container from a description (scoped so only physical-object container links can be removed)
- Generate an AtoM-style box list for a container, showing each held record's reference code, title, dates, access conditions, and parent collection

### Holdings Reporting
- Export a holdings report of all storage locations (name, type, location) as a downloadable CSV file

### Strongroom Space Allocation
- Browse strongrooms with search, and view a strongroom with its occupants, used capacity, and remaining capacity
- Create, edit, and delete strongrooms (delete is admin-only and blocked while occupants remain), capturing name, location description, capacity value and unit, and notes
- Assign or unassign a physical storage object to a strongroom with a recorded number of capacity units used

## Terms and Taxonomy Management (ahg-term-taxonomy)

### Taxonomy and Term Browsing
- List all taxonomies with pagination
- Browse the terms in a taxonomy with sorting, keyword search, parent-term filtering, and a scope-note-only toggle, enriched with scope notes, use-for labels, descendant counts, and linked description/actor counts
- View a term with its scope/source/display notes, use-for labels, broader/narrower/sibling/associated/converse terms, breadcrumb ancestry, previous/next navigation, and paginated related archival descriptions (with thumbnails and optional direct-only filter)
- Show a per-taxonomy expand/collapse tree view (HTML page plus lazy-loading JSON endpoint)
- List related authorities (actors and repositories) linked to a term, with sorting, pagination, and a direct-only option
- Autocomplete terms within a taxonomy, and autocomplete taxonomies themselves (used by the ACL editor); Google Maps rendering for place terms; ACL read-permission gating on term views

### Term Authoring
- Create terms, capturing name, code, parent/broader term, use-for labels, scope/source/display notes, related terms, converse term, and narrower terms
- Edit and update terms including notes, relationships, and self-reciprocal relations
- Delete terms with a confirmation page

### SKOS Import and Export (Linked Data)
- Import a SKOS vocabulary into a taxonomy from an uploaded RDF/XML file or a remote URL (with SSRF protection and no redirect following), skipping already-present concepts
- Export a taxonomy as SKOS in four serialisations: RDF/XML, Turtle, N-Triples, and JSON-LD, emitting prefLabels, alt/hidden labels, scope/history notes, notation, broader/topConcept hierarchy, and optional SKOS-XL label resources

### Cross-Vocabulary Mapping
- Manage per-term cross-vocabulary mapping links (exact/close/broad/narrow/related match) to external vocabulary URIs, which are emitted in all SKOS export formats

### CLI Commands
- `php artisan skos:validate {--taxonomy=} {--json}` - Validates the SKOS concepts of one taxonomy (or every taxonomy when omitted) against the vendored SKOS SHACL shapes, reporting each violation with shape id, concept URI, and message; supports JSON output and returns a non-zero exit code when violations are found (CI-friendly)

## Site Theme and UI Shell (ahg-theme-b5)

### Page Layouts and Rendering
- Render pages in 1, 2, or 3 column layouts plus a dedicated print layout
- Display record collections in multiple modes: gallery, grid, list, timeline, and hierarchical tree/tree-node views
- Play attached audio/video media via a built-in media player component

### Site Navigation and Chrome
- Present header, footer, main menu, browse menu, quick-links menu, user menu, and search box
- Switch interface culture/language via the culture switcher and language menu
- Show admin menus, GLAM/DAM menu, clipboard menu, and an AHG admin menu
- Surface a cart tab, feedback tab, and print-preview bar

### Accessibility and Compliance
- Provide accessibility helper controls and an accessibility statement
- Offer voice-command controls
- Display privacy message / cookie notice, admin notifications, and alert banners
- Inject Google Analytics and Google Tag Manager tracking when configured

## Translation and Localization Workbench (ahg-translation)

### Per-Record Translation
- Translate individual catalogue records (information objects) into a chosen culture via a slug-based translation form
- Edit translations side-by-side with the source, including CCO field values, in a translate modal
- Tag translated content with ICIP (Indigenous Cultural and Intellectual Property) sensitivity levels via badge and select components
- Save and apply record translations

### Interface (UI String) Translation
- Browse and edit the full matrix of interface UI strings per locale
- Save UI string changes into a review workflow (editors queue changes; admins auto-approve or request review)
- Get machine-translation suggestions for a UI string on demand
- View the change history of any UI string
- Review a pending UI-string queue and approve or reject submitted changes

### Draft Review Workflow
- List pending machine-translation drafts with filters
- Approve, reject, or edit the text of individual drafts
- Batch-process drafts and clean up orphaned drafts

### Machine Translation and Language Setup
- Configure the machine-translation endpoint, timeout, and API key (defaults to the AHG AI gateway)
- Run a health/probe check against the MT endpoint
- Manage the list of enabled languages and add new languages

### CLI Commands
- `php artisan ahg:translation:import-json-to-db {--locale=} {--dry-run} {--batch=500}` - Idempotently upsert every `lang/{locale}.json` file into the `ui_string` table; can restrict to one locale, preview with dry-run, and tune batch size

## User Account and Access Management (ahg-user-manage)

### User Account Administration
- Browse and search users with filtering by status (active/inactive), sorting by name, date modified, or email, and pagination
- View a user's detail page including group memberships, contact details, API keys, and security clearance level
- Create new user accounts with credentials, contact information, group assignment, and preferred locale
- Edit existing user accounts, including activation state, groups, and translated fields
- Delete user accounts via a confirmation page

### Self-Service Account Tools
- View own profile and open own edit page
- Change or reset own password (with current-password verification)
- View a personal clipboard of saved items (descriptions, actors, etc.)
- Manage own plugin navigation preferences by hiding enabled plugins from the nav (admin/editor only)

### Public Registration and Approval
- Submit a public self-registration request
- Verify a registration via emailed token link
- Review pending registration requests (admin), filtered by status (pending/verified/approved/rejected/expired)
- Approve a request, which creates the user account and optionally assigns a group
- Reject a request with admin notes

### Per-User Access Control (ACL)
- Manage a user's permissions on archival descriptions (information objects)
- Manage a user's permissions on authority records (actors)
- Manage a user's permissions on archival institutions (repositories)
- Manage a user's permissions on taxonomy terms
- Manage a user's researcher permissions
- Set each permission to grant, deny, or inherit, and add new object-scoped permissions

### API Keys and Plugin Capabilities
- Generate or delete a per-user REST API key
- Generate or delete a per-user OAI-PMH API key
- Grant, deny, or inherit individual plugins per user as an admin capability layer

## Record Version History and Restore (ahg-version-control)

### Version History Browsing
- List the version history of an information object or actor, paginated, showing version number, change summary, changed fields, author, timestamp, and restore markers
- View a single historical version's full snapshot and its changed fields

### Version Comparison
- Compare any two versions of a record and view a field-by-field diff

### Restore
- Restore a record to a previous version, which creates a new version entry recording the restore
- Enforces version.* ACL permissions on list, diff, and restore actions
- Enforces security clearance on classified records so version history, diffs, and restores are blocked for users without sufficient clearance

### CLI Commands
- `php artisan ahg:version-backfill {--entity=information_object,actor} {--batch=500} {--dry-run} {--user-id=}` - Create v1 baseline versions for entities that have no version history
- `php artisan ahg:version-prune {--entity=information_object,actor} {--retain-count=} {--retain-days=} {--dry-run}` - Apply retention rules to version history, preserving v1 and the most-recent N versions
- `php artisan ahg:version-capture {--entity=information_object} {--id=} {--summary=} {--user-id=}` - Build a snapshot and write it as the next version for an entity
- `php artisan ahg:version-snapshot {--entity=information_object} {--id=} {--pretty}` - Print a SnapshotBuilder JSON snapshot for an entity (smoke test)
- `php artisan ahg:version-diff {--entity=information_object} {--id=} {--v1=} {--v2=} {--pretty}` - Print a structured diff between two stored versions

## Museum Collections Workflow and Spectrum Compliance (ahg-workflow)

### Task Management
- View a personal workflow dashboard summarising assigned, pooled, and overdue tasks
- List and work "My Tasks" assigned to the current user, with filtering
- Browse the unassigned task pool and claim tasks into your queue
- Release a claimed task back to the pool
- Open a task detail view to review its object, step, and history
- Approve a task (advancing it to the next step) or reject it back with a reason
- View "My Work" and "Team Work" consolidated task lists
- Browse per-queue task lists and move tasks between queues
- View an overdue-tasks report of items past their due date
- View a task timeline / history audit trail of all transitions

### Bulk Operations
- Preview a bulk action across multiple selected tasks before applying
- Bulk-transition many tasks through a workflow step at once
- Bulk-assign tasks to a user
- Bulk-add a note to many tasks
- Bulk-set task priority
- Bulk-move tasks to another queue

### Workflow Design and Administration
- Create, edit, and delete workflow definitions
- Add, edit, and delete steps within a workflow
- Adjust global workflow settings
- View a read-only visual diagram of a workflow's steps and transitions
- View a task's live progress overlaid on the workflow diagram
- Use a drag-and-drop designer to lay out and save workflow step graphs

### Publish Gates
- Administer publish-gate rules that block object publication until conditions are met
- Create, edit, and delete gate rules
- Check an object's publish-readiness against gate rules
- Run a publish simulation for an object to preview whether it would pass the gates

### Spectrum 5.1 Compliance
- Install / re-install the Spectrum 5.1 procedure pack (21 canonical museum procedures) from the admin screen
- View a Spectrum compliance dashboard tracking procedure coverage/status
- Export Spectrum compliance data as CSV
- Configure Spectrum chain rules (procedure sequencing) and create, save, or delete them

### CLI Commands
- `php artisan workflow:seed-spectrum {--overwrite} {--only=*} {--dry-run}` - Install the Spectrum 5.1 procedure starter pack (21 workflows with paraphrased canonical steps); optionally overwrite existing, restrict to specific procedure codes, or dry-run
- `php artisan spectrum:overdue {--days=14} {--notify=} {--inbox=} {--dry-run}` - Scan for Spectrum-tagged tasks past the overdue threshold and drop Workbench notifications for the configured user
- `php artisan workflow:notify-overdue {--repeat-days=} {--dry-run} {--limit=500}` - Email assignees of tasks past their due date that have not been nagged within the repeat window

## Vendor and Procurement Management (ahg-vendor)

### Vendor Directory
- View a vendor dashboard/index with summary information
- Browse and list vendors
- View an individual vendor by slug
- Create, edit, and delete vendors (ACL-gated by create/update/delete)
- Store vendor PII (contact details, financial data) encrypted at rest, with redacted audit snapshots

### Vendor Contacts
- Add, update, and delete contact people against a vendor
- Keep contact PII encrypted and decrypt on read for authorized users

### Transactions and Line Items
- Browse vendor transactions
- Create, view, and edit transactions
- Update a transaction's status (status values driven by the `vendor_transaction_status` taxonomy)
- Add, update, and remove individual line items within a transaction

### Service Types
- Manage the list of vendor service types (view and edit)

### CLI Commands
- `php artisan ahg:vendor-encrypt-backfill {--dry-run}` - Idempotently encrypt existing vendor and vendor-contact PII rows (contact_details + financial_data); dry-run counts rows that would be encrypted without writing

## Z39.50 / SRU Bibliographic Search and Copy Cataloguing (ahg-z3950)

### Z39.50 Client (Copy Cataloguing)
- Browse a Z39.50 dashboard listing configured remote target host/port/database profiles
- Run bibliographic searches against remote Z39.50 targets (e.g. Library of Congress, WorldCat) by title, author, subject, ISBN, ISSN, LCCN, and more
- Review a search result set of returned MARC records
- Import a single record from a result set into the local catalogue
- Import a batch of selected records from a result set into the catalogue

### Remote Target Administration
- View the target admin screen of configured Z39.50 targets
- Create and store new remote target profiles (host, port, database, syntax, element set)
- Delete remote target profiles

### SRU / Z39.50 Server (Exposing the Catalogue)
- Serve an SRU endpoint (`/sru`) answering `explain` and `searchRetrieve` operations, returning MARCXML records with SRU diagnostics
- Parse incoming CQL queries and echo the search request per the SRU response spec
- Expose the Heratio catalogue as an ISO 23950 Z39.50 target consumable by external clients (Koha, Evergreen, Voyager, EndNote, etc.)
- Log SRU searches with timing and diagnostic details

### CLI Commands
- `php artisan z3950:server {--host=0.0.0.0} {--port=210} {--timeout=30}` - Start the Z39.50 bibliographic server daemon (ISO 23950), binding the given host/port with a per-client socket timeout

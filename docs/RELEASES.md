# Heratio Release History

All releases pushed via `./bin/release`. Current version: **0.32.105** (2026-03-22).

---

## v0.32.x - CSS/Theme Parity & Clone Completion

| Version | Description |
|---------|-------------|
| **0.32.105** | Storage manage: clone AtoM edit+show - 28 extended fields, 2col layout, capacity progress bars, badges, environmental/security sections |
| **0.32.104** | CSS final: add themed thead header rows to 10 tables (8 reports + orders + shared favorites) |
| **0.32.103** | CSS final: fix remaining 4 bad btn-* pages, 10 plain theads (reports/cart/favorites), wire real href='#' links - theme parity complete |
| **0.32.102** | CSS/AtoM parity: fix 10 atom-btn-secondary → atom-btn-white, remove 12 redundant thead CSS blocks, wire 27 href='#' to real routes, add field badges to 2 cataloguing partials - all 290 views now match theme |
| **0.32.101** | Data Migration: add 17 missing routes + controller methods + views - export, preservica-import/export, download, getMapping, jobProgress, queueJob, cancelJob, exportCsv, loadMapping, previewValidation, exportMapping, importMapping, validate, executeAhgImport, ahgImportResults; fix CSS to atom-btn-white + var(--ahg-primary) headers |
| **0.32.100** | IO show: add Move button and Create new rights link matching AtoM |
| **0.32.99** | IO copy: fix Cancel button to route back to source record (not browse page) matching AtoM |
| **0.32.98** | IO copy: switch to 2col layout with repo logo sidebar when copy_from present - match AtoM |
| **0.32.97** | IO edit: change to 2col layout with repository logo sidebar matching AtoM |
| **0.32.96** | IO copy: show Item-Title h1, add Security/Watermark/Admin sections when copy_from present - match AtoM copy page |
| **0.32.95** | IO edit: match AtoM - Item ID-Title h1, add Security/Watermark/Admin sections back, fix io-manage namespace |
| **0.32.94** | Settings h1 match AtoM, fix critical io-manage namespace error (No hint path defined) |
| **0.32.93** | Clipboard load: fix buttons to atom-btn-outline-success, add green accordion headers |
| **0.32.92** | Description updates: match AtoM - dynamic h1, Title/Repository/Created columns, table-bordered, atom-btn-outline-light buttons, green headers |
| **0.32.91** | Site Information: fix siteDescription from textarea to input[text] matching AtoM |
| **0.32.90** | Themes page: match AtoM - h1 List themes, Save button atom-btn-outline-success |
| **0.32.89** | Plugins page: match AtoM - h1 List plugins, Save button atom-btn-outline-success |
| **0.32.88** | Menu browse: match AtoM - simple table with Name/Label columns, Site menu list h1, atom-btn-outline-light, green headers |
| **0.32.87** | Static pages browse: match AtoM - h1 List pages, remove Actions column |
| **0.32.86** | ACL Groups browse: match AtoM - simple table with Group/Members columns, List groups h1, Add new button, green headers |
| **0.32.85** | Admin users: match AtoM - h1 List users, 4 columns (User name/Email/User groups/Updated), atom-btn-outline-light, green headers |
| **0.32.84** | SKOS import: fix url field type to text, add green accordion headers |
| **0.32.83** | XML/CSV Import: add collection select field (Limit matches to), green accordion headers |
| **0.32.82** | Central Dashboard: fix plugin checks (ahgIngestPlugin, ahgResearchPlugin), all 35 cards now visible and ordered matching AtoM |
| **0.32.81** | Central Dashboard: fix plugin name checks so Knowledge Platform and Data Ingest cards appear, scope CSS to main content |
| **0.32.80** | Central Dashboard: reorder 35 cards to match AtoM, add Research/Knowledge/Donor/DataIngest cards, rename URL to /reports, green headers |
| **0.32.79** | Reports dashboard: rename URL to /reports matching AtoM, add green card headers, keep /admin/reports as alias |
| **0.32.78** | Request to Publish browse: match AtoM - h1, 8 column headers, green headers |
| **0.32.77** | Feedback browse: match AtoM - h1 Feedback Management, Subject/Record header, table-hover class, green headers |
| **0.32.76** | Taxonomy list: add green table headers using central theme var(--ahg-primary) |
| **0.32.75** | Rights holder browse: always show Updated column, add green table headers matching AtoM |
| **0.32.74** | Physical object browse: add green table headers using central theme var(--ahg-primary) |
| **0.32.73** | Jobs browse: match AtoM exactly - 6 columns, Manage jobs h1, atom-btn-outline-light actions, green headers, remove stats/filters |
| **0.32.72** | Donor browse: always show Updated column, add green table headers matching AtoM |
| **0.32.71** | Accession browse: add Status + Priority columns, green table headers matching AtoM |
| **0.32.70** | Function add/edit: remove Relationships area (not on AtoM add page), add green accordion headers |
| **0.32.69** | Term add/edit: add green accordion headers using central theme var(--ahg-primary) |
| **0.32.68** | Repository add/edit: add green accordion headers using central theme var(--ahg-primary) |
| **0.32.67** | Actor add/edit: green accordion headers, fix 5 input types |
| **0.32.66** | Accession add/edit: green accordion headers, fix note fields to textarea - match AtoM exactly |
| **0.32.65** | IO add/edit: add green accordion headers using central theme var(--ahg-primary) |
| **0.32.64** | IO add/edit: exact AtoM match - 8 ISAD(G) sections, 36 logical fields |
| **0.32.63** | IO add/edit: match AtoM exactly - 8 ISAD(G) sections, remove Security/Watermark/Admin sections, events/childLevels repeaters |
| **0.32.62** | DAM create: match AtoM exactly - 12 card sections, 59 fields, parent_id |
| **0.32.61** | Library add/edit: exact AtoM clone - 40 form fields, 14 item location fields |
| **0.32.60** | Library add/edit: complete rewrite to match AtoM - 2-column card layout, creator/subject repeaters, ISBN lookup |
| **0.32.59** | Gallery add/edit: fix 18 field levels and 14 input types to match AtoM exactly |
| **0.32.58** | Museum add/edit: exact AtoM clone - 33 CCO fields, all levels/badges/input types match |
| **0.32.57** | Museum add/edit: fix field levels (17 Recommended + 1 Required) and 11 input types |
| **0.32.56** | Gallery add/edit: remove State/Edition section (not shown in AtoM Painting template) |
| **0.32.55** | Museum add/edit: restructure to match AtoM General template exactly - 13 CCO sections, 33 fields |
| **0.32.54** | Gallery add/edit: restructure to match AtoM exactly - 14 CCO sections with identical fields |
| **0.32.53** | Gallery add/edit: add category descriptions, font-size overrides, Generate identifier button |
| **0.32.52** | Gallery add/edit: add CCO field badges, help toggles, cco-field wrappers |
| **0.32.51** | Museum add/edit: add CCO field badges, help toggles, cco-field wrappers |
| **0.32.50** | Gallery add/edit: clone AtoM - add Item Physical Location + Administration Area sections |
| **0.32.49** | Museum add/edit: clone AtoM exactly - add Item Physical Location, Watermark Settings, Administration Area sections |
| **0.32.48** | Accession browse: clone AtoM exactly - simple h1 title, no Status/Priority/clipboard columns |
| **0.32.47** | Menu bar sort order: match AtoM exactly |
| **0.32.46** | Menu bar: clone AtoM exactly - search box classes, logo 35px, clipboard-menu ID |
| **0.32.45** | Menu bar: match AtoM exactly - clipboard ID clipboard-menu, logo height 35px |
| **0.32.44** | Heritage search sidebar: compute facets from ALL published items (match AtoM FilterService) |
| **0.32.43** | Heritage search: match AtoM date format, facet icons match AtoM |
| **0.32.42** | Heritage search: expand to taxonomy terms + creator names + identifier |
| **0.32.41** | Heritage search: fix OR search logic, numeric IDs for filter URLs, Font Awesome icons |
| **0.32.40** | Heritage search page: full-text search with faceted sidebar, result cards, pagination |
| **0.32.39** | Logout redirects to /heritage instead of /login - match AtoM behaviour |
| **0.32.38** | Heritage landing page: exact AtoM clone - 148/148 controls match |
| **0.32.37** | Heritage landing page: clone AtoM exactly - fix hero image, config parse, suggested searches, all URLs |
| **0.32.36** | Heritage landing page: Rijksstudio-style with 7 sections |
| **0.32.35** | Browse pages AtoM clone: grid view cards, pagination, taxonomy URL route |
| **0.32.34** | Taxonomy: add Heratio-compatible URL route with redirect |
| **0.32.33** | Browse pages: AtoM clone - GLAM, repository, function, taxonomy browse all match |
| **0.32.32** | Taxonomy browse + pager: AtoM clone - 2col layout, sidebar treeview, Results text |
| **0.32.31** | Taxonomy browse: AtoM clone - 2col layout with sidebar treeview, section slots |
| **0.32.30** | Function browse: AtoM clone - body class, title, sort order, dropdown IDs |
| **0.32.29** | Function browse: AtoM clone - body class, title, sort order Name-first |
| **0.32.28** | Repository browse: AtoM clone - 6 sidebar facets, advanced search, sort default Date modified |
| **0.32.27** | Repository browse: AtoM clone - 6 sidebar facets, advanced search, sort default to Date modified |
| **0.32.26** | GLAM browse: final AtoM clone polish - sidebar/main IDs, facet li d-flex, per-page dropdown |
| **0.32.25** | GLAM browse: 100% AtoM clone - sidebar facets, toolbar, card layout, advanced search |
| **0.32.24** | GLAM browse: 100% AtoM clone - sidebar facets, toolbar, card layout |
| **0.32.23** | DAM create: verified - all 59 AtoM fields present |
| **0.32.22** | DAM create: match AtoM - rename 39 IPTC fields to iptc_ prefix |
| **0.32.21** | Library add: verified - all 41 AtoM fields present |
| **0.32.20** | Library add: match AtoM fields - add summary, rename pages→pagination |
| **0.32.19** | Gallery add: full AtoM clone - 2col sidebar, all 53 CCO fields |
| **0.32.18** | Museum add: verified - all 53 AtoM CCO fields present |
| **0.32.17** | Museum add: complete AtoM clone - all 53 CCO fields, 2col sidebar |
| **0.32.16** | Museum add: full AtoM clone - 2col layout with sidebar |
| **0.32.15** | Museum add: match AtoM CCO section names and chapter numbers |
| **0.32.14** | Museum add: clone AtoM CCO theme - green accordion headers |
| **0.32.13** | Function add: verified 100% AtoM ISDF clone |
| **0.32.12** | Function add: clone AtoM ISDF - 3 relationship tables |
| **0.32.11** | Term add: verified 100% AtoM clone - 14 controls, 3 multi-row note tables |
| **0.32.10** | Term add: clone AtoM - 3 multi-row note tables |
| **0.32.9** | Repository add: verified 100% AtoM clone - 38 controls, 7 sections |
| **0.32.8** | Repository add: clone AtoM ISDIAH - table+modal contact |
| **0.32.7** | Actor add: replace combined other-names with 3 separate ISAAR fields |
| **0.32.6** | Actor add: match AtoM - relationships tables, occupations, 9 missing contact fields |
| **0.32.5** | Accession add: add missing ISAD Date(s) multi-row table |
| **0.32.4** | IO add: apply AtoM theme CSS - table-bordered, table-light headers, atom-btn-white |
| **0.32.3** | Accession add: match AtoM - alt identifiers table, events table, donor modal |
| **0.32.2** | IO add: match AtoM control parity - add 24 missing controls |
| **0.32.1** | Apply consistent AtoM theme CSS to all 4 GLAM sector packages |

## v0.32.0 - GLAM Sectors

| Version | Description |
|---------|-------------|
| **0.32.0** | Add 4 GLAM sector packages (DAM/IPTC, Library/MARC, Museum/CCO, Gallery/CCO) with full CRUD |

## v0.31.0

| Version | Description |
|---------|-------------|
| **0.31.0** | Add 4 GLAM sector packages with full browse/create/edit/show/dashboard |

## v0.30.x - Browse Parity, Heritage, Research

| Version | Description |
|---------|-------------|
| **0.30.60** | Clone AtoM accession add/edit: 4 sections, all 33 fields with help text |
| **0.30.59** | IO add: verified field-by-field match with AtoM rendered HTML |
| **0.30.58** | IO add: add Date(s)/Language/Script/Publication fields from AtoM |
| **0.30.57** | Clone AtoM IO add page from actual rendered HTML |
| **0.30.56** | Clone AtoM IO add page: accordion layout, ISAD help text |
| **0.30.55** | Match AtoM button styles on all create/edit forms |
| **0.30.54** | Populate footer static pages with proper content |
| **0.30.53** | Create footer static pages and link in footer |
| **0.30.52** | Merge footer custom text and Heratio version into single line |
| **0.30.51** | Extended configurable footer |
| **0.30.50** | Fix static page display: add prose styling, Edit button |
| **0.30.49** | Add static page edit/create |
| **0.30.48** | Fix term browse sort direction |
| **0.30.47** | Fix term browse sort direction (duplicate) |
| **0.30.46** | Fix mouse scroll: use overflow-y auto |
| **0.30.45** | Match AtoM function browse |
| **0.30.44** | Rename sidebar heading to 'Creator of' |
| **0.30.43** | Fix Browse results to filter by creator param |
| **0.30.42** | Actor show: 3-column layout |
| **0.30.41** | Actor show: move Browse results to sidebar |
| **0.30.40** | Clone AtoM actor show page layout |
| **0.30.39** | Match AtoM actor browse rows |
| **0.30.38** | Match AtoM sort button text |
| **0.30.37** | Match AtoM repository grid view |
| **0.30.36** | Increase repository browse thumbnail sizes |
| **0.30.35** | Add repository logo thumbnails to browse list/grid |
| **0.30.34** | Match AtoM repository browse table layout |
| **0.30.33** | Fix actor browse: filter to QubitActor class only |
| **0.30.32** | Search bar goes to /glam/browse |
| **0.30.31** | Fix GLAM browse sort direction |
| **0.30.30** | Add configurable site description bar and footer colours |
| **0.30.29** | Fix footer: ahg-site-footer with configurable colours |
| **0.30.28** | Fix footer: primary colour, override bundle styles |
| **0.30.27** | Fix footer layout, add io.updateStatus route, fix page background CSS |
| **0.30.26** | Fix page background colour: write dynamic CSS to nginx static file |
| **0.30.25** | Fix page background colour not saving |
| **0.30.24** | Fix login double alert, dashboard tab HTML, 5 research pages, clipboard errors |
| **0.30.23–0.30.20** | Fix login, dashboard, research pages, clipboard errors (multiple iterations) |
| **0.30.19** | Fix login, add 5 missing research pages from AtoM |
| **0.30.18** | Port AtoM iiif-manifest.php to Laravel route |
| **0.30.17** | Add Heratio-style security pages |
| **0.30.16–0.30.10** | Fix research package (sidebar, routes, columns, Carbon, pagination) |
| **0.30.9–0.30.1** | Research dashboard alignment with AtoM (multiple iterations) |

## v0.30.0 - Dropdown Manager, Clearances, Help Center

| Version | Description |
|---------|-------------|
| **0.30.0** | Align with AtoM: dropdown manager, clearances page, help center package (201 articles), fix integrity + portable export |

## v0.29.x

| Version | Description |
|---------|-------------|
| **0.29.1** | Dropdown Manager: align with AtoM - 22 section icons, button colors, sidebar |
| **0.29.0** | Add Help Center package (201 articles from DB), fix integrity + portable export |

## v0.28.x - Full REST API, User Admin

| Version | Description |
|---------|-------------|
| **0.28.4–0.28.2** | Fix portable export schema, theme namespace |
| **0.28.1** | User add/edit: match AtoM 6-accordion layout |
| **0.28.0** | Full REST API: v1 CRUD (7 endpoints), v2 REST (56 endpoints), 4 legacy - API keys, webhooks, rate limiting, CORS, batch ops |

## v0.27.0 - Cron Scheduler

| Version | Description |
|---------|-------------|
| **0.27.0** | DB-driven cron scheduler: cron_schedule table, CronSchedulerService, 3 CLI commands, interactive web UI |

## v0.26.x - Media Processing

| Version | Description |
|---------|-------------|
| **0.26.1** | Add Cron Jobs settings page |
| **0.26.0** | Add 6 media processing packages: 3D models, image derivatives, AI/LLM, metadata extraction, PDF tools, media streaming - 50 packages total |

## v0.25.x - Full AtoM Parity

| Version | Description |
|---------|-------------|
| **0.25.1** | Final 3 fixes: term edit, actor/repository Add new buttons, menu reorder |
| **0.25.0** | Full AtoM parity: actor 6 sidebar facets, repository 3 facets, integrity, storage export, conditional columns |

## v0.24.0 - Import & Admin Fixes

| Version | Description |
|---------|-------------|
| **0.24.0** | Full menu parity: 3 Import pages, admin fixes, Repository facet, show page buttons, edit form consistency |

## v0.23.x - Admin Menu Completion

| Version | Description |
|---------|-------------|
| **0.23.1** | Add API comparison doc: 94 missing endpoints |
| **0.23.0** | Admin menu: all 12 items resolve - static pages, plugins, description updates, global search/replace, portable export, integrity check |

## v0.22.0 - Entity Edit Forms

| Version | Description |
|---------|-------------|
| **0.22.0** | Add menu: GLAM/DAM items; Edit forms: Notes, Access points, Relationships, Language/Script, Creators, Alt identifiers |

## v0.21.x - Reports Dashboard

| Version | Description |
|---------|-------------|
| **0.21.2** | Reports dashboard: 100% AtoM parity - all conditional sections, card colors, links |
| **0.21.1** | Fix isset() error |
| **0.21.0** | Reports: Central Dashboard, plugin detection, spatial analysis, /reports alias |

## v0.20.x - Browse Page Parity

| Version | Description |
|---------|-------------|
| **0.20.11** | SettingHelper: all browse pages read hits_per_page from DB |
| **0.20.10** | Taxonomy list: match AtoM table layout |
| **0.20.9** | Add taxonomy/list route, require auth |
| **0.20.8** | Add global .actions CSS matching AtoM |
| **0.20.7** | Match AtoM inline search |
| **0.20.6** | Rights holder + Physical storage browse: match AtoM |
| **0.20.5** | Donor browse: match AtoM |
| **0.20.4** | Accession browse: add sort direction, export CSV |
| **0.20.3** | Jobs, feedback admin, request-to-publish |
| **0.20.2** | Match AtoM browse controls: clipboard, help, search, repository sidebar |
| **0.20.1** | Actor browse: sidebar facets, advanced search, grid/list toggle |
| **0.20.0** | AHG Plugins: 7 new packages, enhance 4 existing, wire all 27 menu items |

## v0.19.x - Navigation, Search, Terms

| Version | Description |
|---------|-------------|
| **0.19.34** | AHG Plugins menu: match AtoM exactly |
| **0.19.33–0.19.30** | GLAM browse refinements, advanced search |
| **0.19.29** | Refactor: move API + OAI-PMH to packages - 32 packages total |
| **0.19.28–0.19.26** | Feedback package, Markdown fix |
| **0.19.25–0.19.19** | Quick Links, Homepage sidebar, settings pages |
| **0.19.18** | Site information: match AtoM exactly |
| **0.19.17–0.19.12** | Function/term browse: match AtoM |
| **0.19.11–0.19.5** | Term browse: sidebar treeview, search, protected terms |
| **0.19.4–0.19.0** | IIIF image viewer: OpenSeadragon + Mirador |

## v0.18.x - IO Show, Settings, Term Show

| Version | Description |
|---------|-------------|
| **0.18.26–0.18.20** | Thumbnails, search options, term show |
| **0.18.19–0.18.13** | Legacy URLs, media, section headers, transcription |
| **0.18.12–0.18.7** | IO show: transcription, snippets, favorites, cart, loan, duplicate |
| **0.18.6–0.18.3** | Settings: generic section, AHG section pages, nav bar icons |
| **0.18.2–0.18.1** | Settings pages: dedicated forms, fix routes |
| **0.18.0** | Data Migration, Workflow, Preservation, Loan, ACL packages |

## v0.17.0 - Settings, Reports, API, Cart

| Version | Description |
|---------|-------------|
| **0.17.0** | Settings dashboard (13 sections), Reports suite (10 types + CSV), REST API v1, Cart/e-commerce, Favorites |

## v0.16.0 - DB Menus, API, OAI-PMH

| Version | Description |
|---------|-------------|
| **0.16.0** | DB-driven menus, REST API v1, OAI-PMH 2.0, print views |

## v0.15.0 - Clipboard & Advanced Search

| Version | Description |
|---------|-------------|
| **0.15.0** | Clipboard + Advanced Search: localStorage, ES faceted search, advanced form |

## v0.14.0 - Treeview & Digital Objects

| Version | Description |
|---------|-------------|
| **0.14.0** | Treeview sidebar navigation + digital object upload |

## v0.13.x - Import & Finding Aids

| Version | Description |
|---------|-------------|
| **0.13.1** | Import/FindingAid jobs: EAD XML + CSV import, PDF finding aid generation |
| **0.13.0** | Full CRUD: all entities, IO sidebar services, user/menu admin - 16 services, ~90 routes |

## v0.12.0 - IO Sidebar Services

| Version | Description |
|---------|-------------|
| **0.12.0** | IO sidebar: 6 new services (Condition, Provenance, Rights, Preservation, AI/NER, Privacy) |

## v0.11.0 - Entity CRUD

| Version | Description |
|---------|-------------|
| **0.11.0** | Full CRUD for Accession, Function, Physical Storage, Term/Taxonomy |

## v0.10.0 - Actor/Repository CRUD

| Version | Description |
|---------|-------------|
| **0.10.0** | Full CRUD for Actor, Repository, Donor, Rights Holder |

## v0.9.0 - v0.5.0 - Foundation

| Version | Description |
|---------|-------------|
| **0.9.0** | Actor + Repository full CRUD with ACL middleware |
| **0.8.x** | IO show, research portal (39 views), IIIF collections, login system, admin packages, search, ACL, CRUD, branding |
| **0.7.0** | ACL middleware, CRUD forms, static pages, audit trail, menus, reports |
| **0.6.0** | Search (Elasticsearch) and digital object display |
| **0.5.0** | CLAUDE.md, bin/release, version.json |

## Foundation (pre-versioning)

| Phase | Description |
|-------|-------------|
| Phase 4 | Admin packages - user-manage, settings, jobs-manage |
| Phase 3 | Core entity browse/show pages for 9 entity types |
| Phase 2 | Theme layout with 16 Blade templates, all nav menus, 17MB static assets |
| Phase 1 | Laravel 12 foundation with 60 Eloquent models, AtoM auth, core services |

---

## Pending Release (unstaged)

**v0.32.106** (next) - Field badges + button class parity:
- 1,164 field badges added across 148 blade views (Required/Recommended/Optional)
- 3 bad button classes fixed (btn-light → atom-btn-white, btn-dark → atom-btn-white)
- Full control audit: `docs/FULL-CONTROL-AUDIT.txt` (361 views, 10,964 controls)
- Tracking worklist: `docs/CLONE-PARITY-WORKLIST.md`
- Audit script: `bin/audit-controls.php`

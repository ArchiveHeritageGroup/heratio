# RiC API Read-Side Coverage Audit

**Audit Date:** April 2026
**Last updated:** 2026-04-18 *(9 of 11 gaps closed — all P0 + all P1; only P2 remain)*
**Scope:** Heratio RiC package (`packages/ahg-ric/`) + dependent packages
**Focus:** READ-only operations missing from `/api/ric/v1/*` public HTTP API

## Status

- ✅ **API-R-1 List relations** — shipped 2026-04-18: `GET /api/ric/v1/relations?q=&page=&per_page=`
- ✅ **API-R-2 Relations for entity** — shipped 2026-04-18: `GET /api/ric/v1/relations-for/{id}`
- ✅ **API-R-3 Hierarchy** — shipped 2026-04-18: `GET /api/ric/v1/hierarchy/{id}?include=parent,children,siblings`
- ✅ **API-R-4 Autocomplete** — shipped 2026-04-18: `GET /api/ric/v1/autocomplete?q=&types=&limit=`
- ✅ **API-R-5 Vocabulary by taxonomy** — shipped 2026-04-18: `GET /api/ric/v1/vocabulary/{taxonomy}`
- ✅ **API-R-6 Linked RiC entities for record** — shipped 2026-04-18: `GET /api/ric/v1/records/{id}/entities`
- ✅ **API-R-7 Entity info card** — shipped 2026-04-18: `GET /api/ric/v1/entities/{id}/info`
- ✅ **API-R-8 Relation types with domain/range filter** — shipped 2026-04-18: `GET /api/ric/v1/relation-types?domain=&range=`
- ✅ **API-R-9 Places flat picker** — shipped 2026-04-18: `GET /api/ric/v1/places/flat?exclude_id=`
- ⚠ **API-R-10 Full query params on browse endpoints** — deferred (P2)
- ⚠ **API-R-11 Vocabulary discovery index** — deferred (P2)

---

## Executive Summary

The RiC API currently covers core entity retrieval (Places, Rules, Activities, Instantiations, Agents, Records, Functions, Repositories) and SPARQL access. However, significant operational gaps exist in:

1. **Relations listing and filtering** — `/admin/ric/relations` browse with global relation search has no API equivalent.
2. **Hierarchy and containment queries** — Parent/child/sibling walks are available only via service layer, not the API.
3. **Autocomplete across all entity types** — Used in forms and relation editors; not exposed as a public endpoint.
4. **Dropdown/taxonomy reads** — Type pickers (place_type, rule_type, activity_type, carrier_type) are admin-only; no public vocabulary endpoint.
5. **Entity-to-entity linking context** — Record-level entity panels fetch linked Places, Rules, Activities, Instantiations; no single API endpoint aggregates this.
6. **Relation types with domain/range filtering** — Used to validate relation creation; filtering is missing from the public API.

These gaps will require filling if Heratio's UI is to become a pure HTTP API consumer.

---

## Gap Inventory

| # | UI Page / Context | Data Source (Service Method) | Current Status | Proposed Endpoint | Gap Type |
|---|---|---|---|---|---|
| 1 | `/admin/ric/relations` browse | `RicEntityController::browseRelations()` → `relation` table join with `ric_relation_meta` | Rendered server-side; no API equivalent | `GET /api/ric/v1/relations?q={query}&page={p}&per_page={n}` | missing-endpoint |
| 2 | Entity show page: relations list | `RicEntityService::getRelationsForEntity($id)` | Service-only (outgoing + incoming relations) | `GET /api/ric/v1/relations-for/{entity-id}` | missing-endpoint |
| 3 | Entity show page: hierarchy | `RicEntityService::getHierarchy($id)` (parent, children, siblings) | Service-only | `GET /api/ric/v1/hierarchy/{entity-id}?include=parent,children,siblings` | missing-endpoint |
| 4 | Relation editor: autocomplete | `RicEntityService::autocompleteEntities($query, $typeFilter)` | AJAX admin-only at `/admin/ric/entity-api/autocomplete` | `GET /api/ric/v1/autocomplete?q={query}&types={types}` | missing-endpoint |
| 5 | Dropdown pickers (Place Type, Rule Type, etc.) | `RicEntityService::getDropdownChoices($taxonomy)` | AJAX admin-only at `/admin/ric/entity-api/dropdown/{taxonomy}` | `GET /api/ric/v1/vocabulary/{taxonomy}` | missing-endpoint |
| 6 | Record show page: linked RiC entities | `RicEntityService::getEntitiesForRecord($id)` (aggregates places, rules, activities, instantiations) | Service-only; returned as single JSON blob | `GET /api/ric/v1/records/{id}/entities?types={types}` | missing-endpoint |
| 7 | Entity show page: relation types | `RicEntityService::getRelationTypes($domain, $range)` | Available at `RicEntityController::getRelationTypes()` but returns all; filtering is present only on form load | `GET /api/ric/v1/relation-types?domain={domain}&range={range}` | missing-param |
| 8 | Browse Places/Rules/Activities/Instantiations | `RicEntityService::browse*()` methods accept `search`, `page`, `per_page`, `sort`, `direction` | Rendered server-side only (no API equivalent) | `GET /api/ric/v1/places?search={q}&page={p}&sort={field}&direction={dir}` *(entity-specific)* | missing-param |
| 9 | Place hierarchy picker (parent selector) | `RicEntityService::listPlacesForPicker($excludeId)` | Service-only | `GET /api/ric/v1/places/flat?exclude_id={id}` | missing-endpoint |
| 10 | Relation editor: info card popover | `RicEntityController::getEntityInfo($id)` | AJAX at `/admin/ric/entity-api/info/{id}` (admin-only) | `GET /api/ric/v1/entities/{id}/info` | missing-endpoint |

---

## Prioritized Tickets

### P0: Required for UI Migration

**API-R-1. List Relations with Search & Pagination**  
Enable public read of the relation graph with filtering by predicate, evidence, and dropdown code.  
*Endpoint:* `GET /api/ric/v1/relations?q={query}&page={page}&per_page={limit}`  
*Response:* Paginated array of relation objects with `{id, subject_id, object_id, rico_predicate, dropdown_code, certainty, evidence, subject_class, object_class}`.

**API-R-2. Fetch Relations for a Specific Entity**  
Return all incoming and outgoing RiC relations for an entity, with cardinality and direction.  
*Endpoint:* `GET /api/ric/v1/relations-for/{entity-id}`  
*Response:* `{outgoing: [...], incoming: [...]}` grouped by predicate/type.

**API-R-3. Fetch Hierarchy (Parent, Children, Siblings)**  
Support containment walk for hierarchical entity types (Places, potentially custom hierarchies).  
*Endpoint:* `GET /api/ric/v1/hierarchy/{entity-id}?include=parent,children,siblings`  
*Response:* `{parent: {...}, children: [{...}], siblings: [{...}]}` with id, name, type, slug.

**API-R-4. Entity-Level Autocomplete with Type Filter**  
Searchable across all RiC entity types (Places, Rules, Activities, Instantiations, Agents, Records).  
*Endpoint:* `GET /api/ric/v1/autocomplete?q={query}&types={types}&limit={limit}`  
*Response:* `[{id, label, type}]` with configurable result limit.

**API-R-5. Dropdown Taxonomies (Vocabularies)**  
Expose type pickers and relation type metadata as public vocabulary endpoints.  
*Endpoint:* `GET /api/ric/v1/vocabulary/{taxonomy}` *(e.g., `/vocabulary/ric_place_type`)*  
*Response:* `[{code, label, color, icon, is_default, metadata}]`.

### P1: Completeness & UX

**API-R-6. Linked RiC Entities for a Record**  
Aggregate Places, Rules, Activities, Instantiations linked to a Record via relations.  
*Endpoint:* `GET /api/ric/v1/records/{id}/entities?types={types}`  
*Response:* `{places: [...], rules: [...], activities: [...], instantiations: [...]}`  
*Types filter:* comma-delimited subset of `place,rule,activity,instantiation`.

**API-R-7. Entity Info Card (Relation Editor Popover)**  
Quick fetch of entity metadata for relation editor inline display.  
*Endpoint:* `GET /api/ric/v1/entities/{id}/info`  
*Response:* `{id, name/title, type, slug, description (truncated)}`.

**API-R-8. Relation Types with Domain/Range Filtering**  
Support dropdown filtering by domain and range class in the relation editor.  
*Endpoint:* `GET /api/ric/v1/relation-types?domain={class}&range={class}`  
*Response:* Filtered subset of `[{code, label, metadata}]`; same shape as `/vocabulary/ric_relation_type` but filtered.

**API-R-9. Flat Place Picker (Exclude ID)**  
All places as a flat list for parent-selector dropdowns, with option to exclude a place (self-loop prevention).  
*Endpoint:* `GET /api/ric/v1/places/flat?exclude_id={id}`  
*Response:* `[{id, name}]` ordered by name.

### P2: Nice-to-Have

**API-R-10. Browse Endpoints with Full Query Support**  
Ensure list endpoints (places, rules, activities, instantiations) accept `search`, `sort`, `direction` parameters.  
*Endpoint:* `GET /api/ric/v1/places?search={q}&sort={field}&direction={asc|desc}&page={p}&limit={n}`  
*Current state:* Endpoints exist but lack search/sort/filter params; extend them.

**API-R-11. Vocabulary Complete Index**  
Comprehensive catalog of all supported taxonomies (RiC types, relation codes, etc.).  
*Endpoint:* `GET /api/ric/v1/vocabulary`  
*Response:* `{available_taxonomies: [...], description}` (discovery endpoint).

---

## Out of Scope

- **Write operations** (create, update, delete) — Covered in separate API-W audit.
- **Sync/queue/integrity/orphan reads** — Admin-only operational endpoints; out of scope for public API.
- **Semantic search** — Deferred to a future S (Search) audit.
- **SHACL validation** — Covered under existing `POST /api/ric/v1/validate`.
- **Dashboard metrics** — Admin-only charts and stats; out of scope.

---

## Implementation Notes

1. **Relation tables:** Must join `relation` with `ric_relation_meta` to return `rico_predicate`, `dropdown_code`, `certainty`, `evidence`, and domain/range classes.
2. **Autocomplete:** Search across `ric_place_i18n.name`, `ric_rule_i18n.title`, `ric_activity_i18n.name`, `ric_instantiation_i18n.title`, `actor_i18n.authorized_form_of_name`, `information_object_i18n.title` (multifield).
3. **Dropdown taxonomies:** Query `ahg_dropdown` where `taxonomy` = "{code}" and `is_active = 1`, ordered by `sort_order`.
4. **Hierarchy:** Use `relation` + `ric_relation_meta` WHERE `dropdown_code` IN ('has_part', 'includes', 'is_child_of', 'is_superior_of').
5. **Entity info:** Cache or serve with minimal i18n join to keep response fast for popovers.

---

**Document prepared:** 2026-04-18  
**Next step:** Begin API-R-1 implementation (list relations endpoint).

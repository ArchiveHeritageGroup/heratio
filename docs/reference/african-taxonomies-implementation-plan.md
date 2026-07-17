# African taxonomies - governance-and-access implementation plan (#1388)

Date: 2026-07-16
Status: **Phase 0 + Phase 1 BUILT & shipped** (v1.154.342-344, dev + prod + sasa + atom). See "Phase 1 - delivered" below. Remaining: ahg-icip label-metadata bind, CLI serializers (EAD/portable/metadata) operator-vs-public decision, badge owner-name resolution, Phase 2+.
Companion: `docs/reference/indigenous-tk-bc-plugin-design.md` (TK/BC-as-plugin + terminology), issue #1388, conference paper "Beyond Translation".

Turns the #1388 design (6 principles, CARE+FAIR, TK/BC Labels, RiC) into phased Heratio build work. The unit of curation is **term-plus-protocol-plus-owner**: a peer-identified, equally-multilingual/oral concept carrying a community-set access condition enforced at retrieval, display and export, crosswalked sideways to international standards.

## What Heratio already has (build on, don't rebuild)

- `ahg-term-taxonomy` - `term` (AtoM nested-set: id, taxonomy_id, code, parent_id, lft, rgt) + `term_i18n` multilingual. **No term-level protocol/owner yet** - this is the core gap.
- `ahg-icip` - `LocalContextsHubService` (TK/BC Label hub), `OcapService` (OCAP governance).
- `ahg-core` - `VocabularyResolverService` + `VocabularyImportCommand`/`VocabularyMirrorCommand` (Getty AAT, LCSH, Wikidata; mirrored locally).
- Enforcement primitives - `AhgCore\Services\DisclosureGate`, `ahg-research OdrlPolicyMiddleware`, ODRL + provenance (C2PA/fixity/PREMIS) substrate.
- Precedents - `actor.icip_sensitivity` (a sensitivity flag), Part B `AclService::addActorVisibilityCriteria`/`isActorVisible` (a working "gate a record for guests, editors bypass" pattern to mirror for terms).

## Data model - term-plus-protocol-plus-owner

New table `term_protocol` (one row per protocol-bearing term; keeps the AtoM `term` table untouched):

```
term_protocol
  id
  term_id            FK term.id
  label_family       enum(tk, bc)            -- Traditional Knowledge | Biocultural
  label_code         string                  -- TK/BC label (from the per-region plugin's set)
  access_condition   enum(open, attribution, non_commercial,
                          community_voice, restricted, sacred_secret, seasonal, gendered)
  owner_actor_id     FK actor.id NULL        -- source community / governing body (an authority record)
  region_module      string                  -- which per-region plugin owns it (southern_africa, ...)
  pid                string NULL             -- sovereign PID (DOCiD) once minted
  no_equivalent      bool                    -- "no Western equivalent" is a valid, information-bearing state
  created_by / audit fields
```

- **Sideways crosswalk** reuses the existing SKOS mapping infra: `skos_match(term_id, target_uri, match_type in {exact,close,related})` - African vocabularies as peers with their OWN uris; NEVER a `broader` edge under Getty/LCSH.
- **Oral-first i18n**: extend `term_i18n` (or a `term_media` side table) with `audio_do_id` (authoritative digital object) + `is_surrogate` on the text label (transcription marked as surrogate), per-label `@lang`, multi-script.

## Enforcement architecture (core engine + per-region plugin)

Mirrors the compliance-module pattern (GRAP/POPIA per-region on a neutral core):

- **Core (jurisdiction-neutral) - the protocol-enforcement engine.** A shared `TermProtocolGate` (in `ahg-core`, sibling to `DisclosureGate`): given a viewer + a term/record, resolves the effective `access_condition` (term-level OR inherited by any record tagged with a protocol-bearing term) and gates `odrl:use` / `odrl:reproduce` / export. Wired at the three choke points: **retrieval** (browse/search/API query filters), **display** (show-page render), **export** (OAI/EAD/portable-export/RiC serialisers). Community/owner and editors bypass per governance; guests get the community-set condition. Reuses ODRL + the Part-B visibility pattern.
- **Plugin (per-region, community-governed) - the label sets + owning communities.** Each region ships as a module (`ahg-protocols-southern-africa`, etc.) providing its TK+BC label vocabulary, categories, and the governing communities, riding the shared core gate. "Indigenous" is NOT hard-coded - communities self-identify (design-doc rule).

## Phasing

**Phase 0 - research DoD (satisfies #1388 section 6).** This plan doc (design note, KM-ingested) + the data-model proposal above + a DOCiD integration decision + a single end-to-end prototype (one African vocabulary as a peer authority, one protocol-bearing term, enforced at retrieval/display/export).

**Phase 1 - core term-protocol + enforcement engine.** `term_protocol` migration; `TermProtocolGate`; wire retrieval/display/export choke points; bind to `ahg-icip` TK/BC label handling. (Highest value; unblocks everything.)

**Phase 2 - peer vocabularies + sideways crosswalk.** Register African-owned authorities (DOCiD / Africa PID Alliance) as peers in `VocabularyResolverService`; SKOS exact/close/related-match to Getty/LCSH (never subordinated); mirror locally (`VocabularyMirrorCommand`) for sovereignty.

**Phase 3 - community governance.** Assignable editorial authority over a term (define/correct/restrict/release) to `owner_actor_id`, audit-logged via the existing audit trail; CARE/OCAP-aware (extend `OcapService`).

**Phase 4 - oral-first + sovereign PIDs.** Authoritative-audio term form + transcription-as-surrogate + multi-script; DOCiD minting on the C2PA/fixity substrate.

**Phase 5 - AI guardrail + per-region modules.** Fence protocol-restricted terms/material from NER/suggestion (model proposes, community disposes); package the first per-region protocol module (Southern Africa) beside the neutral core.

## Standards alignment
CARE (Indigenous Data Governance) + FAIR; Local Contexts TK/BC Labels; RiC-CM/RiC-O; ISAD(G), ISO 15489/23081/21127 (CIDOC-CRM). (Note: ISO 25539 cited in an earlier third-party draft is a cardiovascular-implant standard - not applicable.)

## Risks / decisions to confirm
- DOCiD/Africa PID Alliance integration path + availability (Phase 2/4 dependency).
- Whether protocol inheritance is term->record (a record tagged with a restricted term inherits its condition) - recommended yes; confirm scope.
- Per-region module governance/hosting (who administers each community module).
- Interaction with the existing Part-B actor draft/embargo gate + `icip_sensitivity` (unify under the enforcement engine, don't duplicate).

## Phase 0 / 1 - buildable task breakdown

### Phase 0 - research DoD (small; unblocks the go/no-go)
- **0.1 Design note + data-model proposal** - this doc (done); satisfies the first two #1388 section-6 DoD items.
- **0.2 Sovereign-PID decision spike** - probe Africa PID Alliance / DOCiD: API availability, auth, minting/resolution, licensing. Deliverable: a go/no-go + integration note in `docs/reference/`. No code. (Blocks P2/P4; do first.)
- **0.3 End-to-end prototype (one term)** - register ONE African vocabulary as a peer authority (own URI namespace) in `VocabularyResolverService`; add one `term_protocol` row (`access_condition = restricted`); wire the gate for that single term; demonstrate guest = gated / owner+editor = visible, at retrieval, display and export. Throwaway-ish; proves the model before P1 hardening.

### Phase 1 - core term-protocol + enforcement engine (the real build)
- **1.1 Migrations** (`ahg-term-taxonomy`, registered via `loadMigrationsFrom` - see the package-migrations rule): `term_protocol` table (schema above); `skos_match` (term_id, target_uri, match_type) if not already present; oral-first `term_i18n` additions (`audio_do_id`, `is_surrogate`).
- **1.2 `TermProtocolService`** - CRUD for term protocols; `effectiveCondition(termId)`; `conditionForRecord(objectId)` (inherit the strictest condition of any protocol-bearing term tagged on the record). Cache like `AclService`.
- **1.3 `TermProtocolGate`** (`ahg-core`, sibling to `DisclosureGate`) - `allows(viewer, term|record, action)` where action in `{view, use, reproduce, export}`; owner (`owner_actor_id`) + editors/admins bypass; guests get the community-set condition. Reuse the Part-B `AclService` group-check + a query-scope helper `addTermVisibilityCriteria($q, $idCol)` mirroring `addActorVisibilityCriteria`.
- **1.4 Wire the three choke points:**
  - *Retrieval* - term-taxonomy browse + IO/actor browse/search/API: filter or annotate records carrying a restricted term for guests.
  - *Display* - show-page render: gate restricted content, render the Local Contexts TK/BC label banner (via 1.5).
  - *Export* - OAI-PMH, EAD, portable-export, RiC serialisers: suppress or annotate restricted terms/records (fail-closed, like the redaction path).
- **1.5 Bind to `ahg-icip`** - map `label_code` -> Local Contexts label metadata (`LocalContextsHubService`) for the banner + tooltip; governance events through `OcapService`.
- **1.6 Admin UI** - term edit form: protocol fields (label family/code, access_condition, owner community autocomplete, region module); editor-only "Draft/Restricted" badge on the term/show page (reuse the Part-B badge pattern).
- **1.7 Tests** - guest vs owner vs editor across retrieval/display/export for a protocol-bearing term + record inheritance + the `no_equivalent` state.

Sequencing: 0.2 then 0.3 (validate), then 1.1 -> 1.3 (the gate is the linchpin, build it before wiring), then 1.4 surface-by-surface, then 1.5/1.6/1.7. Per-region modules (P5) only after the neutral core is solid.

---

## Phase 1 - delivered (v1.154.342 - v1.154.344)

Built and deployed to dev + prod + sasa + atom.

- **1.1 Migration** - `term_protocol` table via `ahg-term-taxonomy` (`loadMigrationsFrom` registered): `term_id`, `label_family` (tk/bc), `label_code`, `access_condition`, `owner_actor_id`, `region_module`, `pid`, `no_equivalent`, `created_by`. No hard FKs (fail-soft on partial installs). *(v1.154.342)*
- **1.2 `TermProtocolService`** (`ahg-core`) - `effectiveCondition(termId)`, `conditionForRecord(objectId)` (strictest inherited condition via `object_term_relation`), `isRestricted()`, `set()` (persist/clear from the form), `protocolsForTerm()` (badge data), `restrictedRecordIds()` (batch set for offline-export gate). `RESTRICTED = sacred_secret / restricted / gendered / seasonal / community_voice`; usage-obligation labels (open/attribution/non_commercial) stay visible. Try/catch → `open` so a missing table never 500s.
- **1.3 `TermProtocolGate`** (`ahg-core`, sibling to `DisclosureGate`) - `allowsTerm()`, `allowsRecord()`, query scopes `addTermVisibilityCriteria()` + `excludeRestrictedRecords()`. Editors/admins bypass via `AclService` group check.
- **1.4 Choke points wired** - term show (404 for guests), term browse (`TermBrowseService`), public display / print / export (`DisplayController`), OAI-PMH harvest (shared `publishedQuery`), RiC linked-data API (list + showRecord + exportRecordSet), portable offline-export bundle (`BundleWorkerCommand::applyDisclosureGates`, counted as `protocol` in `disclosure-summary.json`). *(v1.154.343-344)*
- **1.6 Admin UI + badge** - term add/edit form Community-protocol fieldset; `store()` + `update()` both persist; provenance badge on the public term show page (TK/BC label + condition + region, colour-coded). *(v1.154.343-344)*
- **1.7 Tests** - `tests/Feature/TermProtocolGateTest.php` 6/6 (restricted term/record hidden, open stays visible, editor bypass, `set()` round-trip, badge data). Made deterministic via `AclService::forgetUser()`.

- **1.5 (partial)** - bound to `ahg-icip`'s canonical Local Contexts catalog (`icip_tk_label_type`): the term edit form offers a TK/BC label **dropdown** (`TermProtocolService::labelCatalog()`), the family auto-derives from the chosen code, and the term-page badge resolves the official **name + description tooltip + Local Contexts link** (`labelMeta()`) plus the **owning-community name**. *(v1.154.346)*

**Remaining (Phase 1 tail / later):**
- **1.5 (rest)** - the external `LocalContextsHubService` Hub API is still a stub (live project/notice sync), and `OcapService` governance events aren't emitted on protocol change.
- **EAD / portable / metadata *CLI* serializers** - the public OAI/RiC surfaces and the portable *bundle* fail closed; the operator-run CLI export commands need an explicit operator-vs-public gating decision (they run with an operator identity, not a guest).
- Badge **owner-actor name** resolution (currently shows label + condition + region, not the owning community's authority-record name).


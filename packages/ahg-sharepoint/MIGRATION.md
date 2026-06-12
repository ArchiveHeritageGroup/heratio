# MIGRATION — SharePoint federated search into `ahg-sharepoint` (issue #1221)

> Status: **Step 2 of N — connector now self-contained + WORKING inside
> `ahg-sharepoint`; the only remaining change is operator-only (the `/opt`
> dispatcher patch + promotion + re-lock + AtoM mirror).**
>
> Step 2 added a package-owned, working SharePoint federated-search surface
> (config helper + runner + controller + route + view) and a connector-discovery
> registry, all inside `packages/ahg-sharepoint/`. It changed **no** file in
> `ahg-federation` and **no** file under `/opt/ahg-sp-integration/F3/`. The one
> unavoidable upstream edit (extensible dispatcher) is written as an
> operator-only patch in [`cutover.patch`](cutover.patch) — see step C below.
>
> See "Step 2 — DONE vs remaining" near the end for the current state.

## Why

`ahg-federation` is a **general** federation package (OAI-PMH harvesting, union
catalogue, loans, join-network, Europeana EDM, the cross-peer search
dispatcher). The Microsoft 365 / SharePoint Graph search connector is the one
peer type that drags a hard dependency on `ahg-sharepoint`
(`GraphClientService`, `sharepoint_tenant` config) into that general package.
Issue #1221 relocates the SharePoint-specific connector into this dedicated
`ahg-sharepoint` package so:

- the general federation package stays free of Microsoft-Graph coupling;
- all SharePoint surface (ingest/sync + federated search) lives in one place;
- an install that does not use SharePoint can drop the whole package.

The general federation engine, contract, dispatcher, controller, routes, and
the non-SharePoint connectors (`OaiPmhConnector`, `AtomElasticsearchConnector`)
**stay in `ahg-federation`**.

## Canonical sources

| Surface | Canonical source of truth |
| --- | --- |
| F3 federated-search peer (Heratio side) | `/opt/ahg-sp-integration/F3/heratio/` (read-only reference) |
| F3 federated-search peer (AtoM side) | `/opt/ahg-sp-integration/F3/atom/lib/` |
| SharePoint ingest/sync (this package) | `packages/ahg-sharepoint/` |
| SharePoint AtoM ingest plugin | `/usr/share/nginx/archive/atom-ahg-plugins/ahgSharePointPlugin/` |

Note: the F3 federated-search code in `/opt/.../F3/heratio/` is the **upgraded**
dispatcher (it adds the `PeerConnector` dispatch path to `FederatedSearchService`).
The copy of `FederatedSearchService.php` currently deployed in the live
`packages/ahg-federation/` is the **pre-F3** build and has **no** connector
dispatch and **no** reference to any SharePoint connector. So today there is no
live SharePoint federated-search path to break — the relocation is forward-only.

## Classification — what is SharePoint-specific vs general

### Moves into `ahg-sharepoint` (SharePoint-specific)

| File (F3 / ahg-federation) | Disposition |
| --- | --- |
| `F3/heratio/src/Connectors/SharePointGraphConnector.php` (ns `AhgFederation\Connectors`) | **Moves** here, re-namespaced to `AhgSharePoint\Federation`. Already copied as inert scaffold in step 1. |
| The `'sharepoint_graph_search' => SharePointGraphConnector::class` arm of `FederatedSearchService::connectorClassFor()` | **Moves** out of the hard-coded match in `ahg-federation` and is contributed by `ahg-sharepoint` via a registry hook (see "Cutover", step C). |
| SharePoint-type config fields in `ahg-federation/resources/views/edit-peer.blade.php` (tenant_id / default_site_ids / default_drive_ids / max_results_per_query for `peer_type=sharepoint_graph_search`) | **The SharePoint sub-fields** become a partial owned by `ahg-sharepoint`; the generic peer form stays in `ahg-federation`. `edit-peer.blade.php` is **locked** — user unlock required before this final edit. |
| Microsoft Graph dependency (`AhgSharePoint\Services\GraphClientService`) | Already lives in this package. No move needed; the relocated connector now sits next to its dependency instead of reaching across packages. |

### Stays in `ahg-federation` (general federation)

| File | Why it stays |
| --- | --- |
| `src/Connectors/PeerConnector.php` (interface) | General contract for every peer type. |
| `src/Connectors/PeerSearchResult.php` (value object) | General result shape. |
| `src/Connectors/OaiPmhConnector.php` | OAI-PMH, not SharePoint. |
| `src/Connectors/AtomElasticsearchConnector.php` | Local ES peer, not SharePoint. |
| `src/Services/FederatedSearchService.php` | General dispatcher. Only its `connectorClassFor()` registry needs to become extensible (see cutover C). |
| `src/Controllers/FederationController.php` | General federation admin/search controller. |
| `routes/web.php` (`/federation/*`) | General federation routes. SharePoint search rides `/federation/search` with `peer_type=sharepoint_graph_search`; **no new route is added** by this package. |
| `resources/views/edit-peer.blade.php` (generic peer fields) | General peer form chrome. |
| Union catalogue, loans, join-network, Europeana EDM, harvest | Entirely unrelated to SharePoint. |

## What step 1 added (this scaffold — all inert)

Under `packages/ahg-sharepoint/src/Federation/`:

- `PeerConnector.php` — local copy of the general contract (new namespace
  `AhgSharePoint\Federation`), so the connector below is self-contained and does
  not `use` anything from `ahg-federation`.
- `PeerSearchResult.php` — local copy of the general value object.
- `SharePointGraphConnector.php` — the relocated connector, re-namespaced to
  `AhgSharePoint\Federation\SharePointGraphConnector`, depending on this
  package's own `GraphClientService`.

**These are dormant.** They are not registered in `connectorClassFor()` of any
live dispatcher, they register no routes, and they are not instantiated at boot.
Their FQCNs differ from the upstream F3 FQCNs (`AhgFederation\Connectors\…`), so
there is no class collision and no double registration. PSR-4 autoload already
covers them (`"AhgSharePoint\\": "src/"`), so no composer change is required for
the scaffold.

## Contract placement decision (resolve at cutover)

Two valid end states — pick ONE; never run both:

1. **Single shared contract (recommended).** Delete the local
   `AhgSharePoint\Federation\PeerConnector` + `PeerSearchResult` copies and have
   `SharePointGraphConnector` implement `AhgFederation\Connectors\PeerConnector`
   and return `AhgFederation\Connectors\PeerSearchResult`. Add
   `"ahg/federation": "@dev"` to this package's `composer.json` require block.
   One interface in the whole system; no duplication.
2. **Owned contract copy.** Keep the local copies and have the dispatcher accept
   any object implementing either interface (structural, not nominal). More
   indirection; avoid unless a hard dependency cycle forces it.

The scaffold ships option 2's files so it is self-contained today, but the
cutover SHOULD collapse to option 1.

## Cutover steps (do NOT run as part of step 1)

Pre-req: confirm with the user that F3 federated search is being promoted from
the `/opt/.../F3/` reference into the live tree, because that is the first time
any SharePoint connector becomes live.

- **A. Unlock the locked F3 files** (user runs these — never the agent):
  ```bash
  cd /usr/share/nginx/heratio
  ./bin/unlock packages/ahg-federation/src/Services/FederatedSearchService.php \
               packages/ahg-federation/src/Controllers/FederationController.php \
               packages/ahg-federation/resources/views/edit-peer.blade.php \
               packages/ahg-federation/src/Connectors/
  ```
  (`packages/ahg-federation/src/Connectors/` is locked even though it does not
  yet exist on disk — it is a forward-declared lock for when the F3 connectors
  land.)

- **B. Promote the F3 dispatcher + general connectors into the live tree.**
  Copy from `/opt/.../F3/heratio/src/` into `packages/ahg-federation/src/`:
  - `Connectors/PeerConnector.php`, `Connectors/PeerSearchResult.php`,
    `Connectors/OaiPmhConnector.php`, `Connectors/AtomElasticsearchConnector.php`
    (general — these STAY in ahg-federation)
  - `Services/FederatedSearchService.php` (the connector-dispatch version)
  Do **not** copy `Connectors/SharePointGraphConnector.php` into ahg-federation —
  it now lives in this package.

- **C. Make `connectorClassFor()` extensible** instead of hard-coding the
  SharePoint arm. Replace the `'sharepoint_graph_search' => \AhgFederation\…`
  literal with a lookup over a registry that other packages contribute to (a
  `config('federation.connectors')` map). Then `ahg-sharepoint`'s service
  provider registers:
  ```php
  'sharepoint_graph_search' => \AhgSharePoint\Federation\SharePointGraphConnector::class
  ```
  Result: the SharePoint FQCN never appears in `ahg-federation` source.

  **Status (step 2):** the `ahg-sharepoint` HALF is **DONE** — its provider now
  publishes the registry entry into `config('federation.connectors')`
  (`AhgSharePointServiceProvider::register()`). The `ahg-federation` HALF — making
  the dispatcher *read* that registry — is the one genuinely-unavoidable
  locked + NO-PUSH change, and is therefore written as an **operator-only** patch
  in [`cutover.patch`](cutover.patch) for application to the canonical
  `/opt/ahg-sp-integration/F3/heratio/src/Services/FederatedSearchService.php`.
  It is NOT applied in the repo because that file is both locked
  (`.locked-paths`) and NO-PUSH (`memory/f3_federation_sharepoint_local.md`); the
  operator applies it upstream, and it rides into the live tree at step B.

- **D. Resolve the contract decision** (see above). If option 1, delete the two
  local contract copies in this package and add the `ahg/federation` require.

  **Status (step 2):** deferred to the operator, by necessity. Option 1 requires
  the ahg-federation F3 `PeerConnector` interface to exist in the live tree,
  which only happens at step B (operator-run promotion). The package therefore
  still ships option 2's local contract copies today so its OWN self-contained
  `/sharepoint/federated-search` surface works now; `cutover.patch` documents the
  exact two-line switch to option 1 once step B lands.

- **E. Move the SharePoint config sub-fields** out of the locked
  `edit-peer.blade.php` into a `ahg-sharepoint`-owned partial included by the
  generic peer form (e.g. via a Blade `@stack`/`@include` hook or a
  `View::composer`-injected partial, the unlocked-caller pattern). Keep the
  generic peer chrome in `ahg-federation`.

  **Status (step 2):** the unlocked-caller injection is **DONE** — the locked
  `edit-peer.blade.php` was NOT edited. `AhgSharePointServiceProvider::boot()`
  registers a `View::composer('ahg-federation::edit-peer', …)` that injects
  `$sharepointConfigured`, `$sharepointTenantOptions`, and
  `$sharepointDefaultTenant` whenever that view renders. ahg-federation's own
  markup can surface the SharePoint tenant picker for
  `peer_type=sharepoint_graph_search` from those variables without this package
  ever touching the locked blade. The composer degrades cleanly (try/catch) and
  is inert until that view exists/renders.

- **F. Re-lock** whatever was unlocked, per the locked-paths workflow (lock
  auto-rearms after a successful `./bin/release`; or `./bin/lock --all`).

- **G. AtoM parity.** Mirror the same connector relocation in
  `/usr/share/nginx/archive/atom-ahg-plugins/` so the AtoM-AHG fork does not
  drift (per the always-fix-both-codebases rule). Canonical AtoM source:
  `/opt/ahg-sp-integration/F3/atom/lib/Connectors/`.

- **H. Verify.** `php -l` the moved files; confirm `/federation/search` resolves
  unchanged; confirm a `sharepoint_graph_search` peer dispatches to
  `AhgSharePoint\Federation\SharePointGraphConnector`; run the federation tests.

## Step 2 — DONE vs remaining

### DONE in this package (pushable, all inside `packages/ahg-sharepoint/`)

- **Self-contained connector wiring.** `AhgSharePointServiceProvider::register()`
  binds `SharePointGraphConnector`, `SharePointFederationConfig`, and
  `SharePointFederationRunner` as singletons. The connector reads tenant +
  credentials from this package's OWN M365 store (`GraphClientService` /
  `SharePointTenantRepository` / `sharepoint_tenant`), never from an
  ahg-federation peer row.
- **Connector-discovery registry.** The provider publishes
  `config('federation.connectors')['sharepoint_graph_search'] =
  \AhgSharePoint\Federation\SharePointGraphConnector::class`, so a future
  extensible dispatcher resolves the SharePoint connector without the FQCN
  appearing in ahg-federation source. Harmless metadata until the dispatcher
  reads it.
- **Working package-owned search surface.** `SharePointFederationRunner` +
  `SharePointFederatedSearchController` + routes
  `GET /sharepoint/federated-search` (rendered) and
  `GET /sharepoint/federated-search.json` (JSON) + view
  `ahg-sharepoint::federated-search`. Runs a real Graph search from a tenant in
  the package store. Distinct prefix from `/federation/*` — no route collision.
- **Degrade-when-unconfigured.** `SharePointFederationConfig::isConfigured()`
  guards everything and never throws (missing table / unreachable DB / no tenant
  all resolve to "not configured"). The runner returns
  `SharePointFederationRunResult::notConfigured()` and the UI/JSON render an
  honest "SharePoint not configured" state with HTTP 200 — never a 500.
- **Locked-blade config injection without editing it.** A
  `View::composer('ahg-federation::edit-peer', …)` in `boot()` injects SharePoint
  tenant options into the locked peer-edit view. The locked/NO-PUSH
  `edit-peer.blade.php` is untouched.

### Remaining — OPERATOR-ONLY (locked + NO-PUSH; cannot be pushed from here)

- **The `/opt` dispatcher patch (step C, ahg-federation half).** Apply
  [`cutover.patch`](cutover.patch) to the canonical
  `/opt/ahg-sp-integration/F3/heratio/src/Services/FederatedSearchService.php` to
  make `connectorClassFor()` registry-first and drop the SharePoint literal.
- **Promote F3 into the live tree (step B)** + **collapse to the single shared
  contract (step D, option 1)** + **re-lock (step F)** — all operator-run, in
  order, per the locked-paths workflow.
- **AtoM-AHG parity mirror (step G)** under
  `/usr/share/nginx/archive/atom-ahg-plugins/` from
  `/opt/ahg-sp-integration/F3/atom/lib/Connectors/`.

## Hard rules observed (steps 1 + 2)

- No edit/move/delete of any `.locked-paths` entry (the four F3 files in
  `ahg-federation` are untouched and byte-identical).
- No edit of any file under `/opt/ahg-sp-integration/F3/` (the dispatcher change
  is delivered as `cutover.patch` for the operator to apply upstream).
- No `install.sql` (or any SQL) run against the live database.
- New routes are added ONLY under this package's own `/sharepoint/*` prefix;
  nothing competes with the live `/federation/*` routes.
- `./bin/check-locked` stays green; `git status` shows only new/changed files
  under `packages/ahg-sharepoint/` and `docs/`.

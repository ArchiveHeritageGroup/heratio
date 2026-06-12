# SharePoint federated search moving into ahg-sharepoint (issue #1221)

**Summary:** Heratio's SharePoint Graph federated-search connector is being
relocated out of the general `ahg-federation` package and into the dedicated
`ahg-sharepoint` package, so the general federation engine carries no Microsoft
365 coupling. Issue #1221 is the relocation. Step 1 was a non-destructive
scaffold (inert connector + cutover plan). **Step 2 (current)** makes the
connector self-contained and WORKING inside `ahg-sharepoint`: the package now has
its own connector wiring, a connector-discovery registry, a package-owned search
route/controller/runner that draws config from its own M365 tenant store, a
clean degrade-when-unconfigured path, and a `View::composer` that injects
SharePoint config options into the locked federation peer-edit view WITHOUT
editing it. The single remaining change — making the `ahg-federation` dispatcher
read the registry — is locked AND NO-PUSH, so it is delivered as an operator-only
patch (`packages/ahg-sharepoint/cutover.patch`) for the canonical `/opt` source.

## Packages and their roles

- `ahg-federation` is the **general** federation package: OAI-PMH harvesting,
  union catalogue, inter-institution loans, join-network, Europeana EDM export,
  and the cross-peer federated-search dispatcher. It is jurisdiction-neutral and
  protocol-general, suitable for any GLAM federation in any market.
- `ahg-sharepoint` is the **Microsoft 365 SharePoint** integration package:
  tenant config, drive registration, delta sync, Graph change-notification
  webhooks, auto-ingest, and (per issue #1221) the SharePoint Graph search
  connector for federated search.

## Why split

The federated-search engine is general, but exactly one peer type,
`sharepoint_graph_search`, depends on Microsoft Graph (`GraphClientService`,
`sharepoint_tenant` config). Keeping that connector inside `ahg-federation`
forces a Microsoft 365 dependency onto every federation install. Moving it into
`ahg-sharepoint` means an install that does not use SharePoint can drop the
whole package and federation still works for every other peer type.

## What is SharePoint-specific (moves) vs general (stays)

Moves into `ahg-sharepoint`:

- `SharePointGraphConnector` (the Microsoft Graph search connector).
- The `sharepoint_graph_search` arm of the dispatcher's connector registry
  (`connectorClassFor()`), which becomes an extension hook contributed by
  `ahg-sharepoint` rather than a hard-coded literal in `ahg-federation`.
- The SharePoint-only peer-config fields (tenant id, default site ids, default
  drive ids, max results) currently inlined in the locked federation
  `edit-peer` view; these become a partial owned by `ahg-sharepoint`.

Stays in `ahg-federation`:

- The `PeerConnector` interface and `PeerSearchResult` value object (general
  contract).
- The `OaiPmhConnector` and `AtomElasticsearchConnector` (not SharePoint).
- The `FederatedSearchService` dispatcher, `FederationController`, and the
  `/federation/*` routes.
- Union catalogue, loans, join-network, Europeana EDM, harvest.

## Routing fact

SharePoint federated search does not get its own route. It rides the general
`/federation/search` page with a peer of type `sharepoint_graph_search`. There
is therefore no new route and no risk of a duplicate or competing route when the
connector is relocated. The legacy stub `GET /sharepoint/federated-search` (HTTP
503) was already removed in the F3 design so the dispatcher is the only entry
point.

## Deployment status nuance

The upgraded federation dispatcher that adds connector dispatch (the F3 build)
is held in the canonical upstream reference and has not yet been promoted into
the live `ahg-federation` package. The copy of `FederatedSearchService`
currently deployed has no connector dispatch and references no SharePoint
connector, so today there is no live SharePoint federated-search code path. The
relocation is forward-only and cannot break a live path.

## Step 1 scaffold contents

Added under `packages/ahg-sharepoint/src/Federation/` (all inert, registered
nowhere, no routes, not constructed at boot):

- `PeerConnector.php` (local copy of the general contract, new namespace).
- `PeerSearchResult.php` (local copy of the general value object).
- `SharePointGraphConnector.php` (the relocated connector, re-namespaced to
  `AhgSharePoint\Federation`, depending on this package's own Graph client).

PSR-4 autoload (`AhgSharePoint\` to `src/`) already covers these, so no composer
change was needed for the scaffold.

## Why the scaffold cannot conflict with live behaviour

- It is not registered in the dispatcher's connector registry, so the dispatcher
  never resolves it.
- Its fully qualified class name (`AhgSharePoint\Federation\…`) differs from the
  upstream connector name (`AhgFederation\Connectors\…`), so there is no class
  collision and no double registration.
- It declares no routes and is not instantiated at boot.

## Cutover (operator-run, not part of step 1)

The full plan is in `packages/ahg-sharepoint/MIGRATION.md`. Key points:

- The four SharePoint-specific files in `ahg-federation` are locked
  (no-push). An operator must unlock them with `./bin/unlock` before the final
  move; the agent never runs unlock.
- The cutover promotes the general F3 dispatcher and the non-SharePoint
  connectors into `ahg-federation`, makes the connector registry extensible,
  registers the SharePoint connector from `ahg-sharepoint`, resolves whether the
  contract is shared or copied, and moves the SharePoint config fields out of the
  locked peer view via an unlocked-caller injection pattern.
- AtoM parity: the same relocation is mirrored in the AtoM-AHG SharePoint plugin
  so the two codebases do not drift.

## Step 2 — self-contained, working connector

Step 2 made the relocated connector usable on its own, all within
`packages/ahg-sharepoint/`:

- **Provider wiring.** `AhgSharePointServiceProvider::register()` binds
  `SharePointGraphConnector`, `SharePointFederationConfig`, and
  `SharePointFederationRunner` as singletons. The connector takes tenant and
  credentials from this package's own `GraphClientService` /
  `SharePointTenantRepository` (the `sharepoint_tenant` table), never from an
  ahg-federation peer row.
- **Connector-discovery registry.** The provider publishes
  `config('federation.connectors')['sharepoint_graph_search']` =
  `\AhgSharePoint\Federation\SharePointGraphConnector::class`. A future
  extensible dispatcher resolves the connector from this map, so the SharePoint
  FQCN never has to appear in `ahg-federation` source. Until the dispatcher reads
  it, the entry is harmless metadata.
- **Package-owned search surface.** `SharePointFederationRunner` runs a real
  Graph `/search/query` from a tenant in the package store;
  `SharePointFederatedSearchController` exposes it at
  `GET /sharepoint/federated-search` (rendered admin page) and
  `GET /sharepoint/federated-search.json` (JSON for the union catalogue / AJAX).
  This is under the package's own `/sharepoint/*` prefix, so it does NOT collide
  with the general `/federation/*` routes.
- **Degrade-when-unconfigured.** `SharePointFederationConfig::isConfigured()`
  guards every entry point and never throws: a missing `sharepoint_tenant` table,
  an unreachable DB, or zero tenants all resolve to "not configured". The runner
  returns `SharePointFederationRunResult::notConfigured()`, and the UI/JSON render
  an honest "SharePoint not configured" panel with HTTP 200 — never a 500. This
  matters because an instance may have no tenant.
- **Locked-blade config injection.** A
  `View::composer('ahg-federation::edit-peer', …)` injects
  `$sharepointConfigured`, `$sharepointTenantOptions`, and
  `$sharepointDefaultTenant` into the locked peer-edit view whenever it renders.
  The locked/NO-PUSH `edit-peer.blade.php` is untouched; ahg-federation's own
  markup can read those variables to show the SharePoint tenant picker for
  `peer_type=sharepoint_graph_search`. The composer is wrapped in try/catch so it
  can never break the peer form, and stays inert until that view exists.

### Why the dispatcher change ships as an operator-only patch

The one genuinely-unavoidable upstream edit is making the `ahg-federation`
`FederatedSearchService::connectorClassFor()` registry-first. That file is BOTH
locked (`.locked-paths`) and NO-PUSH (the SharePoint local-only policy), and its
canonical source of truth is `/opt/ahg-sp-integration/F3/`, not the repo. So the
change is written as `packages/ahg-sharepoint/cutover.patch` — a unified diff the
operator applies to the canonical `/opt` source, where it rides into the live
tree when F3 is promoted. The agent never edits the locked file and never touches
`/opt`.

## Hard constraints honoured (steps 1 + 2)

No locked file edited, moved, renamed, or deleted. No upstream F3 reference file
touched (the dispatcher change is a patch for the operator, not an edit). No SQL
run against any database. New routes only under the package's own `/sharepoint/*`
prefix — no competing route. The locked-path check stays green; the working tree
shows only new/changed files under the SharePoint package and docs.

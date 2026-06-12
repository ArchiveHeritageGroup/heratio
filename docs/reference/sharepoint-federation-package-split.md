# SharePoint federated search moving into ahg-sharepoint (issue #1221)

**Summary:** Heratio's SharePoint Graph federated-search connector is being
relocated out of the general `ahg-federation` package and into the dedicated
`ahg-sharepoint` package, so the general federation engine carries no Microsoft
365 coupling. Issue #1221 is the relocation. Step 1 (this change) is a
non-destructive scaffold: it adds an inert connector to `ahg-sharepoint` and a
written cutover plan, and moves nothing yet.

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

## Hard constraints honoured in step 1

No locked file edited, moved, renamed, or deleted. No upstream F3 reference file
touched. No SQL run against any database. No new or competing route. The
locked-path check stays green; the working tree shows only new files under the
SharePoint package and docs.

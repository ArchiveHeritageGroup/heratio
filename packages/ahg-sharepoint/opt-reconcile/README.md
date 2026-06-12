# /opt + AtoM reconciliation after the #1221 SharePoint cutover

The #1221 cutover (Heratio v1.142.72) made the **repo's `ahg-federation` canonical**:

- `FederatedSearchService::connectorClassFor()` is **registry-first** - it reads `config('federation.connectors')[$peerType]` before its own general connectors, so the SharePoint connector class never appears in `ahg-federation` source.
- The SharePoint connector lives in **`packages/ahg-sharepoint`** (`AhgSharePoint\Federation\SharePointGraphConnector`), which publishes itself into `config('federation.connectors')` from its service provider.

Two external trees still carry the **old, pre-cutover shape** and must be reconciled so they do not diverge or re-introduce the SharePoint FQCN into the federation layer.

---

## 1. `/opt/ahg-sp-integration/F3/heratio` - READY (verified)

Only one file differs from the repo (`connectorClassFor`), plus the now-relocated connector to remove. Run:

```bash
sudo bash packages/ahg-sharepoint/opt-reconcile/reconcile-opt-heratio.sh
```

It applies `heratio-federatedsearchservice-registry-first.patch` (verified to apply cleanly) and removes `/opt/.../heratio/src/Connectors/SharePointGraphConnector.php`. All other promoted files (`FederationController`, `edit-peer.blade`, the general connectors) already match the repo byte-for-byte, so nothing else changes.

After this, `/opt` heratio == repo `ahg-federation`.

---

## 2. AtoM-AHG mirror - SPEC ONLY (needs an archive-repo pass, per fix-both-codebases)

This is the **same #1221 problem in the Symfony/AtoM codebase**, not a copy-paste patch, so it is documented rather than auto-patched (it cannot be smoke-tested from the Laravel side).

**Where it lives:** the live archive `atom-ahg-plugins/`:
- `ahgFederationPlugin/lib/FederatedSearchService.php` - hardcodes `private const CONNECTOR_TYPES` mapping both `atom_local` and `sharepoint_graph_search` to `AhgFederation\Connectors\...`, used by `partitionPeersByDispatch()` and `runConnector()`.
- `ahgFederationPlugin/lib/Connectors/SharePointGraphConnector.php` - the SharePoint connector, currently in the WRONG plugin.
- `ahgSharePointPlugin/` - already exists; the correct home for the SharePoint connector.

**The change (mirror of the heratio cutover):**

1. In `ahgFederationPlugin/lib/FederatedSearchService.php`, replace the hardcoded `CONNECTOR_TYPES` const with a registry-merge. Symfony 1.4 has no Laravel `config()`, so use `sfConfig`:
   ```php
   private static function connectorTypes(): array
   {
       // Registry-first (#1221): plugins (e.g. ahgSharePointPlugin) contribute
       // their peer_type => connector-class via sfConfig, so the SharePoint FQCN
       // never appears in ahgFederationPlugin source.
       return array_merge(
           ['atom_local' => 'AhgFederation\\Connectors\\AtomElasticsearchConnector'],
           (array) sfConfig::get('app_ahg_federation_connectors', [])
       );
   }
   ```
   and change the two usages (`partitionPeersByDispatch()`, `runConnector()`) from `self::CONNECTOR_TYPES[$type]` to `self::connectorTypes()[$type]`.

2. **Move** `ahgFederationPlugin/lib/Connectors/SharePointGraphConnector.php` to `ahgSharePointPlugin/lib/Connectors/` (keep its `AhgFederation\Connectors` FQCN, or re-namespace to an `ahgSharePointPlugin` namespace and update the registered class string to match).

3. In `ahgSharePointPlugin/config/app.yml` (or the plugin config), register the mapping:
   ```yaml
   all:
     ahg_federation_connectors:
       sharepoint_graph_search: 'AhgFederation\Connectors\SharePointGraphConnector'
   ```

4. Verify: federated search still dispatches `atom_local` (general) and, with the plugin enabled, `sharepoint_graph_search`; `ahgFederationPlugin` source no longer names the SharePoint connector.

This belongs in the **archive repo** (`atom-ahg-plugins`) and should be released there with `atom-ahg-plugins/bin/release` + `git push` (per the fix-both-codebases rule). It is intentionally left as a separate, reviewed task.

---

## Tracking

Heratio `#1221` stays OPEN until both reconciliations above are applied. The repo cutover itself is done (v1.142.72).

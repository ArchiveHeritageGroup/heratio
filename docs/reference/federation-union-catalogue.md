# Federated GLAM network - opt-in union catalogue (ahg-federation, #1203 first slice)

The union catalogue is the first slice of the federated GLAM network in
`packages/ahg-federation`. It lets multiple institutions publish their
discovery metadata into one portable index and search across all of them
from a single page, respecting each institution's opt-in sharing settings.

## What it is

- A member registry of participating institutions.
- A per-institution opt-in sharing switch (default OFF).
- A portable union index (a DB table, not a second Elasticsearch cluster).
- A console command that publishes this institution's opt-in, published
  records into the index.
- A public cross-member search surface (HTML + CORS-open JSON).

## Tables (database/install_union.sql, auto-installed on boot)

- `federation_member` - id, name, base_url, contact, share_scope, is_self,
  is_enabled, timestamps. The local institution is the self-member
  (is_self=1). Opt-in default is_enabled=0.
- `federation_share_setting` - single row (id auto). share_enabled (default
  0 = OFF), published_only (default 1), min_level_id (optional level gate),
  updated_at. Read lazily with a safe OFF default object when the row or
  table is missing.
- `federation_union_record` - member_id, record_ref, title, level, dates,
  repository, url, indexed_at. Unique key (member_id, record_ref) so publish
  upserts idempotently.

Auto-install: `AhgUnionCatalogueServiceProvider::boot()` runs a single outer
try/catch around `Schema::hasTable('federation_union_record')` + the install
SQL, so CI without a DB stays green.

## Routes

Public (registered in `register()` via `callAfterResolving('router')` so they
beat the locked `/{slug}` catch-all in ahg-information-object-manage by
registration order - the catch-all's exclusion regex is locked and cannot be
edited):

- `GET /union-catalogue` -> UnionCatalogueController@index (HTML search)
- `GET /union-catalogue.json` -> UnionCatalogueController@json (CORS-open)

Admin (auth + admin), prefix `federation/members`:

- `GET /` index, `GET /add`, `GET /{id}/edit`, `POST /save`,
  `POST /{id}/delete`, `POST /share`, `POST /publish` -> UnionMemberController.

## The opt-in gate (enforced in two places)

1. Publish (`UnionCatalogueService::publish()` / `ahg:federation-publish`):
   returns immediately with reason `sharing_disabled` unless
   `share_enabled=1`; returns `no_self_member` if no is_self member exists.
   The published-record filter is the standard gate - `status.type_id=158`
   AND `status.status_id=160`, `io.id > 1`. An optional `min_level_id`
   skips records below the configured level. Idempotent upsert by
   (self member_id, record_ref). Streams in id batches (default 500);
   never loads the whole catalogue. Writes DB only - www-data safe.
2. Search (`UnionCatalogueService::search()`): only queries
   `federation_union_record` rows whose member_id is in the set of members
   with `is_enabled=1`. A member that has not opted in contributes nothing.

## Catch-all safety

`/union-catalogue` is a single segment, which the locked `/{slug}` show
route would otherwise swallow. We register it in the provider's `register()`
through `callAfterResolving('router')`, mirroring the AppServiceProvider
pattern for `/z3950` and `/sru`. Routes defined earlier win the match, so
ours resolve before the catch-all is added in the IO package's `boot()`.

## Resilience

Every service method is `Schema::hasTable`-guarded and try/catch wrapped.
Missing tables degrade to a dignified empty-state (members=[], a safe OFF
share-setting object, total=0 search) - never a 500. Reads are bounded
(members capped at 500, search paginated at 20/page, max 100/page).

## Locked F3 files - NOT touched

The four locked + NO-PUSH F3 SharePoint files
(`src/Connectors/`, `src/Services/FederatedSearchService.php`,
`src/Controllers/FederationController.php`,
`resources/views/edit-peer.blade.php`) were read for patterns only. All new
code lives in new files: `UnionCatalogueService`, `UnionPublishCommand`,
`UnionCatalogueController`, `UnionMemberController`,
`AhgUnionCatalogueServiceProvider`, `union/*.blade.php`, and
`database/install_union.sql`, with the new provider added to composer.json.

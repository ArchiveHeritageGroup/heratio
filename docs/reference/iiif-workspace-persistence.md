# IIIF / Mirador workspace persistence (issue #699)

Two-layer persistence on top of the Heratio Mirador 4 bundle so a researcher's open canvases, window positions, layouts, view modes, zoom, and annotation panels survive a page reload - and optionally a device switch.

## Layers

### Layer 1: localStorage (anonymous + authenticated)

Automatic. No user action. The Mirador build (`tools/mirador-build/src/heratio-workspace-persistence.js`) subscribes to the Mirador redux `store` after `Mirador.viewer()` returns, debounces writes (1.5 s after the last mutation), and persists `store.getState()` to `localStorage`. The key is `heratio-mirador-workspace:<scope>` where `<scope>` defaults to the page pathname (e.g. `/iiif-viewer/abc-123`) so different viewer hosts on the same site cannot clobber each other.

On viewer mount, the persistence layer reads the saved state and dispatches `actions.importMiradorState(state)` to restore the workspace. The auto-restore call defers one tick so Mirador's initial render is complete before the state-replacement action lands.

### Layer 2: per-user DB-backed (authenticated only)

Optional, opt-in via the workspace dropdown. Storage is the `ahg_iiif_workspace` table:

| column | type | purpose |
|---|---|---|
| `id` | BIGINT PK | row id |
| `user_id` | BIGINT | `Auth::id()` of the owner; rows are tightly scoped to this user |
| `name` | VARCHAR(255) | user-supplied label, e.g. `Research session 2026-05-25` |
| `config_json` | JSON | `Mirador.exportConfig()` output / `store.getState()` snapshot |
| `is_default` | TINYINT(1) | when 1, auto-load on next viewer open (mutually exclusive per user) |
| `created_at`, `updated_at` | TIMESTAMP | standard timestamps |

Indexes: `(user_id)`, `(user_id, is_default)`.

The schema lives in `packages/ahg-iiif-collection/database/install-workspace.sql` and is auto-seeded by `AhgIiifCollectionServiceProvider::boot()` on first boot (probe + install wrapped in one outer try/catch per `reference_ci_schema_hastable.md`).

## REST API

All endpoints are session-auth gated (`Auth::id()` must be non-null) and live under `/api/iiif/workspace` in `packages/ahg-iiif-collection/routes/web.php`:

| method | path | purpose |
|---|---|---|
| GET    | `/api/iiif/workspace`            | list the current user's saved workspaces (no `config_json`, listing only) |
| POST   | `/api/iiif/workspace`            | create one (body: `name`, `config_json`, optional `is_default`) |
| GET    | `/api/iiif/workspace/{id}`       | fetch a specific workspace including decoded `config_json` |
| PUT    | `/api/iiif/workspace/{id}`       | rename and/or overwrite `config_json` |
| DELETE | `/api/iiif/workspace/{id}`       | delete |
| POST   | `/api/iiif/workspace/{id}/load`  | flag as the default-on-load workspace (clears the flag on every other row in one txn) |

`WorkspaceController` lives at `packages/ahg-iiif-collection/src/Controllers/WorkspaceController.php`; `WorkspaceService` (DB facade, no Eloquent) at `packages/ahg-iiif-collection/src/Services/WorkspaceService.php`.

## Client API

The Mirador bundle exposes `window.HeratioWorkspaces` once a viewer has been constructed. Methods:

```js
HeratioWorkspaces.list()                            // -> Array of summary rows
HeratioWorkspaces.saveRemote(name, { isDefault })   // POST current state to /api/iiif/workspace
HeratioWorkspaces.overwriteRemote(id, { name })     // PUT current state into an existing row
HeratioWorkspaces.loadRemote(id)                    // fetch + importMiradorState
HeratioWorkspaces.setDefault(id)                    // POST .../load
HeratioWorkspaces.deleteRemote(id)                  // DELETE
HeratioWorkspaces.saveLocal()                       // force a localStorage write
HeratioWorkspaces.clearLocal()                      // wipe the localStorage entry
```

The viewer factory installs one persistence handler per scope (`config.id` of the Mirador root element, or the page pathname), so multiple viewers on a single page each carry their own auto-save channel.

## Admin page

`/iiif/workspaces` (route name `iiif.workspaces.index`) is a thin Bootstrap 5 table letting the user rename, delete, or set-default any of their saved workspaces. The table view at `packages/ahg-iiif-collection/resources/views/workspaces/index.blade.php` reloads on each successful action so stale state cannot pile up.

## Build / deploy

The persistence module is bundled into `public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js` by:

```
cd tools/mirador-build
npm run build      # produces dist/mirador.min.js
npm run deploy     # build + cp to public/vendor/...
```

`tools/mirador-build/src/index.js` imports the module and wires `installHeratioWorkspacePersistence(instance, { scope })` after the wrapped `Mirador.viewer()` call.

## Test plan

1. Log in as any user, open an IIIF viewer with a IIIF manifest, move windows around, change layout to Mosaic, hit reload - workspace state should restore from localStorage.
2. On the same viewer, run `await HeratioWorkspaces.saveRemote('Test session')` in the console - the row appears at `/iiif/workspaces`.
3. From that admin page, click "Set default" on the row - reload the viewer and the saved layout should restore from the DB even after `HeratioWorkspaces.clearLocal()`.
4. Delete the workspace; reload the viewer; auto-restore should fall back to localStorage.
5. Log out, reload the viewer; localStorage still restores the last workspace but the REST endpoints all 401.

## Notes

- `config_json` is stored verbatim; we do not lint or rewrite Mirador's emitted state. If a future Mirador upgrade changes the state shape, old rows will gracefully fail to restore (try/catch around `importMiradorState`) and the localStorage layer takes over.
- The persistence layer is best-effort throughout. Any thrown error in the redux subscription, the localStorage write, or the API call is swallowed so it can never block the viewer from rendering.
- Multi-viewer pages (record show + compare) keep separate scopes by setting `config.id` differently for each viewer.

## Known limitations / follow-ups

- **In-viewer toolbar dropdown is not yet a Mirador React plugin.** The brief calls for a "Save current / Save as new / Manage" dropdown rendered inside the Mirador toolbar. The current pass ships the persistence engine + the `window.HeratioWorkspaces` JS surface + the `/iiif/workspaces` admin page; a future iteration should add a `companionWindow` or `workspaceMenu` MUI plugin that calls those methods. The hand-off point is `window.HeratioWorkspaces.{list, saveRemote, loadRemote, setDefault, deleteRemote}`.
- **No cross-tab sync.** Two tabs editing the same scope race on the localStorage key; last write wins. A `storage` event listener could push remote changes between tabs.
- **No quota guard.** Very large workspaces could exceed the 5 MB localStorage budget. We swallow the QuotaExceededError silently today.

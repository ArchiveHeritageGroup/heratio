# ahg-version-control

Version history with diff and restore for `information_object` and `actor` entities. Mirrors the AHG version-snapshot pattern used by reports, landing pages, and heritage contributions.

## Purpose

- Per-entity snapshot table (one row per save) capturing the full i18n payload
- Diff between any two versions with field-by-field deltas
- One-click restore of a prior version (admin / ACL-gated, audit trail)
- Backfill command to seed snapshots for legacy data
- Clearance check so classified records cannot be restored by a user below the classification

## Install

Auto-discovered. The ServiceProvider:

1. Boots routes + views under the `web` + `auth` middleware group
2. Registers Eloquent observers (`InformationObjectSnapshotObserver`, `ActorSnapshotObserver`) that write a snapshot on every save
3. Registers four Artisan commands (see below)
4. Auto-installs `database/install.sql` (and any `database/migrations/*`) on first boot if the snapshot table is missing

## Routes

All under `/version-control` (auth required):

- `GET /version-control/{entity}/{id}` - list versions
- `GET /version-control/{entity}/{id}/{number}` - show a single version
- `GET /version-control/{entity}/{id}/diff/{v1}/{v2}` - diff two versions
- `POST /version-control/{entity}/{id}/{number}/restore` - restore

Where `{entity}` is one of `information_object` or `actor`.

## Key classes

| Class | Role |
|---|---|
| `Services\SnapshotBuilder` | Builds a snapshot row from a fresh Eloquent payload |
| `Services\VersionWriter` | Persists snapshots (called by the observers) |
| `Services\DiffComputer` | Field-by-field deltas between two snapshots |
| `Services\RestoreService` | Applies a snapshot back to the live entity (with audit) |
| `Services\ClearanceCheck` / `AclCheck` | Authorisation gates |
| `Observers\InformationObjectSnapshotObserver` | Hooks into `saved` events |
| `Observers\ActorSnapshotObserver` | Same, for actors |
| `Http\Middleware\VersionLinkInjector` | Adds the "Versions" tab into entity show pages |

## Commands

```bash
php artisan version-control:backfill   # snapshot every existing IO + actor row
php artisan version-control:capture    # ad-hoc capture for a single record
php artisan version-control:diff       # CLI diff between two version numbers
php artisan version-control:prune      # retention sweep
php artisan version-control:snapshot-smoke   # smoke test against a fixture
```

## Notes

- Snapshots are stored as serialised payloads keyed by `(entity, entity_id, number)`; the writer always increments `number` atomically.
- The restore path runs the snapshot through the same validation pipeline as a normal save - it cannot resurrect data that violates current business rules.

# Browse default sort direction (global setting)

**Summary:** Operators can set the default sort *direction* (ascending / descending) for the GLAM item browse (`/glam/browse`), separately for authenticated and anonymous users. Set it on **Admin → Settings → Global** (`/admin/settings/global`). It complements the existing default sort *field* setting (#80).

## Settings keys

| Key | Audience | Default | Values |
|---|---|---|---|
| `sort_browser_direction_user` | Authenticated users | `desc` | `asc`, `desc` |
| `sort_browser_direction_anonymous` | Anonymous users | `desc` | `asc`, `desc` |

Stored in `ahg_settings` (saved via the generic `settings[...]` form array on the Global settings page). Any unrecognised value is coerced to `desc`.

## How it is read

`AhgCore\Support\GlobalSettings::sortBrowserDirectionUser()` and
`::sortBrowserDirectionAnonymous()` return a validated `asc`/`desc` string,
mirroring the existing `sortBrowserUser()` / `sortBrowserAnonymous()` field accessors.

## How it is used

`ahg-display`'s `DisplayController` browse methods pick the audience-appropriate
default and apply it only when the request has no explicit `sortDir`/`dir`:

```php
$defaultSortDir = auth()->check()
    ? GlobalSettings::sortBrowserDirectionUser()
    : GlobalSettings::sortBrowserDirectionAnonymous();
$sortDir = $request->input('sortDir', $request->input('dir', $defaultSortDir));
```

A user clicking a column/direction control still overrides the default for that
request (the `?dir=` / `?sortDir=` query param wins). The default only fills in
when none is supplied.

## Implementation note: saveSetting now creates missing parent rows

The legacy AtoM `setting` table needs a parent row before a value can be stored
in `setting_i18n`. `SettingsService::saveSetting()` previously only wrote when a
parent row already existed (`if ($setting) {...}` with no else), so any settings
key added *after* the AtoM base seed — like these two direction keys — saved as a
**silent no-op** and the reader fell back to the default forever. `saveSetting()`
now `insertGetId()`s the parent `setting` row (editable=1) when it is missing, so
newly added global-settings keys persist on first save, including on fresh
installs. This fixed the "setting saves but browse still defaults to desc" symptom.

# ahg-theme-b5

The AHG Bootstrap 5 theme for Heratio. Provides the central layout, navigation, footer, theme settings, and shared view composers. Every admin and public page in Heratio extends a layout from this package.

## Purpose

- Single source of truth for the Heratio look and feel (BS5 + `bi-*` icons)
- Master layouts (`layouts.app`, `layouts.public`, `layouts.admin`) under TWO view namespaces:
  - `theme::...` (primary)
  - `ahg-theme-b5::...` (legacy alias)
- Shared `themeData` payload (logo, palette, footer copy, etc.) injected via `View::composer`
- Logout / register stub routes so other packages can `route('logout')` safely

## Install

Auto-discovered. The ServiceProvider:

1. Binds `ThemeService` as a singleton
2. Registers views under both the `theme` and `ahg-theme-b5` namespaces
3. Adds a view composer that hydrates `themeData` on every `layouts.*` view
4. The `routes/web.php` file ships short logout / register stubs but the canonical auth routes come from `ahg-user-manage`

## Configuration

Theme palette + logo come from `ahg_settings` rows (managed through `/admin/settings`). `ThemeService::getLayoutData()` collects them once per request.

## Key classes

| Class | Role |
|---|---|
| `Services\ThemeService` | Aggregates `themeData` (logo, palette, footer copy, system version) |
| `Providers\AhgThemeB5ServiceProvider` | Registers view namespaces and view composers |

## Views

`resources/views/layouts/` contains the master Blade layouts every other package extends. Asset bundles (`ahgThemeB5Plugin.bundle.js`, theme CSS) live under `resources/assets/` and are compiled by the root Vite pipeline.

## Notes

- Do NOT load `display-mode.js` or `voiceCommands.js` separately - they are already inside the bundle. Double-loading throws `Identifier already declared` and kills all JS on the page.
- The clipboard normaliser script sits in `<head>` of the master layout and prevents `indexOf` crashes when localStorage shape drifts.

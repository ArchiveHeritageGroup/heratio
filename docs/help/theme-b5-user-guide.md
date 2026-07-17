# Theme B5

> Theme B5 is Heratio's Bootstrap 5 presentation layer: it supplies the master layout, header, navigation menus, footer and shared partials that every page extends, and injects the data (site identity, menus, user state, branding and asset bundles) those templates need.

## Overview

The Theme B5 module (`ahg-theme-b5`) is the central look and feel for Heratio. It defines the page layouts (one-, two- and three-column plus a print layout), the header and footer, the navigation menus, and a library of shared partials. A `ThemeService` assembles the data every layout needs and shares it with all layout templates through a view composer, so individual pages do not have to fetch site settings or menus themselves.

The theme is built on Bootstrap 5 with Bootstrap Icons. Its compiled JavaScript and CSS bundles (including clipboard, display-mode and voice-command behaviour) are loaded from the theme's distribution directory, and a separately generated dynamic CSS file applies branding colours site-wide.

## Key features

- **Layouts** - master, one-column, two-column, three-column and print layouts that pages extend.
- **Header and navigation** - the site header plus a full menu set: main menu, browse menu, GLAM/DAM menu, quick links, search box, language and culture switchers, user menu, clipboard menu and the AHG admin menu.
- **Footer and branding** - configurable footer text and an optional branding line.
- **Shared partials** - alerts and flash messages, admin notifications, the cart tab, a feedback tab, privacy message, accessibility statement and helpers, voice commands, a print-preview bar, and analytics tag managers.
- **Display modes** - reusable list, grid, gallery, timeline and tree (with tree-node) renderers for result and browse views.
- **Media player component** for embedded audio and video.
- **Per-culture menus** - menus render in the request's resolved locale, so navigation flips with the user's language choice.
- **Role-aware data** - the theme exposes whether the current user is authenticated, an administrator or an editor, and which plugins are enabled, so templates can show or hide controls accordingly.
- **Custom logo resolution** - a configured logo path is resolved against the install's public directory or uploads path, supporting absolute URLs, public assets and uploaded images.
- **Asset bundle discovery** - the vendor, theme JS and theme CSS bundles are located by glob so cache-busted filenames resolve automatically.

## How to use

Theme B5 is applied automatically; you do not invoke it directly. Pages extend a theme layout (for example the master or column layouts), and the view composer injects `themeData` into every layout template. That data includes the site title and description, logo and title toggles, the browse / main / quick-link menus, the current user and their groups, footer text, branding flag, and the resolved asset bundle URLs.

For administrators, the visible appearance is controlled through the Settings module rather than this package:

1. Site title, description and logo toggles come from the core `setting` table - edit them on the relevant Settings pages.
2. The custom logo is set via the `ahg_logo_path` setting; it can be an absolute URL, a public asset path, or an uploaded image under the uploads path.
3. Footer text and the branding line come from `ahg_footer_text` and `ahg_show_branding` in `ahg_settings`.
4. Branding colours are emitted through the dynamic theme CSS endpoint, so changes apply across the site without rebuilding assets.

Authentication routes (`/login`, `/logout`, registration) are owned by the user-management module, not by this theme — the theme only supplies the screens they render into.

## Configuration

- **Site identity** - `siteTitle`, `siteDescription`, and the `toggleLogo`, `toggleTitle`, `toggleDescription` and `toggleLanguageMenu` flags (core `setting` / `setting_i18n`).
- **Logo** - `ahg_logo_path` in `ahg_settings`; resolved against `public/` first, then the configured uploads path.
- **Footer and branding** - `ahg_footer_text` and `ahg_show_branding` in `ahg_settings`.
- **Asset bundles** - the vendor bundle, `ahgThemeB5Plugin.bundle.*.js` and `ahgThemeB5Plugin.bundle.*.css` are auto-discovered under `public/vendor/ahg-theme-b5/dist/`.
- Clipboard, display-mode and voice-command JavaScript ship inside the theme bundle; do not load standalone copies, as double-loading breaks page JavaScript.

## Known issues

- If the theme distribution bundles are missing from `public/vendor/ahg-theme-b5/dist/`, the corresponding JavaScript or CSS will not load; rebuild the theme assets to restore them.
- A configured logo path that does not resolve to an existing file is returned as-is so the broken image is visible and the path can be corrected, rather than silently reverting to the default.

## References

- Source: packages/ahg-theme-b5/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues (see the ahg-theme-b5 tracker)

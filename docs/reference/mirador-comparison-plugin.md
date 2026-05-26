> Heratio reference doc. Tracks the build pipeline behind issue #700 (Mirador comparison-glass / dual-pane slider).
> Sibling: `mirador-comparison-plugin.md` is the build-pipeline reference; `mirador-comparison-glass.md` is the feature-internals reference.

# Mirador comparison plugin - build pipeline and bundle regeneration

This document covers the build pipeline that produces the comparison-glass plugin in the shipped Mirador 4 bundle. For runtime behaviour, discovery rules, CSS contracts, and known limitations see `docs/reference/mirador-comparison-glass.md`.

## Source layout

```
tools/mirador-build/
  package.json                       webpack project, Mirador 4 + plugins
  webpack.config.js                  single-bundle UMD, exposes window.Mirador
  src/
    index.js                         entry: imports + wraps Mirador.viewer
    heratio-comparison-plugin.js     issue #700 - the dual-pane slider
    heratio-av-plugin.js             issue #701 - A/V playback panel
    heratio-magnifier-plugin.js      issue #698 - circular loupe
    heratio-scalebar-plugin.js       issue #698 - physical-dimensions bar
    heratio-search-plugin.js         issue #694 - Content Search 2.0 hooks
    heratio-workspace-persistence.js issue #699 - workspace save / restore
    heratio-mui-theme.js             central MUI 7 theme override
```

Every Heratio plugin module exports a Mirador 4 plugin-spec array (a list of `{ target, mode, component }` objects). `src/index.js` concatenates all of them onto the `plugins` array that is passed to `Mirador.viewer(config, plugins)`. The user never has to pass plugins explicitly - the wrapper in `src/index.js` auto-injects the full list.

## Plugin contract (comparison-glass specifically)

The comparison plugin in `heratio-comparison-plugin.js` exports:

- `heratioComparisonPlugin` (default + named) - the plugin-spec array consumed by `src/index.js`.
- `HeratioComparisonMenuItem` - the React component contributed at `target: 'WindowTopMenu'`.
- `attachComparison(activeViewer, partnerViewer)` - low-level entry point that mounts the overlay and returns a `{ setLabels, detach }` handle.
- `findPartnerWindowId(activeWindowId)` - the DOM-order partner-discovery helper.

`attachComparison` and `findPartnerWindowId` are exported deliberately so the smoke / unit test in `tools/mirador-build/__tests__/comparison.test.js` can exercise them under a stub OSD viewer without booting React or Mirador.

## Build pipeline

The build is a stock webpack 5 production build orchestrated by two npm scripts:

- `npm run build` - runs `webpack --mode production`. Output lands in `tools/mirador-build/dist/mirador.min.js`.
- `npm run deploy` - runs the build, then copies `dist/mirador.min.js` over `public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`. This is the file every Mirador-loading page on Heratio actually serves.

The bundle is a single UMD-style file. `src/index.js` overrides `Mirador.viewer` so callers get the plugin list injected automatically; the result is attached to `window.Mirador`.

### Regenerating the bundle

```bash
cd tools/mirador-build
npm install            # one-time, after a fresh clone
npm run deploy         # builds and overwrites the served bundle
```

A few host-level caveats:

- Node 20+ is required (matches the lockfile and React 19 toolchain).
- Run as a non-root user. Building as `root` will leave `node_modules/` and `dist/` files owned `root:root`; if the dev later runs the watcher as `www-data` the next build will fail with EACCES. If a root build slips through, `chown -R www-data:www-data tools/mirador-build/node_modules tools/mirador-build/dist`.
- Do NOT run the build on production. The deploy script writes directly into `public/vendor/...` which is served live by nginx. Build on the dev box, copy the artifact across, or commit the built bundle (the current convention - the bundle is checked into the repo).

The compiled bundle is tracked in git so production hosts never need a Node toolchain. The `tools/mirador-build/` workspace itself is unlocked per `.locked-paths`.

### Adding a plugin or extending the comparison plugin

1. Edit the plugin module under `src/`.
2. If you are adding a brand-new plugin file, import it in `src/index.js` and spread its array into the `plugins` constant in the Heratio-additions block.
3. Re-run `npm run deploy`.
4. Hard-refresh the browser (`Ctrl-Shift-R`) - the bundle filename does not version-bust automatically.

If a change needs a new npm dependency, prefer pinning the major version in `package.json` so reproducible builds across dev boxes stay reproducible.

### Dependency notes

- `mirador@^4.0.0` is the runtime base. Mirador 3 / MUI 4 / React 16-17 plugins (notably `mirador-annotations` and `mirador-textoverlay`) cannot be combined with this bundle without forking them forward.
- `@mui/material@^7` and `@mui/icons-material@^7` are required for `<MenuItem>` / `<Switch>` / `<CompareIcon>` used by the comparison plugin's menu entry.
- `react@^19` is pinned by `mirador-dl-plugin@1.0.0`. Downgrading React breaks that plugin's `useHooks` shape.

## Verifying the deployed bundle

Quick "did the deploy land" checks:

```bash
# 1. file exists and is non-empty
ls -l public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js

# 2. comparison-glass code paths are present in the minified output
grep -c "heratio-compare-overlay" public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js

# 3. exported symbol survives webpack tree-shaking
grep -c "HeratioComparison" public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js
```

Each grep should return `>0`. If `heratio-compare-overlay` is absent the comparison plugin was dropped at bundle time - usually because `src/index.js` was edited to remove the import.

## Unit test

`tools/mirador-build/__tests__/comparison.test.js` exercises the pure-JS sync logic that does not depend on React or a live Mirador / OSD instance:

- `findPartnerWindowId` against a fake `__heratioMiradorOsdRegistry`.
- `attachComparison` against a stub OSD viewer + stub partner canvas, asserting overlay DOM is mounted, the seam handle responds to pointer drag, and `detach` cleans up.
- A presence smoke check on the deployed bundle file (`public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`) - confirms the artifact exists, is non-empty, and contains the plugin's compiled namespace markers.

The test file uses node's built-in `node:test` runner so it requires no extra devDependencies:

```bash
cd tools/mirador-build
node --test __tests__/comparison.test.js
```

If `node --test` exits 0 the comparison plugin's sync logic and deployed bundle are both healthy.

## Related issues

- #694 - Content Search 2.0 (sibling plugin)
- #698 - Scalebar + magnifier (sibling plugins, share the same bundle)
- #699 - Workspace persistence (the system that one day should round-trip the seam position)
- #700 - This plugin (comparison-glass / dual-pane slider)
- #701 - A/V playback + transcript panel (sibling plugin)

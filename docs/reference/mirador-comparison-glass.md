> Heratio reference doc. Tracks issue #700 (Mirador comparison-glass / dual-pane slider).

# Mirador comparison glass

The comparison glass is a Heratio-internal Mirador 4 plugin that adds a draggable vertical "before/after" slider between two open Mirador windows in the same workspace. It is intended for paired-canvas review tasks such as conservation before/after photography, multi-spectral imaging passes (RGB vs UV vs IR), or comparing two manuscript witnesses page-by-page.

## Where it lives

- Plugin source: `tools/mirador-build/src/heratio-comparison-plugin.js`
- Registered in: `tools/mirador-build/src/index.js` (Heratio additions block for issues #700 + #701)
- Shipped bundle: `public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`
- Loaded by every page that initialises Mirador via `ahg-iiif-viewer.js`

## Toolbar entry

The plugin contributes a `WindowTopMenu` MenuItem labelled "Comparison glass" with the `bi-arrows-collapse-vertical`-equivalent MUI icon `CompareIcon`. The right-edge `<Switch>` reflects the per-window enable state and is stored on `window.AHG_IIIF_STATE.compare[windowId]` for embedder recovery.

## Discovery rules

When the switch is flipped on the plugin looks for a partner viewer via two paths:

1. DOM-order scan of `[data-test-id^="window-"]` elements, picking the first windowId that is not the active one and is registered in `window.__heratioMiradorOsdRegistry`.
2. Fallback - any non-self key in the registry.

If no partner exists the menu item surfaces a single console hint (`[HeratioComparison] open a second Mirador window in this workspace ...`) and auto-flips the switch back to off so the UI does not lie about state.

## Overlay rendering

Once a partner is found we mount a `<div class="heratio-compare-overlay">` over the active viewer's `osdViewer.element`. The overlay contains a single `<canvas>` that paints the partner viewer's `.openseadragon-canvas canvas` content clipped to the right of the draggable seam. The seam is a thin `HANDLE_BG` (Heratio accent yellow) rule with a circular knob for grabbing.

Repainting is wired to both the active and partner viewer's `animation`, `zoom`, `resize` and `open` OSD events, so pan/zoom in EITHER pane scrubs the comparison without stale frames.

Independent navigation is intentional: zooming into the right pane lets the user line up a detail at full magnification against the left pane's current state. Co-navigated mode (synchronised zoom/pan) is tracked as a follow-up and is NOT in this drop.

## CORS

Sampling the partner canvas via `ctx.drawImage` will throw if the partner is loading cross-origin tiles from a server that does not return `Access-Control-Allow-Origin`. The plugin warns once per overlay (`overlay.__corsWarned`) and otherwise paints whatever the active pane shows. The Cantaloupe IIIF Image API server at `https://heratio-host/iiif/` returns CORS headers so internal-to-internal comparisons always succeed; external manifests may not.

## Hooks the plugin relies on

- `window.__heratioMiradorOsdRegistry[windowId]` - mapping written by the OSD `open` monkey-patch in `index.js`.
- `window.__heratioMiradorStore` - redux store reference for the (future) auto-label work.
- The OSD canvas drawer (NOT WebGL) being the active draw mode. This is pinned by `ahg-iiif-viewer.js`. If a host page overrides it to WebGL the partner canvas will be unreadable.

## Coexistence

The overlay sits at z-index 1180, BELOW the magnifier loupe (1200) and ABOVE the scalebar (1100), so a user can simultaneously: see the scalebar of the active pane, drag the seam, and hold the magnifier loupe over either side.

## Known limitations

- One partner per active window. Three-way splits (e.g. RGB / UV / IR side-by-side-by-side) are a follow-up.
- No saved-state of the seam position across page reloads. Workspace persistence (issue #699) does not yet round-trip `AHG_IIIF_STATE.compare`.
- Independent navigation only. A "lock zoom" toggle is a candidate enhancement.

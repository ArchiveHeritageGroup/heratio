# 3D Viewer Phase 4 - Measurement, Cross-Section, Reduced Motion

Issue #666 Phase 4 adds three viewer enhancements to the Heratio 3D viewer (`packages/ahg-3d-model`). All three live in `resources/views/_model3d-viewer.blade.php` so they ship with every page that already renders the model-viewer partial - browse, show, embed, and the multi-angle gallery branch.

## Toolbar additions

Two new Bootstrap 5 toolbars sit underneath the existing Phase 2 animation toolbar and the Phase 3 bookmark/share-view toolbar:

- `.ahg-3d-measure-toolbar` - Measure button (`bi bi-rulers`), Clear button (`bi bi-eraser`), live status text.
- `.ahg-3d-cross-toolbar` - Cross-section toggle (`bi bi-scissors`), an axis radio group (X / Y / Z), a position slider, a live offset readout.

Both toolbars use the same `btn btn-sm atom-btn-white` styling, `gap-2 mb-2` wrapper, and `data-target="ahg-3d-viewer-{id}"` association as the existing toolbars. They render once per visible viewer, which means the tabbed multi-angle gallery branch picks them up too.

## Measurement tool (`heratio-3d-measure.js`)

Click Measure, then click two points on the model. The script reads the world-space position via the `model-viewer` `positionAndNormalFromPoint()` API, anchors two hotspots on the surface, and renders a third hotspot at the midpoint with the Euclidean distance label. Hotspots track camera movement natively because they ride the model-viewer slot system. Distance is reported in raw model units; the toolbar carries `data-measure-units="m"` as the label suffix because glTF / glb meshes are metres by convention. For non-metric exports the publisher can override the attribute.

Escape cancels an in-progress measurement. The Clear button removes the current pair. The toolbar supports multiple viewers per page - a per-page counter keeps hotspot slot names globally unique.

## Cross-section view (`heratio-3d-cross-section.js`)

`model-viewer` does not expose a documented clipping-plane shader, and patching its private Three.js renderer is brittle across releases. Phase 4 ships a lightweight overlay instead: when the user activates cross-section mode, an HTML overlay (red guideline plus CROSS-SECTION badge) is drawn over the viewer, and the `cameraTarget` is shifted along the chosen axis to frame the slice depth. The slider moves the target through `-extent/2 ... +extent/2` where `extent` comes from `viewer.getDimensions()[axis]`.

A hook point is reserved: if `window.HeratioCrossSectionAdvanced.update/disable` are defined elsewhere (for example by a future Phase 5 deliverable that pulls in a real `THREE.Plane`), the toolbar will call them on top of the overlay. Escape deactivates the mode and restores the camera target.

## Reduced-motion handling

The Phase 4 inline script in the blade checks `window.matchMedia('(prefers-reduced-motion: reduce)')`. When the user has the reduced-motion preference set, the viewer:

- strips `auto-rotate` and `autoplay` attributes from every `<model-viewer>` element on `init` and again on `load`,
- calls `viewer.pause()` so animation toolbars render the play icon (paused state),
- inside `applyState()`, temporarily zeros `viewer.interpolationDecay` so the share-view URL snap-restores the pose instead of running model-viewer's default smooth tween, then restores the original decay on the next frame.

The data-source-of-truth for the user-configured auto-rotate flag still lives on the `<model-viewer data-auto-rotate-pref="0|1">` attribute, so future code can re-enable it on a per-action basis without losing the original preference.

## Inlining

The two Phase 4 JS files live at `packages/ahg-3d-model/resources/js/heratio-3d-{measure,cross-section}.js`. The blade reads both via `file_get_contents` at render time and embeds them inside `<script>` tags before its closing `@endif`. That keeps the package working without `php artisan vendor:publish`, while leaving the canonical source-of-truth as standalone files that the rest of the codebase (and the IDE, and the linter) treats as plain JS. Both scripts are idempotent IIFEs guarded by `window.__ahg3dMeasureInit` / `window.__ahg3dCrossSectionInit`, so embedding them inline on every model card on a multi-model page is harmless.

## What Phase 4 does not include

Draco mesh compression support and Apple USDZ export are Phase 5 of #666 and are tracked separately. Real WebGL clipping-plane support is the long-tail follow-up that the `HeratioCrossSectionAdvanced` hook reserves space for. Neither lands in Phase 4.

Copyright (c) Johan Pieterse / Plain Sailing Information Systems / AGPL-3.0-or-later.

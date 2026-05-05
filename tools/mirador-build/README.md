# Heratio Mirador build

Self-contained webpack project that bundles Mirador 4 with plugins into the single file served at `/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`. The deployed bundle is what `viewer.html` and `compare.html` load.

## Plugins included

- `mirador-image-tools` - rotate, flip, brightness, contrast, saturation, invert
- `mirador-dl-plugin` - download original / canvas / region

The `src/index.js` wrapper auto-attaches both plugin lists to every `Mirador.viewer(config)` call, so callers do not need to pass plugins themselves.

## Rebuild + deploy

```
cd tools/mirador-build
npm install         # one-time
npm run deploy      # builds and copies dist/mirador.min.js into public/vendor/...
```

`npm run build` builds without copying.

## Adding a plugin

1. `npm install <plugin-name>`
2. Add the import + spread into the `plugins` array in `src/index.js`.
3. `npm run deploy`.

Note: `mirador-annotations` and `mirador-textoverlay` are still on Mirador 3 / MUI 4 / React 16-17 and cannot be combined with the Mirador 4 chain used here. If you need them, either fork them to MUI 7 or downgrade the whole bundle to Mirador 3.

## Notes

- Output target is a `window.Mirador` UMD-ish global with a wrapped `viewer()` that auto-injects the plugin list.
- React 19 is required because `mirador-dl-plugin@1.0.0` pins to it.
- Mirador 4 uses MUI 7 + emotion CSS-in-JS, so styles inject at runtime - no separate `mirador.min.css` is needed (the existing one in the public folder is just a placeholder comment).

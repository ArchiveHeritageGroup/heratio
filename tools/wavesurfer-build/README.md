# Heratio WaveSurfer.js vendor bundle

Builds the WaveSurfer.js (~50 KB minified) front-end asset that backs
the `media_show_waveform` setting on `/admin/ahgSettings/media` (see
issue #101).

WaveSurfer 7.x ships a precompiled UMD bundle on npm; no webpack
step is required.

## Build

```bash
cd tools/wavesurfer-build
./build.sh
```

Output: `public/vendor/wavesurfer/wavesurfer.min.js`.

## Wiring

`packages/ahg-theme-b5/resources/views/layouts/master.blade.php` reads
`window.AHG_MEDIA.show_waveform` and, when true, emits a `<script>` tag
for the bundle plus an init that walks every `.ahg-media-player`
wrapper on the page (the AHG custom audio UI in
`_digital-object-viewer.blade.php`), finds the hidden `<audio>` element
inside, and replaces the placeholder progress bar with a real
WaveSurfer canvas. WaveSurfer is configured with
`backend: 'MediaElement'` + `media: <existingAudio>` so the existing
play/pause/skip/speed/volume buttons keep driving the same `<audio>`
element — the waveform is purely a visual upgrade.

If the WaveSurfer script fails to load (asset missing, CSP block,
404), the init guards on `typeof window.WaveSurfer === 'function'`
and leaves the original placeholder progress bar in place — no
runtime errors.

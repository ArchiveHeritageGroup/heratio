# Heratio media-player vendor bundle

Builds the Plyr (~50 KB) and Video.js (~200 KB) front-end assets that
back the `media_player_type` setting on `/admin/ahgSettings/media`
(see issue #103).

Both libraries ship precompiled UMD bundles on npm — no webpack step
is required. `build.sh` runs `npm install` and copies the dist files
into the right place under `public/vendor/`.

## Build

```bash
cd tools/plyr-build
./build.sh
```

Output:

| File | Destination |
|---|---|
| `plyr.min.js`, `plyr.css`, `plyr.svg`, `blank.mp4` | `public/vendor/plyr/` |
| `video.min.js`, `video-js.min.css` | `public/vendor/videojs/` |

## Wiring

`packages/ahg-theme-b5/resources/views/layouts/master.blade.php` reads
`window.AHG_MEDIA.player_type` and, when it equals `plyr` or `videojs`,
emits the matching `<link>`/`<script>` tags + a small enhancement loop
that wraps every `<audio>`/`<video>` element on the page.

If the chosen library fails to load (e.g. asset deleted, CDN block,
404), the inline init falls back to native HTML5 — the existing
`controls`/`autoplay`/`loop`/`volume` attributes still apply.

## Bundle sizes (gzipped)

| Player | JS | CSS |
|---|---|---|
| Plyr 3 | ~50 KB | ~8 KB |
| Video.js 8 | ~200 KB | ~50 KB |

Operators on bandwidth-constrained networks should prefer `basic` (no
extra payload) or `plyr`. Video.js is the heavy option but offers more
plugin surface.

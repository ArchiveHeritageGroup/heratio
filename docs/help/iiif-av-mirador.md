> Heratio Help Center article. Category: Viewers & Media.

# IIIF A/V playback in Mirador

## User Guide

The Heratio Mirador 4 viewer can play video and audio canvases inline, on top of the same window that normally renders still images. The control appears as an "A/V playback + transcript" entry in the window's top menu (the three-dot menu icon at the top-right of each Mirador window).

This article explains when the control shows up, what each control on the inline player does, and the keyboard shortcuts that work while the player is focused.

---

## When the A/V control appears

The toggle entry "A/V playback + transcript" is always present in the Mirador window menu, but flipping it on only does something when the **active canvas** in that window carries a painting annotation whose body is one of:

- `type: "Video"` (a video resource - typically `video/mp4`, `video/webm`)
- `type: "Sound"` or `type: "Audio"` (an audio resource - typically `audio/mpeg`, `audio/wav`, `audio/ogg`)

Heratio follows the IIIF Presentation API 3.0 convention exactly. If the active canvas only carries still-image painting bodies (the default for digital photographs, scanned documents, manuscript pages), the toggle prints a one-shot console hint and flips itself back to off - the still-image canvas keeps rendering normally.

You can tell a record has A/V canvases at a glance: in the Mirador thumbnail strip, A/V canvases get the small filmstrip icon supplied by the manifest's `accompanyingCanvas` thumbnail.

---

## What appears when you switch it on

When the active canvas is A/V, flipping the menu switch on does three things at once:

1. **Hides the underlying OpenSeadragon image canvas** - the OSD canvas wrapper is collapsed for as long as the A/V toggle is on. The original image canvas comes back automatically when you turn the toggle off again.
2. **Inserts a `<video>` or `<audio>` element** filling the full window, with the browser's native media controls (play/pause, scrubber, volume, time display, fullscreen, captions). The video/audio source URL comes from the manifest's painting annotation.
3. **Inserts a 300-pixel transcript panel** docked to the right edge of the window. The panel loads time-aligned transcript lines for the active canvas if Heratio has a transcript on file; otherwise it shows a quiet "No transcript for this canvas." message and stays out of the way.

The toggle is per-window, so a workspace with two Mirador windows side-by-side can have A/V on in one and the still-image canvas in the other.

---

## Player controls

The inline player uses the browser's native HTML5 media controls - the same controls you see on a standalone `<video>` or `<audio>` element. They are visually consistent across Chrome, Firefox, Safari and Edge:

- **Play / pause** - the leftmost button on the player toolbar
- **Scrubber** - drag to seek; the underlying current time updates the transcript panel highlight in real-time
- **Volume** - hover the speaker icon, drag the slider; muted state is remembered per browser session
- **Time display** - shows current position / total duration in `MM:SS` (or `HH:MM:SS` for content over an hour)
- **Captions toggle** - the "CC" icon, only visible when the underlying media carries embedded captions or a separate `<track>` element (the current Heratio plugin does not yet wire separate WebVTT tracks; see Limitations below)
- **Fullscreen** - the rightmost icon, expands the player to fill the viewport. Press `Esc` to leave fullscreen.
- **Picture-in-picture** (video only, Chrome and Edge) - the small floating-window icon in the player toolbar; lets you pop the video out of the browser tab while you continue working in Heratio

---

## Keyboard shortcuts

When the player has keyboard focus (click the video/audio area once), the standard HTML5 media shortcuts apply in every major browser:

| Key | Action |
|---|---|
| `Space` | Play / pause |
| `K` | Play / pause (alternate) |
| `Left arrow` | Seek backwards 5 seconds |
| `Right arrow` | Seek forwards 5 seconds |
| `J` | Seek backwards 10 seconds |
| `L` | Seek forwards 10 seconds |
| `M` | Toggle mute |
| `Up arrow` | Volume up |
| `Down arrow` | Volume down |
| `0` to `9` | Jump to 0% / 10% / ... / 90% of duration |
| `F` | Toggle fullscreen (Chrome / Edge) |
| `C` | Toggle captions (when a track is available) |

Browser-level shortcuts (`Ctrl+Shift+M`, etc.) are unaffected. The transcript panel does not intercept the media keys, so the keyboard shortcuts above keep working while you are clicking through transcript rows.

---

## Transcript panel

The right-edge transcript panel lists every time-aligned line in the canvas's transcript. Each row carries:

- A small monospace timestamp (`MM:SS`) on the left
- The transcribed text on the right

Behaviour:

- **Click a row** - the player jumps to that row's start time and resumes playback.
- **As the player plays** - the row whose time-range contains the current playback position is highlighted in Heratio yellow. The panel auto-scrolls so the active row always stays in view.
- **No transcript on file** - the panel shows a quiet "No transcript for this canvas." italic message and the player continues to work normally.

The transcript is fetched from Heratio's `/api/iiif/transcript` endpoint scoped to the active canvas. If a curator has not uploaded a transcript for the canvas yet, the endpoint returns an empty list and the panel shows the empty-state message - the player itself is never blocked.

---

## Turning the toggle off

Flip the same menu switch back to off and the plugin:

- Pauses the `<video>` / `<audio>` element so audio stops immediately
- Detaches the player and the transcript panel from the DOM
- Restores the OpenSeadragon image canvas underneath - exactly the same state you had before turning A/V on

If you close the Mirador window entirely the player is destroyed along with it; nothing leaks between sessions.

---

## Limitations

- **Separate WebVTT caption tracks are not yet wired.** Captions only appear when they are embedded inside the media container (e.g. burned-in subtitles or an in-container `mov_text` track). Loading a separate `.vtt` `seeAlso` reference is on the candidate-enhancement list.
- **Audio-only canvases** show the audio controls on a black background filling the window. A future polish pass will replace the empty area with a centred player and waveform.
- **Multi-body painting annotations** (e.g. a video that lists an alternate audio track or alternate resolutions) currently resolve only the first matching body. Choosing between alternate qualities is not yet wired into the toggle.
- **CORS-clean media URLs are required.** The player uses `crossOrigin="anonymous"`. If the upstream media URL does not return `Access-Control-Allow-Origin: *` the browser will block playback. Heratio's own `uploads` proxy adds the right headers automatically; external manifests that point at a third-party media host need that host to be CORS-friendly.

---

## See also

- [IIIF Integration](iiif-integration-user-guide.md)
- [IIIF Scalebar and Magnifier](iiif-scalebar-magnifier.md)
- [IIIF Content Search](iiif-content-search.md)
- [IIIF Compliance and Validation](iiif-compliance-validation.md)

## References

- IIIF Presentation API 3.0: https://iiif.io/api/presentation/3.0/
- W3C Media Fragments URI 1.0: https://www.w3.org/TR/media-frags/
- Issue tracker: GH #701

# IIIF Audio + Video Playback

Heratio's IIIF viewer now plays audio and video files directly, alongside
the existing image and 3D-model surfaces. Recordings, oral histories,
lectures, and moving-image archives are exposed through the same manifest
endpoint as still images, so the same viewer (Mirador 4 / Universal Viewer 4)
handles all of it.

## Overview

When a digital object's mime type is `audio/*` or `video/*` (or its
filename ends in a recognised audio / video extension), the manifest
generator emits a IIIF Presentation 3.0 **temporal canvas** rather than
an image canvas. The viewer detects the canvas type and shows a media
player with scrub bar, volume, and play / pause controls.

Supported audio extensions: `.mp3`, `.wav`, `.ogg`, `.oga`, `.flac`,
`.m4a`, `.aac`, `.opus`.

Supported video extensions: `.mp4`, `.webm`, `.mov`, `.m4v`, `.mkv`,
`.avi`, `.ogv`.

## How A/V canvases look in the manifest

Each A/V digital object becomes one canvas with `duration` in seconds:

```json
{
  "type": "Canvas",
  "duration": 184.5,
  "items": [{
    "items": [{
      "motivation": "painting",
      "body": {
        "type":     "Sound",
        "format":   "audio/mpeg",
        "duration": 184.5
      }
    }]
  }]
}
```

Video canvases additionally carry `width` and `height`. A
`MediaFragmentSelector` service block on each body tells the viewer it
can request `#t=ss,ee` ranges to address a temporal slice.

## Poster frames for video

If `digital_object_property.poster_url` (or `poster_frame`) is set, the
manifest emits both a canvas `thumbnail` and a `placeholderCanvas` -
Mirador 4 renders the still before the user clicks play.

## Populating duration and dimensions

The manifest is best-effort. Without explicit metadata:

- Duration falls back to `1.0` seconds (the spec requires a positive
  duration; a placeholder keeps the manifest valid).
- Video width / height fall back to `1920 x 1080`.

For accurate playback timeline display, populate during ingest:

```sql
INSERT INTO digital_object_property (object_id, name, value)
VALUES
  (<digital_object.id>, 'duration', '184.5'),
  (<digital_object.id>, 'width',    '1920'),
  (<digital_object.id>, 'height',   '1080'),
  (<digital_object.id>, 'poster_url', '<full URL to a JPG>');
```

The `ahg-ingest` package's media-processing step writes these
automatically when ffprobe is available on the host.

## What to check when playback fails

1. Mime type on the `digital_object` row is set correctly (not
   `application/octet-stream`).
2. The file is accessible at the same path the manifest advertises -
   confirm with `curl -I <media-url>` from the viewer host.
3. Browser console: a `Range: bytes=` request must return `206 Partial
   Content`. Nginx must not strip the range header. The Heratio default
   nginx config already passes ranges through.
4. CORS: cross-origin viewers need `Access-Control-Allow-Origin: *` on
   the media response. The standard storage location includes this; if
   you've moved media to S3 / a CDN, configure CORS there.

## Related help articles

- [IIIF Content Search](./iiif-content-search.md) - searching transcribed
  audio (when speech-to-text has produced a transcript).
- [IIIF Content State](./iiif-content-state.md) - sharing a deep link to
  a specific second within an audio or video canvas.
- [IIIF Auth 2.0](./iiif-auth-2.md) - restricting playback to authorised
  users.

## Reference

- IIIF Presentation 3.0 (canvases) - https://iiif.io/api/presentation/3.0/
- W3C Media Fragments URI 1.0 - https://www.w3.org/TR/media-frags/

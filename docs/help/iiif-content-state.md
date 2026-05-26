# IIIF Content State

Share a deep link that opens a IIIF viewer on the same manifest, the
same canvas, and the same region another researcher was looking at.

## Overview

A IIIF Content State is a short, URL-safe token that captures a viewer's
current pose:

- which manifest is open
- which canvas in the manifest
- (optionally) a `xywh=` region of the canvas
- (optionally) zoom and rotation

Sharing the token via chat or email lets the recipient open the same
viewport, on the same canvas, in any IIIF-aware viewer that supports
Content State 1.0 (Mirador 4, UV 4, custom integrations).

## API

Heratio exposes a Content State 1.0 endpoint pair:

| Method | Path | Purpose |
|---|---|---|
| POST | `/iiif/content-state/encode` | Mint a token from a manifest / canvas / selector triple. |
| GET  | `/iiif/content-state/decode?token=<token>` | Resolve a token back into the annotation. |

### Encode request

```bash
curl -X POST https://heratio.example/iiif/content-state/encode \
  -H 'Content-Type: application/json' \
  -d '{
    "manifest": "https://heratio.example/iiif-manifest/photo-album-1",
    "canvas":   "https://heratio.example/iiif-manifest/photo-album-1/canvas/4",
    "selector": { "xywh": "100,200,400,300" }
  }'
```

### Encode response

```json
{
  "token": "<url-safe base64>",
  "annotation": {
    "@context":   "http://iiif.io/api/presentation/3/context.json",
    "type":       "Annotation",
    "motivation": "contentState",
    "target":     { "type": "SpecificResource", "source": { ... } }
  }
}
```

### Decode

```bash
curl 'https://heratio.example/iiif/content-state/decode?token=<token>'
```

Returns the same annotation, or HTTP 400 + an error envelope if the
token is malformed.

## How tokens are encoded

The spec uses URL-safe base64 (RFC 4648 §5) with stripped padding:

1. Serialise the annotation to JSON.
2. Base64-encode.
3. Replace `+` -> `-` and `/` -> `_`.
4. Strip trailing `=`.

The result fits in any URL, chat link, or QR code with no escaping.

## Worked example

Researcher A is looking at canvas 4 of a photo album manifest, zoomed
into a face in the top-left:

```bash
curl -X POST https://heratio.example/iiif/content-state/encode \
  -H 'Content-Type: application/json' \
  -d '{
    "manifest": "https://heratio.example/iiif-manifest/album",
    "canvas":   "https://heratio.example/iiif-manifest/album/canvas/4",
    "selector": { "xywh": "120,80,200,200" }
  }'
# -> { "token": "eyJAY29...", "annotation": { ... } }
```

Researcher A pastes
`https://heratio.example/iiif-viewer/album?cs=eyJAY29...` into a chat.
Researcher B clicks it; the viewer reads `?cs=...`, calls the decode
endpoint, and pans / zooms to the same face.

## Authorization

Content State tokens carry only references. They don't grant access to
the manifest itself - the manifest's existing IIIF Auth 2.0 protections
still apply. See [IIIF Auth 2.0](./iiif-auth-2.md) for the access flow.

## Related help articles

- [IIIF Auth 2.0](./iiif-auth-2.md)
- [IIIF Content Search](./iiif-content-search.md)
- [IIIF A/V Playback](./iiif-av-playback.md)

## Reference

- IIIF Content State 1.0 - https://iiif.io/api/content-state/1.0/
- W3C Annotation Model - https://www.w3.org/TR/annotation-model/

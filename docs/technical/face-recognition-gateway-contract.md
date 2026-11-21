# Face recognition - gateway route contract

Heratio issue: heratio#1764 (face recognition search). This page is for the AI gateway team: it says what Heratio sends to the gateway face route and what it expects back, so the route can be added. Heratio already speaks this contract; until the route exists, every call gets a 404 and Heratio stores no faces.

## Route

`POST https://ai.theahg.co.za/ai/v1/vision/faces`

The host comes from the gateway URL Heratio already uses for other AI calls (the `ai_services_api_url` setting, default `https://ai.theahg.co.za/ai/v1`). The path is the `face_gateway_path` setting (Settings - Face Detection), default `/ai/v1/vision/faces`, so a route under a different name needs no code change.

Heratio never calls a GPU node directly. The gateway chooses the node; the 24 GB RTX 3090 on 192.168.0.76 is the preferred home for the face model.

## Request

Headers:

- `Authorization: Bearer <gateway API key>` - the key every Heratio gateway client uses (`AiServicesSettings::gatewayKey()`).
- `Content-Type: application/json`
- `Accept: application/json`

Body:

```json
{
  "image": "<base64 of the image file>",
  "filename": "photo.jpg",
  "return_embedding": true
}
```

- `image` is JPEG or PNG. Heratio converts TIFF, JP2 and other formats to JPEG before sending. Video is never sent: Heratio samples one frame every `face_video_interval` seconds (default 10) with ffmpeg and sends each frame as its own JPEG request.
- `return_embedding` is always true. Heratio needs the vector to match faces.

Timeout on the Heratio side: the `ai_services_api_timeout` setting (default 60 s).

## Response (200)

```json
{
  "model": "example-face-model-v1",
  "faces": [
    { "box": [120, 64, 88, 88], "embedding": [0.0123, -0.0456, "..."], "score": 0.998 }
  ]
}
```

- `faces` - one entry per detected face; an empty list when there is none.
- `box` - `[x, y, w, h]` in pixels of the image as sent, origin top left.
- `embedding` - the face vector, a list of floats. Every face from one model must have the same length (for example 512 for ArcFace). Heratio scales it to unit length itself and compares faces by cosine similarity, so it does not have to be normalised. Faces are compared only with faces whose vectors have the same length.
- `score` - detection confidence, 0 to 1.
- `model` - optional; recorded with the call cost. Include the model name and version, because embeddings from different models are not comparable.

The match threshold (`face_match_threshold`, default 0.6) suits ArcFace-style models in cosine space; it is approximate and should be checked against the chosen model.

## Errors

| Answer | What Heratio does |
| --- | --- |
| 404 | The route is not deployed. Logged once a day, no faces stored, ingest carries on. |
| Other non-2xx, timeout | Logged as a warning, no faces stored for that image or frame, ingest carries on. |
| 401 / 403 | Same as any non-2xx. Check the key has the `gateway` scope. |

: Heratio behaviour on gateway errors

Heratio counts each call against the `face_detect` AI quota and records it in the AI call cost log.

## Privacy

Face images and vectors are personal (biometric) information. The gateway should not keep the image or the vector after answering, and should not log request bodies for this route.

## Heratio side

- Client: `packages/ahg-ai-services/src/Services/GatewayFaceDetector.php`
- Detection and video sampling: `packages/ahg-core/src/Services/FaceDetectionService.php`
- Matching, naming, search, purge: `packages/ahg-core/src/Services/FaceRecognitionService.php`
- Tests (gateway faked): `tests/Feature/FaceRecognitionTest.php`

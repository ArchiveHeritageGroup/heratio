# Image embedding - AI gateway contract (heratio#1272)

Heratio's image search (search by picture, search by description, "more like this") needs a multimodal embedder: one model family that puts images and short texts into the same vector space. Heratio never calls a GPU node for this. Every call goes to the AHG AI gateway, and the gateway picks the node. This page is the contract between the two sides, so the GPU service can be built and swapped without touching Heratio.

## State on 21 November 2026

Checked live through the gateway with Heratio's configured key:

- The gateway has no `/ai/v1/models` route (404), and no OpenAI-style `/ai/v1/embeddings` route.
- `POST /ai/v1/embed/image` is registered (it is in the gateway's OpenAPI document), but answers **HTTP 500**: no node lists the `clip` service yet, so the call falls through to the fallback worker on `.76`, which is down.
- `/ai/v1/routing` shows no node carrying `clip`. Ollama on the nodes serves `nomic-embed-text`, `qwen3:8b`, `qwen2.5vl:7b`, `llava` and `mistral`. None of these embed images.
- `POST /ai/v1/ollama/api/embeddings` with `nomic-embed-text` returns a 768-dimension vector. The text half of the default pairing below therefore works today.

The Heratio side is complete and tested against a faked gateway. Image search starts returning results once a node serves `/embed/image`.

## Model choice

The default is **`nomic-embed-vision-v1.5`** (768 dimensions, cosine). It was trained into the same space as `nomic-embed-text-v1.5`, the model Ollama already serves as `nomic-embed-text`. So text-to-image search reuses the existing text embedder, and only the image tower needs a new service. It is about 90M parameters (under 1 GB of VRAM in fp16) and fits on `.78` (RTX 3070) or `.115` (RTX 3080).

A CLIP-family model (OpenCLIP `ViT-B-32`, 512 dimensions, or SigLIP) also works. It has to serve both of its towers through the gateway, because Ollama has no CLIP text tower. See the `/embed/text` route below.

Swapping models means rebuilding the collection: `php artisan ahg:image-embed --recreate`.

## Routes

All routes sit under `https://ai.theahg.co.za/ai/v1`. Authentication, scope, quota, metering and failover work exactly as they do for `ner`, `htr` and `tts`: `Authorization: Bearer <key>` with the `gateway` scope. Upstream selection uses `gpu_nodes.capabilities.services @> ["clip"]`.

### `POST /embed/image` (required)

Request:

```json
{ "model": "nomic-embed-vision-v1.5", "image": "<base64 of the raw file bytes>" }
```

- `image` holds the file bytes, base64 encoded, with no `data:` prefix. Heratio sends JPEG, PNG, GIF, WebP, BMP or TIFF. It sends the reference copy (a web-size JPEG) when one exists, otherwise the master, and never more than `ahg_discovery_image_max_mb` (default 25 MB). Encrypted-at-rest files are decrypted first.
- The service must decode any of those formats, convert to RGB, and apply the model's own preprocessing (resize and crop, normalise).

Response `200`:

```json
{ "embedding": [0.0123, -0.0456, "... 768 floats"], "model": "nomic-embed-vision-v1.5", "dimensions": 768 }
```

Heratio reads only `embedding`. The other two fields are recommended, for logging. Returning L2-normalised vectors is recommended, though Qdrant's cosine distance does not depend on it.

### `POST /embed/text` (only for a model with its own text tower)

```json
{ "model": "clip-vit-b-32", "text": "a steam train crossing a bridge" }
```

The response has the same shape as `/embed/image`. Heratio uses this route only when the setting `ahg_discovery_image_text_model` is empty. With the default nomic pairing, text goes through `POST /ollama/api/embeddings` as `{"model": "nomic-embed-text", "prompt": "search_query: <text>"}`, a route that already exists.

### Errors

How Heratio reads the status code decides whether it retries or gives up on an image, so the codes matter:

| Status | Heratio's reading | Effect |
| --- | --- | --- |
| 200 with an `embedding` array | success | Point upserted, ledger row `embedded` |
| 400, 413, 415, 422 | this image is bad (undecodable, too large, unsupported) | Ledger row `failed`, attempt counted; dropped after 3 attempts |
| 401, 403 | key or scope problem | Run stops; nothing recorded |
| 404, 408, 429, any 5xx, timeout | no upstream, busy, or down | Run stops; nothing recorded; the next sweep retries |

: Gateway status codes and how Heratio handles them

The service should therefore return 4xx only for problems with the input, and 5xx or 503 when it cannot work, for example a model that has not loaded or GPU memory that has run out.

### Health

The routes need no separate health call. `php artisan ahg:image-embed` starts by embedding a 1x1 PNG. If that fails, it stops and leaves the index and ledger untouched.

## What Heratio stores

- Qdrant collection `ahg_discovery_image_collection` (default `archive_images`), cosine distance, with the size taken from the first vector. Heratio refuses to write into an existing collection whose vectors have a different size, and points to `--recreate` instead.
- One point per master image digital object. The point id is `digital_object.id`, and the payload holds `information_object_id`, `digital_object_id`, `object_id` (legacy key), `title`, `slug`, `entity_iri`, `mime_type`, `model` and `database`.
- Ledger table `ahg_image_embedding`, one row per digital object: model, dimensions, status (`embedded`, `skipped`, `failed`), attempts, checksum and the time of embedding. A changed checksum or a model change makes the image pending again.
- Images of records under a community protocol (TK/ICIP terms, #1406) are never embedded.

## Disclosure

Search is open to visitors, so the gates are applied after the vector search, per record:

- **DisclosureGate**: published, and no ICIP, ODRL, embargo, security classification or tenant restriction.
- **PII redaction**: records carrying visual redaction regions are left out, since a visual match would reveal the unredacted image.
- **FileScreeningGate**: in repositories or collections that require screening, records whose files have not cleared are left out.

Staff (administrators, editors, contributors) see every hit. A visitor cannot seed "more like this" from a record they may not see.

## Heratio settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `ahg_discovery_image_enabled` | `1` | Turns image search on or off |
| `ahg_discovery_image_embed_url` | gateway base | Override; ignored if it points at a raw node address |
| `ahg_discovery_image_embed_model` | `nomic-embed-vision-v1.5` | Image model name sent to the gateway |
| `ahg_discovery_image_text_model` | `nomic-embed-text` | Paired text model; empty means use `/embed/text` |
| `ahg_discovery_image_text_prefix` | `search_query: ` | Task prefix the text model expects |
| `ahg_discovery_image_collection` | `archive_images` | Qdrant collection |
| `ahg_discovery_image_min_score` | `0.30` | Lowest cosine score shown |
| `ahg_discovery_image_max_mb` | `25` | Largest file sent to the embedder |

: Heratio image search settings

## Running it

- `php artisan ahg:image-embed` embeds pending images: new, changed, or failed fewer than three times. The scheduler runs it as `image-embed-pending` every 10 minutes from 04:00 to 15:59 UTC, which is how newly ingested images become searchable whichever path created them (upload, ingest wizard, scanner, API).
- `php artisan ahg:image-embed --io=<id>` embeds one record's images again.
- `php artisan ahg:image-embed --recreate` drops the collection and the ledger and rebuilds everything. Use it once when the first real embedder goes live, because the old `archive_images` collection was built at 512 dimensions.
- The search page is `/discovery/image`. The JSON endpoint is `GET /discovery/image/search?q=...` or `?similar=<record id>`, and `POST /discovery/image/search` with an `image` file. The upload is used for the one search and is not stored.

## Standing up the GPU side (operator)

1. On `.78` or `.115` (not `.76`), run a small FastAPI service on the worker port that serves `POST /ai/v1/embed/image` (and `/embed/text` if the model has a text tower) as described above. With `transformers`, load `nomic-ai/nomic-embed-vision-v1.5` with `trust_remote_code=True`, take the CLS token of `last_hidden_state`, and L2-normalise it.
2. Register the node in the gateway database: add `"clip"` to that node's `gpu_nodes.capabilities.services`.
3. Check the route through the gateway with a small image, then run `php artisan ahg:image-embed --recreate` on each Heratio instance during GPU hours.

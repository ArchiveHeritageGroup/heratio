> Heratio Help Center article. Category: Digital Objects / Imaging.

# Image Animation (AI Image-to-Video)

Image Animation turns a still master image attached to an archival record into a short looping MP4 clip using an AI image-to-video model. Once an administrator enables the feature and points it at the image-to-video server, an **Animate image (AI)** button appears on archival record pages; curators click it to generate, replace, or remove a clip for that record. Settings, server health, and recent generations are managed at **Admin -> Image Animation Settings** (`/admin/image-ar/settings`). Heratio is only a client - the actual generation runs on a separate AI host that exposes an `/animate` HTTP endpoint, and Heratio stores the returned MP4 alongside the record's other digital objects.

## Overview

Image Animation is delivered by the `ahg-image-ar` package. It holds exactly one animation per archival record (information object). When a clip is generated, Heratio:

1. Finds the record's master image (a top-level `image/*` digital object).
2. Sends that image, plus the chosen model and parameters, to the image-to-video server.
3. Saves the returned MP4 under `uploads/ar/{record-id}/` in Heratio storage.
4. Records the generation in the `object_image_ar` table, including the model, prompt, seed, motion strength, file size, duration, and how long generation took.

Generating a new clip atomically replaces any previous one for that record (the old MP4 is deleted). Because heavier prompt-aware models need more GPU memory, the default model (`svd`) is image-only and works on smaller cards; prompt-aware models become available on larger GPUs.

## Key features

| Feature | Description |
|---|---|
| One-click animate | An **Animate image (AI)** button on the record page generates a clip from the master image |
| Model choice | `svd`, `svd-xt`, `cogvideox-2b`, or `wan-2.1` (the model must be loaded on the server) |
| Per-call overrides | Prompt, motion strength, frame count, fps, seed, and model can be overridden per generation |
| Atomic replace | Re-generating swaps the clip and removes the old MP4; one animation per record |
| Delete | Remove a record's animation and its MP4 |
| Server health panel | The settings page shows whether the AI server is reachable, the default and loaded models, CUDA status, low-VRAM mode, and GPU/VRAM info |
| Recent generations log | The last five clips with model, prompt, size, and generation time |
| Command line | `php artisan ahg:image-ar` for headless or scheduled generation |

## How to use

### For curators - animate a record's image

1. Confirm an administrator has enabled the feature and the **Animate image (AI)** button is switched on (see Configuration).
2. Open the archival record. It must have a master image (a top-level image digital object).
3. Click **Animate image (AI)**.
4. Wait for generation. On smaller GPUs this can take several minutes; on larger GPUs it is much faster.
5. On success, a notice reports the file size, time taken, and model used, and the MP4 becomes the record's animation. If the feature is disabled, no master image exists, or the server is unreachable, an error message explains why.

To replace an existing clip, generate again - the previous MP4 is removed automatically. To remove a clip entirely, use the delete action for that animation.

```
+------------------+     +-----------------------+     +-----------------------+
| Curator clicks   | --> | Heratio sends master  | --> | AI server returns MP4 |
| "Animate image"  |     | image + params to     |     | Heratio saves it under|
| on the record    |     | the image-to-video    |     | uploads/ar/{id}/ and  |
|                  |     | server /animate       |     | logs the generation   |
+------------------+     +-----------------------+     +-----------------------+
```

### For administrators - check server health

1. Go to **Admin -> Image Animation Settings** (`/admin/image-ar/settings`).
2. The **AI server health** card shows **reachable** or **unreachable**. When reachable it lists the default model, loaded models, whether CUDA is available, whether low-VRAM mode is on, and the GPU device with free/total VRAM.
3. If it is unreachable, confirm the **Server URL** is correct and that the image-to-video service is running on the AI host. Install steps are in `packages/ahg-image-ar/tools/video-server/INSTALL.md`.

## Configuration

All settings live in the `image_ar_settings` table and are edited at **Admin -> Image Animation Settings** (`/admin/image-ar/settings`), which is restricted to administrators.

### Feature toggles

| Setting | Key | Default | Meaning |
|---|---|---|---|
| Enable image animation | `ar_enabled` | `1` | Master switch for the feature |
| Show Animate button | `ar_user_button` | `1` | Whether the **Animate image (AI)** button appears on record pages |

The button only appears, and generation is only allowed, when both `ar_enabled` and `ar_user_button` are on.

### AI server

| Setting | Key | Default | Meaning |
|---|---|---|---|
| Server URL | `ar_server_url` | (set per install) | Base URL of the image-to-video server |
| Model | `ar_model` | `svd` | `svd`, `svd-xt`, `cogvideox-2b`, or `wan-2.1` (must be loaded on the server) |
| Request timeout | `ar_request_timeout` | `900` | Maximum seconds for one generation |

### Generation defaults

| Setting | Key | Default | Notes |
|---|---|---|---|
| Frames | `ar_num_frames` | `14` | SVD: 14 or 25; CogVideoX: up to 49 |
| FPS | `ar_fps` | `7` | Output framerate (SVD canonical is 7) |
| Motion bucket | `ar_motion_bucket_id` | `127` | SVD only; 1 = barely moves, 255 = strong motion (and more artifacts) |
| Seed | `ar_seed` | `0` | 0 = random per call; a positive value is deterministic |
| Default prompt | `ar_default_prompt` | empty | Used by CogVideoX/WAN; ignored by SVD |

### Model notes

- `svd` / `svd-xt` are image-only and ignore the text prompt.
- `cogvideox-2b` and `wan-2.1` are prompt-aware and need more GPU memory; the `ar_default_prompt` (or a per-call prompt) steers the motion.
- The model name you choose must already be loaded on the image-to-video server, which the health panel reports.

### Command line

`php artisan ahg:image-ar` generates a clip for one record outside the browser. Options:

| Option | Purpose |
|---|---|
| `--object-id=` | The information object id to animate (required unless `--health`) |
| `--model=` | Override the model (`svd`, `svd-xt`, `cogvideox-2b`, `wan-2.1`) |
| `--prompt=` | Text prompt (ignored by SVD) |
| `--frames=`, `--fps=`, `--motion=`, `--seed=` | Override generation parameters |
| `--force` | Re-render even if the record already has an animation |
| `--health` | Print the AI server health and exit |

The schema and settings tables are created and seeded automatically on first boot, so no manual install step is required.

## References

- Source: packages/ahg-image-ar/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/583

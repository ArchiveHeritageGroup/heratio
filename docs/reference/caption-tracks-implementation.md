# Caption Track Management Implementation Reference

**Issue:** heratio#757
**Package:** `packages/ahg-media-streaming/`
**Status:** Admin UI + storage + player auto-hydration shipped (v1.112+).

## Surface

| Component | Path |
|---|---|
| Controller | `src/Controllers/CaptionTrackController.php` (8 actions) |
| Service | `src/Services/CaptionTrackService.php` (8 methods, incl. SRT-to-VTT converter, remote URL fetch + cache) |
| Migration | `database/migrations/2026_05_27_000000_create_media_caption_track_table.php` |
| Views | `resources/views/caption-track/{index,form}.blade.php` |
| Player auto-hydration | `View::composer('theme::components.media-player', ...)` registered in `AhgMediaStreamingServiceProvider::boot()` |

## Database (`media_caption_track`)

```sql
id                BIGINT UNSIGNED PK
digital_object_id BIGINT UNSIGNED FK -> digital_object.id ON DELETE CASCADE
track_type        ENUM('caption','subtitle','description','chapters') DEFAULT 'subtitle'
label             VARCHAR(120)
language_code     VARCHAR(10)  DEFAULT 'en'
is_sdh            TINYINT(1)   DEFAULT 0
is_default        TINYINT(1)   DEFAULT 0
active            TINYINT(1)   DEFAULT 1
vtt_content       LONGTEXT     NULL
source_url        VARCHAR(500) NULL
created_at        TIMESTAMP
updated_at        TIMESTAMP
INDEX (digital_object_id)
INDEX (digital_object_id, active)
INDEX (digital_object_id, language_code)
```

## Routes

```
GET    /media-streaming/caption-tracks/{digitalObjectId}
GET    /media-streaming/caption-tracks/{digitalObjectId}/create
POST   /media-streaming/caption-tracks/{digitalObjectId}
GET    /media-streaming/caption-tracks/{digitalObjectId}/{trackId}/edit
PUT    /media-streaming/caption-tracks/{digitalObjectId}/{trackId}
DELETE /media-streaming/caption-tracks/{digitalObjectId}/{trackId}
POST   /media-streaming/caption-tracks/{digitalObjectId}/{trackId}/toggle
POST   /media-streaming/caption-tracks/{digitalObjectId}/{trackId}/fetch
GET    /media-streaming/captions/{trackId}              (public VTT serving)
```

## Player auto-hydration

The `theme::components.media-player` Blade component is invoked from several locked packages (`ahg-library`, `ahg-information-object-manage`, `ahg-core`). Rather than edit those callers, the AhgMediaStreaming service provider registers a View composer that:

1. Detects the `digitalObjectId` (or `digital_object_id`) prop on the component
2. Calls `CaptionTrackService::getActiveForPlayer($id)`
3. Injects the resulting tracks as `$tracks` if the caller didn't supply them

Result: the player picks up tracks automatically without modifying any caller view.

## Conversion (SRT to VTT)

`CaptionTrackService::create()` detects the upload extension. SRT files are converted server-side by:

1. Replacing the `,` thousands separator in timecodes with `.`
2. Stripping the SRT cue number lines (VTT doesn't require them)
3. Prepending the `WEBVTT` header

The VTT body is stored in `vtt_content` for inline playback; remote URLs go into `source_url` for on-demand fetch.

## Configuration

`config/ahg-media-streaming.php`:

| Key | Default | Purpose |
|---|---|---|
| `captions.max_upload_size` | `1MB` | Per-file upload cap |
| `captions.remote_fetch_timeout` | `15` | Remote URL fetch timeout (seconds) |
| `captions.allow_remote_urls` | `true` | Set false to disable remote-URL tracks |

## Acceptance vs heratio#757

- Admin UI to upload .vtt or .srt with kind/srclang/label/default flag - shipped
- `media_caption_track` storage table - shipped
- Player auto-populated from new table - shipped via view composer
- Help article for curators - shipped at `docs/help/caption-tracks-user-guide.md`

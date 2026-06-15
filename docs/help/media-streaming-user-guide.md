> Heratio Help Center article. Category: Digital Media.

# Media Streaming and Captions

Heratio streams audio and video digital objects directly in the browser, transcoding older or non-web formats on demand and adding caption, subtitle, audio-description and chapter tracks for accessibility. Players seek instantly because the stream honours HTTP Range requests, and caption tracks are injected into the media player automatically wherever a digital object is shown.

## Overview

When a record carries an audio or video digital object, the page embeds a standard HTML5 media player. Instead of pointing the player at the raw file, Heratio routes playback through a streaming endpoint. That endpoint does three things:

- Serves the file with byte-range support so viewers can scrub and seek without downloading the whole file.
- Detects formats that browsers cannot play natively (for example AVI, MOV, WMV, MKV, FLAC, WMA) and transcodes them once to MP4 (video) or MP3 (audio), caching the result for every later view.
- Hydrates the player with any active caption and subtitle tracks you have configured for that digital object.

Transcoding relies on `ffmpeg` and `ffprobe` being installed on the server. If they are absent, files already in a web-ready format (MP4, WebM, MP3, and similar) still stream normally; only the formats that need conversion are affected.

## Key features

- **Range-aware streaming** for smooth seeking in long audio and video (HTTP 206 partial content).
- **On-demand transcoding** of non-web video to H.264/AAC MP4 (with faststart for progressive playback) and non-web audio to MP3, cached so each file is converted only once.
- **Caption and subtitle management** per digital object, grouped into four track types: Caption, Subtitle, Audio Description, and Chapters.
- **SDH support** (Subtitles for the Deaf and Hard of Hearing), a default-track flag, and per-track active/inactive switching.
- **Inline or remote tracks**: paste WebVTT directly, or link an external `.vtt` or `.srt` file that Heratio fetches, converts to VTT, and caches locally.
- **Automatic player hydration**: active caption and subtitle tracks appear in the player wherever the digital object is rendered, with no per-page setup.

## How to use

### Playing media

Open any archival record that has an audio or video digital object. The embedded player streams through the route `GET /media-streaming/stream/{digitalObjectId}`. Press play and seek freely; the first play of a non-web format may take a moment while the file is transcoded and cached, after which playback is immediate for everyone.

### Adding a caption or subtitle track

You need to be signed in to manage tracks. The caption manager is reached at `/media-streaming/caption-tracks/{digitalObjectId}`, where `{digitalObjectId}` is the numeric ID of the digital object you want to caption.

1. Go to **`/media-streaming/caption-tracks/{digitalObjectId}`** to see the tracks already configured, grouped by type.
2. Click **Add Track** (or **Add your first track** if none exist). This opens the create form.
3. Choose a **Track type**: Subtitle, Caption, Audio Description, or Chapters.
4. Enter a short **Label** (for example "English", "English (SDH)", "isiZulu"). This is what viewers see in the player's track menu. If you leave it blank, Heratio fills it from the language you select.
5. Pick a **Language code** from the list.
6. Optionally tick **SDH** if the track includes speaker identification and sound descriptions, and tick **Default track** to have it selected automatically when the media loads.
7. Provide the caption data one of two ways:
   - Paste WebVTT directly into **Inline VTT content**, or
   - Enter a **Remote VTT or SRT URL** pointing to an external file. SubRip (`.srt`) files are converted to WebVTT automatically.
8. Click **Add Track**. You return to the track list with a confirmation.

### Editing, toggling, fetching and deleting tracks

From the track list each row has action buttons:

- **Edit** opens the same form to change any field (`/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/edit`).
- **Pause / Play** toggles a track active or inactive. Inactive tracks stay configured but are not loaded into the player.
- **Download** appears for remote-URL tracks that have not yet been cached. It fetches the external file now and stores the VTT inline, so playback no longer depends on the remote host being reachable.
- **Trash** deletes the track after a confirmation prompt.

### How tracks reach the player

You do not have to wire tracks into each record page. Active tracks of type Caption and Subtitle are injected into the media player automatically, ordered with the default track first. The player loads each one from the public endpoint `GET /media-streaming/captions/{trackId}`, which returns properly headed `text/vtt`. Audio Description and Chapters tracks are managed here but are not auto-loaded as on-screen subtitle tracks.

## Configuration

Media streaming has no settings screen of its own; it reads from existing Heratio configuration and the server environment.

- **Source files** are read from the configured uploads location (`heratio.uploads_path`). Each digital object resolves its own full path from there.
- **Transcoded cache** is written to `storage/app/transcoded/` (`{id}.mp4` for video, `{id}.mp3` for audio). Delete a cached file to force re-transcoding on the next view.
- **`ffmpeg` / `ffprobe`** must be on the server's path for transcoding and media probing. Without them, conversion-dependent formats will not play and the relevant request returns an error; web-ready formats are unaffected.
- **Caption tracks** are stored in the `media_caption_track` table, one row per track, holding the type, label, language code, SDH and default flags, active state, and either inline VTT content or a source URL.
- **Remote caption fetches** use a 15-second timeout. Cache remote tracks (the Download action) so playback does not depend on the external host at view time.

Video transcoding produces H.264 video with AAC audio and a relocated moov atom (faststart) for progressive playback. Audio transcoding produces variable-bitrate MP3 at roughly 190 kbps.

## References

- Source package: `packages/ahg-media-streaming/`
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/596

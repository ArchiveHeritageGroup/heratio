> Heratio Help Center article. Category: User Guide.

# Heratio - Caption & Subtitle Track Management: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issue:** heratio#757

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Track Types](#2-track-types)
3. [Adding a Track](#3-adding-a-track)
4. [Editing a Track](#4-editing-a-track)
5. [VTT vs SRT](#5-vtt-vs-srt)
6. [Player Behaviour](#6-player-behaviour)
7. [Configuration](#7-configuration)
8. [Accessibility](#8-accessibility)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Introduction

Every video and audio digital object in Heratio can carry one or more caption tracks. Tracks are managed independently of the media file - you can add, edit, and remove tracks without re-uploading the underlying file.

The player picks tracks up automatically: there's nothing to wire by hand on the show page. As soon as you save a track marked `active`, it appears in the player's track selector.

---

## 2. Track Types

| Type | Use case |
|---|---|
| `caption` | Speech, sound effects, and music cues (for deaf / hard-of-hearing viewers). Often labelled "SDH" (Subtitles for the Deaf and Hard-of-hearing). |
| `subtitle` | Translation of the audio dialogue into another language. |
| `description` | Audio descriptions for blind / low-vision viewers (rendered as text in players that support it). |
| `chapters` | Navigable chapter markers (jump-points inside a long video). |

The four types map directly to HTML5 `<track kind="...">` values.

---

## 3. Adding a Track

1. Open the digital object's edit page.
2. Click **Caption tracks** in the sidebar (visible only for video / audio types).
3. Click **Add track**.
4. Fill in:
   - **Label** - shown in the player's track selector (e.g. "English", "Afrikaans subtitles", "SDH").
   - **Language code** - ISO 639-1 (e.g. `en`, `af`, `zu`, `nso`).
   - **Type** - one of caption / subtitle / description / chapters.
   - **SDH flag** - tick if this is an SDH track.
   - **Default flag** - tick if this track should auto-display.
   - **Upload** - drop a `.vtt` file (preferred) or `.srt` (auto-converted on save).
   - OR paste VTT content directly into the textarea.
5. **Save**. The track is now live.

---

## 4. Editing a Track

From the track list:

- **Edit** - rename label, change language code, swap type, toggle SDH/default, upload a new file.
- **Active toggle** - hide the track from the player without deleting it. Useful when proofreading.
- **Delete** - permanent removal.

Multiple tracks per language are allowed (e.g. English standard + English SDH).

---

## 5. VTT vs SRT

Heratio stores all tracks in WebVTT format internally (HTML5 standard). When you upload a `.srt` file:

1. The server detects the format from the file extension and content.
2. SRT timecodes (`00:00:00,000 --> 00:00:02,500`) are converted to VTT timecodes (`00:00:00.000 --> 00:00:02.500`).
3. The VTT body is stored in `media_caption_track.vtt_content`.

If you have a remote VTT URL (e.g. CDN-hosted), paste it into the **Source URL** field instead - the server fetches it on demand and caches the response.

---

## 6. Player Behaviour

The media-player component checks the `media_caption_track` table for every digital object and:

- Renders one `<track>` element per active track.
- Sets `default` on the track flagged as default.
- Sets `kind` based on the track type.
- Sets `srclang` from the language code.

Switching tracks live in the player does not require a page reload.

---

## 7. Configuration

`config/ahg-media-streaming.php`:

| Key | Default | Purpose |
|---|---|---|
| `captions.max_upload_size` | 1 MB | Per-file upload cap |
| `captions.remote_fetch_timeout` | 15 s | Remote URL fetch timeout |
| `captions.allow_remote_urls` | true | Set false to disable remote-URL tracks |

---

## 8. Accessibility

Captions / subtitles / descriptions are WCAG 2.1 Level A (1.2.2 Captions / 1.2.3 Audio Description) and Level AA (1.2.4 Captions Live / 1.2.5 Audio Description) requirements.

- Always provide at least one caption track for any video that includes speech.
- For South African public-sector content, the Promotion of Access to Information Act may impose additional requirements.
- For EU content, Web Accessibility Directive (EU 2016/2102) applies.

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Track not appearing in player | Active flag unticked | Toggle active on the track row |
| Wrong language showing as default | More than one default per language | Edit and untick default on the duplicates |
| Garbled characters | SRT file in Windows-1252 encoding | Re-save the SRT as UTF-8 before upload |
| Cue text missing | VTT missing `WEBVTT` header | Open the file in a text editor and ensure first line is `WEBVTT` |
| Remote URL track fails | CORS or 4xx from upstream | Switch to inline `vtt_content` upload |

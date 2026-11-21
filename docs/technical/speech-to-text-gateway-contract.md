# Speech-to-text gateway contract (voice search fallback)

Heratio's voice search runs speech recognition in the browser where the browser has the Web Speech API (Chrome, Edge, Safari). Firefox and some other browsers do not have it. For those, and for users who switch on "Use server speech recognition", Heratio records a short clip and asks the AHG AI gateway to transcribe it (heratio#1767).

This page is the contract the gateway has to meet. It follows the OpenAI-compatible `audio/transcriptions` shape, so a standard Whisper server behind the gateway (for example faster-whisper or whisper.cpp with an OpenAI-compatible front end) fits without a custom adapter.

Status on 20 November 2026: the Heratio side is built and tested with a faked gateway. **No gateway node runs a speech-to-text service yet**, so the route below does not exist on `ai.theahg.co.za` today. Until it does, Heratio shows the user "Voice search is not available right now; please type your search" and logs one warning, as described under Failure handling.

## Route

| Item | Value |
| --- | --- |
| Method | `POST` |
| URL | gateway origin + `voice_stt_path`; default `https://ai.theahg.co.za/ai/v1/audio/transcriptions` |
| Auth | `Authorization: Bearer <gateway key>`, the same `ahg_live_*` key the other AI services use (`AiServicesSettings::gatewayKey()`) |
| Request body | `multipart/form-data` |
| Response | `application/json` |

: Speech-to-text route

The gateway origin is taken from the AI Services API URL setting (`ai_services_api_url`, default `https://ai.theahg.co.za/ai/v1`). `voice_stt_path` must be a path; a full URL there is ignored and the default path is used, so the audio cannot be pointed at a GPU node directly.

## Request fields

| Field | Required | Content |
| --- | --- | --- |
| `file` | yes | The recording. Filename `speech.webm` (WebM container, Opus audio) or `speech.ogg` (Ogg container, Opus audio). At most `voice_stt_max_kb` (default 1024 KB) and `voice_stt_max_seconds` (default 15 s). |
| `model` | yes | The value of `voice_stt_model`, for example `whisper-large-v3`. |
| `language` | no | ISO 639-1 code from the user's interface locale (`en`, `af`, `fr`, `pt`, `sw` ...). Sent only when the locale is one Whisper supports; otherwise left out and the model detects the language. isiZulu, isiXhosa and Sesotho are not Whisper languages, so for those locales no `language` is sent. |
| `response_format` | no | Always `json`. |

: Request fields

Example:

```
curl -X POST https://ai.theahg.co.za/ai/v1/audio/transcriptions \
  -H "Authorization: Bearer $KEY" \
  -F file=@speech.webm -F model=whisper-large-v3 -F language=en -F response_format=json
```

## Response

Success is HTTP 200 with a JSON object carrying `text`:

```json
{ "text": "search for maps" }
```

Other fields (`language`, `duration`, `segments`) are allowed and ignored. Heratio trims the text and returns it to the browser, which strips leading and trailing punctuation ("Search for maps." becomes "search for maps") and runs it through the same command matcher as browser speech.

An empty `text` is a valid answer: the user sees "No speech detected".

## Failure handling on the Heratio side

| Gateway answer | What the user gets | Heratio HTTP status |
| --- | --- | --- |
| 404 (route not deployed), any 4xx or 5xx | "Voice search is not available right now; please type your search" | 503 |
| Connection error or no answer within `voice_stt_timeout` (default 20 s) | same message | 503 |
| 200 without a string `text` | same message | 503 |

: Gateway failures and what the user sees

A failure never becomes a 500. Each kind of failure writes one `[voice-stt]` warning to the Laravel log, then stays quiet for 10 minutes, so an outage does not log every search.

## Heratio endpoint (for reference)

- `GET /voice/stt/config` - whether the fallback is on for this visitor, the limits, and the translated messages.
- `POST /voice/stt` - multipart `audio`, CSRF token required. Returns `{ "ok": true, "text": "..." }`, or `{ "ok": false, "error": "...", "message": "..." }` with 404 (switched off or no model set), 401 (sign-in required by `voice_stt_require_auth`), 422 (missing, too large, or not WebM/Ogg), 429 (more than `voice_stt_rate_limit` per minute per user or IP), or 503 (gateway unavailable).

## Privacy

Recordings are not stored. The browser holds the clip in memory until it is uploaded; Heratio passes it to the gateway and deletes the temporary upload file when the request ends. The speech-to-text endpoint writes no audio and no transcript to Heratio's database or logs; its log warnings carry only the failure reason and the gateway URL. Once the text has become a search, that search is recorded exactly like a typed one (the audit trail's normal search entry). The gateway service should do the same: transcribe and discard, and not keep the audio.

## What the gateway team needs to stand up

1. A Whisper service (for example `whisper-large-v3` via faster-whisper) on `192.168.0.76` (RTX 3090 24 GB), the preferred strong node.
2. A gateway route `POST /ai/v1/audio/transcriptions` that checks the API key and `gateway` scope, meters the call, and forwards the multipart body to that service.
3. The model name registered in the gateway, so `model=whisper-large-v3` resolves.
4. WebM/Opus and Ogg/Opus accepted (ffmpeg decodes both; faster-whisper reads them through PyAV).

Then an administrator sets **Transcription Model** under Settings > Voice & AI > Server Speech-to-Text, and the fallback goes live. No Heratio release is needed for that step.

## Settings

| Key | Default | Meaning |
| --- | --- | --- |
| `voice_stt_enabled` | `true` | Master switch. Works only once a model is set. |
| `voice_stt_model` | empty | Model name; empty keeps the fallback off. |
| `voice_stt_path` | `/ai/v1/audio/transcriptions` | Path on the gateway. |
| `voice_stt_require_auth` | `false` | Signed-in users only. |
| `voice_stt_max_seconds` | `15` | Longest recording (3 to 60). |
| `voice_stt_max_kb` | `1024` | Largest upload (64 to 10240). |
| `voice_stt_rate_limit` | `10` | Recordings per minute per user, or per IP for visitors. |
| `voice_stt_timeout` | `20` | Seconds before giving up on the gateway (3 to 120). |

: voice_stt settings (group voice_ai), seeded by database/migrations/2026_11_20_200000_voice_stt_settings.php

Code: `packages/ahg-core/src/Services/VoiceSttService.php`, `packages/ahg-core/src/Controllers/VoiceSttController.php`, `public/vendor/ahg-theme-b5/js/voiceSttFallback.js`. Tests: `tests/Feature/VoiceSearchSttTest.php`.

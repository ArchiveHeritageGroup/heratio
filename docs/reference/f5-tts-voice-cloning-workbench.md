# F5-TTS Voice Cloning Service (AHG Workbench Audio)

Operational reference for the F5-TTS voice-cloning service that powers the AHG Workbench's audio / narration features, including cloned voices such as `f5:johan`.

## What it is

A single FastAPI process ("F5-TTS HTTP service") that renders text to speech in registered reference voices. It runs on the cluster's GPU node (workhorse3, RTX 3090), installed at `/opt/f5-tts` with its own Python venv, on port **7860**. It is a direct internal node service - it is NOT behind the `ai.theahg.co.za` gateway.

## Service management

- systemd unit: **`f5-tts.service`** (WorkingDirectory `/opt/f5-tts`, runs `uvicorn app.server:app` bound to the node's LAN address, port 7860).
- The model loads **lazily on the first `/tts` or `/healthz` call** - expect ~30-60s on the first request after a (re)start, then ~1-3s per 10s of audio.
- `systemctl {status,restart,enable} f5-tts` to manage it.

## HTTP API

- `GET  /voices` - list registered voices (`demo`, `johan`, ...).
- `POST /tts` - JSON `{ "voice_id": "johan", "text": "..." }` -> `audio/wav` bytes.
- `POST /voices/{voice_id}` - form-data `file=<ref.wav>`, `ref_text=<transcript>` to register a new cloned voice.
- `GET  /healthz` - readiness (also warms the model).
- `GET  /version` - package + model versions.

Voice ids are surfaced to the Workbench as `f5:<voice_id>` (e.g. `f5:johan`).

## Workbench integration

- Config: `env.F5_TTS_BASE_URL` in the Workbench API (`api/src/lib/env.ts`).
- Dispatch: `audioGenerator.ts` routes any voice starting with `f5:` to F5-TTS via `synthesiseLineF5` (POST `/tts`), OpenAI voices otherwise.
- Public helper: `synthesiseSpeech(text, 'f5:johan')` returns MP3 bytes - the clean way to render **verbatim** narration.

### Important: the Audio Overview UI is NOT verbatim TTS

The Workbench "Audio Overview" feature is a NotebookLM-style generator: an LLM writes its own short two-voice summary from source documents, then TTS renders that. It will **not** speak a supplied script word-for-word, and it comes out short/generic. To narrate an exact script in a cloned voice, call `synthesiseSpeech(text, 'f5:johan')` directly (a one-off `tsx` script that reads the text file and writes the mp3 is the simplest path).

## Failure mode seen (2026-07-05)

Symptom: Workbench audio produced a short, flat clip that was "not really doing anything". Root cause: **F5-TTS was down** - the service was installed but the systemd unit was disabled and not running, so `POST /tts` failed with `ECONNREFUSED` on port 7860 and the Workbench fell back to a default path. Fix: `systemctl enable --now f5-tts` (the unit already existed and binds the correct LAN address). If Workbench voices ever go silent/short again, check `systemctl is-active f5-tts` and that port 7860 is listening first.

Verified 2026-07-05.

# Server-GPU pixel-streaming for the exhibition walkthrough - evaluation (#1154)

Assessment of rendering very heavy / photoreal exhibition scenes on a **server GPU** and
streaming the result as video to any browser (NVIDIA Omniverse, Unreal Pixel Streaming, or a
generic WebRTC pipeline). Rendering-performance track of the #1145 digital-twin roadmap.

## TL;DR / recommendation

**Defer.** Pixel-streaming costs roughly **one GPU per concurrent viewer**, so it does not
fit a public exhibition with many simultaneous visitors - the just-shipped **WebGPU
renderer** (#1153) + **downsampled point clouds** (#1183) already render the current content
client-side, for **unlimited concurrent visitors at zero server-GPU cost**. Pixel-streaming
is only justified for a **narrow case**: a single kiosk or low-concurrency, curated
*console-quality / photoreal* experience that weak clients genuinely cannot render. Revisit
only when such a requirement is concrete; then prefer **Unreal Pixel Streaming** (free,
mature) or **Omniverse Kit App Streaming** (if RTX path-traced photoreal is the point), on a
**dedicated** GPU - not one shared with the AI inference gateway.

## Why it's a poor fit for the common case

| | WebGPU (client-side, shipped) | Server-GPU pixel-streaming |
|---|---|---|
| Concurrent visitors | Unlimited (each browser renders) | ~1 GPU per viewer (or a few) |
| Server GPU cost | None | One RTX session per viewer |
| Scene ceiling | Client GPU (good on modern devices) | Server GPU (console-quality / path-traced) |
| Weak/old clients | Limited by their GPU | Only needs to decode video |
| Latency | Local (none) | Encode + network + decode round-trip |
| Infra | A CDN script | Engine + NVENC + WebRTC + signalling + session orchestration |
| Input | Native | Keyboard/mouse/pointer-lock round-tripped to server |

The economics are the crux: a public GLAM walkthrough wants **many** concurrent web
visitors. Client-side WebGPU serves them all for free. Pixel-streaming would need N GPUs for
N viewers - infeasible except for a single kiosk or a handful of curated sessions.

## The AHG-specific constraint: GPU contention with the AI gateway

The AHG GPU nodes (RTX 3090 / 3080 / 3070) are **already managed by the AI gateway's GPU
allocation + preemption scheduler** (`/opt/ahg-ai/gateway`, over Postgres `ahgai`) for LLM /
HTR / NER / TTS inference. A pixel-streaming session is a **long-lived, GPU-pinned** workload
(it holds a GPU for the whole walkthrough), which would:

- contend with inference for the same cards (the scheduler preempts GPUs - a streaming
  session being preempted mid-walkthrough is unacceptable), and
- consume NVENC encoder sessions (consumer RTX cards historically cap concurrent NVENC
  sessions - another hard limit on concurrent viewers).

So any pixel-streaming pilot needs a **dedicated GPU** carved out of the gateway pool (or new
hardware), with the gateway scheduler taught to treat it as reserved. This is a real
operational cost, not just app code.

## Candidate stacks

1. **Unreal Engine Pixel Streaming** - UE renders, NVENC encodes H.264/H.265, WebRTC to the
   browser via the Signalling/Web server (cirrus). Free engine; mature; well-documented
   Linux + container path. Cost: the scene must be built/loaded in **Unreal** (a generic UE
   "glTF/USD viewer" app, or a per-exhibition project) - a separate content pipeline from our
   three.js walkthrough. Best balance of quality, cost, and maturity for a pilot.
2. **NVIDIA Omniverse Kit App Streaming** - renders **USD** scenes with RTX path tracing
   (the highest photoreal quality), streams via WebRTC. Needs an Omniverse Kit app, a USD
   pipeline, RTX GPUs, and enterprise licensing review. Justified only if true RTX photoreal
   is the explicit requirement.
3. **CloudXR** - streams to **VR headsets** specifically. Tangential here (walkthrough VR was
   parked in #1184); only relevant if remote high-end VR becomes a goal.
4. **Generic headless three.js + WebRTC** - run our existing scene headless on a server GPU
   (Dawn/WebGPU-node or headless-GL), capture + NVENC encode + WebRTC + signalling. Reuses
   our content, but we'd build the entire streaming/encode/transport/input stack ourselves
   and the quality ceiling is still our three.js scene - i.e. most of the cost of (1) for
   little of the photoreal upside. Not recommended.

## Architecture sketch (if/when piloted)

Browser (video + data channel for input)  <--WebRTC-->  Signalling server  <-->  GPU node:
engine (UE/Omniverse) renders -> NVENC H.264/265 -> WebRTC media. A **session broker** asks
the (extended) gateway scheduler for a *reserved* GPU, launches a container per session, and
tears it down on disconnect. Heratio would embed the stream in an `<iframe>`/video element on
a dedicated route (mirroring how the #1156 360/Matterport embed is surfaced), with a hard
**concurrent-session cap** and a graceful "all streaming slots busy - use the standard 3D
walkthrough" fallback to the WebGPU page.

## Minimal proof path (deferred - do only on a concrete need)

1. One **dedicated** RTX node, reserved out of the gateway pool.
2. **Unreal Pixel Streaming** sample container + a generic glTF-loading UE viewer; stream one
   exhibition GLB; measure quality, latency, and GPU/NVENC headroom for 1-3 concurrent
   sessions.
3. A tiny session broker (launch/teardown + cap) and a Heratio embed route behind a feature
   flag, defaulting to the WebGPU walkthrough.
4. Decide from real numbers whether the narrow kiosk/curated case justifies the ongoing GPU
   cost.

## Decision

Pixel-streaming is **not** adopted now. WebGPU (#1153) + point clouds (#1183) cover the
current need at zero server-GPU cost and unlimited concurrency. Keep #1154 open as a
**documented evaluation**; pursue the proof path above only when a specific console-quality /
photoreal, low-concurrency (kiosk/curated) requirement appears, and only on a GPU reserved
away from the inference gateway.

## Related

- `docs/reference/webgpu-walkthrough-evaluation.md` - the client-side renderer (the default).
- `docs/reference/exhibition-photoreal-capture.md` - scan shells + point clouds (#1156/#1183).
- #1145 digital-twin roadmap umbrella; #1153 WebGPU; #1183 point clouds; #1184 walkthrough VR.

# Digital twin: server-GPU pixel-streaming evaluation (#1154)

**Status:** Evaluation complete - recommendation below. No build undertaken.
**Date:** 2026-06-11
**Track:** Rendering-performance track of #1145; sibling of #1153 (WebGPU renderer upgrade).

## Question

Should the exhibition digital twin render very heavy / photoreal scenes on a
server GPU and stream the result as video to the browser (pixel streaming),
rather than rendering in the client's browser? Candidate stacks: Unreal Engine
Pixel Streaming and NVIDIA Omniverse / CloudXR. The draw is console-quality
visuals on weak clients (old laptops, phones, kiosks) that cannot run a heavy
WebGL/WebGPU scene locally.

## Options compared

1. **Client-side render (today + #1153).** The scene runs in the visitor's
   browser via three.js (WebGL today, WebGPU under #1153). The server ships
   geometry/textures once; the client does all the rendering.
2. **Server-GPU pixel streaming.** A GPU on the server renders each viewer's
   session and streams H.264/H.265/AV1 video + receives input events. The
   browser is a thin video client.
3. **Hybrid.** Client-side by default; an opt-in "cinematic" server-rendered
   mode for a small set of flagship/very-heavy scenes.

## Evaluation criteria and findings

| Criterion | Client-side (WebGPU, #1153) | Server-GPU pixel streaming |
|---|---|---|
| Visual fidelity ceiling | High - PBR, large scenes, post FX; not film-render | Highest - path-traced / Nanite-class, console quality |
| Client requirements | A modern browser + a capable-enough GPU | Almost none - just video decode (works on weak clients) |
| Concurrency / cost | Scales to many viewers at ~zero marginal server cost | One GPU context (or a shared slice) per concurrent viewer - cost scales linearly with audience |
| Latency sensitivity | Local - no network round-trip per frame | Interactive video - needs low-latency encode + good network; sensitive to RTT and jitter |
| Bandwidth | One-time asset download, then local | Continuous multi-Mbps video per viewer for the whole session |
| Infra complexity | Static asset hosting + a JS bundle | GPU session orchestration, signalling/TURN, autoscaling, encode pipeline - heavy |
| Fit with AHG GPU nodes | None needed | Contends with the AI gateway's GPU nodes, which are scheduled for inference with preemption (see the gateway GPU-allocation plane). Co-locating pixel-streaming sessions would compete with inference for the same VRAM/SMs. |
| Browser support | WebGPU now broad (Chromium, Safari, Firefox) with WebGL fallback | Universal (any browser that decodes video) |
| Fallback story | Graceful (WebGPU -> WebGL -> static) | All-or-nothing per session |

## Key conclusions

- **WebGPU (#1153) lifts the client-render ceiling substantially at near-zero
  marginal cost and scales naturally to many concurrent viewers.** For the
  large majority of digital-twin scenes it closes most of the fidelity gap
  without any per-viewer server GPU.
- **Server-GPU pixel streaming buys console-quality on weak clients, but the
  economics are per-concurrent-viewer.** A ticketed multi-user opening (#1192)
  with N simultaneous visitors needs ~N GPU slices for the duration. On the
  shared AHG GPU fleet that directly competes with the AI gateway's inference
  scheduling, which is the higher-value, revenue-bearing use of those nodes.
- The infrastructure (signalling, TURN, session autoscaling, low-latency
  encode, GPU lifecycle) is a standing operational system, not a feature you
  ship once. It is only justified by a concrete, funded need.

## Recommendation

1. **Make WebGPU (#1153) the default path** for heavy scenes. It is the right
   first investment and removes most of the motivation for pixel streaming.
2. **Do not build server-GPU pixel streaming now.** Keep it as a documented,
   opt-in "cinematic mode" option for a narrow set of flagship / very-heavy
   scenes, to be revisited only when ALL of these are true:
   - a specific client needs console-quality on devices that cannot run WebGPU;
   - there is budget for per-concurrent-viewer GPU time; and
   - the audience size is bounded and known (e.g. a ticketed event under #1192).
3. **If it is ever built, it must go through the gateway GPU scheduler** as a
   low-priority, preemptible workload so live inference is never starved - never
   pin a node to a streaming session outside that scheduler.

**Decision:** #1154 is resolved as "evaluated - defer build; prioritise #1153
(WebGPU); revisit pixel streaming only against the trigger conditions above."
This document is the deliverable; no code was changed.

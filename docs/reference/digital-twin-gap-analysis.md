# Digital Twin Gap Analysis — Heratio vs Wessels (2025) Blueprint

**Summary:** Heratio's exhibition "digital twin" began as a strong *virtual model* (3D
visualisation of exhibition spaces). As of June 2026 it now also has the **defining
digital-twin loop** - a live data link (sensor/visitor readings -> 3D state overlay),
conservation simulation and light-dose prediction, a per-room analytics dashboard, and
in-twin AI recommendation - so it meets the thesis's section-6.5 test (a virtual
representation linked to a physical entity through real-time data, enabling monitoring,
simulation and prediction). What remains is the *social/immersive/federated* reach
(multi-user, XR, cross-institution interoperability) and cyber hardening. This note
compares Heratio against the digital-twin blueprint in Lizette Wessels' 2025 UNISA PhD
thesis *"Digital Twin Information Hub: Possibilities for the Future of Information Sharing
in Metaversities"* (supervisor Prof L Jacobs) and gives a prioritised roadmap to close
the gap. Heratio is GLAM, not a library information hub, so the work is reframed as a
**Collection / Exhibition Digital Twin** — but the architecture layers map almost 1:1.

## The thesis blueprint (Table 7.1)
The thesis proposes an AI-enhanced *digital twin information hub* with these layers:

1. Vision / mission / objectives — collaborative, equitable information-sharing ecosystem.
2. Digital leadership — business model, digital transformation, culture, staff upskilling, digital footprint.
3. Functionalities — stakeholder/consortium partnerships, digital learning and research support.
4. Technical architecture — scalable cloud infra; immersive UI with real-time engagement; security/privacy/auth; AI/ML + analytics + AI discovery and recommendation; and the **digital-twinning process**.
5. Information content management — policies, accessibility, digitisation and curation, data quality and governance, interoperable data flow.

The thesis's rigorous definition of a digital twin (section 6.5): a virtual
representation **bidirectionally linked to a physical entity through real-time data**,
enabling **monitoring, simulation and prediction**.

## Gap assessment

| Blueprint layer | Heratio status | Gap |
|---|---|---|
| Curation / content / digitisation | Strong (ISAD, RiC, Spectrum, IIIF, media pipeline) | — |
| Security / privacy / governance | ACL, ODRL rights, provenance, redaction, POPIA/GDPR modules | encryption / cyber hardening only |
| Scalable infra + UI | Laravel + Three.js walkthrough (desktop + mobile), builder, plan editor | — |
| AI supporting tech | HTR / NER / condition via the AI gateway, KM RAG | **CLOSED** - in-twin AI recommendation (#1149) shipped (LlmService over the gateway) |
| Immersive UX | 3D first-person + touch | no WebXR / VR headset support (#1152 open) |
| Real-time twin link + simulation + prediction | **CLOSED** - live readings overlay (#1146) + conservation/visitor simulation + light-dose prediction (#1147) | the defining digital-twin loop now exists |
| Multi-user / metaverse presence | single-user | no avatars / shared sessions / live docent (#1150 open) |
| Interoperability / FAIR / federation of the twin | F3 federation, RiC, IIIF | twin not yet exposed via 3D/linked-data standards or shared across consortia (#1151 open) |
| Twin analytics / continuous improvement | **CLOSED** - per-room time-series analytics dashboard (#1148, Chart.js) | feeds continuous improvement (thesis Objective 6) |
| Encryption / cybersecurity hardening | ACL + ODRL + provenance + redaction | encryption-at-rest / cyber hardening of the twin still open (thesis Objective 5, no issue yet) |

## Roadmap to a full digital twin (highest-leverage first)

### Shipped (June 2026) - the defining twin loop now exists
1. **Live data link (model -> twin).** [DONE #1146] `ahg_exhibition_reading` table + readings API; per-room conservation status (lux vs target, temp 16-24C, RH 40-60%); 3D Live overlay colour-tints each room green/amber/red and a HUD reads the current room. This is what elevates "model" to "twin."
2. **Simulation and prediction.** [DONE #1147] Conservation light-budget forecast (30-day avg lux -> annual dose vs ICOM tier budgets, days-to-budget, risk), visitor what-if simulator, demo readings seeder.
3. **Twin analytics dashboard.** [DONE #1148] Per-room time-series (lux/temp/humidity/visitors) bucketed hour/day, Chart.js dashboard, period selector, summary stats - feeds continuous improvement (thesis Objective 6).
4. **AI recommendation in-twin.** [DONE #1149] "You might also like" object suggestions via title-token similarity + optional gateway-LLM reasons (LlmService); in-walkthrough chips fly the visitor to the suggested object.

5. **Multi-user presence + live docent.** [DONE #1150] Shared walkthrough via HTTP polling: named avatars updated ~2-3x/sec, People panel; logged-in staff run a guided tour (start/stop, follow-the-docent camera tether, auto-spotlight on object open, message banner). No websocket - polling only; voice deferred.
6. **Immersive XR.** [DONE #1152] WebXR VR button (shown only when supported), room-scale head tracking, left-stick move / right-stick turn; `setAnimationLoop` drives desktop + headset.
7. **Audio docent (AI).** [DONE] Hold T + click an object to hear its description read aloud (browser speech); metadata-less objects get an on-the-fly AI description via the gateway (cached).
8. **Interoperability exports.** [DONE #1151] Public, CORS, open-standard exports: IIIF Presentation 3.0 manifest, open 3D scene JSON, schema.org `ExhibitionEvent` JSON-LD, plus an iframe embed snippet. (Publish-in-standards; *live* cross-institution F3 federation is the follow-up.)
9. **Authored audio guided tours.** [DONE] Curator-built multi-tour routes (object + narration + dwell; AI-draftable) stored per space; visitor Play flies stop-to-stop and the guide speaks each script; pause/stop, tour picker, **mobile big-Play quick-launch**. Browser TTS with a **voice selector**; natural neural TTS via the gateway tracked in #1168.
10. **Walkthrough UX batch.** [DONE #1163 zoom (Z), #1164 torch (F), #1165 wall graffiti, #1166 right-click help, #1167 force-AI describe (G)]; plus default ceilings + cornices, click-a-door-to-enter, two-sided interior walls, crouch/stand. Large-texture (ceiling) fix via CanvasTexture.

### Outstanding (still open) - infrastructure / migration
- **Live F3 federation.** [#1155] Consume the #1151 exports across institutions via the (locked) F3 layer. The remaining half of "federate."
- **Natural neural narration voice.** [#1168] TTS engine (e.g. Piper) behind the gateway + a `/ai/v1/tts` route + Heratio proxy + client playback; falls back to browser voice. Today narration uses browser `speechSynthesis`.
- **Photoreal scan import.** [#1156] glTF / point-cloud / Matterport room backdrops - the one capability the proprietary tools (Matterport, Weiss) have that Heratio lacks.
- **Landing-page promotion.** [#1157] Feature exhibitions with "Enter in 3D" on the home page (locked landing view).
- **Encryption / cyber hardening.** Data anonymisation, access limits, encryption-at-rest for the twin (thesis Objective 5 + STEEP 6.4.2.3). No issue filed yet.
- **Engineering scale (beyond the thesis).** WebGPU renderer [#1153] (needs a three.js r128 -> r16x ESM migration) and server-GPU pixel-streaming [#1154] for heavy scenes. Both are deliberate migrations, not patches.
- **Live docent voice.** WebRTC push-to-talk for the live docent (Phase 3 of #1150) + a Reverb websocket upgrade if polling load grows.
- **Blockchain trust layer.** Listed in the thesis (Table 7.1 6.4) but deliberately not adopted; Heratio instead uses AI-inference provenance (ADR-0002). Flagged for discussion, not committed.

## Current digital-twin capabilities (baseline, as built)
Exhibition Space digital twin (`packages/ahg-exhibition/`): drag-and-drop builder; first-person 3D walkthrough (multi-room buildings, building plan editor, polygon room footprints, manual + auto doorways with destination labels, per-room ceilings/wall paintings/floorplans/heights/rotation, corridor objects, building minimap, mobile controls); objects rendered as 3D models / images / PDFs linked to their archival records.

## Positioning note
Reframe as a "Collection / Exhibition Digital Twin," not an "information hub." The live
data link + simulation were the pieces that justify the "digital twin" name (now shipped);
multi-user, XR and interoperability deepen reach and collaboration (also shipped). What
remains is infrastructure: live F3 federation, encryption hardening, WebGPU/pixel-streaming
and tour voice.

Source: Wessels, L. (2025). *Digital Twin Information Hub: Possibilities for the Future of
Information Sharing in Metaversities.* DPhil Information Science, University of South Africa.

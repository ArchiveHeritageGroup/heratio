# Digital Twin Gap Analysis — Heratio vs Wessels (2025) Blueprint

**Summary:** Heratio's exhibition "digital twin" is today a strong *virtual model* (3D
visualisation of exhibition spaces), but not yet a *true digital twin* in the academic
sense, because it has no live bidirectional data link to a physical space. This note
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
| AI supporting tech | HTR / NER / condition via the AI gateway, KM RAG | no in-twin recommendation / personalisation |
| Immersive UX | 3D first-person + touch | no WebXR / VR headset support |
| Real-time twin link + simulation + prediction | none | the defining digital-twin feature is missing |
| Multi-user / metaverse presence | single-user | no avatars / shared sessions / live docent |
| Interoperability / FAIR / federation of the twin | F3 federation, RiC, IIIF | twin not exposed via standards / consortia |
| Twin analytics / continuous improvement | reports module | no twin-specific metrics (dwell, paths, popularity) |

## Roadmap to a full digital twin (highest-leverage first)

1. **Live data link (model -> twin).** Feed live or periodic physical-space data into the 3D view: visitor counts, conservation sensors (light lux, temperature, humidity) per room/case, object on-display vs in-storage state, loan status. Colour-code rooms/objects by live state. This is what elevates "model" to "twin."
2. **Simulation and prediction.** Visitor-flow simulation, cumulative light/UV exposure prediction for conservation, capacity and heat-map forecasting. Heratio already stores capacity and lighting-lux targets per space — a natural hook.
3. **Twin analytics dashboard.** Dwell time, walked paths, most-viewed objects, doorway usage; feeds continuous improvement.
4. **AI recommendation in-twin.** "Related objects / suggested next room" via KM RAG + embeddings; personalised tours.
5. **Multi-user presence.** Shared walkthrough sessions, a live curator/docent avatar, synchronous guided tours (WebSocket / WebRTC).
6. **Immersive XR.** WebXR so the existing Three.js scene works in VR headsets.
7. **Interoperate / federate.** Expose spaces and 3D via standards (IIIF 3D / glTF, linked data, OAI) and share across institutions (ties into F3 federation).

## Current digital-twin capabilities (baseline, as built)
Exhibition Space digital twin (`packages/ahg-exhibition/`): drag-and-drop builder; first-person 3D walkthrough (multi-room buildings, building plan editor, polygon room footprints, manual + auto doorways with destination labels, per-room ceilings/wall paintings/floorplans/heights/rotation, corridor objects, building minimap, mobile controls); objects rendered as 3D models / images / PDFs linked to their archival records.

## Positioning note
Reframe as a "Collection / Exhibition Digital Twin," not an "information hub." Roadmap
items 1 and 2 (live data + simulation) are the genuinely missing pieces that justify the
"digital twin" name; items 3-7 deepen reach and collaboration.

Source: Wessels, L. (2025). *Digital Twin Information Hub: Possibilities for the Future of
Information Sharing in Metaversities.* DPhil Information Science, University of South Africa.

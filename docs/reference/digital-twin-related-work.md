# Digital Twin / Virtual Museum - Related Work and Competitive Landscape

Survey of comparable virtual-museum / exhibition digital twins (live experiences,
commercial tooling, and open-source projects), and how the Heratio exhibition twin
differs. Compiled 2026-06-05 to feed KM and the joint paper with Wessels & Jacobs
(see `digital-twin-thesis-paper.md`) as a related-work / competitive section.

## Visit-now experiences (browser, no headset)

- **Kremer Collection Virtual Museum** - the closest match to Heratio's concept: a
  curated 3D gallery you walk through, 74 Old Masters across 5 rooms, each object with
  audio, deep-zoom and a 3D/AR view. https://virtualmuseum.thekremercollection.com/
- **Smithsonian National Museum of Natural History - virtual tours** - room-by-room
  navigation of the real building via map + arrows.
  https://naturalhistory.si.edu/visit/virtual-tour
- **British Museum - Museum of the World** - interactive object/timeline experience;
  plus a "Boulevard" Meta Quest app for full-gallery VR.
  https://britishmuseum.withgoogle.com/
- **Cranbrook Art Museum - 3D virtual exhibition tours** - Matterport scans of real
  exhibitions. https://cranbrookartmuseum.org/3d-virtual-exhibition-tours/
- **Google Arts & Culture** - Street View + some 3D for hundreds of museums (Louvre,
  Met, etc.). https://artsandculture.google.com/
- Other notable twins cited in the literature: Deutsches Museum (point-cloud twin),
  Kyoto National Museum Meiji Kotokan web twin, Dunhuang Academy 1:1 Cave 285 replica,
  Palace Museum Online Ceramics Gallery.

## Commercial / industry tooling (what GLAMs deploy)

- **Matterport** - 3D-scan "digital twins" of physical galleries. A *visual* twin
  (photoreal), generally no live data link. Widely used by museums.
- **Weiss AG** - 360 / VR museum & gallery digital twins.
  https://weiss-ag.com/museumgallery/
- **Sketchfab** - museum 3D-object collections (object-level, not whole-space).
- Recent "twin + monitoring" writeups (good paper citations): FusionVR (building twins
  of museums), Quinn Evans ("digital twins beyond environmental monitoring"), and a 2025
  *Scientific Reports* study on a ceramics-collection digital twin (Palace Museum).

## Open-source on GitHub (closest to the Heratio approach)

- **notbigmuzzy/linkwalk** - 3D gallery populated *dynamically from data* (Wikipedia
  API); most like Heratio building rooms from records.
  https://github.com/notbigmuzzy/linkwalk
- **theringsofsaturn/virtual-museum-tour-threejs** - guided three.js virtual tour.
  https://github.com/theringsofsaturn/virtual-museum-tour-threejs
- **TomPast/artwork-3D-museum** - React-Three-Fiber gallery.
  https://github.com/TomPast/artwork-3D-museum
- **lrusso/3DArtMuseum**, **KerimKochekov/Threejs-museum**, **ptrgags/virtual-museum**,
  **r23/Virtual-Reality-Museum** (A-Frame WebVR). Mostly static galleries with
  hard-coded art and no catalogue binding or live data.

## Feature comparison vs the open-source GitHub projects

Reviewed the three closest repos (Sept-2024 fetch of their READMEs/live demos). All are
front-end-only three.js demos: a single 3D room, artworks placed in code, no backend.

| Capability | linkwalk | vm-tour-threejs | artwork-3D-museum | Heratio |
|---|---|---|---|---|
| Engine | three.js | three.js | R3F/three.js | three.js |
| Who builds it | code (Wikipedia API) | code (one model) | code (hardcoded) | curator drag-drop builder + plan editor |
| Bound to a real catalogue/DB | no (Wikipedia) | no | no | yes (ISAD/RiC + DAM) |
| Multi-room / building | partial | no | no | yes (polygon rooms, doors) |
| Audio / narration | no | no | bg music | yes (T-to-talk + AI description) |
| Live data / conservation monitoring | no | no | no | yes (sensor overlay) |
| Simulation / forecast | no | no | no | yes (light-dose forecast) |
| Analytics | no | no | no | yes (per-room dashboard) |
| Multi-user / docent | no | no | no | yes (avatars + guided tour) |
| VR / WebXR | no | no | no | yes (VR button + controllers) |
| AI recommendation | no | no | no | yes |
| Open exports (IIIF/glTF/JSON-LD) | no | no | no | yes |
| Backend / persistence | static | static | static | yes (Laravel + MySQL) |

`linkwalk` is the most interesting (exhibits pulled live from Wikipedia) but still has no
catalogue, audio, VR, multi-user or data link. None of these is a digital twin in the
Wessels sense. On the open-source side there is effectively **no comparable**; the real
comparables are the commercial / academic deployments (Kremer, Matterport+BMS, Palace
Museum), which strengthens the paper's novelty claim.

## Proprietary product claims vs Heratio

Claimed features read from each vendor's own pages (Matterport from its documented
product set - its pages are JS-rendered and not fetchable as text).

- **Matterport** (SaaS market leader): photoreal 3D capture (LiDAR/phone/Pro cameras) ->
  auto mesh; Dollhouse + floor-plan views; Mattertags annotations; measurement mode;
  guided tours / highlight reels; VR (Quest); API/SDK; Cortex AI (auto-defurnish, blur,
  "Property Intelligence"); e57/OBJ/glTF exports; visit analytics / heatmaps.
- **Weiss AG**: 360 HDR panoramas; point-cloud/mesh + measurement; object photogrammetry;
  VAM2 asset management; virtual-exhibition builder (360 or 3D); "condition monitoring
  over time" (documentation, not live sensors). No visitor analytics, IoT, or AR.
- **Kremer**: bespoke architect-built VR gallery; 74 works; pro audio tours, deep zoom,
  view-the-back, AR wall projection; browser + headset. No multi-user, data or catalogue.
- **FusionVR**: scan + walkthrough; AR overlays; MR/holographic; curator metadata;
  multi-user avatars + docent; guided tours. "Real-time" = content updates; explicitly
  no sensor monitoring, analytics or BMS.

| Capability | Matterport | Weiss AG | Kremer | FusionVR | Heratio |
|---|---|---|---|---|---|
| Photoreal 3D capture of a real space | yes (strong) | yes (strong) | bespoke | yes | no (model-built) |
| Measurement tools | yes | yes | no | no | no |
| Bound to a collections catalogue (ISAD/RiC) | no | partial (VAM) | no | metadata only | yes |
| Live environmental/sensor twin loop | no | "over time" only | no | no | yes |
| Conservation simulation / forecast | no | no | no | no | yes |
| Visitor analytics | yes | no | no | no | yes |
| Multi-user + docent | no | no | no | yes | yes |
| VR / WebXR | yes | yes | yes | yes | yes |
| AI (describe / recommend) | capture AI (Cortex) | no | no | no | yes (content AI) |
| Open-standards export (IIIF/glTF/JSON-LD) | glTF/e57 only | no | no | no | yes |
| Open / self-hosted (not SaaS) | no | no | no | no | yes |

**Verdict:** proprietary tools win on photoreal capture + measurement (Matterport, Weiss)
and polished bespoke UX (Kremer). None claims the catalogue-bound + live-conservation-twin
+ open-standards combination Heratio has; FusionVR is closest on experience (multi-user/AR)
but has no sensor/monitoring/analytics. The one capability they all have that Heratio lacks
is **photoreal capture** (photogrammetry / Matterport import) - tracked as a roadmap issue.

## How Heratio differs (the gap)

Almost every comparable is one of two kinds:

1. **Photogrammetry / Matterport scan of a real room** - a *visual* twin: photoreal but
   with no live data and no link to a catalogue.
2. **Static 3D gallery with hard-coded art** - the open-source three.js demos.

The Heratio exhibition twin is unusual in combining, in one running GLAM system:

- curator-built rooms **bound to a real archival catalogue** (ISAD/RiC) and DAM media;
- the **live conservation loop** (sensor/visitor readings -> 3D status overlay ->
  light-dose forecast -> analytics) - the defining digital-twin behaviour per Wessels
  (2025) section 6.5;
- **multi-user presence + a live docent / guided tour**;
- an **AI audio docent** (T + click; gateway-generated description when metadata is
  absent);
- **open-standard exports** (IIIF Presentation 3.0, glTF/scene JSON, schema.org JSON-LD)
  and an embed.

The Kremer Museum is the nearest "walk + per-object info + AR" experience; the academic /
Matterport-plus-BMS deployments are nearest on the "twin with monitoring" axis. No single
surveyed system was found that does **both** the catalogue-bound curation AND the live
conservation/monitoring loop the way Heratio does - a defensible novelty claim for the
paper.

## Sources
Kremer Collection (virtualmuseum.thekremercollection.com); Wonderful Museums virtual
walkthrough guide; FusionVR "Building Digital Twins of Museums" (2025); Quinn Evans
"Digital Twins for Museums"; *Scientific Reports* (2025) ceramic-collection digital twin;
Weiss AG museum/gallery twins; Cranbrook Art Museum 3D tours; GitHub: linkwalk,
virtual-museum-tour-threejs, artwork-3D-museum, 3DArtMuseum, Threejs-museum,
virtual-museum, Virtual-Reality-Museum.

# Heratio Roadmap: North Stars, Apex Vision, and the Digital-Twin Line

> Summary: This document is the canonical, queryable record of Heratio's strategic
> roadmap - the apex vision, the eight north-star moonshots, the building-scale
> "rebuild an encyclopedic museum" goal, and the digital-twin feature line that
> delivers them. It exists because the roadmap otherwise lives only in the GitHub
> issue tracker (which the KM indexer does not crawl); this file makes the roadmap
> discoverable from KM and any cross-agent query. Status notes are as of June 2026.

Heratio is an international GLAM (Gallery, Library, Archive, Museum) platform. The
roadmap below is jurisdiction-neutral: every capability is built for any market,
with country-specific compliance as pluggable modules, never as the core.

## Apex vision

- **APEX (issue #1212): "that humanity never forgets."** The single vision all
  north-stars roll up to - Heratio as a durable memory layer so that cultures,
  places, objects, and the connections between them are never lost.
- **ROADMAP (issue #1213): "from catalogue to the memory layer of humanity."** The
  arc from a cataloguing system to a living, queryable, multi-sensory memory of
  human heritage that anyone, anywhere, can walk into, question, and trust.

## The eight north stars

Each north star is a visionary moonshot; first slices of all eight have shipped,
and they continue to deepen.

1. **The world heritage graph / open memory protocol (#1204).** A crawlable,
   content-negotiable linked-data graph (JSON-LD, Turtle, RDF/XML) with a
   self-describing dataset front door and a cursor-paginated crawl index, so any
   agent or institution can walk the whole graph as open data. Served from the
   `/api/v1/graph` family.
2. **Race against loss - endangered-heritage capture network (#1205).** Scores the
   records most at risk of being lost (missing master, poor condition, fragility
   signals) so a digitisation effort reaches them first. Surfaced as an admin
   capture-priority register and a public "race against loss" board
   (`/race-against-loss`).
3. **Walk through what no longer exists - reconstruct lost places (#1206).** Rebuild
   places that are gone or inaccessible as walkable 3D twins. Extended by the
   reconstruction assembly montage (#1219): a lost structure visibly rebuilds itself
   on screen - layered-assembly collage or dated time-lapse - before the visitor
   walks into its twin.
4. **Repatriation engine - trace and virtual return of displaced heritage (#1207).**
   Traces displaced objects (origin community vs current holder, displacement
   context) and offers a dignified virtual return into the object's twin or
   surrogate. Public displaced-heritage register (`/displaced-heritage`).
5. **The truth anchor - verifiable authenticity for primary sources (#1209, with
   #1201).** C2PA / content-credentials provenance: every digitised object carries a
   signed, verifiable manifest. Public `/verify` surface, per-object provenance-chain
   detail, and an embeddable verify badge so authenticity travels with the object.
6. **A culture you can talk to - corpus-grounded history and language revival
   (#1208).** Ask the collection (`/ask-the-collection`) answers grounded in the
   catalogue; the path extends toward language revival and conversational history.
7. **Generative scholarship - AI finds connections no human spotted (#1210).**
   Surfaces cross-collection connections with rationale and confidence; public
   Discoveries page (`/discoveries`). Always labelled AI-generated, verify before
   citing.
8. **Every museum for everyone - universal multilingual access (#1211).** Read any
   record, label, or tour in your own language, on demand, routed through the AHG AI
   gateway.

## The building-scale moonshot: rebuild an encyclopedic museum (Louvre-class)

- **North star #1217: "the encyclopedic museum, fully rebuilt - a building-scale
  digital twin (Louvre-class)."** The integration-at-scale apex of the digital-twin
  line: reconstruct an entire world-class museum (the Louvre as the exemplar, but
  generalising to any institution) as a fully navigable twin - hundreds of rooms,
  tens of thousands of objects in situ at archival fidelity, every object answerable
  and readable in any language, authenticity verifiable, with no app and no
  high-end GPU on the visitor's side.
- It consumes, rather than competes with, the existing twin work: the room engine,
  Gaussian-splat and IIIF fidelity, reconstructions (#1206), generative curation
  (#1186), the AI docent (#1185, #1208), multilingual access (#1211), federation
  (#1155), provenance (#1201, #1209), and the GPU delivery path (#1153, #1154).
- New scale-specific work it introduces (to be sliced into child issues): the
  architectural shell (multi-wing / multi-floor building graph), wayfinding ("take
  me to the Mona Lisa"), massive-object level-of-detail streaming, and a
  volume capture-to-twin acquisition pipeline. First slice = one real wing,
  end to end.

## The digital-twin feature line (delivery vehicle)

- **Exhibition-space twin + 3D walkthrough** - the room engine (ESM / three.js
  r169, live co-presence).
- **In-room Gaussian splats and IIIF deep-zoom** - the fidelity layer.
- **AI Curator-Docent (#1185)** - a conversational, corpus-grounded, in-room guide;
  optionally enriched by the KM RAG as a clearly-labelled secondary source.
- **Generative exhibitions (#1186)** - give the AI a theme and it curates a
  review-ready show (objects, grouping, wall text, tour); candidate recall upgraded
  to Elasticsearch semantic search with keyword fallback.
- **Live virtual openings (#1192)** - ticketed, capacity-checked multi-user events
  (free and paid), with real-time presence as a later slice (#1150).
- **Generative exhibitions, reconstructions, and federation (#1155, #1203)** - the
  twin links across institutions.
- **Renderer and delivery (#1153 WebGPU, #1154 server-GPU pixel-streaming)** - the
  heavy-scene path that makes building-scale feasible on ordinary devices.

## Discoverability surfaces (where the public meets the roadmap)

- **Explore hub (`/explore`)** - one place that surfaces the public capabilities:
  ask the collection, read a record in your language, Discoveries, content
  credentials / verify, the system map, reconstructions, the race-against-loss
  board, and the open-data graph API. Each entry is shown only when its feature is
  installed.
- **System map (`/help/system-map`)** - a traversable end-to-end diagram of the
  whole platform in the Help Center, with search, stage filters, and a minimap.

## How this roadmap stays current in KM

The roadmap lives in the GitHub issue tracker, which the KM indexer does not crawl.
This reference document is the bridge: update it whenever a north star, the apex, or
the digital-twin line changes materially, and the `docs/` inotify watcher
re-ingests it into KM within minutes. Per-release `docs/sessions/` logs already
capture shipped detail automatically; this file captures the strategic shape.

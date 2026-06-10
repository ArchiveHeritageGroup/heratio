# Heratio Exhibition functionality

A complete reference for the Exhibition / Digital-Twin feature set, split by who can do what:
**visitor (not logged in)** vs **curator / admin (logged in)**. The split below is exactly what
the route middleware enforces (`auth` = must be logged in, `acl:create|update|delete` = needs that
permission, `admin` = admin only). Anything not under those groups is public.

There are two related concepts:

- **Exhibition record** (`/exhibition/...`) - the curatorial record of a show: its objects,
  storylines, sections, physical events and checklists. Staff-only.
- **Exhibition Space / Digital Twin** (`/exhibition-space/...`) - the 3D virtual gallery a visitor
  can walk through: builder, plan, walkthrough, analytics, AI docent, live openings. Mostly public
  to *view*, staff-only to *edit*.

---

## Part A - Visitor (NOT logged in)

Everything here is public and read-only (a guest can explore and interact, but cannot change the
space). Edit controls are hidden behind `@auth`, so a guest sees the experience without the tools.

### Discover
| Capability | Where | Notes |
|---|---|---|
| Browse all exhibition spaces | `/exhibition-space/browse` | List with utilisation |
| Space overview page | `/exhibition-space/{slug}` | Metadata + entry points |

### Walk through the twin
| Capability | Where | Notes |
|---|---|---|
| **3D walkthrough** (first-person) | `/exhibition-space/{slug}/walkthrough` | The main visitor experience. W A S D / arrows to move, wheel to step, look around; objects on pedestals, in glass cases, or hung on walls |
| **Beta walkthrough** (ESM/r169) | `/exhibition-space/{slug}/walkthrough-next` | Modern renderer; adds **in-room Gaussian splats**, the **conversational docent**, and **live-opening mode**. Object-scale splats render in-room; scene-scale splats show a card with a "View in 3D" link to the full splat viewer |
| Mobile **AR companion** | `/exhibition-space/{slug}/companion` | Phone page (open via QR in the gallery): object cards + room AI docent |
| **Accessible tour** | `/exhibition-space/{slug}/accessible-tour` | Keyboard / screen-reader text + narration alternative |
| VR (WebXR) | inside the walkthrough | "Enter VR" on a headset-capable browser |
| Night mode | inside the walkthrough | Walk with a flashlight |
| Builder / Plan / Forecast / Analytics **(view-only)** | `/builder`, `/plan`, `/forecast`, `/analytics` | A guest can open these to look; all save/edit actions require login |

### Talk to the AI docent (grounded only in the objects on display)
| Capability | Where | Notes |
|---|---|---|
| Ask about a single object | `…/object/{ioId}/ask` | Grounded Q&A |
| Ask about the whole room/exhibition | `…/{slug}/ask-room` (+ `/room-questions` chips) | One-shot grounded Q&A |
| **Conversational docent** (multi-turn) | `…/{slug}/converse` | Remembers the recent conversation + your location, resolves "this/that/it", and offers a **"Walk me to …"** next-object suggestion. *(Beta walkthrough)* |
| AI-describe an object with no metadata | `…/object/{ioId}/describe` | On demand |
| In-twin recommendations | `…/{slug}/recommend` | "You might also like" |
| Spoken narration (neural TTS) | `…/tts` | Audio docent voice |

All AI routes through the AHG AI gateway; if it is unavailable the docent degrades gracefully.

### Be present + contribute
| Capability | Where | Notes |
|---|---|---|
| **Live multi-user presence** | `…/presence/beat`, `…/presence/leave` | See other visitors moving in real time. (Leading a guided tour as **docent** requires login) |
| Wall graffiti / annotations | `…/annotation` (+ delete) | Leave a tag on the wall |
| Visit analytics events | `…/visit-event` | Automatic; feeds the heatmap (dwell + per-object attention) |

### Attend a live opening (ticketed event)
| Capability | Where | Notes |
|---|---|---|
| Public event page | `/exhibition-space/opening/{token}` | Schedule, host, countdown |
| RSVP / get a ticket | `…/opening/{token}/rsvp` | Capacity-checked; one ticket per email |
| **Join the live opening** | `…/opening/{token}/join?t=<ticket>` | Validates the ticket + join window (opens 15 min before), then drops you into the walkthrough as a **named, co-present** attendee |

### Interoperability (open standards, CORS-open, read-only)
| Capability | Where |
|---|---|
| IIIF manifest | `…/{slug}/manifest.json` |
| Scene export (glTF-ish scene graph) | `…/{slug}/scene.json` |
| Linked-data (JSON-LD) | `…/{slug}/exhibition.jsonld` |

> **IoT note:** `…/sensor/ingest` is public but authenticated by a per-space token (for hardware
> sensors, not people).

---

## Part B - Curator / Admin (logged in)

Everything in Part A **plus** the authoring, management and analytics tools. Most write actions
need `acl:update` / `acl:create`; deletion needs `acl:delete` / admin.

### Exhibition records (the curatorial show)
`/exhibition` (login required)
- Dashboard + index (`/exhibition`, `/exhibition/dashboard`, alias `/museum/exhibitions`)
- Create / edit / show an exhibition (`add`, `{id}/edit`, `{id}`)
- Objects + object list + **CSV export** (`{id}/objects`, `{id}/object-list`, `…/csv`)
- **Storylines** and individual storyline (`{id}/storylines`, `{id}/storyline/{storylineId}`)
- **Sections**, **Events**, **Checklists** (`{id}/sections`, `{id}/events`, `{id}/checklists`)

### Create + manage a digital-twin space
- Create / edit / update / delete a space (`add`, `{slug}/edit`, delete is **admin-only**)
- Place / remove objects (`place`, `placement/{id}/remove`)
- Publish the space into the **RiC knowledge graph** as a `rico:Activity` (`sync-ric`)

### Builder - dress the room (drag-and-drop, all `acl:update`)
`/exhibition-space/{slug}/builder`
- Place objects on the floor or hang on walls; save the whole layout
- Per-object: **size** (real metres), **tilt**, **spotlight** (off / on-approach / always), **display
  case** (glass vitrine), **on-floor** (no pedestal), **viewing spot**, **z-order**
- **Furniture**: benches, pedestals, cases, planters, rope railings (add / move / remove / poles) +
  upload custom furniture assets
- **Walls**: add / move / place interior walls + per-wall positioning
- **Surfaces**: floorplan, ceiling, per-wall images, decorative floor image, floor grout/tile,
  wall colour (global + per-edge)
- **Photoreal capture**: back the room with a 3D **scan shell** (glTF/GLB/OBJ/STL/PLY) + scale/meta
- Room dimensions; **guided walkthrough path** (the order objects are visited)

### Plan editor - lay out a multi-room building (`acl:update`/`acl:create`)
`/exhibition-space/{slug}/plan`
- Add / delete rooms; snap rooms together; custom (non-rectangular) room shapes
- Doors, windows, stairs (multi-floor), corridors (add / move / remove)
- Building blueprint image (upload / clear / position); per-room floor level; room lock; grouping

### Live data, conservation + analytics
- **Live sensor binding** (`#1188`): bind a real light/temp/humidity sensor via a per-space token;
  raises **conservation alerts** (e.g. temp 16-24 C, humidity 40-60% RH, light ≤ 200 lux)
- Record readings / **simulate** readings / regenerate the sensor token (`acl:update`)
- **Conservation forecast** (`/forecast`) - simulate + predict against the physical room
- **Analytics dashboard** (`/analytics`) - per-room trends for light/temp/humidity/visitors over
  1/7/30/90 days, plus:
  - **Visitor heatmap** - top-down plan shaded by dwell time
  - **Per-object attention** (`#1187`) - each object dot sized/shaded by **dwell** (draws vs loses
    attention), with a **views · dwell** list

### AI authoring
- Precompute **AI recommendations** for the twin (`recommend/generate`, `acl:update`)
- **Generative exhibitions** (`#1186`) - enter a theme, get an **AI-curated draft** (rooms +
  objects + labels): `/exhibition-space/generate` → `suggest` → `build` (`acl:create`)
- Authored **audio guided tour** - save the route + narration, upload tour audio (`acl:update`)
- Lead a **live guided tour** as **docent** inside the walkthrough (your docent role is granted
  server-side because you're logged in; visitors follow you in real time)

### Live virtual openings (ticketed events) - admin side
`/exhibition-space/{slug}/openings` (`acl:create`/`update`/`delete`)
- Schedule an opening (title, host, start, duration, capacity)
- Manage status (scheduled → live → ended / cancelled), delete
- The public RSVP page + ticket-gated join (Part A) flow from these

### Danger zone
- Delete a space - **admin** only (`confirmDelete`, `destroy` + `acl:delete`)

---

## Quick capability matrix

| Capability | Visitor | Curator/Admin |
|---|:--:|:--:|
| Browse + view spaces | ✅ | ✅ |
| 3D walkthrough (+ beta, AR, accessible, VR) | ✅ | ✅ |
| AI docent (ask / converse / describe / recommend / TTS) | ✅ | ✅ |
| Multi-user presence (as visitor) | ✅ | ✅ |
| Lead a guided tour (docent role) | ❌ | ✅ |
| Wall graffiti, visit events | ✅ | ✅ |
| RSVP + join a ticketed opening | ✅ | ✅ |
| Interop exports (IIIF / scene / JSON-LD) | ✅ | ✅ |
| View builder/plan/forecast/analytics | ✅ (read-only) | ✅ |
| Edit builder / plan / furniture / walls / surfaces | ❌ | ✅ |
| Live sensor binding, readings, forecast tuning | ❌ | ✅ |
| AI recommendations + generative exhibitions | ❌ | ✅ |
| Authored audio guided tour | ❌ | ✅ |
| Schedule / manage live openings | ❌ | ✅ |
| Create / edit space, place objects, RiC publish | ❌ | ✅ |
| Exhibition records (storylines/sections/events/checklists) | ❌ | ✅ |
| Delete a space | ❌ | ✅ (admin) |

---

## Recently added (this iteration)
- **Per-object attention heatmap** (`#1187`) - dwell-based object attention in Analytics.
- **In-room Gaussian splats** (`#1193`) - object-scale splats composited into the walkthrough via
  GaussianSplats3D DropInViewer; scene-scale splats link to the framed standalone viewer. *(Beta)*
- **Conversational docent** (`#1185`) - multi-turn, room-aware guide with walk-to suggestions. *(Beta)*
- **Ticket-gated live openings** (`#1192`) - validated join + named co-presence + event banner.
- **Self-hosted 3D libraries** - three.js, model-viewer, GaussianSplats3D, decoders all served from
  `/vendor` (no external CDN).
- **Generative exhibitions** (`#1186`) and a `ThemeExhibitionService::curate()` one-shot builder.

> The **beta walkthrough** (`/walkthrough-next`, ESM/three-r169) renders modern glTF materials
> (e.g. `KHR_materials_specular`) and in-room splats that the live r137 walkthrough cannot; it is
> intended to replace the live walkthrough once signed off.

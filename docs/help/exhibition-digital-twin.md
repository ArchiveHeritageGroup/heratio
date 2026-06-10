---
category: User Guide
title: Exhibition digital twin - live data, simulation, analytics and recommendations
---

# Exhibition digital twin

An Exhibition Space in Heratio is more than a 3D model of your gallery. When you feed it
live or periodic readings from the real space, it becomes a **digital twin**: a virtual
copy that you can monitor, simulate and forecast against the physical room. This guide
covers the twin features that sit on top of the builder and walkthrough.

## The building blocks

- **Builder** (`/exhibition-space/{slug}/builder`) - drag objects onto the floor or hang
  them on walls; set size, tilt and which wall (including the front or back face of an
  interior divider). Doors you place in the plan editor, and doorways between adjoining
  rooms, show on the floor and wall views. In **Wall view** you can also **add windows** to
  the selected perimeter wall - click **Add window**, drag it along the wall to position it,
  and **click a window to edit its width, sill and height or remove it** (windows can also be
  added in the plan editor); they appear as glass openings in the 3D walkthrough.
- **Plan editor** (`/exhibition-space/{slug}/plan`) - arrange rooms of a building on a
  blueprint; rooms snap to each other and can take custom (non-rectangular) shapes.
- **Walkthrough** (`/exhibition-space/{slug}/walkthrough`) - a first-person 3D tour on
  desktop and mobile. Move with W A S D or the arrow keys, wheel to step forward or back,
  and **hold U + mouse wheel to stand taller or crouch**.

## Photoreal capture (scan a real room)

Instead of building a room block by block, you can back it with a **photoreal 3D scan**
of the real space. In the builder, open the **Photoreal capture** card:

- **Upload scan shell** - a `.glb`, `.gltf`, `.obj`, `.stl` or `.ply` mesh exported from
  photogrammetry (e.g. RealityCapture, Metashape, Polycam) or a 3D scanner. It renders
  inside the walkthrough as the room's backdrop. The built floor and walls stay in place
  underneath, so you keep solid collision (you cannot walk out through a scanned wall) and
  a clean fallback if the scan is hidden.
- **Fit scale** - a single multiplier to size the scan to the room's real metres. The
  scan's own origin is placed at the room's corner; nudge the scale until it lines up.
- Your **object placements and the live overlay still work** over the scan - drop objects,
  hang pictures, run the conservation overlay exactly as on a built room.
- **360 / Matterport embed URL** - paste a Matterport (or any 360 tour) share URL. A
  **360 button** then appears in the walkthrough whenever you stand in that room, opening
  the immersive tour in an overlay. Licensing for the embedded tour stays with its host.

**Point clouds** render too: upload a `.pcd` or a point-cloud `.ply` and it shows as points
in the walkthrough (large clouds are automatically downsampled so they stay smooth, on
mobile too). `.las` / `.e57` aren't read directly by the browser - export them to PLY or
PCD from your scan software first. Larger scans take longer to download; keep them under a
few hundred MB and decimate where you can.

## Live data link

Readings of **light (lux), temperature, humidity and visitor count** can be recorded per
room. In the walkthrough, press the **thermometer button** to turn on the Live overlay:
each room is tinted green, amber or red by its conservation status, and a panel reads out
the current room's values. Status is judged against the room's lighting-lux target and
safe ranges for temperature (16-24C) and humidity (40-60%).

- Sensors or a building-management system POST readings to the room's readings endpoint.
- To see how it looks before any sensors are wired, use **Simulate live data** in the
  builder header to seed demo readings across the building.

### Binding a real sensor (#1188)

Each space has a **sensor token** so a real IoT sensor or gateway can push readings without
logging in. Open **Analytics** (logged in) to see the token, a ready-to-use `curl` example,
and a **Regenerate token** button. A device just POSTs to `/exhibition-space/sensor/ingest`
with the `X-Sensor-Token` header and a `readings` array of `{metric, value}` (metrics:
`temp_c`, `humidity`, `lux`, `visitors`).

When a reading is **out of conservation range** (temp 16-24 C, humidity 40-60% RH, light at
or below the space's lux target / 200 lux), Heratio raises a **conservation alert** shown at
the top of Analytics, marked warning or critical. The readings also drive the live overlay,
forecast and analytics as before.

## Conservation forecast (simulation and prediction)

Open **Forecast** from a space to see, per room, the projected **annual light dose** from
the recent average lux, compared with conservation budgets for sensitive, moderately
sensitive and durable material. Each room shows the percentage of budget used, the days
until the budget is reached, and a risk band. A what-if simulator lets you try different
lux levels, daily display hours and target tiers.

## Analytics dashboard

Open **Analytics** to see per-room trends for lux, temperature, humidity and visitors
over the last 1, 7, 30 or 90 days, with summary statistics. Use it to spot drift, busy
periods and rooms that need attention. This supports continuous improvement of the space.

The **Visitor heatmap** there shows a top-down plan of the building with each room shaded
by how long visitors spent in it (cool = less, hot = more), and red dots over the objects
that drew the most attention. It is built automatically from real walkthrough usage, so you
can see at a glance which rooms and objects engage people and which get overlooked.

## In-twin recommendations

While walking through, a "You might also like" strip suggests related objects. Click a
suggestion to glide to that object. Suggestions are based on how object titles relate to
each other, optionally enriched with AI-generated reasons. Curators can pre-generate AI
recommendations from the builder.

## Hear an object described (audio docent)

In the walkthrough, **hold T and click an object** to have its description read aloud.
If the object has no description recorded, Heratio asks the AI gateway to generate a short
docent description on the spot, reads that out, and marks it in the panel. To force a
**fresh AI description** even when one exists, **hold G and click**. Press **Esc** to stop.

Choose the speaking voice under the **Controls** panel (the **?** button) -> **Narration
voice**; on most phones and Macs the system neural voices are available there.

## Ask the docent (AI Q&A)

Open an object's panel (click it) and use **Ask the docent about this** - type a question, or
tap one of the suggested chips ("Tell me about this", "Who made it?", "When is it from?",
"Why does it matter?"). The AI answers **grounded in that object's catalogue record** and
reads the answer aloud. It will not invent dates, names or provenance that are not in the
record - if the record does not say, it tells you so. Press **Esc** first to free the cursor
so you can type.

You can also ask the docent to **take you somewhere**: type "take me to the dresser",
"where is the robot", or "show me the Benson portrait" and it walks you to the best-matching
object anywhere in the building and opens its panel.

## Multiple visitors and live guided tours

Several people can walk the same exhibition at once. Each visitor appears to the others
as a named avatar that moves in real time (updated a few times a second). The **People**
button (top-right) shows who is here; you can set your display name.

Logged-in staff can run a **guided tour**: press **Start guided tour**, and visitors get
a **Follow the docent** button that tethers their view to yours (it releases the moment
they move themselves). While leading, whatever object you open is **spotlighted** so
everyone following is flown to it, and you can post a short message banner to the group.

## Virtual reality (WebXR)

On a VR headset with a WebXR browser, a **VR button** appears in the walkthrough. Enter
VR for room-scale, head-tracked viewing; the **left thumbstick moves** and the **right
thumbstick turns**. On ordinary desktops and phones the button stays hidden and the normal
controls apply.

## Share and interoperability

From the builder, the **Share & interoperability** card exposes the exhibition in open
standards any other system can read:

- **IIIF manifest** (`/exhibition-space/{slug}/manifest.json`) - opens in IIIF viewers
  such as Mirador or the Universal Viewer, and can be harvested by other institutions.
- **3D scene manifest** (`/exhibition-space/{slug}/scene.json`) - rooms and object
  placements so another 3D viewer can rebuild the space.
- **Linked data** (`/exhibition-space/{slug}/exhibition.jsonld`) - a schema.org
  `ExhibitionEvent` for search engines and linked-data tools.
- An **embed snippet** to drop the live walkthrough into any website with an iframe.

## Authored audio guided tours

Curators can pre-build one or more **guided tours**: an ordered route of objects, each
with a script the guide reads aloud and a dwell time. Build them in the builder's
**Guided tour (audio)** card - add objects, type the narration (or tap the wand to draft
it with AI), set seconds per stop, reorder, and save. You can keep several named tours.

Visitors press the green **Play** button in the walkthrough (or pick a tour in the
Controls panel when there is more than one). The guide flies you from object to object,
speaks each script, waits, then moves on. A banner shows the current stop and text;
Pause / Stop are there too.

**On mobile**, where walking is fiddly, a big **Start guided tour** button appears at the
bottom of the screen - tap it and the tour drives the whole visit for you.

## Graffiti / wall tags

Tap the **spray-can** button, then click a wall to leave a short graffiti tag. Tags pin
flat to the surface you click. (The demo war room and AI room already carry a few.)

When you are **signed in**, tags are saved and shown to everyone who visits. When you are
**not signed in**, tags are kept only in your browser for the current session - they let
you doodle and try the feature without writing to the gallery, and they disappear when you
close the tab. To remove a tag, switch on graffiti mode and click the tag.

## Night mode (walk with a flashlight)

Press the **moon button** (or **N**) to switch the gallery to night. The room drops to a
dim, moonlit ambient - fairly dark but never pitch black, so you can still make out shapes
- and a **flashlight** switches on that follows wherever you look. Press the button or **N**
again to return to normal lighting. Great for a dramatic after-hours tour, or for picking
out a single spotlit object in the dark.

## Reading an object's details

Aim at an object and click it (or click a numbered button) to open its info popup. When the
popup opens the **mouse cursor is freed** and lands on the popup, so you can click **Close**,
**View full record**, or a related-item suggestion straight away. Click the popup's close
button, click away, or press **Esc** to dismiss it - the view re-locks and you carry on
walking without having to click back in.

## All the walkthrough controls

| Action | Control |
|---|---|
| Move | W A S D or arrow keys (drag to look on touch) |
| Forward / back | mouse wheel |
| Stand taller / crouch | hold U + mouse wheel |
| Zoom in / out | Z |
| Torch (light dark corners) | F or the bulb button |
| Night mode (dim room + flashlight) | N or the moon button |
| Hear description | hold T + click an object |
| Force fresh AI description | hold G + click an object |
| Graffiti | spray-can button, then click a wall |
| Help menu | right-click, or the ? button |
| Open full record | V |
| Close info popup | click the popup, click away, or Esc |
| Virtual reality | the VR button (headset) |
| Guided tour | the Play button (big Start button on mobile) |

## What makes it a "twin"

A virtual model becomes a digital twin once it is linked to the physical space through
real-time data and can be used to monitor, simulate and predict. With the live link,
conservation forecast and analytics in place, an Exhibition Space meets that test: you can
watch the real room's condition, test changes safely, and forecast conservation risk
before it happens.

## Roadmap

Further extensions under consideration: live cross-institution federation of exhibitions, and
rendering `.las` / `.e57` point clouds (a conversion step, issue #1183). Neural narration
voice (issue #1168) and photoreal scan import incl. point clouds (issues #1156 / #1183) have
shipped - see **Photoreal capture** and the audio docent above.

A **WebGPU renderer** (issue #1153) was trialled but **not adopted**: on the target three.js
build it broke transparency (glass), alpha-cutout foliage (trees/grass) and billboards
(labels/people) with no visible performance gain, so the walkthrough stays on the proven WebGL
renderer. A standalone proof page (`/exhibition-space/{slug}/walkthrough-webgpu`) is kept for a
future retry. VR therefore continues to work as before.

Server-GPU **pixel-streaming** (rendering console-quality scenes on a server and streaming
video to the browser, issue #1154) was evaluated and **deferred**: it costs roughly one GPU
per viewer, whereas the walkthrough already serves unlimited concurrent visitors at no server
cost. It is only worth revisiting for a single kiosk or low-concurrency photoreal experience.

A **WebGPU renderer** (issue #1153) is being evaluated: open
`/exhibition-space/{slug}/walkthrough-webgpu` for the proof page - it runs the room on
modern three.js with WebGPU on capable devices and a graceful WebGL2 fallback elsewhere
(a badge shows which backend is live). The main walkthrough still uses WebGL until that
evaluation completes.

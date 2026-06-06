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
  rooms, show on the floor and wall views.
- **Plan editor** (`/exhibition-space/{slug}/plan`) - arrange rooms of a building on a
  blueprint; rooms snap to each other and can take custom (non-rectangular) shapes.
- **Walkthrough** (`/exhibition-space/{slug}/walkthrough`) - a first-person 3D tour on
  desktop and mobile. Move with W A S D or the arrow keys, wheel to step forward or back,
  and **hold U + mouse wheel to stand taller or crouch**.

## Live data link

Readings of **light (lux), temperature, humidity and visitor count** can be recorded per
room. In the walkthrough, press the **thermometer button** to turn on the Live overlay:
each room is tinted green, amber or red by its conservation status, and a panel reads out
the current room's values. Status is judged against the room's lighting-lux target and
safe ranges for temperature (16-24C) and humidity (40-60%).

- Sensors or a building-management system POST readings to the room's readings endpoint.
- To see how it looks before any sensors are wired, use **Simulate live data** in the
  builder header to seed demo readings across the building.

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

Tap the **spray-can** button, then click a wall to leave a short graffiti tag. Tags are
saved and shown to everyone who visits. (The demo war room and AI room already carry a
few.)

## All the walkthrough controls

| Action | Control |
|---|---|
| Move | W A S D or arrow keys (drag to look on touch) |
| Forward / back | mouse wheel |
| Stand taller / crouch | hold U + mouse wheel |
| Zoom in / out | Z |
| Torch (light dark corners) | F or the bulb button |
| Hear description | hold T + click an object |
| Force fresh AI description | hold G + click an object |
| Graffiti | spray-can button, then click a wall |
| Help menu | right-click, or the ? button |
| Open full record | V |
| Virtual reality | the VR button (headset) |
| Guided tour | the Play button (big Start button on mobile) |

## What makes it a "twin"

A virtual model becomes a digital twin once it is linked to the physical space through
real-time data and can be used to monitor, simulate and predict. With the live link,
conservation forecast and analytics in place, an Exhibition Space meets that test: you can
watch the real room's condition, test changes safely, and forecast conservation risk
before it happens.

## Roadmap

Further extensions under consideration: **natural neural narration voice** routed through
the AI gateway (issue #1168), a WebGPU renderer and server-GPU pixel-streaming for very
heavy scenes, live cross-institution federation of exhibitions, and importing photoreal
3D scans (photogrammetry / Matterport / glTF) as room backdrops.

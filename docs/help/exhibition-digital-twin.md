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

## What makes it a "twin"

A virtual model becomes a digital twin once it is linked to the physical space through
real-time data and can be used to monitor, simulate and predict. With the live link,
conservation forecast and analytics in place, an Exhibition Space meets that test: you can
watch the real room's condition, test changes safely, and forecast conservation risk
before it happens.

## Roadmap

Planned extensions include shared multi-user tours with a live docent, VR/AR headset
support (WebXR), and sharing twins across institutions via open 3D and linked-data
standards.

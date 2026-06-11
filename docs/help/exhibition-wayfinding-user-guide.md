> Heratio Help Center article. Category: Public Access.

# Exhibition Wayfinding User Guide

## Overview

Exhibition Wayfinding gives visitors a floor plan and a "take me to X" directory for an exhibition space, so they can find any object, room, or feature without wandering. You see the layout at a glance, search or browse the directory of what is on show, and then jump straight to the chosen item - either highlighted on the plan or opened in the immersive walkthrough. Open it at **/exhibition-space/{slug}/wayfinding**, where `{slug}` is the exhibition's identifier (for example `https://your-site.example/exhibition-space/founders-hall/wayfinding`).

---

## What it does

Exhibition Wayfinding helps people orient themselves inside an exhibition and navigate straight to what they came to see:

- It shows a **floor plan** of the exhibition space, so the overall layout is clear before you move.
- It provides a **directory** of the objects and features on display - a searchable "take me to X" list.
- It links each directory entry to its **location on the plan**, so you can see exactly where a thing sits in the room.
- It can **deep-link into the walkthrough** using a `?focus=` parameter, dropping you into the immersive view already pointed at the chosen object.

It works for both on-site visitors planning a route and remote visitors exploring the same space online.

---

## How to use it

1. Go to **/exhibition-space/{slug}/wayfinding**, replacing `{slug}` with the exhibition's identifier.
2. Read the **floor plan** to get your bearings - rooms, zones, and key features are laid out as they are arranged in the space.
3. Use the **directory** to search or browse for the object, room, or feature you want. Selecting an entry highlights its position on the plan.
4. To jump straight into the immersive view focused on a specific item, follow a **`?focus=` deep link** - for example `https://your-site.example/exhibition-space/founders-hall/walkthrough?focus=cabinet-3`. The walkthrough opens already aimed at that target.
5. Return to the wayfinding page at any time to pick another destination.

---

## Good to know

- The `?focus=` deep link is shareable - you can send someone a link that opens the walkthrough already pointed at a particular object, which is handy for tours, lessons, and social posts.
- Wayfinding is a navigation aid; it reflects how the exhibition has been laid out and does not change any catalogue data.
- If a directory entry does not appear, the object may not be included in this exhibition or may not be published - wayfinding only lists what is on show and what you are permitted to see.
- The page is public-facing and works in a standard web browser, on both desktop and mobile.

# Digital Twin Builder - Exhibition Space (User Guide)

**Summary.** The Digital Twin Builder is the first step of digital twins for
exhibition spaces. It turns the capacity/timeline placement list into a visual
floorplan where you drag, rotate and scale objects into position. Open it from
any exhibition space via the **Digital Twin Builder** button
(`/exhibition-space/{slug}/builder`). Shipped under heratio#1138 (Phase 1).

## What it does

- Shows the space as a canvas - your uploaded floorplan image, or a grid if none
  is set yet.
- Each object placed in the space appears as a card on the canvas (its thumbnail
  where one exists, otherwise a labelled placeholder).
- Arrange the collection visually: drag to move, use the selection handles or the
  side panel to rotate and resize.
- Every change saves automatically (the header shows "Saving..." then
  "All changes saved").

## How to use

1. **Open the builder.** From an exhibition space page, click **Digital Twin
   Builder**.
2. **Add an object.** In the left "Add object" box, type 2+ characters to search
   archival descriptions by title. Selecting one drops it on the centre of the
   canvas and selects it.
3. **Position it.** Drag the card anywhere on the floorplan. With a card
   selected, use the corner handles to resize/rotate, or the side-panel buttons
   (rotate left/right, smaller/bigger).
4. **Remove.** Select a card and click **Remove from twin** (this removes the
   placement from the space).
5. **Upload a floorplan** (optional). In the left "Floorplan" box choose an image
   and optionally enter the real-world width/height in metres, then **Upload
   floorplan**. The image becomes the canvas background.

## Notes

- Positions are stored normalized (0-1), so the layout holds its proportions when
  the canvas is resized or viewed on different screens.
- Placements created in the builder carry no date range and therefore do not
  consume dated capacity; set dates from the space page if a placement needs to
  count against capacity for a period.
- Editing requires the update permission; viewing the builder requires sign-in.

## 3D Walkthrough (first-person virtual gallery)

A visitor-facing **first-person 3D gallery** is available from the
**Walkthrough** button (`/exhibition-space/{slug}/walkthrough`, public - no
sign-in needed). Built with Three.js.

- The room is rendered in 3D: the uploaded floorplan becomes the **floor
  texture**, with four walls and gallery lighting.
- Each placed object stands at its floorplan position on a pedestal:
  - **3D objects** (a `.glb`/`.gltf` model) load as the **real interactive 3D
    model**.
  - **2D objects** appear as a **framed image** facing the room.
  - Objects with no usable media show a neutral placeholder block.
- **Navigation:** click the room to enter (pointer lock). Walk with **W A S D**
  (or arrow keys), look with the **mouse**, and press **Esc** to exit. A centre
  crosshair shows what you are pointing at.
- **Details:** click an object to open a side panel with its image, title,
  description and a link to the full archival record.

Best experienced on desktop (pointer-lock mouse-look). The richer the object's
media (a real 3D model, a good reference image), the better it presents - so keep
digital objects and 3D models attached to records.

## Roadmap

- Capture true z / wall coordinates in the Builder for precise 3D hanging
  (currently objects are placed from their 2D floorplan position).
- Optional AR / WebVR headset mode (model-viewer already supports AR per object).
- Builder UI to reorder a guided route. See
  `docs/exhibition-space-virtual-builder-plan.md` and heratio#1138.

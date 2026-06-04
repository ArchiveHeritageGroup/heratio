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

## Virtual Walkthrough (Phase 2)

A visitor-facing **2.5D pannable walkthrough** is available from the
**Walkthrough** button (`/exhibition-space/{slug}/walkthrough`, public - no
sign-in needed).

- The floorplan pans (drag) and zooms (scroll, or the zoom buttons); each placed
  object is a numbered hotspot at its position.
- Click a hotspot to open a detail panel with the object's image, title,
  description and a link to the full archival record.
- **Guided tour:** click "Guided tour" to step Next/Prev through the objects;
  the view animates to each stop and opens its panel. The order follows the saved
  guided route where one exists, otherwise natural reading order
  (top-to-bottom, left-to-right).

## Roadmap

- Builder UI to reorder the guided route explicitly (the backend
  `walkthrough_path_json` + save endpoint already exist).
- Optional immersive 360/VR view. See
  `docs/exhibition-space-virtual-builder-plan.md` and heratio#1138.

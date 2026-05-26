> Heratio Help Center article. Category: Viewers & Media.

# 3D Viewer Tools - Measure and Cross-Section

## User Guide

The 3D viewer now ships two extra tools below the animation and Save / Share view toolbars: a measurement tool, and a cross-section view. Both work on any model already shown in the viewer (browse cards, the archival description show page, the embed view, and the multi-angle gallery branch).

---

## Measure tool

Use the Measure tool to read the distance between two points on the model.

1. Click the **Measure** button (ruler icon) under the model.
2. Click the first point on the model surface. A small blue marker appears.
3. Click the second point. A second blue marker appears and a blue label between them shows the distance.
4. Click **Clear** (eraser icon) to remove the markers and start over.
5. Press **Esc** at any time to cancel an in-progress measurement.

The status text next to the toolbar tells you what to do next. If you click off the model, the status line says "No model surface under cursor - try again".

### Units

Most 3D models in glTF / GLB format are exported in metres. The label suffix is `m` by default. If your model is in millimetres or inches, the publisher can change the suffix without touching the model file itself.

### Tips

- The markers and label move with the camera, so you can spin or zoom after taking a measurement and the label stays anchored to the surface points.
- You can take multiple measurements in a row - each new pair starts after the second click. Use Clear or Esc to start fresh.
- The tool reads the model surface using the model-viewer hit-test API, so very thin or transparent surfaces may not register a hit.

---

## Cross-section view

Use the Cross-section view to focus on a slice of the model along an axis.

1. Click the **Cross-section** button (scissors icon) under the model.
2. Pick an axis - **X**, **Y**, or **Z** - to slice along.
3. Drag the slider to move the slice through the model. The offset, in metres from the model centre, is shown next to the slider.
4. Click **Cross-section** again, or press **Esc**, to leave the mode.

When the mode is active, a red guideline and a `CROSS-SECTION` badge overlay the viewer to mark where the slice falls. The camera target moves with the slider so the slice depth stays roughly centred on screen. Leaving the mode restores the original camera target.

### Tips

- Changing the axis resets the slider to the centre so the camera does not lurch.
- The mode is non-destructive: the underlying model file is not modified and the original camera target is restored on exit.
- Multiple viewers on the same page each have their own toolbar and remember their own slice settings independently.

---

## Reduced-motion preference

If you have **prefer reduced motion** set in your operating system or browser, the 3D viewer respects that:

- Auto-rotation is switched off when the model loads.
- Embedded animations do not autoplay - press the Play button in the animation toolbar when you want them to run.
- Opening a shared view URL snaps the camera to the target pose instead of running the smooth tween.

You can still use Measure, Cross-section, and the manual animation controls as normal. The preference only affects motion that would otherwise happen on its own.

---

## When to use these tools

- **Measure** is useful for capturing object dimensions for catalogue records, condition reports, and reproduction quotes when only a 3D scan is available.
- **Cross-section** helps researchers inspect the inside of hollow objects (vessels, boxes, instruments) or trace internal damage without taking a real cross-section.
- Both tools work on the same model regardless of whether it was scanned in metres, millimetres, or inches - the measurement label simply reports the model's own units.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Measure status says "This <model-viewer> build does not expose positionAndNormalFromPoint" | Your browser is using an older bundled model-viewer release | Reload the page; if it persists, contact your Heratio administrator to update the model-viewer asset bundle |
| Slider does nothing in Cross-section mode | Model dimensions could not be read | Wait for the model to fully load (the loading bar at the bottom of the viewer must reach 100%) and try again |
| Animation autoplays even with reduced-motion set | The browser is not surfacing the OS preference | Set the reduced-motion preference in your browser as well - Chrome and Firefox both expose it under accessibility settings |

---

Copyright (c) Johan Pieterse / Plain Sailing Information Systems / AGPL-3.0-or-later.

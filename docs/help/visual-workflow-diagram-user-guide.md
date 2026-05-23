---
category: User Guide
title: Visual workflow diagrams + drag-drop designer
---

# Visual workflow diagrams + drag-drop designer

Every workflow in Heratio can now be **visualised as a flow chart** — see at a glance how an object moves through your procedure. Beyond the diagram, you can drop into a drag-and-drop **designer** to model branching workflows where two paths run in parallel or where a step is conditional.

## What you get

| Surface | What it shows |
|---|---|
| **Workflow → Diagram** (from the workflow admin list, or per-workflow edit page) | A clean read-only flow chart of every step in the workflow, with optional steps shown as diamonds and inactive steps dashed. |
| **Workflow → Task → Progress diagram** | The same chart, but with the current task's state coloured in — green for completed steps, amber for the current step, grey for upcoming, red if anything was rejected. |
| **Workflow → Designer** | A canvas where you can drag handles between step nodes to draw connections, build branches, and save the resulting graph. |

All three surfaces are reachable from the **AHG Plugins → Workflow → Workflows & diagrams** menu, and from contextual buttons on existing workflow pages.

## Reading a diagram

- **Blue rounded boxes** are normal steps
- **Purple diamonds** are optional steps (configured per step in the workflow editor)
- **Dashed grey boxes** are deactivated steps
- **Arrows** show the order of execution
- The **small numbered circle** in the top-left of each step is its order

When viewing a task in progress (the "Progress diagram" link from `My Tasks`), the boxes are colour-coded by current state:

- **Green** — step completed
- **Amber** — current step (the one assigned to you or your team)
- **Grey** — pending future step
- **Red** — a task on that step was rejected (this is sticky — a later approval doesn't clear it from history)

## When to use the designer

Most workflows are linear — step 1 → step 2 → step 3. For those, you don't need the designer at all; the diagram just renders the implicit linear order from each step's "order" number.

The designer is for the cases linear ordering can't model:

- **Branching** — *"After Acquisition completes, EITHER ship to Cataloguing OR ship to Conservation depending on condition."*
- **Parallel approval gates** — *"Curator and Registrar must both approve before moving on."*
- **Conditional sign-off** — *"If the loan is over £10,000, route through Insurance review; otherwise skip it."*

To wire any of these, open the workflow, click **Designer**, drag from the right edge of one step's box to the left edge of another to draw an edge, and click **Save edges** when done. Existing connections show up automatically, and the renderer takes over: from that moment on, the diagram displays your custom topology, not the linear fallback.

## Using the designer

1. **Add or edit a workflow normally** via Workflow → Workflows. Add all the steps you want before you start drawing edges — the designer doesn't (yet) let you create new steps from the canvas.
2. **Open the designer** with the *Designer* button on the workflow's edit page.
3. **Drag from a node's right handle to another node's left handle** to draw a connection.
4. **Right-click an edge** to delete it.
5. **Save edges** to persist the graph to the database. The system validates that the graph is a DAG (no loops back to an earlier step) and that every step belongs to this workflow before saving.
6. **Clear all edges** wipes the canvas (still requires a Save to persist). Useful for starting over.
7. **Auto-layout** resets the visual positions to a step-order grid (the data isn't lost — this is purely a visual refresh).

## Designer guard rails

The save endpoint rejects with a 422 if:

- The graph contains a **cycle** (workflows must be DAGs)
- An edge references a step that doesn't belong to this workflow
- A self-loop is detected (step → itself)
- An edge is duplicated in the same save

When the save fails, none of the edges are written — your existing graph stays intact.

## What about the existing step editor?

The step editor on the workflow edit page **is still the authoritative way to add, remove, and configure steps**. The designer only changes the connections between them. You can switch back and forth at will.

If a workflow has no edges saved, the diagram falls back to linear order by `step_order`. So adding edges later is purely additive — you can ignore the designer entirely until you actually need branching.

## Where to find each surface

- **AHG Plugins menu → Workflow → Workflows & diagrams** — the workflow admin list with diagram + designer buttons per row
- **Workflow → Workflows & diagrams → Edit a workflow** — the *View diagram* and *Designer* buttons in the page header
- **Workflow → My Tasks → click a task → Progress diagram** — the live task progress overlay

## Behind the scenes

The diagram is **pure server-side SVG** — no JavaScript, prints cleanly, copies to PDF cleanly. The designer is the one piece that uses JavaScript (drawflow.js v0.0.59, self-hosted; no CDN dependency).

When a task is approved, the workflow service checks the Spectrum chain rules (if any are configured) and may automatically spawn a downstream task — see the *Spectrum compliance dashboard* article for how chain rules work.

> Heratio Help Center article. Category: Viewers & Media.

# Comparing two IIIF images side by side

**Audience:** Researchers, conservators, cataloguers, anyone working in the Mirador 4 viewer
**Related:** [IIIF Viewer User Guide](mirador-user-guide.md), [Saving Mirador workspaces](iiif-workspace-persistence-user-guide.md), [IIIF Scalebar and Magnifier](iiif-scalebar-magnifier.md)

---

## User Guide

Compare mode lets you place two IIIF manifests next to each other inside the same Mirador workspace and drag a vertical "comparison glass" across the active pane to reveal the partner pane beneath it. It is built for paired-canvas review tasks such as conservation before / after photography, multi-spectral imaging passes (RGB vs UV vs IR), or comparing two manuscript witnesses page-by-page.

## Launching compare mode

There are two ways to open the compare view.

### From the IIIF Collections page

1. Open **Browse IIIF Collections** from the main navigation.
2. Tick the checkbox next to each of the two collection items you want to compare.
3. Click the **Compare selected** button (icon: `bi-arrows-collapse-vertical`). The viewer opens at `/iiif-compare?manifests=...` with one Mirador window per manifest already positioned side by side.

### By URL

You can also launch the viewer directly:

```
/iiif-compare?manifests=https://example.org/iiif/A/manifest.json,https://example.org/iiif/B/manifest.json
```

The `manifests` query parameter accepts a comma-separated list, or repeated `manifest=...` parameters. Two manifests is the supported case for the comparison glass; if you pass more than two only the first two will be paired.

## Picking the manifests

Each pane is a fully independent Mirador window. You can:

- Switch the canvas in either pane using the canvas thumbnail strip.
- Pan and zoom each pane independently. The comparison glass scrubs between the two states regardless of which pane is zoomed in, which is what makes it useful for "look at the same painting at two different exposures".
- Open the sidebar in either pane to access annotations, info, or content search for that manifest.

If you would rather have both panes navigate together, see the sync toggle below.

## Using the comparison glass

1. With both windows open, open the **Window options** menu (the three-dot menu at the top of either window).
2. Click **Comparison glass**. A vertical yellow rule appears across the window with a circular knob at the centre.
3. Drag the knob left or right. The right-hand side of the active pane is replaced by a clipped overlay of the partner pane. Slide the knob all the way left to see only the partner, all the way right to see only the active pane.
4. The two small labels in the top corners (`A` on the left, `B` on the right) tell you which pane is currently exposed on each side of the seam.

To turn it off, open the same menu and click **Comparison glass** again.

If you toggle the glass on while only one window is open Mirador surfaces a one-shot console hint and the switch returns to off - open a second window first.

## Sync toggle (synchronised pan and zoom)

Mirador 4 ships with a built-in **window-side bar** entry called **Window synchronisation** that lets you bind any two open windows together so they share pan and zoom. Use it in combination with the comparison glass when you want both panes locked to the same viewport:

1. Open the side menu on the first window (icon: `bi-list`).
2. Pick **Window synchronisation** from the bottom of the list.
3. Tick the second window in the sync list. Both windows now share zoom and pan.
4. Drag the comparison glass as normal - the seam now scrubs between the two manifests at identical magnification, which is the right mode for before / after comparisons of the same physical object.

Untick the sync entry to return to independent navigation.

## Tips

- **CORS:** the comparison glass samples pixels from the partner pane, which requires the partner's Image API server to return `Access-Control-Allow-Origin` headers. Internal Cantaloupe-served images always work. External manifests may not - if you see a one-shot console warning that says `partner canvas not readable`, the partner pane is cross-origin without CORS and the right side of the seam will stay blank.
- **Annotations:** annotations on either pane are rendered as usual. The comparison overlay is drawn above the canvas but below the annotation overlay, so annotation pins and shapes remain interactive.
- **Scalebar and magnifier:** both still work in compare mode. The magnifier loupe sits above the seam, and the scalebar sits below it. You can hover the loupe over either side of the seam to inspect detail at native resolution.
- **Workspace save:** the seam position is NOT yet persisted by the workspace save feature. Re-toggle the glass after restoring a workspace.

## Known limitations

- One partner per active window. Three-way splits are on the roadmap.
- The seam position does not save with the workspace.
- Independent navigation is the default; a "lock zoom" shortcut inside the comparison-glass menu is on the roadmap.

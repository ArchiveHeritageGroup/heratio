> Heratio Help Center article. Category: IIIF.

# Saving Mirador workspaces

**Audience:** Researchers, archivists, anyone working in the Mirador 4 viewer
**Related:** [IIIF Viewer User Guide](iiif-viewer-user-guide.md)

---

## What is a workspace?

A Mirador workspace captures everything you have on screen in the viewer at a given moment: every open canvas, where each window is positioned, the active layout (Mosaic / Elastic), zoom level, panel states, annotation panels. Heratio now persists that workspace so you can come back to it later instead of rebuilding the layout every time.

## Two layers of persistence

### Auto-save to your browser

Whenever you change anything in the viewer (open a window, drag a panel, zoom in), Heratio writes a snapshot of the workspace to your browser's local storage about 1.5 seconds after you stop. Reloading the page restores that snapshot automatically.

- No login required.
- Tied to the browser and device you are using.
- Cleared when you clear browser data.

### Per-user saved workspaces

If you are logged in, you can save named workspaces to the server. They follow you across devices.

To save:

1. Open the **Workspace** dropdown in the Mirador toolbar.
2. Click **Save current** (overwrites the active saved workspace) or **Save as new** (creates a new named entry).
3. Give it a name, e.g. *"Letters from 1923 - working set"*.

To load:

1. Open the **Workspace** dropdown.
2. Click a saved name in the list.
3. The viewer rebuilds itself from the saved state.

To make a workspace the default on next page load:

1. Go to **Manage workspaces** at `/iiif/workspaces`.
2. Find the row and click **Set default**.
3. Next time you open a Mirador viewer, that workspace restores automatically.

## Managing your workspaces

The admin page at `/iiif/workspaces` shows:

| Column | What it means |
|---|---|
| **Name** | The label you gave the workspace |
| **Default** | A green badge marks the workspace that auto-loads on next open |
| **Created / Updated** | Timestamps |
| **Actions** | Rename, Set default, Delete |

Each user only sees their own workspaces; nothing is shared.

## Limits and quirks

- The auto-save is **per page**, not per record. Two viewer pages on the same site keep separate local snapshots.
- Workspaces are stored as opaque JSON. If a future Mirador upgrade changes the state shape, old saved workspaces may fail to restore - in that case the auto-save takes over and you can save a fresh copy.
- Local storage is roughly 5 MB per origin; very large workspaces (hundreds of windows) may hit that ceiling. The server-side store has no practical size limit for normal use.
- Logging out keeps your local auto-save but the saved-workspace dropdown will be empty until you log back in.

## Troubleshooting

- **My workspace did not restore on reload.** Check that the same browser is still attached - private / incognito tabs do not share local storage with normal tabs.
- **I see the dropdown but no saved entries.** Either you are not logged in, or you have not saved any yet. Pick **Save as new** to make the first one.
- **I want to start fresh.** Clear the auto-save with the browser console: `HeratioWorkspaces.clearLocal()`, then reload.

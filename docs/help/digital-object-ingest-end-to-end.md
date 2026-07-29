# Adding images and files to a record - end-to-end guide

This guide walks through getting digital material (photographs, scans, PDFs, audio, video) into Heratio and making it show up correctly on the public site. It covers the two everyday routes - adding a file to a single description, and loading a folder of files as children under one parent - plus the optional Archivematica preservation round-trip. A worked example runs through it using the **Mobrey Family Archive**.

Written for archivists and collection staff. No command-line knowledge is assumed for the parts you will use day to day; the batch and preservation sections note where an administrator is needed.

---

## 1. How Heratio models a digital object

One archival description (an "information object") normally carries **one primary digital object** - the master file - plus the derivatives Heratio makes from it automatically:

- **Master** - the original file exactly as you uploaded it. Never shown at full size to the public; kept for preservation.
- **Reference** - a web-friendly JPEG (long edge ~480px) shown inline on the record page.
- **Thumbnail** - a small JPEG (~100px) used in search results, browse lists and the tree.

The reference and thumbnail are generated for you the moment a master is uploaded. If a record has a master but **no** reference or thumbnail, the viewer has nothing to display and the record looks empty even though the file is safely on disk. That distinction matters later.

Because a description holds one primary image, **a set of photographs becomes a set of child descriptions** - one per image - under a parent that represents the whole grouping (a file, an album, a site, a family collection). That is the structure the public tree and the image gallery both expect.

---

## 2. Route A - one file on one description

Use this when a description should carry a single image or document.

1. Open the description and choose **Edit** (or create a new one).
2. Scroll to the **Digital object** area.
3. Drag the file onto the upload box, or click to browse.
4. Save.

Heratio stores the master under the record's own folder and builds the reference and thumbnail. Reload the public page - the image appears inline, with a thumbnail in any list that shows the record.

Supported out of the box: JPEG, PNG, TIFF, PDF, common audio and video, and 3D models. TIFFs and PDFs are rasterised to a JPEG reference so they render in any browser.

---

## 3. Route B - a folder of images under one parent

This is the route for "I have twenty photographs that all belong to this collection." Each file becomes its own child description, correctly titled from the filename, each with its own image and derivatives. An administrator runs this once from the server.

The parent must already exist. In the worked example the parent is **Mobrey Family Archive** (slug `mobrey-family-archive`).

```bash
sudo -u www-data php artisan ahg:load-digital-objects \
    --path=/path/to/folder-of-images \
    --attach-to=mobrey-family-archive
```

What happens for each file in the folder:

- a child description is created under the parent, titled from the filename;
- the file is uploaded as the master and the reference + thumbnail are generated;
- a URL-friendly slug is assigned.

Two follow-up steps finish the job (also administrator, run once after a batch):

```bash
# make the new children visible to the public (they start as drafts)
#   - set publication status to Published on each, then:

sudo -u www-data php artisan ahg:nested-set-rebuild   # slots children into the tree
sudo -u www-data php artisan ahg:build-closure        # rebuilds the hierarchy index
```

The rebuild walks the whole catalogue, so on a large site it can take several minutes - that is normal. Until it finishes, the child pages themselves display fine; only their position in the parent's tree is pending.

### Worked result - Mobrey Family Archive

Seven images were loaded under Mobrey Family Archive:

- Glass bead assemblage, small find
- Glass bead assemblage, small find 221
- Ostrich eggshell beads, small find 238
- Potsherds, small find 215
- Archaeological bead image
- rama
- a dated field photograph

Each became a published child with a viewable image. Opening any child (for example `/glass-bead-assemblage-small-find`) shows the photograph; the parent lists all seven in its tree.

---

## 4. Viewing and troubleshooting

**The record page is blank / no image.** The master is probably there but the derivatives are missing (common after a bulk load that skipped the derivative step, or an import from another system). Regenerate them:

```bash
sudo -u www-data php artisan ahg:regen-derivatives --type=all
# or, per package, the media derivative service can be run for one master id
```

**The image shows but the record is not in the tree / not in search.** Run `ahg:nested-set-rebuild` then `ahg:build-closure`; for search visibility also run `ahg:search-update`.

**The public cannot see it, but you can.** New records are **drafts** until published. Set the publication status to **Published** on the record (or across the batch) and the anonymous public page returns instead of a 404.

---

## 5. Archivematica preservation (optional, advanced)

Heratio can hand a record's files to an Archivematica pipeline for full preservation processing (format identification, virus scan, checksums, AIP storage) and take back an access copy (a DIP) linked to the original record. This is a round-trip:

1. **Send** - from a record's admin actions, "Send to Archivematica" stages its files and starts a transfer. Heratio writes a processing configuration so the pipeline runs unattended and stores both the AIP and the DIP.
2. **Preserve** - Archivematica does its work; the AIP lands in its Storage Service.
3. **Return** - Heratio polls for finished DIPs and attaches the access files back to the matching record (matched on the record identifier carried through the transfer).

This is for institutions running an Archivematica server. It is configured under **Admin - Archivematica** (Storage Service URL, Dashboard URL, API keys, transfer paths). None of it is needed for ordinary cataloguing; Routes A and B above do not touch Archivematica.

> Note on grouping: the Archivematica return path attaches a DIP's files to the **single** record it matches by identifier. When you specifically want each image as its own child under a chosen parent, use Route B (`ahg:load-digital-objects`), which is built for that shape.

---

## 6. What runs automatically (cron)

Heratio schedules its background work in a database table (**Admin - System - Scheduled tasks**), driven by one system cron that ticks every minute and runs whatever is due. On this platform 98 of 101 tasks are enabled by default, covering search indexing, derivative regeneration, backups, preservation fixity, notifications and more. You manage them from that admin screen - enable, disable, or change timing - without touching the server.

Relevant to the workflows above:

| Task | Command | When | Purpose |
|------|---------|------|---------|
| Regenerate derivatives | `ahg:regen-derivatives --type=all` | Weekly (Sun 02:00) | Rebuilds any missing reference/thumbnail images |
| Search update | `ahg:search-update` | Every 5 min | Keeps new records searchable |
| Browse facet refresh | `ahg:display-reindex` | Weekly | Rebuilds browse counts and facets |
| Daily backup | `ahg:backup --components=database` | Daily 02:00 | Database backup |

### Archivematica scheduling

The Archivematica round-trip needs two commands running on a timer:

| Task | Command | Timing | Purpose |
|------|---------|--------|---------|
| Advance AM transfers | `am:poll` | Every 5 minutes | Moves in-flight Heratio - AM transfers to completion |
| Ingest finished DIPs | `am:ingest-dips` | Every 15 minutes | Pulls completed access copies back into Heratio |

On the **dev** instance - the one wired to the Archivematica server - these are installed as a dedicated cron at `/etc/cron.d/heratio-am-dev` (running as `www-data`, logging to `/var/log/heratio-am.log`). Dev's general scheduler is disabled, so the AM commands run on their own from that file rather than through the scheduled-tasks table.

On any instance that uses the database scheduler (the demo runs `schedule:run` every minute), add the same two as tasks in **Admin - System - Scheduled tasks** instead - but only once that instance is pointed at an Archivematica server under **Admin - Archivematica**.

A third command, `am:ping`, checks that the servers are reachable - handy when diagnosing a connection. To advance a transfer by hand at any time:

```bash
sudo -u www-data php artisan am:poll          # advance transfers you have sent
sudo -u www-data php artisan am:ingest-dips    # pull back finished access copies
```

---

## 7. Quick reference

| I want to... | Do this |
|--------------|---------|
| Add one image to a description | Edit the record - Digital object - drag file - Save |
| Load a folder of images under a parent | `ahg:load-digital-objects --path=... --attach-to=<parent-slug>` (admin) |
| Fix a blank/imageless record | `ahg:regen-derivatives --type=all` (admin) |
| Put new records into the tree | `ahg:nested-set-rebuild` then `ahg:build-closure` (admin) |
| Make records public | Set publication status to Published |
| Send a record to Archivematica | Record admin actions - Send to Archivematica |
| Automate Archivematica | Add `am:poll` and `am:ingest-dips` in Scheduled tasks |

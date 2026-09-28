> Heratio Help Center article. Category: Collections Management.

# Physical Storage Management

Track where your physical holdings actually live. The Storage Management module records storage locations and containers (boxes, shelves, racks, folders, and similar), captures their building/floor/room/aisle/bay/rack/shelf/position coordinates, optional barcodes, capacity and environmental data, and links them to the archival descriptions and accessions they hold. It also models strongrooms as space-allocation units so you can see how much of each room's capacity is in use. This is about the physical whereabouts of your collections, not about file or disk storage.

## Overview

Every archival item has to be findable on a shelf, not just in the catalogue. The module gives you four related but separate tools:

- **Storage locations** - the building itself, as a tree. A building holds floors, a floor holds rooms, a room holds aisles, bays, racks and shelves, as deep as your building goes. Renaming a room renames it once, and everything under it stays where it is.
- **Physical storage objects** - the containers themselves (a box, a folder, a map drawer). Each one has a name, a type, a free-text location, and an extended record covering precise coordinates, dimensions, capacity, climate control, and security level. Storage objects are linked to the archival descriptions and accessions stored inside them, so the show page for a container tells you exactly what is in it.
- **The movement log** - every move a container makes between locations, and every move a location makes within the tree. It is the record of where your holdings have been, kept permanently.
- **Strongrooms** - higher-level rooms with a stated capacity. Physical storage objects are assigned into a strongroom and record how much of the room's capacity they consume, so you get a live used/remaining picture per room. A strongroom cannot be deleted while it still has occupants.

Together these answer the everyday questions: "where is this record kept?", "where has it been?", and "how full is that room?"

## Key features

- Storage locations as a tree: a building with floors, rooms, aisles, bays, racks and shelves nested as deep as you need, with a page per location showing its path, its children and everything beneath it.
- A movement log recording every move, with who made it, when, and why.
- Move a single container from its page, or empty a shelf by moving many containers at once from the location that holds them.
- Browse, search, view, create, edit, and delete physical storage objects (containers and locations).
- Rich extended location data: building, floor, room, aisle, bay, rack, shelf, position, barcode, reference code, dimensions (width/height/depth), capacity, linear metres, climate control with temperature and humidity ranges, security level, access restrictions, and status.
- Link a storage container to one or many archival descriptions, and view linked accessions.
- A per-container box list showing the holdings inside, with built-up reference codes, dates, and the collection each item belongs to.
- A holdings report exported as CSV (name, type, location) for the whole storage register.
- Strongroom CRUD with capacity tracking in linear metres, shelves, boxes, or cubic metres.
- Strongroom occupancy view: which containers are assigned, how much each consumes, and how much capacity remains.
- Assign or unassign a container to a strongroom directly from the storage edit form.

## How to use

### Build your storage tree

1. Go to **`/storagelocation/browse`** to see every location, as a tree and as a searchable list. You must be signed in: the layout of a building and its security levels are staff-only.
2. Add a location at **`/storagelocation/add`**, or use **Add child location** on a location's page to create one directly underneath it. Give it a name and a type (building, floor, room, aisle, bay, rack, shelf, container, storage unit), and optionally a capacity and notes.
3. Open a location at **`/storagelocation/{slug}`** to see its path from the building down, its child locations, everything beneath it at any depth, the objects currently in it, and its movement history.
4. Edit at **`/storagelocation/{slug}/edit`**. Changing the parent moves the location and everything under it in one step, and records one movement event. The parent picker leaves out the location's own subtree, because a room cannot be moved inside itself.
5. Delete at **`/storagelocation/{slug}/delete`** (administrator only). A location cannot be deleted while it has child locations, while it still holds objects, or once it appears in the movement history. The first two are tidiness; the third is deliberate, and explained under the movement log below.

Start with the building and work down. A location's depth is whatever your building needs; there is no fixed set of levels.

### Move a container to a location

1. Open the container and choose **Move**, or go to **`/physicalobject/{slug}/move`**.
2. Pick the destination location. The container's current location is shown at the top and cannot be picked again, since moving something to where it already is would record a move that never happened.
3. To take a container out of storage entirely rather than moving it elsewhere, tick **Remove from storage**. This is a deliberate choice, not a blank destination, so the log can tell "left the building" apart from "moved to Room B".
4. Add a note saying why it moved. Optional, but it is the field people are most grateful for a year later.
5. Save. The container's page now shows its location as a clickable path from the building down, with its full movement history underneath.

### Move many containers at once

1. Open the location that currently holds them, at **`/storagelocation/{slug}`**.
2. In the **Objects here** card, tick the containers to move, or use the header checkbox to take the lot.
3. Choose the destination (or tick **Remove from storage**), add a note, and choose **Move selected**.

Everything moves together in one step. Each container gets its own entry in the history, and they share a batch so the whole relocation can be read back as one event. Containers already in the destination are skipped rather than recorded as moves that did not happen.

### Read the movement history

Every location page carries a **Movement history** card, and every container page shows its own history. Each entry gives the date, what moved, where from, where to, who moved it, and the note.

Two things are worth knowing about how it reads:

- For a **container**, an empty "from" means this was its first placement, and an empty "to" means it was removed from storage.
- For a **location**, the from and to columns hold its **old and new parent**, and an empty one means the top of the tree. A location entry is marked with a `location` badge so it is not mistaken for a container.

The history is permanent and cannot be edited. If a move was recorded wrongly, record another move that puts things right; the correction becomes part of the record. A history someone can quietly rewrite proves nothing about the custody of the holdings, which is the only reason to keep one. That is also why a location named in the history cannot be deleted: removing it would take part of the record with it.

### Browse and search physical storage

1. Go to **`/physicalobject/browse`**.
2. Use the inline search box to filter by name, and the column headers to sort by Name or Location.
3. Click any row to open the storage object's show page, which lists its type, location, linked archival descriptions, and linked accessions.

### Add a new storage container or location

1. From the browse page (or directly at **`/physicalobject/add`**), choose to add a new physical storage object. You must be signed in.
2. Enter a **Name** (required) and pick a **Type** (box, shelf, folder, and so on).
3. Fill in the **Location** free-text field and the extended fields you need: building, floor, room, aisle, bay, rack, shelf, position, barcode, dimensions, capacity, climate control, security level, and status.
4. Optionally assign the container to a strongroom and record the capacity it uses (see below).
5. Save. You are taken to the new container's show page.

### Edit or delete a container

1. Open the container, then go to **`/physicalobject/{slug}/edit`** to change any field. Editing requires sign-in and update permission.
2. To remove a container, use **`/physicalobject/{slug}/delete`**. The confirmation page lists any archival descriptions still linked so you can check before removing. Deletion requires administrator access.

### Link containers to archival descriptions

1. From an archival description, open the link page at **`/physicalobject/link-to/{slug}`**.
2. Either link an existing container by searching for it, or create a new container inline with its type, location, and coordinates in one step.
3. Linked containers appear in a table; use the unlink action (**`/physicalobject/unlink/{relationId}`**) to remove a link without deleting the container.

### View what is inside a container (box list)

1. Go to **`/physicalobject/box-list`** with the container slug to see every holding inside it, complete with reference code, dates, and parent collection.

### Export a holdings report

1. Go to **`/physicalobject/holdingsReportExport`** to download a CSV of every storage object with its name, type, and location.

### Manage strongrooms

1. Go to **`/strongroom/browse`** to see all strongrooms with their used and remaining capacity and occupant counts. Use the search box to filter by name or location description.
2. Add a strongroom at **`/strongroom/add`**: give it a name, an optional location description, a capacity value, a capacity unit (linear metres, shelves, boxes, or cubic metres), and notes.
3. Open a strongroom at **`/strongroom/{slug}`** to see its occupants, total used capacity, and remaining capacity.
4. Edit at **`/strongroom/{slug}/edit`**; delete at **`/strongroom/{slug}/delete`** (administrator only). A strongroom with occupants cannot be deleted until they are moved out.

### Assign a container to a strongroom

1. On the container's edit form, choose a strongroom and enter the number of capacity units it consumes, then save. Each container lives in at most one strongroom.
2. To remove the assignment, choose unassign on the edit form and save.

## Configuration

- **Storage location types** come from the Dropdown Manager taxonomy `storage_location_type` at **`/admin/dropdowns`** (building, floor, room, aisle, bay, rack, shelf, container, storage unit by default). Add the levels your building actually has, and they appear in the location forms. Capacity units come from the `capacity_unit` taxonomy.
- **Permissions on locations and moves**: reading needs a sign-in, creating, editing and moving need update permission, and deleting a location needs administrator access. The whole storage surface is staff-only, so an anonymous visitor cannot map your building.
- **Container and location types** are not hardcoded. They are drawn from the controlled taxonomies and managed in the **Dropdown Manager at `/admin/dropdowns`**. Add or rename storage-container types there and they appear automatically in the storage and link-to forms - never edit option lists in code.
- **Strongroom capacity units** are fixed to four values: linear metres, shelves, boxes, and cubic metres. Each strongroom is set to one of these when created or edited.
- **Permissions** follow the standard access model. Browsing and viewing are open; creating and editing require sign-in plus the matching create/update permission; deleting requires administrator access.
- **Strongroom feature availability**: if the strongroom tables have not been installed, the storage forms simply hide the strongroom assignment controls and continue to work for plain container management.

## References

- Source package `packages/ahg-storage-manage/`
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/144 (Strongroom space allocation; the package also predates this for general physical storage management)
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/1514 (hierarchical storage locations and the movement log, shipped in v1.155.0 and v1.156.0)
- The same tables and behaviour are available in AtoM through `ahgStorageManagePlugin`, so an institution running both reads one storage history.

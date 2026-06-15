> Heratio Help Center article. Category: Collections Management.

# Physical Storage Management

Track where your physical holdings actually live. The Storage Management module records storage locations and containers (boxes, shelves, racks, folders, and similar), captures their building/floor/room/aisle/bay/rack/shelf/position coordinates, optional barcodes, capacity and environmental data, and links them to the archival descriptions and accessions they hold. It also models strongrooms as space-allocation units so you can see how much of each room's capacity is in use. This is about the physical whereabouts of your collections, not about file or disk storage.

## Overview

Every archival item has to be findable on a shelf, not just in the catalogue. The module gives you two related but separate tools:

- **Physical storage objects** - the containers and locations themselves (a box, a shelf, a folder, a map drawer). Each one has a name, a type, a free-text location, and an extended record covering precise coordinates, dimensions, capacity, climate control, and security level. Storage objects are linked to the archival descriptions and accessions stored inside them, so the show page for a container tells you exactly what is in it.
- **Strongrooms** - higher-level rooms with a stated capacity. Physical storage objects are assigned into a strongroom and record how much of the room's capacity they consume, so you get a live used/remaining picture per room. A strongroom cannot be deleted while it still has occupants.

Together these answer two everyday questions: "where is this record kept?" and "how full is that room?"

## Key features

- Browse, search, view, create, edit, and delete physical storage objects (containers and locations).
- Rich extended location data: building, floor, room, aisle, bay, rack, shelf, position, barcode, reference code, dimensions (width/height/depth), capacity, linear metres, climate control with temperature and humidity ranges, security level, access restrictions, and status.
- Link a storage container to one or many archival descriptions, and view linked accessions.
- A per-container box list showing the holdings inside, with built-up reference codes, dates, and the collection each item belongs to.
- A holdings report exported as CSV (name, type, location) for the whole storage register.
- Strongroom CRUD with capacity tracking in linear metres, shelves, boxes, or cubic metres.
- Strongroom occupancy view: which containers are assigned, how much each consumes, and how much capacity remains.
- Assign or unassign a container to a strongroom directly from the storage edit form.

## How to use

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

- **Container and location types** are not hardcoded. They are drawn from the controlled taxonomies and managed in the **Dropdown Manager at `/admin/dropdowns`**. Add or rename storage-container types there and they appear automatically in the storage and link-to forms - never edit option lists in code.
- **Strongroom capacity units** are fixed to four values: linear metres, shelves, boxes, and cubic metres. Each strongroom is set to one of these when created or edited.
- **Permissions** follow the standard access model. Browsing and viewing are open; creating and editing require sign-in plus the matching create/update permission; deleting requires administrator access.
- **Strongroom feature availability**: if the strongroom tables have not been installed, the storage forms simply hide the strongroom assignment controls and continue to work for plain container management.

## References

- Source package `packages/ahg-storage-manage/`
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/144 (Strongroom space allocation; the package also predates this for general physical storage management)

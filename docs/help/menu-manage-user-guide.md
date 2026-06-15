> Heratio Help Center article. Category: Administration.

# Menu Manager

The Menu Manager lets administrators edit the site's navigation menus - the items that appear in the main navigation and its sub-menus. You can rename the user-facing labels, point items at internal or external paths, add and remove your own items, nest them into a hierarchy, and reorder siblings up and down. Core system menus are protected so navigation never breaks.

---

## Overview

Heratio's navigation is data-driven. The menu structure is stored in the database as a tree, and the Menu Manager is the administrative interface over that tree. Rather than editing template files, you adjust menu items through forms at **`/admin/menu/browse`**.

The tree is stored using a nested-set model (each row has `lft` and `rgt` boundary values plus a `parent_id`), which lets Heratio represent arbitrary depth and ordering efficiently. Each item also has a translatable side: the **label** (what users see) and an optional **description** live in a per-language `menu_i18n` table, so the same menu can carry different labels for different site languages.

There is an invisible **root** node at the top of the tree (the system root). Every real menu hangs underneath it. The browse screen hides the root and shows everything below it.

Two kinds of fields matter:

- **Name** - the internal identifier of the item. Not shown to visitors. Used by the system to recognise core menus.
- **Label** - the visible text in the navigation. Leave it blank for organisational-only containers that should not render as a clickable link.

---

## Key features

### Manageable navigation menus

The Menu Manager manages the standard navigation tree built on the base `menu` / `menu_i18n` tables. The well-known top-level and structural menus include the main menu and its core children - **browse**, **add**, **manage**, **import**, **admin**, the institution browse menu, the static-pages menu, and the clipboard. These appear in the tree alongside any custom items you add.

### Protected core menus

Certain menus are essential to the application and are marked **protected**. The protected names are:

`mainMenu`, `browse`, `add`, `manage`, `import`, `admin`, `browseInstitution`, `staticPagesMenu`, `clipboard` - plus the invisible root node.

For a protected item:

- It **cannot be deleted**. The Delete button is hidden and the service refuses the operation.
- Its internal **name cannot be changed** (the Name field is hidden on its edit form).
- You **can** still edit its user-facing **label**, **path** and **description**, and you can reorder it.

The detail page shows a warning banner when an item is protected.

### Full CRUD on custom items

For items you create yourself you have the complete set:

- **Create** a new menu item under any parent.
- **Edit** its name, label, path, parent and description.
- **Delete** it (which also removes any descendants beneath it).
- **Move** it up or down among its siblings.

### Hierarchy and nesting

Every item has a **Parent**. When you create or edit an item you choose its parent from a dropdown that lists the whole tree, indented by depth, with a **(Top level)** option at the top for items that should hang directly off the root. Changing the parent moves the item - and its entire subtree - to the new location in the tree.

### Ordering

Sibling items (items sharing the same parent) can be reordered with the up and down arrows on the browse screen. Moving an item up swaps it with the previous sibling; moving it down swaps it with the next. The arrows only appear when there is a sibling to swap with in that direction.

### Translatable labels and descriptions

The **label** and **description** are stored per language (culture). Editing them affects the language you are currently working in, so a menu can present localised text in each of the site's languages.

---

## How to use

### Open the Menu Manager

1. Sign in as an administrator. All menu routes are behind the `admin` middleware.
2. Go to **`/admin/menu/browse`** (the legacy URL `/admin/menus` redirects here).

You see the **Site menu list** - a hierarchical table with two columns, **Name** and **Label** - showing every menu below the root, indented by depth. Top-level items are shown in bold.

### View an item's detail

1. From the browse list, you can open an item's edit form directly by clicking its name, or open its detail page at **`/admin/menu/{id}`**.
2. The detail page lists Name, Label, Description, Path, Parent ID, the `lft`/`rgt` boundaries, serial number, source culture, and created/updated timestamps.
3. Any direct **children** are listed in a table below, each linking to its own detail page.

### Add a new menu item

1. On the browse screen, click **Add new** (or go to **`/admin/menu/add`**).
2. Fill in the form:
   - **Name** - internal identifier (optional; not shown to users).
   - **Label** - the visible navigation text. Leave blank for an organisational-only container.
   - **Parent** - choose where it sits. Pick **(Top level)** to attach it directly under the root.
   - **Path** - the link target: an external URL, or an internal application path.
   - **Description** - a short note on the item's purpose.
3. Click **Save**. The item is inserted as the last child of the chosen parent, and you are taken to its detail page with a success message.

The only required field is **Label** (maximum 255 characters). Name and Path are each up to 255 characters; Description is free text.

### Edit a menu item

1. From the browse list click the item's name, or go to **`/admin/menu/{id}/edit`**.
2. Change the **Label**, **Path**, **Parent** or **Description**. For non-protected items you can also change the **Name**.
3. Click **Save**. If you changed the parent, the item (and its subtree) is moved accordingly. A success message confirms the update.

The edit form also offers **Cancel** (returns to the list) and, for non-protected items, a **Delete** link.

### Reorder items

1. On the browse screen, find the item you want to move.
2. Click the **up arrow** to swap it with the sibling above it, or the **down arrow** to swap with the sibling below it.
3. The list refreshes in the new order. Arrows are only shown where a sibling exists in that direction.

### Delete a menu item

1. Open the item's edit page or detail page and click **Delete**, or go to **`/admin/menu/{id}/delete`**.
2. The confirmation screen asks you to confirm.
3. Confirm to remove the item. Note that deleting an item also deletes every item nested beneath it.
4. Protected items cannot be deleted - the Delete option is not offered for them, and the operation is refused with an error message if attempted.

---

## Routes

| Method | URI | Action | Purpose |
|--------|-----|--------|---------|
| GET | `/admin/menu/browse` | `browse` | Show the full menu tree. |
| GET | `/admin/menu/add` | `create` | Show the create form. |
| POST | `/admin/menu/add` | `store` | Save a new menu item. |
| GET | `/admin/menu/{id}/edit` | `edit` | Show the edit form. |
| POST | `/admin/menu/{id}/edit` | `update` | Save changes to an item. |
| GET | `/admin/menu/{id}/delete` | `confirmDelete` | Show the delete confirmation. |
| DELETE | `/admin/menu/{id}/delete` | `destroy` | Delete an item and its descendants. |
| POST | `/admin/menu/{id}/move-up` | `moveUp` | Swap the item with its previous sibling. |
| POST | `/admin/menu/{id}/move-down` | `moveDown` | Swap the item with its next sibling. |
| GET | `/admin/menu/{id}` | `show` | View an item's detail and its children. |
| GET | `/admin/menus` | (redirect) | Legacy alias - redirects to `/admin/menu/browse`. |

All `/admin/menu/...` routes (except the legacy redirect) run under the `admin` middleware, so administrator rights are required.

---

## Configuration

The Menu Manager has no separate settings screen - the menus themselves are the configuration.

- **Access** - the routes are gated by the `admin` middleware; only administrators can manage menus.
- **Storage** - menus live in the base `menu` table (structure, name, path, `lft`/`rgt`, parent) and `menu_i18n` (label and description per language). No custom tables are added by this package; it works on the standard navigation tables.
- **Protected names** - the protected set (`mainMenu`, `browse`, `add`, `manage`, `import`, `admin`, `browseInstitution`, `staticPagesMenu`, `clipboard`) is fixed in the menu service. These keep the core navigation intact; their labels, paths and descriptions remain editable.
- **Languages** - labels and descriptions are edited in the active site language (culture). Switch language to maintain the navigation text for another locale.
- **Path values** - a path may be an external URL or an internal application path; leave it blank for a container item that only groups children and is not itself a link.

---

## References

- **Source package:** `packages/ahg-menu-manage/`
- **Controller:** `packages/ahg-menu-manage/src/Controllers/MenuController.php`
- **Service:** `packages/ahg-menu-manage/src/Services/MenuService.php`
- **Views:** `packages/ahg-menu-manage/resources/views/browse.blade.php`, `edit.blade.php`, `show.blade.php`, `delete.blade.php`
- **Routes:** `packages/ahg-menu-manage/routes/web.php`
- **Tables:** `menu`, `menu_i18n`
- **GitHub issue:** [#597](https://github.com/ArchiveHeritageGroup/heratio/issues/597)

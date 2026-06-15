> Heratio Help Center article. Category: Administration / Configuration.

# Dropdown Manager

The Dropdown Manager is the central place to manage every controlled vocabulary (dropdown / select option / status value) used across Heratio. It lives at `/admin/dropdowns` and edits the values held in the `ahg_dropdown` table - the single source of truth for enumerated values in the platform. Administrators can create and rename taxonomies, add, edit, reorder, recolour, activate or delete terms, set a default term, and author per-language translations of labels. The same screen can also surface labels from two read-mostly backends (the AtoM-style `term` taxonomy and `setting` tables) so their translations can be managed alongside the native dropdowns.

## Overview

Heratio never hardcodes enumerated values and never uses MySQL `ENUM` columns. Status values, type lists, condition states, equipment and seat types, ID types, and every other select list come from the `ahg_dropdown` table and are surfaced through the Dropdown Manager. Each taxonomy is a named group of values (for example `seat_type`, `equipment_type`, `id_type`, `condition_status`), and each value is a term with a code, a display label, an optional colour and icon, a sort order, an active flag, and a default flag.

Taxonomies are organised into sections (such as Core and System, Condition and Conservation, People and Organisations) for navigation. The manager renders as a Bootstrap 5 accordion with a section sidebar, live search and filter, and inline AJAX editing - changes apply without a full page reload.

### Three sources

Phase 3 of the dropdown work (issue #59) introduced a source dispatcher so the editor can open values from three backends. The source is part of the edit URL:

- `ahg_dropdown` - the native AHG sidecar dropdowns. Full create / edit / reorder / delete and translation.
- `term` - AtoM-style taxonomy terms (`term` + `term_i18n`), keyed by numeric taxonomy id. Translation-only here; structural CRUD stays in the core taxonomy tools.
- `setting` - named settings scopes (`setting` + `setting_i18n`). Translation-only.

For the `term` and `setting` sources the editor shows only the side-by-side label translator; Add and Delete are disabled with an explanatory notice.

## Key features

- **Index of all taxonomies** grouped by section, with per-taxonomy term counts and totals (taxonomies, terms, column mappings).
- **Section sidebar** with a section filter box, click-to-scroll, and Expand All / Collapse All controls.
- **Taxonomy search** that filters rows by label or code and auto-expands matching sections.
- **Create taxonomy** - modal that captures section, display name, and code (the code is auto-derived from the display name, lowercase with underscores). Creates the taxonomy with a single default placeholder term.
- **Rename taxonomy** - change a taxonomy's display label.
- **Move section** - reassign a taxonomy to a different section.
- **Delete taxonomy** - remove a taxonomy and all of its terms (confirmation required).
- **Per-term editing on the edit page:**
  - Inline label editing (edit in place, save on blur or Enter, Escape to cancel).
  - Colour picker per term.
  - Default radio - exactly one default per taxonomy; setting a new one clears the old.
  - Active checkbox - deactivate a value without deleting it.
  - Delete term (confirmation required); if the deleted term was the default, the first remaining term becomes the new default.
  - Drag-to-reorder via a drag handle; the new order is persisted as `sort_order`.
  - Add term modal capturing label, code (auto-derived), optional colour and optional icon.
  - Show / hide inactive toggle.
- **Side-by-side label translator** on the edit page: read-only English (en) source label on the left, an editable target-culture input on the right, with a target-culture selector and per-row Save.
- **Column mappings panel** - where a taxonomy is bound to specific database columns, the edit page lists each table and column and whether the binding is strict (only dropdown values allowed) or non-strict (freetext allowed).
- **Translation workflow split** - administrators apply translations directly; users with editor (translate) rights queue a draft for review instead.

## How to use

### Opening the Dropdown Manager

Go to:

```
/admin/dropdowns
```

The route is named `dropdown.index` and is protected by the `admin` middleware - you need administrator access. The legacy URL `/admin/dropdown` (singular) permanently redirects here.

The landing page lists every active taxonomy grouped by section. Use the sidebar to jump to a section, the **Filter sections** box to narrow the sidebar, or the **Search taxonomies** box to find a taxonomy by label or code. **Expand All** / **Collapse All** open or close every section.

### Creating a taxonomy

1. Click **Create Taxonomy** in the sidebar.
2. Choose a **Section**, enter a **Display Name**, and confirm the **Code** (auto-filled from the display name; lowercase letters, numbers, and underscores only).
3. Click **Create**. The taxonomy is created with one default term ("Default") that you can then rename or replace.

Codes must be unique; creating a taxonomy with an existing code is rejected.

### Managing terms in a taxonomy

1. From the index, click a taxonomy name or its edit (pencil) button. This opens:
   ```
   /admin/dropdowns/ahg_dropdown/{taxonomy}/edit
   ```
   The older URL `/admin/dropdowns/{taxonomy}/edit` (without a source) permanently redirects to the `ahg_dropdown` source.
2. The edit page header shows the taxonomy label and a badge for the source. The sidebar shows the code, section, and term counts, plus column mappings if any exist.
3. Edit terms in the lower table:
   - Click a label to edit it inline; press Enter or click away to save, Escape to cancel.
   - Click the colour swatch to set a term colour.
   - Select the **Default** radio to make a term the taxonomy default.
   - Tick or untick **Active** to enable or disable a value.
   - Drag the handle on the left to reorder; the order saves automatically.
   - Click the red delete button to remove a term (you will be asked to confirm).
   - Use **Show inactive** to hide or reveal deactivated values.
4. To add a value, click **Add Term**, enter a label and code (auto-derived), optionally pick a colour and icon, then click **Add**.

All of these actions are AJAX calls that update the `ahg_dropdown` table immediately.

### Renaming, moving, or deleting a taxonomy

From the index, each taxonomy row has buttons to:

- **Rename** (pencil) - change its display label.
- **Move Section** (arrows) - choose a new section.
- **Delete** (trash) - remove the taxonomy and all its terms after confirmation.

### Translating labels

On any edit page (any of the three sources) the **Translate labels (side-by-side)** panel lets you author per-language labels:

1. Pick a **Target culture** from the selector (enabled locales other than English).
2. Each row shows the read-only English source label and an editable target input.
3. Edit the target label and click the row's **Save** button.
4. If you are an administrator, the translation is applied directly to the source's i18n table and the row shows "saved". If you have editor (translate) rights only, the edit is queued as a draft for a second administrator to review, and the row shows "queued for review (draft #...)".

For `ahg_dropdown`, translations are written to `ahg_dropdown_i18n`; for `term`, to `term_i18n` (`name`); for `setting`, to `setting_i18n` (`value`). Queued drafts are recorded in `ahg_translation_draft` and reviewed on the translation drafts admin page.

## Configuration

- **Access** - the Dropdown Manager is administrator-only (`admin` middleware). Translation drafting is available to users with editor (translate) rights, but their edits are queued rather than applied.
- **Storage** - native dropdowns live in `ahg_dropdown` (parent values plus `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `taxonomy`, `taxonomy_label`, `taxonomy_section`). Per-language labels live in `ahg_dropdown_i18n`.
- **i18n table install** - the package installs `ahg_dropdown_i18n` automatically on boot if it is missing (from `database/install_i18n.sql`) and back-fills an English (en) row for every dropdown value that lacks one. This is idempotent and safe to run repeatedly; on a fresh install it simply waits until `ahg_dropdown` exists. Read paths fall back through current-culture label, then en label, then the parent `ahg_dropdown.label`, so installs without the i18n table still render correctly.
- **Sections** - the section list and their labels and icons are defined in the controller (for example Access and Research, AI and Automation, Condition and Conservation, Core and System, Digital Assets and Media, People and Organisations, Privacy and Compliance, and others). Any taxonomy whose section is unknown is grouped under "Other".
- **Enabled locales** - the target-culture list for translation comes from the `setting` scope `i18n_languages` (managed on the Languages admin page); if empty it falls back to the available `lang/*.json` files. English is always included as the source culture.
- **Column mappings** - bindings between a taxonomy and specific table columns are held in `ahg_dropdown_column_map` and shown read-only on the edit page, including whether each binding is strict.

## References

- Source: packages/ahg-dropdown-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/565

> Heratio Help Center article. Category: Cataloguing & Description.

# Forms & Templates

The Forms module lets administrators build custom metadata-entry templates, assign them to repositories, collections, or description levels, and drive editing of records (information objects, authority records, repositories, accessions) through those templates. This guide covers the template builder, field types, assignments, template-driven editing, and the submission audit trail.

---

## Overview

A **form template** is a reusable definition of which fields appear when a record is created or edited, how those fields are grouped (sections and tabs), and which database columns each field writes to. Templates let an institution tailor cataloguing to a standard (for example ISAD(G) or Dublin Core) or to a specialised collection (for example photographs).

The module has four moving parts:

- **Templates** (`ahg_form_template`) - the named forms, each tied to a record type (information object, accession, authority record, repository, and so on).
- **Fields** (`ahg_form_field`) - the inputs on a template, with type, label, help text, ordering, and validation.
- **Field mappings** (`ahg_form_field_mapping`) - the link between a form field and the actual database table / column it reads from and writes to, including i18n (translated) targets and optional value transformations.
- **Assignments** (`ahg_form_assignment`) - rules that decide which template applies to which records, scoped by repository, description level, and/or collection, with a priority order.

Two more tables support runtime behaviour: drafts (`ahg_form_draft`) for autosave, and a submission log (`ahg_form_submission_log`) for the audit trail of every save made through a template.

Heratio ships a starter **library** of system templates: ISAD-G Minimal, ISAD-G Full (all 26 elements across 7 areas), Dublin Core Simple (15 core elements), Accession Standard, and Photo Collection Item.

---

## Key features

- **Visual form builder** - add, edit, reorder, and delete fields on a template.
- **Fourteen field types** covering text, structured input, choices, files, and layout elements.
- **Sections and tabs** to group fields, with single, sectioned, or tabbed layouts.
- **Field-to-column mapping** so a single form can write to the record table and its translated i18n table in one save, including value transformations (uppercase, lowercase, trim, prefix, JSON array).
- **Context-aware assignment** - the right template is chosen automatically for a record based on its repository, description level, and parent collection, with a priority tie-breaker.
- **Template-driven editing** - edit an existing record through a chosen or auto-resolved template; all writes happen in a single database transaction.
- **Autosave drafts** - in-progress form data is saved per user, template, and record so work is not lost.
- **JSON export** - download any template (with its fields) as a JSON file for backup or transfer.
- **Duplicate / clone** templates as a starting point for a new form.
- **Submission audit log** - every template-driven save is recorded with the user, record, timestamp, and submitted values.
- **Statistics** - counts of templates (by type), fields, assignments, pending drafts, and submissions in the last 30 days.

---

## Field types

The builder offers these field types:

| Type | Use |
|---|---|
| `text` | Single-line text input |
| `textarea` | Multi-line plain text |
| `richtext` | Rich text editor |
| `date` | Date picker |
| `daterange` | Date range (start / end) |
| `select` | Single-choice dropdown |
| `multiselect` | Multiple-choice select |
| `autocomplete` | Type-ahead lookup (for example actors, taxonomy terms, repositories) |
| `checkbox` | Single checkbox / boolean |
| `radio` | Radio button group |
| `file` | File upload |
| `hidden` | Hidden value |
| `heading` | Section heading (layout only) |
| `divider` | Visual divider (layout only) |

Each field can carry: an internal field name, a visible label (with translated labels), help text, a placeholder, a default value, validation rules, options (for select / multiselect / radio), an autocomplete source (for example `taxonomy:subject`, `actor:creator`, `repository:all`), a section and tab name, a sort order, a width (full / half / third / quarter), and the required / read-only / hidden / repeatable flags.

---

## How to use

All forms screens are under the `/forms` prefix and require an authenticated administrator. A set of aliases under `/admin/formTemplates` points to the same screens for menu and report links.

### Open the forms dashboard

Go to **`/forms`** (route `forms.index`). The dashboard lists existing templates and shows statistics: template counts by type, total fields, assignments, pending drafts, and submissions in the last 30 days.

### Browse templates

Go to **`/forms/browse`** (route `forms.browse`). Filter by record type or search by name / description. Each template shows its field count.

### Create a template

Go to **`/forms/template/create`** (route `forms.template.create`).

1. Enter a name, an optional description, and the record type (information object, authority record, repository, accession, deaccession, or rights).
2. Choose a layout (single, sectioned, or tabbed).
3. Submit. You are taken straight to the builder for the new template.

### Build the form

Open **`/forms/builder/{id}`** (route `forms.builder`). In the builder you can:

- **Add a field** - choose a field type and label. The internal field name is derived from the label automatically. (AJAX route `forms.field.add`.)
- **Edit a field** - change the label, field name, help text, placeholder, default value, and the required / read-only flags. (AJAX route `forms.field.update`.)
- **Delete a field** - remove it from the template. (AJAX route `forms.field.delete`.)
- **Reorder fields** - drag fields into the order you want; the new sort order is saved. (AJAX route `forms.field.reorder`.)

### Preview a template

Open **`/forms/preview/{id}`** (route `forms.preview`) to see how the form renders to a user before assigning it.

### Export a template

Download a template and all of its fields as a JSON file from **`/forms/template/{id}/export`** (route `forms.template.export`). The filename includes the template id and a timestamp.

### Duplicate or delete a template

These are POST actions to **`/forms`** (route `forms.post`):

- **Duplicate** - clones the template (named "... (Copy)") and opens the builder on the copy.
- **Delete** - removes the template and all of its fields.

### Browse the starter library

Go to **`/forms/library`** (route `forms.library`) to see the bundled starter templates (ISAD-G Minimal, ISAD-G Full, Dublin Core Simple, Accession Standard, Photo Collection Item) and their field counts.

---

## Assignments: choosing which template applies

Assignments decide which template a record uses automatically.

### Manage assignments

Go to **`/forms/assignments`** (route `forms.assignments`) to list current assignments with their template name and type, ordered by priority.

### Create an assignment

Go to **`/forms/assignment/create`** (route `forms.assignment.create`).

Set:

- **Template** - the template to apply.
- **Repository** - scope to one repository (leave blank for all repositories).
- **Level of description** - scope to one description level (leave blank for all levels).
- **Collection** - scope to a specific collection / fonds.
- **Priority** - lower numbers win when several assignments match the same record.
- **Inherit to children** - apply the assignment to descendant records.

### How a template is resolved

When a record is edited through the module, Heratio resolves the template like this:

1. **Assignments** for the record's type are checked in priority order. An assignment matches only if every scope it sets (repository, level, collection) matches the record's context. For an information object, the context is built from the record's repository, description level, and the top-level collection found by walking up the parent chain. The first matching assignment wins.
2. If no assignment matches, the **global default** template for that record type (the one flagged `is_default` and active) is used.
3. If there is still no match, the module falls back to the standard edit page.

---

## Template-driven editing

A record can be edited through a template using the edit dispatcher.

### Edit a record with a template

Open **`/forms/edit/{entityType}/{entityId}/{templateId?}`** (route `forms.template.edit`). The entity type must be one of `information_object`, `actor`, `repository`, or `accession`. This route requires update permission (`acl:update`).

- If a template id is supplied, that template is used (its type must match the entity type).
- If no template id is supplied, the best-matching template is resolved automatically as described above.
- The form is pre-filled with the record's current values, read through the field mappings.

### Save the record

Submitting posts to **`/forms/edit/{entityType}/{entityId}/submit/{templateId}`** (route `forms.template.submit`). On save:

1. Each submitted field value is normalised by its type (numbers, checkboxes, dates, and trimmed text).
2. For each field with one or more mappings, any configured transformation is applied (uppercase, lowercase, trim, prefix, JSON array).
3. Writes are bucketed by target table; one update or insert is issued per table. Translated (i18n) targets are written to the correct culture row, inserting the row if it does not yet exist.
4. All writes happen inside a single database transaction. If anything fails, the whole save is rolled back and the form is returned with the error and the entered values preserved.
5. A row is written to the submission log recording the template, entity, the submitting user, the timestamp, and the submitted values.
6. On success you are redirected back to the record (its public page for information objects).

Unmapped fields are ignored for writing but are still captured in the submission log.

---

## Field mappings

A mapping (`ahg_form_field_mapping`) connects a form field to a real database location:

- **Target table** - for example `information_object` or `information_object_i18n`.
- **Target column** - for example `title` or `scope_and_content`.
- **i18n flag and culture** - whether the column lives in a translated table, and which culture (defaults to `en`).
- **Transformation and config** - an optional value transformation applied on save.

A single field may have several mappings (so one input can populate more than one column), and on read the first mapping that yields a value is used to pre-fill the field. The bundled ISAD-G Minimal template, for example, maps Title to `information_object_i18n.title`, Reference Code to `information_object.identifier`, Extent and Medium and Scope and Content to their i18n columns.

---

## Drafts and autosave

While a form is being filled in, the data can be autosaved per user, template, and record through the autosave API (route `forms.api.autosave`, `POST /api/forms/autosave`). A draft is keyed by template, object type, object id, and user, so each user keeps their own in-progress copy. A companion lookup (route `forms.api.template`, `GET /api/forms/template`) resolves the appropriate template and its fields for a given object type and id, used by JavaScript widgets that render a form inline.

---

## Configuration

The Forms module has no separate settings page; configuration is the templates, fields, mappings, and assignments themselves, all managed through the screens above.

Behaviour to keep in mind:

- **System templates** (`is_system = 1`) are the bundled starter templates and are intended to be protected from deletion.
- **Default templates** (`is_default = 1`) act as the global fallback for their record type when no assignment matches.
- **Active flag** (`is_active`) - only active templates and assignments take part in resolution.
- **Validation, options, autocomplete sources, conditional logic, and translated labels** are all stored per field and can be set when building the field. Choice fields store their options as a list of value / label pairs.

In line with Heratio's rules, all enumerated choices should come from the Dropdown Manager or the field's own options list rather than being hardcoded.

---

## Troubleshooting

- **"No form template configured for this entity"** - no assignment matched the record and there is no active default template for its type. Create an assignment or mark a template as the default.
- **"Template form_type does not match entity type"** - the template you chose is for a different record type than the record you are editing; pick a template whose type matches.
- **A saved value did not appear** - confirm the field has a mapping to the correct table and column, and that the i18n flag and culture are set correctly for translated fields. Unmapped fields are logged but not written.
- **Lost in-progress entry** - autosave keeps a per-user draft; reopening the same record and template restores it.

---

## References

- Source package: `packages/ahg-forms/`
- Issue: [GH #572](https://github.com/ArchiveHeritageGroup/heratio/issues/572)

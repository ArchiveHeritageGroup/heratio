# Custom Fields — User Guide

**Plugin:** ahgCustomFieldsPlugin

---

## Introduction

The Custom Fields plugin allows administrators to define additional metadata fields on any entity type (archival descriptions, authority records, accessions, repositories, donors, functions) without writing code or modifying templates.

Fields are defined via a web admin interface and automatically appear on entity edit and view pages.

---

## Accessing Custom Fields Admin

1. Log in as an administrator
2. Navigate to **Admin > Custom Fields** (URL: `/admin/customFields`)

You will see a list of all defined fields, grouped by entity type.

---

## Defining a New Field

1. Click **Add Field** in the top-right corner
2. Fill in the form:

### Required Settings

| Setting | Description |
|---------|-------------|
| **Field Label** | The display name shown on forms and views (e.g., "Schedule Code") |
| **Field Key** | Machine name, auto-generated from label. Cannot be changed after creation. |
| **Field Type** | Select from: Text, Textarea, Date, Number, Boolean, Dropdown, URL |
| **Entity Type** | Which entity this field applies to (e.g., Information Object, Actor) |

### Optional Settings

| Setting | Description |
|---------|-------------|
| **Field Group** | Group related fields under a section heading (e.g., "Legacy Data") |
| **Dropdown Taxonomy** | When type is Dropdown, select which taxonomy to use for values |
| **Default Value** | Pre-filled value for new records |
| **Help Text** | Guidance shown below the input field |
| **Validation Rule** | e.g., `max:255` or `regex:/^[A-Z]/` |
| **Sort Order** | Controls display order within the entity type |

### Checkboxes

| Option | Default | Description |
|--------|---------|-------------|
| **Required** | Off | Field must be filled in |
| **Searchable** | Off | Flag for search index inclusion |
| **Visible on Public View** | On | Show on the public-facing record page |
| **Visible on Edit Form** | On | Show on the staff edit form |
| **Repeatable** | Off | Allow multiple values for this field |
| **Active** | On | Inactive fields are hidden from forms and views |

3. Click **Create Field**

---

## Editing an Existing Field

1. In the field list, click the **pencil icon** next to the field
2. Modify settings as needed
3. Click **Update Field**

Note: The **Field Key** and **Entity Type** cannot be changed after creation.

---

## Reordering Fields

Fields within each entity type can be reordered by dragging:

1. Grab the **grip handle** (vertical dots) on the left of a field row
2. Drag to the desired position
3. The new order is saved automatically

---

## Deactivating / Deleting a Field

### Deactivate (soft delete)
1. Click the **trash icon** next to the field
2. In the confirmation dialog, click **Delete**
3. The field is deactivated — hidden from forms and views, but data is preserved

### Permanently Delete (hard delete)
1. Click the **trash icon** next to the field
2. Check **Permanently delete**
3. Click **Delete**

Note: Hard delete is only possible if no values have been saved for the field.

---

## Using Custom Fields on Entity Pages

Once fields are defined, they automatically appear:

### Edit Pages
- A new **"Additional Fields"** section appears on the entity edit page
- Fields are grouped by their Field Group setting
- Fill in values and click **Save Fields**

### View Pages
- A new **"Additional Fields"** panel appears on the entity view page
- Only fields with values are shown
- Fields marked as not visible on public view are hidden from non-staff users

---

## Repeatable Fields

For fields marked as **Repeatable**:

1. On the edit page, the field shows with an **"Add another"** button
2. Click to add additional value rows
3. Click the **minus icon** to remove a value row
4. At least one row is always shown

Example: A "Barcode" field on Information Object can store multiple barcodes per record.

---

## Dropdown Fields

Dropdown-type fields use controlled vocabularies from the Dropdown Manager:

1. When defining the field, set **Field Type** to "Dropdown"
2. Select the **Dropdown Taxonomy** (e.g., "restriction_code", "loan_status")
3. On entity edit pages, the field renders as a select dropdown populated from the taxonomy
4. On view pages, dropdown values show their display label (with color badge if configured)

To add new dropdown options, go to **Admin > Dropdown Manager**.

---

## Import / Export Field Definitions

### Export
1. Click **Export All** to download all field definitions as JSON
2. Or click the **download icon** on a specific entity type card to export only that type

### Import
1. Click **Import** in the top-right corner
2. Paste JSON (exported from another instance) into the text area
3. Click **Import**
4. Existing fields (same key + entity type) are automatically skipped

This enables standardization across multiple Heratio deployments.

---

## Reporting Views

Three SQL views are available for connecting to external BI tools:

| View | Use |
|------|-----|
| `v_report_descriptions` | Report on archival descriptions with repository, level, dates |
| `v_report_authorities` | Report on authority records with entity type, status |
| `v_report_accessions` | Report on accessions with acquisition type, processing info |

Connect your BI tool (Power BI, Tableau, Metabase) directly to the MySQL database and query these views.

---

## Access Restriction Codes

A base vocabulary of 9 restriction codes is pre-loaded:

- Open / Unrestricted
- Closed
- Time-based Restriction
- Permission Required
- Privacy Restriction
- Legal Hold
- Cultural Protocol
- Security Classification
- Donor Restriction

Add institution-specific codes via **Admin > Dropdown Manager** under the `restriction_code` taxonomy.

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Custom fields not appearing on entity pages | Verify the plugin is enabled at `/admin/ahgSettings/plugins` |
| Fields show on edit but not on view | Check "Visible on Public View" is enabled for the field |
| Dropdown shows no options | Verify the linked taxonomy has active values in Dropdown Manager |
| Changes not visible after update | Clear cache: `php artisan cache:clear && php artisan view:clear` |
| Import reports 0 fields imported | Fields with the same key + entity type already exist (skipped) |

---

*The Archive and Heritage Group (Pty) Ltd*

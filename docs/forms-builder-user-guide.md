# Forms Builder

## User Guide

Create custom metadata entry forms tailored to your repository, collection type, or level of description using a visual drag-and-drop builder.

---

## Overview
```
+-------------------------------------------------------------+
|                    FORMS BUILDER                             |
+-------------------------------------------------------------+
|                                                              |
|   TEMPLATES         FIELDS           ASSIGNMENTS             |
|       |                |                  |                  |
|       v                v                  v                  |
|   Create forms    Drag-drop         Assign to               |
|   for different   field builder     repositories            |
|   record types                      and levels              |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                    WHAT YOU CAN DO                           |
+-------------------------------------------------------------+
|  + Create custom form templates                              |
|  + Drag-and-drop field builder                               |
|  + Multiple field types (text, date, select, etc.)           |
|  + Assign forms to repositories or description levels        |
|  + Clone and customize existing templates                    |
|  + Import/export templates as JSON                           |
|  + Pre-built templates (ISAD-G, Dublin Core, etc.)           |
|  + Form preview before deployment                            |
|  + Draft auto-save functionality                             |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Main Menu
      |
      v
   Admin
      |
      v
   Form Templates
      |
      +---> Dashboard        (overview & statistics)
      |
      +---> Templates        (create & manage forms)
      |
      +---> Assignments      (assign forms to contexts)
      |
      +---> Library          (pre-built templates)
```

---

## Dashboard Overview

The dashboard shows key statistics and quick links:

### Statistics Cards
```
+---------------+  +---------------+  +---------------+  +---------------+
|     15        |  |      8        |  |      3        |  |     127       |
| Total         |  | Active        |  | Pending       |  | Submissions   |
| Templates     |  | Assignments   |  | Drafts        |  | (30 days)     |
+---------------+  +---------------+  +---------------+  +---------------+
```

### Quick Links
- **Templates** - Create and manage form templates
- **Assignments** - Assign templates to repositories and levels
- **Library** - Browse pre-built templates

---

## Managing Templates

### Viewing Templates

Go to **Admin** > **Form Templates** > **Templates**

You will see a list of all templates:
```
+----------+-----------------+---------+--------+----------+
| Name     | Type            | Fields  | Status | Actions  |
+----------+-----------------+---------+--------+----------+
| ISAD-G   | information_    |    8    | Active | Edit     |
| Minimal  | object          |         |        | Settings |
|          |                 |         |        | Clone    |
|          |                 |         |        | Export   |
+----------+-----------------+---------+--------+----------+
| Dublin   | information_    |   15    | Active | Edit     |
| Core     | object          |         |        | Settings |
|          |                 |         |        | Clone    |
|          |                 |         |        | Export   |
+----------+-----------------+---------+--------+----------+
```

### Creating a New Template

1. Click **New Template**
2. Fill in the details:
   - **Name**: A descriptive name (e.g., "Photo Collection Form")
   - **Description**: Brief description of the form's purpose
   - **Form Type**: Select the record type
     - Information Object
     - Accession
     - Actor
     - Repository
     - Custom
   - **Layout**: Single page or tabbed
3. Click **Create**
4. You will be taken to the Form Builder

---

## Form Builder

The drag-and-drop interface for designing forms.

### Layout
```
+------------------+------------------------+------------------+
|   FIELD TYPES    |      FORM CANVAS       |  FIELD SETTINGS  |
|                  |                        |                  |
| [Text]           |  +------------------+  |  Label:          |
| [Text Area]      |  | Title *          |  |  [___________]   |
| [Rich Text]      |  | [text field]     |  |                  |
| [Date]           |  +------------------+  |  Field Name:     |
| [Date Range]     |                        |  [___________]   |
| [Dropdown]       |  +------------------+  |                  |
| [Multi-Select]   |  | Description      |  |  Help Text:      |
| [Autocomplete]   |  | [textarea]       |  |  [___________]   |
| [Checkbox]       |  +------------------+  |                  |
| [Radio]          |                        |  [ ] Required    |
| [File Upload]    |  Drag fields here...   |  [ ] Read Only   |
| [Heading]        |                        |                  |
| [Divider]        |                        |  [Save Field]    |
+------------------+------------------------+------------------+
|                                                              |
|   AtoM FIELDS                                                |
|   [Title] [Identifier] [Level] [Scope & Content]             |
|   [Extent] [Dates] [Creators] [Subjects]                     |
+------------------+------------------------+------------------+
```

### Adding Fields

**Method 1: Drag and Drop**
1. Drag a field type from the left palette
2. Drop it onto the form canvas
3. The field appears in the form

**Method 2: AtoM Fields**
1. Use pre-configured AtoM fields from the bottom palette
2. These automatically map to the correct database fields

### Field Types

| Type | Description | Use For |
|------|-------------|---------|
| Text | Single-line text input | Titles, identifiers, names |
| Text Area | Multi-line text input | Short descriptions |
| Rich Text | Text editor with formatting | Scope and content, notes |
| Date | Single date picker | Specific dates |
| Date Range | Start and end dates | Date ranges (1990-2000) |
| Dropdown | Select one option | Level of description |
| Multi-Select | Select multiple options | Languages, subjects |
| Autocomplete | Type-ahead search | Creators, repositories |
| Checkbox | Yes/No toggle | Boolean options |
| Radio | Select one from list | Mutually exclusive options |
| File Upload | Attach files | Supporting documents |
| Heading | Section title | Organize form sections |
| Divider | Visual separator | Group related fields |

### Editing Field Properties

1. Click on a field in the canvas
2. The properties panel shows on the right:

```
+--------------------------------+
|  FIELD PROPERTIES              |
+--------------------------------+
|                                |
|  Label:                        |
|  [Title                    ]   |
|                                |
|  Field Name:                   |
|  [title                    ]   |
|  (internal identifier)         |
|                                |
|  Help Text:                    |
|  [Enter the title of the   ]   |
|  [record                   ]   |
|                                |
|  Placeholder:                  |
|  [e.g., Meeting minutes    ]   |
|                                |
|  Default Value:                |
|  [                         ]   |
|                                |
|  [x] Required                  |
|  [ ] Read Only                 |
|                                |
|  [Save Field]                  |
+--------------------------------+
```

### Reordering Fields

1. Click and hold the grip handle on a field
2. Drag to the new position
3. Drop to place
4. Order is saved automatically

### Deleting Fields

1. Click the trash icon on a field
2. Confirm deletion
3. Field is removed from the form

---

## Form Assignments

Assign templates to specific contexts so the right form appears for the right records.

### How Assignment Works
```
+-------------------------------------------------------------+
|                    ASSIGNMENT PRIORITY                       |
+-------------------------------------------------------------+
|                                                              |
|   Most Specific                                              |
|       |                                                      |
|       v                                                      |
|   1. Collection + Level + Repository  (highest priority)    |
|   2. Level + Repository                                      |
|   3. Repository only                                         |
|   4. Level only                                              |
|   5. Default template                 (lowest priority)      |
|                                                              |
+-------------------------------------------------------------+
```

### Creating an Assignment

1. Go to **Admin** > **Form Templates** > **Assignments**
2. Click **New Assignment**
3. Configure:

```
+--------------------------------+
|  CREATE ASSIGNMENT             |
+--------------------------------+
|                                |
|  Template:                     |
|  [Photo Collection Form    v]  |
|                                |
|  Repository:                   |
|  [Historical Society       v]  |
|  (leave blank for all)         |
|                                |
|  Level of Description:         |
|  [Item                     v]  |
|  (leave blank for all)         |
|                                |
|  Collection (optional):        |
|  [                         v]  |
|                                |
|  Priority: [100]               |
|  (higher = more important)     |
|                                |
|  [x] Apply to child records    |
|                                |
|  [Create Assignment]           |
+--------------------------------+
```

### Assignment Examples

| Scenario | Configuration |
|----------|---------------|
| Default form for all records | No repository, no level |
| Form for specific repository | Select repository only |
| Form for Item level records | Select "Item" level only |
| Photo form for museum photos | Repository: Museum, Level: Item |
| Custom form for one collection | Select specific collection |

---

## Template Library

Pre-built templates ready to use.

### Available Templates

| Template | Description | Fields |
|----------|-------------|--------|
| **ISAD-G Minimal** | Essential ISAD(G) fields only | 8 |
| **ISAD-G Full** | Complete ISAD(G) with all 26 elements | 26 |
| **Dublin Core Simple** | Dublin Core 15 core elements | 15 |
| **Accession Standard** | Standard accession registration | 15 |
| **Photo Collection Item** | Specialized for photographs | 19 |

### Using Library Templates

1. Go to **Admin** > **Form Templates** > **Library**
2. Browse available templates
3. Click **Install** on desired template
4. Template is added to your system
5. Optionally clone and customize

---

## Form Preview

Test how your form will look before deployment.

### Previewing a Template

1. From the Form Builder, click **Preview**
2. See the form as users will see it:

```
+-------------------------------------------------------------+
|  PREVIEW: Photo Collection Item                              |
+-------------------------------------------------------------+
|                                                              |
|  Title/Caption *                                             |
|  [                                                       ]   |
|                                                              |
|  Photo Number *                                              |
|  [                                                       ]   |
|                                                              |
|  Photographer                                                |
|  [Start typing to search...                              ]   |
|                                                              |
|  Date Taken                                                  |
|  [    /    /        ]                                        |
|                                                              |
|  Description                                                 |
|  +-------------------------------------------------------+   |
|  | B I U | = = = |                                       |   |
|  |                                                       |   |
|  |                                                       |   |
|  +-------------------------------------------------------+   |
|                                                              |
|  [Save (Preview Only)]  [Cancel]                             |
+-------------------------------------------------------------+
```

### Template Information Panel

The preview also shows:
- Template name and type
- Total field count
- Required vs optional fields
- Active/inactive status

---

## Import and Export

Share templates between systems or create backups.

### Exporting a Template

1. Find the template in the list
2. Click the **Export** (download) icon
3. A JSON file downloads to your computer

### Importing a Template

1. Go to **Admin** > **Form Templates**
2. Click **Import**
3. Select your JSON file
4. Optionally rename the template
5. Click **Import**
6. Template is created with all fields

### CLI Export/Import

```bash
# Export template to file
php symfony forms:export --template-id=1 --output=my-template.json

# Import template from file
php symfony forms:import --input=my-template.json

# Import with new name
php symfony forms:import --input=template.json --name="Custom Form"

# Preview import without creating
php symfony forms:import --input=template.json --dry-run
```

---

## Auto-Save and Drafts

Forms automatically save work in progress.

### How Auto-Save Works
```
+-------------------------------------------------------------+
|                    AUTO-SAVE PROCESS                         |
+-------------------------------------------------------------+
|                                                              |
|   User types                                                 |
|       |                                                      |
|       v                                                      |
|   Wait 30 seconds                                            |
|       |                                                      |
|       v                                                      |
|   Save draft automatically                                   |
|       |                                                      |
|       v                                                      |
|   "Draft saved at 14:32"                                     |
|                                                              |
+-------------------------------------------------------------+
```

### Recovering a Draft

1. Navigate to the record you were editing
2. If a draft exists, you'll see a prompt:

```
+--------------------------------+
|  DRAFT FOUND                   |
+--------------------------------+
|                                |
|  A draft was saved on          |
|  2026-01-30 at 14:32           |
|                                |
|  [Restore Draft]  [Discard]    |
+--------------------------------+
```

3. Click **Restore Draft** to continue editing
4. Click **Discard** to start fresh

---

## CLI Commands

### List Templates

```bash
# List all templates
php symfony forms:list

# Filter by type
php symfony forms:list --type=information_object

# Show fields for a template
php symfony forms:list --fields=1

# Show assignments
php symfony forms:list --assignments
```

### Example Output

```
>> forms  === Form Templates ===
>> forms  Found 5 templates:

>> forms  INFORMATION_OBJECT:
>> forms    #1: ISAD-G Minimal [SYSTEM]
>> forms        Fields: 8 | Version: 1
>> forms        Minimal ISAD(G) compliant form

>> forms    #2: ISAD-G Full [SYSTEM]
>> forms        Fields: 26 | Version: 1
>> forms        Complete ISAD(G) form with all elements

>> forms    #3: Dublin Core Simple [SYSTEM]
>> forms        Fields: 15 | Version: 1

>> forms    #4: Photo Collection Item [SYSTEM]
>> forms        Fields: 19 | Version: 1

>> forms  ACCESSION:
>> forms    #5: Accession Standard [SYSTEM]
>> forms        Fields: 15 | Version: 1
```

---

## Best Practices

### Template Design
```
+--------------------------------+--------------------------------+
|  DO                            |  DON'T                         |
+--------------------------------+--------------------------------+
|  Start with library templates  |  Create forms from scratch     |
|  when possible                 |  unnecessarily                 |
+--------------------------------+--------------------------------+
|  Group related fields with     |  Put all fields in one long    |
|  sections and tabs             |  form without organization     |
+--------------------------------+--------------------------------+
|  Mark only essential fields    |  Make every field required     |
|  as required                   |                                |
+--------------------------------+--------------------------------+
|  Provide helpful help text     |  Leave help text empty         |
+--------------------------------+--------------------------------+
|  Use descriptive field names   |  Use generic names like        |
|  (photographer, date_taken)    |  (field1, field2)              |
+--------------------------------+--------------------------------+
|  Test with preview before      |  Deploy without testing        |
|  deployment                    |                                |
+--------------------------------+--------------------------------+
```

### Assignments
```
+--------------------------------+--------------------------------+
|  DO                            |  DON'T                         |
+--------------------------------+--------------------------------+
|  Create specific assignments   |  Create many overlapping       |
|  for special cases             |  assignments                   |
+--------------------------------+--------------------------------+
|  Use priority to control       |  Assume assignment order       |
|  which form wins               |  is predictable                |
+--------------------------------+--------------------------------+
|  Set a default template        |  Leave records without         |
|  as fallback                   |  an applicable template        |
+--------------------------------+--------------------------------+
```

---

## Troubleshooting

### Form Not Appearing

1. **Check assignment exists** - Is there an assignment for this context?
2. **Check assignment is active** - Is the assignment enabled?
3. **Check template is active** - Is the template enabled?
4. **Check priority** - Is another template taking precedence?

### Fields Not Saving

1. **Check required fields** - Are all required fields filled?
2. **Check validation** - Does the data meet validation rules?
3. **Clear cache** - Run `php symfony cc`

### Import Fails

1. **Check JSON format** - Is the file valid JSON?
2. **Check file size** - Large files may timeout
3. **Check permissions** - Does the web server have write access?

---

## Field Type Reference

### Text Input
- **Use for**: Short text (titles, names, identifiers)
- **Options**: Placeholder, default value, max length

### Text Area
- **Use for**: Longer text without formatting
- **Options**: Placeholder, rows, max length

### Rich Text
- **Use for**: Formatted text (scope and content, notes)
- **Options**: Toolbar configuration

### Date
- **Use for**: Single dates
- **Options**: Format, min/max date

### Date Range
- **Use for**: Date spans (1990-2000)
- **Options**: Format, labels for start/end

### Select (Dropdown)
- **Use for**: Choose one from list
- **Options**: List of options, default value

### Multi-Select
- **Use for**: Choose multiple from list
- **Options**: List of options, min/max selections

### Autocomplete
- **Use for**: Type-ahead search (actors, subjects)
- **Options**: Source (taxonomy, actor, repository)

### Checkbox
- **Use for**: Yes/No options
- **Options**: Default checked state

### Radio Buttons
- **Use for**: Choose one from visible options
- **Options**: List of options

### File Upload
- **Use for**: Attach documents
- **Options**: Allowed types, max size

### Heading
- **Use for**: Section titles
- **Options**: Level (h3, h4, h5)

### Divider
- **Use for**: Visual separation
- **Options**: None

---

## Need Help?

Contact your system administrator if you experience issues with the Forms Builder.

---

*Part of the AtoM AHG Framework*

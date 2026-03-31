# Report Builder

## User Guide

Create custom reports with a drag-and-drop designer, schedule automated delivery, and export in multiple formats.

---

## Overview
```
+-------------------------------------------------------------+
|                     REPORT BUILDER                           |
+-------------------------------------------------------------+
|                                                              |
|   SELECT          CONFIGURE        VISUALIZE       EXPORT    |
|     |                |                |              |       |
|     v                v                v              v       |
|   Data            Columns          Tables         PDF        |
|   Source          Filters          Charts         Excel      |
|                   Sorting          Stats          CSV        |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                    CAPABILITIES                              |
+-------------------------------------------------------------+
|  Data Sources    - 40+ data types available                  |
|  Column Selection - Choose and order fields                  |
|  Filters         - Refine data with conditions               |
|  Sorting         - Order results by any field                |
|  Charts          - Bar, line, pie, doughnut                  |
|  Export          - PDF, Excel (XLSX), CSV                    |
|  Scheduling      - Daily, weekly, monthly, quarterly         |
|  Email Delivery  - Automatic report distribution             |
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
   Report Builder --------------------------------+
      |                                           |
      +---> Create New Report                     |
      |                                           |
      +---> View/Edit Reports                     |
      |                                           |
      +---> View Archive                          |
```

---

## Creating a New Report

### Step 1: Start New Report

Go to **Admin** > **Report Builder** > **Create New Report**

### Step 2: Choose Data Source

Select what type of data you want to report on:
```
+-------------------------------------------------------------+
|                  AVAILABLE DATA SOURCES                      |
+-------------------------------------------------------------+
|  CORE ARCHIVES                                               |
|  +-------------------+  +-------------------+                |
|  | Archival          |  | Authority         |                |
|  | Descriptions      |  | Records           |                |
|  +-------------------+  +-------------------+                |
|  | Accessions        |  | Repositories      |                |
|  +-------------------+  +-------------------+                |
|  | Physical Storage  |  | Digital Objects   |                |
|  +-------------------+  +-------------------+                |
|                                                              |
|  GLAM SECTOR                                                 |
|  +-------------------+  +-------------------+                |
|  | Library Items     |  | Museum Objects    |                |
|  +-------------------+  +-------------------+                |
|  | Gallery Artists   |  | Gallery Loans     |                |
|  +-------------------+  +-------------------+                |
|                                                              |
|  COMPLIANCE & SECURITY                                       |
|  +-------------------+  +-------------------+                |
|  | Privacy Consents  |  | Security          |                |
|  |                   |  | Classifications   |                |
|  +-------------------+  +-------------------+                |
|  | DSAR Requests     |  | Audit Logs        |                |
|  +-------------------+  +-------------------+                |
+-------------------------------------------------------------+
```

### Step 3: Enter Report Details
```
+-------------------------------------------------------------+
|                  REPORT SETTINGS                             |
+-------------------------------------------------------------+
|                                                              |
|  Report Name:    [Monthly Accessions Report              ]   |
|                                                              |
|  Description:    [Tracks all new accessions received     ]   |
|                  [during the month...                    ]   |
|                                                              |
|                        [Continue to Designer]                |
+-------------------------------------------------------------+
```

---

## Using the Report Designer

### Designer Interface
```
+-------------------------------------------------------------+
|  TOOLBAR   [Save]  [Preview]  [Export v]  [Schedule]         |
+-------------------------------------------------------------+
|            |                              |                  |
|  LEFT      |       CENTER                 |    RIGHT         |
|  PANEL     |       CANVAS                 |    PANEL         |
|            |                              |                  |
| Settings   |   +-------------------+      | Column Order     |
| - Name     |   | DATA TABLE        |      | - Drag to        |
| - Sharing  |   |                   |      |   reorder        |
|            |   | [Table Preview]   |      |                  |
| Columns    |   |                   |      | Chart Settings   |
| [ ] ID     |   +-------------------+      | - Type           |
| [x] Title  |                              | - Group By       |
| [x] Date   |   +-------------------+      |                  |
| [ ] Scope  |   | CHART             |      | Quick Preview    |
|            |   | [Bar Chart]       |      | [Load Data]      |
| Filters    |   +-------------------+      |                  |
| [+ Add]    |                              |                  |
|            |                              |                  |
| Sort Order |                              |                  |
| [Column v] |                              |                  |
| [Desc   v] |                              |                  |
+-------------------------------------------------------------+
```

### Selecting Columns

Check boxes next to columns you want in your report:
```
+------------------------------+
|  AVAILABLE COLUMNS           |
+------------------------------+
|  Core Fields                 |
|  [ ] ID                      |
|  [x] Identifier              |
|  [x] Level of Description    |
|  [x] Repository              |
|                              |
|  Descriptive Fields          |
|  [x] Title                   |
|  [ ] Alternate Title         |
|  [x] Scope and Content       |
|  [ ] Archival History        |
|                              |
|  System Fields               |
|  [x] Created At              |
|  [x] Updated At              |
+------------------------------+
```

### Reordering Columns

Drag columns in the right panel to change display order:
```
+------------------------------+
|  COLUMN ORDER                |
+------------------------------+
|  :: Title            [x]     |  <- Drag handle
|  :: Identifier       [x]     |
|  :: Repository       [x]     |
|  :: Created At       [x]     |
+------------------------------+
```

### Adding Filters

Click **+ Add** to create filter conditions:
```
+-------------------------------------------------------------+
|  FILTER                                                      |
+-------------------------------------------------------------+
|  [Repository    v]  [Equals      v]  [Delete]               |
|  [City Archives                                  ]           |
+-------------------------------------------------------------+

Available Operators:
- Equals / Not Equals
- Contains
- Starts with / Ends with
- Is empty / Is not empty
- Greater than / Less than
- Between
```

### Configuring Sorting
```
+------------------------------+
|  SORT ORDER                  |
+------------------------------+
|  Sort by:  [Created At    v] |
|  Direction: [Descending   v] |
+------------------------------+
```

---

## Layout Components

### Adding Components

Use toolbar buttons to add elements:
```
+------------------------------+
|  [+ Table]  [+ Chart]  [+ Stats]
+------------------------------+

- Table:  Display data in rows and columns
- Chart:  Visualize data as bar, line, or pie chart
- Stats:  Show summary statistics (total count)
```

### Table Component
```
+-------------------------------------------------------------+
|  DATA TABLE                                          [x]     |
+-------------------------------------------------------------+
|  Title          | Identifier | Repository  | Created At     |
+-------------------------------------------------------------+
|  Meeting Notes  | 2024-001   | City Archive| 2024-01-15     |
|  Annual Report  | 2024-002   | City Archive| 2024-01-18     |
|  Photographs    | 2024-003   | State Arch  | 2024-01-20     |
+-------------------------------------------------------------+
```

### Chart Component
```
+-------------------------------------------------------------+
|  CHART                                               [x]     |
+-------------------------------------------------------------+
|                                                              |
|   |||                                                        |
|   |||  |||                                                   |
|   |||  |||  |||                                              |
|   |||  |||  |||  |||                                         |
|   |||  |||  |||  |||  |||                                    |
|  -------------------------                                   |
|   Jan  Feb  Mar  Apr  May                                    |
|                                                              |
+-------------------------------------------------------------+

Chart Types: Bar, Line, Pie, Doughnut, Horizontal Bar
```

### Stats Component
```
+------------------------------+
|  STATISTIC           [x]     |
+------------------------------+
|                              |
|          1,234               |
|      Total Records           |
|                              |
+------------------------------+
```

---

## Saving Your Report

### Save Button

Click **Save** in the toolbar. Unsaved changes show asterisk (*):
```
[Save] -> [Save *] -> [Saving...] -> [Saved!]
```

### Visibility Options
```
+------------------------------+
|  VISIBILITY                  |
+------------------------------+
|  ( ) Private                 |  <- Only you can see
|  ( ) Shared                  |  <- Logged-in users
|  (o) Public                  |  <- Anyone can view
+------------------------------+
```

---

## Previewing Reports

### Quick Preview

Click **Load Preview Data** in the designer to see sample data:
```
+------------------------------+
|  QUICK PREVIEW               |
+------------------------------+
|  [Load Preview Data]         |
|                              |
|  1,234 total records         |
+------------------------------+
```

### Full Preview

Click **Preview** button to open full report view with pagination:
```
+-------------------------------------------------------------+
|  Monthly Accessions Report                                   |
+-------------------------------------------------------------+
|  Total Records: 1,234  |  Columns: 6  |  Page 1/25          |
+-------------------------------------------------------------+
|  Title          | Identifier | Repository  | Date           |
+-------------------------------------------------------------+
|  [Data rows...]                                              |
+-------------------------------------------------------------+
|              << < 1 2 3 4 5 ... 25 > >>                      |
+-------------------------------------------------------------+
```

---

## Exporting Reports

### Available Formats
```
+-------------------------------------------------------------+
|                    EXPORT FORMATS                            |
+-------------------------------------------------------------+
|  CSV    - Comma-separated values for spreadsheets            |
|  XLSX   - Microsoft Excel format with formatting             |
|  PDF    - Portable document for printing/sharing             |
+-------------------------------------------------------------+
```

### Export Steps

1. Open report or click **Export** dropdown
2. Select format (CSV, Excel, or PDF)
3. File downloads automatically

---

## Scheduling Reports

### Access Schedule Settings

From designer: Click **Schedule** button

### Create a Schedule
```
+-------------------------------------------------------------+
|  CREATE NEW SCHEDULE                                         |
+-------------------------------------------------------------+
|                                                              |
|  Frequency:     [Weekly          v]                          |
|                                                              |
|  Day of Week:   [Monday          v]   (for weekly)           |
|  Day of Month:  [1               v]   (for monthly)          |
|                                                              |
|  Time of Day:   [08:00        ]                              |
|                                                              |
|  Output Format: [PDF             v]                          |
|                                                              |
|  Email Recipients:                                           |
|  [manager@example.com, team@example.com                  ]   |
|                                                              |
|                   [Create Schedule]                          |
+-------------------------------------------------------------+
```

### Schedule Options
```
+------------------------------+
|  FREQUENCY OPTIONS           |
+------------------------------+
|  Daily     - Every day       |
|  Weekly    - Specific day    |
|  Monthly   - Specific date   |
|  Quarterly - Every 3 months  |
+------------------------------+
```

### Managing Schedules
```
+-------------------------------------------------------------+
|  EXISTING SCHEDULES                                          |
+-------------------------------------------------------------+
|  Frequency | Time  | Format | Next Run         | Status      |
+-------------------------------------------------------------+
|  Weekly    | 08:00 | PDF    | 2024-02-05 08:00 | Active  [x] |
|  Monthly   | 09:00 | XLSX   | 2024-02-01 09:00 | Active  [x] |
+-------------------------------------------------------------+
```

---

## Report Archive

### Viewing Generated Reports

Go to **Admin** > **Report Builder** > **Archive**

```
+-------------------------------------------------------------+
|  REPORT ARCHIVE                                              |
+-------------------------------------------------------------+
|  Report Name          | Format | Size    | Generated         |
+-------------------------------------------------------------+
|  Monthly_Accessions   | PDF    | 245 KB  | 2024-01-01 08:00  |
|  Monthly_Accessions   | PDF    | 238 KB  | 2023-12-01 08:00  |
|  Weekly_Stats         | XLSX   | 156 KB  | 2024-01-08 08:00  |
+-------------------------------------------------------------+
```

---

## Tips and Best Practices

### Do's and Don'ts
```
+--------------------------------+--------------------------------+
|  DO                            |  DON'T                         |
+--------------------------------+--------------------------------+
|  Name reports descriptively    |  Use generic names             |
|  Use filters to focus data     |  Export huge datasets          |
|  Schedule recurring reports    |  Forget to save changes        |
|  Test with preview first       |  Share sensitive reports       |
|  Limit columns for clarity     |  Include all available fields  |
+--------------------------------+--------------------------------+
```

### Performance Tips

- **Limit columns**: Only select fields you need
- **Use filters**: Narrow down data before export
- **Schedule off-hours**: Run large reports during quiet times
- **Archive management**: Clean old archived reports periodically

---

## Common Use Cases
```
+-------------------------------------------------------------+
|                    EXAMPLE REPORTS                           |
+-------------------------------------------------------------+
|                                                              |
|  Accessions Report                                           |
|  - Track new materials by date, donor, repository            |
|                                                              |
|  Holdings Summary                                            |
|  - Count records by level of description                     |
|                                                              |
|  Privacy Compliance                                          |
|  - List consent records, DSAR requests                       |
|                                                              |
|  Security Audit                                              |
|  - Access logs, classification reports                       |
|                                                              |
|  Collection Statistics                                       |
|  - Charts by repository, date range, type                    |
|                                                              |
+-------------------------------------------------------------+
```

---

## Troubleshooting

### Common Issues
```
+-------------------------------------------------------------+
|  PROBLEM                      |  SOLUTION                    |
+-------------------------------------------------------------+
|  No data appears              |  Check filters are correct   |
|  Export fails                 |  Try smaller date range      |
|  Schedule not running         |  Verify cron job is set up   |
|  Charts not loading           |  Select "Group By" field     |
|  Columns not showing          |  Check at least one selected |
+-------------------------------------------------------------+
```

### Getting Help

Contact your system administrator if you experience persistent issues.

---

## Keyboard Shortcuts

- **Ctrl+S**: Save report (when in designer)
- **Esc**: Cancel current action

---

*Part of the AtoM AHG Framework*

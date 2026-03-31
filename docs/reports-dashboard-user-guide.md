# Reports Dashboard

## User Guide

Generate and export reports about your archive's collections, users, activity, and compliance.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                    REPORTS DASHBOARD                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📊 Collection     📈 Activity      👥 Users                │
│     Reports           Reports          Reports              │
│        │                 │                │                 │
│        ▼                 ▼                ▼                 │
│   Holdings          Usage Stats      Access Logs            │
│   Statistics        Popular Items    Permissions            │
│   Formats           Downloads        Registrations          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Access
```
  Main Menu
      │
      ▼
   Admin
      │
      ▼
   Reports ──────────────────────────────────────────────────┐
      │                                                       │
      ├──▶ Collection Reports    (holdings, formats, gaps)    │
      ├──▶ Activity Reports      (usage, downloads, views)    │
      ├──▶ User Reports          (access, permissions)        │
      ├──▶ Compliance Reports    (POPIA, audits, retention)   │
      └──▶ Custom Reports        (build your own)             │
```

---

## Collection Reports

### Available Reports
```
┌─────────────────────────────────────────────────────────────┐
│  COLLECTION REPORTS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📦 Holdings Summary                                        │
│     Total records by level (fonds, series, items)           │
│                                                             │
│  📁 Repository Statistics                                   │
│     Records per repository/department                       │
│                                                             │
│  📅 Date Coverage                                           │
│     Time periods covered in collection                      │
│                                                             │
│  🏷️ Subject Analysis                                        │
│     Most common subjects and access points                  │
│                                                             │
│  💾 Digital Objects                                         │
│     File formats, sizes, storage used                       │
│                                                             │
│  📋 Completeness                                            │
│     Records missing required fields                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Running a Collection Report

1. Select the report type
2. Choose filters (repository, date range, level)
3. Click **Generate Report**
4. View on screen or export

---

## Activity Reports

### Available Reports
```
┌─────────────────────────────────────────────────────────────┐
│  ACTIVITY REPORTS                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  👁️ Most Viewed Records                                     │
│     Popular items in your collection                        │
│                                                             │
│  📥 Download Statistics                                     │
│     Files downloaded, by type and user                      │
│                                                             │
│  🔍 Search Analysis                                         │
│     What users are searching for                            │
│                                                             │
│  📈 Usage Trends                                            │
│     Activity over time (daily, weekly, monthly)             │
│                                                             │
│  🌍 Geographic Access                                       │
│     Where your users are located                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## User Reports

### Available Reports
```
┌─────────────────────────────────────────────────────────────┐
│  USER REPORTS                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  👥 User List                                               │
│     All registered users and roles                          │
│                                                             │
│  🔐 Permission Summary                                      │
│     Who can access what                                     │
│                                                             │
│  📝 Registration Report                                     │
│     New user sign-ups over time                             │
│                                                             │
│  🕐 Login Activity                                          │
│     User sessions and last access                           │
│                                                             │
│  📊 User Contributions                                      │
│     Records created/edited per user                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Compliance Reports

### Available Reports
```
┌─────────────────────────────────────────────────────────────┐
│  COMPLIANCE REPORTS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔒 Access Restrictions                                     │
│     Embargoed and restricted records                        │
│                                                             │
│  📋 Retention Schedule                                      │
│     Records approaching retention dates                     │
│                                                             │
│  🛡️ Privacy (POPIA/GDPR)                                    │
│     Personal data processing activities                     │
│                                                             │
│  📜 Audit Trail                                             │
│     System changes for compliance audits                    │
│                                                             │
│  🏛️ Heritage Accounting (GRAP 103)                          │
│     Asset valuations and movements                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Generating Reports

### Step 1: Select Report Type

Choose from the categories above.

### Step 2: Set Parameters
```
┌─────────────────────────────────────────────────────────────┐
│  REPORT PARAMETERS                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Date Range:     [From: 01/01/2025]  [To: 31/12/2025]      │
│                                                             │
│  Repository:     [All Repositories        ▼]                │
│                                                             │
│  Level:          [All Levels              ▼]                │
│                                                             │
│  Include:        [✓] Published records                      │
│                  [✓] Draft records                          │
│                  [ ] Deleted records                        │
│                                                             │
│                     [Generate Report]                       │
└─────────────────────────────────────────────────────────────┘
```

### Step 3: View Results

Results appear on screen in a table format.

### Step 4: Export (Optional)
```
┌─────────────────────────────────────────────────────────────┐
│  EXPORT OPTIONS                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [📄 CSV]     - For spreadsheets (Excel, Google Sheets)     │
│  [📑 PDF]     - For printing and sharing                    │
│  [📊 Excel]   - Formatted spreadsheet with charts           │
│  [🔗 JSON]    - For technical/API integration               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### PDF Export

PDF export creates professional, printable reports:

- **TCPDF Support**: If TCPDF is installed, generates native PDF with:
  - Proper headers and footers
  - Auto-sized table columns
  - Report title and date
  - UTF-8 character support

- **HTML Fallback**: If TCPDF is not available:
  - Print-optimized HTML page opens
  - Use browser Print → Save as PDF
  - Bootstrap-compatible formatting
  - Proper page break handling

---

## Scheduling Reports

Set up automatic report generation:
```
┌─────────────────────────────────────────────────────────────┐
│  SCHEDULE REPORT                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Report:        Holdings Summary                            │
│                                                             │
│  Frequency:     [Monthly          ▼]                        │
│                 • Daily                                     │
│                 • Weekly                                    │
│                 • Monthly                                   │
│                 • Quarterly                                 │
│                                                             │
│  Email to:      [admin@archive.org              ]           │
│                                                             │
│  Format:        [PDF              ▼]                        │
│                                                             │
│                [Cancel]    [Schedule]                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Tips
```
┌────────────────────────────────┬────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                   │
├────────────────────────────────┼────────────────────────────┤
│  Use date filters              │  Run reports on all time   │
│  Export large reports          │  View thousands on screen  │
│  Schedule regular reports      │  Forget to check stats     │
│  Compare periods               │  Look at single snapshots  │
│  Share with stakeholders       │  Keep insights to yourself │
└────────────────────────────────┴────────────────────────────┘
```

---

## Troubleshooting
```
Problem                          Solution
───────────────────────────────────────────────────────────
Report takes too long         →  Narrow date range
                                 Add more filters
                                 Try during quiet hours
                                 
Export fails                  →  Try smaller date range
                                 Try different format
                                 
No data showing               →  Check filters aren't too narrow
                                 Verify you have permission
                                 
Charts not displaying         →  Refresh the page
                                 Try a different browser
```

---

## Optional Features

Some menu items only appear when the corresponding plugins are enabled. If you don't see a feature listed below, ask your administrator to enable the plugin.

| Feature | Requires Plugin |
|---------|----------------|
| Collections Management Dashboard | ahgSpectrumPlugin |
| GRAP 103 Dashboard | ahgSpectrumPlugin |

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the AtoM AHG Framework*
*Last Updated: February 2026*

# Provenance Tracking

## User Guide

Track chain of custody and ownership history for archival records, museum objects, and library materials.

---

## Overview
```
+-------------------------------------------------------------+
|                    PROVENANCE TRACKING                       |
+-------------------------------------------------------------+
|                                                             |
|   WHO            WHAT            WHEN           WHERE       |
|    |              |               |               |         |
|    v              v               v               v         |
|  Owner/        Transfer        Date of        Location      |
|  Holder        Event           Event          Details       |
|                                                             |
|                         |                                   |
|                         v                                   |
|           CHAIN OF CUSTODY TIMELINE                         |
+-------------------------------------------------------------+
```

---

## What is Provenance?

Provenance is the chronological history of ownership, custody, or location of a historical object. Proper provenance research is essential for museums, archives, and libraries to establish authenticity, legal ownership, and cultural significance of their collections.

```
+-------------------------------------------------------------+
|                     WHY PROVENANCE MATTERS                   |
+-------------------------------------------------------------+
|  * Establishes legal ownership                              |
|  * Verifies authenticity                                    |
|  * Documents cultural significance                          |
|  * Identifies gaps in ownership history                     |
|  * Supports Nazi-era due diligence (museums)                |
|  * Tracks cultural property claims                          |
|  * Maintains donor relationships                            |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                      PLUGIN FEATURES                         |
+-------------------------------------------------------------+
|  * Chain of custody timeline with event tracking            |
|  * Certainty levels for each provenance claim               |
|  * Nazi-era provenance checking (1933-1945)                 |
|  * Cultural property status tracking                        |
|  * Supporting document management                           |
|  * Integration with donor agreements                        |
|  * Visual timeline visualization with D3.js                 |
|  * Agent/owner management with autocomplete                 |
|  * Multi-language support (i18n)                            |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Record View
      |
      v
  More Menu / Actions
      |
      v
  Provenance -------------------------------------+
      |                                           |
      +---> View Provenance    (read-only view)   |
      |                                           |
      +---> Edit Provenance    (add/modify)       |
      |                                           |
      +---> View Timeline      (visual display)   |
```

Alternatively, navigate directly to:
- `/provenance` - Dashboard with statistics
- `/provenance/[slug]` - View provenance for a record
- `/provenance/[slug]/edit` - Edit provenance
- `/provenance/[slug]/timeline` - Visual timeline

---

## Viewing Provenance

### Provenance View Page

When viewing provenance for a record, you will see:

```
+-------------------------------------------------------------+
|  PROVENANCE & CHAIN OF CUSTODY                               |
+-------------------------------------------------------------+
|  Record: [Title of Item]                                    |
+-------------------------------------------------------------+
|                                                             |
|  PROVENANCE SUMMARY                                         |
|  +-------------------------------------------------------+  |
|  | Created by John Smith (1920); Sold by Smith Family    |  |
|  | to City Museum (1985), New York.                      |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  CHAIN OF CUSTODY TIMELINE                                  |
|  +-------------------------------------------------------+  |
|  | o-- 1920: Creation - By John Smith                    |  |
|  | |   [Certain]                                         |  |
|  | |                                                     |  |
|  | o-- 1950: Inheritance - To Smith Family               |  |
|  | |   [Probable]                                        |  |
|  | |                                                     |  |
|  | o-- 1985: Donation - Smith Family -> City Museum      |  |
|  |     [Certain] @ New York                              |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  STATUS                    NAZI-ERA CHECK                   |
|  +---------------------+   +-----------------------------+  |
|  | Status: Owned       |   | [x] Checked                 |  |
|  | Custody: Permanent  |   | [x] Clear - No issues       |  |
|  | Certainty: Certain  |   +-----------------------------+  |
|  +---------------------+                                    |
+-------------------------------------------------------------+
```

---

## Adding/Editing Provenance

### Step 1: Navigate to Edit

Go to a record and click **Edit Provenance** or navigate to `/provenance/[slug]/edit`

### Step 2: Complete the Form

```
+-------------------------------------------------------------+
|                    PROVENANCE SUMMARY                        |
+-------------------------------------------------------------+
| Provenance Statement:                                       |
| +-------------------------------------------------------+   |
| | Enter a human-readable summary of the item's          |   |
| | provenance history...                                 |   |
| +-------------------------------------------------------+   |
| (Leave blank to auto-generate from events)                  |
+-------------------------------------------------------------+

+-------------------------------------------------------------+
|                   ACQUISITION DETAILS                        |
+-------------------------------------------------------------+
| Acquisition Type:  [Donation        v]                      |
| Acquisition Date:  [1985-06-15      ]                       |
| Date (Text):       [circa 1985      ]  (for imprecise)      |
| Price:             [               ]                        |
| Currency:          [ZAR             v]                      |
| Notes:             [Gift from estate                    ]   |
+-------------------------------------------------------------+
```

### Step 3: Add Chain of Custody Events

Click **Add Event** to record ownership changes:

```
+-------------------------------------------------------------+
|                  CHAIN OF CUSTODY EVENTS                     |
+-------------------------------------------------------------+
| EVENT 1                                              [X]    |
| +-------------------------------------------------------+   |
| | Type: [Donation     v] Date: [1985-06-15]             |   |
| | Date Text: [         ] Certainty: [Certain  v]        |   |
| | From: [Smith Family    ] To: [City Museum    ]        |   |
| | Location: [New York              ]                    |   |
| | Notes: [Gift from estate                          ]   |   |
| +-------------------------------------------------------+   |
|                                                             |
| EVENT 2                                              [X]    |
| +-------------------------------------------------------+   |
| | Type: [Inheritance  v] Date: [1950-01-01]             |   |
| | Date Text: [circa 1950] Certainty: [Probable v]       |   |
| | From: [John Smith     ] To: [Smith Family    ]        |   |
| | Location: [                      ]                    |   |
| +-------------------------------------------------------+   |
|                                                             |
|                            [+ Add Event]                    |
+-------------------------------------------------------------+
```

---

## Event Types

Events are organized into categories:

### Ownership Changes
| Type | Description |
|------|-------------|
| Sale | Item sold to new owner |
| Purchase | Item purchased from seller |
| Auction | Sold at public auction |
| Gift | Given as a present |
| Donation | Donated (usually to institution) |
| Bequest | Left in a will |
| Inheritance | Passed down through family |
| By Descent | Inherited within family |
| Transfer | Moved between collections |
| Exchange | Traded for another item |

### Loans & Deposits
| Type | Description |
|------|-------------|
| Loan Out | Temporarily loaned to another party |
| Loan Return | Returned from loan |
| Deposit | Placed in custody (not ownership) |
| Withdrawal | Removed from deposit |

### Creation & Discovery
| Type | Description |
|------|-------------|
| Creation | When item was made/created |
| Commission | Ordered to be created |
| Discovery | Found or unearthed |
| Excavation | Archaeological recovery |

### Loss & Recovery
| Type | Description |
|------|-------------|
| Theft | Stolen from owner |
| Recovery | Recovered after theft/loss |
| Confiscation | Seized by authority |
| Restitution | Returned to rightful owner |
| Repatriation | Returned to country/community of origin |

### Movement
| Type | Description |
|------|-------------|
| Import | Brought into country |
| Export | Sent out of country |

### Documentation
| Type | Description |
|------|-------------|
| Authentication | Verified as genuine |
| Appraisal | Valued for insurance/sale |
| Conservation | Preservation treatment |
| Restoration | Repair/restoration work |

### Institutional
| Type | Description |
|------|-------------|
| Accessioning | Added to collection formally |
| Deaccessioning | Removed from collection |

---

## Certainty Levels

Each event and the overall provenance can be assigned a certainty level:

```
+---------------+-----------------------------------------------+
|    Level      |               Description                     |
+---------------+-----------------------------------------------+
|   Certain     | Documented evidence (invoices, receipts,      |
|               | photographs, correspondence)                  |
+---------------+-----------------------------------------------+
|   Probable    | Strong circumstantial evidence                |
+---------------+-----------------------------------------------+
|   Possible    | Some supporting evidence                      |
+---------------+-----------------------------------------------+
|   Uncertain   | Limited evidence                              |
+---------------+-----------------------------------------------+
|   Unknown     | No evidence available                         |
+---------------+-----------------------------------------------+
```

---

## Status Options

### Current Status
- **Owned** - Institution has full ownership
- **On Loan** - Temporarily held from another owner
- **Deposited** - In custody but not owned
- **Unknown** - Ownership unclear
- **Disputed** - Ownership contested

### Custody Type
- **Permanent** - Intended to remain indefinitely
- **Temporary** - Time-limited custody
- **Loan** - Borrowed from another party
- **Deposit** - Placed for safekeeping

---

## Nazi-Era Provenance (Museums)

For museum objects, especially art and cultural property, checking Nazi-era provenance (1933-1945) is essential:

```
+-------------------------------------------------------------+
|              NAZI-ERA PROVENANCE CHECK                       |
+-------------------------------------------------------------+
| [ ] Nazi-era provenance has been checked                    |
|                                                             |
| If checked:                                                 |
| +-------------------------------------------------------+   |
| | Result: [Clear - No issues found     v]               |   |
| |         [Requires investigation      ]                |   |
| |                                                       |   |
| | Notes:                                                |   |
| | +---------------------------------------------------+ |   |
| | | Provenance traced through 1933-1945 period.       | |   |
| | | No gaps or suspicious transfers identified.       | |   |
| | +---------------------------------------------------+ |   |
| +-------------------------------------------------------+   |
+-------------------------------------------------------------+
```

**Important:** Objects acquired between 1933-1945 or with gaps during this period should be thoroughly researched for potential Nazi-era looting.

---

## Cultural Property Status

Track claims and disputes related to cultural property:

| Status | Description |
|--------|-------------|
| None | No cultural property issues |
| Claimed | Subject to ownership claim |
| Disputed | Active dispute in progress |
| Repatriated | Returned to community/country of origin |
| Cleared | Investigated and cleared |

---

## Supporting Documents

Upload or link documents that support provenance claims:

```
+-------------------------------------------------------------+
|                  SUPPORTING DOCUMENTS                        |
+-------------------------------------------------------------+
| Existing Documents:                                         |
| +-------------------------------------------------------+   |
| | [PDF] Deed of Gift 1985.pdf     [Deed of Gift]  [Del] |   |
| | [IMG] Photo with Smith 1980.jpg [Photograph]    [Del] |   |
| +-------------------------------------------------------+   |
|                                                             |
| Add New Document:                                           |
| +-------------------------------------------------------+   |
| | Type: [Bill of Sale    v] Title: [1985 Transfer   ]   |   |
| | Date: [1985-06-15      ]                              |   |
| | File: [Choose file...  ]  OR  URL: [https://...   ]   |   |
| | Description: [Original transfer documentation    ]    |   |
| +-------------------------------------------------------+   |
|                           [+ Add Document]                  |
+-------------------------------------------------------------+
```

### Document Types
- Deed of Gift
- Bill of Sale
- Invoice / Receipt
- Auction Catalog
- Exhibition Catalog
- Inventory Record
- Insurance Record
- Photograph
- Correspondence
- Certificate
- Customs Document
- Export License / Import Permit
- Appraisal
- Condition Report
- Newspaper Clipping
- Publication
- Oral History
- Affidavit
- Legal Document

---

## Visual Timeline

The timeline view provides a visual representation of the chain of custody:

```
+-------------------------------------------------------------+
|                    VISUAL TIMELINE                           |
+-------------------------------------------------------------+
|                                                             |
|  1920        1950        1985        2000        2024       |
|    |           |           |           |           |        |
|    v           v           v           v           v        |
|    o-----------o-----------o-----------------------o        |
|    |           |           |                       |        |
| Creation   Inheritance  Donation              Current       |
| J. Smith   Smith Family  to Museum           Holdings       |
|                                                             |
| Legend:                                                     |
| [Creation] [Sale] [Gift] [Inheritance] [Auction] [Other]    |
+-------------------------------------------------------------+
```

Access via: `/provenance/[slug]/timeline`

---

## Dashboard Statistics

The provenance dashboard shows collection-wide statistics:

```
+-------------------------------------------------------------+
|                 PROVENANCE MANAGEMENT                        |
+-------------------------------------------------------------+
| +-------------+  +-------------+  +-------------+  +-------+ |
| |   Total     |  |  Complete   |  | With Gaps   |  | Nazi  | |
| |    150      |  |     98      |  |     23      |  |  Era  | |
| |  Records    |  |             |  |             |  |  125  | |
| +-------------+  +-------------+  +-------------+  +-------+ |
|                                                             |
| BY ACQUISITION TYPE              BY CERTAINTY LEVEL         |
| +------------------------+       +------------------------+ |
| | Donation     | 45 |====|       | Certain   | 65 |=======| |
| | Purchase     | 38 |=== |       | Probable  | 42 |====   | |
| | Bequest      | 22 |==  |       | Possible  | 23 |==     | |
| | Transfer     | 18 |=   |       | Uncertain | 15 |=      | |
| | Unknown      | 27 |==  |       | Unknown   |  5 |       | |
| +------------------------+       +------------------------+ |
+-------------------------------------------------------------+
```

---

## Agents (Owners/Holders)

Agents represent persons, organizations, or families in the chain of custody:

### Agent Types
- **Person** - Individual owner
- **Organization** - Company, museum, gallery
- **Family** - Family group or estate
- **Unknown** - Unidentified party

### Agent Autocomplete
When entering agent names, the system suggests existing agents:
```
From Agent: [Smith_____________]
            +------------------+
            | Smith, John      |
            | Smith Family     |
            | Smithsonian      |
            +------------------+
```

---

## Best Practices

```
+--------------------------------+--------------------------------+
|           DO                   |          DON'T                 |
+--------------------------------+--------------------------------+
| Record all known events        | Leave gaps undocumented        |
| Assign certainty levels        | Assume all events are certain  |
| Upload supporting documents    | Rely on memory alone           |
| Check Nazi-era provenance      | Ignore 1933-1945 period        |
| Note gaps in the chain         | Hide uncertain provenance      |
| Use specific dates when known  | Use vague dates unnecessarily  |
| Link to existing agents        | Create duplicate agent entries |
| Add research notes             | Lose research findings         |
+--------------------------------+--------------------------------+
```

---

## CSV Import Format

Bulk import provenance using CSV:

```csv
identifier,legacy_id,provenance_summary,acquisition_type,acquisition_date,certainty_level,event_type,event_date,from_agent,to_agent,event_location,event_notes
ABC-001,,Donated by Smith Family in 1985,donation,1985-06-15,certain,donation,1985-06-15,Smith Family,City Museum,New York,Gift from estate
ABC-002,PRES-12345,Purchased at auction 2010,purchase,2010-03-22,certain,auction,2010-03-22,Christie's,City Museum,London,Lot 245
ABC-003,,Unknown provenance before 1950,unknown,,uncertain,,,,,Requires further research
```

---

## Integration with Other Plugins

The Provenance Plugin integrates with:

| Plugin | Integration |
|--------|-------------|
| ahgDonorAgreementPlugin | Link provenance to donor agreements |
| ahgRightsPlugin | Connect provenance to rights information |
| ahgMuseumPlugin | Display provenance on museum object views |
| ahgDAMPlugin | Track provenance for digital assets |
| ahgLibraryPlugin | Document provenance for library materials |

---

## Common Tasks

### Mark Provenance Complete
1. Add all known events
2. Upload supporting documents
3. Check Nazi-era provenance (if applicable)
4. Set certainty level
5. Check "Provenance research is complete"

### Record a Gap
1. Check "There are gaps in the provenance chain"
2. Describe the gap in the provided field
3. Set certainty to "Uncertain" or "Unknown"

### Flag Cultural Property Issue
1. Set Cultural Property Status to "Claimed" or "Disputed"
2. Add detailed notes
3. Upload any related documentation

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Timeline not showing | Ensure events have dates |
| Agent not appearing | Check autocomplete, create new if needed |
| Documents not uploading | Check file size limits, permissions |
| Summary not generating | Add events or enter manual summary |

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the AtoM AHG Framework - Version 1.0.3*

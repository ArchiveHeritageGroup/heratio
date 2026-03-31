# Rights Management

## User Guide

Manage copyright, licensing, embargoes, and access restrictions for your archive collections.

---

## Overview
```
+-------------------------------------------------------------+
|                    RIGHTS MANAGEMENT                         |
+-------------------------------------------------------------+
|                                                              |
|  COPYRIGHT         LICENSES          EMBARGOES               |
|      |                |                  |                   |
|      v                v                  v                   |
|  Legal Status    Creative Commons   Time-Based               |
|  Rights Holder   Rights Statements  Restrictions             |
|  Jurisdiction    Custom Licenses    Auto-Release             |
|                                                              |
+-------------------------------------------------------------+
|                                                              |
|  TK LABELS         ORPHAN WORKS      TERRITORY               |
|      |                  |                |                   |
|      v                  v                v                   |
|  Indigenous        Due Diligence     Geographic              |
|  Knowledge         Documentation     Restrictions            |
|  Protocols         Search Steps      GDPR Compliance         |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                    WHAT YOU CAN DO                           |
+-------------------------------------------------------------+
|  + PREMIS Rights Basis (Copyright, License, Statute, Donor)  |
|  + RightsStatements.org - 12 standardized statements         |
|  + Creative Commons - All CC license variants                |
|  + Traditional Knowledge Labels - Local Contexts TK/BC       |
|  + Embargo Management - Time-based access restrictions       |
|  + Orphan Works - Due diligence tracking                     |
|  + Territory Restrictions - GDPR/geographic controls         |
|  + Access Derivatives - Watermarking controls                |
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
   AHG Settings
      |
      v
   Rights Management --------------------+
      |                                   |
      +---> Dashboard    (overview)       |
      |                                   |
      +---> Embargoes    (restrictions)   |
      |                                   |
      +---> Orphan Works (due diligence)  |
      |                                   |
      +---> TK Labels    (indigenous)     |
      |                                   |
      +---> Statements   (standards)      |
```

---

## Dashboard

The Rights Management dashboard shows:

```
+-------------------------------------------------------------+
|                    DASHBOARD OVERVIEW                        |
+-------------------------------------------------------------+
|  +----------------+  +----------------+  +----------------+  |
|  | Total Rights   |  | Active         |  | Expiring       |  |
|  | Records        |  | Embargoes      |  | Soon (30 days) |  |
|  |     156        |  |      23        |  |       5        |  |
|  +----------------+  +----------------+  +----------------+  |
|                                                              |
|  Expiring Embargoes Alert:                                   |
|  +-------------------------------------------------------+  |
|  | Object              | Expires      | Action           |  |
|  |---------------------|--------------|------------------|  |
|  | Board Minutes 1990  | 15 Feb 2026  | [Review]         |  |
|  | Personnel Files     | 28 Feb 2026  | [Review]         |  |
|  +-------------------------------------------------------+  |
|                                                              |
|  Rights by Basis (Chart):                                    |
|  [Copyright: 45%] [License: 30%] [Statute: 15%] [Other: 10%] |
+-------------------------------------------------------------+
```

---

## Adding Rights to a Record

### Step 1: Navigate to the Record

Go to the archival description you want to add rights to.

### Step 2: Access Rights Panel

Click **Rights** in the sidebar or action buttons.

### Step 3: Add New Rights Record

Click **Add** to create a new rights record.

### Step 4: Select Rights Basis
```
+-------------------------------------------------------------+
|                    RIGHTS BASIS OPTIONS                      |
+-------------------------------------------------------------+
|                                                              |
|  [x] Copyright    - Legal copyright protection               |
|  [ ] License      - Licensed content (CC, custom)            |
|  [ ] Statute      - Statutory/legal requirements             |
|  [ ] Donor        - Donor agreement restrictions             |
|  [ ] Policy       - Institutional policy                     |
|  [ ] Other        - Other basis                              |
|                                                              |
+-------------------------------------------------------------+
```

### Step 5: Complete the Form

Based on your selection:

**For Copyright:**
- Copyright status (In Copyright, Public Domain, Unknown)
- Copyright holder name
- Jurisdiction (e.g., ZA for South Africa)
- Determination date

**For License:**
- Creative Commons license selection
- Or custom license identifier
- License terms

**For Statute:**
- Statute citation
- Jurisdiction
- Determination date

---

## Rights Statements

RightsStatements.org provides 12 standardized statements:

### In Copyright
```
+-------------------------------------------------------------+
|  InC       | In Copyright                                    |
|  InC-OW-EU | In Copyright - EU Orphan Work                   |
|  InC-EDU   | In Copyright - Educational Use Permitted        |
|  InC-NC    | In Copyright - Non-Commercial Use Permitted     |
|  InC-RUU   | In Copyright - Rights-holder Unlocatable        |
+-------------------------------------------------------------+
```

### No Copyright
```
+-------------------------------------------------------------+
|  NoC-CR    | No Copyright - Contractual Restrictions         |
|  NoC-NC    | No Copyright - Non-Commercial Use Only          |
|  NoC-OKLR  | No Copyright - Other Known Legal Restrictions   |
|  NoC-US    | No Copyright - United States                    |
+-------------------------------------------------------------+
```

### Other
```
+-------------------------------------------------------------+
|  CNE       | Copyright Not Evaluated                         |
|  UND       | Copyright Undetermined                          |
|  NKC       | No Known Copyright                              |
+-------------------------------------------------------------+
```

---

## Creative Commons Licenses
```
+-------------------------------------------------------------+
|                    CC LICENSE OPTIONS                        |
+-------------------------------------------------------------+
|                                                              |
|  CC0-1.0        | Public Domain - No rights reserved         |
|  CC-BY-4.0      | Attribution - Credit required              |
|  CC-BY-SA-4.0   | Attribution-ShareAlike                     |
|  CC-BY-NC-4.0   | Attribution-NonCommercial                  |
|  CC-BY-NC-SA-4.0| Attribution-NonCommercial-ShareAlike       |
|  CC-BY-ND-4.0   | Attribution-NoDerivatives                  |
|  CC-BY-NC-ND-4.0| Attribution-NonCommercial-NoDerivatives    |
|  PDM-1.0        | Public Domain Mark                         |
|                                                              |
+-------------------------------------------------------------+
```

### License Attributes
```
+-------------------------------------------------------------+
|  License     | Commercial | Derivatives | Share Alike       |
+-------------------------------------------------------------+
|  CC0         |    Yes     |    Yes      |     No            |
|  CC-BY       |    Yes     |    Yes      |     No            |
|  CC-BY-SA    |    Yes     |    Yes      |     Yes           |
|  CC-BY-NC    |    No      |    Yes      |     No            |
|  CC-BY-NC-SA |    No      |    Yes      |     Yes           |
|  CC-BY-ND    |    Yes     |    No       |     No            |
|  CC-BY-NC-ND |    No      |    No       |     No            |
+-------------------------------------------------------------+
```

---

## Managing Embargoes

### What is an Embargo?

An embargo restricts access to a record for a specified period.

### Embargo Types
```
+-------------------------------------------------------------+
|  Type           | Description                                |
+-------------------------------------------------------------+
|  Full           | No access to metadata or digital objects   |
|  Metadata Only  | Metadata visible, no digital objects       |
|  Digital Only   | No digital objects, metadata visible       |
|  Partial        | Custom restrictions                        |
+-------------------------------------------------------------+
```

### Creating an Embargo

**Step 1:** Go to **Admin** > **AHG Settings** > **Rights Management** > **Embargoes**

**Step 2:** Click **Create New Embargo**

**Step 3:** Complete the form:
```
+-------------------------------------------------------------+
|                    NEW EMBARGO                               |
+-------------------------------------------------------------+
|                                                              |
|  Object ID:        [                    ]                    |
|                                                              |
|  Embargo Type:     [Full Embargo        v]                   |
|                                                              |
|  Reason:           [Donor Restriction   v]                   |
|                    - Donor Restriction                       |
|                    - Copyright                               |
|                    - Privacy                                 |
|                    - Legal Hold                              |
|                    - Commercial Sensitivity                  |
|                    - Research Embargo                        |
|                    - Cultural Sensitivity                    |
|                    - Security Classification                 |
|                                                              |
|  Start Date:       [2026-01-30        ]                      |
|  End Date:         [2030-12-31        ]                      |
|                                                              |
|  [x] Auto-release when end date is reached                   |
|                                                              |
|  Review Date:      [2028-01-30        ]                      |
|                                                              |
|  Notify Before:    [30] days                                 |
|                                                              |
|  Notification Emails: [archivist@example.com             ]   |
|                                                              |
|  Reason Note:      [Donor requested restriction until 2030]  |
|                                                              |
|                            [Save Embargo]                    |
+-------------------------------------------------------------+
```

### Managing Existing Embargoes
```
+-------------------------------------------------------------+
|  Actions Available:                                          |
+-------------------------------------------------------------+
|  [Lift Embargo]   - Remove restriction immediately           |
|  [Extend]         - Change end date to later date            |
|  [Review]         - Mark as reviewed, set next review date   |
|  [Edit]           - Modify embargo settings                  |
+-------------------------------------------------------------+
```

### Auto-Release

When enabled, embargoes automatically lift when the end date passes:

1. System checks daily for expired embargoes
2. Embargoes with auto-release enabled are lifted
3. Status changes to "Expired"
4. Action logged for audit trail

---

## Traditional Knowledge Labels

### What are TK Labels?

TK Labels are part of the Local Contexts initiative supporting Indigenous communities.

### Categories
```
+-------------------------------------------------------------+
|  TK Labels (Traditional Knowledge):                          |
+-------------------------------------------------------------+
|  TK-A    | TK Attribution       | Attribution required       |
|  TK-NC   | TK Non-Commercial    | Non-commercial use only    |
|  TK-C    | TK Community Voice   | Community consent needed   |
|  TK-CV   | TK Culturally Sens.  | Handle with sensitivity    |
|  TK-SS   | TK Secret/Sacred     | Restricted access          |
|  TK-MC   | TK Multiple Comm.    | Multiple community interest|
|  TK-MR   | TK Men Restricted    | Gender-specific access     |
|  TK-WR   | TK Women Restricted  | Gender-specific access     |
|  TK-SR   | TK Seasonal          | Time-restricted access     |
|  TK-F    | TK Family            | Family-specific content    |
|  TK-O    | TK Outreach          | Educational use permitted  |
|  TK-V    | TK Verified          | Protocols verified         |
|  TK-NV   | TK Non-Verified      | Protocols not yet verified |
+-------------------------------------------------------------+

+-------------------------------------------------------------+
|  BC Labels (Biocultural):                                    |
+-------------------------------------------------------------+
|  BC-R    | BC Research Use      | Research purposes only     |
|  BC-CB   | BC Consent Before    | Prior consent obtained     |
|  BC-P    | BC Provenance        | Provenance documented      |
|  BC-MC   | BC Multiple Comm.    | Multiple communities       |
|  BC-CL   | BC Clan              | Clan-specific content      |
|  BC-O    | BC Outreach          | Educational outreach       |
+-------------------------------------------------------------+
```

### Assigning TK Labels

**Step 1:** Go to **Rights Management** > **TK Labels**

**Step 2:** Click **Assign Label**

**Step 3:** Complete the form:
- Select the record (Object ID)
- Choose the appropriate TK Label
- Add community name
- Add community contact information
- Add any custom text from the community

---

## Orphan Works

### What is an Orphan Work?

A work whose rights holder cannot be identified or located after diligent search.

### Due Diligence Process

**Step 1:** Create Orphan Work Record
```
+-------------------------------------------------------------+
|  Work Type:     [Photograph        v]                        |
|                 - Literary Work                              |
|                 - Dramatic Work                              |
|                 - Musical Work                               |
|                 - Artistic Work                              |
|                 - Film                                       |
|                 - Sound Recording                            |
|                 - Photograph                                 |
|                 - Database                                   |
|                                                              |
|  Jurisdiction:  [ZA - South Africa v]                        |
|  Intended Use:  [Digitization and online access          ]   |
+-------------------------------------------------------------+
```

**Step 2:** Document Search Steps

For each source searched:
```
+-------------------------------------------------------------+
|                    ADD SEARCH STEP                           |
+-------------------------------------------------------------+
|  Source Type:     [Copyright Registry  v]                    |
|                   - Database/Registry                        |
|                   - Copyright Registry                       |
|                   - Publisher                                |
|                   - Author/Rights Society                    |
|                   - Archive/Library                          |
|                   - Internet Search                          |
|                   - Newspaper/Publication                    |
|                                                              |
|  Source Name:     [SAMRO                                 ]   |
|  Source URL:      [https://www.samro.org.za              ]   |
|  Search Date:     [2026-01-30                            ]   |
|  Search Terms:    [photographer name, date, location     ]   |
|                                                              |
|  Results Found:   [ ] Yes  [x] No                            |
|  Results Note:    [No matching records found             ]   |
|                                                              |
|                            [Add Step]                        |
+-------------------------------------------------------------+
```

**Step 3:** Complete Search

When sufficient searches completed:
- Mark search as complete
- Indicate if rights holder was found
- Document any proposed licensing fee

---

## Rights Grants (PREMIS Acts)

Define what actions are permitted or restricted:

### Available Acts
```
+-------------------------------------------------------------+
|  Act          | Description                                  |
+-------------------------------------------------------------+
|  Render       | Display/view the content                     |
|  Disseminate  | Distribute copies                            |
|  Replicate    | Make copies                                  |
|  Migrate      | Transform to other formats                   |
|  Modify       | Edit or alter                                |
|  Delete       | Remove from system                           |
|  Print        | Physical printing                            |
|  Publish      | Make publicly available                      |
|  Use          | General use                                  |
|  Excerpt      | Use portions                                 |
|  Annotate     | Add notes/comments                           |
+-------------------------------------------------------------+
```

### Restriction Types
```
+-------------------------------------------------------------+
|  Restriction   | Color     | Meaning                         |
+-------------------------------------------------------------+
|  Allow         | Green     | Permitted                       |
|  Disallow      | Red       | Not permitted                   |
|  Conditional   | Yellow    | Requires additional conditions  |
+-------------------------------------------------------------+
```

---

## Viewing Rights on Records

Rights information displays in the sidebar:
```
+-------------------------------------------------------------+
|  RIGHTS & RESTRICTIONS                                       |
+-------------------------------------------------------------+
|                                                              |
|  [!] Access Restricted                                       |
|  - Under embargo until 31 Dec 2030                           |
|                                                              |
|  Traditional Knowledge:                                      |
|  [TK-A] [TK-CV]                                             |
|                                                              |
|  Rights Records:                                             |
|  +-------------------------------------------------------+  |
|  | Basis     | Statement           | Acts    | Period    |  |
|  |-----------|---------------------|---------|-----------|  |
|  | Copyright | InC                 | Render  | 1990-Open |  |
|  | License   | CC-BY-NC-4.0        | Use     | 2020-     |  |
|  +-------------------------------------------------------+  |
|                                                              |
|                                          [Add Rights]        |
+-------------------------------------------------------------+
```

---

## Reports
```
+-------------------------------------------------------------+
|                    AVAILABLE REPORTS                         |
+-------------------------------------------------------------+
|                                                              |
|  Embargo Report:                                             |
|  - All active embargoes                                      |
|  - Expiring soon                                             |
|  - By reason type                                            |
|  - Export to CSV                                             |
|                                                              |
|  TK Label Report:                                            |
|  - All TK label assignments                                  |
|  - By community                                              |
|  - Verification status                                       |
|  - Export to CSV                                             |
|                                                              |
|  Rights Summary:                                             |
|  - By basis type                                             |
|  - By rights statement                                       |
|  - By CC license                                             |
|                                                              |
+-------------------------------------------------------------+
```

---

## Best Practices
```
+--------------------------------+--------------------------------+
|  DO                            |  DON'T                         |
+--------------------------------+--------------------------------+
|  Document rights thoroughly    |  Assume copyright status       |
|  Set review dates for embargoes|  Let embargoes expire unnoticed|
|  Consult communities for TK    |  Apply TK labels without input |
|  Document orphan work searches |  Skip due diligence steps      |
|  Use standardized statements   |  Create inconsistent metadata  |
|  Review rights periodically    |  Set and forget                |
+--------------------------------+--------------------------------+
```

---

## Compliance

The plugin supports compliance with:

| Standard | Implementation |
|----------|----------------|
| PAIA (SA) | Public access request integration |
| POPIA (SA) | Privacy-aware access controls |
| GDPR (EU) | Territory restrictions, consent tracking |
| Copyright Act | Jurisdiction-specific rights |
| PREMIS | Rights basis and acts vocabulary |

---

## Quick Reference

### Keyboard Shortcuts
- None (web-based interface)

### URLs
- Dashboard: `/rightsAdmin`
- Embargoes: `/rightsAdmin/embargoes`
- TK Labels: `/rightsAdmin/tkLabels`
- Statements: `/rightsAdmin/statements`

### Permissions Required
- View rights: Read access to record
- Edit rights: Editor or Administrator role
- Manage embargoes: Administrator only
- Assign TK Labels: Editor or Administrator

---

## Need Help?

Contact your system administrator for:
- Complex rights determinations
- Bulk rights updates
- Custom territory restrictions
- Integration questions

---

*Part of the AtoM AHG Framework*

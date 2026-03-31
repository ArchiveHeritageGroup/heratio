# Extended Rights Management

## User Guide

Manage copyright, licensing, access restrictions, and cultural heritage labels for your archival records.

---

## Workflow Overview
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   Select     │    │   Choose     │    │   Set        │    │   Apply      │
│   Record     │ ──▶│   Rights     │ ──▶│   Details    │ ──▶│   & Save     │
│              │    │   Type       │    │              │    │              │
│ Find item    │    │ Copyright    │    │ Dates        │    │ Record       │
│ to manage    │    │ License      │    │ Holder       │    │ updated      │
└──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘
```

---

## What Extended Rights Manages
```
┌─────────────────────────────────────────────────────────────┐
│                    EXTENDED RIGHTS                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📜 RIGHTS STATEMENTS                                       │
│     Standard copyright status indicators                    │
│     (In Copyright, No Copyright, Unknown, etc.)             │
│                                                             │
│  🅭 CREATIVE COMMONS                                        │
│     Open licenses for sharing and reuse                     │
│     (CC BY, CC BY-SA, CC BY-NC, CC0, etc.)                 │
│                                                             │
│  🔒 EMBARGO                                                 │
│     Temporary access restrictions                           │
│     (Closed until specific date)                            │
│                                                             │
│  🏷️  TRADITIONAL KNOWLEDGE LABELS                           │
│     Indigenous community protocols                          │
│     (TK Labels from Local Contexts)                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Access
```
Option A: From a Record                Option B: From Admin
───────────────────────                ────────────────────
                                       
  View Archival Description              Main Menu
         │                                   │
         ▼                                   ▼
  Scroll to "Rights" section             Admin
         │                                   │
         ▼                                   ▼
  Click "Add Extended Rights"            Rights Dashboard
         │                                   │
         ▼                                   ▼
  Extended Rights Form                   Overview of all rights
```

---

## Part 1: Rights Statements

### What Are Rights Statements?

Rights Statements are standardised indicators that tell users what they can do with a digital object.
```
┌─────────────────────────────────────────────────────────────┐
│ RIGHTS STATEMENTS CATEGORIES                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔴 IN COPYRIGHT                                            │
│     ├── In Copyright                                        │
│     ├── In Copyright - Educational Use Permitted            │
│     ├── In Copyright - EU Orphan Work                       │
│     ├── In Copyright - Non-Commercial Use Permitted         │
│     └── In Copyright - Rights-holder Unlocatable            │
│                                                             │
│  🟢 NO COPYRIGHT                                            │
│     ├── No Copyright - Contractual Restrictions             │
│     ├── No Copyright - Non-Commercial Use Only              │
│     ├── No Copyright - Other Known Legal Restrictions       │
│     └── No Copyright - United States                        │
│                                                             │
│  🟡 OTHER                                                   │
│     ├── Copyright Not Evaluated                             │
│     ├── Copyright Undetermined                              │
│     └── No Known Copyright                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Choosing a Rights Statement
```
                    Is copyright status known?
                           │
              ┌────────────┴────────────┐
              │                         │
             YES                        NO
              │                         │
              ▼                         ▼
      Is item in copyright?      ┌─────────────┐
              │                  │ Copyright   │
     ┌────────┴────────┐        │ Not         │
     │                 │        │ Evaluated   │
    YES               NO        └─────────────┘
     │                 │
     ▼                 ▼
┌──────────┐    ┌──────────┐
│   In     │    │   No     │
│Copyright │    │Copyright │
│(choose   │    │(choose   │
│ subtype) │    │ subtype) │
└──────────┘    └──────────┘
```

---

## Part 2: Creative Commons Licenses

### Understanding CC Licenses
```
┌─────────────────────────────────────────────────────────────┐
│ LICENSE        │ MEANING                                    │
├────────────────┼────────────────────────────────────────────┤
│                │                                            │
│  CC0           │ No rights reserved (public domain)         │
│                │ Anyone can use for any purpose             │
│                │                                            │
│  CC BY         │ Credit must be given                       │
│                │ Commercial use allowed                     │
│                │                                            │
│  CC BY-SA      │ Credit + Share alike                       │
│                │ Derivatives must use same license          │
│                │                                            │
│  CC BY-NC      │ Credit + Non-commercial only               │
│                │ No commercial use                          │
│                │                                            │
│  CC BY-NC-SA   │ Credit + Non-commercial + Share alike      │
│                │ Strictest open license                     │
│                │                                            │
│  CC BY-ND      │ Credit + No derivatives                    │
│                │ Cannot modify or remix                     │
│                │                                            │
│  CC BY-NC-ND   │ Credit + Non-commercial + No derivatives   │
│                │ Most restrictive CC license                │
│                │                                            │
└────────────────┴────────────────────────────────────────────┘
```

### Which License to Choose?
```
         Do you want to allow commercial use?
                        │
              ┌─────────┴─────────┐
              │                   │
             YES                  NO
              │                   │
              ▼                   ▼
    Allow modifications?    Allow modifications?
              │                   │
      ┌───────┴───────┐    ┌──────┴──────┐
      │       │       │    │      │      │
     YES  SHARE-ALIKE NO  YES    SA     NO
      │       │       │    │      │      │
      ▼       ▼       ▼    ▼      ▼      ▼
   ┌────┐ ┌──────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐
   │BY  │ │BY-SA │ │BY- │ │BY- │ │BY- │ │BY- │
   │    │ │      │ │ND  │ │NC  │ │NC- │ │NC- │
   │    │ │      │ │    │ │    │ │SA  │ │ND  │
   └────┘ └──────┘ └────┘ └────┘ └────┘ └────┘
```

---

## Part 3: Embargo Management

### What is an Embargo?

An embargo restricts access to a record until a specific date.
```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   EMBARGO TIMELINE                                          │
│                                                             │
│   Today           Embargo End        After Embargo          │
│     │                 │                   │                 │
│     ▼                 ▼                   ▼                 │
│   ━━━━━━━━━━━━━━━━━━━━╋━━━━━━━━━━━━━━━━━━━━━━━━━━━━━▶      │
│                       │                                     │
│   🔒 RESTRICTED       │   🔓 OPEN ACCESS                    │
│   • Hidden from       │   • Visible to all                  │
│     public search     │   • Searchable                      │
│   • Staff access      │   • Downloadable                    │
│     only              │                                     │
│                       │                                     │
└─────────────────────────────────────────────────────────────┘
```

### Setting an Embargo
```
┌─────────────────────────────────────────────────────────────┐
│ SET EMBARGO                                                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Record:        ABC/001/005 - Personal Correspondence       │
│                                                             │
│  Embargo Type:  [ Time-based           ▼]                   │
│                 ┌─────────────────────────┐                 │
│                 │ Time-based              │                 │
│                 │ Death + Years           │                 │
│                 │ Donor Restriction       │                 │
│                 │ Legal Hold              │                 │
│                 └─────────────────────────┘                 │
│                                                             │
│  Start Date:    [ 01/01/2020  📅]                          │
│                                                             │
│  End Date:      [ 01/01/2050  📅]  ← Access opens           │
│                                                             │
│  Reason:        [Personal information - 30 year closure___] │
│                                                             │
│  Exceptions:    ☐ Allow researcher access with approval     │
│                 ☐ Allow staff access                        │
│                 ☐ Show metadata only                        │
│                                                             │
│                              [ Cancel ]  [ Apply Embargo ]  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Embargo Status Dashboard
```
┌─────────────────────────────────────────────────────────────┐
│ EMBARGO OVERVIEW                                            │
├──────────────────┬──────────────────┬───────────────────────┤
│                  │                  │                       │
│   ACTIVE         │  EXPIRING SOON   │   EXPIRED             │
│   EMBARGOES      │  (next 90 days)  │   (review needed)     │
│                  │                  │                       │
│      47          │       5          │       12              │
│    records       │    records       │     records           │
│                  │     ⚠️            │                       │
│                  │                  │                       │
└──────────────────┴──────────────────┴───────────────────────┘

EXPIRING SOON:
┌─────────────────────────────────────────────────────────────┐
│ Reference    │ Title              │ Expires    │ Action    │
├──────────────┼────────────────────┼────────────┼───────────┤
│ ABC/001/005  │ Smith Letters      │ 15 Feb 26  │ [Review]  │
│ DEF/003/012  │ Board Minutes 1995 │ 28 Feb 26  │ [Review]  │
│ GHI/007/001  │ Personnel File     │ 01 Mar 26  │ [Review]  │
└──────────────┴────────────────────┴────────────┴───────────┘
```

---

## Part 4: Traditional Knowledge Labels

### What Are TK Labels?

TK Labels are designed by indigenous communities to indicate cultural protocols for using traditional knowledge and cultural heritage materials.
```
┌─────────────────────────────────────────────────────────────┐
│ TK LABEL CATEGORIES                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔵 PROVENANCE                                              │
│     TK Attribution     - Credit this community              │
│     TK Family          - Family ownership                   │
│     TK Clan            - Clan ownership                     │
│                                                             │
│  🟢 PROTOCOLS                                               │
│     TK Community Voice - Community should be consulted      │
│     TK Culturally Sensitive - Handle with care              │
│     TK Secret/Sacred   - Restricted viewing                 │
│                                                             │
│  🟡 PERMISSIONS                                             │
│     TK Non-Commercial  - No commercial use                  │
│     TK Verified        - Community verified                 │
│     TK Open to Collaboration - Welcomes engagement          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Applying TK Labels
```
┌─────────────────────────────────────────────────────────────┐
│ ADD TRADITIONAL KNOWLEDGE LABELS                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Record:  ABC/009/003 - Traditional Healing Practices       │
│                                                             │
│  Select applicable labels:                                  │
│                                                             │
│  ☑ TK Attribution                                           │
│    Credit: [Ndebele Community Council______________]        │
│                                                             │
│  ☑ TK Culturally Sensitive                                  │
│    Note:  [Contains sacred ceremonial information__]        │
│                                                             │
│  ☑ TK Non-Commercial                                        │
│    Note:  [May not be used for commercial purposes_]        │
│                                                             │
│  ☐ TK Secret/Sacred                                         │
│  ☐ TK Community Voice Only                                  │
│  ☐ TK Family                                                │
│                                                             │
│                              [ Cancel ]  [ Apply Labels ]   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Adding Extended Rights to a Record

### Complete Workflow
```
                         START
                           │
                           ▼
              ┌────────────────────────┐
              │  View archival record  │
              │  Click "Edit Rights"   │
              └───────────┬────────────┘
                          │
                          ▼
              ┌────────────────────────┐
              │  Select Rights Type    │
              │  (can choose multiple) │
              └───────────┬────────────┘
                          │
        ┌─────────┬───────┴───────┬─────────┐
        │         │               │         │
        ▼         ▼               ▼         ▼
   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
   │ Rights  │ │Creative │ │ Set     │ │   TK    │
   │Statement│ │Commons  │ │Embargo  │ │ Labels  │
   └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘
        │           │           │           │
        └─────────┬─┴───────────┴───────────┘
                  │
                  ▼
        ┌────────────────────────┐
        │  Add Rights Holder     │
        │  (name, contact, URI)  │
        └───────────┬────────────┘
                    │
                    ▼
        ┌────────────────────────┐
        │  Set Dates             │
        │  • Effective from      │
        │  • Expires on          │
        └───────────┬────────────┘
                    │
                    ▼
        ┌────────────────────────┐
        │  Add Notes             │
        │  (any special terms)   │
        └───────────┬────────────┘
                    │
                    ▼
        ┌────────────────────────┐
        │        SAVE            │
        └───────────┬────────────┘
                    │
                    ▼
              ┌───────────┐
              │  COMPLETE │
              │  Rights   │
              │  Applied  │
              └───────────┘
```

---

## Extended Rights Form
```
┌─────────────────────────────────────────────────────────────┐
│ EXTENDED RIGHTS                                     [Save]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Record: ABC/001/012 - Photograph Album 1935                │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  COPYRIGHT STATUS                                           │
│                                                             │
│  Rights Statement:  [ In Copyright              ▼]          │
│                                                             │
│  Creative Commons:  [ CC BY-NC (Attribution-   ▼]          │
│                       Non-Commercial)                       │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  RIGHTS HOLDER                                              │
│                                                             │
│  Name:              [Smith Family Trust_________]           │
│  Contact:           [trust@smithfamily.co.za____]           │
│  URI:               [___________________________ ]          │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  DATES                                                      │
│                                                             │
│  Effective From:    [ 01/01/1935  📅]                      │
│  Expires On:        [ 01/01/2035  📅]  (70 years after)    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  EMBARGO (Optional)                                         │
│                                                             │
│  ☐ Apply embargo    End Date: [___________📅]              │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  TK LABELS (Optional)                                       │
│                                                             │
│  ☐ TK Attribution   ☐ TK Culturally Sensitive              │
│  ☐ TK Non-Commercial ☐ TK Secret/Sacred                    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  NOTES                                                      │
│                                                             │
│  [Permission granted for non-commercial research use.     ] │
│  [Contact rights holder for publication permissions.      ] │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Rights Display on Record
```
┌─────────────────────────────────────────────────────────────┐
│ 📜 RIGHTS INFORMATION                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🔴 IN COPYRIGHT                                      │   │
│  │    This item is protected by copyright.              │   │
│  │                                                      │   │
│  │    Rights Holder: Smith Family Trust                 │   │
│  │    Expires: 01 January 2035                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🅭 CC BY-NC                                          │   │
│  │    Attribution-NonCommercial 4.0 International       │   │
│  │                                                      │   │
│  │    You may share and adapt this work for non-        │   │
│  │    commercial purposes with attribution.             │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🏷️ TK ATTRIBUTION                                    │   │
│  │    Credit: Ndebele Community Council                 │   │
│  │                                                      │   │
│  │    Please acknowledge the community when using       │   │
│  │    this material.                                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Quick Reference
```
┌─────────────────────────────────────────────────────────────┐
│  TASK                      │  HOW TO DO IT                  │
├────────────────────────────┼────────────────────────────────┤
│  Add rights to record      │  View record → Edit Rights     │
│  View embargo dashboard    │  Admin → Rights → Embargoes    │
│  Export rights data        │  Admin → Rights → Export       │
│  Check expiring embargoes  │  Admin → Rights → Expiring     │
│  Apply TK labels           │  Edit Rights → TK Labels tab   │
│  Bulk update rights        │  Admin → Rights → Batch Update │
└────────────────────────────┴────────────────────────────────┘
```

---

## Tips for Best Practice
```
┌─────────────────────────────────────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                  │
├────────────────────────────────┼────────────────────────────┤
│  Research copyright status     │  Guess at rights status   │
│  Record rights holder contact  │  Leave holder blank       │
│  Set expiry dates              │  Use indefinite embargoes │
│  Consult communities for TK    │  Apply TK labels alone    │
│  Review embargoes regularly    │  Forget expiring items    │
│  Document your decisions       │  Skip the notes field     │
└────────────────────────────────┴────────────────────────────┘
```

---

## Part 5: CLI Commands (System Administrators)

For system administrators, the plugin provides command-line tools for automated embargo management.

### Automated Embargo Processing
```
┌─────────────────────────────────────────────────────────────┐
│  EMBARGO:PROCESS - Automated Daily Processing               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Process all (lift expired + send notifications):           │
│  $ php symfony embargo:process                              │
│                                                             │
│  Preview without making changes:                            │
│  $ php symfony embargo:process --dry-run                    │
│                                                             │
│  Send notifications only:                                   │
│  $ php symfony embargo:process --notify-only                │
│                                                             │
│  Lift expired embargoes only:                               │
│  $ php symfony embargo:process --lift-only                  │
│                                                             │
│  Custom warning intervals:                                  │
│  $ php symfony embargo:process --warn-days=14,7,3           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Cron Setup
```
┌─────────────────────────────────────────────────────────────┐
│  RECOMMENDED CRON CONFIGURATION                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Run daily at 6am to:                                       │
│  • Automatically lift expired embargoes                     │
│  • Send expiry warning notifications (30, 7, 1 days)        │
│                                                             │
│  Add to crontab:                                            │
│  0 6 * * * cd /usr/share/nginx/archive && \                 │
│            php symfony embargo:process                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Embargo Reports
```
┌─────────────────────────────────────────────────────────────┐
│  EMBARGO:REPORT - Generate Reports                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Summary statistics:                                        │
│  $ php symfony embargo:report                               │
│                                                             │
│  List all active embargoes:                                 │
│  $ php symfony embargo:report --active                      │
│                                                             │
│  List embargoes expiring in N days:                         │
│  $ php symfony embargo:report --expiring=30                 │
│                                                             │
│  List recently lifted embargoes:                            │
│  $ php symfony embargo:report --lifted --days=7             │
│                                                             │
│  List expired but not lifted:                               │
│  $ php symfony embargo:report --expired                     │
│                                                             │
│  Export as CSV:                                             │
│  $ php symfony embargo:report --active --format=csv \       │
│                               --output=/tmp/report.csv      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Report Output Example
```
$ php symfony embargo:report

=== Embargo Status Report ===

Summary Statistics:
  Total Active Embargoes:    47
  Expiring in 30 days:        5
  Expired (not lifted):      12
  Lifted (last 30 days):      8

Embargo Types:
  Full:           23 (49%)
  Metadata Only:  12 (26%)
  Digital Only:    8 (17%)
  Partial:         4 (8%)

Top Embargo Reasons:
  1. Donor Restriction    18
  2. Privacy              14
  3. Copyright             9
  4. Legal                 6
```

---

## Troubleshooting
```
Problem                          Solution
───────────────────────────────────────────────────────────
Can't find rights option      →  Check you have edit permission
                                 May need administrator access
                                 
Embargo not working           →  Check start/end dates
                                 Verify record is published
                                 Clear cache
                                 
TK labels not showing         →  Labels may need approval
                                 Check community settings
                                 
Rights statement missing      →  Administrator may need to
                                 enable vocabulary
                                 
CC license not in list        →  Check license is active
                                 Contact administrator
```

---

## Need Help?

Contact your system administrator if you experience issues.

For more information on standards:
- Rights Statements: https://rightsstatements.org
- Creative Commons: https://creativecommons.org
- TK Labels: https://localcontexts.org

---

*Part of the AtoM AHG Framework*

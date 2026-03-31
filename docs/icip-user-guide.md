# Indigenous Cultural and Intellectual Property (ICIP)

## User Guide

Manage Indigenous Cultural and Intellectual Property for Australian GLAM institutions, supporting ethical stewardship of First Nations cultural materials.

---

## Overview

```
+---------------------------------------------------------------------+
|                       ICIP MANAGEMENT                                |
+---------------------------------------------------------------------+
|                                                                     |
|   COMMUNITIES     CONSENT      NOTICES       TK LABELS              |
|       |              |            |              |                  |
|       v              v            v              v                  |
|   Aboriginal &   Track       Cultural      Traditional              |
|   Torres Strait  permission  sensitivity   Knowledge                |
|   Islander       status      warnings      labels from              |
|   communities                              Local Contexts           |
|                                                                     |
|   CONSULTATIONS        RESTRICTIONS         REPORTS                 |
|       |                    |                    |                   |
|       v                    v                    v                   |
|   Community          ICIP-specific        Dashboard &               |
|   engagement         access controls      compliance                |
|   tracking                                reporting                 |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Legal Framework

```
+---------------------------------------------------------------------+
|                    SUPPORTED STANDARDS                               |
+---------------------------------------------------------------------+
|                                                                     |
|   UNDRIP Article 31                                                 |
|   UN Declaration on the Rights of Indigenous Peoples                |
|   - Right to maintain, control, protect cultural heritage           |
|                                                                     |
|   Creative Australia Protocols                                      |
|   First Nations Cultural and Intellectual Property protocols        |
|                                                                     |
|   AIATSIS Code of Ethics                                            |
|   Aboriginal and Torres Strait Islander Research guidelines         |
|                                                                     |
|   Local Contexts                                                    |
|   Traditional Knowledge (TK) and Biocultural (BC) Labels            |
|                                                                     |
+---------------------------------------------------------------------+
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
   ICIP Management (/icip)
      |
      +-----> Dashboard         (overview & statistics)
      |
      +-----> Communities       (community registry)
      |
      +-----> Consent Records   (consent management)
      |
      +-----> Consultations     (engagement log)
      |
      +-----> TK Labels         (Local Contexts labels)
      |
      +-----> Cultural Notices  (sensitivity warnings)
      |
      +-----> Restrictions      (access controls)
      |
      +-----> Reports           (compliance reports)
```

---

## Dashboard

The ICIP dashboard provides an overview of all Indigenous Cultural and Intellectual Property management activities.

### Key Statistics

```
+----------------+  +----------------+  +----------------+  +----------------+
|                |  |                |  |                |  |                |
| ICIP RECORDS   |  | COMMUNITIES    |  | PENDING        |  | EXPIRING       |
|                |  |                |  | CONSULTATION   |  | CONSENTS       |
|    [123]       |  |    [15]        |  |    [8]         |  |    [3]         |
|                |  |                |  |                |  |                |
| Total records  |  | Registered     |  | Awaiting       |  | Consents due   |
| with ICIP      |  | communities    |  | community      |  | to expire in   |
| content        |  |                |  | consultation   |  | 90 days        |
|                |  |                |  |                |  |                |
+----------------+  +----------------+  +----------------+  +----------------+
```

### Secondary Metrics

```
+---------------------+  +---------------------+  +---------------------+
|                     |  |                     |  |                     |
| TK Labels Applied   |  | Active Restrictions |  | Follow-ups Due      |
|       [45]          |  |       [12]          |  |       [5]           |
|                     |  |                     |  |                     |
| Traditional         |  | Records with        |  | Consultations       |
| Knowledge labels    |  | current ICIP        |  | requiring           |
| on records          |  | access controls     |  | follow-up action    |
|                     |  |                     |  |                     |
+---------------------+  +---------------------+  +---------------------+
```

---

## Community Registry

Track Aboriginal and Torres Strait Islander communities linked to your collection materials.

### Adding a Community

1. Go to **ICIP** > **Communities**
2. Click **Add Community**
3. Complete the form:

```
+---------------------------------------------------------------------+
|                     ADD COMMUNITY                                    |
+---------------------------------------------------------------------+
|                                                                     |
|  Community Name: [Wiradjuri                              ]          |
|                                                                     |
|  Alternate Names: [Wiradyuri, Wiradjeri                  ]          |
|                   (comma-separated)                                 |
|                                                                     |
|  Language Group:  [Wiradjuri                             ]          |
|                                                                     |
|  Region:          [Central NSW                           ]          |
|                                                                     |
|  State/Territory: [NSW  v]                                          |
|                                                                     |
|  ----------------------------------------------------------------   |
|  CONTACT INFORMATION                                                |
|  ----------------------------------------------------------------   |
|                                                                     |
|  Contact Name:    [                                      ]          |
|  Contact Email:   [                                      ]          |
|  Contact Phone:   [                                      ]          |
|  Contact Address: [                                      ]          |
|                                                                     |
|  ----------------------------------------------------------------   |
|  NATIVE TITLE INFORMATION                                           |
|  ----------------------------------------------------------------   |
|                                                                     |
|  Native Title Reference:    [                            ]          |
|  Prescribed Body Corporate: [                            ]          |
|  PBC Contact Email:         [                            ]          |
|                                                                     |
|  [x] Active                                                         |
|                                                                     |
|                              [Cancel]  [Save Community]             |
+---------------------------------------------------------------------+
```

### Community List

```
+---------+--------------+-----------+-------+------------------+--------+
| Name    | Language     | Region    | State | Contact          | Status |
+---------+--------------+-----------+-------+------------------+--------+
| Yorta   | Yorta Yorta  | Murray    | VIC   | J. Morgan        | Active |
| Yorta   |              | River     |       | j.m@example.com  |        |
+---------+--------------+-----------+-------+------------------+--------+
| Noongar | Nyungar      | Southwest | WA    | K. Smith         | Active |
|         |              | WA        |       |                  |        |
+---------+--------------+-----------+-------+------------------+--------+
```

### State/Territory Codes

| Code | Territory |
|------|-----------|
| NSW | New South Wales |
| VIC | Victoria |
| QLD | Queensland |
| WA | Western Australia |
| SA | South Australia |
| TAS | Tasmania |
| NT | Northern Territory |
| ACT | Australian Capital Territory |

---

## Consent Management

Track consent status for records containing Indigenous cultural materials.

### Consent Workflow

```
                          +-------------------+
                          |                   |
                          |     UNKNOWN       |
                          |   (Default)       |
                          |                   |
                          +--------+----------+
                                   |
                                   v
                     +-------------+-------------+
                     |                           |
                     v                           v
          +------------------+        +-------------------+
          |                  |        |                   |
          | NOT REQUIRED     |        | PENDING           |
          | (No ICIP         |        | CONSULTATION      |
          |  content)        |        |                   |
          +------------------+        +--------+----------+
                                               |
                                               v
                                      +-------------------+
                                      |                   |
                                      | CONSULTATION      |
                                      | IN PROGRESS       |
                                      |                   |
                                      +--------+----------+
                                               |
               +---------------+---------------+---------------+
               |               |               |               |
               v               v               v               v
     +-----------+    +------------+   +-----------+    +---------+
     |           |    |            |   |           |    |         |
     | FULL      |    | CONDITIONAL|   | RESTRICTED|    | DENIED  |
     | CONSENT   |    | CONSENT    |   | CONSENT   |    |         |
     |           |    |            |   |           |    |         |
     +-----------+    +------------+   +-----------+    +---------+
```

### Consent Status Options

| Status | Description | Usage |
|--------|-------------|-------|
| Not Required | No ICIP content | Materials with no Indigenous connection |
| Pending Consultation | Awaiting contact | Need to initiate community consultation |
| Consultation in Progress | Active discussions | Currently consulting with community |
| Conditional Consent | Consent with conditions | Consent granted with specific restrictions |
| Full Consent | Unrestricted consent | Community has granted full permission |
| Restricted Consent | Limited consent | Consent for specific uses only |
| Denied | Consent refused | Community has not granted permission |
| Unknown | Status unclear | Need to determine ICIP status |

### Consent Scope Options

Define what the consent covers:

```
+---------------------------------------------------------------------+
|                     CONSENT SCOPE                                    |
+---------------------------------------------------------------------+
|                                                                     |
|  [ ] Preservation Only    - Storage and conservation                |
|  [ ] Internal Access      - Staff viewing only                      |
|  [ ] Public Access        - Open to the public                      |
|  [ ] Reproduction         - Copying and reproductions               |
|  [ ] Commercial Use       - For-profit activities                   |
|  [ ] Educational Use      - Teaching and learning                   |
|  [ ] Research Use         - Academic research                       |
|  [ ] Full Rights          - All uses permitted                      |
|                                                                     |
+---------------------------------------------------------------------+
```

### Adding a Consent Record

1. Navigate to a record's ICIP tab, or go to **ICIP** > **Consent Records**
2. Click **Add Consent** or **Record Consent**
3. Complete the form:

```
+---------------------------------------------------------------------+
|                     CONSENT RECORD                                   |
+---------------------------------------------------------------------+
|                                                                     |
|  Record:          [Box 15 - Photographs 1920s           ]           |
|                                                                     |
|  Community:       [Wiradjuri                        v]              |
|                                                                     |
|  Consent Status:  [Full Consent                     v]              |
|                                                                     |
|  Consent Scope:                                                     |
|  [x] Preservation Only                                              |
|  [x] Internal Access                                                |
|  [x] Public Access                                                  |
|  [ ] Commercial Use                                                 |
|  [x] Educational Use                                                |
|  [x] Research Use                                                   |
|                                                                     |
|  Consent Date:    [2025-06-15        ]                              |
|  Expiry Date:     [2030-06-15        ]  (optional)                  |
|                                                                     |
|  Granted By:      [Elder Mary Johnson               ]               |
|                                                                     |
|  Conditions:                                                        |
|  [Must acknowledge community in any publication.     ]              |
|                                                                     |
|                              [Cancel]  [Save]                       |
+---------------------------------------------------------------------+
```

---

## Cultural Notices

Apply cultural sensitivity warnings to records.

### Notice Types

```
+-------------------+------------+--------------------------------------+
|      Notice       |  Severity  |            Description               |
+-------------------+------------+--------------------------------------+
| Cultural          | Info       | General cultural sensitivity         |
| Sensitivity       |            | awareness                            |
+-------------------+------------+--------------------------------------+
| Aboriginal and    | Warning    | May contain images/names of          |
| Torres Strait     |            | deceased persons                     |
| Islander          |            |                                      |
+-------------------+------------+--------------------------------------+
| Deceased Person   | Warning    | Contains images/names of             |
|                   |            | deceased people                      |
+-------------------+------------+--------------------------------------+
| Sacred/Secret     | Critical   | Contains sacred or secret            |
| Material          |            | cultural content                     |
+-------------------+------------+--------------------------------------+
| Men's Business    | Critical   | Restricted to initiated men          |
+-------------------+------------+--------------------------------------+
| Women's Business  | Critical   | Restricted to initiated women        |
+-------------------+------------+--------------------------------------+
| Ceremonial        | Critical   | Contains ceremonial content          |
+-------------------+------------+--------------------------------------+
| Community Only    | Critical   | Restricted to community members      |
+-------------------+------------+--------------------------------------+
| Seasonal          | Warning    | Time-based viewing restrictions      |
| Restriction       |            |                                      |
+-------------------+------------+--------------------------------------+
```

### Adding a Cultural Notice

1. Navigate to record's ICIP tab > **Notices**
2. Click **Add Notice**
3. Select notice type and configure:

```
+---------------------------------------------------------------------+
|                     ADD CULTURAL NOTICE                              |
+---------------------------------------------------------------------+
|                                                                     |
|  Notice Type: [Aboriginal and Torres Strait Islander   v]           |
|                                                                     |
|  Custom Text: [                                          ]          |
|  (Optional - overrides default text)                                |
|                                                                     |
|  Community:   [Wiradjuri                               v]           |
|                                                                     |
|  [x] Applies to descendant records                                  |
|                                                                     |
|  Seasonal Dates (optional):                                         |
|  Start Date:  [           ]                                         |
|  End Date:    [           ]                                         |
|                                                                     |
|                              [Cancel]  [Add Notice]                 |
+---------------------------------------------------------------------+
```

### Notice Display

Cultural notices appear on record pages:

```
+---------------------------------------------------------------------+
|  ! WARNING                                                          |
|  -----------------------------------------------------------------  |
|  Aboriginal and Torres Strait Islander peoples should be aware     |
|  that this collection may contain images, voices, or names of      |
|  deceased persons.                                                  |
|                                                                     |
|                          [I Acknowledge]                            |
+---------------------------------------------------------------------+
```

---

## TK Labels (Traditional Knowledge Labels)

Apply Traditional Knowledge Labels from Local Contexts to records.

### What are TK Labels?

TK Labels are a suite of labels developed by Local Contexts (localcontexts.org) that Indigenous communities can use to express local protocols for access and use.

### TK Label Categories

```
+---------------------------------------------------------------------+
|                     TK LABELS (TRADITIONAL KNOWLEDGE)                |
+---------------------------------------------------------------------+
|                                                                     |
|  [TK A]  Attribution     - Correct historical attribution          |
|  [TK CL] Clan            - Clan ownership                          |
|  [TK F]  Family          - Family ownership                        |
|  [TK MC] Multiple Comm.  - Multiple community interests            |
|  [TK NC] Non-Commercial  - Non-commercial use conditions           |
|  [TK O]  Outreach        - Community outreach ongoing              |
|  [TK S]  Secret/Sacred   - Access restrictions apply               |
|  [TK V]  Verified        - Community verified information          |
|  [TK CS] Culturally      - Culturally sensitive material           |
|          Sensitive                                                  |
|  [TK CV] Community Voice - Should be heard by community            |
|  [TK CO] Community Only  - Community use only                      |
|  [TK WR] Women Restricted- Gender restrictions (women)             |
|  [TK WG] Women General   - Traditionally belongs to women          |
|  [TK MR] Men Restricted  - Gender restrictions (men)               |
|  [TK MG] Men General     - Traditionally belongs to men            |
|  [TK SS] Seasonal        - Seasonal restrictions                   |
|                                                                     |
+---------------------------------------------------------------------+
|                     BC LABELS (BIOCULTURAL)                          |
+---------------------------------------------------------------------+
|                                                                     |
|  [BC P]  Provenance      - Origins of biological resources         |
|  [BC MC] Multiple Comm.  - Multiple community interests            |
|  [BC CL] Clan            - Clan ownership of resources             |
|  [BC CNC]Commercial/     - Commercial use conditions               |
|          Non-Commercial                                             |
|  [BC O]  Outreach        - Outreach regarding resources            |
|  [BC R]  Research Use    - Research use conditions                 |
|                                                                     |
+---------------------------------------------------------------------+
```

### Applying a TK Label

1. Navigate to record's ICIP tab > **TK Labels**
2. Click **Add Label**
3. Configure:

```
+---------------------------------------------------------------------+
|                     ADD TK LABEL                                     |
+---------------------------------------------------------------------+
|                                                                     |
|  Label Type:    [TK Attribution (TK A)                 v]           |
|                                                                     |
|  Community:     [Yorta Yorta                           v]           |
|                                                                     |
|  Applied By:    ( ) Community                                       |
|                 (*) Institution                                     |
|                                                                     |
|  Local Contexts Project ID: [                          ]            |
|  (If linked to a Local Contexts Hub project)                        |
|                                                                     |
|  Notes:         [                                      ]            |
|                                                                     |
|                              [Cancel]  [Add Label]                  |
+---------------------------------------------------------------------+
```

### Label Display on Records

```
+------+  +------+  +------+
| TK A |  | TK V |  | TK NC|
+------+  +------+  +------+
  |          |         |
  v          v         v
Attribution  Verified  Non-Commercial
```

---

## Access Restrictions

Apply ICIP-specific access controls that can override standard security clearance.

### Restriction Types

| Restriction | Description |
|-------------|-------------|
| Community Permission Required | Must obtain community approval |
| Gender Restricted (Male) | Men only access |
| Gender Restricted (Female) | Women only access |
| Initiated Only | Restricted to initiated persons |
| Seasonal | Time-based viewing restrictions |
| Mourning Period | Temporarily restricted |
| Repatriation Pending | Under repatriation process |
| Under Consultation | Active community consultation |
| Elder Approval Required | Requires elder consent |
| Custom | Custom restriction text |

### Adding a Restriction

```
+---------------------------------------------------------------------+
|                     ADD ACCESS RESTRICTION                           |
+---------------------------------------------------------------------+
|                                                                     |
|  Restriction Type: [Community Permission Required      v]           |
|                                                                     |
|  Community:        [Noongar                            v]           |
|                                                                     |
|  Start Date:       [2025-01-15       ]                              |
|  End Date:         [                 ]  (leave blank = indefinite)  |
|                                                                     |
|  [x] Applies to descendant records                                  |
|  [x] Overrides standard security clearance                          |
|                                                                     |
|  Notes:            [Awaiting consultation outcome      ]            |
|                                                                     |
|                              [Cancel]  [Add Restriction]            |
+---------------------------------------------------------------------+
```

### Override Security Clearance

When **Override Security Clearance** is enabled:
- ICIP restrictions take precedence over standard AtoM access controls
- Even users with high security clearance cannot bypass ICIP restrictions
- Ensures cultural protocols are respected

---

## Consultation Log

Track all community consultations and engagements.

### Consultation Types

| Type | Description |
|------|-------------|
| Initial Contact | First contact with community |
| Consent Request | Requesting consent for materials |
| Access Request | Community requesting access |
| Repatriation | Returning materials discussions |
| Digitisation | Digital copying discussions |
| Exhibition | Display/exhibition planning |
| Publication | Publication permission requests |
| Research | Research project consultations |
| General | General community engagement |
| Follow Up | Follow-up to previous consultation |

### Recording a Consultation

1. Go to **ICIP** > **Consultations** > **Add Consultation**
2. Complete the consultation form:

```
+---------------------------------------------------------------------+
|                     LOG CONSULTATION                                 |
+---------------------------------------------------------------------+
|                                                                     |
|  Record (optional): [Box 15 - Photographs             v]            |
|                                                                     |
|  Community:         [Wiradjuri                        v]            |
|                                                                     |
|  ----------------------------------------------------------------   |
|  CONSULTATION DETAILS                                               |
|  ----------------------------------------------------------------   |
|                                                                     |
|  Type:              [Consent Request                  v]            |
|  Date:              [2025-06-15        ]                            |
|  Method:            [In Person                        v]            |
|  Location:          [Dubbo Community Hall             ]             |
|                                                                     |
|  ----------------------------------------------------------------   |
|  ATTENDEES                                                          |
|  ----------------------------------------------------------------   |
|                                                                     |
|  Community Representatives:                                         |
|  [Elder Mary Johnson, David Williams                  ]             |
|                                                                     |
|  Institution Representatives:                                       |
|  [Jane Smith (Archivist), Tom Brown (Director)        ]             |
|                                                                     |
|  ----------------------------------------------------------------   |
|  OUTCOMES                                                           |
|  ----------------------------------------------------------------   |
|                                                                     |
|  Summary:                                                           |
|  [Discussed photographs from 1920s expedition. Community           ]
|  [identified several family members. Requested access to           ]
|  [high-resolution copies for cultural education program.           ]
|                                                                     |
|  Outcomes:                                                          |
|  [Agreed to provide digital copies for community use.              ]
|  [Community will provide additional context information.           ]
|                                                                     |
|  ----------------------------------------------------------------   |
|  FOLLOW-UP                                                          |
|  ----------------------------------------------------------------   |
|                                                                     |
|  Status:            [Completed                        v]            |
|  Follow-up Date:    [2025-07-15       ]                             |
|  Follow-up Notes:   [Prepare digital copies package   ]             |
|                                                                     |
|  [ ] Mark as Confidential                                           |
|                                                                     |
|                              [Cancel]  [Save]                       |
+---------------------------------------------------------------------+
```

### Consultation Timeline

```
  2025-06-15 |----+
             |    |   COMPLETED
             |    |   Initial Contact - Wiradjuri
             |    |   Discussed photographs from 1920s expedition
             |    |
  2025-07-01 |----+
             |    |   COMPLETED
             |    |   Consent Request - Wiradjuri
             |    |   Community granted conditional consent
             |    |
  2025-07-20 |----+
             |    |   FOLLOW-UP REQUIRED
             |    |   Follow Up - Wiradjuri
             |    |   Awaiting additional information
             |    |
```

---

## Record ICIP View

Each archival record has an ICIP tab with comprehensive information.

### Accessing Record ICIP

1. Navigate to any information object
2. Click on the **ICIP** tab, or use URL: `/{record-slug}/icip`

### ICIP Overview Page

```
+---------------------------------------------------------------------+
|  Record: Box 15 - Photographs 1920s                  [Back to Record]|
+---------------------------------------------------------------------+
|  ICIP Information                                                   |
+---------------------------------------------------------------------+

+-------------------+  +-------------------+  +-------------------+
|                   |  |                   |  |                   |
| Consent Status    |  | Cultural Notices  |  | TK Labels         |
|                   |  |                   |  |                   |
| FULL CONSENT      |  |        2          |  |       3           |
|                   |  |                   |  |                   |
+-------------------+  +-------------------+  +-------------------+

+-------------------+
|                   |
| Restrictions      |
|                   |
|        1          |
|                   |
+-------------------+

+---------------------------------------------------------------------+
|  TABS: [Overview] [Consent] [Notices] [TK Labels] [Restrictions]    |
|        [Consultations]                                              |
+---------------------------------------------------------------------+
```

---

## Reports

### Available Reports

1. **Pending Consultation Report**
   - Records awaiting or in progress of community consultation
   - Prioritized by waiting time

2. **Consent Expiry Report**
   - Consents expiring within specified timeframe
   - Default: 90 days

3. **Community Report**
   - All records, consultations, and consents for a specific community
   - Complete engagement history

### Pending Consultation Report

```
+---------------------------------------------------------------------+
|              PENDING CONSULTATION REPORT                             |
+---------------------------------------------------------------------+

+---------------------+------------------+-----------------------+------+
| Record              | Community        | Status                | Days |
+---------------------+------------------+-----------------------+------+
| Box 12 - Letters    | Yorta Yorta      | Pending Consultation  | 45   |
| Photo Album 1915    | Noongar          | Consultation in Prog  | 23   |
| Ceremonial Objects  | Wiradjuri        | Unknown               | 12   |
+---------------------+------------------+-----------------------+------+
```

### Consent Expiry Report

```
+---------------------------------------------------------------------+
|              CONSENT EXPIRY REPORT (Next 90 Days)                    |
+---------------------------------------------------------------------+

+---------------------+------------------+-------------+---------------+
| Record              | Community        | Expires     | Days Left     |
+---------------------+------------------+-------------+---------------+
| Box 15 - Photos     | Wiradjuri        | 15 Mar 2025 |      45       |
| Audio Collection    | Noongar          | 28 Mar 2025 |      58       |
| Artifact Series     | Yorta Yorta      | 10 Apr 2025 |      71       |
+---------------------+------------------+-------------+---------------+
```

---

## User Acknowledgement

When users access records with ICIP notices requiring acknowledgement:

```
+---------------------------------------------------------------------+
|                                                                     |
|    +-----------------------------------------------------+          |
|    |                                                     |          |
|    |              CULTURAL NOTICE                        |          |
|    |                                                     |          |
|    |  Aboriginal and Torres Strait Islander peoples      |          |
|    |  should be aware that this collection may contain   |          |
|    |  images, voices, or names of deceased persons.      |          |
|    |                                                     |          |
|    |  By clicking "I Acknowledge" you confirm that you   |          |
|    |  understand and accept these cultural protocols.    |          |
|    |                                                     |          |
|    |              [I Acknowledge]                        |          |
|    |                                                     |          |
|    +-----------------------------------------------------+          |
|                                                                     |
+---------------------------------------------------------------------+
```

- Acknowledgements are recorded with user ID, timestamp, and IP
- Users only need to acknowledge once per notice type per record

---

## Quick Actions

### From Dashboard

```
+-------------------+  +-------------------+  +-------------------+
|                   |  |                   |  |                   |
| + Add Community   |  | + Record Consent  |  | + Log Consultation|
|                   |  |                   |  |                   |
+-------------------+  +-------------------+  +-------------------+

+-------------------+
|                   |
| Cultural Notices  |
|                   |
+-------------------+
```

### From Record ICIP Tab

- Add Consent Record
- Log Consultation
- Add Cultural Notice
- Apply TK Label
- Add Access Restriction

---

## Best Practices

```
+---------------------------------------------------------------------+
|                     ICIP BEST PRACTICES                              |
+---------------------------------------------------------------------+
|                                                                     |
|  DO:                                                                |
|  ----                                                               |
|  + Consult with communities before digitizing or displaying         |
|  + Record all consultations with detailed notes                     |
|  + Set consent expiry dates and review regularly                    |
|  + Apply TK Labels as directed by communities                       |
|  + Respect gender and cultural access restrictions                  |
|  + Keep community contact information current                       |
|  + Document consent conditions thoroughly                           |
|                                                                     |
|  DON'T:                                                             |
|  ------                                                             |
|  - Assume consent covers all uses                                   |
|  - Ignore community requests for restrictions                       |
|  - Bypass ICIP restrictions with admin access                       |
|  - Share sensitive materials without explicit consent               |
|  - Forget to follow up on pending consultations                     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Configuration

Access ICIP settings via plugin configuration:

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Public Notices | Yes | Show notices on public view |
| Enable Staff Notices | Yes | Show notices on staff view |
| Require Acknowledgement | Yes | Default acknowledgement requirement |
| Consent Expiry Warning | 90 days | Days before expiry to warn |
| Local Contexts Hub | Disabled | Enable API integration |
| Audit All ICIP Access | Yes | Log access to ICIP records |

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Community not showing in dropdown | Check community is marked Active |
| Consent record not saving | Ensure record ID is valid |
| TK Label not displaying | Verify label type is active |
| Notice not blocking access | Check "Blocks Access" is enabled |
| Expiry report empty | Verify consent records have expiry dates |

---

## Glossary

| Term | Definition |
|------|------------|
| ICIP | Indigenous Cultural and Intellectual Property |
| TK Label | Traditional Knowledge Label (Local Contexts) |
| BC Label | Biocultural Label (Local Contexts) |
| PBC | Prescribed Body Corporate |
| UNDRIP | UN Declaration on Rights of Indigenous Peoples |
| AIATSIS | Australian Institute of Aboriginal and Torres Strait Islander Studies |
| Local Contexts | Organization providing TK/BC Label framework |

---

## Need Help?

- Check the [Technical Documentation](/docs/technical/ahgICIPPlugin.md)
- Contact your system administrator
- Visit [Local Contexts](https://localcontexts.org) for TK Label information

---

*Part of the AtoM AHG Framework - ahgICIPPlugin v1.0.0*

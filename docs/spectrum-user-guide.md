# Spectrum 5.0 Collections Management

## A Guide for Museum Staff

---

## What is Spectrum?

Spectrum helps you keep track of everything that happens to objects in your collection. Think of it as a complete history for each item.

---

## The Dashboard

Find it at: **Admin → Spectrum Dashboard**

```
┌─────────────────────────────────────────────────────────────┐
│                   SPECTRUM DASHBOARD                         │
├───────────────┬───────────────┬───────────────┬─────────────┤
│ 📦 Loans Out  │ 📥 Loans In   │ ⚠️ Overdue    │ 🔍 Checks   │
│     12        │      5        │      3        │    Due: 8   │
├───────────────┴───────────────┴───────────────┴─────────────┤
│                                                              │
│  ALERTS                                                      │
│  • 3 loans are overdue - action needed                       │
│  • 5 condition checks due this week                          │
│  • 2 insurance renewals coming up                            │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## When Objects Arrive

### The Process

```
         ┌──────────────────┐
         │  Object Arrives  │
         └────────┬─────────┘
                  │
                  ▼
         ┌──────────────────┐
         │  Create Entry    │
         │  Record          │
         └────────┬─────────┘
                  │
                  ▼
         ┌──────────────────┐
         │  Check Condition │
         │  & Take Photos   │
         └────────┬─────────┘
                  │
                  ▼
         ┌──────────────────┐
         │  Assign Storage  │
         │  Location        │
         └────────┬─────────┘
                  │
                  ▼
         ┌──────────────────┐
         │  Is it staying   │
         │  permanently?    │
         └────────┬─────────┘
                  │
        ┌─────────┴─────────┐
        │                   │
       YES                  NO
        │                   │
        ▼                   ▼
┌───────────────┐   ┌───────────────┐
│ Create        │   │ Note return   │
│ Acquisition   │   │ date          │
│ Record        │   │               │
└───────────────┘   └───────────────┘
```

### How to Record Entry

1. Find the object in AtoM (or create a new record)
2. Click **Extensions → Spectrum 5.0**
3. Click **New Entry**
4. Fill in the form:

| Question | Your Answer |
|----------|-------------|
| When did it arrive? | Enter the date |
| How did it arrive? | Deposit, loan, purchase, donation, or found |
| Who brought it? | Name and contact details |
| Staying permanently? | Yes = continue to Acquisition |
| If temporary, return date? | When it needs to go back |

---

## Lending Objects to Others

### The Loan Out Process

```
┌──────────────────┐
│ Someone wants    │
│ to borrow        │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐      NO       ┌──────────────┐
│ Can we lend it?  │──────────────▶│ Decline the  │
│ Check condition  │               │ request      │
└────────┬─────────┘               └──────────────┘
         │ YES
         ▼
┌──────────────────┐
│ Create loan      │
│ record           │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Generate loan    │
│ agreement        │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Print & get      │
│ signatures       │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Pack object      │
│ carefully        │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Record dispatch  │
│ details          │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Wait for return  │
│ Dashboard tracks │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Check condition  │
│ when returned    │
└──────────────────┘
```

### Creating a Loan

1. Go to the object
2. Click **Extensions → Spectrum → New Loan Out**
3. Enter the details:

| Field | Example |
|-------|---------|
| Who is borrowing? | National Art Gallery |
| Contact person | Jane Smith |
| Email | jane@gallery.org |
| Purpose | Exhibition: "Modern Art" |
| Start date | 1 March 2026 |
| End date | 30 June 2026 |
| Insurance value | R500,000 |

4. Click **Save**
5. Click **Generate Agreement** to create the paperwork

### When a Loan is Overdue

The dashboard shows overdue items in red. Click on any overdue loan to:
- Send a reminder to the borrower
- Extend the loan dates
- Record the return

---

## Checking Condition

### When to Check

```
┌─────────────────────────────────────────┐
│       ALWAYS CHECK CONDITION:           │
│                                         │
│   ✓  When object first arrives          │
│   ✓  Before lending to anyone           │
│   ✓  When a loan returns                │
│   ✓  After any accident or incident     │
│   ✓  Once a year (routine check)        │
│   ✓  Before photography or display      │
│                                         │
└─────────────────────────────────────────┘
```

### Condition Ratings

| Rating | What it Means |
|--------|---------------|
| ⭐⭐⭐⭐⭐ Excellent | Perfect, no problems at all |
| ⭐⭐⭐⭐ Good | Minor wear, normal for its age |
| ⭐⭐⭐ Fair | Some issues, keep an eye on it |
| ⭐⭐ Poor | Needs treatment, limit handling |
| ⭐ Unacceptable | Serious damage, do not touch |

### Recording a Check

1. Go to the object
2. Click **Extensions → Spectrum → New Condition Check**
3. Fill in:
   - Today's date
   - Your name
   - Overall rating
   - What you can see (describe any damage)
   - Does it need treatment?
   - When to check again?
4. Click **Add Photos** to document what you see

---

## Tracking Locations

### Why This Matters

When someone asks "where is the blue vase?" you need to answer quickly. Good location records save hours of searching.

### Location Format

```
┌─────────────────────────────────────────┐
│           OBJECT LOCATION               │
│                                         │
│   Building:   Main Museum               │
│   Floor:      Ground Floor              │
│   Room:       Storage Room A            │
│   Unit:       Cabinet 12                │
│   Position:   Shelf 3, Left Side        │
│                                         │
└─────────────────────────────────────────┘
```

### Recording Movement

Every time an object moves:

1. Go to the object
2. Click **Extensions → Spectrum → Record Movement**
3. Enter:
   - New location
   - Why it moved (exhibition, photography, storage)
   - Who moved it
   - Condition before and after

---

## Insurance & Valuations

### Recording Value

1. Go to the object
2. Click **Extensions → Spectrum → Add Valuation**
3. Enter:

| Field | Example |
|-------|---------|
| Type | Insurance |
| Value | R250,000 |
| Who valued it? | ABC Valuations |
| Date valued | 15 January 2026 |
| Renewal date | 15 January 2027 |

The dashboard alerts you when renewals are due.

---

## Quick Tips

**Start each day with the Dashboard**
- Check for overdue loans
- See what condition checks are due
- Note any expiring insurance

**Update locations immediately**
- Takes 30 seconds now
- Saves hours of searching later

**Always photograph condition**
- Pictures are worth a thousand words
- Essential for insurance claims

---

*For technical support, contact your system administrator.*
# Spectrum 5.0 Collections Management

## User Guide

Manage museum collections using UK Collections Trust Spectrum 5.0 procedures for object entry, loans, movements, and more.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                    SPECTRUM 5.0                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  21 Collection Management Procedures                        │
│                                                             │
│  📥 Object Entry       📤 Object Exit       📍 Location     │
│  📋 Loans In           📋 Loans Out         🔍 Condition    │
│  💰 Valuation          📷 Documentation     🗑️  Deaccession  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Core Procedures
```
┌─────────────────────────────────────────────────────────────┐
│  PRIMARY PROCEDURES (8)                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📥 Object Entry         - Receive objects into building    │
│  📦 Acquisition          - Add to permanent collection      │
│  📍 Location & Movement  - Track where objects are          │
│  📋 Cataloguing          - Create descriptive records       │
│  📤 Object Exit          - Remove objects from building     │
│  📋 Loans In             - Borrow from others               │
│  📋 Loans Out            - Lend to others                   │
│  📸 Documentation        - Photography and records          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Access
```
  Main Menu
      │
      ▼
   GLAM/DAM
      │
      ▼
   Spectrum ──────────────────────────────────────────────────┐
      │                                                        │
      ├──▶ Dashboard          (overview and quick actions)     │
      │                                                        │
      ├──▶ Object Entry       (receive objects)                │
      │                                                        │
      ├──▶ Loans              (loans in and out)               │
      │                                                        │
      ├──▶ Movements          (location tracking)              │
      │                                                        │
      └──▶ Condition          (condition checking)             │
```

---

## Object Entry

Record objects coming into the building:

### Step 1: Create Entry Record
```
┌─────────────────────────────────────────────────────────────┐
│  OBJECT ENTRY                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Entry Number:    [E2025.001                ] (auto)        │
│                                                             │
│  Entry Date:      [10 January 2026          ]               │
│                                                             │
│  Entry Reason:    [Potential acquisition    ▼]              │
│                   • Potential acquisition                   │
│                   • Loan in                                 │
│                   • Return from loan                        │
│                   • Conservation treatment                  │
│                   • Photography                             │
│                   • Identification/research                 │
│                                                             │
│  Depositor:       [John Smith               ]               │
│  Contact:         [john@email.com           ]               │
│  Phone:           [+27 21 123 4567          ]               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step 2: Add Objects
```
┌─────────────────────────────────────────────────────────────┐
│  OBJECTS IN THIS ENTRY                                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [+ Add Object]                                             │
│                                                             │
│  1. Victorian tea set (6 pieces)                            │
│     Brief description: Porcelain with floral pattern        │
│     Condition on entry: Good                                │
│                                                             │
│  2. Silver photograph frame                                 │
│     Brief description: Hallmarked Birmingham 1902           │
│     Condition on entry: Fair - some tarnishing              │
│                                                             │
│  Total objects: 2 (7 individual items)                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step 3: Set Return/Decision Date
```
┌─────────────────────────────────────────────────────────────┐
│  ENTRY CONDITIONS                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Return Date:     [10 February 2026         ]               │
│                   (if not acquired)                         │
│                                                             │
│  Decision Date:   [01 February 2026         ]               │
│                   (acquisition decision)                    │
│                                                             │
│  Special Conditions:                                        │
│  [Depositor requests objects be kept together if acquired  ]│
│                                                             │
│  Receipt Given:   [✓] Yes                                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Loans Out

Lend objects to other institutions:

### Loan Request
```
┌─────────────────────────────────────────────────────────────┐
│  NEW LOAN OUT                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  BORROWER DETAILS                                           │
│  ─────────────────────────────────────────────────────────  │
│  Institution:     [National Gallery of Art    ]             │
│  Contact:         [Jane Curator               ]             │
│  Email:           [j.curator@gallery.org      ]             │
│  Address:         [123 Gallery Road, City     ]             │
│                                                             │
│  LOAN DETAILS                                               │
│  ─────────────────────────────────────────────────────────  │
│  Purpose:         [Exhibition                ▼]             │
│                   • Exhibition                              │
│                   • Research                                │
│                   • Conservation                            │
│                   • Photography                             │
│                                                             │
│  Exhibition:      [Impressionism in Africa    ]             │
│  Venue:           [National Gallery of Art    ]             │
│                                                             │
│  Loan Period:                                               │
│  From:            [01 March 2026              ]             │
│  To:              [30 June 2026               ]             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Loan Objects
```
┌─────────────────────────────────────────────────────────────┐
│  OBJECTS FOR LOAN                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [+ Add Object from Collection]                             │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MUS-2020-00045                                      │   │
│  │ "Sunset over Table Mountain" - J.H. Pierneef       │   │
│  │ Condition: Good                                     │   │
│  │ Insurance Value: R 2,500,000                        │   │
│  │ [Condition Report Required] [Remove]                │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Total Objects: 1                                           │
│  Total Insurance Value: R 2,500,000                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Loan Conditions
```
┌─────────────────────────────────────────────────────────────┐
│  LOAN CONDITIONS                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  INSURANCE                                                  │
│  Type:            [Wall to wall             ▼]              │
│  Insured by:      [Borrower                 ▼]              │
│  Value:           [R 2,500,000               ]              │
│                                                             │
│  ENVIRONMENT                                                │
│  Temperature:     [20-22°C                   ]              │
│  Humidity:        [45-55% RH                 ]              │
│  Light Level:     [50 lux maximum            ]              │
│                                                             │
│  DISPLAY                                                    │
│  ☑ No photography without permission                       │
│  ☑ Must be displayed in glazed case                        │
│  ☑ Credit line required                                    │
│                                                             │
│  Credit Line:     [On loan from The Archive   ]             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Loan Workflow
```
┌─────────────────────────────────────────────────────────────┐
│  LOAN OUT WORKFLOW                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Request Received                                           │
│       │                                                     │
│       ▼                                                     │
│  ┌─────────────────┐                                       │
│  │ Review Request  │                                       │
│  └────────┬────────┘                                       │
│           │                                                 │
│     ┌─────┴─────┐                                          │
│     ▼           ▼                                          │
│  Declined    Approved                                      │
│     │           │                                          │
│     ▼           ▼                                          │
│  Notify      Condition                                     │
│  Borrower    Report                                        │
│                 │                                          │
│                 ▼                                          │
│              Loan Agreement                                │
│                 │                                          │
│                 ▼                                          │
│              Pack & Ship                                   │
│                 │                                          │
│                 ▼                                          │
│              On Loan ──────────────────┐                   │
│                 │                      │                   │
│                 ▼                      ▼                   │
│              Return              Extension?                │
│                 │                      │                   │
│                 ▼                      │                   │
│              Condition ◄───────────────┘                   │
│              Check                                         │
│                 │                                          │
│                 ▼                                          │
│              Complete                                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Location & Movement

Track where objects are:

### Current Location
```
┌─────────────────────────────────────────────────────────────┐
│  OBJECT LOCATION                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Object: MUS-2020-00045 "Sunset over Table Mountain"        │
│                                                             │
│  Current Location:                                          │
│  ─────────────────────────────────────────────────────────  │
│  Building:        Main Museum                               │
│  Floor:           Ground Floor                              │
│  Room:            Gallery 3 - South African Art             │
│  Unit:            Wall A                                    │
│  Position:        Center                                    │
│                                                             │
│  Status:          On Display                                │
│  Since:           01 December 2025                          │
│                                                             │
│  [Move Object]  [View History]                              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Record a Movement
```
┌─────────────────────────────────────────────────────────────┐
│  MOVE OBJECT                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  From:            Gallery 3 - Wall A (current)              │
│                                                             │
│  To:                                                        │
│  Building:        [Main Museum             ▼]               │
│  Floor:           [Basement                ▼]               │
│  Room:            [Conservation Lab        ▼]               │
│  Unit:            [Work Table 2            ▼]               │
│                                                             │
│  Move Date:       [10 January 2026          ]               │
│  Move Reason:     [Conservation treatment  ▼]               │
│  Moved By:        [M. Handler               ]               │
│                                                             │
│  Notes:                                                     │
│  [Moving for frame repair - estimated 2 weeks              ]│
│                                                             │
│                [Cancel]    [Confirm Move]                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Condition Checking

Assess and record object condition:
```
┌─────────────────────────────────────────────────────────────┐
│  CONDITION CHECK                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Object:          MUS-2020-00045                            │
│  Check Date:      10 January 2026                           │
│  Checked By:      A. Conservator                            │
│                                                             │
│  CONDITION RATING                                           │
│  ─────────────────────────────────────────────────────────  │
│  Overall:         [Good                    ▼]               │
│                   • Excellent                               │
│                   • Good  ←                                 │
│                   • Fair                                    │
│                   • Poor                                    │
│                   • Unacceptable                            │
│                                                             │
│  CONDITION DETAILS                                          │
│  ─────────────────────────────────────────────────────────  │
│  ☐ Surface dirt                                            │
│  ☑ Minor scratches                                         │
│  ☐ Cracks                                                  │
│  ☐ Losses                                                  │
│  ☐ Discoloration                                           │
│  ☐ Structural damage                                       │
│  ☑ Frame damage                                            │
│                                                             │
│  Notes:                                                     │
│  [Minor scratches to frame gilt, lower right corner.       ]│
│  [Paint surface stable. No change since last check.        ]│
│                                                             │
│  [Attach Photos]                                            │
│                                                             │
│  RECOMMENDATIONS                                            │
│  ─────────────────────────────────────────────────────────  │
│  ☑ Routine monitoring only                                 │
│  ☐ Conservation treatment needed                           │
│  ☐ Not suitable for loan                                   │
│  ☐ Urgent attention required                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Valuation

Record object values:
```
┌─────────────────────────────────────────────────────────────┐
│  VALUATION                                                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Object:          MUS-2020-00045                            │
│                                                             │
│  NEW VALUATION                                              │
│  ─────────────────────────────────────────────────────────  │
│  Value:           [R 2,500,000.00           ]               │
│  Currency:        [ZAR                     ▼]               │
│                                                             │
│  Valuation Type:  [Insurance Replacement   ▼]               │
│                   • Insurance Replacement                   │
│                   • Market Value                            │
│                   • Internal Value                          │
│                   • Probate                                 │
│                                                             │
│  Valuation Date:  [01 January 2026          ]               │
│  Valued By:       [Stephan Welz & Co.       ]               │
│  Valid Until:     [31 December 2026         ]               │
│                                                             │
│  Notes:                                                     │
│  [Based on recent auction results for comparable works     ]│
│                                                             │
│  [Attach Valuation Certificate]                             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Spectrum Dashboard

Quick overview of collection activity:
```
┌─────────────────────────────────────────────────────────────┐
│  SPECTRUM DASHBOARD                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │ Pending     │  │ Objects     │  │ Condition   │         │
│  │ Entries     │  │ On Loan     │  │ Checks Due  │         │
│  │             │  │             │  │             │         │
│  │     5       │  │     12      │  │     8       │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │ Loan        │  │ Overdue     │  │ Valuations  │         │
│  │ Requests    │  │ Returns     │  │ Expiring    │         │
│  │             │  │             │  │             │         │
│  │     3       │  │     1       │  │     15      │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
│                                                             │
│  RECENT ACTIVITY                                            │
│  ─────────────────────────────────────────────────────────  │
│  • E2025.001 - Objects received from J. Smith              │
│  • L2024.015 - Loan returned from National Gallery         │
│  • MUS-2020-00045 - Condition check completed              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Tips for Spectrum
```
┌────────────────────────────────────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                  │
├────────────────────────────────┼────────────────────────────┤
│  Always give entry receipts    │  Accept objects informally │
│  Complete condition reports    │  Skip pre-loan checks      │
│  Update locations immediately  │  Move without recording    │
│  Set reminder dates            │  Miss loan return dates    │
│  Document all decisions        │  Leave gaps in records     │
│  Keep valuations current       │  Let valuations expire     │
└────────────────────────────────┴────────────────────────────┘
```

---

## Need Help?

Contact your system administrator or registrar if you need assistance.

---

*Part of the AtoM AHG Framework*

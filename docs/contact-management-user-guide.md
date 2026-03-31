# Contact Management

## User Guide

Manage contact information for authority records (persons, organizations) and repositories in your archive.

---

## Overview
```
+-------------------------------------------------------------+
|                   CONTACT MANAGEMENT                        |
+-------------------------------------------------------------+
|                                                             |
|   AUTHORITY RECORDS              REPOSITORIES               |
|        |                              |                     |
|        v                              v                     |
|   +----------+                  +----------+                |
|   |  Person  |                  |  Archive |                |
|   +----------+                  +----------+                |
|        |                              |                     |
|        +---------- CONTACTS ----------+                     |
|                       |                                     |
|           +-----------+-----------+                         |
|           |           |           |                         |
|           v           v           v                         |
|        Primary    Secondary    Additional                   |
|        Contact    Contact      Contacts                     |
|                                                             |
+-------------------------------------------------------------+
```

---

## What is Contact Management?

Contact Management allows you to:
- Store multiple contact entries for authority records
- Designate primary contacts for quick reference
- Record extended details like cell phones, departments, and roles
- Support multiple languages for city, region, and notes
- Track preferred contact methods

---

## Contact Types
```
+-------------------------------------------------------------+
|                    CONTACT FIELDS                           |
+-------------------------------------------------------------+
|  STANDARD FIELDS (AtoM Core)                                |
|  +-------------------------------------------------------+  |
|  |  Contact Person    - Name of the contact              |  |
|  |  Street Address    - Physical address                 |  |
|  |  City              - City name (translatable)         |  |
|  |  Region            - Province/State (translatable)    |  |
|  |  Postal Code       - ZIP/Postal code                  |  |
|  |  Country Code      - Country (e.g., ZA, US, GB)       |  |
|  |  Telephone         - Main phone number                |  |
|  |  Fax               - Fax number                       |  |
|  |  Email             - Primary email                    |  |
|  |  Website           - URL                              |  |
|  |  Note              - Additional notes (translatable)  |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  EXTENDED FIELDS (AHG Extension)                            |
|  +-------------------------------------------------------+  |
|  |  Title             - Mr, Mrs, Dr, Prof, etc.          |  |
|  |  Role              - Job title/Position               |  |
|  |  Department        - Department/Division              |  |
|  |  Cell              - Mobile phone number              |  |
|  |  ID Number         - ID/Passport number               |  |
|  |  Alternative Email - Secondary email                  |  |
|  |  Alternative Phone - Secondary phone                  |  |
|  |  Preferred Method  - Email/Phone/Cell/Fax/Mail        |  |
|  |  Language Pref.    - Preferred language               |  |
|  +-------------------------------------------------------+  |
+-------------------------------------------------------------+
```

---

## How to Access

### From Authority Records
```
  Authority Record (Person/Organization)
      |
      v
   Edit Mode
      |
      v
   Contact Information Section ----------------+
      |                                        |
      +---> View Contacts    (read-only list)  |
      |                                        |
      +---> Add Contact      (new entry)       |
      |                                        |
      +---> Edit Contact     (modify existing) |
      |                                        |
      +---> Delete Contact   (remove entry)    |
```

### From Repositories
```
  Repository Record
      |
      v
   Edit Mode
      |
      v
   Contact Information Section ----------------+
      |                                        |
      +---> Same operations as authority       |
```

---

## Adding a New Contact

### Step 1: Open the Record

Navigate to the authority record or repository you want to add contact information to.

### Step 2: Enter Edit Mode

Click the **Edit** button to open the record for editing.

### Step 3: Locate Contact Section

Scroll to the **Contact Information** section.

### Step 4: Click Add Contact

Click the **Add contact** button:
```
+-------------------------------------------------------------+
|  Contact Information                         [+ Add contact] |
+-------------------------------------------------------------+
|  (Your contacts will appear here)                           |
+-------------------------------------------------------------+
```

### Step 5: Fill in the Form
```
+-------------------------------------------------------------+
|  Contact #1                                           [X]   |
+-------------------------------------------------------------+
|                                                             |
|  Contact person:    [_____________________________]         |
|                                                             |
|  [ ] Primary contact                                        |
|                                                             |
|  Street address:                                            |
|  +-------------------------------------------------------+  |
|  |                                                       |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  City:           [___________]  Region:    [___________]    |
|                                                             |
|  Postal code:    [___________]  Country:   [___________]    |
|                                                             |
|  Telephone:      [___________]  Fax:       [___________]    |
|                                                             |
|  Email:          [_____________________________]            |
|                                                             |
|  Website:        [_____________________________]            |
|                                                             |
|  Note:                                                      |
|  +-------------------------------------------------------+  |
|  |                                                       |  |
|  +-------------------------------------------------------+  |
|                                                             |
+-------------------------------------------------------------+
```

### Step 6: Save the Record

Click **Save** to store the contact information.

---

## Setting a Primary Contact

### What is a Primary Contact?

The primary contact is the main point of contact for the record. It is highlighted in the display view.

### How to Set Primary
```
+-------------------------------------------------------------+
|  Contact #1                                           [X]   |
+-------------------------------------------------------------+
|                                                             |
|  Contact person:    [John Smith______________]              |
|                                                             |
|  [X] Primary contact    <-- Check this box                  |
|                                                             |
+-------------------------------------------------------------+
```

Only one contact can be primary. When you set a new primary contact, the previous one is automatically cleared.

---

## Viewing Contact Information

Contact information displays on the record's view page:
```
+-------------------------------------------------------------+
|  Contact Information                                        |
+-------------------------------------------------------------+
|                                                             |
|  [Primary] Badge                                            |
|                                                             |
|  Contact person: John Smith                                 |
|                                                             |
|  Address:                                                   |
|  123 Main Street                                            |
|  Pretoria, Gauteng, 0001                                    |
|  ZA                                                         |
|                                                             |
|  Telephone: +27 12 345 6789                                 |
|  Email: john.smith@example.co.za                            |
|  Website: https://www.example.co.za                         |
|                                                             |
|  Note: Available weekdays 8am-5pm                           |
|                                                             |
|  -----------------------------------------------------------+
|                                                             |
|  Contact person: Jane Doe                                   |
|  ...                                                        |
+-------------------------------------------------------------+
```

---

## Editing a Contact

### Step 1: Open Record in Edit Mode

Navigate to the record and click **Edit**.

### Step 2: Locate the Contact

Find the contact entry you want to modify in the Contact Information section.

### Step 3: Make Changes

Update the fields as needed.

### Step 4: Save

Click **Save** to apply changes.

---

## Deleting a Contact

### Step 1: Open Record in Edit Mode

Navigate to the record and click **Edit**.

### Step 2: Find the Contact Entry

Locate the contact you want to remove.

### Step 3: Click Remove Button
```
+-------------------------------------------------------------+
|  Contact #2                                           [X]   |
+-------------------------------------------------------------+
|                                      Click X to remove ^    |
```

### Step 4: Save

The contact will be removed when you save the record.

---

## Multiple Contacts

You can add multiple contacts to a single record:
```
+-------------------------------------------------------------+
|  Contact Information                         [+ Add contact] |
+-------------------------------------------------------------+
|                                                             |
|  +--- Contact #1 (Primary) -------------------------[X]--+  |
|  |  John Smith - Director                                |  |
|  |  john@archive.org                                     |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  +--- Contact #2 ---------------------------------[X]----+  |
|  |  Jane Doe - Archivist                                 |  |
|  |  jane@archive.org                                     |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  +--- Contact #3 ---------------------------------[X]----+  |
|  |  Public Enquiries                                     |  |
|  |  enquiries@archive.org                                |  |
|  +-------------------------------------------------------+  |
|                                                             |
+-------------------------------------------------------------+
```

---

## Common Uses
```
+-------------------------------------------------------------+
|                  USE CONTACT MANAGEMENT TO:                 |
+-------------------------------------------------------------+
|  + Store repository contact details                         |
|  + Record donor/depositor information                       |
|  + Track organizational contacts                            |
|  + Manage researcher contact details                        |
|  + Document creator/author information                      |
|  + Store vendor/supplier contacts                           |
|  + Keep staff contact records                               |
+-------------------------------------------------------------+
```

---

## Tips
```
+--------------------------------+-----------------------------+
|  DO                            |  DON'T                      |
+--------------------------------+-----------------------------+
|  Set a primary contact         |  Leave all contacts equal   |
|  Include multiple contacts     |  Rely on single contact     |
|  Add notes for context         |  Skip important details     |
|  Use standard country codes    |  Use full country names     |
|  Keep information updated      |  Let data become stale      |
|  Include preferred method      |  Assume contact preference  |
+--------------------------------+-----------------------------+
```

---

## Country Codes

Use standard ISO 3166-1 alpha-2 country codes:
```
+--------+----------------------+
|  Code  |  Country             |
+--------+----------------------+
|  ZA    |  South Africa        |
|  US    |  United States       |
|  GB    |  United Kingdom      |
|  AU    |  Australia           |
|  CA    |  Canada              |
|  DE    |  Germany             |
|  FR    |  France              |
|  NL    |  Netherlands         |
|  NA    |  Namibia             |
|  BW    |  Botswana            |
+--------+----------------------+
```

---

## Integration with Other Features

### Donor Agreements
Contact information links to donor records for agreement management.

### Access Requests
Researcher contacts are used for PAIA/access request processing.

### Loan Management
Contact details support loan agreement workflows.

### Privacy Compliance
Contact information is subject to POPIA/GDPR compliance rules.

---

## Troubleshooting
```
+-------------------------------------------------------------+
|  ISSUE                      |  SOLUTION                     |
+-----------------------------+-------------------------------+
|  Contact not saving         |  Check required fields        |
|  Primary not showing        |  Ensure checkbox is checked   |
|  Contact missing in view    |  Clear cache, refresh page    |
|  Multiple primaries shown   |  Edit and re-save record      |
|  Address not formatting     |  Check field entries          |
+-----------------------------+-------------------------------+
```

---

## Need Help?

Contact your system administrator if you experience issues with contact management.

---

*Part of the AtoM AHG Framework*

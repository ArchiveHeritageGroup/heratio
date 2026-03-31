# Embargo System - User Guide

## What is an Embargo?

An embargo restricts access to archival records for a specified period or indefinitely. This is used to protect sensitive materials, comply with donor agreements, or meet legal requirements.

---

## Embargo Types

| Type | What Public Users See | Use Case |
|------|----------------------|----------|
| 🔒 **Full** | Nothing - Record completely hidden | Highly sensitive materials, legal holds |
| 📄 **Metadata Only** | Title and description visible, no images | Privacy concerns, pending clearance |
| 🖼️ **Digital Object** | Metadata + thumbnail visible, no downloads | Copyright restrictions |
| ⚙️ **Partial** | Customizable per field | Special arrangements |

---

## Visual Guide: What Gets Blocked
```
┌─────────────────────────────────────────────────────────────┐
│                    FULL EMBARGO                              │
│  ┌─────────────┐                                            │
│  │   🔒 403    │  "Access Restricted"                       │
│  │   BLOCKED   │  User cannot see anything                  │
│  └─────────────┘                                            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                 METADATA ONLY                                │
│  ┌─────────────┐  ┌──────────────────────────┐              │
│  │   🔒 No     │  │ ✓ Title                  │              │
│  │   Images    │  │ ✓ Description            │              │
│  │             │  │ ✓ Dates, Creator         │              │
│  └─────────────┘  │ ✗ No thumbnails          │              │
│                   │ ✗ No digital objects     │              │
│                   └──────────────────────────┘              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│               DIGITAL OBJECT ONLY                            │
│  ┌─────────────┐  ┌──────────────────────────┐              │
│  │  🖼️ Preview │  │ ✓ Title                  │              │
│  │   Visible   │  │ ✓ Description            │              │
│  │             │  │ ✓ Thumbnail preview      │              │
│  └─────────────┘  │ ✗ No full-size view      │              │
│                   │ ✗ No downloads           │              │
│                   └──────────────────────────┘              │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Add an Embargo

### Step 1: Navigate to Record
Go to the archival record you want to embargo.

### Step 2: Click "Add Embargo"
In the sidebar under "Rights", click **"Add embargo"**.

### Step 3: Choose Settings
- Select embargo type (Full, Metadata Only, etc.)
- Set start date
- Set end date (or mark as perpetual)
- Add reason (Donor, Copyright, Privacy, etc.)
- Optional: Add public message

### Step 4: Apply to Children (Optional)
☑️ Check **"Apply to all descendants"** to embargo all child records at once.

### Step 5: Save
Click **"Create Embargo"** to apply.

---

## Applying Embargo to Multiple Records

When you have a **fonds** or **series** with many items, you can apply the embargo to all descendants at once:
```
       📁 Fonds (Parent)
           │
    ☑️ Apply embargo here
           │
     ┌─────┴─────┐
     ↓           ↓
   📁 Series   📁 Series
     │           │
   ┌─┴─┐       ┌─┴─┐
   ↓   ↓       ↓   ↓
  📄  📄      📄  📄   ← All automatically embargoed!
```

> ⚠️ **Warning:** Each child record gets its own embargo. To remove, you must lift each one individually or use bulk operations.

---

## Managing Embargoes

### View Active Embargoes
Navigate to: **Rights → Embargo Management**

Shows:
- All active embargoes
- Expiring soon (next 30 days)
- Embargo statistics

### Edit an Embargo
1. Go to the embargoed record
2. Click "Manage embargo" in sidebar
3. Modify settings
4. Optionally apply changes to descendants
5. Save

### Lift an Embargo
1. Go to the embargoed record
2. Click "Manage embargo" 
3. Click "Lift Embargo"
4. Provide a reason (optional)
5. Confirm

---

## Embargo Timeline
```
     START DATE                              END DATE
         │                                       │
         ▼                                       ▼
    ─────●═══════════════════════════════════════●─────
         │         EMBARGO ACTIVE                │
         │                                       │
         │  📧 Notification sent                 │
         │     30 days before expiry             │
         │              │                        │
         │              ▼                        │
         │         ┌─────────┐                   │
         │         │ Review  │                   │
         │         │ Extend? │                   │
         │         └─────────┘                   │
         │                                       │
    Record                                   Record
    Hidden                                   Visible
```

---

## Who Can See Embargoed Records?

| User Type | Full | Metadata Only | Digital Object |
|-----------|------|---------------|----------------|
| **Public (anonymous)** | ❌ | ✓ Metadata | ✓ Metadata + Thumb |
| **Registered researcher** | ❌ | ✓ Metadata | ✓ Metadata + Thumb |
| **Editor (with permission)** | ✓ All | ✓ All | ✓ All |
| **Administrator** | ✓ All | ✓ All | ✓ All |

> **Note:** Editors and administrators can always see embargoed content to manage it.

---

## Common Reasons for Embargo

| Reason | Typical Duration | Type |
|--------|-----------------|------|
| **Donor Restriction** | 25-50 years | Full or Metadata Only |
| **Privacy (living persons)** | Until death + 20 years | Metadata Only |
| **Copyright** | Until cleared | Digital Object |
| **Legal Hold** | Until resolved | Full |
| **Cultural Sensitivity** | Indefinite | Full or Metadata Only |
| **Research Embargo** | 1-5 years | Metadata Only |

---

## Quick Reference

| Action | Where to Find |
|--------|--------------|
| Add embargo | Record page → Sidebar → "Add embargo" |
| Edit embargo | Record page → Sidebar → "Manage embargo" |
| Lift embargo | Embargo view → "Lift Embargo" button |
| View all embargoes | Rights → Embargo Management |
| Bulk apply | Add/Edit form → "Apply to all descendants" |

---

## Screenshots

### Embargo Blocked Page (Full Embargo)
When a public user tries to access a fully embargoed record:

![Embargo Blocked](../images/embargo-blocked.png)

### Add Embargo Form
![Add Embargo](../images/embargo-add.png)

### Embargo Status in Sidebar
![Embargo Sidebar](../images/embargo-sidebar.png)

---

## Need Help?

Contact your system administrator if you:
- Need to embargo a large collection
- Require special access arrangements
- Have questions about embargo policies
- Need to lift multiple embargoes at once

---

*Last updated: January 2026*
*Part of the AHG Extensions for AtoM 2.10*

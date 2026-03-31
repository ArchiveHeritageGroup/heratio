# Security & Compliance

## A Guide for Staff and Administrators

---

## What This Covers

This guide explains how the system protects sensitive materials and helps you meet legal requirements for:
- **Privacy** - POPIA (South Africa), GDPR (Europe)
- **Records Management** - NARSSA requirements
- **Security Classifications** - Controlling who sees what
- **Audit Trails** - Tracking who did what and when

---

## Security Classifications

### The Five Levels

```
┌─────────────────────────────────────────────────────────────┐
│                  SECURITY LEVELS                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  🟢 PUBLIC                                                   │
│     Anyone can view, including the general public            │
│                                                              │
│  🔵 INTERNAL                                                 │
│     Staff members only                                       │
│                                                              │
│  🟡 CONFIDENTIAL                                             │
│     Authorised staff with a need to know                     │
│                                                              │
│  🟠 SECRET                                                   │
│     Named individuals only, approved by management           │
│                                                              │
│  🔴 TOP SECRET                                               │
│     Special clearance required, very restricted              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### How Access Works

```
        RECORD                           USER
     ┌──────────┐                   ┌──────────┐
     │ Security │                   │ Clearance│
     │  Level:  │                   │  Level:  │
     │CONFIDENT-│                   │  SECRET  │
     │   IAL    │                   │          │
     └────┬─────┘                   └────┬─────┘
          │                              │
          │         COMPARISON           │
          └──────────────┬───────────────┘
                         │
                         ▼
              ┌─────────────────────┐
              │ User clearance      │
              │ (SECRET) is HIGHER  │
              │ than record level   │
              │ (CONFIDENTIAL)      │
              └──────────┬──────────┘
                         │
                         ▼
              ┌─────────────────────┐
              │   ✅ ACCESS         │
              │      GRANTED        │
              └─────────────────────┘
```

**Simple Rule:** Your clearance level must be equal to or higher than the record's security level.

| Your Clearance | Can Access |
|----------------|------------|
| Public | Public only |
| Internal | Public + Internal |
| Confidential | Public + Internal + Confidential |
| Secret | All except Top Secret |
| Top Secret | Everything |

---

## Setting Security on Records

### When Creating a Record

1. Look for the **Security Classification** field
2. Select the appropriate level
3. The system remembers this for all future access checks

### Changing Security Level

1. Go to the record
2. Click **Edit**
3. Change the **Security Classification**
4. Click **Save**

**Note:** You can only set security levels up to your own clearance. If you have Confidential clearance, you cannot mark something as Secret.

---

## User Clearance

### Checking Your Clearance

1. Click on your username (top right)
2. Select **My Profile**
3. Your clearance level is shown

### Requesting Higher Clearance

Contact your administrator if you need access to records above your current level. They will:
1. Verify your need
2. Get appropriate approval
3. Update your clearance in the system

---

## Privacy Compliance

### POPIA (South Africa)

The Protection of Personal Information Act requires us to:
- Only collect information we need
- Keep it secure
- Allow people to see their information
- Delete it when no longer needed

### What You Need to Do

```
┌─────────────────────────────────────────────────────────────┐
│              PRIVACY CHECKLIST                               │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ✓ Only access records you need for your work               │
│                                                              │
│  ✓ Don't share login details with anyone                    │
│                                                              │
│  ✓ Log out when leaving your computer                       │
│                                                              │
│  ✓ Report any suspected breaches immediately                │
│                                                              │
│  ✓ Don't copy personal information unnecessarily            │
│                                                              │
│  ✓ Ask if unsure about sharing information                  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Handling Information Requests

If someone asks to see their personal information:

```
┌──────────────────┐
│ Person requests  │
│ their data       │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Verify their     │
│ identity         │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Log the request  │
│ in the system    │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Forward to       │
│ Information      │
│ Officer          │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Response within  │
│ 30 days          │
└──────────────────┘
```

---

## Audit Trail

### What Gets Recorded

The system automatically tracks:

| Action | What's Recorded |
|--------|-----------------|
| Login/Logout | Who, when, from where |
| View Record | Who looked at what |
| Create | Who created what record |
| Edit | Who changed what (old and new values) |
| Delete | Who deleted what |
| Download | Who downloaded files |
| Print | Who printed what |

### Why This Matters

- **Accountability** - Everyone is responsible for their actions
- **Investigation** - If something goes wrong, we can find out what happened
- **Compliance** - Legal requirement for many organisations
- **Protection** - Protects you by showing you followed procedures

### Viewing Audit History

For any record:
1. Go to the record
2. Click **History** or **Audit Trail**
3. See all changes with who, what, and when

---

## Embargoes

### What is an Embargo?

An embargo prevents access to a record until a specific date. Common reasons:
- Personal information (e.g., 75 years after creation)
- Donor requirements
- Legal restrictions
- Commercial sensitivity

### How Embargoes Look

```
┌─────────────────────────────────────────────────────────────┐
│  🔒 THIS RECORD IS EMBARGOED                                │
│                                                              │
│  Available from: 1 January 2050                             │
│  Reason: Contains personal information                       │
│                                                              │
│  Contact the archivist if you need special access           │
└─────────────────────────────────────────────────────────────┘
```

### Setting an Embargo

1. Go to the record
2. Click **Edit**
3. Find **Embargo Settings**
4. Enter:
   - End date (when it becomes available)
   - Reason
5. Click **Save**

### Requesting Embargo Exemption

If you need access to embargoed material:
1. Submit a written request
2. Explain your research purpose
3. Wait for approval from management
4. If approved, you'll get temporary access

---

## Best Practices

### Daily Habits

```
┌─────────────────────────────────────────────────────────────┐
│                  SECURITY HABITS                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  WHEN STARTING WORK                                          │
│  • Use your own login                                        │
│  • Check for any security announcements                      │
│                                                              │
│  DURING THE DAY                                              │
│  • Lock screen when away (Windows+L)                         │
│  • Don't leave sensitive records on screen                   │
│  • Be careful what you discuss in public areas               │
│                                                              │
│  WHEN FINISHING                                              │
│  • Log out properly                                          │
│  • Clear your desk of sensitive papers                       │
│  • Secure any physical records                               │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### If Something Goes Wrong

**Suspected security breach:**
1. Don't panic
2. Stop what you're doing
3. Tell your supervisor immediately
4. Don't try to fix it yourself
5. Document what happened

**Lost password:**
1. Contact IT support
2. Never share passwords
3. Use the password reset function

**Suspicious activity:**
1. Note what you saw
2. Report to your supervisor
3. Don't confront anyone directly

---

## Quick Reference

### Security Level Colours

| Colour | Level | Access |
|--------|-------|--------|
| 🟢 Green | Public | Everyone |
| 🔵 Blue | Internal | Staff |
| 🟡 Yellow | Confidential | Authorised staff |
| 🟠 Orange | Secret | Named individuals |
| 🔴 Red | Top Secret | Special clearance |

### Key Contacts

| Issue | Contact |
|-------|---------|
| Access problems | System Administrator |
| Security concerns | Information Officer |
| Privacy requests | Information Officer |
| Policy questions | Your Manager |

---

*For technical support, contact your system administrator.*

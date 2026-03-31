# Cart Module - User Guide

**Plugin:** ahgCartPlugin  
**Version:** 1.0.0

---

## Overview

The Cart module allows you to collect multiple archival records and submit them together as a batch Request to Publish.

---

## Quick Start
```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Browse Records │ ──▶ │  Add to Cart    │ ──▶ │  Submit All     │
│  with Images    │     │  🛒              │     │  as Request     │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

---

## When to Use Cart

Use the Cart when you need to:
- ✅ Request multiple images at once
- ✅ Submit a batch request for a research project
- ✅ Collect items for an exhibition proposal
- ✅ Gather images for a publication

---

## Adding Items to Cart

### From Any Record

1. Navigate to a record **with a digital object** (image)
2. Click the **cart icon** (🛒) - "Add to Cart"
3. Item is added to your cart
4. Icon changes to show item is in cart

### Button States

| Icon | Meaning |
|------|---------|
| 🛒+ (cart plus) | Click to add to cart |
| 🛒 (cart) | Already in cart - click to view cart |

---

## Viewing Your Cart

1. Click the cart icon on any record, OR
2. Navigate directly to `/cart`

### Cart Page Features

- List of all items in your cart
- Record title with link to view
- Indicates if record has digital object
- Date added
- Remove individual items
- Clear entire cart

---

## Submitting a Request

### From Cart Page

1. Go to `/cart`
2. Review your items
3. Click **Submit Request**
4. Complete the Request to Publish form:

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Your first name |
| Surname | Yes | Your last name |
| Phone | Yes | Contact number |
| Email | Yes | Contact email |
| Institution | Yes | Your organization |
| Planned Use | Yes | How you will use the images |
| Need Image By | No | Project deadline |
| Motivation | No | Why you need these images |

5. Click **Submit Request for X Item(s)**
6. A separate Request to Publish is created for **each item**
7. Cart is cleared after successful submission

---

## Workflow Diagram
```
┌─────────────────────────────────────────────────────────────────┐
│                      CART WORKFLOW                              │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
    │  Record A   │     │  Record B   │     │  Record C   │
    │  + Image    │     │  + Image    │     │  + Image    │
    └──────┬──────┘     └──────┬──────┘     └──────┬──────┘
           │                   │                   │
           │  Click 🛒         │  Click 🛒         │  Click 🛒
           │                   │                   │
           └─────────────┬─────┴─────┬─────────────┘
                         │           │
                         ▼           ▼
              ┌─────────────────────────────────┐
              │           CART                  │
              │   /cart                         │
              │                                 │
              │   • Record A                    │
              │   • Record B                    │
              │   • Record C                    │
              │                                 │
              │   [Submit Request]              │
              └─────────────┬───────────────────┘
                            │
                            ▼
              ┌─────────────────────────────────┐
              │     CHECKOUT FORM               │
              │                                 │
              │   Name: ____________            │
              │   Email: ___________            │
              │   Institution: _____            │
              │   Planned Use: _____            │
              │   ...                           │
              │                                 │
              │   [Submit for 3 Items]          │
              └─────────────┬───────────────────┘
                            │
          ┌─────────────────┼─────────────────┐
          │                 │                 │
          ▼                 ▼                 ▼
    ┌───────────┐     ┌───────────┐     ┌───────────┐
    │ Request A │     │ Request B │     │ Request C │
    │ (Pending) │     │ (Pending) │     │ (Pending) │
    └───────────┘     └───────────┘     └───────────┘
                            │
                            ▼
              ┌─────────────────────────────────┐
              │     CART CLEARED                │
              │     Redirect to Request List    │
              └─────────────────────────────────┘
```

---

## Requirements

- **Must be logged in** to use cart
- **Digital object must exist** on the record
- Cart button only appears on records with images

---

## Managing Your Cart

### Remove Single Item

1. Go to `/cart`
2. Click **Remove** (🗑️) next to the item

### Clear All Items

1. Go to `/cart`
2. Click **Clear Cart**
3. Confirm when prompted

---

## Tips

- Build your cart over multiple sessions - items persist
- Review all items before submitting
- One form submission creates requests for ALL items
- You can't edit a request after submission

---

## Cart vs Single Request

| Feature | Single Request | Cart |
|---------|---------------|------|
| Items | 1 record | Multiple records |
| Form | Fill once | Fill once for all |
| Result | 1 request | 1 request per item |
| Best for | Quick single need | Research projects |

---

*Part of the AtoM AHG Framework*

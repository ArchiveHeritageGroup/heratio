# Shopping Cart & E-Commerce - User Guide

## Overview

The Shopping Cart system allows visitors to request reproductions of archival materials or purchase digital copies. The system supports both **free requests** (Request to Publish workflow) and **paid purchases** (E-Commerce mode with PayFast integration).

---

## How It Works
```
┌─────────────────────────────────────────────────────────────────────────┐
│                    SHOPPING CART WORKFLOW                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌──────────────┐    ┌──────────────┐    ┌──────────────┐              │
│   │   BROWSE     │───▶│   ADD TO     │───▶│   VIEW       │              │
│   │   ARCHIVE    │    │   CART       │    │   CART       │              │
│   └──────────────┘    └──────────────┘    └──────┬───────┘              │
│                                                   │                      │
│                        ┌──────────────────────────┴───────┐              │
│                        │                                  │              │
│                        ▼                                  ▼              │
│             ┌──────────────────┐              ┌──────────────────┐       │
│             │  REQUEST MODE    │              │  E-COMMERCE MODE │       │
│             │  (Free)          │              │  (Paid)          │       │
│             └────────┬─────────┘              └────────┬─────────┘       │
│                      │                                 │                 │
│                      ▼                                 ▼                 │
│             ┌──────────────────┐              ┌──────────────────┐       │
│             │  Fill Request    │              │  Select Products │       │
│             │  Form            │              │  & Quantities    │       │
│             └────────┬─────────┘              └────────┬─────────┘       │
│                      │                                 │                 │
│                      ▼                                 ▼                 │
│             ┌──────────────────┐              ┌──────────────────┐       │
│             │  Submit for      │              │  Proceed to      │       │
│             │  Review          │              │  Checkout        │       │
│             └────────┬─────────┘              └────────┬─────────┘       │
│                      │                                 │                 │
│                      ▼                                 ▼                 │
│             ┌──────────────────┐              ┌──────────────────┐       │
│             │  Staff Reviews   │              │  Pay via         │       │
│             │  & Responds      │              │  PayFast         │       │
│             └──────────────────┘              └────────┬─────────┘       │
│                                                        │                 │
│                                                        ▼                 │
│                                               ┌──────────────────┐       │
│                                               │  Order Complete  │       │
│                                               │  Download Ready  │       │
│                                               └──────────────────┘       │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## For Public Users

### Adding Items to Cart

1. **Browse the archive** and find an item with a digital object (image, document, etc.)
2. Look for the **shopping cart icon** (🛒) in the action buttons
3. Click **"Add to Cart"** - the button changes to **"Go to Cart"**

> **Note:** You can add items as a guest or logged-in user. Guest carts are saved in your browser session.

### Viewing Your Cart

1. Click the **Cart** link in the navigation menu, or
2. Click **"Go to Cart"** button on any item you've added
3. Your cart shows all items with thumbnails and titles

### Checkout Process

#### Request Mode (Free)
If the archive uses Request to Publish mode:

1. Click **"Proceed to Checkout"** or **"Submit Request to Publish"**
2. Fill in the request form:
   - Your name and contact details
   - Institution/organization
   - Planned use of the images
   - Motivation for request
   - When you need the images by
3. Submit your request
4. Staff will review and contact you

#### E-Commerce Mode (Paid)
If the archive has e-commerce enabled:

1. **Select product types** for each item:
   - Digital Download (JPEG, TIFF, etc.)
   - Prints (A4, A3, A2, etc.)
   - Commercial License
   - Research Use (often free)
2. See real-time **price calculations** including VAT
3. Click **"Proceed to Checkout"**
4. Enter billing details
5. Complete payment via **PayFast**
6. Receive order confirmation and download links

### Guest Checkout

You don't need an account to use the cart:
- Items are saved in your browser session
- At checkout, enter your email for order confirmation
- If you create an account later, guest cart items can merge

---

## For Staff / Administrators

### Managing E-Commerce Settings

Access: **Admin → E-Commerce Settings**

#### General Tab
| Setting | Description |
|---------|-------------|
| Enable E-Commerce | Toggle between Request mode and Paid mode |
| Currency | ZAR, USD, EUR, etc. |
| VAT Rate | Percentage (e.g., 15% for South Africa) |
| VAT Number | Your organization's VAT registration |

#### Payment Tab (PayFast)
| Setting | Description |
|---------|-------------|
| Merchant ID | From your PayFast dashboard |
| Merchant Key | From your PayFast dashboard |
| Passphrase | Security passphrase (recommended) |
| Sandbox Mode | Enable for testing, disable for live payments |

#### Product Types
Configure available products:
- Digital downloads (JPEG, TIFF Master, PDF)
- Physical prints (various sizes)
- Licenses (Research, Commercial, Editorial)

#### Pricing
Set prices per product type. Prices are VAT-inclusive.

### Processing Orders

Access: **Admin → Orders**

1. View all orders with status filters
2. Click an order to see details
3. Update order status as you process it:
   - **Pending** → Awaiting payment
   - **Paid** → Payment received
   - **Processing** → Being prepared
   - **Completed** → Ready/Delivered
   - **Cancelled** / **Refunded**

### Request to Publish Review

Access: **Admin → Request to Publish**

1. View all reproduction requests
2. Review requester details and motivation
3. Approve or decline requests
4. Contact requester with next steps

---

## Status Reference

### Order Statuses
| Status | Meaning |
|--------|---------|
| 🟡 Pending | Awaiting payment |
| 🔵 Paid | Payment confirmed |
| 🟠 Processing | Order being prepared |
| 🟢 Completed | Order fulfilled |
| ⚫ Cancelled | Order cancelled |
| 🔴 Refunded | Payment returned |

### Request Statuses
| Status | Meaning |
|--------|---------|
| 🟡 Pending | Awaiting review |
| 🟢 Approved | Request granted |
| 🔴 Declined | Request denied |

---

## Tips

- **Save your cart** by logging in - guest carts may be lost if you clear browser data
- **Check product types** carefully - different formats have different prices
- **Research Use** is often free for academic/non-commercial purposes
- **Commercial licenses** are required for business or advertising use

---

## Need Help?

Contact the archive staff if you:
- Can't find the cart button on an item
- Have questions about pricing or licensing
- Need special arrangements for large orders
- Experience payment issues

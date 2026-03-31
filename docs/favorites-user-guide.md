# Favorites Module - User Guide

**Plugin:** ahgFavoritesPlugin  
**Version:** 1.0.0

---

## Overview

The Favorites module allows you to save archival records for quick access later. Build your own collection of frequently referenced items.

---

## Quick Start
```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Browse Records │ ──▶ │  Click Heart    │ ──▶ │  View Favorites │
│                 │     │  Icon ❤️         │     │  /favorites     │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

---

## Adding to Favorites

### From Any Record

1. Navigate to any archival record (Archive, Museum, Library, Gallery, or DAM)
2. Look for the **heart icon** (❤️) button below the image
3. Click to add to your favorites
4. The icon changes to confirm it's saved

### Button States

| Icon | Meaning |
|------|---------|
| ❤️ (outline) | Click to add to favorites |
| 💔 (broken) | Already in favorites - click to remove |

---

## Viewing Your Favorites

1. Navigate to `/favorites` in your browser
2. Or click **Favorites** in the user menu
3. View all your saved records in a list

### Favorites List Features

- View record title and identifier
- Quick link to view full record
- Remove individual items
- Clear all favorites

---

## Removing from Favorites

### From the Record Page

1. Navigate to the record
2. Click the broken heart icon (💔)
3. Item is removed from favorites

### From the Favorites List

1. Go to `/favorites`
2. Click the **Remove** button next to any item
3. Or click **Clear All** to remove everything

---

## Requirements

- **Must be logged in** to use favorites
- Works with all record types:
  - ✅ Archival Descriptions (ISAD)
  - ✅ Museum Objects
  - ✅ Library Items
  - ✅ Gallery Artworks
  - ✅ Digital Assets (DAM)

---

## Workflow Diagram
```
                    ┌─────────────────────────────────────┐
                    │           USER LOGGED IN            │
                    └─────────────────┬───────────────────┘
                                      │
                    ┌─────────────────▼───────────────────┐
                    │         Browse Any Record           │
                    │  (Archive/Museum/Library/Gallery/DAM)│
                    └─────────────────┬───────────────────┘
                                      │
              ┌───────────────────────┼───────────────────────┐
              │                       │                       │
              ▼                       ▼                       ▼
    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
    │  Click ❤️ Heart  │    │   Click 💔      │    │  View Favorites │
    │  Add to Favs    │    │   Remove        │    │  /favorites     │
    └────────┬────────┘    └────────┬────────┘    └────────┬────────┘
             │                      │                      │
             ▼                      ▼                      ▼
    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
    │  Saved to DB    │    │  Removed from   │    │  Browse/Remove  │
    │  Icon changes   │    │  Database       │    │  Items          │
    └─────────────────┘    └─────────────────┘    └─────────────────┘
```

---

## Tips

- Use favorites to build a research collection
- Share your favorites list URL with colleagues
- Favorites persist across browser sessions
- No limit on number of favorites

---

*Part of the AtoM AHG Framework*

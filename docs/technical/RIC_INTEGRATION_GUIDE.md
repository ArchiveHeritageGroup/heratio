# Records in Contexts (RiC) Integration Guide

## What is RiC?

Records in Contexts (RiC) is the new international standard from the International Council on Archives (ICA) that replaces the traditional archival standards ISAD(G), ISAAR(CPF), ISDF, and ISDIAH. Unlike the hierarchical approach of ISAD(G), RiC uses a **graph-based model** that allows records to have multiple relationships and contexts.

---

## How RiC Works in AtoM AHG

### The Basic Concept

Traditional AtoM shows records in a **tree structure** (Fonds → Series → File → Item). RiC adds a **network view** that shows all the connections between records, people, organizations, places, and events.
```
Traditional View (ISAD):          RiC View (Graph):
                                  
    Fonds                              Person ←──── Record ────→ Place
      │                                   │            │
    Series                                ↓            ↓
      │                           Organization ←── Event
    File                                
      │                           
    Item                          
```

### What You See

When viewing any archival description, a **RiC Explorer panel** appears in the sidebar showing:

1. **Interactive Graph** - Visual network of related entities
2. **Creators** - People and organizations who created the records
3. **Related Records** - Other descriptions connected to this one
4. **Events** - Activities like creation, accumulation, transfer

---

## User Features

### 1. RiC Panel (Sidebar)

On every record view page, the RiC panel shows:

| Feature | Description |
|---------|-------------|
| **2D Graph** | Interactive network diagram (drag, zoom, click) |
| **3D Graph** | Immersive 3D visualization (toggle button) |
| **Fullscreen** | Expand graph to full screen |
| **Accordions** | Expandable lists of Creators, Related Records, Events |

### 2. Full RiC Explorer

Access via: **Browse → RiC Explorer** or `/ric-dashboard/`

| Page | What It Does |
|------|--------------|
| **Dashboard** | Overview statistics and recent activity |
| **Graph Explorer** | Full-page interactive visualization |
| **Semantic Search** | Search by relationships, not just keywords |
| **Entity Categories** | Browse by RiC entity types |
| **Provenance Timeline** | Visual history of record custody |

### 3. Admin Dashboard

Access via: **Admin → RiC Management** or `/admin/ric`

| Section | Purpose |
|---------|---------|
| **Status** | Monitor synchronization health |
| **Orphans** | Manage disconnected data |
| **Queue** | View pending sync operations |
| **Configuration** | Adjust sync settings |

---

## How Records Map to RiC

### AtoM Records → RiC Entities

| What You Create in AtoM | Becomes in RiC |
|-------------------------|----------------|
| Archival Description (Fonds/Series/File) | **Record Set** |
| Archival Description (Item) | **Record** |
| Digital Object | **Instantiation** |
| Authority Record (Person) | **Person** |
| Authority Record (Family) | **Family** |
| Authority Record (Organization) | **Corporate Body** |
| Repository | **Agent (Holder)** |
| Function | **Activity** |
| Subject/Place Access Points | **Concepts/Places** |

### Relationships Captured

| AtoM Action | RiC Relationship Created |
|-------------|--------------------------|
| Add Creator to record | `hasCreator` / `wasCreatedBy` |
| Add record to Repository | `hasOrHadHolder` |
| Link digital object | `hasInstantiation` |
| Parent/child hierarchy | `isOrWasIncludedIn` |
| Add subject access point | `hasOrHadSubject` |
| Add place access point | `hasOrHadPlaceRelation` |

---

## Automatic Synchronization

### What Happens Automatically

| When You... | RiC System... |
|-------------|---------------|
| **Create** a record | Adds it to the graph with all relationships |
| **Edit** a record | Updates the graph connections |
| **Delete** a record | Removes it and cleans up orphaned links |
| **Move** a record | Updates parent/child relationships |
| **Add** a creator/subject | Creates new relationship links |

### Background Processing

- Sync runs automatically when you save
- Large operations queue for background processing
- Weekly integrity checks ensure data consistency
- Monthly cleanup removes orphaned data

---

## Benefits of RiC Integration

### For Researchers

- **Discover connections** between records that hierarchy doesn't show
- **Visual exploration** of archival collections
- **Multiple entry points** - find records via people, places, or events
- **Understand provenance** through relationship chains

### For Archivists

- **Richer description** with multi-dimensional relationships
- **Flexible arrangement** without breaking hierarchies
- **Standards compliance** with ICA's latest model
- **Future-proof** metadata that exports to RiC (Records in Contexts) linked data formats

### For Institutions

- **Linked data ready** - connects to Wikidata, VIAF, etc.
- **Semantic search** capabilities
- **Interoperability** with other RiC-compliant systems
- **Modern standards** alignment

---

## Quick Reference

### Access Points

| Feature | URL |
|---------|-----|
| RiC Panel | Sidebar on any record view |
| Full Explorer | `/ric-dashboard/` |
| Admin Dashboard | `/admin/ric` |

### Graph Colors

| Color | Entity Type |
|-------|-------------|
| 🔵 Cyan | Records (Fonds, Series, Files, Items) |
| 🟡 Yellow | Corporate Bodies |
| 🔴 Red | People and Families |
| 🟣 Purple | Activities and Events |
| 🟠 Orange | Places |
| ⚫ Gray | Digital Objects (Instantiations) |

### Keyboard Shortcuts (Graph)

| Key | Action |
|-----|--------|
| Scroll | Zoom in/out |
| Drag | Pan view |
| Click node | View details |
| Double-click | Navigate to record |
| Escape | Exit fullscreen |

---

## Summary

RiC integration transforms AtoM from a hierarchical catalog into a **connected knowledge graph**. Records, people, organizations, places, and events are all linked together, enabling new ways to discover and understand archival materials.

| Component | Status |
|-----------|--------|
| RiC Panel (sidebar) | ✅ Complete |
| Full Explorer | ✅ Complete |
| Admin Dashboard | ✅ Complete |
| Auto-sync | ✅ Complete |
| 2D/3D Visualization | ✅ Complete |

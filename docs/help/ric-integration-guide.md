> Heratio Help Center article. Category: Technical.

# Records in Contexts (RiC) Integration Guide

## What is RiC?

Records in Contexts (RiC) is the new international standard from the International Council on Archives (ICA) that replaces the traditional archival standards ISAD(G), ISAAR(CPF), ISDF, and ISDIAH. Unlike the hierarchical approach of ISAD(G), RiC uses a **graph-based model** that allows records to have multiple relationships and contexts.

---

## How RiC Works in Heratio AHG

### The Basic Concept

Traditional Heratio shows records in a **tree structure** (Fonds → Series → File → Item). RiC adds a **network view** that shows all the connections between records, people, organizations, places, and events.
<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 524 164" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="523" height="163" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="348.4" y1="50.0" x2="352.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="50.0" x2="355.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="50.0" x2="359.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="50.0" x2="362.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="50.0" x2="366.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="366.4" y1="50.0" x2="370.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="50.0" x2="373.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="373.6" y1="50.0" x2="377.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="50.0" x2="438.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="438.4" y1="50.0" x2="442.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="50.0" x2="445.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="50.0" x2="449.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="50.0" x2="452.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="452.8" y1="50.0" x2="456.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="50.0" x2="460.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="50.0" x2="463.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="58.0" x2="56.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="66.0" x2="56.8" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="58.0" x2="316.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="66.0" x2="316.0" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="58.0" x2="409.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="66.0" x2="409.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="90.0" x2="56.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="98.0" x2="56.8" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="98.0" x2="359.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="98.0" x2="362.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="98.0" x2="366.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="366.4" y1="98.0" x2="370.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="122.0" x2="56.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="130.0" x2="56.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><path d="M349.8 46.0 L342.8 50.0 L349.8 54.0 Z" fill="#10373E"/><path d="M462.2 46.0 L469.2 50.0 L462.2 54.0 Z" fill="#10373E"/><path d="M312.0 77.0 L316.0 84.0 L320.0 77.0 Z" fill="#10373E"/><path d="M405.6 77.0 L409.6 84.0 L413.6 77.0 Z" fill="#10373E"/><path d="M357.0 94.0 L350.0 98.0 L357.0 102.0 Z" fill="#10373E"/><text x="10.0" y="22.0" font-size="9.5" fill="#10373E">Traditional</text><text x="96.4" y="22.0" font-size="9.5" fill="#10373E">View</text><text x="132.4" y="22.0" font-size="9.5" fill="#10373E">(ISAD):</text><text x="254.8" y="22.0" font-size="9.5" fill="#10373E">RiC</text><text x="283.6" y="22.0" font-size="9.5" fill="#10373E">View</text><text x="319.6" y="22.0" font-size="9.5" fill="#10373E">(Graph):</text><text x="38.8" y="54.0" font-size="9.5" fill="#10373E">Fonds</text><text x="290.8" y="54.0" font-size="9.5" fill="#10373E">Person</text><text x="384.4" y="54.0" font-size="9.5" fill="#10373E">Record</text><text x="478.0" y="54.0" font-size="9.5" fill="#10373E">Place</text><text x="38.8" y="86.0" font-size="9.5" fill="#10373E">Series</text><text x="254.8" y="102.0" font-size="9.5" fill="#10373E">Organization</text><text x="377.2" y="102.0" font-size="9.5" fill="#10373E">Event</text><text x="38.8" y="118.0" font-size="9.5" fill="#10373E">File</text><text x="38.8" y="150.0" font-size="9.5" fill="#10373E">Item</text></svg></div>

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

### Heratio Records → RiC Entities

| What You Create in Heratio | Becomes in RiC |
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

| Heratio Action | RiC Relationship Created |
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
- **Future-proof** metadata that exports to linked data formats

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

RiC integration transforms Heratio from a hierarchical catalog into a **connected knowledge graph**. Records, people, organizations, places, and events are all linked together, enabling new ways to discover and understand archival materials.

| Component | Status |
|-----------|--------|
| RiC Panel (sidebar) | ✅ Complete |
| Full Explorer | ✅ Complete |
| Admin Dashboard | ✅ Complete |
| Auto-sync | ✅ Complete |
| 2D/3D Visualization | ✅ Complete |

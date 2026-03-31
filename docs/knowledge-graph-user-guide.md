# Knowledge Graph User Guide

The Knowledge Graph visualizes relationships between entities (people, organizations, places, dates) extracted from your archival records using AI-powered Named Entity Recognition (NER).

---

## Overview

The Knowledge Graph helps you:

- **Discover connections** between people, places, and organizations across your collection
- **Explore relationships** through an interactive visualization
- **Navigate** from entities to related records
- **Filter searches** by AI-extracted entities

---

## Accessing the Knowledge Graph

### From the Heritage Landing Page

1. Navigate to `/heritage`
2. In the "Explore By" section, click **Knowledge Graph**

### Direct URL

Navigate to `/heritage/graph`

---

## Understanding the Visualization

### Node Types and Colors

| Entity Type | Color | Icon |
|-------------|-------|------|
| Person | Blue | Person icon |
| Organization | Green | Building icon |
| Place | Red | Map pin icon |
| Date | Purple | Calendar icon |
| Event | Teal | Calendar event icon |
| Work | Pink | Document icon |

### Node Size

Larger nodes indicate entities that appear in more records.

### Connections (Edges)

Lines between nodes represent **co-occurrence** - entities that appear together in the same records. Thicker lines indicate stronger relationships (more frequent co-occurrence).

---

## Interacting with the Graph

### Navigation

- **Pan**: Click and drag the background
- **Zoom**: Use mouse scroll wheel or pinch gesture
- **Reset View**: Click the reset button in the controls

### Selecting Entities

- **Click a node** to view entity details in the side panel
- **Double-click** to focus the graph on that entity

### Filtering

Use the checkboxes to show/hide entity types:
- People
- Organizations
- Places
- Dates

---

## Entity Detail Panel

When you click an entity, the side panel shows:

### Summary
- Entity name and type
- Confidence score (High/Medium/Low)
- Number of occurrences

### Statistics
- Total occurrences across records
- Number of related entities
- Average confidence score

### Related Entities
- Other entities frequently appearing with this one
- Click to navigate to related entities

### Found In
- Records containing this entity
- Click to view the full record

### Actions
- **Search Records**: Find all records mentioning this entity
- **View in Graph**: Center the graph on this entity

---

## Entity Detail Page

For more information about an entity, click "View Details" or navigate to:

`/heritage/entity/{type}/{value}`

Example: `/heritage/entity/place/South%20Africa`

### Page Sections

1. **Header**: Entity name, type badge, occurrence count, confidence indicator
2. **Records**: List of archival records containing this entity
3. **Related Entities**: Other entities connected through co-occurrence
4. **Details**: Metadata including first/last seen dates
5. **External Links**: Links to authority records, Wikidata, VIAF (if available)

---

## Filtering Searches by Entity

### From the Graph

1. Click an entity node
2. In the detail panel, click **Search Records**
3. View filtered search results

### From Explore Page

1. Navigate to `/heritage/explore/place` (or other entity type)
2. Click on a specific place/person/organization
3. View records tagged with that entity

### From Search Page

Use the filter sidebar to select:
- **Place**: Geographic locations
- **Creator**: People and organizations
- **People (AI)**: NER-extracted persons
- **Organizations (AI)**: NER-extracted organizations
- **Places (AI)**: NER-extracted locations

---

## How Entities are Extracted

### Named Entity Recognition (NER)

The system uses AI to automatically identify entities in:
- Record titles
- Scope and content descriptions
- Attached PDF documents

### Confidence Scores

Each extracted entity has a confidence score:

| Level | Score | Badge |
|-------|-------|-------|
| High | 90%+ | Green |
| Medium | 70-89% | Yellow |
| Low | Below 70% | Grey |

Only entities with 70%+ confidence appear in the Knowledge Graph.

### Review Process

Extracted entities go through a review workflow:
1. **Pending**: Awaiting review
2. **Approved**: Verified and included in graph
3. **Linked**: Connected to authority record
4. **Rejected**: Excluded from graph

---

## Tips for Effective Use

### Discovering Connections

1. Start with a known entity (person, place, or organization)
2. Explore connected entities
3. Follow the path to discover unexpected relationships

### Research Workflows

1. Use the graph to identify key figures in a topic
2. Find geographic connections
3. Discover organizational relationships
4. Track events and dates

### Quality Indicators

- Larger nodes = more frequently mentioned
- Thicker connections = stronger relationships
- High confidence badges = more reliable extraction

---

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `+` / `-` | Zoom in/out |
| `0` | Reset zoom |
| `Esc` | Close entity panel |
| Arrow keys | Pan view |

---

## Troubleshooting

### Graph Not Loading

1. Ensure JavaScript is enabled
2. Check browser console for errors
3. Try refreshing the page
4. Clear browser cache

### No Entities Showing

1. Verify NER extraction has been run on records
2. Check that entities have been approved
3. Ensure confidence threshold is met (70%+)

### Missing Connections

Entities must appear in the same record to show a connection. Records processed at different times may not show all relationships until the graph is rebuilt.

---

## Administrator Notes

### Syncing Entities

Run periodically to sync new approved entities:
```bash
php symfony ai:sync-entity-cache
```

### Rebuilding the Graph

Rebuild to include new relationships:
```bash
php symfony heritage:build-graph
```

### Statistics

View current graph statistics:
```bash
php symfony heritage:build-graph --stats
```

---

## Related Documentation

- [AI Tools User Guide](ai-tools-user-guide.md) - NER extraction and entity review
- [Heritage Platform User Guide](heritage-sites-user-guide.md) - Heritage landing page features
- [Advanced Search User Guide](advanced-search-user-guide.md) - Search filtering options

---

## Support

For technical issues or questions, contact your system administrator or refer to the [Technical Documentation](technical/ner-knowledge-graph-technical.md).

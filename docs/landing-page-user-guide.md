# Landing Page Builder

## User Guide

Create custom landing pages for your archive with a visual drag-and-drop builder. No coding required.

---

## Overview
```
+-------------------------------------------------------------+
|                   LANDING PAGE BUILDER                       |
+-------------------------------------------------------------+
|                                                              |
|   CREATE        DESIGN          PUBLISH       MANAGE         |
|     |             |               |             |            |
|     v             v               v             v            |
|   New Page    Drag Blocks    Go Live      Versions          |
|   Setup       Configure      Preview      Restore           |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                    WHAT YOU CAN DO                           |
+-------------------------------------------------------------+
|  + DRAG-AND-DROP BUILDER   - Visual page construction        |
|  + 20+ BLOCK TYPES         - Hero banners, search, stats     |
|  + COLUMN LAYOUTS          - 1, 2, or 3 column sections      |
|  + LIVE PREVIEW            - See changes before publishing   |
|  + VERSION CONTROL         - Save drafts, restore versions   |
|  + DYNAMIC CONTENT         - Auto-update from database       |
|  + RESPONSIVE DESIGN       - Works on all devices            |
|  + CUSTOM STYLING          - Colors, padding, backgrounds    |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Main Menu
      |
      v
   Admin
      |
      v
   Landing Pages --------------------------------+
      |                                          |
      +---> All Pages       (manage pages)       |
      |                                          |
      +---> Create New      (start building)     |
      |                                          |
      +---> Edit Page       (visual builder)     |
```

---

## Creating a New Page

### Step 1: Open Landing Pages

Go to **Admin** > **Landing Pages**

### Step 2: Click Create New Page

Click the **Create New Page** button

### Step 3: Fill in Page Details
```
+-------------------------------------------------------------+
|  CREATE NEW LANDING PAGE                                     |
+-------------------------------------------------------------+
|                                                              |
|  Page Name:     [Home Page                    ]              |
|                 (Internal name for reference)                |
|                                                              |
|  URL Slug:      [/] [home                     ]              |
|                 (Creates URL: /landing/home)                 |
|                                                              |
|  Description:   [Main landing page for visitors]             |
|                                                              |
|  [ ] Set as default home page                                |
|  [x] Active (visible to public)                              |
|                                                              |
|                    [Create Page]                             |
+-------------------------------------------------------------+
```

### Step 4: Start Building

You are taken to the visual builder where you can add blocks.

---

## Using the Visual Builder

### Builder Interface
```
+-------+--------------------------------+------------+
| BLOCKS|          CANVAS                | SETTINGS   |
| PANEL |                                | PANEL      |
+-------+--------------------------------+------------+
|       |                                |            |
| Layout|  +------------------------+   | Block      |
| ------|  |     Header Section     |   | Settings   |
| Header|  +------------------------+   |            |
| Footer|  |                        |   | Title:     |
| 2 Col |  |     Hero Banner        |   | [____]     |
| 3 Col |  |                        |   |            |
|       |  +------------------------+   | Config:    |
| Content  |                        |   | [____]     |
| ------|  |     Search Box         |   |            |
| Hero  |  +------------------------+   | Styling:   |
| Text  |  |                        |   | [____]     |
| Image |  |     Browse Panels      |   |            |
|       |  |                        |   |            |
| Data  |  +------------------------+   |            |
| ------|                                |            |
| Search|                                |            |
| Stats |                                |            |
| Recent|                                |            |
+-------+--------------------------------+------------+
```

### Adding Blocks

**Method 1: Drag and Drop**
1. Find the block you want in the left panel
2. Drag it to the canvas
3. Drop where you want it to appear

**Method 2: Click to Add**
1. Click on any block type in the left panel
2. Block is added at the bottom of the page

### Reordering Blocks

1. Grab the drag handle on any block card
2. Drag up or down to new position
3. Release to drop

### Editing a Block

1. Click the pencil icon on the block card
2. Settings panel opens on the right
3. Configure the block options
4. Changes save automatically

### Block Actions
```
+-------------------------------------------------------------+
|  BLOCK CARD ACTIONS                                          |
+-------------------------------------------------------------+
|                                                              |
|  [eye]     Toggle visibility (hide without deleting)         |
|  [pencil]  Edit block settings                               |
|  [copy]    Duplicate this block                              |
|  [trash]   Delete this block                                 |
|                                                              |
+-------------------------------------------------------------+
```

---

## Block Types Reference

### Layout Blocks
```
+-------------------------------------------------------------+
|  LAYOUT BLOCKS                                               |
+-------------------------------------------------------------+
|                                                              |
|  Header Section  - Page header with logo, title, navigation  |
|  Footer Section  - Page footer with columns and links        |
|  1 Column Row    - Single column container                   |
|  2 Column Row    - Two column layout (drag blocks inside)    |
|  3 Column Row    - Three column layout                       |
|  Divider         - Horizontal line separator                 |
|  Spacer          - Vertical spacing between blocks           |
|                                                              |
+-------------------------------------------------------------+
```

### Content Blocks
```
+-------------------------------------------------------------+
|  CONTENT BLOCKS                                              |
+-------------------------------------------------------------+
|                                                              |
|  Hero Banner       - Large banner with image, title, CTA     |
|  Text Content      - Rich text with optional image           |
|  Image Carousel    - Slideshow of images from collection     |
|                                                              |
+-------------------------------------------------------------+
```

### Data Blocks (Dynamic Content)
```
+-------------------------------------------------------------+
|  DATA BLOCKS - Auto-populate from database                   |
+-------------------------------------------------------------+
|                                                              |
|  Search Box         - Archive search field                   |
|  Browse Panels      - Category links with counts             |
|  Recent Items       - Latest records added                   |
|  Featured Items     - Curated items from IIIF collection     |
|  Statistics         - Entity counts (archives, images, etc.) |
|  Holdings List      - List of top-level holdings             |
|  Repository         - Spotlight on a single repository       |
|    Spotlight                                                 |
|  Map                - Interactive map of repositories        |
|                                                              |
+-------------------------------------------------------------+
```

### Other Blocks
```
+-------------------------------------------------------------+
|  OTHER BLOCKS                                                |
+-------------------------------------------------------------+
|                                                              |
|  Quick Links     - Buttons or links to important pages       |
|  Copyright Bar   - Copyright notice and links                |
|                                                              |
+-------------------------------------------------------------+
```

---

## Block Configuration Examples

### Hero Banner
```
+-------------------------------------------------------------+
|  HERO BANNER SETTINGS                                        |
+-------------------------------------------------------------+
|                                                              |
|  Title:           [Welcome to Our Archive          ]         |
|  Subtitle:        [Preserving history for the future]        |
|  Background Image:[/uploads/hero.jpg               ]         |
|  Height:          [400px                           ]         |
|  Text Alignment:  [Center    v]                              |
|  Overlay Opacity: [0.5                             ]         |
|                                                              |
|  Call to Action:                                             |
|  Button Text:     [Start Exploring                 ]         |
|  Button URL:      [/informationobject/browse       ]         |
|                                                              |
+-------------------------------------------------------------+
```

### Browse Panels
```
+-------------------------------------------------------------+
|  BROWSE PANELS SETTINGS                                      |
+-------------------------------------------------------------+
|                                                              |
|  Title:           [Browse Our Collections          ]         |
|  Style:           [Cards    v]  (or List)                    |
|  Columns:         [3        v]                               |
|  Show Counts:     [x]                                        |
|                                                              |
|  Panels:                                                     |
|  +---------------------------------------------------+       |
|  | Label: [Archives    ]  Icon: [bi-archive ]        |       |
|  | URL:   [/informationobject/browse         ]       |       |
|  | Count Entity: [informationobject    v]            |       |
|  +---------------------------------------------------+       |
|  | Label: [Photographs ]  Icon: [bi-camera  ]        |       |
|  | URL:   [/informationobject/browse?type=photo]     |       |
|  +---------------------------------------------------+       |
|                                                              |
|                        [+ Add Panel]                         |
+-------------------------------------------------------------+
```

### Statistics
```
+-------------------------------------------------------------+
|  STATISTICS SETTINGS                                         |
+-------------------------------------------------------------+
|                                                              |
|  Title:           [Our Collection by Numbers       ]         |
|  Layout:          [Horizontal v]                             |
|  Animate Numbers: [x]                                        |
|                                                              |
|  Stats:                                                      |
|  +---------------------------------------------------+       |
|  | Label: [Archives    ]  Icon: [bi-archive ]        |       |
|  | Entity: [informationobject  v]                    |       |
|  +---------------------------------------------------+       |
|  | Label: [Digital Images]  Icon: [bi-image ]        |       |
|  | Entity: [digitalobject  v]                        |       |
|  +---------------------------------------------------+       |
|  | Label: [Repositories ]  Icon: [bi-building]       |       |
|  | Entity: [repository     v]                        |       |
|  +---------------------------------------------------+       |
|                                                              |
+-------------------------------------------------------------+
```

### Recent Items
```
+-------------------------------------------------------------+
|  RECENT ITEMS SETTINGS                                       |
+-------------------------------------------------------------+
|                                                              |
|  Title:           [Recent Additions                ]         |
|  Entity Type:     [Information Objects v]                    |
|  Limit:           [6                               ]         |
|  Layout:          [Grid      v]  (or List)                   |
|  Columns:         [3                               ]         |
|  Show Date:       [x]                                        |
|  Show Thumbnail:  [x]                                        |
|                                                              |
+-------------------------------------------------------------+
```

---

## Using Column Layouts

### Two Column Layout
```
+-------------------------------------------------------------+
|  2 COLUMN ROW                                                |
+-------------------------------------------------------------+
|                                                              |
|  +-------------------------+  +-------------------------+    |
|  |      Column 1           |  |      Column 2           |    |
|  |  (drop blocks here)     |  |  (drop blocks here)     |    |
|  |                         |  |                         |    |
|  |  - Browse Panels        |  |  - Recent Items         |    |
|  |  - Quick Links          |  |  - Statistics           |    |
|  |                         |  |                         |    |
|  +-------------------------+  +-------------------------+    |
|                                                              |
|  Column Widths:  [50%  v]  |  [50%  v]                       |
|  (Options: 25/75, 33/66, 50/50, 66/33, 75/25)                |
|                                                              |
+-------------------------------------------------------------+
```

### Three Column Layout
```
+-------------------------------------------------------------+
|  3 COLUMN ROW                                                |
+-------------------------------------------------------------+
|                                                              |
|  +----------------+  +----------------+  +----------------+  |
|  |   Column 1     |  |   Column 2     |  |   Column 3     |  |
|  |                |  |                |  |                |  |
|  | (drop blocks)  |  | (drop blocks)  |  | (drop blocks)  |  |
|  +----------------+  +----------------+  +----------------+  |
|                                                              |
+-------------------------------------------------------------+
```

### Adding Blocks to Columns

1. Drag a block from the palette
2. Drop it into the column drop zone
3. Block appears inside the column
4. Each column can have multiple blocks

---

## Styling Blocks

### Block Style Settings
```
+-------------------------------------------------------------+
|  STYLING OPTIONS (available on all blocks)                   |
+-------------------------------------------------------------+
|                                                              |
|  Title:            [Custom Block Title     ]                 |
|  CSS Classes:      [my-custom-class        ]                 |
|  Container Type:   [container    v]                          |
|                    - container (centered, max-width)         |
|                    - container-fluid (full width)            |
|                    - none (no container)                     |
|                                                              |
|  Background Color: [#ffffff  ] [color picker]                |
|  Text Color:       [#212529  ] [color picker]                |
|                                                              |
|  Padding Top:      [3  v]  (0-5 Bootstrap units)             |
|  Padding Bottom:   [3  v]                                    |
|                                                              |
|  Column Span:      [12 v]  (for grid layouts, 1-12)          |
|                                                              |
+-------------------------------------------------------------+
```

---

## Preview and Publish

### Previewing Your Page

1. Click the **Preview** button in the toolbar
2. Opens in new tab showing how page will look
3. Hidden blocks appear with reduced opacity
4. Close preview tab to return to editor

### Saving a Draft

1. Click **Save Draft** in the toolbar
2. Creates a version snapshot
3. Optionally add notes about changes
4. Draft saves your work without publishing

### Publishing

1. Click **Publish** in the toolbar
2. Makes page visible to public
3. Creates a published version
4. Previous version is preserved

### Restoring Previous Versions

1. Click **Versions** dropdown in toolbar
2. See list of saved versions
3. Click on any version to preview
4. Click **Restore** to revert to that version

---

## Page Settings

### Accessing Settings

Click the **Settings** button in the toolbar to open the settings panel.

### Page Settings Options
```
+-------------------------------------------------------------+
|  PAGE SETTINGS                                               |
+-------------------------------------------------------------+
|                                                              |
|  Page Name:       [Home Page                   ]             |
|                                                              |
|  URL Slug:        [/] [home                    ]             |
|                   Accessible at: /landing/home               |
|                                                              |
|  Description:     [Main landing page           ]             |
|                                                              |
|  [ ] Set as default home page                                |
|      (Displays when visiting root URL)                       |
|                                                              |
|  [x] Active (visible to public)                              |
|      (Uncheck to hide page temporarily)                      |
|                                                              |
|                  [Save Settings]                             |
|                                                              |
|  -----------------------------------------------------------+
|  DANGER ZONE                                                 |
|  [Delete Page] (not available for default page)              |
+-------------------------------------------------------------+
```

---

## Managing Multiple Pages

### Page List View
```
+-------------------------------------------------------------+
|  LANDING PAGES                            [Create New Page]  |
+-------------------------------------------------------------+
|                                                              |
|  +------------------------+  +------------------------+      |
|  | Home Page              |  | About Us               |      |
|  | [Default] [Active]     |  | [Active]               |      |
|  | 8 blocks               |  | 4 blocks               |      |
|  | /home                  |  | /about                 |      |
|  |                        |  |                        |      |
|  | [Edit] [Preview] [View]|  | [Edit] [Preview] [View]|      |
|  | Updated: Today 14:30   |  | Updated: Yesterday     |      |
|  +------------------------+  +------------------------+      |
|                                                              |
|  +------------------------+                                  |
|  | Research Guide         |                                  |
|  | [Inactive]             |                                  |
|  | 6 blocks               |                                  |
|  | /research              |                                  |
|  |                        |                                  |
|  | [Edit] [Preview]       |                                  |
|  | Updated: Last week     |                                  |
|  +------------------------+                                  |
|                                                              |
+-------------------------------------------------------------+
```

### Page Status Badges

| Badge | Meaning |
|-------|---------|
| **Default** | This page shows at root URL |
| **Active** | Visible to public |
| **Inactive** | Hidden from public |

---

## Tips and Best Practices
```
+-------------------------------+--------------------------------+
|  DO                           |  DONT                          |
+-------------------------------+--------------------------------+
|  Start with a clear layout    |  Overcrowd the page            |
|  Use hero banner for impact   |  Use too many colors           |
|  Include search prominently   |  Forget mobile users           |
|  Show key statistics          |  Leave pages unpublished       |
|  Save drafts frequently       |  Delete without reviewing      |
|  Preview before publishing    |  Ignore version history        |
|  Use column layouts wisely    |  Nest too many blocks          |
+-------------------------------+--------------------------------+
```

### Recommended Page Structure
```
+-------------------------------------------------------------+
|  TYPICAL LANDING PAGE STRUCTURE                              |
+-------------------------------------------------------------+
|                                                              |
|  1. Header Section (logo, title, navigation)                 |
|  2. Hero Banner (welcome message, CTA)                       |
|  3. Search Box (prominently placed)                          |
|  4. Browse Panels OR Quick Links (navigation options)        |
|  5. Statistics (show collection size)                        |
|  6. Recent Items OR Featured Items (engagement)              |
|  7. Footer Section (links, contact info)                     |
|                                                              |
+-------------------------------------------------------------+
```

---

## Common Use Cases

### Archive Home Page
- Hero banner with institution branding
- Prominent search box
- Browse panels for main collections
- Statistics showing holdings counts
- Recent additions carousel
- Footer with contact information

### Repository Spotlight
- Hero with repository image
- Repository spotlight block
- Holdings list from that repository
- Map showing location
- Contact information

### Research Guide
- Text content explaining resources
- Browse panels for key collections
- Quick links to finding aids
- Search box for immediate access

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Block not appearing | Check visibility toggle (eye icon) |
| Changes not saving | Refresh page, check network connection |
| Page not visible | Ensure "Active" is checked in settings |
| Cannot delete page | Default page cannot be deleted |
| Blocks not dragging | Try clicking instead of dragging |
| Preview shows old content | Clear browser cache |
| Statistics show 0 | Check entity type in configuration |

---

## Need Help?

Contact your system administrator if you experience issues with the Landing Page Builder.

---

*Part of the AtoM AHG Framework*

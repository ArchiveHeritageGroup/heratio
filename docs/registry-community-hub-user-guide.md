# Community Hub & Registry - User Guide

## Overview

The AtoM Community Hub & Registry (ahgRegistryPlugin) is a comprehensive directory and collaboration platform for the global GLAM community (Galleries, Libraries, Archives, Museums) and Digital Asset Management (DAM) institutions. It serves as the central meeting point where institutions, vendors, and archival software converge.

The Registry provides:

- **Public Directory** -- A searchable, filterable catalogue of institutions, vendors, and archival software worldwide, with map visualization
- **Self-Service Profiles** -- Institutions and vendors can register and manage their own profiles, contacts, instances, and client relationships
- **Community Features** -- User groups with Google Groups-style threaded discussions, a blog/news platform, and newsletters
- **Reviews & Ratings** -- Community-driven ratings for vendors and software products
- **Favorites** -- Bookmark institutions, vendors, software, and groups for quick access
- **Vendor CRM** -- Call and issue log for tracking client interactions, support tickets, and follow-ups
- **Software Catalog** -- Full software lifecycle management including components/plugins, releases, git integration, and direct file uploads
- **Sync API** -- Automated registration and heartbeat monitoring for remote Heratio/AtoM instances
- **Admin Dashboard** -- Centralized moderation, verification, and configuration

### Who Is It For?

| Audience | What They Do |
|----------|-------------|
| **Archivists & Librarians** | Discover peer institutions, find compatible software, join user groups, participate in discussions |
| **Museum & Gallery Staff** | Register their institution, document their archival setup, connect with service providers |
| **Vendors & Developers** | List their services and products, manage client relationships, publish software releases |
| **System Administrators** | Register AtoM/Heratio instances, enable sync monitoring, track software versions |
| **Community Organizers** | Create and manage user groups, moderate discussions, publish blog posts |
| **Registry Administrators** | Verify registrations, moderate content, manage settings, monitor sync health |

---

## How It Works

```
+------------------------------------------------------------------------+
|                    REGISTRY ARCHITECTURE                                |
+------------------------------------------------------------------------+
|                                                                        |
|  PUBLIC DIRECTORY                    SELF-SERVICE                       |
|  ----------------                   ------------                       |
|  Browse Institutions                My Institution Dashboard            |
|  Browse Vendors                     My Vendor Dashboard                 |
|  Browse Software                    Manage Contacts                     |
|  Map View                           Manage Instances                    |
|  Unified Search                     Manage Software & Releases          |
|  Detail Pages                       Call & Issue Log (CRM)              |
|                                                                        |
|  COMMUNITY                           ADMIN                             |
|  ---------                           -----                             |
|  User Groups                         Dashboard & Stats                  |
|  Threaded Discussions                Verify Institutions/Vendors        |
|  Blog / News                         Moderate Content                   |
|  Newsletters                         Manage Groups & Members            |
|  Reviews & Ratings                   Newsletters & Email                |
|  Favorites                           Sync Monitoring                    |
|                                      Settings & Footer                  |
|                                      Data Import                        |
|                                                                        |
|  SYNC API                                                              |
|  --------                                                              |
|  Remote Instance Registration                                          |
|  Heartbeat Monitoring                                                  |
|  Metadata Sync                                                         |
|  Public JSON Directory                                                 |
|                                                                        |
+------------------------------------------------------------------------+
```

---

## Getting Started

### Accessing the Registry

The Registry is available at:

```
/registry
```

The home page displays:

- **Live statistics** -- counts of registered institutions, vendors, software, and user groups
- **Featured institutions** -- up to 6 highlighted institutions
- **Featured vendors** -- up to 6 highlighted vendors
- **Featured software** -- up to 6 highlighted software products
- **Recent blog posts** -- the latest 4 published articles
- **Recent discussions** -- the 5 most recent community discussions
- **Quick link cards** -- shortcuts to register an institution, register as a vendor, or browse groups
- **Newsletter call-to-action** -- subscribe to the community newsletter

### Creating an Account

To interact with the Registry beyond browsing (registering entities, joining groups, posting discussions, leaving reviews), you need an account.

1. Navigate to `/registry/register`
2. Fill in the registration form with your name, email, and password
3. If moderation is enabled (default), your account will be pending until an administrator approves it
4. Once approved, log in at `/registry/login`

**Social login** is also supported if the administrator has enabled OAuth providers:

- Google
- Facebook
- GitHub
- LinkedIn
- Microsoft

Click the corresponding provider button on the login page to authenticate via OAuth.

### Navigation Structure

The Registry uses a breadcrumb navigation system. All pages show a trail like:

```
Home > Registry > Institutions > [Institution Name]
```

Key navigation entry points:

| URL | Description |
|-----|-------------|
| `/registry` | Home page with stats and featured items |
| `/registry/institutions` | Institution directory |
| `/registry/vendors` | Vendor directory |
| `/registry/software` | Software catalogue |
| `/registry/map` | Interactive map of institutions |
| `/registry/search` | Unified search across all entities |
| `/registry/groups` | User groups directory |
| `/registry/community` | Community hub (groups + discussions + blog) |
| `/registry/blog` | Blog / news listing |
| `/registry/newsletters` | Newsletter archive |
| `/registry/my/institution` | My institution dashboard |
| `/registry/my/vendor` | My vendor dashboard |
| `/registry/my/favorites` | My bookmarked items |
| `/registry/my/groups` | Groups I have joined |
| `/registry/admin` | Admin dashboard (admin only) |

---

## Public Directory

### Browsing Institutions

**URL:** `/registry/institutions`

The Institutions Directory lists all active, registered institutions as card tiles in a responsive grid (1-3 columns depending on screen size).

**Search bar** -- Type keywords to search by institution name, description, or collection summary. Results use MySQL full-text search.

**Filter sidebar** (left side, collapsible on mobile):

| Filter | Options |
|--------|---------|
| **Type** | Archive, Library, Museum, Gallery, Digital Asset Management, Heritage Site, Research Centre, Government, University, Other |
| **Size** | Small, Medium, Large, National |
| **Governance** | Public, Private, NGO, Academic, Government, Tribal, Community |
| **Uses AtoM** | Yes / No |
| **Country** | Dynamic list from registered institutions |

**Sorting** -- Results can be sorted by name (default, ascending) or other criteria via URL parameters (`sort` and `dir`).

**Pagination** -- 24 results per page with numbered page navigation.

**Quick actions:**

- Click **Map View** to switch to the interactive map
- Click **Register** to add your own institution

Each institution card shows the institution name, type badge, country, short description, and verification status.

### Browsing Vendors

**URL:** `/registry/vendors`

The Vendors Directory uses the same card grid layout as institutions.

**Filter sidebar:**

| Filter | Options |
|--------|---------|
| **Vendor Type** | Developer, Integrator, Consultant, Service Provider, Hosting, Digitization, Training, Other |
| **Specialization** | Archives, Libraries, Museums, Galleries, Digital Asset Management, Digital Preservation |
| **Country** | Dynamic list |

Each vendor card displays the vendor name, type(s), country, short description, rating, and verification badge.

### Browsing Software

**URL:** `/registry/software`

The Software Catalogue lists all registered archival software products.

**Filter sidebar:**

| Filter | Options |
|--------|---------|
| **Category** | AMS, IMS, DAM, DAMS, CMS, GLAM, Preservation, Digitization, Discovery, Utility, Plugin, Integration, Theme, Other |
| **Vendor** | Filter by publishing vendor |
| **License** | Filter by license type (e.g., GPL-3.0, MIT, proprietary) |
| **Pricing** | Free, Open Source, Freemium, Subscription, One-time, Contact |
| **GLAM Sector** | Filter by target sector |

Each software card shows the product name, category badge, vendor name, latest version, pricing model, rating, and download count.

### Viewing Detail Pages

#### Institution Detail

**URL:** `/registry/institutions/:slug`

The institution detail page shows:

- **Header** -- Logo, name, verification badge, featured badge, favorite button
- **Overview** -- Full description, institution type, governance model, size, established year
- **Address & Contact** -- Street address, city, country, phone, fax, email, website
- **Map location** -- If latitude/longitude are set, an embedded map marker
- **Collection information** -- Collection summary, strengths, total holdings, digitization percentage
- **Technical details** -- Descriptive standards used, management system, whether they use AtoM
- **GLAM sectors** -- Which sectors the institution operates in
- **Tags** -- Searchable keyword tags
- **Contacts** -- Public contact persons (name, role, email, phone)
- **Instances** -- Registered AtoM/Heratio instances with status indicators
- **Software in use** -- Which software products the institution uses
- **Vendor relationships** -- Which vendors provide services to this institution
- **Reviews** -- Reviews left for associated vendors/software

Logged-in users can:

- Toggle the **favorite** (star) button to bookmark the institution
- If the owner or admin, click **Edit** to modify the profile

#### Vendor Detail

**URL:** `/registry/vendors/:slug`

The vendor detail page shows:

- **Header** -- Logo, name, verification badge, featured badge, favorite button
- **Overview** -- Description, vendor type(s), team size, established year
- **Contact & Address** -- Email, phone, address, website
- **Social links** -- GitHub, GitLab, LinkedIn URLs
- **Specializations** -- Areas of expertise (JSON array displayed as badges)
- **Service regions** -- Geographic areas served
- **Languages** -- Languages supported
- **Certifications** -- Professional certifications
- **Company details** -- Registration number, VAT number
- **Client relationships** -- Institutions this vendor works with (public relationships)
- **Software products** -- Software published by this vendor
- **Reviews** -- Community reviews with star ratings
- **Tags** -- Keyword tags

#### Software Detail

**URL:** `/registry/software/:slug`

The software detail page shows:

- **Header** -- Logo, name, verification badge, featured badge, favorite button
- **Overview** -- Full description, category, vendor name (linked)
- **Technical details** -- License, pricing model, latest version, supported platforms, minimum PHP/MySQL versions
- **GLAM sectors** -- Target sectors
- **Links** -- Website, documentation URL, installation URL
- **Git repository** -- Provider (GitHub/GitLab/Bitbucket), repository URL, default branch, latest tag, latest commit
- **Components/Plugins** -- If the software has registered sub-components (plugins, modules, themes, etc.), they are listed with name, type, category, version, and description
- **Releases** -- Link to the full release history page
- **Reviews** -- Community reviews with star ratings
- **Institutions using this software** -- Count and list

**Software Releases page** (`/registry/software/:slug/releases`) shows a chronological list of all releases with:

- Version number and release type (major/minor/patch/beta/rc/alpha)
- Release date
- Git tag and commit hash
- Release notes
- Download link (if file attached)
- Stability indicator

### Unified Search

**URL:** `/registry/search?q=your+query`

The search page performs a cross-entity search across institutions, vendors, and software simultaneously.

**Parameters:**

| Parameter | Description |
|-----------|-------------|
| `q` | Search query (required) |
| `type` | Filter by entity type: `institution`, `vendor`, or `software` (optional) |
| `page` | Page number for pagination |

Results are returned as a unified list, each showing the entity type, name, and a brief description.

### Map View

**URL:** `/registry/map`

The interactive map displays all institutions that have latitude and longitude coordinates set. The map:

- Centers on the default location (configurable in admin settings; default: South Africa)
- Uses the default zoom level (configurable; default: 5)
- Shows institution markers with popup cards containing the institution name, type, country, and a link to the detail page
- Uses Leaflet.js with OpenStreetMap tiles

---

## Institution Management

### Registering Your Institution

**URL:** `/registry/my/institution/register`

**Requires:** Logged-in user

1. Click **Register Institution** from the home page or navigate directly to the URL
2. Fill in the registration form:

| Field | Required | Description |
|-------|----------|-------------|
| Institution name | Yes | Official name of your institution |
| Type | Yes | Archive, Library, Museum, Gallery, DAM, Heritage Site, Research Centre, Government, University, Other |
| Description | No | Full text description |
| Short description | No | Up to 500 characters for card display |
| Website | No | Main website URL |
| Email | No | Contact email |
| Phone | No | Contact phone |
| Street address | No | Physical address |
| City | No | City |
| Province/State | No | Province or state |
| Postal code | No | ZIP/postal code |
| Country | No | Defaults to the registry's configured default country |
| Size | No | Small, Medium, Large, National |
| Governance | No | Public, Private, NGO, Academic, Government, Tribal, Community |
| Parent body | No | Umbrella organization (e.g., "University of Cape Town") |
| Established year | No | Year the institution was founded |
| Collection summary | No | Brief description of holdings |
| Total holdings | No | e.g., "50,000 items" or "2 km of shelving" |
| Management system | No | Current archival management system |
| Uses AtoM | No | Whether the institution uses AtoM |

3. Click **Submit** to create your institution
4. If moderation is enabled, your institution will appear as "unverified" until an admin verifies it

### Institution Dashboard

**URL:** `/registry/my/institution`

After registration, the My Institution Dashboard provides a centralized management hub showing:

- **Institution overview** -- Name, type, verification status
- **Contacts** -- List of registered contact persons
- **Instances** -- Registered AtoM/Heratio instances
- **Vendors** -- Vendor relationships
- **Software** -- Software products in use

Quick action links lead to each management section.

### Editing Your Institution Profile

**URL:** `/registry/my/institution/edit`

The edit form includes all fields from registration plus additional fields:

| Additional Field | Description |
|-----------------|-------------|
| Logo | Upload an image (PNG, JPG, SVG, WebP; max 5 MB) |
| Fax | Fax number |
| Latitude / Longitude | GPS coordinates for map placement |
| Accreditation | Professional accreditations |
| Collection strengths | Comma-separated list of collection strengths |
| Descriptive standards | Multi-select: ISAD(G), DACS, RAD, Dublin Core, MODS, etc. |
| GLAM sectors | Multi-select checkboxes for relevant sectors |
| Digitization percentage | Integer 0-100 |
| Open to public | Whether the institution is publicly accessible |
| Institution URL | Main website (distinct from AtoM instance URL) |
| Tags | Comma-separated keyword tags for discoverability |

### Managing Contacts

**URL:** `/registry/my/institution/contacts`

Contacts are the people associated with your institution who can be publicly listed.

**Adding a contact** (`/registry/my/institution/contacts/add`):

| Field | Required | Description |
|-------|----------|-------------|
| First name | Yes | Contact's first name |
| Last name | Yes | Contact's last name |
| Email | No | Email address |
| Phone | No | Office phone |
| Mobile | No | Mobile phone |
| Job title | No | e.g., "Head Archivist" |
| Department | No | e.g., "Special Collections" |
| Primary contact | No | Check to mark as the institution's primary contact |
| Public | No | Whether this contact is visible on the public profile |
| Notes | No | Internal notes about this contact |

**Editing a contact** -- Click the edit button next to any contact in the list to modify their details.

Contact roles are stored as a JSON array, supporting multiple roles such as primary, technical, billing, administrative, and curatorial.

### Managing Instances

**URL:** `/registry/my/institution/instances`

Instances are the actual AtoM or Heratio installations your institution runs.

**Adding an instance** (`/registry/my/institution/instances/add`):

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Descriptive name (e.g., "Production Server") |
| URL | No | Instance URL (e.g., `https://atom.example.org`) |
| Instance type | No | Production, Staging, Development, Demo, Offline |
| Software | No | Software name (default: "heratio") |
| Software version | No | e.g., "2.8.2" |
| Hosting | No | Self-hosted, Cloud, Vendor-hosted, SaaS |
| Hosting vendor | No | Select from registered vendors |
| Maintenance vendor | No | Select from registered vendors |
| OS environment | No | e.g., "Ubuntu 22.04 LTS" |
| Languages | No | Comma-separated language codes (e.g., "en,fr,af") |
| Descriptive standard | No | Primary standard (RAD, ISAD(G), DACS, etc.) |
| Record count | No | Number of archival descriptions |
| Digital object count | No | Number of digital objects |
| Storage (GB) | No | Total storage in gigabytes |
| Feature usage | No | Checkboxes for which AtoM features are in use (accessions, authority records, physical storage, etc.) |
| Sync enabled | No | Enable automated sync with the registry |
| Public | No | Whether this instance appears on the public profile |
| Description | No | Notes about this instance |

**Instance Detail Page** (`/registry/instances/:id`) -- Shows all instance details plus:

- Hosting vendor information (if assigned)
- Maintenance vendor information (if assigned)
- Recent sync log history (last 10 events)

### Managing Software in Use

**URL:** `/registry/my/institution/software`

View which software products your institution uses. Software associations are created through the `registry_institution_software` table, linking institutions to software products with optional version and deployment date information.

### Viewing Vendor Relationships

**URL:** `/registry/my/institution/vendors`

View which vendors provide services to your institution. Relationships can include:

- Development
- Hosting
- Maintenance
- Consulting
- Digitization
- Training
- Integration

---

## Vendor Management

### Registering as a Vendor

**URL:** `/registry/my/vendor/register`

**Requires:** Logged-in user

1. Navigate to the vendor registration page
2. Fill in the form:

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Company or individual vendor name |
| Vendor type | Yes | Multi-select checkboxes: Developer, Integrator, Consultant, Service Provider, Hosting, Digitization, Training, Other |
| Description | No | Full text description of services |
| Short description | No | Up to 500 characters for card display |
| Website | No | Company website |
| Email | No | Business email |
| Phone | No | Business phone |
| Street address | No | Office address |
| City | No | City |
| Province/State | No | Province or state |
| Postal code | No | ZIP/postal code |
| Country | No | Defaults to registry default |
| Company registration | No | Business registration number |
| Established year | No | Year business was established |
| Team size | No | Solo, 2-5, 6-20, 21-50, 50+ |
| GitHub URL | No | GitHub organization/profile URL |

3. Click **Submit** to create your vendor profile

### Vendor Dashboard

**URL:** `/registry/my/vendor`

The My Vendor Dashboard shows:

- **Vendor overview** -- Name, type(s), verification status, rating
- **Contacts** -- Registered contact persons
- **Clients** -- Institution relationships
- **Software products** -- Published software

### Editing Your Vendor Profile

**URL:** `/registry/my/vendor/edit`

The edit form includes all registration fields plus:

| Additional Field | Description |
|-----------------|-------------|
| Logo | Upload an image (PNG, JPG, SVG, WebP) |
| VAT number | Tax registration number |
| GitLab URL | GitLab profile URL |
| LinkedIn URL | LinkedIn company page URL |
| Tags | Comma-separated keyword tags |

### Managing Vendor Contacts

**URL:** `/registry/my/vendor/contacts`

Works identically to institution contacts. Add, edit, and manage contact persons associated with your vendor profile. The same fields apply (first name, last name, email, phone, mobile, job title, department, primary flag, public flag, notes).

### Managing Client Relationships

**URL:** `/registry/my/vendor/clients`

Client relationships link your vendor to the institutions you serve.

**Adding a client** (`/registry/my/vendor/clients/add`):

| Field | Required | Description |
|-------|----------|-------------|
| Institution | Yes | Select from registered institutions |
| Relationship type | Yes | Developer, Hosting, Maintenance, Consulting, Digitization, Training, Integration |
| Service description | No | Description of services provided |
| Start date | No | When the relationship began |
| Public | No | Whether this relationship is visible on public profiles |

Client relationships appear on both the vendor's and institution's public profiles (when marked as public).

### Managing Software Products

**URL:** `/registry/my/vendor/software`

View all software products published under your vendor profile.

**Adding a software product** (`/registry/my/vendor/software/add`):

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Software product name |
| Category | Yes | AMS, IMS, DAM, DAMS, CMS, GLAM, Preservation, Digitization, Discovery, Utility, Plugin, Integration, Theme, Other |
| Description | No | Full description |
| Short description | No | Up to 500 characters |
| Website | No | Product website |
| Documentation URL | No | Link to documentation |
| Install URL | No | Link to installation instructions |
| Git provider | No | GitHub, GitLab, Bitbucket, Self-hosted, None |
| Git URL | No | Repository URL |
| Git default branch | No | Default: "main" |
| Git is public | No | Whether the repository is publicly accessible |
| License | No | e.g., "GPL-3.0", "MIT", "Proprietary" |
| Pricing model | No | Free, Open Source, Freemium, Subscription, One-time, Contact |
| Pricing details | No | Description of pricing tiers |
| GLAM sectors | No | Multi-select checkboxes for target sectors |

**Editing a software product** (`/registry/my/vendor/software/:id/edit`) -- Modify any of the above fields.

### Managing Software Releases

**URL:** `/registry/my/vendor/software/:id/releases`

Track version history for each software product.

**Adding a release** (`/registry/my/vendor/software/:id/releases/add`):

| Field | Required | Description |
|-------|----------|-------------|
| Version | Yes | Semantic version (e.g., "2.8.2") |
| Release type | No | Major, Minor, Patch, Beta, RC, Alpha |
| Release notes | No | What changed in this version |
| Git tag | No | Tag name in the repository (e.g., "v2.8.2") |
| Git commit | No | Full commit SHA |
| Is stable | No | Whether this is a stable release (default: yes) |
| Released at | No | Release date (defaults to current date) |

When a release is marked as the latest, the software product's `latest_version` field is updated.

**Uploading software packages** (`/registry/my/vendor/software/:id/upload`) -- Upload distributable files directly:

- Allowed extensions are configurable (default: `zip, tar.gz, deb, rpm`)
- Maximum file size is configurable (default: 100 MB)
- File checksum (SHA-256) is computed on upload
- Download count is tracked per release

### Call & Issue Log (CRM)

**URL:** `/registry/my/vendor/call-log`

The Call & Issue Log is a CRM-style interaction tracker for vendors to log communications with their clients.

**Dashboard stats** at the top show:

- **Open** -- Count of entries with status Open, In Progress, or Escalated
- **Resolved** -- Count of entries with status Resolved or Closed
- **Overdue Follow-ups** -- Count of entries past their follow-up date that are not yet resolved

**Filters:**

| Filter | Options |
|--------|---------|
| Status | Open, In Progress, Resolved, Closed, Escalated |
| Type | Call, Email, Meeting, Support Ticket, Site Visit, Video Call, Other |
| Priority | Low, Medium, High, Urgent |

**Log entry table columns:**

- Interaction type icon with inbound/outbound direction indicator
- Subject (linked to detail view)
- Contact person name
- Status badge (color-coded)
- Priority badge (color-coded)
- Follow-up date (overdue dates highlighted in red)
- Created date
- Action buttons (view, edit)

Overdue follow-up rows are highlighted with a red background.

**Creating a new entry** (`/registry/my/vendor/call-log/add`):

| Field | Required | Description |
|-------|----------|-------------|
| Interaction type | Yes | Phone Call, Email, Meeting, Support Ticket, Site Visit, Video Call, Other |
| Direction | No | Inbound or Outbound (default: Outbound) |
| Institution | No | Select from your client institutions |
| Subject | Yes | Brief summary of the interaction |
| Description | No | Detailed notes about the interaction |
| Status | No | Open (default), In Progress, Resolved, Closed, Escalated |
| Priority | No | Low, Medium (default), High, Urgent |
| Duration (minutes) | No | How long the interaction lasted |
| Contact name | No | Name of the person you spoke with |
| Contact email | No | Their email |
| Contact phone | No | Their phone |
| Resolution notes | No | How the issue was resolved |
| Follow-up date | No | Date for next follow-up action |
| Follow-up notes | No | What needs to happen at follow-up |

**Editing an entry** -- Modify any field. When the status is changed to Resolved or Closed, the system automatically records the resolution timestamp and the user who resolved it.

**Viewing an entry** (`/registry/my/vendor/call-log/:id`) -- Shows the full detail view with all fields, linked institution details, and complete interaction history.

---

## Community Features

### Community Hub

**URL:** `/registry/community`

The Community Hub is a landing page that aggregates community activity:

- **Featured Groups** -- Up to 6 highlighted user groups
- **Recent Discussions** -- The 10 most recent discussions across all groups
- **Latest Blog Posts** -- The 6 most recent published articles

### User Groups

#### Browsing Groups

**URL:** `/registry/groups`

User groups are communities within the Registry organized by topic, region, or software focus.

**Filter sidebar:**

| Filter | Options |
|--------|---------|
| **Group type** | Regional, Topic, Software, Institutional, Other |
| **Country** | Dynamic list |
| **Region** | Dynamic list |
| **Virtual** | Filter for virtual-only groups |

Each group card shows:

- Group name and type badge
- Description excerpt
- Location (city, country) or "Virtual"
- Member count
- Meeting frequency
- Verification and featured status

#### Viewing a Group

**URL:** `/registry/groups/:slug`

The group detail page shows:

- **Header** -- Logo, name, type, verification badge
- **Description** -- Full group description
- **Meeting details** -- Frequency (weekly/biweekly/monthly/quarterly/annual/ad hoc), format (in-person/virtual/hybrid), platform (e.g., Zoom, Teams), next meeting date and details
- **Focus areas** -- Topic tags
- **External links** -- Website, email, mailing list URL, Slack URL, Discord URL, forum URL
- **Location** -- City, country, region
- **Member count** -- Total active members
- **Organizer** -- Name and email of the group organizer
- **Recent discussions** -- Latest discussion threads
- **Action buttons:**
  - **Join Group** -- Become a member (requires login)
  - **Leave Group** -- Remove yourself from the group
  - **Toggle Notifications** -- Enable/disable email notifications for new discussions
  - **View Members** -- See the full member list

#### Creating a Group

**URL:** `/registry/my/groups/create`

**Requires:** Logged-in user

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Group name |
| Description | No | Full description |
| Group type | No | Regional, Topic, Software, Institutional, Other |
| Website | No | Group website |
| Email | No | Group contact email |
| City | No | City |
| Country | No | Country |
| Region | No | Geographic region (e.g., "Southern Africa") |
| Virtual group | No | Check if the group meets only online |
| Meeting frequency | No | Weekly, Biweekly, Monthly, Quarterly, Annual, Ad hoc |
| Meeting format | No | In-person, Virtual, Hybrid |
| Meeting platform | No | e.g., "Zoom", "Microsoft Teams" |
| Mailing list URL | No | Link to mailing list |
| Slack URL | No | Slack workspace invite |
| Discord URL | No | Discord server invite |

The creating user is automatically added as the group's organizer.

#### Managing Groups

**URL:** `/registry/my/groups`

View all groups you are a member of. Organizers can:

- **Edit group** (`/registry/my/groups/:id/edit`) -- Modify all group details plus next meeting date and details
- **Manage members** (`/registry/my/groups/:id/members`) -- Update member roles (organizer, co-organizer, member, speaker, sponsor) or remove members

#### Joining and Leaving Groups

- **Join:** Click the "Join Group" button on any group's detail page. You must be logged in.
- **Leave:** Click "Leave Group" on the group detail page.
- **Notifications:** Toggle email notifications on/off for the group. When enabled, you receive email alerts for new discussions and replies.

#### Viewing Members

**URL:** `/registry/groups/:slug/members`

The public member list shows each member's name, email, role, institution affiliation, and join date.

### Discussions

Discussions follow a Google Groups-style threaded format within each user group.

#### Browsing Discussions

**URL:** `/registry/groups/:slug/discussions`

The discussion list shows all threads in the group with:

- Title and topic type badge
- Author name
- Reply count
- View count
- Last reply timestamp
- Pinned status (pinned discussions appear first)

**Filter by topic type:**

| Topic Type | Description |
|-----------|-------------|
| Discussion | General discussion |
| Question | A question seeking answers |
| Announcement | Official announcement |
| Event | Event notification |
| Showcase | Show and tell |
| Help | Help request |

#### Starting a Discussion

**URL:** `/registry/groups/:slug/discussions/new`

**Requires:** Logged-in user

| Field | Required | Description |
|-------|----------|-------------|
| Title | Yes | Discussion thread title |
| Content | Yes | Full content of the opening post |
| Topic type | No | Discussion, Question, Announcement, Event, Showcase, Help |

After creating a discussion, all group members with email notifications enabled receive an email alert (excluding the author).

#### Viewing and Replying

**URL:** `/registry/groups/:slug/discussions/:id`

The discussion view shows:

- The original post with author name, date, and content
- Topic type, view count, and reply count
- Pinned/locked/resolved status indicators
- A threaded reply tree with nested replies

**Replying** -- Group members can post replies using the reply form at the bottom of the discussion. Replies support:

- Plain text content
- Nested threading (reply to a specific reply via `parent_reply_id`)
- Accepted answer marking (for Question-type discussions)

Replies also trigger email notifications to group members.

### Blog / News

#### Browsing Blog Posts

**URL:** `/registry/blog`

The blog listing shows all published posts as cards with:

- Title
- Author name and author type (admin, vendor, institution, user group)
- Category badge
- Published date
- Excerpt
- Featured image (if set)
- Featured and pinned status

**Filter by category:**

| Category | Description |
|----------|-------------|
| News | General news |
| Announcement | Official announcements |
| Event | Event information |
| Tutorial | How-to guides |
| Case Study | Implementation stories |
| Release | Software release notes |
| Community | Community stories |
| Other | Other content |

Pagination: 12 posts per page.

#### Reading a Blog Post

**URL:** `/registry/blog/:slug`

The full post view shows the title, author, published date, category, featured image, and full content. The view count is incremented on each visit.

#### Writing Blog Posts

**URL:** `/registry/my/blog/new`

**Requires:** Admin user

Blog posts are created in draft status and must be published by an admin.

| Field | Required | Description |
|-------|----------|-------------|
| Title | Yes | Post title |
| Content | Yes | Full post content (HTML supported) |
| Excerpt | No | Short summary for card display |
| Author type | No | Admin, Vendor, Institution, User Group |
| Category | No | News, Announcement, Event, Tutorial, Case Study, Release, Community, Other |

**Editing** (`/registry/my/blog/:id/edit`) -- Modify title, content, excerpt, and category.

### Newsletters

#### Subscribing

**URL:** `/registry/newsletter/subscribe`

1. Enter your email address and name
2. If you are logged in, your email may be pre-filled and your user account is linked
3. Subscriptions are auto-confirmed by default
4. If already subscribed, the system informs you

#### Unsubscribing

**URL:** `/registry/newsletter/unsubscribe?token=<token>`

Each subscriber receives a unique unsubscribe token. Clicking the unsubscribe link in any newsletter email processes the unsubscription automatically.

#### Browsing Newsletters

**URL:** `/registry/newsletters`

View an archive of all sent newsletters. Each entry shows the subject, excerpt, send date, and a link to read the full content.

#### Reading a Newsletter

**URL:** `/registry/newsletters/:id`

The full newsletter content is displayed. Only newsletters with "sent" status are publicly viewable.

---

## Reviews & Ratings

### Leaving a Review

**URL:** `/registry/my/institution/review/:type/:id`

Where `:type` is either `vendor` or `software`, and `:id` is the entity ID.

**Requires:** Logged-in user

| Field | Required | Description |
|-------|----------|-------------|
| Rating | Yes | 1-5 stars |
| Title | No | Review headline |
| Reviewer name | No | Your display name |
| Comment | Yes | Your review text |

Reviews are associated with the reviewer's institution (if they have one registered). Reviews appear on the vendor or software detail page.

### Review Display

Reviews show:

- Star rating (1-5)
- Reviewer name and institution
- Review title
- Comment text
- Date posted
- Verification status

**Average ratings** are computed and stored on vendor and software records (`average_rating`, `rating_count`) for display on cards and detail pages.

---

## Favorites

### Adding and Removing Favorites

Favorites let you bookmark entities for quick access. You can favorite:

- **Institutions**
- **Vendors**
- **Software**
- **User Groups**

On any detail page, click the **star button** to toggle the favorite. A filled star indicates the item is favorited; an outline star indicates it is not.

### Viewing Your Favorites

**URL:** `/registry/my/favorites`

**Requires:** Logged-in user

Your favorites page groups bookmarked items by type:

- **Institutions** -- Cards with links to institution detail pages
- **Vendors** -- Cards with links to vendor detail pages
- **Software** -- Cards with links to software detail pages
- **Groups** -- Cards with links to group detail pages

---

## Admin Features

All admin features require the `administrator` credential. Navigate to the admin dashboard at `/registry/admin`.

### Admin Dashboard

**URL:** `/registry/admin`

The dashboard shows real-time statistics in color-coded cards:

| Metric | Description |
|--------|-------------|
| Institutions | Total count + pending verification count |
| Vendors | Total count + pending verification count |
| Software | Total count |
| Instances | Total count + online count |
| Groups | Total count |
| Discussions | Total count |
| Blog Posts | Total count + pending review count |
| Reviews | Total count |

**Quick link cards** provide shortcuts to:

- Verify Institutions (with pending count badge)
- Verify Vendors (with pending count badge)
- Moderate Blog (with pending count badge)
- Sync Dashboard (with online count badge)
- User Approval (with pending count badge)
- Manage Software
- Manage Groups
- Moderate Discussions
- Newsletters
- Subscribers
- Email Settings
- Footer Settings
- General Settings

### Verifying Institutions

**URL:** `/registry/admin/institutions`

Lists all institutions (including inactive) sorted by creation date. For each institution, admins can:

| Action | Description |
|--------|-------------|
| **Verify** | Mark the institution as verified (adds verification badge, records verifier and timestamp) |
| **Unverify** | Remove verification status |
| **Feature** | Toggle featured status (featured items appear on the home page) |
| **Suspend** | Deactivate the institution (hidden from public directory) |
| **Activate** | Reactivate a suspended institution |
| **Delete** | Permanently remove the institution |
| **Edit** | Open the institution edit form |

Verification notes can be attached to explain the verification decision.

### Verifying Vendors

**URL:** `/registry/admin/vendors`

Same workflow as institutions. Actions: Verify, Unverify, Feature, Suspend, Activate, Delete, Edit.

### Managing Software

**URL:** `/registry/admin/software`

Same workflow as institutions and vendors. Admins can also edit any software product directly via `/registry/admin/software/:id/edit`.

### Managing Groups

**URL:** `/registry/admin/groups`

Lists all user groups with admin-only controls:

| Action | Description |
|--------|-------------|
| **Verify** | Mark group as verified |
| **Unverify** | Remove verification |
| **Feature** | Toggle featured status |
| **Suspend** | Deactivate the group |
| **Activate** | Reactivate a suspended group |
| **Delete** | Permanently remove the group |
| **Edit** | Full group edit form with admin-only fields (is_active, is_verified, is_featured, organizer details, focus areas) |
| **Members** | Full member management with add, remove, toggle active, update role capabilities |
| **Email** | Send an email to all active group members |

**Admin Group Edit** (`/registry/admin/groups/:id/edit`) -- Includes all standard group fields plus admin-only toggles for active, verified, and featured status.

**Admin Group Members** (`/registry/admin/groups/:id/members`) -- Full member management:

- Add new members by email
- Update member roles (organizer, co-organizer, member, speaker, sponsor)
- Toggle member active/inactive status
- Remove members
- Send bulk email to all active members

### Moderating Discussions

**URL:** `/registry/admin/discussions`

Lists all discussions across all groups. Admins can filter by status (active, closed, hidden, spam). Actions per discussion:

| Action | Description |
|--------|-------------|
| **Hide** | Set status to "hidden" (not visible to members) |
| **Spam** | Mark as spam |
| **Activate** | Restore to active status |
| **Lock** | Prevent new replies |
| **Pin** | Pin to the top of the group's discussion list |

### Moderating Blog Posts

**URL:** `/registry/admin/blog`

Lists all blog posts regardless of status. Actions:

| Action | Description |
|--------|-------------|
| **Publish** | Set status to "published" and record the publication timestamp |
| **Archive** | Move to archived status |
| **Feature** | Toggle featured status |
| **Pin** | Toggle pinned status (pinned posts appear first in listings) |

### Moderating Reviews

**URL:** `/registry/admin/reviews`

Lists all reviews with the ability to:

- **Toggle visibility** -- Show/hide individual reviews
- **Delete** -- Permanently remove a review

### User Approval

**URL:** `/registry/admin/users`

When moderation is enabled, new user accounts require admin approval.

The page shows:

- **Pending users** -- Users with `active=0`, showing name, email, username, and registration date
- **Recently approved users** -- The last 20 active users

Actions:

| Action | Description |
|--------|-------------|
| **Approve** | Set the user's active flag to 1, granting access |
| **Reject** | Permanently delete the user account and all associated records |

### Data Import

**URL:** `/registry/admin/import`

The import tool supports importing data from external sources via JSON:

1. Paste the JSON data into the text area
2. Click **Preview** to see what will be imported
3. Review the preview
4. Click **Import** to execute the import

The import service handles mapping external data structures to the registry's database schema.

### Newsletter Administration

**URL:** `/registry/admin/newsletters`

Manage newsletters with subscriber statistics.

**Creating a newsletter** (`/registry/admin/newsletters/new`):

- Subject (required)
- Content (required, supports HTML)
- Excerpt
- Schedule for later or save as draft

**Editing** (`/registry/admin/newsletters/:id/edit`) -- Modify content of unsent newsletters.

**Sending** -- Click "Send" to dispatch the newsletter to all active subscribers. The system tracks:

- Recipient count
- Sent count
- Open count
- Click count
- Individual send status (queued, sent, failed, bounced, opened, clicked)

### Managing Subscribers

**URL:** `/registry/admin/subscribers`

View and manage newsletter subscribers:

- Email, name, status (active/unsubscribed/bounced)
- Subscription date
- Linked user, institution, or vendor account
- Confirmation status

### Email Settings (SMTP)

**URL:** `/registry/admin/email`

Configure the SMTP server used for sending newsletters and discussion notifications:

| Setting | Description |
|---------|-------------|
| SMTP enabled | Toggle SMTP on/off |
| SMTP host | Server hostname (e.g., `smtp.gmail.com`) |
| SMTP port | Server port (default: 587) |
| SMTP encryption | TLS, SSL, or None |
| SMTP username | Authentication username |
| SMTP password | Authentication password / app password |
| From email | Sender email address |
| From name | Sender display name |

**Test email** -- Enter an email address and click "Send Test" to verify your SMTP configuration. The result is displayed immediately.

If SMTP is not configured, the system falls back to PHP's built-in `mail()` function.

### Footer Settings

**URL:** `/registry/admin/footer`

Customize the registry's footer:

- **Description** -- Footer tagline text
- **Copyright** -- Copyright notice (supports `{year}` placeholder and HTML links)
- **Columns** -- Up to 4 link columns, each with a title and a list of label/URL pairs

The footer editor provides a visual form for managing link columns without editing JSON directly.

### Registry Settings

**URL:** `/registry/admin/settings`

All registry settings are stored in the `registry_settings` table and editable from this page.

**Key settings:**

| Setting | Default | Description |
|---------|---------|-------------|
| `registry_name` | Heratio Registry | Display name |
| `moderation_enabled` | 1 | Require admin approval for registrations |
| `allow_self_registration` | 1 | Allow public self-registration |
| `featured_count` | 6 | Number of featured items on home page |
| `heartbeat_interval_hours` | 24 | Expected heartbeat interval |
| `heartbeat_offline_threshold_days` | 7 | Days without heartbeat before marking offline |
| `max_upload_size_mb` | 100 | Maximum software upload size |
| `allowed_upload_extensions` | zip,tar.gz,deb,rpm | Allowed file types for uploads |
| `default_country` | South Africa | Default country for new registrations |
| `map_default_lat` | -30.5595 | Map center latitude |
| `map_default_lng` | 22.9375 | Map center longitude |
| `map_default_zoom` | 5 | Map zoom level |
| `max_attachment_size_mb` | 10 | Maximum attachment size for discussions |
| `allowed_attachment_types` | jpg,jpeg,png,gif,pdf,... | Allowed attachment types |
| `discussion_require_approval` | 0 | Moderate new discussions |
| `blog_require_approval` | 1 | Moderate blog posts from non-admins |
| `max_logo_size_mb` | 5 | Maximum logo upload size |
| `allowed_logo_types` | jpg,jpeg,png,gif,svg,webp | Allowed logo file types |

**OAuth settings:**

| Setting | Description |
|---------|-------------|
| `oauth_google_enabled` | Enable Google OAuth |
| `oauth_google_client_id` | Google OAuth Client ID |
| `oauth_google_client_secret` | Google OAuth Client Secret |
| `oauth_facebook_enabled` | Enable Facebook OAuth |
| `oauth_facebook_app_id` | Facebook App ID |
| `oauth_facebook_app_secret` | Facebook App Secret |
| `oauth_github_enabled` | Enable GitHub OAuth |
| `oauth_github_client_id` | GitHub OAuth Client ID |
| `oauth_github_client_secret` | GitHub OAuth Client Secret |

### Sync Dashboard

**URL:** `/registry/admin/sync`

The Sync Dashboard monitors all instances with sync enabled:

- **Instance list** -- Shows each sync-enabled instance with:
  - Instance name and institution name
  - Status (online/offline/maintenance/decommissioned)
  - Last heartbeat timestamp
  - Last sync timestamp
  - Software version
  - Record count and digital object count

- **Recent sync logs** -- The last 50 sync events showing:
  - Instance name
  - Event type (register, heartbeat, sync, update, error)
  - Status (success/error)
  - Timestamp
  - Error message (if any)
  - IP address

---

## Sync API

The Sync API enables remote Heratio/AtoM instances to register with the Registry and send periodic health updates. All API endpoints use JSON request/response bodies.

### API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/registry/api/sync/register` | None | Register a new instance |
| POST | `/registry/api/sync/heartbeat` | Sync Token | Send a heartbeat |
| POST | `/registry/api/sync/update` | Sync Token | Update metadata |
| GET | `/registry/api/sync/status` | Sync Token | Get current status |
| GET | `/registry/api/directory` | None (public) | Get the public institution directory |
| GET | `/registry/api/software/:slug/latest` | None (public) | Get the latest version of a software product |

### Registering a Remote Instance

**Endpoint:** `POST /registry/api/sync/register`

A remote instance sends its details to register with the Registry.

**Request body:**

```json
{
  "institution_name": "Cape Town Archive",
  "institution_slug": "cape-town-archive",
  "institution_type": "archive",
  "country": "South Africa",
  "city": "Cape Town",
  "website": "https://cta.org.za",
  "email": "info@cta.org.za",
  "instance_name": "CTA Production",
  "instance_url": "https://atom.cta.org.za",
  "instance_type": "production",
  "software": "heratio",
  "software_version": "2.8.2",
  "hosting": "self_hosted",
  "uses_atom": true,
  "record_count": 45000,
  "digital_object_count": 12000,
  "storage_gb": 250.5
}
```

**Response:**

```json
{
  "success": true,
  "institution_id": 42,
  "instance_id": 67,
  "sync_token": "a1b2c3d4e5f6..."
}
```

The returned `sync_token` must be stored by the remote instance and included in all subsequent API calls.

If the `institution_slug` matches an existing institution, the instance is added to that institution instead of creating a new one.

### Heartbeat Mechanism

**Endpoint:** `POST /registry/api/sync/heartbeat`

Remote instances should send heartbeats at regular intervals (default: every 24 hours).

**Headers:**

```
X-Sync-Token: <sync_token>
```

Or include the token in the request body:

```json
{
  "sync_token": "a1b2c3d4e5f6...",
  "software_version": "2.8.2",
  "record_count": 46000,
  "digital_object_count": 12500,
  "storage_gb": 260.0
}
```

**Response:**

```json
{
  "success": true,
  "instance_id": 67,
  "latest_version": "2.9.0"
}
```

The `latest_version` field is included when a newer version of the software is available in the Registry, enabling automatic update notifications.

**Offline detection:** If an instance misses heartbeats for longer than `heartbeat_offline_threshold_days` (default: 7 days), it can be flagged as offline.

### Updating Metadata

**Endpoint:** `POST /registry/api/sync/update`

Send comprehensive metadata updates for both the instance and the parent institution.

**Headers:**

```
X-Sync-Token: <sync_token>
```

**Request body:**

```json
{
  "instance": {
    "name": "CTA Production (Updated)",
    "software_version": "2.9.0",
    "record_count": 50000,
    "digital_object_count": 15000,
    "storage_gb": 300.0,
    "sync_data": {
      "plugins_enabled": ["ahgCorePlugin", "ahgThemeB5Plugin"],
      "last_import": "2026-01-15"
    }
  },
  "institution": {
    "name": "Cape Town Archive",
    "description": "Updated description...",
    "total_holdings": "50,000 items",
    "digitization_percentage": 30,
    "glam_sectors": ["archive", "library"]
  }
}
```

### Checking Status

**Endpoint:** `GET /registry/api/sync/status?sync_token=<token>`

Returns the current registration status, instance details, institution details, and the last 10 sync log entries.

### Public Directory API

**Endpoint:** `GET /registry/api/directory`

Returns a JSON array of all active institutions with their public instances. No authentication required.

**Response:**

```json
[
  {
    "id": 42,
    "name": "Cape Town Archive",
    "slug": "cape-town-archive",
    "institution_type": "archive",
    "city": "Cape Town",
    "country": "South Africa",
    "latitude": -33.9249,
    "longitude": 18.4241,
    "website": "https://cta.org.za",
    "uses_atom": 1,
    "is_verified": 1,
    "logo_path": "/uploads/registry/institutions/cta-logo.png",
    "instances": [
      {
        "id": 67,
        "name": "CTA Production",
        "url": "https://atom.cta.org.za",
        "instance_type": "production",
        "software": "heratio",
        "software_version": "2.8.2",
        "status": "online"
      }
    ]
  }
]
```

### Software Latest Version API

**Endpoint:** `GET /registry/api/software/:slug/latest`

Returns the latest version information for a software product. Used by remote instances to check for updates.

**Response:**

```json
{
  "software": "Heratio",
  "slug": "heratio",
  "latest_version": "2.9.0",
  "release": {
    "version": "2.9.0",
    "release_type": "minor",
    "released_at": "2026-02-01 00:00:00",
    "git_tag": "v2.9.0",
    "is_stable": true
  }
}
```

---

## Software Components

### What Are Components?

Software products can have sub-components such as plugins, modules, extensions, themes, or libraries. The Registry tracks these in the `registry_software_component` table.

### Viewing Components

**URL:** `/registry/software/:slug` (scroll to Components section)

Components are displayed on the software detail page, organized by category with:

- Name and type badge (plugin, module, extension, theme, integration, library, other)
- Category grouping
- Version number
- Short description
- Required flag (indicates whether the component is mandatory)
- Links to documentation and git repository

### Adding Components (Admin)

Components can be added to a software product through the software component management interface:

| Field | Description |
|-------|-------------|
| Name | Component name |
| Component type | Plugin, Module, Extension, Theme, Integration, Library, Other |
| Category | Functional category |
| Description | Full description |
| Short description | Card-level summary |
| Version | Component version |
| Is required | Whether this component is mandatory |
| Git URL | Source code repository |
| Documentation URL | Link to component documentation |
| Icon class | Font Awesome icon class for display |
| Sort order | Display ordering (lower = first) |

---

## Instance Features

### Feature Usage Tracking

Each instance can report which AtoM features it uses through the `registry_instance_feature` table. This enables:

- Understanding feature adoption across the community
- Identifying commonly used vs. underutilized features
- Planning development priorities

Features are tracked per instance with a name (e.g., "accession_records", "archival_descriptions"), a usage flag, and optional comments.

### Instance Detail View

**URL:** `/registry/instances/:id`

The instance detail page shows:

- Instance name and status indicator
- Parent institution (linked)
- Instance type (production/staging/development/demo/offline)
- Software and version
- Hosting type and hosting vendor
- Maintenance vendor
- URL (linked)
- Operating system environment
- Interface languages
- Descriptive standard
- Record count and digital object count
- Storage usage
- Feature usage checkboxes with comments
- Sync status and token information
- Recent sync log entries

---

## Attachments

The Registry supports file attachments on multiple entity types:

| Entity Type | Use Case |
|-------------|----------|
| Discussion | Attach files to discussion threads |
| Reply | Attach files to discussion replies |
| Blog post | Attach images and documents to blog posts |
| Institution | Attach documents to institution profiles |
| Vendor | Attach documents to vendor profiles |
| Software | Attach screenshots, documentation, or packages to software profiles |

**Attachment properties:**

- File path, name, size, and MIME type
- File type classification: image, document, log, archive, screenshot, other
- Caption text
- Inline flag (for embedding in content)
- Download count tracking
- Uploader identification

**Limits:**

- Maximum attachment size: configurable (default: 10 MB for discussions/blog)
- Allowed types: configurable (default: `jpg, jpeg, png, gif, pdf, doc, docx, xlsx, csv, txt, log, zip`)

---

## Tags

Tags provide cross-cutting keyword categorization for institutions, vendors, and software. Tags are:

- Stored in the `registry_tag` table as `(entity_type, entity_id, tag)` tuples
- Entered as comma-separated values in edit forms
- Unique per entity (no duplicate tags on the same entity)
- Searchable through the unified search

---

## Tips & Best Practices

### For Institutions

1. **Complete your profile** -- Profiles with logos, descriptions, and collection details get significantly more visibility. Upload a logo and fill in as many fields as possible.

2. **Add coordinates** -- Set latitude and longitude so your institution appears on the interactive map. This helps other institutions and vendors find you geographically.

3. **Register your instances** -- Document your AtoM/Heratio installations. This helps the community understand deployment patterns and helps vendors identify potential clients.

4. **Enable sync** -- If you run Heratio, enable the sync API on your instances. This provides automated monitoring and keeps your version information up to date.

5. **Add contacts selectively** -- Only mark contacts as "public" if they are comfortable being listed. Use the "primary" flag to identify the main point of contact.

6. **Leave reviews** -- Review the vendors and software you use. Honest reviews help other institutions make informed decisions.

7. **Join relevant groups** -- Participate in user groups related to your region, sector, or software stack. The discussions are a valuable knowledge resource.

### For Vendors

1. **Verify your profile** -- Verified vendors receive a blue checkmark badge, which builds trust. Complete your profile thoroughly to expedite admin verification.

2. **List your software** -- Register all your software products with detailed descriptions, documentation links, and git repository URLs.

3. **Publish releases** -- Keep your release history current. Institutions use this to plan upgrades, and the API allows remote instances to check for updates automatically.

4. **Manage client relationships** -- Add your institutional clients. This creates cross-references that benefit both parties' profiles.

5. **Use the Call Log** -- The CRM-style call and issue log helps you track interactions, follow-ups, and support tickets. Set follow-up dates to avoid overdue items.

6. **Add multiple vendor types** -- The vendor type field supports multi-select. If you provide both development and hosting services, select both.

7. **Link your repositories** -- Provide git repository URLs for your software. This demonstrates transparency and makes it easy for institutions to evaluate your code.

### For Community Organizers

1. **Keep meetings updated** -- Always update the next meeting date and details. Members rely on this information.

2. **Pin important discussions** -- Use the pin feature for announcements and important threads.

3. **Set focus areas** -- Define clear focus areas for your group so potential members can quickly understand the group's purpose.

4. **Use email notifications wisely** -- The group email feature lets you send messages to all members. Use it sparingly for important announcements.

### For Administrators

1. **Process pending verifications promptly** -- The dashboard shows pending counts. Regular verification builds community trust.

2. **Monitor the sync dashboard** -- Check for instances that have gone offline (missed heartbeats). This may indicate issues at those institutions.

3. **Configure SMTP early** -- Set up SMTP email settings before enabling discussion notifications and newsletters. Without SMTP, the system falls back to PHP `mail()` which may not work on all servers.

4. **Review blog posts before publishing** -- The `blog_require_approval` setting (default: on) ensures non-admin blog posts are reviewed before going live.

5. **Use the footer editor** -- Customize the footer to include relevant links for your specific deployment. The footer appears on all registry pages.

6. **Back up regularly** -- The registry stores significant community data. Ensure your database backup strategy includes all `registry_*` tables.

---

## Database Tables Reference

For technical reference, the Registry uses the following database tables:

| Table | Purpose |
|-------|---------|
| `registry_institution` | Institution profiles (name, type, address, collection info, verification) |
| `registry_vendor` | Vendor profiles (name, type, services, ratings) |
| `registry_contact` | Contact persons for institutions and vendors |
| `registry_instance` | AtoM/Heratio instances (URL, version, hosting, sync) |
| `registry_software` | Software products (name, category, license, git, pricing) |
| `registry_software_release` | Version history for software products |
| `registry_software_component` | Plugins/modules within a software product |
| `registry_vendor_institution` | Vendor-institution relationships |
| `registry_institution_software` | Institution-software associations |
| `registry_review` | Reviews and ratings for vendors and software |
| `registry_user_group` | User groups (name, type, meeting info, links) |
| `registry_user_group_member` | Group membership (email, role, notification preference) |
| `registry_discussion` | Discussion threads within groups |
| `registry_discussion_reply` | Threaded replies to discussions |
| `registry_blog_post` | Blog/news articles |
| `registry_tag` | Keyword tags for institutions, vendors, software |
| `registry_attachment` | File attachments across entity types |
| `registry_sync_log` | Sync API event log |
| `registry_settings` | Registry configuration key-value store |
| `registry_favorite` | User bookmarks for entities |
| `registry_oauth_account` | OAuth social login accounts |
| `registry_instance_feature` | Feature usage tracking per instance |
| `registry_newsletter` | Newsletter content and send tracking |
| `registry_newsletter_subscriber` | Newsletter subscriber list |
| `registry_newsletter_send_log` | Per-subscriber send tracking |

---

## URL Reference

### Public Routes

| URL | Description |
|-----|-------------|
| `/registry` | Home page |
| `/registry/institutions` | Institution directory |
| `/registry/institutions/:slug` | Institution detail |
| `/registry/vendors` | Vendor directory |
| `/registry/vendors/:slug` | Vendor detail |
| `/registry/software` | Software catalogue |
| `/registry/software/:slug` | Software detail |
| `/registry/software/:slug/releases` | Software release history |
| `/registry/instances/:id` | Instance detail |
| `/registry/search?q=...` | Unified search |
| `/registry/map` | Interactive map |
| `/registry/community` | Community hub |
| `/registry/groups` | User groups directory |
| `/registry/groups/:slug` | Group detail |
| `/registry/groups/:slug/discussions` | Group discussions |
| `/registry/groups/:slug/discussions/new` | Start a new discussion |
| `/registry/groups/:slug/discussions/:id` | Discussion thread |
| `/registry/groups/:slug/members` | Group member list |
| `/registry/groups/:slug/join` | Join a group |
| `/registry/groups/:slug/leave` | Leave a group |
| `/registry/blog` | Blog listing |
| `/registry/blog/:slug` | Blog post |
| `/registry/newsletters` | Newsletter archive |
| `/registry/newsletters/:id` | Newsletter view |
| `/registry/newsletter/subscribe` | Subscribe to newsletter |
| `/registry/newsletter/unsubscribe` | Unsubscribe from newsletter |
| `/registry/login` | Login page |
| `/registry/register` | Registration page |

### Self-Service Routes (Requires Login)

| URL | Description |
|-----|-------------|
| `/registry/my/institution` | Institution dashboard |
| `/registry/my/institution/register` | Register institution |
| `/registry/my/institution/edit` | Edit institution |
| `/registry/my/institution/contacts` | Manage contacts |
| `/registry/my/institution/contacts/add` | Add contact |
| `/registry/my/institution/contacts/:id/edit` | Edit contact |
| `/registry/my/institution/instances` | Manage instances |
| `/registry/my/institution/instances/add` | Add instance |
| `/registry/my/institution/instances/:id/edit` | Edit instance |
| `/registry/my/institution/software` | Software in use |
| `/registry/my/institution/vendors` | Vendor relationships |
| `/registry/my/institution/review/:type/:id` | Leave a review |
| `/registry/my/vendor` | Vendor dashboard |
| `/registry/my/vendor/register` | Register as vendor |
| `/registry/my/vendor/edit` | Edit vendor profile |
| `/registry/my/vendor/contacts` | Manage vendor contacts |
| `/registry/my/vendor/contacts/add` | Add vendor contact |
| `/registry/my/vendor/contacts/:id/edit` | Edit vendor contact |
| `/registry/my/vendor/clients` | Manage clients |
| `/registry/my/vendor/clients/add` | Add client relationship |
| `/registry/my/vendor/software` | Manage software products |
| `/registry/my/vendor/software/add` | Register software |
| `/registry/my/vendor/software/:id/edit` | Edit software |
| `/registry/my/vendor/software/:id/releases` | Manage releases |
| `/registry/my/vendor/software/:id/releases/add` | Add release |
| `/registry/my/vendor/software/:id/upload` | Upload software package |
| `/registry/my/vendor/call-log` | Call & issue log |
| `/registry/my/vendor/call-log/add` | New log entry |
| `/registry/my/vendor/call-log/:id` | View log entry |
| `/registry/my/vendor/call-log/:id/edit` | Edit log entry |
| `/registry/my/groups` | My groups |
| `/registry/my/groups/create` | Create a group |
| `/registry/my/groups/:id/edit` | Edit group |
| `/registry/my/groups/:id/members` | Manage group members |
| `/registry/my/blog` | My blog posts |
| `/registry/my/blog/new` | Write a blog post |
| `/registry/my/blog/:id/edit` | Edit blog post |
| `/registry/my/favorites` | My favorites |

### Admin Routes (Requires Administrator)

| URL | Description |
|-----|-------------|
| `/registry/admin` | Admin dashboard |
| `/registry/admin/institutions` | Manage institutions |
| `/registry/admin/institutions/:id/edit` | Edit any institution |
| `/registry/admin/vendors` | Manage vendors |
| `/registry/admin/vendors/:id/edit` | Edit any vendor |
| `/registry/admin/software` | Manage software |
| `/registry/admin/software/:id/edit` | Edit any software |
| `/registry/admin/groups` | Manage groups |
| `/registry/admin/groups/:id/edit` | Edit any group |
| `/registry/admin/groups/:id/members` | Manage group members |
| `/registry/admin/discussions` | Moderate discussions |
| `/registry/admin/blog` | Moderate blog posts |
| `/registry/admin/reviews` | Moderate reviews |
| `/registry/admin/sync` | Sync dashboard |
| `/registry/admin/settings` | Registry settings |
| `/registry/admin/footer` | Footer settings |
| `/registry/admin/email` | Email/SMTP settings |
| `/registry/admin/import` | Data import |
| `/registry/admin/users` | User approval |
| `/registry/admin/newsletters` | Newsletter management |
| `/registry/admin/newsletters/new` | Create newsletter |
| `/registry/admin/newsletters/:id/edit` | Edit newsletter |
| `/registry/admin/subscribers` | Subscriber management |

### API Routes

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/registry/api/sync/register` | Register remote instance |
| POST | `/registry/api/sync/heartbeat` | Send heartbeat |
| POST | `/registry/api/sync/update` | Update metadata |
| GET | `/registry/api/sync/status?sync_token=...` | Check sync status |
| GET | `/registry/api/directory` | Public institution directory (JSON) |
| GET | `/registry/api/software/:slug/latest` | Latest software version (JSON) |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Cannot register | Check that `allow_self_registration` is enabled in admin settings |
| Account not active | Ask an administrator to approve your account at `/registry/admin/users` |
| Institution not appearing in directory | Check that the institution is active and not suspended. If just registered, it may need admin verification. |
| Map not showing institutions | Ensure institutions have latitude and longitude values set |
| Heartbeat not working | Verify the sync token is correct and sync is enabled on the instance |
| Instance showing as offline | Check that heartbeats are being sent within the configured threshold (`heartbeat_offline_threshold_days`) |
| Email notifications not sending | Verify SMTP settings at `/registry/admin/email` and use the test email feature |
| OAuth login not working | Ensure the OAuth provider is enabled and client ID/secret are configured in admin settings |
| Software upload failing | Check `max_upload_size_mb` and `allowed_upload_extensions` in settings. Also check PHP `upload_max_filesize` and `post_max_size` in `php.ini`. |
| Reviews not visible | Admin may have hidden the review. Check at `/registry/admin/reviews` |
| Discussion locked | The discussion has been locked by an admin or organizer. New replies cannot be posted. |
| Blog post not published | Blog posts from non-admins require approval when `blog_require_approval` is enabled. Ask an admin to publish at `/registry/admin/blog`. |

---

*This guide covers the ahgRegistryPlugin v1.0.0 for Heratio v2.8+. For technical documentation, see the [technical manual](technical/). For questions, contact The Archive and Heritage Group at johan@theahg.co.za.*

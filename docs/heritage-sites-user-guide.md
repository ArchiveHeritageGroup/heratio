# Heritage Discovery Platform

## User Guide

A comprehensive heritage discovery platform that provides an engaging public landing page, community contributions, custodian management, access mediation, and analytics.

---

## Overview
```
+---------------------------------------------------------------------+
|                   HERITAGE DISCOVERY PLATFORM                        |
+---------------------------------------------------------------------+
|                                                                     |
|   PUBLIC DISCOVERY          CONTRIBUTIONS         ADMINISTRATION    |
|        |                         |                      |           |
|        v                         v                      v           |
|   Landing Page             Community              Custodian         |
|   Search/Browse            Participation          Management        |
|   Collections              Trust System           Analytics         |
|   Timeline                 Review Queue           Access Control    |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Key Features
```
+---------------------------------------------------------------------+
|                       PLATFORM CAPABILITIES                          |
+---------------------------------------------------------------------+
|  Landing Page     - Rijksstudio-inspired visual discovery           |
|  Search           - Intelligent search with autocomplete            |
|  Contributions    - Community transcription and identification      |
|  Access Control   - Embargo and access request management           |
|  Custodian Tools  - Batch operations and audit trail                |
|  Analytics        - Search insights and usage tracking              |
|  POPIA Compliance - Privacy flag management                         |
+---------------------------------------------------------------------+
```

---

## Public Discovery Interface

### Landing Page

The heritage landing page provides a visually engaging entry point to your collections.

```
+---------------------------------------------------------------------+
|                        LANDING PAGE SECTIONS                         |
+---------------------------------------------------------------------+
|                                                                     |
|   +----------------------------------------------------------+     |
|   |                    HERO SECTION                          |     |
|   |  Full-viewport background with Ken Burns effect          |     |
|   |  Tagline, search box, suggested searches                 |     |
|   +----------------------------------------------------------+     |
|                                                                     |
|   +----------------------------------------------------------+     |
|   |                    EXPLORE BY                            |     |
|   |  Time | Place | People | Theme | Format | Trending       |     |
|   +----------------------------------------------------------+     |
|                                                                     |
|   +----------------------------------------------------------+     |
|   |               CURATED COLLECTIONS                        |     |
|   |  Auto-rotating carousel of featured collections          |     |
|   +----------------------------------------------------------+     |
|                                                                     |
|   +----------------------------------------------------------+     |
|   |                BROWSE BY CREATOR                         |     |
|   |  Top creators with item counts                           |     |
|   +----------------------------------------------------------+     |
|                                                                     |
|   +----------------------------------------------------------+     |
|   |                 TIMELINE                                 |     |
|   |  Interactive historical period navigation                |     |
|   +----------------------------------------------------------+     |
|                                                                     |
|   +----------------------------------------------------------+     |
|   |                RECENTLY ADDED                            |     |
|   |  Masonry grid of recent items with images                |     |
|   +----------------------------------------------------------+     |
|                                                                     |
|   +----------------------------------------------------------+     |
|   |               HELP US IMPROVE                            |     |
|   |  Contribution call-to-action with leaderboard            |     |
|   +----------------------------------------------------------+     |
|                                                                     |
+---------------------------------------------------------------------+
```

### How to Access

Navigate to: `/heritage` or visit the homepage when not logged in (auto-redirect).

### Search Features

The heritage search provides intelligent discovery:

```
+---------------------------------------------------------------------+
|                      SEARCH CAPABILITIES                             |
+---------------------------------------------------------------------+
|  Autocomplete     - Suggestions as you type                         |
|  Query Expansion  - Synonyms and related terms                      |
|  Multi-language   - Language detection                              |
|  Filters          - Format, time, place, subject, creator           |
|  Results Ranking  - Relevance + quality + engagement scoring        |
+---------------------------------------------------------------------+
```

---

## Explore Categories

### Browse by Time (Timeline)

Navigate collections through historical periods:

```
+---------------------------------------------------------------------+
|                        TIMELINE PERIODS                              |
+---------------------------------------------------------------------+
|                                                                     |
|  Pre-Colonial -----> Dutch Colonial -----> British -----> Union    |
|     Pre-1652           1652-1795          1795-1910     1910-1948  |
|                                                                     |
|                  -----> Apartheid -----> Democratic Era             |
|                          1948-1994        1994-Present              |
|                                                                     |
+---------------------------------------------------------------------+
```

### Browse by Place

Explore collections by geographic location with map integration.

### Browse by People

Discover records connected to specific individuals and organizations:

1. Navigate to **Heritage** > **Creators**
2. Browse alphabetically or by item count
3. Click a creator to see related records

### Browse by Theme

Filter collections by subject matter and topics.

### Browse by Format

Filter by content type (photographs, documents, maps, etc.).

### Trending

View popular items based on recent user engagement.

---

## Community Contributions

### Overview

The heritage platform enables community participation through moderated contributions.

```
+---------------------------------------------------------------------+
|                    CONTRIBUTION TYPES                                |
+---------------------------------------------------------------------+
|                                                                     |
|  Transcription     - Convert handwritten documents to text          |
|                      Points: 25 per contribution                    |
|                                                                     |
|  Identification    - Identify people, places, objects               |
|                      Points: 15 per contribution                    |
|                                                                     |
|  Historical        - Add context and background information         |
|  Context             Points: 20 per contribution                    |
|                                                                     |
|  Correction        - Suggest corrections to metadata                |
|                      Points: 10 per contribution                    |
|                                                                     |
|  Translation       - Translate content to other languages           |
|                      Points: 30 per contribution                    |
|                                                                     |
|  Tags/Keywords     - Add searchable keywords                        |
|                      Points: 5 per contribution                     |
|                                                                     |
+---------------------------------------------------------------------+
```

### Contributor Registration

1. Navigate to **Heritage** > **Login** or click "Sign In" on the landing page
2. Click **Create Account**
3. Fill in:
   - Email address
   - Display name
   - Password
4. Verify your email via the confirmation link
5. Start contributing!

### Making a Contribution

1. Find an item you want to contribute to
2. Click the **Contribute** button
3. Select contribution type (transcription, identification, etc.)
4. Fill in the contribution form
5. Click **Submit Contribution**
6. Wait for review and approval

```
+---------------------------------------------------------------------+
|                   CONTRIBUTION WORKFLOW                              |
+---------------------------------------------------------------------+
|                                                                     |
|   Contributor         Custodian/Admin         System                |
|       |                     |                    |                  |
|       | Submit              |                    |                  |
|       |-------------------->|                    |                  |
|       |                     | Review             |                  |
|       |                     |                    |                  |
|       |                     | Approve/Reject     |                  |
|       |                     |------------------->|                  |
|       |                     |                    | Award Points     |
|       |<-----------------------------------------|                  |
|       |                     |                    | Update Record    |
|       |                     |                    |                  |
+---------------------------------------------------------------------+
```

### Trust Levels

Contributors earn trust through consistent quality contributions:

```
+---------------------------------------------------------------------+
|                      TRUST LEVELS                                    |
+---------------------------------------------------------------------+
|                                                                     |
|  Level 0: New           - Just registered, limited permissions      |
|  Level 1: Contributor   - Active contributor                        |
|  Level 2: Trusted       - Consistent quality, verified              |
|  Level 3: Expert        - High approval rate, special access        |
|                                                                     |
+---------------------------------------------------------------------+
```

### Badges and Achievements

Earn badges for contribution milestones:

| Badge | Requirement |
|-------|-------------|
| First Steps | First contribution |
| Active Contributor | 10 approved contributions |
| Dedicated Contributor | 50 approved contributions |
| Heritage Champion | 100 approved contributions |
| Transcription Expert | 25 transcriptions |
| Sharp Eye | 25 identifications |
| Local Historian | 25 context additions |
| High Quality | 95% approval rate on 20+ contributions |

### Leaderboard

View top contributors on the landing page and at **Heritage** > **Leaderboard**.

---

## Access Requests

### Why Request Access?

Some items may have restricted access due to:
- Privacy concerns (POPIA/GDPR)
- Copyright restrictions
- Cultural sensitivity
- Embargo periods
- Security classification

### Submitting an Access Request

1. Navigate to the restricted item
2. Click **Request Access**
3. Select your purpose:
   - Personal/Family Research
   - Academic Research
   - Educational Use
   - Commercial Use (requires approval)
   - Media/Journalism (requires approval)
   - Legal/Compliance (requires approval)
   - Government/Official (requires approval)
   - Preservation/Conservation
4. Provide institution affiliation (if applicable)
5. Describe your research project
6. Justify why you need access
7. Agree to terms and conditions
8. Submit request

### Request Status

Track your requests at **Heritage** > **My Access Requests**:

```
+---------------------------------------------------------------------+
|                    REQUEST STATUS                                    |
+---------------------------------------------------------------------+
|  Pending     - Awaiting review                                      |
|  Approved    - Access granted (check valid dates)                   |
|  Denied      - Access not granted (see notes)                       |
|  Expired     - Access period ended                                  |
|  Withdrawn   - Request cancelled by user                            |
+---------------------------------------------------------------------+
```

---

## Viewing Your Activity

### My Contributions

Navigate to **Heritage** > **My Contributions** to view:
- Pending contributions
- Approved contributions
- Rejected contributions (with feedback)
- Points earned
- Badges awarded

### My Access Requests

Navigate to **Heritage** > **My Access Requests** to view:
- Request status
- Approval/denial notes
- Access validity period

---

## Administration

### Admin Dashboard

Access at **Heritage** > **Admin** (requires admin privileges)

```
+---------------------------------------------------------------------+
|                    ADMIN DASHBOARD                                   |
+---------------------------------------------------------------------+
|                                                                     |
|  Quick Stats:                                                       |
|  - Total Users                                                      |
|  - Active Users                                                     |
|  - New This Month                                                   |
|  - Active Alerts                                                    |
|                                                                     |
|  Quick Actions:                                                     |
|  - Review Access Requests                                           |
|  - Create Batch Operation                                           |
|  - View System Alerts                                               |
|  - Preview Landing Page                                             |
|                                                                     |
+---------------------------------------------------------------------+
```

### Landing Page Configuration

Configure at **Heritage** > **Admin** > **Landing Config**:

| Setting | Description |
|---------|-------------|
| Hero Tagline | Main headline on landing page |
| Hero Subtext | Supporting text below tagline |
| Search Placeholder | Placeholder text in search box |
| Suggested Searches | Quick search suggestions |
| Primary Color | Theme color for the platform |
| Show Sections | Toggle visibility of landing page sections |

### Hero Slides

Manage background images at **Heritage** > **Admin** > **Hero Slides**:

- Upload hero images
- Set captions and credits
- Configure Ken Burns effect
- Set rotation timing
- Enable/disable slides

### Featured Collections

Curate collections at **Heritage** > **Admin** > **Featured Collections**:

- Select IIIF or archival collections
- Override titles and descriptions
- Set display order
- Enable/disable collections

### Feature Toggles

Enable/disable features at **Heritage** > **Admin** > **Features**:

| Feature | Default | Description |
|---------|---------|-------------|
| Community Contributions | Enabled | Allow public contributions |
| User Registration | Enabled | Allow new registrations |
| Social Sharing | Enabled | Enable share buttons |
| Downloads | Enabled | Allow file downloads |
| Citations | Enabled | Show citation options |
| Analytics | Enabled | Track usage data |
| Access Requests | Enabled | Allow access requests |
| Embargoes | Enabled | Enable embargo system |
| Batch Operations | Enabled | Enable bulk updates |
| Audit Trail | Enabled | Track all changes |

### Branding

Customize appearance at **Heritage** > **Admin** > **Branding**:

- Logo upload
- Favicon
- Primary/secondary colors
- Banner text
- Footer text
- Custom CSS
- Social links
- Contact information

---

## Access Control Management

### Access Requests Review

Manage at **Heritage** > **Admin** > **Access Requests**:

1. View pending requests
2. Review justification and purpose
3. Approve or deny with notes
4. Set access validity period

### Embargo Management

Manage at **Heritage** > **Admin** > **Embargoes**:

| Embargo Type | Effect |
|--------------|--------|
| Full | Record completely hidden |
| Digital Only | Metadata visible, files hidden |
| Metadata Hidden | Files visible, details hidden |

Configure:
- Start and end dates
- Auto-release option
- Notification preferences
- Legal basis

### POPIA Flags

Manage privacy concerns at **Heritage** > **Admin** > **POPIA Flags**:

Flag types:
- Personal Information
- Sensitive Data
- Children's Data
- Health Information
- Biometric Data
- Criminal Records
- Financial Information
- Political Affiliation
- Religious Beliefs
- Sexual Orientation

---

## Custodian Tools

### Custodian Dashboard

Access at **Heritage** > **Custodian**:

```
+---------------------------------------------------------------------+
|                   CUSTODIAN DASHBOARD                                |
+---------------------------------------------------------------------+
|                                                                     |
|  Quick Actions:                                                     |
|  - Batch Operations                                                 |
|  - Audit Trail                                                      |
|  - Access Requests                                                  |
|                                                                     |
|  Activity Summary (30 Days):                                        |
|  - Actions by category                                              |
|  - Top contributors                                                 |
|                                                                     |
|  Recent Activity:                                                   |
|  - Latest changes with timestamps                                   |
|                                                                     |
+---------------------------------------------------------------------+
```

### Batch Operations

Perform bulk updates at **Heritage** > **Custodian** > **Batch**:

- Select items by search or list
- Choose operation type
- Preview changes
- Execute with progress tracking
- Review results

### Audit Trail

View change history at **Heritage** > **Custodian** > **History**:

- Filter by user, date, action type
- View field-level changes
- Export audit logs

### Contribution Review

Review community contributions at **Heritage** > **Review**:

1. View pending contributions
2. Compare with original record
3. Approve with optional edits
4. Reject with feedback
5. Award points automatically

---

## Analytics

### Analytics Dashboard

Access at **Heritage** > **Analytics**:

```
+---------------------------------------------------------------------+
|                   ANALYTICS OVERVIEW                                 |
+---------------------------------------------------------------------+
|                                                                     |
|  Overview Stats:                                                    |
|  - Page Views                                                       |
|  - Searches                                                         |
|  - Downloads                                                        |
|  - Unique Visitors                                                  |
|                                                                     |
|  Search Performance:                                                |
|  - Average Results                                                  |
|  - Zero Result Rate                                                 |
|  - Click-through Rate                                               |
|                                                                     |
|  Access Control:                                                    |
|  - Pending Requests                                                 |
|  - Approval Rate                                                    |
|  - POPIA Flags                                                      |
|                                                                     |
|  Trends Chart:                                                      |
|  - Search and click trends over time                                |
|                                                                     |
+---------------------------------------------------------------------+
```

### Search Insights

Access at **Heritage** > **Analytics** > **Search**:

- Top search queries
- Zero-result searches (content gaps)
- Failed searches
- Search term relationships
- Autocomplete effectiveness

### Content Analytics

Access at **Heritage** > **Analytics** > **Content**:

- Most viewed items
- Download statistics
- Engagement metrics
- Quality scores

### Alerts

View system alerts at **Heritage** > **Analytics** > **Alerts**:

Alert categories:
- Content issues
- Search problems
- Access concerns
- Quality warnings
- System notifications
- Opportunities

---

## URL Routes Reference

| Route | Purpose |
|-------|---------|
| `/heritage` | Public landing page |
| `/heritage/search` | Search interface |
| `/heritage/explore` | Browse categories |
| `/heritage/timeline` | Timeline navigation |
| `/heritage/creators` | Browse by creator |
| `/heritage/collections` | Featured collections |
| `/heritage/trending` | Trending items |
| `/heritage/login` | Contributor login |
| `/heritage/register` | Contributor registration |
| `/heritage/contribute/:slug` | Contribution form |
| `/heritage/my/contributions` | My contributions |
| `/heritage/my/access-requests` | My access requests |
| `/heritage/leaderboard` | Contributor leaderboard |
| `/heritage/access/request/:slug` | Request access form |
| `/heritage/admin` | Admin dashboard |
| `/heritage/custodian` | Custodian dashboard |
| `/heritage/analytics` | Analytics dashboard |
| `/heritage/review` | Contribution review queue |

---

## Tips for Users
```
+----------------------------------+----------------------------------+
|  DO                              |  DON'T                           |
+----------------------------------+----------------------------------+
|  Explore different browse modes  |  Ignore suggested searches       |
|  Contribute what you know        |  Submit low-quality content      |
|  Provide detailed justification  |  Request access without reason   |
|  Check your contribution status  |  Expect instant approval         |
|  Earn trust through quality      |  Try to game the points system   |
+----------------------------------+----------------------------------+
```

---

## Tips for Administrators
```
+----------------------------------+----------------------------------+
|  DO                              |  DON'T                           |
+----------------------------------+----------------------------------+
|  Review requests promptly        |  Let requests pile up            |
|  Configure landing page branding |  Use default settings            |
|  Monitor zero-result searches    |  Ignore analytics insights       |
|  Curate featured collections     |  Leave collections static        |
|  Address POPIA flags quickly     |  Ignore privacy concerns         |
+----------------------------------+----------------------------------+
```

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the AtoM AHG Framework*

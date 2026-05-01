# ahgRepositoryManagePlugin - Archival Institutions (ISDIAH)

The **ahgRepositoryManagePlugin** manages **archival institutions** - the museums, archives, libraries, galleries, or DAM operations that hold the material described in Heratio. Each institution is described per **ISDIAH** (International Standard for Describing Institutions with Archival Holdings) and acts as the parent for the records it custodies.

---

## What a repository is in Heratio

A `repository` row is a specialised `actor` row (class-table inheritance - see `ahgActorManagePlugin`). It carries the ISAAR(CPF) authority fields *plus* an additional ISDIAH-specific field set: location, contact details, opening hours, accessibility, services, etc.

Every information_object can be tagged with a `repository_id` indicating which institution owns it. This drives:

- the **Repository facet** on `/glam/browse`
- the **isPartOf** relationship in OAI-PMH harvests and RiC-O exports
- per-repository CSV bulk operations
- per-repository themed sub-sites (when `ahgMultiTenantPlugin` is enabled)

---

## Where it lives

| Surface | URL |
| --- | --- |
| Browse | `/repository/browse` |
| Show | `/repository/<slug>` |
| Add | `/repository/add` |
| Edit | `/repository/<slug>/edit` |
| Delete | `/repository/<slug>/delete` |
| Holdings (records owned by this repo) | `/repository/<slug>/holdings` |

---

## ISDIAH field set

The edit form covers all six ISDIAH areas:

- **5.1 Identity** - identifier, authorized form of name, parallel forms, other forms, type
- **5.2 Contact** - physical address(es), telephone, fax, email, web, contact person
- **5.3 Description** - history, geographical and cultural context, mandates/sources of authority, administrative structure, records management and collecting policies, building, archival and other holdings, finding aids/guides/publications
- **5.4 Access** - opening hours, conditions and requirements for access and use, accessibility (disabled access, public transport)
- **5.5 Services** - research services, reproduction services, public areas
- **5.6 Control** - description identifier, institution responsible, rules, status, level of detail, dates, language, sources, maintenance notes

Multilingual: every text field has per-culture rows in `actor_i18n` + `repository_i18n`.

---

## Common workflows

### Set up a new institution

1. `/repository/add` - log in as admin (only admins create repositories).
2. Fill the Identity area (authorized form of name, type - National / Regional / Private / Specialised).
3. Fill Contact (street address, phone, web). The web field powers the **Visit website** link on the public show page.
4. Save. The institution gets a slug like `national-archive-of-bechuanaland`.

### Attribute records to the institution

Three ways:

**a. At ingest:** include a `repository` column in your CSV; the wizard resolves it by slug or by ISDIAH identifier.
**b. Per-record edit:** open the IO edit page, set the **Archival institution** dropdown, save. Setting at the fonds level cascades to descendants (when "inherit to children" is enabled in repository settings).
**c. Bulk reassign:** `/admin/repository/bulk-reassign` lets an admin move all of repository A's holdings to repository B in one operation (e.g. when two institutions merge).

### Holdings page

`/repository/<slug>/holdings` shows every IO whose `repository_id` matches the current institution. It's the public browse surface scoped to that institution. Useful link from the institution's public website.

### Per-institution branding

When `ahgMultiTenantPlugin` is enabled, a repository can carry its own theme/logo/footer. Configured at `/admin/tenants` (admin-only). Visiting `/repository/<slug>` then uses the institution's palette and logo, even if the rest of the site uses the platform-wide one.

---

## Settings

The plugin has no top-level settings page of its own - it inherits from `ahgActorManagePlugin` (because repository extends actor). Authority-record settings (completeness, NER, merge thresholds) at `/admin/settings/ahg/authority` apply to repositories as well.

For the optional per-tenant branding, settings are at `/admin/tenants/<id>/branding`.

---

## Permissions

| Action | Required role |
| --- | --- |
| Browse, view | Anonymous |
| Add, edit | Admin (`acl:create`, `acl:update`) |
| Delete | Admin |
| Bulk reassign holdings | Admin |
| Edit per-tenant branding | Admin |

By design, repositories are admin-managed - they're foundational records, and accidental edits cascade to thousands of descendants.

---

## Common gotchas

- **Don't delete a repository that has holdings.** The delete flow refuses if any IO has `repository_id = <this>`. Reassign first via `/admin/repository/bulk-reassign`.
- **Orphan holdings** can happen if you `mysql DELETE`-d a repository directly. The faceted browse will show "Unknown repository" rows. Run `php artisan ahg:fix-orphan-repository` to surface them and assign to a placeholder institution.
- **Class-table inheritance:** an actor row exists for every repository (same `id`). Don't try to insert a repository row without first creating its parent actor row - the FK will reject.
- **Authority-record archival institution identifier** (ISDIAH 5.6.1) is **not the same** as `actor.identifier`. ISDIAH expects an ISAAR-style code like `ZA-AHG-001`; populate it on the **edit form** under "Description identifier" (it's stored separately from the ISAAR equivalent).

---

## Related

- **`ahgActorManagePlugin`** - the parent class (people, families, corporate bodies). Repositories share its data model.
- **`ahgDonorManagePlugin`** - donors are also actors; donors → repositories via accessions.
- **`ahgInformationObjectManagePlugin`** - the records that *belong to* repositories.
- **`ahgMultiTenantPlugin`** - if enabled, lets each repository carry its own branding.
- **Help articles**: *Multi-tenancy*, *AHG Authority Records - User Guide*

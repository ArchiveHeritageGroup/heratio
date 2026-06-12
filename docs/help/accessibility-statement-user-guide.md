# Accessibility statement (public)

The outward, public, human-readable accessibility statement that your service
publishes at **/accessibility-statement**. It is the standard conformance
statement that visitors, auditors, and procurement teams expect every public
digital service to carry.

## What this page is (and is not)

The public page at **/accessibility-statement** follows the
[W3C model accessibility statement](https://www.w3.org/WAI/planning/statements/)
structure. It is your institution's **commitment**, its **conformance claim**
against WCAG, an honest list of **known limitations**, and the **channel to
report a barrier**.

It is **distinct** from the two internal admin tools:

- **Admin -> Digital accessibility** (`/admin/accessibility`) is a heuristic
  *coverage report* over your metadata. It measures how much published content
  carries each accessibility signal.
- **Admin -> Alt text** (`/admin/alt-text`) is the *curation worklist* where
  cataloguers author real text alternatives for images.

The public statement is the outward face of that internal work. The page is
**read-only**, never writes to the database, and is designed never to error: if
a setting is missing it falls back to a neutral default, and if anything fails
it renders an all-defaults statement rather than a 500.

## Where it lives

It is a **public** page (no login). The address is a single path segment,
`/accessibility-statement`, registered so it is matched before the archival
record catch-all route. A normal record still resolves at its own slug.

You can link to it from your footer, your contact page, or any "Accessibility"
menu item using the route name `accessibility.statement`.

## The sections (W3C model)

1. **Our commitment** - a plain statement that the collection should be usable by
   as many people as possible.
2. **Conformance status** - the platform is assessed against **WCAG 2.2** (the
   version is configurable), with a configurable conformance label that defaults
   to *"Partially conformant, level AA targeted"*. WCAG is named as the
   internationally recognised baseline, and **EN 301 549** is named as **one**
   recognised harmonised standard that references WCAG - explicitly as an
   example, not as the sole or governing legal regime. The statement is
   international and jurisdiction-neutral.
3. **What is accessible** - the accessibility features that are actually wired in:
   keyboard navigation, semantic structure, curated image alt text, read-a-record
   in-your-language, and read-aloud. Each feature only appears when its underlying
   capability is installed, so the statement never over-claims.
4. **Known limitations** - stated honestly as gaps, never hidden: legacy scanned
   material without full text, third-party deep-zoom and 3D viewers, user-
   contributed content, and older untagged documents.
5. **Report an accessibility barrier** - a contact email plus an optional feedback
   form link, and the target response time in working days.
6. **Preparation of this statement** - the date it was prepared and last reviewed.

## Configuring it (no code, no new table)

All configurable text is read from the existing **`ahg_settings`** table. Set any
of these keys (Admin -> AHG Settings, or the Dropdown / settings store) to override
the neutral defaults. Nothing here is required - the page works unconfigured.

| Setting key | Purpose | Default if unset |
|---|---|---|
| `accessibility_institution_name` | Your institution / service name | "This institution" |
| `accessibility_contact_email` | Barrier-reporting email | `accessibility@your-site.example` |
| `accessibility_contact_url` | Optional feedback-form / contact-page URL | (hidden when blank) |
| `accessibility_conformance_level` | Conformance claim label | "Partially conformant, level AA targeted" |
| `accessibility_wcag_version` | WCAG version targeted | "2.2" |
| `accessibility_statement_date` | Preparation / last-reviewed date (free text) | the deploy date (never a fabricated legal date) |
| `accessibility_response_days` | Target response time in working days (1-30) | 10 |

The preparation date deliberately falls back to the **deploy date** rather than
inventing a legal date. Set `accessibility_statement_date` to your own reviewed
date once your accessibility team has signed it off.

## Keeping it honest

The "What is accessible" list is feature-gated, and the "Known limitations" list
is shipped as real gaps, not marketing. When you close a limitation (for example,
once a class of legacy documents is reprocessed), update the statement and the
preparation date so the public record stays truthful.

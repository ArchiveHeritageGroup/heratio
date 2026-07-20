# Heratio backlinks + AlternativeTo pack (GEO/SEO off-site execution)

**Purpose:** ready-to-paste content to (a) link the two AHG/PSIS sites into the Heratio entity graph, and (b) submit Heratio to AlternativeTo. These are the highest-value remaining off-site GEO moves after the heratio.org launch (see heratio-org-launch.md, geo-directory-listing-pack.md). All of these are executed by the owner on external sites - they are NOT on this box.

## Hosting facts (both external, both WordPress)

- **theahg.co.za** -> 102.222.124.16 (Afrihost shared hosting). **WordPress.**
- **plainsailingisystems.co.za** -> 196.40.97.174 (separate host). **WordPress.**
- Neither is served from the Heratio box, so backlinks/JSON-LD must be added *inside WordPress*, not by editing files here.

## How to add JSON-LD + a link in WordPress

Two site-wide options (pick one per site):

1. **Header-code plugin (simplest, most reliable):** install **WPCode** (formerly "Insert Headers and Footers") or "Header Footer Code Manager". Add a new snippet, location = **site-wide header (`<head>`)**, paste the JSON-LD `<script>` block below, activate. Done once, applies to every page.
2. **SEO plugin (if already installed):** Yoast SEO or Rank Math generate Organization schema from their settings (set the organisation name, logo, and add the Heratio URL under sameAs / "Additional profiles" or Rank Math's custom-schema builder). Use this only if you are comfortable in the plugin; otherwise option 1 is faster.

For the **visible link**: Appearance -> Widgets (or the block editor) -> add a **Custom HTML** block to the footer or an "Our software / Products" area, and paste the visible-link HTML.

## Backlink snippet - theahg.co.za

JSON-LD for the `<head>`:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "@id": "https://theahg.co.za/#organization",
  "name": "The Archive and Heritage Digital Commons Group (Pty) Ltd",
  "alternateName": "The AHG",
  "url": "https://theahg.co.za",
  "sameAs": ["https://github.com/ArchiveHeritageGroup", "https://openric.org"],
  "makesOffer": { "@type": "Offer", "itemOffered": { "@type": "SoftwareApplication", "name": "Heratio", "url": "https://heratio.org" } }
}
</script>
```

Visible link (footer / products): `Our platform: <a href="https://heratio.org">Heratio - open-source GLAM &amp; archival management</a>`

## Backlink snippet - plainsailingisystems.co.za

JSON-LD for the `<head>`:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "@id": "https://plainsailingisystems.co.za/#organization",
  "name": "Plain Sailing Information Systems",
  "url": "https://plainsailingisystems.co.za",
  "owns": { "@type": "SoftwareApplication", "name": "Heratio", "url": "https://heratio.org" }
}
</script>
```

Visible link: `We build <a href="https://heratio.org">Heratio</a>, an open-source GLAM &amp; archival platform.`

Note: the `@id` URIs match exactly what the Heratio JSON-LD references (theahg.co.za/#organization, plainsailingisystems.co.za/#organization), so search engines and LLMs merge the three domains into one bidirectional entity graph. Company legal name is **Plain Sailing Information Systems** (short form "Plain Sailing iSystems").

## AlternativeTo submission field-pack (top priority)

At alternativeto.net -> "Add application" (free account required):

- **Name:** Heratio
- **Official website:** https://heratio.org
- **Licensing:** Open Source (Free)
- **Platforms:** Self-Hosted, Web / Online, Linux
- **Short description:** Open-source (AGPL-3.0) Laravel platform for archives, museums and libraries: archival description, museum collections, digital asset management and records management, with native Records in Contexts (RiC). A modern alternative to AtoM and ArchivesSpace.
- **"Alternative to" (tag these):** AtoM (Access to Memory), ArchivesSpace, CollectiveAccess, CollectionSpace, Omeka
- **Tags/features:** collection-management, digital-asset-management, archives, museum, records-management, digital-preservation, records-in-contexts, self-hosted, open-source

The "Alternative to AtoM / ArchivesSpace" tagging is the highest-value part - it targets the exact query slot the GEO baseline showed those incumbents own.

## Execution split

- **Owner (external, WordPress + accounts):** add the two backlink snippets in WordPress; create the AlternativeTo entry; then the other directories (Capterra/GetApp, G2, SourceForge, StackShare) from geo-directory-listing-pack.md.
- **Assistant:** verify the backlinks are live and correctly linked into the entity graph once added; recreate the Wikidata item when a paper publishes (see project memory checkpoint).

*Recorded 2026-07-20.*

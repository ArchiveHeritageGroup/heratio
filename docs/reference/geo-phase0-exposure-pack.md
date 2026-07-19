# Heratio GEO/SEO Phase 0 exposure pack (2026-07-19)

**Summary:** Phase 0 of the Heratio exposure programme fixes "branded invisibility" - the finding that Heratio does not surface even for a search of its own name. Phase 0 covers only assets fully in AHG's control: a product front door with structured data, an llms.txt file, and GitHub repository metadata. Background: geo-seo-visibility-baseline.md.

## Assets produced

1. **llms.txt** - a clean, factual markdown brief for LLM crawlers, served at the domain root (https://heratio.theahg.co.za/llms.txt).
2. **schema.org JSON-LD** - a linked graph of SoftwareApplication + Organization + FAQPage, pasted into the `<head>` of the product page. Validated as well-formed.
3. **GitHub repo metadata** - a rewritten "About" description (under 350 characters), 20 category topics, and a README opening rewrite. The public GitHub repo is the single most-crawled surface LLMs use.
4. **Product front-door copy** - declarative, extractable page copy plus an FAQ, for a dedicated Heratio product page separate from theahg.co.za/services.

## Positioning decisions baked in

- **Lead claims** (proven uncontested by the cold-model test): RiC-native, Laravel-based, and open-source alternative to AtoM / ArchivesSpace.
- **International framing**: jurisdiction-neutral core; compliance regimes (GRAP 103, POPIA, GDPR, IPSAS) presented as pluggable modules, not the core.
- **Open source is a feature**: AGPL-3.0, self-hostable at no licence cost, is called out explicitly because it unlocks the open-source / self-host buyer segment.

## Spectrum wording rule (important)

All public-facing copy uses **"Spectrum-capable"** - a factual statement that the software supports the Spectrum 5.1 procedures. It does NOT use **"Spectrum compliant"** or **"Spectrum Partner"**, which require a commercial licence from Collections Trust (see spectrum-partner-licensing.md and spectrum-partner-outreach-log.md). Each Phase 0 file carries a switch-on note: upgrade the wording only once the Partner licence is signed. Publishing "compliant/Partner" before then would advertise the exact commercial-use gap the licence closes.

## Standards claimed (grounded against the codebase)

ISAD(G), ISAAR(CPF), ISDIAH, Records in Contexts (RiC / RiC-O), Dublin Core, OAIS, PREMIS, OCFL, BagIt, IIIF, and Spectrum-capable (Spectrum 5.1 procedures). Grounded facts: AGPL-3.0-or-later, PHP 8.3, Laravel 12, 116 packages, live at heratio.theahg.co.za.

## Deployment status / next actions

- README rewrite: applied to the repo working tree (docs change).
- GitHub description + topics: applied via `gh repo edit` (run by the owner).
- llms.txt + JSON-LD + product page: pending a decision on whether the canonical Heratio product page lives at heratio.theahg.co.za or a dedicated domain, and pending the page itself being built.

*Recorded 2026-07-19.*

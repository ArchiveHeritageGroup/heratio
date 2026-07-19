# Heratio vs AtoM comparison page (GEO/SEO Phase 1 asset)

**Status:** Content final and verified against the codebase (2026-07-19). Canonical URL
pending a domain decision (see "Publishing / domain" below). This page targets the
"AtoM alternative" / "migrate from AtoM" query slot that the GEO visibility baseline
(geo-seo-visibility-baseline.md) showed ArchivesSpace currently owns.

**Editorial stance:** deliberately fair. It states both products are AGPL-3.0 and
self-hostable (so open source is not claimed as a differentiator), and includes a
"When AtoM is the right choice" section. Fairness is what makes the page rank and get
cited by LLMs rather than dismissed as vendor spin. All Heratio claims were verified
against the code; the only row AtoM legitimately leads (multilingual completeness) is
stated as an AtoM strength and is tracked for improvement in issue #1410.

## At a glance

| | Heratio | AtoM (Access to Memory) |
|---|---|---|
| Framework / stack | Laravel 12, PHP 8.3 | Symfony 1.4, PHP (legacy framework, EOL 2012) |
| Database / search | MySQL 8, Elasticsearch 8 | MySQL, Elasticsearch |
| Licence | AGPL-3.0 | AGPL-3.0 |
| Self-hostable | Yes | Yes |
| Archival description | ISAD(G), ISAAR(CPF), ISDIAH | ISAD(G), RAD, DACS, ISAAR(CPF), ISDIAH, Dublin Core |
| EAD / EAC export | EAD 2002, EAD3, and EAC-CPF serialization | EAD 2002 and EAC-CPF export |
| EAD / EAC import | Native EAD 2002 and EAD3 XML import, round-trip safe (preview + commit) | Mature EAD 2002 and EAC-CPF import |
| OAI-PMH | Serve and harvest (provider and harvester) | OAI-PMH provider |
| Finding aids | Generated PDF finding aids | PDF / RTF finding aid generation |
| Records in Contexts (RiC) | Native, first-class (traditional + RiC view per entity) | Not native (community and roadmap interest) |
| Museum collections | Spectrum-capable (Spectrum 5.1 procedures) | Not a museum collections system |
| Digital asset management | Built-in: IIIF deep-zoom, 3D viewing, media at scale | Basic digital object handling |
| Digital preservation | OCFL, BagIt, OAIS/PREMIS, portable dark-archive export | Via integration with Archivematica |
| Archivematica integration | Connector (pulls DIPs) | Native (same steward, Artefactual) |
| Research / reading-room portal | Built-in: bookings, reproductions, ODRL rights, API keys | Not included |
| AI-assisted workflows | Built-in: HTR, NER, condition assessment, metadata suggestion | Not included |
| Multilingual | 66 locale scaffolds; English and Afrikaans complete, others in progress (#1410) | Yes, very strong: ~50 community-maintained locales |
| REST API | v1 and v2 (key auth, OpenAPI) | Available (older) |
| Maturity / install base | Newer, actively developed, growing | Mature, very large global install base |
| Steward | The Archive and Heritage Digital Commons Group (The AHG) | Artefactual Systems + AtoM Foundation |

## Positioning summary

Heratio matches or beats AtoM on standards interchange (EAD 2002 + EAD3 with round-trip,
OAI-PMH serve and harvest, finding-aid PDF), runs a current stack (Laravel 12 vs Symfony
1.4, EOL 2012), is RiC-native, and spans museum/DAM/preservation/records on one data
model. AtoM legitimately leads on multilingual completeness and community/install-base
size. The page concedes those two honestly.

The full page copy (hero, "what is Heratio", when-AtoM / when-Heratio, migration, FAQ)
is drafted in the working pack; this file preserves the verified comparison substance for KM.

## Publishing / domain (decided 2026-07-19)

Canonical URL: **https://heratio.org/compare/atom** (pending heratio.org registration -
owner action). Decision rationale:
- The OSS category norm is a dedicated product .org (accesstomemory.org, archivesspace.org,
  collectiveaccess.org, collectionspace.org, omeka.org) - never a subdomain of the parent org.
- Both current AHG domains are .co.za, which geo-signals South Africa and undercuts
  Heratio's international positioning. A dedicated international TLD fixes it.
- **heratio.com is NOT owned by AHG** - it was registered by a reseller (2025-12-22) and is
  parked for sale at ~USD 4,888. Decision: do NOT buy it. heratio.org registers for ~USD 12/yr
  and is a better brand fit for an open-source archival product. Revisit .com only if the
  product gains traction and the price becomes trivial.
- Brand split: Plain Sailing iSystems = software/IP owner; The AHG = publisher/services/hosting;
  Heratio = the product on heratio.org.

## Conversion CTA (decided 2026-07-19)

The page is a dual-purpose asset (SEO/GEO discovery + sales enablement + migration
justification + bid/RFP content), not SEO-only. Primary CTA: **"Book a free AtoM migration
assessment"** (https://heratio.org/migration-assessment), placed after the intro and as a
closing section. It captures high-intent AtoM switchers and matches the page's migration framing.

*Recorded 2026-07-19.*

# Heratio GEO/SEO visibility baseline (2026-07-19)

**Summary:** A GEO (Generative Engine Optimization) and SEO visibility test run on 2026-07-19 found that Heratio is effectively invisible in both the training knowledge of large language models and the web-retrieval layer that AI answer engines and search rank from. This is the measured "before" state against which later exposure work (Phase 0 onward) is compared.

## Method

Two legs:
1. **Cold-model leg** - five fresh LLM agents, each with no Heratio context, were asked the buyer questions real customers ask (best museum/archive CMS; AtoM alternatives; museum collections management CMS; university archives + research-data platform; open-source self-hostable GLAM). Each answered purely from training knowledge, no web access, and named the specific products it would recommend.
2. **Retrieval leg** - the same category queries plus the branded query "Heratio ... AHG" were run against web search to approximate what Perplexity, Google AI Overviews, and Bing Copilot retrieve.

## Findings

### Cold-model leg: Heratio absent from all five segments
Every agent, in every segment, volunteered "never heard of it" for both **Heratio** and **The AHG as a software vendor**. Products the models recommend instead:

- Best museum/archive CMS: Axiell, TMS / Gallery Systems, CollectiveAccess, AtoM, PastPerfect
- AtoM alternatives: ArchivesSpace (now the default), CollectiveAccess, Preservica, Axiell / CALM
- museum collections management CMS: Axiell, TMS, MuseumPlus (zetcom), Vernon, Modes, System Simulation, CollectionSpace
- University archives + RDM: ArchivesSpace + Archivematica + Dataverse / Figshare + DSpace
- Open-source self-hostable: AtoM, CollectiveAccess, Omeka S, ArchivesSpace, Islandora

### Retrieval leg: invisible even to the branded query
Searching the product name plus "AHG" surfaces only theahg.co.za **services/consulting** pages, not the software. The category SERPs are owned by aggregators and Wikipedia (Capterra, SourceForge, G2, AlternativeTo, SoftwareWorld, gitnux, zipdo) and by incumbent vendor sites. Heratio is on none of them. AtoM has a Wikipedia page; ArchivesSpace has displaced AtoM as the default "serious AtoM alternative".

## Two structural findings that shape strategy

1. **The retrieval substrate is third-party (aggregators + Wikipedia), not your own domain.** Getting listed on review/comparison directories and Wikidata/Wikipedia is the highest-leverage move because that is what both search and LLMs pull from.
2. **The cold test revealed uncontested white space.** No mainstream open-source GLAM platform is Laravel-based, and RiC (Records in Contexts) is barely shipped by any competitor. Heratio is both Laravel-native and RiC-native. "The modern, RiC-native GLAM platform" is a distinctive, repeatable, factual claim no incumbent can contest, and distinctiveness is what GEO rewards.

## Competitive set (who wins the answer today)

- Commercial: Axiell, TMS / Gallery Systems, PastPerfect, Preservica, Lucidea (ArchivEra / Argus), Vernon, MuseumPlus / zetcom, Soutron
- Open-source: ArchivesSpace, AtoM, CollectiveAccess, CollectionSpace, Omeka / Omeka S, Islandora, DSpace
- Digital preservation: Archivematica, Preservica, Ex Libris Rosetta
- Research data management: Dataverse, Figshare, DSpace, InvenioRDM / Zenodo, CKAN

## Phased response

- **Phase 0** (controllable, days): fix branded invisibility - product front door with schema.org JSON-LD, llms.txt, GitHub repo metadata, first comparison page. See geo-phase0-exposure-pack.md.
- **Phase 1** (weeks): directory listings (AlternativeTo, Capterra, G2, SourceForge), Collections Trust Partner directory, Wikidata entity, comparison pages.
- **Phase 2** (months): Wikipedia article, named case studies, r/archivists and SAA presence, academic citations.
- **Phase 3** (ongoing): repeat the Laravel + RiC-native positioning until models associate it with Heratio.

*Recorded 2026-07-19. Re-run this test after each phase to measure movement.*

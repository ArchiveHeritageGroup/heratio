# Artificial Intelligence as Collections Steward: Enabling Inclusive, Sustainable Museum Collections Management in the African Context

**Johannes Jurie Pieterse**
The Archive and Heritage Group (Pty) Ltd
johan@theahg.co.za

*Submitted to: South African Museums Association Bulletin, Volume 48 (2026)*
*Status: Full paper draft - 30 May 2026 deadline*

---

## Abstract

The intersection of artificial intelligence and museum collections management presents both transformative opportunity and practical challenge for African memory institutions. Drawing on doctoral research in AI-assisted records management and archives, and the development of *Heratio* - a modular, open-source collections management framework built for the GLAM (Galleries, Libraries, Archives, Museums) sector - this paper examines how AI is reshaping the core activities of collections stewardship in ways that are particularly relevant to under-resourced African institutions.

The paper presents four specific AI applications developed within the Heratio framework. First, *AI-powered object description for persons with disabilities*, where natural language generation enables visually impaired users and researchers to receive rich contextual descriptions of collection objects, advancing inclusive access without additional curatorial labour. Second, *automated condition assessment*, where computer vision analyses object photographs to detect damage typologies - mould, tears, structural instability, surface deterioration - aligned with the Spectrum 5.0 standard, supporting proactive conservation prioritisation in institutions lacking dedicated conservators. Third, *AI-assisted fixity and digital preservation integrity*, where machine learning supplements traditional checksum-based fixity checking to identify at-risk digital objects based on format vulnerability, storage environment indicators, and deterioration pattern recognition. Fourth, *AI metadata extraction* from archival documents, photographs, and digital assets, including Named Entity Recognition (NER) for automated authority record creation and EXIF/IPTC/XMP harvesting.

The paper argues that these capabilities, when integrated into affordable open-source infrastructure and aligned with African legislative frameworks (POPIA, NARSSA, GRAP 103), can meaningfully close the digital capability gap facing South African and continental African museums. Critically, it positions AI not as a displacement of specialist knowledge but as an *amplifier*, enabling smaller institutions to meet international standards while adapting to local realities. The paper concludes with reflections on the ethical dimensions of AI-generated descriptions and the importance of community-informed training data in the African context.

**Keywords:** artificial intelligence, collections management, condition assessment, accessibility, digital preservation, fixity, open-source, GLAM, South Africa

---

## 1. Introduction

South Africa's museum sector - and the broader African continental context within which it operates - faces a structural digital capability gap. International standards for collections management, conservation, and digital preservation continue to evolve, but the staffing, infrastructure, and procurement budgets required to meet them remain disproportionately concentrated in institutions of the global North (Tonta 2024; Manžuch 2017). Smaller South African museums, regional archives, and community heritage institutions are routinely asked to deliver Spectrum 5.0 (Collections Trust 2017)–conformant condition assessment, ISAD(G)–compliant archival description, and OAIS–aligned (CCSDS 2012) digital preservation, with capability footprints designed for organisations several times their size.

This paper reports on a body of practitioner-researcher work attempting to close this gap by building open-source, AI-augmented collections management infrastructure tailored to the realities of African memory institutions. The work is grounded in doctoral research currently under examination on the application of artificial intelligence to records management and archives, and is operationalised through the development of *Heratio* - a modular, open-source GLAM collections management framework now deployed in production at South African heritage and archival institutions.

The thesis of this paper is that artificial intelligence, when integrated thoughtfully into open-source collections infrastructure, can act not as a substitute for specialist knowledge but as an *amplifier* of it. Where a museum has one part-time conservator overseeing 30,000 objects, an AI-assisted condition triage tool does not replace the conservator's expertise - it allows that expertise to be applied first to objects that the model has flagged as deteriorating, rather than dissipated across routine surveys. Where a regional archive lacks the budget for a full-time accessibility officer, a natural-language description module enables the institution to produce alt-text and verbal-image descriptions for visually impaired users at the moment of cataloguing rather than as a future aspiration.

The paper is structured as follows. Section 2 sets out the policy and technical context within which African collections management currently operates, with particular attention to the South African legislative environment (NARSSA, POPIA, GRAP 103) and the standards (Spectrum 5.0, ISAD(G), Dublin Core) that shape institutional practice. Sections 3 through 6 present in turn the four AI applications developed within Heratio: inclusive object description for persons with disabilities; automated condition assessment; AI-assisted fixity and digital preservation; and AI-driven metadata extraction with authority control. Section 7 reports on implementation realities - the GPU and infrastructure requirements, the open-source-as-decolonial-strategy framing, and the cloud-versus-on-premises decision points that recur in deployment. Section 8 examines the ethical dimensions of these capabilities, particularly the risks of bias in AI-generated descriptions of African cultural material. Section 9 concludes with a synthesis and a research agenda.

---

## 2. Background

### 2.1 The state of collections management in South African and African institutions

South African museum institutions sit on a wide capability spectrum. National-level institutions such as Iziko Museums, the Ditsong Museums of South Africa, and the McGregor Museum have invested in Spectrum-aligned collections management systems (CMS) and in dedicated digital preservation programmes. At the other end of the spectrum, district museums, university-affiliated heritage collections, and community museums often manage their holdings on commercial off-the-shelf software not designed for archival workflows - or, in many cases, on shared spreadsheets and local file systems. The continental picture, surveyed in studies of African archives and digital heritage (Mutula and Wamukoya 2007; Britz et al. 2016), is consistent with this divergence: capability follows budget.

The cost barrier of commercially-licensed collections management is significant. Per-seat licences for the dominant museum systems can exceed an institution's entire annual digitisation budget. Open-source systems such as Access to Memory (AtoM) - developed by Artefactual Systems - and CollectionSpace have substantially democratised access to standards-compliant tooling, and Heratio's own data model is built on the same Qubit schema framework that underlies AtoM (acknowledging the foundational contribution of Artefactual Systems to the open archival software ecosystem).

### 2.2 The promise and the risks of AI

Artificial intelligence introduces a second-order democratisation. Where the first wave of open-source CMS made standards-compliant cataloguing affordable, AI promises to make standards-compliant cataloguing *fast* - and in particular, to make accessibility, condition assessment, and metadata enrichment achievable for institutions whose human capacity has not historically scaled with the size of their holdings. The promise is real: large language models, computer vision models, and named-entity recognition pipelines are now mature enough to produce institution-grade output for many tasks that formerly required specialist staff (Padilla 2019; Ehrmann et al. 2023).

The risks are equally real. AI-generated descriptions of African cultural material, trained predominantly on Western corpora, encode systematic biases of perspective and vocabulary (Bender et al. 2021; Birhane 2022). Computer vision models trained on conservation photographs of European paintings perform unevenly on bark-cloth, beadwork, and ethnographic material. Named-entity recognition systems trained on English news text tag isiZulu and Sesotho place-names inconsistently. A research-grade response to these risks treats AI not as an off-the-shelf solution but as infrastructure to be tested, tuned, and surrounded by human-in-the-loop review. This paper presents Heratio's design choices in that light.

### 2.3 The South African policy context

Four legislative and standards instruments frame this work.

**NARSSA**, the National Archives and Records Service of South Africa Act (Act 43 of 1996), regulates the management, preservation, and access of public records and establishes the authority responsible for records disposition. Its general retention disposal authority and the National Archives' published file plans constitute the substrate against which any records management workflow operates in the public sector.

**POPIA**, the Protection of Personal Information Act (Act 4 of 2013), governs the processing of personal information by public and private bodies. Its implications for AI-assisted metadata extraction are substantial: NER systems that surface persons' names from archival documents must be operated within the lawful-processing requirements of POPIA, with attention to the right of data subjects to object to processing and to the special protection of children's information.

**GRAP 103** (Accounting Standards Board 2013) - the Generally Recognised Accounting Practice standard for Heritage Assets - requires public-sector entities to recognise heritage holdings on the financial statements where reliable valuation is feasible. The integration of collections management with heritage accounting is an emerging area of practice in South African public museums, and the AI-assisted condition assessment pipeline described in Section 4 supports the regular condition reporting that GRAP 103 entails.

**Spectrum 5.0** (Collections Trust 2017) is the international collections management standard most widely adopted in South African museum institutions. It defines twenty-one core procedures spanning acquisition, location and movement control, condition checking, and disposal. The AI-assisted condition assessment pipeline in Heratio is explicitly aligned with the Spectrum condition-checking procedure to ensure that AI output integrates with - rather than competes with - established practice.

---

## 3. AI for Inclusive Access: Describing Objects for Persons with Disabilities

### 3.1 The accessibility gap in digital collections

Digital collections that lack rich textual descriptions are inaccessible to blind and low-vision users in ways that are easily overlooked by sighted curators. The Web Content Accessibility Guidelines (WCAG) 2.1 require non-text content to have a text alternative serving an equivalent purpose (W3C 2018). Applied to a digitised photograph collection, this implies that every image carries a descriptive text - its alt-text - sufficient for a screen reader user to grasp its content and significance. In practice, alt-text fields in collections databases are often empty, sparsely populated, or duplicated from object titles. Surveys of cultural heritage websites consistently identify accessibility as the area in which digital practice most lags policy commitment (Walsh 2016).

The labour cost of remediating this gap manually is prohibitive. A small museum with 25,000 digitised images would require thousands of hours of curatorial time to write quality alt-text - labour that simply does not exist in current institutional budgets.

### 3.2 AI-generated alt-text and natural-language object descriptions

Heratio's AI Description module addresses this gap by integrating a vision-language model (VLM) - currently LLaVA at 7B and 13B parameter sizes (Liu et al. 2023), running locally on institution-controlled GPU infrastructure - with the cataloguing workflow. When an object record is created or its representative image is updated, the cataloguer may invoke the *Generate Description* action. The model receives the master image at full resolution along with structured contextual fields from the object record (title, classification, place of origin, material, period). It returns a candidate description in three layers:

1. A short *alt-text* (one sentence, ≤120 characters), suitable for screen-reader announcement.
2. A medium-length *visual description* (three to five sentences), describing what is visually present in the image without interpretation.
3. A longer *contextual description* (one to two paragraphs), grounding what is visible in the metadata-supplied context - the object's known origin, function, and provenance.

Each layer is presented to the cataloguer for review. The cataloguer accepts, edits, or rejects the candidate. Accepted descriptions are written to the corresponding fields in the Heratio data model and appear in the public-facing object page, in JSON-LD structured data for search engines, and in the alt attribute of every image rendering of that object.

### 3.3 Multi-modal description: visual, contextual, and provenance layers

The three-layer structure exists for two reasons. First, accessibility standards properly distinguish between alt-text (a brief substitute for the image, designed for in-line consumption by screen readers) and a long-description (a richer description appropriate when the image carries significant informational content). Most blind users do not want every alt-text to be a paragraph. Second, separating the visually present from the contextually inferred makes editorial review tractable. A cataloguer can confirm what the image shows independently of whether the model's contextual interpretation is correct, and reject a contextual layer that introduces unsupported claims.

### 3.4 Case study: Heratio's AI Description module

In a pilot deployment at a South African archival institution holding approximately 8,500 digitised photographs of twentieth-century political activity, the AI Description module was used to draft alt-text for previously alt-text-empty records. Of the first 500 candidates reviewed by the lead archivist, [N1]% were accepted as drafted, [N2]% were accepted with editorial revision, and [N3]% were rejected and rewritten manually. The most common reason for rejection was the model's tendency to identify generic categorical descriptions ("a group of people gathered outdoors") in cases where the institution's metadata indicated specific historical events; the contextual layer was the most frequently edited.

> *[Author's note for the SAMAB editor: the figures N1–N3 will be filled from the validation pilot completed before the May 30 deadline. The pilot study is in progress; preliminary results from the first 100 records show acceptance-as-drafted rates between 60% and 70% for alt-text and between 30% and 45% for the contextual layer.]*

### 3.5 Alignment with WCAG 2.1 and universal-design principles

Heratio's public object pages render alt-text on every image. JSON-LD structured data emits a `caption` and an `alternativeHeadline` in machine-readable form. The administrative interface flags any object record that lacks alt-text, surfacing accessibility debt as a tracked metric rather than an invisible failure mode. This treatment is consistent with the universal-design principle that accessibility is not a separable feature for a subset of users but a baseline of the system that benefits all (Story et al. 1998).

### 3.6 Ethical considerations: who defines the "correct" description?

The most consequential ethical question raised by AI-assisted description is not whether a model can produce text but whose description it produces. A photograph of a 1985 protest in Mamelodi, described by a vision-language model trained on globally-aggregated image-text pairs, will be described in the language of generic protest photography - and may misidentify the protest's specific historical referents, mistake symbols, or apply terminology that the descended community does not recognise. The Heratio module addresses this risk in three ways. First, the cataloguer is positioned as the authoritative editor - the AI proposes, the cataloguer disposes. Second, where institutions have established community advisory relationships, those relationships govern editorial review of contextual layers, particularly for sacred, sensitive, or living-tradition material. Third, institutions can disable AI description for designated collections, retaining manual description for material where the editorial overhead of AI review outweighs the time saved.

---

## 4. AI-Assisted Condition Assessment

### 4.1 The conservation staffing crisis in South African museums

Conservation as a profession is in structural decline in many South African museum institutions. The 2017 SAMAB survey of conservation capacity (Anonymous 2017) reported that of [N4] reporting institutions, fewer than [N5]% employed a full-time conservator, and a substantial proportion reported no in-house conservation capacity at all. Where conservation expertise existed, it was disproportionately concentrated in national institutions and university museums; smaller regional and community museums frequently relied on external consultancy or, in many cases, no formal condition assessment at all. This creates a paradox: the institutions whose collections are most vulnerable - those holding mixed media, ethnographic material, and photographic archives in non-climate-controlled storage - are precisely those least likely to have routine condition information against which deterioration can be measured.

### 4.2 Spectrum 5.0 as the assessment standard

Spectrum's *Condition Checking and Technical Assessment* procedure defines a structured condition record with controlled-vocabulary assessments of overall condition (good / fair / poor / unstable), specific damage typologies, and recommended treatment urgency. Spectrum's design assumes a trained conservator or conservation technician performing the assessment. Heratio's AI-assisted module is positioned not as a substitute for that role but as a *triage* layer: it produces a Spectrum-shaped draft assessment that a human specialist reviews, prioritises, and signs off.

### 4.3 Computer vision for damage typology detection

The Heratio condition module accepts one or more high-resolution photographs of an object (a primary view, optional detail shots) and runs them through a pre-trained vision-language model fine-tuned on a custom dataset of conservation reference photographs annotated with Spectrum damage codes. The model outputs:

1. An overall condition grade (Stable / Fair / Poor / Unstable).
2. Per-defect findings: foxing, tears, water damage, fading, support loss, mould, biological attack, ink loss, with bounding-box masks for each defect class.
3. A severity score (0–100) calibrated against archival material from the institution's own collections.
4. A recommended treatment action drawn from the institution's `conservation_action` taxonomy, ranging from "digitise immediately" through "consult conservator" to "no action required."

Each AI-generated assessment is written as a draft record in the Heratio condition log. The conservator reviews the heatmap overlay, confirms or revises individual defect findings, sets the treatment urgency, and either accepts the assessment (at which point it becomes the authoritative condition record) or rejects and rewrites it.

### 4.4 Human-in-the-loop: AI suggests, conservator confirms

By design, AI condition assessments do not auto-publish. The audit log records who accepted or rejected each draft, when, and with what edits. This serves both a curatorial function - the conservator's professional judgement remains the system of record - and a regulatory one. GRAP 103's heritage-asset condition reporting requirements are met by the conservator's signed-off record, not by the AI draft.

### 4.5 Integration with loan workflows, access restrictions, and conservation queuing

A condition record carrying an *Unstable* grade automatically triggers downstream effects. Access to the object's digital surrogate may be restricted for researchers (subject to institutional policy), outgoing loan requests are blocked pending conservator review, and the object is added to the institution's conservation priority queue, ranked by severity. These integrations turn condition data from a static record into an operational signal - the "amplifier" effect: conservator decisions shape what happens to the object across the institution's daily operations rather than living only in the conservation database.

### 4.6 Validation: agreement rates between AI and human assessors

The AI-versus-conservator agreement rate is the central methodological question for this work. In a calibration study against [N6] objects from a South African archival photograph collection, the AI's overall-grade assignment agreed with the conservator's at [N7]%, with the most frequent disagreement being one grade more pessimistic on the AI side (i.e., AI flagged as *Poor* what the conservator graded as *Fair*). For specific damage typologies, agreement rates varied: foxing detection [N8]%, tear detection [N9]%, mould detection [N10]%, water damage [N11]%. The lower agreement on water damage is consistent with international literature, which reports that water-damage staining is visually similar to a range of unrelated processes (printer ink, intentional dye, photographic developer artefacts) (Conservation OnLine 2020).

> *[Author's note: Validation figures will be substituted from the calibration study currently in progress; the photograph-collection sample is being reviewed by an external conservator as a blind comparison.]*

---

## 5. AI and Digital Preservation Fixity

### 5.1 Traditional checksum-based fixity: limitations at scale

Digital preservation rests on fixity - the verifiable demonstration that a digital object has not changed since it was last verified. The standard mechanism is cryptographic checksumming: when an object is ingested, a SHA-256 (or equivalent) hash is computed and stored; subsequent verification recomputes the hash and compares. Discrepancies indicate corruption (silent data corruption, "bit rot") or unauthorised modification.

At the scale of a typical regional archive (hundreds of thousands of files, terabytes of storage), routine fixity verification becomes a non-trivial operation. A single full-corpus pass can take hours, consume substantial I/O bandwidth, and - in NFS or cloud-storage environments - incur measurable financial cost. Most institutions therefore verify on a sampling basis or on a long cycle (annually or biannually), accepting a window during which corruption could go undetected.

### 5.2 AI-enhanced fixity: predictive risk assessment for digital objects

The Heratio fixity module, building on the foundation of cryptographic verification, adds a predictive layer that prioritises which objects to verify next based on a learnt risk score. Inputs to the risk model include:

1. **Format vulnerability**, derived from a PRONOM-aligned (The National Archives 2024) format identification of each digital object. Formats classified as obsolete, proprietary-without-current-renderers, or known-to-be-corruption-prone (early MPEG, certain compressed-TIFF variants) receive elevated risk scores.
2. **Storage environment indicators**: file-system ageing signals (last-verified timestamp, time-since-last-access, storage-tier transitions), checksum-history pattern (files that have been re-checksummed before are *less* likely to need attention than files with no verification history), and storage-medium classification (NAS, S3, cold-tier).
3. **Deterioration pattern recognition**: where institutions have access to historical fixity-failure logs, the model learns which file-system, format, and metadata combinations have historically produced corruption; new objects matching those patterns are scheduled for verification first.

The output is not a replacement for the cryptographic checksum but a *scheduling input*: a daily-refreshed prioritisation of which objects the routine fixity job should verify next within its available time and bandwidth budget.

### 5.3 Integration with digital preservation workflows in under-resourced environments

This is where the under-resourced framing matters. A national-level digital preservation programme can afford to verify everything on a regular cadence; a regional museum cannot. Predictive risk-prioritised fixity allows the regional museum to allocate its limited verification capacity to the objects most likely to fail, while still demonstrating to its auditors and to its stakeholders that *all* objects are eventually verified. The AI does not replace the cryptographic guarantee of fixity; it makes the cryptographic guarantee operationally tractable.

### 5.4 Practical implementation in Heratio

The Heratio Phase 3.5 work added a `RunFixitySchedulesCommand` artisan command and a `preservation_workflow_schedule` table that, given a fixity-check workflow definition with an algorithm (sha256/sha512), a stale-day window, a per-repository scope, and a batch limit, walks the prioritised candidate set on each scheduled execution and records every result as a PREMIS-aligned `fixityCheck` event (PREMIS Editorial Committee 2015). The risk-prioritisation layer is implemented as a query-side ordering of the candidate set; absent the AI risk model, the default is least-recently-verified-first, which approximates a uniform prior and is the sensible degraded behaviour when training data is unavailable.

---

## 6. AI Metadata Extraction and Authority Control

### 6.1 The metadata backlog problem in African collections

Every memory institution has a backlog of accessioned-but-not-fully-described material. For some institutions the backlog is small; for many South African archives and regional museums, it is the dominant feature of the holdings - material that is physically in custody, intellectually documented at the box level, but not described in researcher-discoverable detail. This backlog grows faster than human cataloguing capacity can reduce it. The case for AI-assisted metadata extraction is therefore not theoretical: it is the difference between a collection that is discoverable and a collection that exists.

### 6.2 EXIF, IPTC, and XMP automated extraction

The simplest tier of AI metadata extraction is, technically, not AI at all but disciplined automated parsing. Modern digital photographs carry EXIF (exposure, camera, GPS), IPTC (caption, byline, copyright, keywords), and XMP (creator, rights, structured taxonomies) embedded metadata that, in many cases, is *already* descriptively rich. Heratio's ingest pipeline extracts all three on accession and writes them into the corresponding Dublin Core and ISAD(G) fields of the object record. Where multiple namespaces describe the same property, a precedence rule (XMP > IPTC > EXIF for descriptive fields; EXIF for technical fields) prevents conflict. This alone closes a substantial portion of the metadata gap for born-digital photographic collections.

### 6.3 Named-Entity Recognition for person, place, organisation, and date

For text-bearing material - typescripts, correspondence, scanned reports, OCR'd photographs - Heratio applies a transformer-based NER pipeline (currently using a base model fine-tuned on archival prose with a domain vocabulary specific to twentieth-century South African political and administrative records). The pipeline extracts persons, organisations, places, dates, and events. Each extraction is tagged with a confidence score; extractions above a configured threshold are automatically suggested as authority record links, and lower-confidence extractions are queued for cataloguer review.

### 6.4 Automated authority record suggestions

A high-confidence extraction of a person's name surfaces a suggestion: link this object to authority record *X*, or create a new authority record of type *Person*. The cataloguer accepts, declines, or merges (selecting an alternative existing authority that the AI did not initially propose). Accepted suggestions write the appropriate `creator`, `subject`, or `place` relationship into the relational data model and into the corresponding ISAAR(CPF) authority record.

### 6.5 Multilingual considerations: indigenous languages, Afrikaans, transliteration

Generic NER models trained on English corpora perform poorly on South African archival prose. They mistag isiZulu and Sesotho proper nouns as common nouns, transliterate Afrikaans place-names inconsistently, and frequently miss multi-word organisational names that span indigenous and colonial-period vocabulary. Heratio's NER pipeline addresses this by using NLLB-200 (Costa-jussà et al. 2022) - a multilingual translation model with explicit support for Afrikaans, isiZulu, isiXhosa, Sesotho, and other South African official languages - as a pre-processing step for cross-lingual entity recognition, and by maintaining an institution-supplied gazetteer of South African place names and organisations against which the NER output is reconciled.

### 6.6 Practical scale

Within Heratio's deployed installations, the NER pipeline has surfaced person, place, and organisation candidates from approximately 4.5 million words of OCR'd archival text, contributing to the construction of a working authority-record graph that - at the time of writing - links several thousand persons and organisations to the records they appear in. The contribution is not that AI has replaced authority-record creation; it is that AI has surfaced the candidates that human archivists then evaluate, dramatically reducing the search-and-match labour that previously dominated authority-control work.

---

## 7. Implementation Realities and Lessons Learned

### 7.1 Open source as decolonial infrastructure strategy

A recurring theme in African digital heritage practice is the dependency relationship between institutions and overseas software vendors. Commercial collections management systems are typically priced, licensed, and developed in the global North; data sovereignty, customisation rights, and the freedom to integrate with local AI infrastructure are constrained by the licensor. Open-source software - and, more specifically, open-source software released under a copyleft licence such as the GNU Affero General Public License - turns this relationship inside out. Institutions own their deployment, contribute back when they can, and can fork the codebase to meet local needs. This is not merely an economic argument; it is, as several authors have argued, a decolonial one (Nakamura 2023; Mhlambi 2020). Heratio is released under the AGPL-3.0 and developed in public on GitHub.

### 7.2 Standards alignment: ISAD(G), Dublin Core, Spectrum 5.0, RiC-CM

Heratio's data model is internally aligned with three descriptive standard families. ISAD(G) (International Council on Archives 2000) governs archival description; Dublin Core (DCMI 2020) governs lightweight resource description for cross-institutional aggregation; Spectrum 5.0 (Collections Trust 2017) governs museum collections management procedures. Each AI capability described in this paper writes its output into one or more of these standards' fields, ensuring that AI-generated metadata is not a parallel data layer but the same data layer. The recently released Records in Contexts Conceptual Model (International Council on Archives 2023) provides an over-arching graph-theoretic model for archival description that Heratio expresses as RDF/SPARQL, exposing AI-derived authority links and provenance relationships through a graph interface alongside the traditional record-page view.

### 7.3 GPU and infrastructure requirements

Vision-language models, computer vision condition assessors, and embedding pipelines for metadata extraction have non-trivial GPU requirements. The deployment configuration used in Heratio's reference installation runs LLaVA 13B and a CLIP-derived condition model on a single mid-range GPU server (an NVIDIA RTX 3070 with 8 GB of VRAM, with an upgrade path to a higher-VRAM card for institutions running larger models). This is well below the cluster-scale infrastructure that vendor cloud-AI services assume. The trade-off is throughput: an on-premises single-GPU configuration can comfortably support a small-to-medium institution's interactive AI workflows but would not scale to a very large institution's bulk reprocessing. For the target institutional segment (regional museums, municipal archives, university heritage collections), single-GPU on-premises is a viable and cost-bounded configuration.

### 7.4 Cloud versus on-premises: data sovereignty considerations

A persistent question in African heritage AI work is whether to use cloud-hosted AI services (OpenAI, Anthropic, Google, Azure) or to run on-premises models. The cloud option is operationally simpler and provides access to larger, more capable models. The on-premises option gives data sovereignty: archival material, much of which carries POPIA-protected personal information and culturally-sensitive content, is not transmitted to third parties; training on the institution's holdings is not permitted (or even possible) by the LLM vendor. For South African public-sector institutions subject to POPIA and to government cloud-residency policies, on-premises is frequently the only legally tractable option. Heratio supports both deployments; the reference deployment is on-premises.

### 7.5 Staff capacity and the human-AI collaboration model

Across the four AI capabilities described, a single design pattern recurs: the AI proposes, the human disposes. Cataloguers, conservators, and researchers retain editorial authority. This is not a technical accident; it is an explicit response to the bias, hallucination, and cultural-knowledge limitations of contemporary AI systems. The amplifier metaphor depends on this collaboration: the AI does the search-and-suggest work that previously dominated specialist labour, freeing the specialist to do the judgement work that AI cannot. Where this collaboration is implemented well, AI accelerates the work; where it is implemented poorly - by short-circuiting the human review step in the name of throughput - AI introduces errors that the institutional record then carries indefinitely.

---

## 8. Ethical Dimensions

### 8.1 Bias in AI-generated descriptions of African cultural objects

Pre-trained vision-language models are predominantly trained on Western image-text corpora; their descriptive vocabulary reflects this training. Applied to African material culture, the bias surfaces in three observable ways. First, *categorical mistakes*: bark-cloth garments are described as "cloth" without specific terminology; ethnographic adornment is described in generic-jewellery vocabulary that does not name the specific tradition. Second, *interpretive overlays*: protest photography is described in generic protest terminology rather than naming specific historical events that the metadata indicates. Third, *omissions*: features that a community-knowledge-bearer would identify as significant (a particular weave, a specific pose, a recognised symbol) are systematically ignored because the model lacks a vocabulary for them.

Heratio's response to these biases is procedural rather than technical. The cataloguer is positioned as the authoritative editor; institutions with established community advisory relationships route AI-generated descriptions through those relationships before publication. Most importantly, AI-generated descriptions are tagged in the data model as machine-generated, allowing researchers and downstream systems to weight them appropriately. This is consistent with the broader principle of provenance transparency in AI-augmented research data (Whitt 2022).

### 8.2 Training data provenance and community consent

The training data underlying contemporary general-purpose vision-language models is opaque. Heratio cannot warrant that the models it integrates were trained without community-objectionable images or texts; this is a constraint the field as a whole faces. Where institutions hold material that is sacred, sensitive, or subject to community-controlled access, Heratio supports - and recommends - disabling AI description for that material. Decisions about whether to use AI on a given collection should be made institution-by-institution, in dialogue with the descended communities, not as a defaulted system feature.

### 8.3 Transparency of AI-assisted metadata for researchers

Researchers are entitled to know which descriptions, transcriptions, and authority links were created by humans and which were AI-suggested or AI-drafted. Heratio records, for every description and every authority-record link, the provenance - human-created, AI-suggested-and-accepted, AI-suggested-and-edited, or AI-suggested-and-rejected - and exposes this provenance through both the public record page and the API. A researcher querying the API for an authority record receives a response that includes the originating method.

### 8.4 AI and intellectual property in collections contexts

A third ethical question concerns the IP status of AI-generated description, transcription, and metadata. The position currently taken by major copyright jurisdictions is unsettled, and South African case law on AI-generated works is largely undeveloped. Heratio's pragmatic position is that AI-suggested metadata, once edited and accepted by a cataloguer, is the institution's metadata under the same authorship attribution as any cataloguer-produced metadata; AI-suggested metadata that has *not* been reviewed remains tagged as machine-generated and is not asserted as institution-authored. Where AI is used to generate transcripts of in-copyright material (correspondence, photographs whose photographers may still be living), the institution's standard rights-clearance procedures apply unchanged.

---

## 9. Conclusion

### 9.1 AI as amplifier, not replacement

The central argument of this paper is that artificial intelligence, when integrated thoughtfully into open-source collections infrastructure and surrounded by human-in-the-loop review, can act as an amplifier of specialist judgement rather than a replacement for it. The four capabilities developed within Heratio - accessibility-focused AI description, AI-assisted condition triage, AI-prioritised fixity, and AI-assisted metadata extraction - each turn a previously labour-bounded task into a labour-bounded *review* task. The same conservator covers more objects; the same cataloguer surfaces more authority records; the same archivist verifies more fixity. Quality, audit-ability, and editorial authority remain with the human specialist.

### 9.2 The case for open-source AI-integrated collections infrastructure

The case for open-source matters because the case for AI matters. A proprietary AI feature locked behind a vendor paywall does not democratise capability - it shifts the dependency from the labour-rich North to the AI-vendor-rich North. An open-source implementation, especially one released under a copyleft licence, allows institutions to inspect, audit, and adapt the AI capability to local needs. For African museums, archives, and libraries seeking to meet international standards while retaining data sovereignty and local control, this is the more viable long-term posture.

### 9.3 Recommendations for South African museums

For South African museum institutions considering AI integration, this paper offers five recommendations.

1. **Begin with the metadata backlog.** AI-assisted metadata extraction has the lowest implementation friction and the largest immediate institutional value. Start with EXIF/IPTC/XMP harvesting on born-digital photographic collections, then extend to NER on text-bearing material.
2. **Treat condition assessment as a triage tool, not a substitute.** Where the institution has no in-house conservator, use AI condition output to prioritise external consultation, not to replace it.
3. **Adopt accessibility AI as a fast win.** Generating alt-text and verbal-image descriptions through a reviewed AI pipeline is the most reliable path to WCAG-aligned digital accessibility at scale.
4. **Run on-premises wherever POPIA and cultural sensitivity require it.** Single-GPU on-premises configurations are sufficient for the institutional segment most often constrained by data-sovereignty considerations.
5. **Make AI provenance visible.** Tag every machine-generated description and authority link, expose the tag in both the public interface and the API, and treat that provenance as a first-class metadata property.

### 9.4 Future directions

Three directions merit follow-on research. First, *multilingual AI for South African languages*, including the integration of NLLB-200 and similar models with archival NER pipelines specifically tuned for isiZulu, isiXhosa, Sesotho, and Afrikaans. Second, *3D object description*, applying recent advances in 3D-aware vision-language models to ethnographic collections digitised through photogrammetry. Third, *community-trained models*, where institutions in dialogue with descended communities co-develop fine-tuned models whose vocabulary, framing, and editorial sensibilities reflect community knowledge rather than the global-North training corpora that current models default to. This last direction is the one with the most consequence for the *African context* framing of the paper title; it is also the direction least currently served by mainstream AI-cultural-heritage research.

---

## References

*[Note: this is a working bibliography; final formatting per SAMAB/Chicago author-guidelines and final pagination/DOIs to be inserted before submission.]*

- Accounting Standards Board. 2013. *GRAP 103: Heritage Assets*. Pretoria: Accounting Standards Board of South Africa.
- Anonymous. 2017. "Conservation Capacity in South African Museums: A Survey." *South African Museums Association Bulletin* 39: 12–24. *[Verify exact citation]*
- Bender, Emily M., Timnit Gebru, Angelina McMillan-Major, and Shmargaret Shmitchell. 2021. "On the Dangers of Stochastic Parrots." *Proceedings of FAccT '21*: 610–623. https://doi.org/10.1145/3442188.3445922.
- Birhane, Abeba. 2022. "The Unseen Black Faces of AI Algorithms." *Nature* 610: 451–452. https://doi.org/10.1038/d41586-022-03050-7.
- Britz, J., A. Lor, P., and J. J. Britz. 2016. *Information Ethics in Africa: Cross-Cutting Themes*. Pretoria: African Centre of Excellence for Information Ethics.
- CCSDS (Consultative Committee for Space Data Systems). 2012. *Reference Model for an Open Archival Information System (OAIS)*. CCSDS 650.0-M-2. Washington, D.C.: CCSDS.
- Collections Trust. 2017. *Spectrum 5.0: The UK Museum Collections Management Standard*. London: Collections Trust.
- Conservation OnLine. 2020. "Water Damage to Photographic Materials." *CoOL Resources*. Stanford University Libraries.
- Costa-jussà, Marta R., et al. 2022. "No Language Left Behind: Scaling Human-Centered Machine Translation." *arXiv* preprint arXiv:2207.04672.
- DCMI (Dublin Core Metadata Initiative). 2020. *DCMI Metadata Terms*. https://www.dublincore.org/specifications/dublin-core/dcmi-terms/.
- Ehrmann, Maud, Ahmed Hamdi, Elvys Linhares Pontes, Matteo Romanello, and Antoine Doucet. 2023. "Named Entity Recognition and Classification in Historical Documents: A Survey." *ACM Computing Surveys* 56 (2). https://doi.org/10.1145/3604931.
- International Council on Archives. 2000. *ISAD(G): General International Standard Archival Description*. 2nd edition. Ottawa: ICA.
- International Council on Archives. 2023. *Records in Contexts: Conceptual Model (RiC-CM)*. Version 1.0. ICA Experts Group on Archival Description.
- Liu, Haotian, Chunyuan Li, Qingyang Wu, and Yong Jae Lee. 2023. "Visual Instruction Tuning." *arXiv* preprint arXiv:2304.08485.
- Manžuch, Zinaida. 2017. "Ethical Issues in Digitization of Cultural Heritage." *Journal of Contemporary Archival Studies* 4 (4).
- Mhlambi, Sabelo. 2020. *From Rationality to Relationality: Ubuntu as an Ethical and Human Rights Framework for AI Governance*. Carr Center Discussion Paper Series 2020-009. Cambridge, MA: Harvard Kennedy School.
- Mutula, Stephen M., and Justus Wamukoya. 2007. *Web Information Management: A Cross-Disciplinary Textbook*. Oxford: Chandos.
- Nakamura, Lisa. 2023. "Decolonising AI: A Field Guide." *AI & Society* 38 (2): 567–581.
- Padilla, Thomas. 2019. *Responsible Operations: Data Science, Machine Learning, and AI in Libraries*. Dublin, OH: OCLC Research.
- PREMIS Editorial Committee. 2015. *PREMIS Data Dictionary for Preservation Metadata, Version 3.0*. Library of Congress.
- Story, Molly Follette, James L. Mueller, and Ronald L. Mace. 1998. *The Universal Design File*. Raleigh, NC: Center for Universal Design.
- The National Archives. 2024. *PRONOM Technical Registry*. https://www.nationalarchives.gov.uk/PRONOM/.
- Tonta, Yaşar. 2024. "Digital Capability and the Global Distribution of Library Infrastructure." *College & Research Libraries* 85 (1).
- W3C. 2018. *Web Content Accessibility Guidelines (WCAG) 2.1*. W3C Recommendation, 5 June 2018.
- Walsh, David. 2016. "Web Accessibility in Cultural Heritage: A Survey." *Journal of the Association for Information Science and Technology* 67 (10): 2412–2423.
- Whitt, Richard S. 2022. "Provenance and Trust in AI-Augmented Research Data." *Data & Policy* 4: e3.

---

## Author Bio Note

The author submitted a PhD thesis on artificial intelligence in records management and archives, currently under examination, and is the founder of The Archive and Heritage Group (Pty) Ltd and lead developer of *Heratio*, an open-source GLAM collections management framework. The author has extensive experience implementing archival and museum information systems in the South African context.

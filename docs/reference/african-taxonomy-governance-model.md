# African taxonomy: a governance-and-access model (design reference)

**Summary:** Adapting taxonomies for African contexts is not a vocabulary-content problem but a governance-and-access problem. The unit of curation is the **term-plus-protocol-plus-owner**: an equally multilingual and often oral concept, carrying its own community-set access condition, resolvable by an African-owned persistent identifier, and crosswalked to international standards **as a peer, not subordinated to them**. This note formalises the model, compares it to how Omeka handles taxonomy, and maps it onto Heratio's existing components. Tracked in heratio#1388. Companion paper: `stuff/docs/ica-conference/ica-african-taxonomies-paper.docx`.

## The default to avoid (the "subordination model")

Keeping a Western standard (LCSH, Getty AAT, Dublin Core) as the canonical spine and bolting local terms onto it. The tell is the direction of the mapping: "map *Ubuntu* to the ICA principle of *provenance* so it is interoperable" flattens the African concept into whatever the receiving standard can hold. It fails three ways:

- **Privileges text** over orality (much African knowledge is authoritatively spoken/performed, not written).
- **Privileges a colonial pivot language** (assumes every concept has an English equivalent; "no equivalent" is a legitimate, information-bearing state).
- **Treats vocabulary as neutral and public** (a term itself can be culturally restricted: clan names, sacred sites, initiated or gendered knowledge).

These are consequences of the frame, not gaps to be closed with more fields. Change the frame.

## The six-principle model

1. **Peer vocabularies with sovereign identifiers.** African vocabularies are first-class authorities with their own URIs (e.g. DOCiD via the Africa PID Alliance), crosswalked sideways with SKOS mapping properties (exact/close/related-match), never rooted beneath Getty or LCSH.
2. **Equally multilingual, oral-first term model.** No privileged pivot language; "no equivalent in language X" is recordable and meaningful; an authoritative audio or performed form is first-class, with transcription marked as a surrogate of the spoken original; multi-script (Ge'ez, Arabic, N'Ko) rendered natively rather than transliterated for convenience.
3. **Protocol-bearing terms.** Each term can carry a Traditional Knowledge (TK) Label / cultural protocol and an access condition that is enforced at retrieval, display, and export - not merely flagged, and not only at the object level.
4. **Community governance and data sovereignty.** Editorial authority over a term (define, correct, restrict, release) is assignable to the source community and audit-logged; vocabularies are mirrored locally so they survive the withdrawal or relocation of any external service.
5. **AI as assistant under governance, never generative authority.** The model proposes; the community disposes. Material under restrictive protocol is fenced off from the model entirely, because a model trained on Western corpora will impose Western categories under African labels and may expose restricted knowledge.
6. **Pluggable per-region, not a pan-African monolith.** Southern, West, East, and North African knowledge systems differ; deliver per-region, community-governed vocabulary modules beside a jurisdiction-neutral core.

**Theory base:** CARE Principles for Indigenous Data Governance (Collective benefit, Authority to control, Responsibility, Ethics) paired with FAIR; Local Contexts TK/BC Labels; ICA Records in Contexts (RiC); decolonial archival theory (Harris, Mbembe); orality as evidence (Vansina). **Standards note:** ISO 25539 (seen in an earlier third-party draft) concerns cardiovascular implants and is not applicable; the relevant standards are ISAD(G), RiC, ISO 15489, ISO 23081, and ISO 21127 (CIDOC-CRM).

## How Omeka handles taxonomy (and what to learn)

**Omeka Classic** is weak for this: Dublin Core element sets plus Item Types, controlled values only via the *Simple Vocab* plugin, subjects largely free text, no native SKOS.

**Omeka S** (linked-data) is a useful reference implementation of the plumbing:

| Omeka S mechanism | What it provides |
|---|---|
| Vocabularies (RDF classes/properties, incl. custom) | Peer namespaces with their own URIs - explicitly not the same as controlled vocabularies |
| Resource Templates | Structure which properties (from which vocabularies) describe a resource |
| Value Suggest module | Autocomplete to external authorities (LCSH, Getty AAT/ULAN/TGN, Geonames, VIAF); stores URIs = sideways crosswalk |
| Custom Vocab module | Controlled term lists (literals, URIs, or resource links) bound to a property |
| Thesaurus module (Daniel-KM) | Full SKOS tree (broader/narrower/related); feeds Custom Vocab and a Value Suggest endpoint |
| Taxonomy module | Vocabularies and their terms are first-class resources, linkable like any resource |
| RDF `@lang` value tagging | Per-value language tag; no privileged pivot language |

**Omeka S gets right (adopt / match):** peer RDF vocabularies with own URIs + sideways crosswalk (Principle 1); per-value language tagging (Principle 2 at the data-model level); terms-as-first-class-resources = the hook to attach protocol (Principle 3); SKOS + graph = polyhierarchy, RiC-compatible.

**Omeka S lacks (Heratio's differentiators):** no access-protocol-on-the-term / enforced TK Labels (rights are object-level); no orality-first term model; governance is admin-based, not community-located; no sovereign PID minting.

**Conclusion:** Omeka S validates the thesis. It nails the plumbing and its gaps are exactly the governance-and-access differentiators. Adopt the plumbing; differentiate on protocol + orality + community governance + sovereign PIDs.

## Mapping onto Heratio

**Already present:** `ahg-icip` (Local Contexts Hub integration, OCAP-informed, TK-Label handling); `ahg-term-taxonomy` (terms + multilingual i18n); `ahg-core` `VocabularyResolverService` / `VocabularyImportCommand` / `VocabularyMirrorCommand` (currently Getty AAT, LCSH, Wikidata, mirrored locally); the content-authenticity chain (fixity / PREMIS / C2PA - see [[content-authenticity-architecture]]) as a substrate for sovereign PIDs.

**Gap (proposed work):** register African-owned authorities as peers in the resolver; model terms as first-class SKOS resources with polyhierarchy; bind the term layer to `ahg-icip` so a term carries an enforced TK Label + access condition; equal-multilingual + oral-first term forms; assignable community editorial authority (audit-logged) + local mirroring; DOCiD / Africa PID Alliance sovereign-PID integration; AI-assist guardrails; per-region pluggable modules.

## Two open questions the model surfaces (not settled)

- **Orality destabilises the text-primary term.** If the authoritative form of a concept is a performance, is the unit the string, the sound, or the event of its performance? The model treats audio as first-class but does not resolve whether a performance-primary vocabulary is still a vocabulary in the sense the standards assume.
- **Interoperability exacts a price.** Every crosswalk to an international aggregator is an act of translation, and translation loses. Refusing to map some concepts, or exposing them only within the community, can be a legitimate archival choice rather than a failure of description.

## References

- Companion paper: `stuff/docs/ica-conference/ica-african-taxonomies-paper.docx`; tracking issue heratio#1388.
- Omeka S user manual (Vocabularies, Resource Templates, Value Suggest, Custom Vocab), Omeka S Taxonomy module, Daniel-KM Omeka-S-module-Thesaurus.
- CARE Principles for Indigenous Data Governance (GIDA); FAIR (Wilkinson et al. 2016); Local Contexts / TK Labels (localcontexts.org); ICA Records in Contexts (RiC-CM / RiC-O); Africa PID Alliance / DOCiD (africapidalliance.org). Related: [[content-authenticity-architecture]].

# EAS Best Practices Guide contribution - RiC-O crosswalk (submission + follow-up)

**Summary.** On 11 August 2026 The AHG filed its first contribution to the TS-EAS (SAA) Encoded Archival Standards Best Practices Guide: a "New Page Proposal" for an **EAS to RiC-O crosswalk** page. It is tracked as GitHub issue **SAA-SDT/EAS-Best-Practices#35** (https://github.com/SAA-SDT/EAS-Best-Practices/issues/35), authored by GitHub user **johanpiet2** (Dr Johan Pieterse, The AHG). This note exists so that when the TS-EAS Editorial Board responds, the context and the intended follow-up are on record.

## What was proposed
A companion crosswalk page mapping the EAS suite (EAC-F / EAC-CPF / EAD) to the ICA Records in Contexts ontology (RiC-O), so EAS-encoded description can be expressed as, and exchanged with, a RiC linked-data graph. The addition of EAC-F (functions) makes it timely - the suite now encodes Who (EAC-CPF), Why (EAC-F), What (EAD), which aligns with how RiC models Agents, Activities/Functions and Records as related entities. Proposed content: entity-level mapping (EAC-F function -> rico:Activity/Function; EAC-CPF -> rico:Agent; EAD -> rico:Record), element/attribute mapping, relation mapping (function-to-agent -> performedBy; function-to-resource -> resultsOrResultedIn; function-to-function hierarchical/temporal/associative), and round-trip/validation notes using the TS-EAS validator.

## How BPG contributions work (mechanism)
- Contributions are **GitHub issues** on `SAA-SDT/EAS-Best-Practices` (NOT commits/PRs, NOT an AHG repo). A GitHub account is enough; no fork or write access needed.
- Templates: `contribute-new-page.md` (New Page Proposal), `suggestion-for-an-existing-page.md`, `new-value-list.md` (sub-teams only).
- The template auto-labels `draft` and assigns editors (kolbeeee, yogitaatigoy, martincritelli, marieelia). Filed via `gh issue create` (CLI), so label/assignees are set by the maintainers on triage.
- Web route: github.com/SAA-SDT/EAS-Best-Practices/issues/new/choose -> "New page proposal".
- Contribute page: https://saa-sdt.github.io/EAS-Best-Practices/docs/about#submitting-issues-including-content

## Status and follow-up (when they respond)
- **Status:** submitted; awaiting TS-EAS Editorial Board review/edit before any publication.
- **If they engage:** offer worked, validator-tested examples drawn from OpenRiC / the ahg-ric module, and align exact EAC-F element names to the released Tag Library and RiC-O IRIs to RiC-O v1.0 with the EAC-F sub-team. This feeds, and is fed by, the internal crosswalk build tracked in **openric/service#14**.
- **Parallel low-friction contributions available:** "Suggestion for an existing page" issues adding South African / African archival examples (the guide's examples skew Global North).
- **Standards-body angle:** Johan chairs the SA mirror committee to ISO/TC46/SC11 - a formal liaison between SC11 and TS-EAS is the higher-leverage move if the relationship develops.

## Artefacts
- Draft issue body: `heratio/stuff/eas-bpg/new-page-proposal-ric-crosswalk.md`.
- Internal crosswalk scope: `heratio/docs/reference/eacf-rico-crosswalk-scope.md`.

Related: [[project_eacf_ric_crosswalk]], [[project_openric_spec]], [[project_geo_seo_exposure]].

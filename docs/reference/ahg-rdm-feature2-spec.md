# ahg-rdm Feature 2 - Sovereign FAIR data repository + POPIA scan (UP/Tuks pilot)

Build spec and source of truth for the first slice of the `ahg-rdm` module: a sovereign (SA-hosted) research-data deposit with an AI-assisted, human-gated POPIA sensitivity scan. Target pilot: University of Pretoria (UP) research-data team, which currently runs its open data repository on Figshare (a foreign cloud - a POPIA exposure for personal-data-bearing datasets).

## Why this exists (one paragraph)
The SA national research-data mandate (NRF 2015 Open Access Statement) is aspirational - it uses "should", has no DMP requirement, and no enforcement. The real teeth come from elsewhere: institutions self-mandate (UCT requires DMPs for publicly-funded research; Wits attaches the NRF DMP form to grant applications), international funders enforce (NIH DMS plan is a Term and Condition of award; Wellcome, Horizon Europe, Gates), and POPIA applies to nearly all research data (it bites whenever anyone can re-identify a subject). UP runs research data on Figshare today. The wedge is NOT "replace Figshare" - it is "the sovereign vault + POPIA gate for the sensitive data that should never have been on a foreign cloud", coexisting with Figshare and expanding from there.

## Scope of this slice (thinnest demoable)
Deposit -> POPIA scan -> human gate -> access/embargo -> DOI -> landing page -> compliance status row.

Build as a new `ahg-rdm` package that **orchestrates existing services, never duplicates them.** Develop on heratio-dev; release from dev; prod pulls when validated. Net-new code, so it does not touch the locked paths - but it must stay a thin orchestration layer, not a new sub-suite.

## Reuse map (verified present in the tree)
- File ingest + storage: `ahg-ingest` `IngestService` + `digital_object`.
- PII / entity detection: `ahg-ai-services` `NerService` / `NerGazetteerService` (all LLM/NER calls routed through the AI gateway - never direct to a node).
- Embargo / access enforcement: `ahg-research` `OdrlService` + `OdrlPolicyMiddleware`.
- Dataset DOI: `ahg-core` DOI service / `DoiMintCommand` (DataCite).
- AI-finding + human-decision provenance: `ahg-research` `AiDisclosureService`.
- Project linkage: `ahg-research` project.
- Compliance status row: `ahg-reports`.

New code = a `Dataset` wrapper, a `PopiaScanService`, and the gated deposit flow. Everything else is wiring.

## The POPIA scan - designed for a legal-weight call
The scan carries legal weight (a missed exposure implicates us in a researcher's breach), and the local AI stack is not yet trustworthy. So the scan is **deterministic-first, AI-augmented, human-final:**
- **Deterministic detectors (the backbone, no LLM):** SA ID number (Luhn + embedded-date validation), email, phone, passport/account patterns. High precision, explainable, cannot hallucinate. This is the demo's headline finding.
- **NER (augmentation, lower trust):** `NerService` for names/locations/orgs, always labelled "AI-suggested" and always human-reviewed.
- **Special-category lexicon:** health/religion/biometric terms -> flag for review.
- **Human gate + provenance = the authority.** The scan NEVER auto-decides. It returns findings `{file, type, sample, confidence, method}` and a dataset verdict `CLEAR | PERSONAL | SPECIAL_CATEGORY`. A human confirms/overrides each finding and chooses a disposition (restrict / embargo / de-identify / release). A dataset with unresolved PERSONAL or SPECIAL_CATEGORY findings **cannot be set to open** - the UI forces a disposition. Every step is logged via `AiDisclosureService`: AI finding + model/version + human + decision + timestamp.

## Flow
1. Deposit files to a `Dataset` linked to a research project (`IngestService` + digital_object).
2. `PopiaScanService` scans each file (deterministic -> NER -> lexicon), returns findings + verdict.
3. Human-gate review screen: confirm/override findings, choose disposition. Open access blocked until PERSONAL/SPECIAL findings are resolved. Provenance logged.
4. Access/embargo applied via `OdrlService` per the disposition.
5. DataCite DOI minted on publish (existing DOI path).
6. Citable landing page (reuse existing show).
7. Compliance status row in `ahg-reports`: "POPIA-flagged - restricted - DMP-linked".

## Explicitly OUT of this slice
DMP tool (that is Feature 1, later), full dashboard (status row only), federation, automated de-identification (flag + human decides; manual redaction via existing tooling), Figshare migration, FAIR metadata beyond DataCite core.

## Demo dataset - 100% synthetic (non-negotiable)
**Never use real personal data** - depositing real PII to demo a POPIA tool is itself a POPIA breach. Generate a synthetic, realistic social-science/health study that exercises precision AND recall:
- `survey_responses.csv` - research variables plus name, **synthetic** SA ID number (format-valid but fake), email, phone. The ID number is the deterministic headline finding.
- `interview_transcripts/*.txt` - free-text names, locations, and health disclosures (special-category) and identifiable third parties -> NER + lexicon.
- `consent_forms.pdf` - scanned, names/signatures -> OCR -> NER (PII inside a PDF).
- `climate_measurements.csv` + `readme.txt` - clean, no PII -> negative control proving the scan does not flag everything.

The scan must catch structured PII (CSV), free-text PII (NER), PDF PII (OCR), special-category data - and pass the clean files.

## Demo script + win condition
Deposit the synthetic set -> scan flags the CSV (ID numbers, deterministic), transcripts (names+health), consent PDF (OCR->names); passes the climate CSV -> human restricts/embargoes the PII files -> DOI minted -> landing page -> compliance row shows "POPIA-flagged, restricted." Punchline: on Figshare, every one of those ID numbers would be on a foreign cloud, open.

**Win =** UP's RDM unit agrees to run this on one faculty's real datasets for a term, and the scan catches at least one real exposure they would otherwise have published.

## Buyer note
The RDM buyer is the UP Department of Library Services RDM team + the DVC: Research office - NOT the university archivist. The archives relationship provides the introduction, not the sale. Validate the POPIA-on-Figshare pain in one short conversation with the RDM unit before building beyond the demo.

## Build order
Feature 2 first (moat + unique feature + most reuse). Within it: deterministic PII detectors + the human-gate first (the demo's spine, lowest risk), then access/embargo + DOI + landing page, then the compliance row. Feature 1 (DMP) and Feature 3 (full dashboard) follow.

# Compliance divergence in 2026: EU loosens, South Africa tightens

Summary: In 2026 data-protection regimes are moving in opposite directions. The
European Union is simplifying and delaying its digital rulebook (GDPR and the AI
Act) through the "Digital Omnibus", while South Africa is expanding enforcement
and has brought immediately-binding health-data rules into force under POPIA.
For an international GLAM platform this confirms that jurisdictional compliance
must be a pluggable per-market module, not a single fixed "GDPR-shaped" core.

This note is a research reference for an article on compliance divergence and for
Heratio's per-market compliance module design. Dates and status were verified
against dated legal-sector sources in June 2026; the EU Omnibus figures describe
a proposal that was still in the legislative process at the time of writing, so
the final ratified text may differ.

## The EU direction: simplify and delay (Digital Omnibus)

The Digital Omnibus, proposed by the European Commission in November 2025, aims
to harmonise and reduce the burden of the overlapping EU digital framework
(GDPR, AI Act, ePrivacy, Data Act, NIS2). It is both a set of deadline delays and
a set of substantive changes to what the GDPR governs.

### AI Act deadline postponements (provisional agreement)

Per a Gibson Dunn briefing dated 27 May 2026, the provisional Omnibus agreement
postpones the AI Act high-risk obligations:

- Standalone high-risk systems (Annex III: recruitment, credit scoring, law
  enforcement, education, border control): from 2 August 2026 to 2 December 2027.
- High-risk systems embedded in regulated products (Annex I: medical devices,
  machinery, vehicles): to 2 August 2028.
- Article 50 transparency obligations proceed on 2 August 2026, with a four-month
  grace period for existing systems' watermarking until 2 December 2026.

Status: provisional at the time of writing. The changes take legal effect only on
formal adoption and publication in the Official Journal, expected before
2 August 2026. Verify the final ratified dates before relying on them.

### GDPR substantive changes proposed

The Omnibus would change what the GDPR actually governs, not just timelines:

- Pseudonymised data may fall outside the GDPR's scope where re-identification
  requires "disproportionate effort" and the holder is not reasonably likely to
  re-identify. The definition of "personal data" itself is not being narrowed;
  the shift is in the re-identification test. The EDPB and EDPS flagged this as a
  critical concern, warning that "disproportionate effort" is vague and could be
  used to reclassify clearly-personal data as non-personal.
- AI training gets an explicit legal basis: legitimate interest expressly covers
  training models on personal data, subject to enhanced safeguards and an
  unconditional opt-out for data subjects.
- A new Article 9(2)(k) would permit processing special-category data (health,
  biometrics, political opinion) for AI training under "appropriate safeguards".
- Breach notification is relaxed: the reporting threshold rises to breaches
  "likely to result in a high risk", and the maximum reporting window extends from
  72 to 96 hours. The EDPB is to publish a common notification template and a
  high-risk list.

### Why the EU is easing

Three drivers, per legal-sector and press analysis:

1. The supporting machinery was not ready: harmonised technical standards were
   delayed, and the Commission missed its own statutory February 2026 deadline for
   Article 6 high-risk classification guidance. Rules cannot be enforced where
   there is no certified path to comply.
2. Industry and member-state pressure, notably from Germany and manufacturers
   facing duplicative obligations across multiple laws, plus pressure from US tech
   and the US administration.
3. Competitiveness anxiety: the recurring critique that the EU regulates
   technologies it struggles to produce at scale.

Critics (EDPB/EDPS, the Jacques Delors Centre, IAPP commentary) argue the package
weakens protection and "heads in the wrong direction", especially on AI.

## The South African direction: expand enforcement (POPIA)

South Africa is moving the opposite way: more enforcement, and new binding rules
with immediate effect.

### Health information regulations now in force

The Regulations relating to the Processing of Data Subjects' Health Information by
Certain Responsible Parties, 2026 were published in Government Gazette No. 54268
and came into force on 6 March 2026, on the same day as publication, with no
transitional period.

Key features:

- Scope: eight categories of responsible parties, including insurance companies,
  medical schemes, scheme administrators, managed-healthcare organisations,
  administrative bodies, pension funds, employers, and institutions working for
  them.
- Contextualised security standard: technical and organisational measures must
  align with generally accepted information-security practices for the responsible
  party's own sector and size. This is proportionate, not fixed: a large insurer
  is held to financial-services practice; a small employer to a standard
  appropriate to its size.
- Confidentiality: processing must occur under a duty of confidentiality
  (legislation, professional code, employment relationship, or written agreement),
  per section 32(2) of POPIA.
- Cross-border transfers of health information are gated by section 72(1) of
  POPIA. The draft's data-subject notification rule for cross-border transfers was
  dropped in the final text, as were references to an individual's "sex life".
- Disposal: records must be disposed of so as to prevent unauthorised access after
  disposal.

### Wider POPIA enforcement trend

- The Information Regulator's 2026-2027 priorities intensify compliance monitoring
  and enforcement across public and private sectors.
- A proposed amendment would remove procedural steps that currently give
  organisations time to remedy non-compliance before sanctions, i.e. faster
  penalties.
- 2025 amended Regulations already allow administrative fines to be paid in
  instalments in defined circumstances. The POPIA administrative fine ceiling is
  R10 million.

## Implications for a per-market compliance module

The practical lesson for an international GLAM platform holding sensitive material
(donor health information, indigenous cultural and intellectual property,
biometrics in collections):

1. A "GDPR-shaped core" is now a moving, loosening target. Hardcoding EU
   assumptions into the platform core would bake in rules that are themselves
   being relaxed and delayed.
2. Per-market modules must encode divergence, not a common baseline: the EU's
   pseudonymisation-out and AI-training carve-outs versus South Africa's in-force
   health-data rules with same-day effect and R10m fines.
3. The recurring design primitives are configurable policy, not fixed rules:
   - a proportionate, sector-contextualised security standard (POPIA), and
   - a safeguards-plus-opt-out model for AI processing of personal and
     special-category data (EU Omnibus).
   Both map onto a policy engine (e.g. ODRL-style rights policies) rather than
   conditional code branches.
4. Cross-border transfer rules differ per regime and per data category (health
   versus general), so transfer gating belongs in the market module, keyed to data
   classification.

## Sources

Verified June 2026 against dated legal-sector and press sources:

- Gibson Dunn, "EU AI Act Omnibus Agreement: Postponed High-Risk Deadlines and
  Other Key Changes" (27 May 2026).
- White & Case, "EU agrees Digital Omnibus deal to simplify AI rules".
- IAPP, "EU Digital Omnibus amendments to GDPR to facilitate AI training miss the
  mark"; and "European Commission misses deadline for AI Act guidance on high-risk
  systems".
- Latham & Watkins, "Digital Omnibus: EU Commission Proposes to Streamline GDPR
  and EU AI Act".
- Compliance & Risks, "How the Digital Omnibus Proposes to Change the GDPR".
- Jacques Delors Centre, "The EU's Digital and AI Omnibus is Heading in the Wrong
  Direction".
- Moonstone, "New POPIA regulations on health information now in force"
  (Government Gazette No. 54268, 6 March 2026).
- Fasken, "Health Data Under the Microscope: New POPIA Regulations".
- IT-Online, "POPIA Health Information Regulations cross the finish line"
  (13 March 2026).
- Mayet & Associates, "POPIA Enforcement in South Africa: Key 2026 Developments".

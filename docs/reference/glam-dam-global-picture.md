# AHG GLAM/DAM global picture - shared cross-agent overview

**Summary.** The canonical, cross-cutting overview of the AHG's GLAM/DAM (AtoM/Heratio) work, so any agent or session - Claude Code, the Workbench agent, Codex, ahg-ai - can get the same picture from one place (KM). It carries the North Star/vision, the live projects and proposals, the critical cultural guardrails, the instance map, and the working rules. Deep detail for each item lives in its own `docs/reference/*.md`; this is the index. (Claude Code sessions also load a symlinked copy of this as memory across the Heratio-family instances; this KM doc is the equivalent for KM-querying agents.)

## North Star / vision (company-wide)

Everything the AHG does helps institutions **preserve, govern and prove memory** - we are in the **trust business**, not the archive-software business. Hook: **provenance is the new scarcity** (AI made having information free, so content stopped being evidence; provenance is what is now scarce). Category: **trustworthy memory is becoming critical infrastructure**. Position: **sovereign, verifiable memory**. Purpose: **the right to remember** (+ the right to restrict / not-digitise). The inversion: regulated AI must ground and cite provenanced sources, so the archive becomes an active **trust endpoint AI must cite** (the "Trust API"); reframe **OpenRiC** as the open trust-and-provenance protocol. Full: `ahg-strategic-angle-trustworthy-memory`.

## The instance family (map)

- **/usr/share/nginx/heratio** - Heratio master (Laravel). Dev + canonical memory + KM docs live here. heratio.org = demo.
- **/usr/share/nginx/archive** - "AtoM/Heratio": AtoM 2.x Symfony + AHG plugins. PSIS reference + Wits Archaeology/RARI. Scoped to archive/PSIS/archaeology only.
- **/usr/share/nginx/atom** - AtoM ANC: **migrated to a VM; the on-host copy is archived/defunct** - the live ANC runs in its own VM.
- **/usr/share/nginx/heratio-dev** - Heratio Dev (Laravel, DB heratio_dev). Develop app-code here, then push.
- **/usr/share/nginx/dam** - DAM (Laravel/Heratio), dam.theahg.
- **/usr/share/nginx/sasa** - SASA archive (Laravel/Heratio, DB sasa).
- Two stacks: archive (+ WDB) = Symfony AtoM; heratio/dam/sasa/heratio-dev = Laravel Heratio. Never copy Laravel code into the Symfony side - port it.

## Live projects & proposals (cross-cutting)

- **Mining museum** - living-research-engine + community hub (SADC migrant-labour reconnection the standout); costed brief v0.5. See `mining-museum-living-research-engine-concept`.
- **Bigibila Nations** - AHG build + Australian sovereign-hosting proposal (Indigenous data sovereignty; Lore Gate flagship) + a blind reliability-coder POC. See `bigibila-nations-development-proposal`. Cultural guardrails apply.
- Ongoing: RDM, RiC / OpenRiC, privacy/DPIA/redaction, exhibition + digital-twin + Lost Places reconstruction, records management (file plan/retention/NARSSA), #1388 TK/BC + ICIP term/object protocols, AI gateway, translation (af via NLLB), Spectrum -> Collections Procedure, GRAP heritage accounting, Archivematica bridge, Wits Archaeology / RARI.

## Critical cultural guardrails (all sensitive / Indigenous / community material)

- Cultural authority rests with the community/custodians, not the AHG. Technical access is not cultural permission.
- Never reproduce, publish, commercialise, or ingest into KM restricted/sacred/gendered knowledge. Keep controlled material local.
- No AI on cultural material without purpose-specific FPIC; no training on it.
- "Do not digitise" is a real, respected outcome. Preserve blind-coding independence where relevant.
- Terminology: Country, Lore, Nation, Cultural Authority, FPIC, ICIP. Align with the platform's ICIP/TK-BC enforcement (IcipAccessService, TermProtocol).

## Client / business / conference context

- CHT / We The People - hosting + SLA v1.1 (WordPress estate + AtoM catalogue); premium licences Elementor Pro / Crocoblock / Themify (pass-through).
- AHG partners: Dr Johan Pieterse + Alexio Motsi + Bandile Sizani (33.333% each); Alexio = NARSSA digitisation authority.
- Conferences - delivered: SASA 2026 (Polokwane, Best Participant), Rhodes 2026 (Makhanda). Forthcoming: NARSSA/DSAC, NSSF EDRM (Kampala), KARMA (Mombasa), GenDSs, AI Expo, UNILISA 2027.

## Working rules that apply everywhere

- Dev-first: application code on heratio-dev, then push; docs exempt. Releases are confirmation-gated; never name an AI provider in releases/commits.
- KM save = write `docs/reference/*.md` in the heratio repo (auto-ingest). Keep KM current.
- DOCX house standard (branded template, ToC, green-header captioned tables); plain hyphens only; byline Dr Johan Pieterse.
- All AI via the ai.theahg.co.za gateway - never a direct GPU node.

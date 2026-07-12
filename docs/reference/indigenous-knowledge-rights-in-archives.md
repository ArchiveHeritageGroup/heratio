# Making Indigenous / Traditional Knowledge Rights Machine-Enforceable in Digital Archives (AHG theme)

**Summary:** Legal frameworks describe obligations for protecting indigenous and traditional knowledge (IK/TK); a GLAM/archival platform has to *operationalise* them. This note captures AHG's position and a future Heratio thought-leadership theme: IK/TK rights should be machine-enforceable inside the repository, not merely documented. It complements the AI-RAM / preservation / epistemic-transparency series (Heratio blog #22 "The Model Was Never the Hard Part", #27 "Preservation", and the KARMA 2026 paper) and reinforces AHG's international / pan-African positioning.

## The gap: normative promise vs implementation

Instruments such as the AfCFTA Intellectual Property Protocol and national IK/TK statutes set out what *should* be protected and by whom, but the domestic, operational effect depends on institutions and systems actually enforcing it. The legal literature is now moving explicitly from the normative promise of these instruments to their implementation - for example the forthcoming article "Operationalising the AfCFTA Intellectual Property Protocol: Lessons from South Africa's Indigenous Knowledge Framework" by Tshimangadzo Donald Mukwevho (North-West University) and Prof. Lonias Ndlovu (University of Venda), in the *Journal of Law, Democracy and Development*. The systems-side counterpart of that argument is the subject of this note.

## AHG position: enforce, don't just describe

A digital archive that holds indigenous or community materials must do more than record that rights exist. It should:

1. **Provenance and community attribution** - capture and preserve who holds the knowledge, the originating community, and the custodial chain, as first-class, preserved metadata (PREMIS-style events and agents), not free-text notes.
2. **Traditional Knowledge Labels** - support community-authored labels (e.g. the Local Contexts TK / BC Labels model) that express culturally specific permissions and protocols (seasonal, gender, secret/sacred, attribution, non-commercial) which formal IP categories do not capture.
3. **Machine-enforceable rights policies** - express permissions and restrictions as executable policy that actually *gates* behaviour: viewing, reproduction, download, and reuse. In Heratio this is done with ODRL policies (`research_rights_policy`, `OdrlService`, `OdrlPolicyMiddleware`), enforced on archival-description viewing (`odrl:use`) and reproduction/printing (`odrl:reproduce`). No policy = open; admins can bypass; policies are per-record.
4. **Auditability** - every access and reproduction decision is logged and reviewable, so an institution can demonstrate (not merely assert) that community protocols were honoured.

## Why it matters for the market

- **Domestic effect** - the piece that makes a Protocol or statute real is enforcement at the point of use; the repository is that point.
- **Jurisdiction-neutral core, pluggable compliance** - IK/TK protection is not SA-specific. The core platform stays jurisdiction-neutral; national/regional IK regimes plug in as modules (consistent with AHG's international positioning), so the same enforcement machinery serves SADC, the wider AfCFTA bloc, and indigenous-rights regimes elsewhere.
- **Ties to the AI story** - the same provenance + audit primitives that make AI outputs accountable (epistemic transparency) also make community rights enforceable. One rights-and-provenance backbone, two payoffs.

## Future article hook (one line)

"The law says protect indigenous knowledge. The archive is where 'protect' becomes a setting that actually does something - or an empty promise."

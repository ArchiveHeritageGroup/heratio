# Community Protocols - Region Packs (TK/BC, #1388 / #1406 P5)

**Summary:** Heratio's TK/BC community-protocol engine is jurisdiction-neutral at its core; the *region pack* is the pluggable layer that carries a deployment's legal/jurisdiction context. This is the P5 layer of the community-protocols build (design #1388, build #1406). Region packs deliberately ship **no named communities and no invented community labels** - communities self-identify and are added by their own stewards.

## What a region pack is (and is not)

A region pack is a row in **`icip_region_module`** describing a jurisdiction and the public legal frameworks that apply there. It makes `region_module` (a column already on `icip_community`, `icip_tk_label_type`, `object_protocol`, `term_protocol`) a first-class, documented concept rather than a free-text field.

A region pack **is**:
- A `code` (e.g. `za`, `sadc`, `international`), display `name`, `jurisdiction`, a `frameworks` JSON list, and a `care_note`.
- Selectable in the community edit form (the `region_module` dropdown).

A region pack is **NOT**:
- A list of communities. Imposing community identity violates #1388 Principle 1 (self-identification) and the CARE Principles. Communities are created by the institution and, once they name a steward, governed by that steward (see P2c).
- A set of community-authored labels. Only a community may author its own TK/BC labels; the platform never invents them. The 22 base Local Contexts label types (in `icip_tk_label_type`) are the shared vocabulary; anything community-specific is authored through the governance UI.

## Packs seeded at first boot

Seeded idempotently by `AhgIcipServiceProvider::ensureRegionPacks()` (INSERT IGNORE by `code`):

| code | name | frameworks (public context only) |
|---|---|---|
| `international` | International (jurisdiction-neutral) | CARE Principles, UNDRIP, Local Contexts, SKOS |
| `za` | South Africa | IKS Act 6 of 2019; NIKSO; Nagoya Protocol / ABS; POPIA |
| `sadc` | SADC | ARIPO Swakopmund Protocol; Nagoya / ABS; CARE Principles |

## Adding another region

1. Add a row to `icip_region_module` (a new pack seed in `ensureRegionPacks()`, or an INSERT) with the region's `code`, `name`, `jurisdiction`, public `frameworks`, and a `care_note`.
2. Institutions in that region tag their communities to the pack via the community edit form.
3. Enforcement, provenance, the AI fence, and interop (OAI/RiC/portable export) are all in the neutral core and need no per-region change - a pack only supplies context and the `region_module` tag.

## Related

- Enforcement engine: `TermProtocolService` / `TermProtocolGate` (ahg-core).
- Governance: communities, stewards, CARE assignment log (ahg-icip, P2).
- AI fence: `TermProtocolGate::isModelEligible()` (P3).
- Interop: `CommunityProtocolSerializer` -> OAI `dc:rights`, RiC `lc:communityProtocols`, portable-export `data/ios.json` (P4).
- Design: `docs/reference/tk-bc-community-protocols-plugin-spec.md`, `indigenous-tk-bc-plugin-design.md`.

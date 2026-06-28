# AI System Inventory (EU AI Act) - User Guide

The **AI System Inventory** is the system-level register the EU AI Act is framed around. Where the model registry, risk register, oversight policies, and attestations are per-model or per-service, the inventory records the AI **systems** your organisation provides or deploys, each with its regulatory role, risk classification, lifecycle status, human-oversight measures, accountable owner, and review schedule.

It lives under **Admin → AI Compliance → System Inventory** (`/admin/ai-compliance/systems`). The route group is gated by `auth` only — any authenticated user can open and view the inventory; the create / edit / delete actions additionally require the matching ACL permission (`acl:create`, `acl:update`, `acl:delete`). Despite the `admin/` URL prefix, it is not restricted to administrators.

## Why it matters

The Act classifies obligations by **system** and by **risk tier** (Art. 6 high-risk classification, Art. 52 transparency tiers). To demonstrate compliance you need a single, maintained list of which AI systems you run, what tier each falls in, who is accountable, and when each was last reviewed. The inventory is that list; the model cards, risk register, and attestations hang off it.

## Fields

| Field | Meaning |
|---|---|
| Name | Short identifier for the AI system |
| Role | Your role for this system: `provider`, `deployer`, `importer`, `distributor` (Art. 3) |
| Risk classification | `prohibited`, `high`, `limited`, `minimal` (Art. 5 / 6 / 52) |
| Lifecycle status | `development`, `deployed`, `suspended`, `retired` |
| Intended purpose | What the system is for (Art. 6) |
| Deployment context | Where and how it is used |
| Human oversight | The oversight measures in place (Art. 14) |
| Owner | Accountable person or unit |
| Last / next review | Review schedule - drives the "review due" alert |
| Active | Whether the system counts toward review-due tracking |

## The dashboard

The index shows a count per risk tier (prohibited / high / limited / minimal) and a **Review due** banner listing active systems whose next review is overdue or within 30 days. Filter the table by risk tier or lifecycle status.

## Typical workflow

1. **Add system** - record each AI system, set its role and risk classification, and note the human-oversight measures.
2. Set a **next review date** so it surfaces in the review-due banner when it comes due.
3. Keep the **lifecycle status** current - move systems to `suspended` or `retired` rather than deleting them, so the inventory stays an audit trail.
4. Cross-reference the **risk register** and **model registry** for high-risk systems.

## Notes

- Risk tiers, roles, and lifecycle states are fixed by the regulation, so they are constrained on save (an out-of-range value cannot be stored).
- The inventory is standalone - it does not delete or alter any model/risk/attestation records.

Source: PSIS `ahgAiCompliancePlugin` parity (issue #1281). Related: the EU AI Act Digital Omnibus deadline note in `docs/reference/eu-ai-act-digital-omnibus.md`.

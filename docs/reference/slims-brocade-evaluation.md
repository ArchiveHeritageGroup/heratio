# SLIMS / Brocade evaluation and the Heratio migration path

**Summary.** In July 2026 the AHG was asked to evaluate SLIMS/Brocade, the MUMPS-based integrated library system behind roughly 1,300 South African public libraries, and to say whether it should be modernised in place or migrated. The depth-verified answer was migration, with Heratio as the target ahead of Koha, largely because Heratio already carries both an ILS module and the GRAP 103 heritage-asset accounting that was the actual compliance driver. This document records what was assessed, the decisions that came out of it, what the code-level comparison found in Heratio itself, and where all of it is now tracked. It exists because the engagement produced architectural decisions that outlive it, and until now none of them were queryable from KM.

## What SLIMS/Brocade is

A shared integrated library system of roughly 1.1 million lines of MUMPS, serving the provincial public-library services. It works. The problem is not function but maintainability: the local skills base for MUMPS has effectively gone, so the system is increasingly hard to change, and the accounting obligations attached to heritage and library assets have moved on while it has not.

That last point is what made this more than a routine ILS replacement. The compliance driver was heritage-asset accounting, not cataloguing, and it is the reason the shortlist came out the way it did.

## The engagement

Requested by the client. Working directory was `/usr/share/nginx/slims` on the build host, which holds the client material (SLAs, disaster recovery, provincial folders, take-on records, meeting notes) alongside three deliverables produced in July 2026:

- AHG-SLIMS-2026-001, SLIMS Brocade Technical Evaluation and Modernisation Options v1.0
- AHG-SLIMS-2026-002, Heratio vs SLIMS Brocade Functional Gap Analysis v1.0
- AHG-SLIMS-2026-003, SLIMS Brocade Modernisation, Combined Pack v1.0

The combined pack consolidates the first two and adds an implementation plan. Options were lettered; Option F is migration to Heratio.

The gap analysis was not a feature-matrix exercise. It read Heratio's source directly and verified each claim against the code, which is why it surfaced four genuine defects in Heratio's own library module as a by-product.

## Decisions that came out of it

**Heratio over Koha as the migration target.** Koha is the obvious open-source comparator and a capable ILS. It has no heritage-asset accounting. Heratio ships an ILS module and a GRAP 103 engine in the same platform, so the compliance driver is met without bolting a finance system onto a library system. For a jurisdiction-neutral reading: the same logic applies wherever public-sector heritage assets must be recognised, measured and disclosed under an IPSAS-derived standard, which is most of the Commonwealth and a good deal beyond it. The SA-specific part is which standard applies, and that is a pluggable module, not the core.

**One shared instance, branches as repository rows.** The foundational architecture decision, and the one most likely to be revisited by someone who was not there, so it is worth stating plainly. The 1,300 branches are modelled as `repository` rows inside a single Heratio instance, not as 1,300 instances. That buys a single scalable Elasticsearch index, a union catalogue at no extra cost, and the substrate for branch-aware circulation. Per-instance deployment would have given none of those and 1,300 upgrade paths.

**Heratio standardises on `library_item`.** Made during this work and since applied in full. The bibliographic model is `library_item` and its satellites; the parallel `library_biblio_*` scaffold that had been sketched in code but never migrated is gone.

## What the comparison found in Heratio

Four defects, all Heratio-side. The parity twin filed against the AtoM plugin repository (`atom-ahg-plugins` #188) confirmed the Symfony side was unaffected and needed no change.

All four are now closed:

| Issue | Defect | Outcome |
| --- | --- | --- |
| #1411 | `AuthorityControlService::linkToItem()` transposed `library_item_id` and `authority_id` in its dedup guard, so it almost never matched and the follow-on insert hit the unique index and threw | Guard corrected to match the index; migration recomputes `linked_count` |
| #1412 | Two competing FRBR work-key schemas, `work_key` plus `library_work_override` against an unused `frbr_work_key` plus `library_item_frbr_override` | Dead pair dropped, live pair kept |
| #1413 | `Z3950Service::importMarc()` wrote to `library_biblio_*` tables no migration creates | Rewritten onto `library_item` via `CopyCataloguingService`. Triage had called it dead code; it was not, the Z39.50 import buttons were actively broken |
| #1414 | BIBFRAME export read the same phantom scaffold behind a `hasTable` guard, so it degraded to permanently empty rather than crashing | New `BiblioWorkRepository` projects the catalogue onto the BIBFRAME hierarchy |
| #1417 | Split from #1414: the FRBR surface still read the phantom scaffold | Driven off `library_item` through the same repository |

Two things about this are worth carrying forward. First, the shared root cause was scaffolding drift: a bibliographic model sketched in migrations and code, never wired, left to sit alongside the real one. The same pattern shows up again in the heritage packages, where `ipsas_heritage_asset` and `heritage_asset` are two registers split across two packages. Second, verification changed the story in three of the five cases. The original agent triage was directionally right and wrong in its details, and only re-reading the code caught that. Treat a findings list as a starting point, not a result.

## The gap register, and where it is tracked

The combined pack carries a prioritised register of what Heratio would need to serve as a national public-library ILS. As of August 2026 the whole register is filed as issues on the Heratio repository:

| Band | Issue | Contents |
| --- | --- | --- |
| Blocker | #1473 | Branch-aware circulation |
| High | #1474 | Fine payment and waiver plus cash-desk POS, SMS and print notice channels, federated authentication, offline circulation, physical stocktake feeding GRAP impairment |
| Medium | #1475 | Loan-rules engine depth and editor, SIP2 self-check, authority control depth, ILL supplier routing and the inbound EDI decoder, invoicing sub-ledger, classification schedules, GRAP engine completion |
| Low | #1476 | Circulation statistics, label printing, canonical MARC persistence |
| Bug | #1477 | Loan Rules screen displays fabricated values, split out of #1475 |

Each issue carries file and line evidence verified against `main` in August 2026 rather than the evaluation's original wording, and in several places the verification found the gap wider than the register described. The findings worth knowing without opening the issues:

Circulation has no concept of a branch at all. `library_loan_rule` is unique on material type and patron type, `library_patron` has no home branch and a globally unique card number, and the two branch columns that do exist (`library_copy.branch` and `library_hold.pickup_branch`) are free text with zero writers anywhere in the repository. The tenancy substrate is real but stops at the catalogue: `repository_id` is used throughout the library service and controller and never once in circulation.

Several gaps are wiring rather than absence. `library_fine` already has the complete payment and waiver column set and not one of those columns is ever written. The notice tables already carry an `email|sms|print` channel and the overdue service hard-codes email, while a working SMS gateway sits in `ahg-security-clearance` and a second-channel pattern already exists in the same library package for serials. That shape recurs often enough to be a useful prior when scoping.

Federated authentication is the opposite case and a regression against SLIMS. The LDAP settings screen collects host, port, base DN and bind attribute, and no LDAP bind implementation exists anywhere in the codebase. `/cas/login` and `/oidc/login` are registered routes that redirect straight back to the local login form. SAML is absent.

Genuinely absent, with no substrate: SIP2, offline circulation, physical stocktake, label printing, and any aggregate circulation statistics. On that last one, note the decoy: `LibraryUsageService` and its command do produce usage statistics, but they implement COUNTER 5 and SUSHI, which is electronic-resource vendor usage and says nothing about physical circulation.

## Working notes for anyone picking this up

`packages/ahg-library/` is a locked path, so changes there need `./bin/unlock` on the specific file first. Application code is developed on `heratio-dev` and pushed from there, never edited directly on the production tree.

The evidence in the issues is migration and source level. None of it was re-verified against a live database, and anyone acting on a schema claim should confirm the live table first.

The Heratio repository is public. The handover document, these issues and this reference are all visible, so keep commercial detail, pricing and client politics out of anything written here.

## Related

`docs/slims-migration-handover.md` in the Heratio repository is the primary source for the defect list and the gap register. The three deliverables are the fuller record and are not in any repository.

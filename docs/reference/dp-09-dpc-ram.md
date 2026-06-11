# DPC Rapid Assessment Model (RAM) and organisational maturity in Heratio

**Summary.** The Digital Preservation Coalition's Rapid Assessment Model (DPC RAM,
currently v2.0) is a maturity-modelling tool that lets an organisation
self-assess its digital-preservation *capability* across a set of service and
organisational sections - things like organisational viability, policy and
strategy, legal basis, IT capability, content / metadata, preservation planning,
bitstream preservation, and content preservation. Each section is scored on a
0-to-4 maturity scale, from "minimal awareness" up to "optimised". RAM is
complementary to the NDSA Levels: NDSA focuses on concrete bitstream / content
controls, while RAM takes a broader organisational and service-management view.
Heratio's shipped self-assessment scores the *technical* NDSA areas directly from
the database; the organisational dimensions RAM emphasises are addressed through
adjacent Heratio modules (policy, audit, rights, governance) but are not yet a
single RAM scorecard.

## The concept

DPC RAM splits digital-preservation maturity into two families of sections:

- **Service capabilities** - the hands-on functions: acquisition / transfer /
  ingest; bitstream preservation (storage and fixity); content preservation
  (format management and migration); metadata management; discovery and access.
- **Organisational capabilities** - the enabling context: organisational
  viability; policy and strategy; legal basis; IT capability; community
  engagement.

Each is rated 0 (minimal) to 4 (optimised). The value of RAM is that it surfaces
*organisational* gaps (no preservation policy, no clear legal basis for keeping
content) that a purely technical checklist misses.

## How Heratio addresses this

Heratio does not ship a literal DPC RAM scorecard, but the capabilities RAM asks
about map onto real, verifiable Heratio features:

- **Bitstream preservation** (RAM service capability) - fixity, checksums, and
  replication. See `dp-07-fixity-and-integrity` and
  `dp-11-storage-replication-ocfl`.
- **Content preservation** - format identification and migration. See
  `dp-06-pronom-format-identification` and `dp-10-significant-properties`.
- **Metadata management** - PREMIS, METS, and the descriptive standards
  exporters. See `dp-03-premis-preservation-metadata` and `dp-04-mets-packaging`.
- **Acquisition / ingest** - the ingest wizard and scanner capture pipeline
  (`ahg-ingest`, `ahg-scan`) and the OAIS package lifecycle. See
  `dp-02-sip-aip-dip-lifecycle`.
- **Policy and strategy** - the preservation policies view at
  `GET /admin/preservation/policies` (route `preservation.policies`).
- **Legal basis** - PREMIS rights (`PremisRightsService` /
  `AhgPremisRights`) plus the rights / ODRL policy machinery and donor-agreement
  modules, which together capture the permission basis for holding and acting on
  content.
- **IT capability / control** - the ACL model, security classification, and the
  audit-log tables that the maturity assessment reads when scoring Control.
- **Closest live scorecard.** The NDSA-Levels self-assessment at
  `GET /admin/preservation-maturity` is the existing maturity surface (see
  `dp-08-ndsa-levels`); it covers the technical heart of what RAM's service
  capabilities measure.

## Gaps / not yet

- There is no dedicated DPC RAM v2.0 scorecard screen in Heratio. The
  *organisational* RAM sections (organisational viability, community engagement,
  long-term funding) are inherently procedural and are not something Heratio can
  score from its database; they would need an operator-completed questionnaire.
- A future enhancement could add a RAM view that reuses the NDSA scorer's
  technical evidence and prompts the operator for the organisational sections, to
  produce a complete RAM v2.0 profile. That does not exist today.

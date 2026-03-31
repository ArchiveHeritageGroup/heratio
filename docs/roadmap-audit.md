# Heratio RM/DM Roadmap — Audit Results

## Roadmap vs Reality

### Corrections to the Roadmap

| Claim in Roadmap | Actual Status | Correction |
|---|---|---|
| Workflow & approvals = "Not built" | **WRONG** — ahg-workflow has 29 routes, 23 views, dashboard, gates, steps, admin | Already built with full workflow engine |
| Email capture = "Not built" | Correct — no email capture package exists |
| Legal holds = "Not built" | Partially correct — package dir exists but no routes/views |
| Vital records flagging = "Not built" | Correct |
| Record declaration = "Not built" | Correct |
| Retention schedule = "Not built" | Correct — basic only, no standalone module |
| Disposal execution = "Partial" | Correct — basic delete exists but no formal disposal workflow |
| Destruction certificates = "Not built" | Correct |
| Review triggers = "Not built" | Correct |
| Format identification PRONOM = "Not built" | Correct |
| Fixity checking = "Not built" | Partially wrong — preservation plugin has checksum generation route |
| BagIt export = "Not built" | Correct |
| TrueNAS NFS = "Done" | **Correct** — /mnt/nas/heratio/ exists and mounted |
| OAIS SIP/AIP/DIP = "Partial" | Correct — preservation plugin has 31 routes but pipeline incomplete |
| Fuseki "17,358+ triples" | **WRONG** — actually **17,912,054 triples** (17.9M not 17K) |
| Spectrum 5.0 = "~60%" | **Higher** — spectrum plugin has 28 routes, full condition/provenance/risk/workflow |
| ICIP = "Not built" | **WRONG** — ahg-icip has 24 routes, communities, consent, restrictions, protocols |
| WCAG 2.1 Level AA = "Done" | Cannot verify — no automated WCAG audit run |
| Natural language discovery = "On horizon" | Partially done — semantic search via Qdrant on ric.theahg.co.za |
| Vector similarity Qdrant = "On horizon" | **WRONG** — already running, 693 records indexed, semantic search working |

### Summary of Corrections

| Category | Roadmap Says | Reality |
|---|---|---|
| Capabilities marked "Done" | 21 | **25** (workflow, ICIP, Qdrant, checksum already built) |
| Capabilities marked "Partial" | 9 | **8** (one upgraded to Done) |
| Capabilities marked "Not built" | 13 | **10** (3 were already built) |
| Fuseki triples | 17,358 | **17,912,054** |

### Standards Compliance — Corrected

| Standard | Roadmap Says | Corrected |
|---|---|---|
| ISO 15489-1:2016 | ~50% | ~55% — workflow exists |
| MoReq2010 | ~35% | ~40% — workflow + audit already there |
| DoD 5015.2 | ~25% | ~30% — audit trail + security covers more |
| Spectrum 5.0 | ~60% | ~75% — full condition/provenance/risk/workflow |
| ICIP | "Not built" | **~40%** — communities, consent, restrictions exist |

### Competitive Position Table — Verified

The competitive position table is accurate. Heratio IS the only open-source platform covering RM + GLAM + RiC-O.

---

## What's Actually Not Built (true gaps)

1. **Email capture connector** — no package
2. **Formal record declaration** — no declaration workflow
3. **Vital records registry** — no flagging mechanism
4. **Legal hold enforcement** — package shell exists, no implementation
5. **Retention schedule authority records** — no standalone management
6. **Event-based review triggers** — no trigger engine
7. **Disposal execution workflow** — no formal destruction process
8. **Destruction certificates** — no PDF generation
9. **Format identification (PRONOM)** — no file format registry
10. **BagIt packaging** — no export format
11. **Archivematica integration** — not connected
12. **Hash chaining** — no blockchain/chain integrity

---

## GitHub Issue Text

```
## Heratio RM/DM Roadmap — Full Lifecycle Platform

### Overview

Build Heratio into a full records management + digital memory platform covering ISO 15489, MoReq2010, DoD 5015.2, ISAD(G), RiC-O, and Spectrum 5.0 end-to-end.

No other open-source platform covers both RM and GLAM description with RiC-O RiC (Records in Contexts) linked data.

### What's Already Built (25 capabilities)

- Born-digital ingestion (ahg-dam, 17 routes)
- Bulk import CSV/XML/EAD (ahg-export, 9 routes)
- Scan/HTR capture (port 5006, TrOCR, 47 routes)
- NER metadata extraction (port 5004, 47 routes)
- Unique identifier assignment (core)
- Audit trail (ahg-audit-trail, 9 routes)
- File plan / classification scheme (ahg-function-manage, 12 routes)
- Security classification (ahg-acl, 48 routes)
- RiC function classification (ahg-ric, 28 routes, Fuseki 17.9M triples)
- Version control (core)
- Workflow & approvals (ahg-workflow, 29 routes, gates, steps)
- Record relationships via RiC-O (Fuseki RiC-O triplestore)
- OAIS preservation pipeline (ahg-preservation, 31 routes)
- ISAD(G) multi-level description (81 routes, 108 views)
- ISAAR-CPF authority records (49 routes, 31 views)
- RiC-O RiC-O triplestore (17.9M triples, graph explorer, SPARQL)
- IIIF delivery (34 routes)
- 3D objects (27 routes)
- HTR/OCR (TrOCR fine-tuned, 736 training pairs)
- Full-text search (ahg-search, 11 routes)
- Voice commands (11 languages)
- Semantic search via Qdrant (693 records indexed)
- OAI-PMH (ahg-oai)
- Research portal (ahg-research, 101 routes)
- ICIP indigenous cultural material (ahg-icip, 24 routes)
- Spectrum 5.0 museum objects (ahg-spectrum, 28 routes)

### P1 — Harden Existing (4-6 weeks)

- [ ] Legal hold flag (table column + UI) on information objects
- [ ] Hash chaining + fixity checksums (extend ahgAuditTrailPlugin)
- [ ] Destruction certificate generation (PDF, admin hub)
- [ ] Event-based retention triggers (date + event)
- [ ] Formal record declaration workflow (status change)
- [ ] Vital records flagging (metadata field + browse filter)

### P2 — Records Management Plugin (10-14 weeks)

- [ ] Retention schedule authority records (MoReq2010 Chapter 3)
- [ ] Disposal classes linked to retention schedule
- [ ] Multi-step disposal approval workflow (recommend - authorise - execute)
- [ ] Disposal action execution (destroy/transfer/retain)
- [ ] Destruction verification (DoD 5015.2)
- [ ] Review triggers (date-based + event-based)
- [ ] File plan management UI
- [ ] Email capture connector
- [ ] Vital records registry
- [ ] ISO 15489 / MoReq2010 / DoD 5015.2 compliance reporting

### P3 — Preservation Plugin (8-10 weeks)

- [ ] BagIt packaging for transfer
- [ ] PRONOM format identification
- [ ] Archivematica bridge/integration
- [ ] OAIS SIP/AIP/DIP formalisation
- [ ] Fixity checking with scheduled verification
- [ ] Transfer to archives workflow

### Standards Target

| Standard | Current | Target |
|---|---|---|
| ISO 15489-1:2016 | ~55% | 95% |
| MoReq2010 | ~40% | 85% |
| DoD 5015.2 | ~30% | 80% |
| OAIS ISO 14721 | ~40% | 90% |
| Spectrum 5.0 | ~75% | 95% |
| ICIP | ~40% | 80% |

### Labels

`enhancement` `records-management` `roadmap` `lifecycle`
```

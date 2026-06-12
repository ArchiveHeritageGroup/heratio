# Digital preservation knowledge base - index (dp-*)

**Summary.** This is the index for Heratio's curated digital-preservation
knowledge layer: a concise, authoritative, *Heratio-contextualised* set of
articles covering the core concepts of long-term digital preservation (OAIS,
PREMIS, METS, BagIt, PRONOM format identification, fixity and integrity, the
NDSA Levels, DPC RAM, significant properties, the SIP/AIP/DIP ingest lifecycle,
storage and replication, and web archiving / WARC). Each article front-loads a
plain-language summary of the concept, then has a "How Heratio addresses this"
section that points at the *real* feature, route, and CLI command in this
codebase, and an honest "Gaps / not yet" note where Heratio does not yet cover
the topic. The set is jurisdiction-neutral and standards-first. It exists so the
in-product AI assistants, Copilot, and the /help search answer preservation
questions grounded in what Heratio actually does, not in generic theory.

First slice of epic heratio#1243 ("Ground Heratio's AI assistants in a
digital-preservation knowledge layer"). Docs-only; the epic stays open.

## Articles

| Slug | Topic |
| --- | --- |
| `dp-01-oais-reference-model` | OAIS reference model (ISO 14721), functional entities, the information-package idea |
| `dp-02-sip-aip-dip-lifecycle` | SIP / AIP / DIP information packages and the ingest -> archive -> access lifecycle |
| `dp-03-premis-preservation-metadata` | PREMIS preservation metadata - objects, events, agents, rights |
| `dp-04-mets-packaging` | METS structural / packaging metadata and PREMIS-in-METS |
| `dp-05-bagit-packaging` | BagIt (RFC 8493) transfer packages and validation |
| `dp-06-pronom-format-identification` | PRONOM / PUID format identification and the format risk registry |
| `dp-07-fixity-and-integrity` | Fixity, checksums, integrity verification and self-healing |
| `dp-08-ndsa-levels` | NDSA Levels of Digital Preservation (v2.0) self-assessment |
| `dp-09-dpc-ram` | DPC Rapid Assessment Model (RAM) v2.0 organisational maturity |
| `dp-10-significant-properties` | Significant properties and format normalisation / migration |
| `dp-11-storage-replication-ocfl` | Storage, geographic replication, and OCFL preservation storage |
| `web-archive-warc` | Web archiving (WARC 1.1): the single merged surface - both capture modes (archive a URL + capture a record page), the reusable ahg-core capture + replay engines, the single warc_capture table, and in-app replay from the stored WARC |

## How to read these

Each `dp-*` article is standalone. The "How Heratio addresses this" sections
cite real files and routes verified against the codebase at the time of writing.
Where Heratio genuinely does not implement a concept (most notably WARC web
archiving), the article says so plainly rather than overstating coverage.

## Standards referenced (jurisdiction-neutral)

OAIS (ISO 14721), PREMIS 3.0, METS, BagIt (RFC 8493), PRONOM, ISO 15489
(records management), the NDSA Levels of Digital Preservation v2.0, the DPC
Rapid Assessment Model v2.0, OCFL v1.1, and WARC (ISO 28500). These are
international standards; Heratio treats them as core, with any country-specific
compliance regime sitting alongside as a pluggable module.

> Heratio Help Center article. Category: Technical / Integration.

# Cite this record - bibliographic citation export

## Overview

Every published record has a "Cite this" surface that gives you a ready-made bibliographic reference and downloadable citation files for the common reference managers. Point a researcher at the record's cite page to read a formatted reference and copy it, or download a `.bib`, `.ris`, `.json`, or `.dc.xml` file and import it straight into Zotero, Mendeley, EndNote, a LaTeX/BibLaTeX bibliography, or any CSL-aware tool.

These surfaces are open data: no API key, read-only, published records only. The downloadable formats are cross-origin (CORS) open, so a browser tool on any site can fetch them.

---

## The cite page

**GET /cite/{idOrSlug}**

`{idOrSlug}` is the record's slug (the same identifier used in its public page address) or its numeric record id. The page shows:

- a formatted reference with a **Copy** button,
- a preview and **Download** / **Copy** button for each machine format.

A browser lands on this themed page. An unknown, unpublished, or root record returns a clean 404 page - never an error.

---

## The download formats

| URL | Format | Content-Type | Import into |
|---|---|---|---|
| `/cite/{idOrSlug}.bib` | BibTeX | `application/x-bibtex` | LaTeX / BibLaTeX, JabRef, Zotero, Mendeley |
| `/cite/{idOrSlug}.ris` | RIS | `application/x-research-info-systems` | EndNote, Zotero, Mendeley, RefWorks |
| `/cite/{idOrSlug}.json` | CSL-JSON | `application/vnd.citationstyles.csl+json` | citeproc, Zotero, pandoc |
| `/cite/{idOrSlug}.dc.xml` | simple Dublin Core (OAI-DC) | `application/xml` | OAI-DC / Dublin Core tooling |

Each file maps the record's metadata as follows:

| Citation field | Source | BibTeX | RIS | CSL-JSON | Dublin Core |
|---|---|---|---|---|---|
| Title | record title | `title` | `TI` | `title` | `dc:title` |
| Author(s) | creators (the linked actors) | `author` (` and `-joined) | `AU` (one per author) | `author` (`literal`) | `dc:creator` (one each) |
| Year | a 4-digit year from the date | `year` | `PY` | `issued.date-parts` | - |
| Date | the record's display/normalised date | `note` | `DA` | `issued.raw` | `dc:date` |
| Publisher | holding repository | `publisher` + `howpublished` | `PB` | `publisher` + `archive` | `dc:publisher` |
| Identifier | archival reference code | `number` | `CN` | `call-number` | `dc:identifier` |
| URL | the record's public page | `url` | `UR` | `URL` | `dc:identifier` |
| Item type | level of description | `@misc` (`type=Collection` for a fonds/collection/series) | `TY  - GEN` | `manuscript` / `collection` | `dc:type` `Text` / `Collection` |

---

## Honest, never fabricated

A field with no value is simply left out. If a record has no recorded creator, no author line is written (no invented "Anon"); if it has no date, no year or date field appears. The reference reflects exactly what the record holds.

## Citation styles

The on-screen reference is a neutral archival reference (creator, title, date, identifier, holding repository, URL). For a specific house style - Chicago, APA, MLA, and so on - download a machine format and let your reference manager apply the style. Citation styling is not locale-locked; the formats are international standards.

---

## Discovery

The cite surface is listed in the Open Memory Protocol capabilities document at **GET /open-data/protocol** (surface id `cite`), alongside the linked-data entity endpoints, the bulk dumps, OAI-PMH, and the rest, so a machine can discover it by fetching one URL.

## Safety

Read-only (no database writes). Every value is escaped for its format - BibTeX special characters, RIS line tags, and XML entities - so a record title can never inject into the downloaded file. Published records only; a draft is never exposed.

# ahg-biblio-bf

> BIBFRAME 2.0 integration for Heratio. Converts bibliographic catalogue records to/from BIBFRAME RDF via the OpenRiC RiC-O service layer.

## Package purpose
Serialises AHG bibliographic catalogue records to the Library of Congress BIBFRAME 2.0 vocabulary and converts incoming BIBFRAME RDF for import back into the Heratio catalogue. All round-trips go through OpenRiC for canonical RiC-O handling.

BIBFRAME 2.0 model (Library of Congress):
- **Work** — a distinct intellectual or artistic creation
- **Instance** — a specific realisation of a Work (edition, format)
- **Item** — a concrete copy of an Instance
- **Agent** — a person or corporate body associated with a Work

## Status
- **GH Issue:** https://github.com/ArchiveHeritageGroup/heratio/issues/760

## What's implemented
- [x] `composer.json` scaffold
- [x] `BibframeService` — full BF <-> Heratio conversion (catalogToRdf, importRdf, validateRdf)
- [x] `BibframeSerializer` — InformationObjectFetcher-based serializer for archival description
- [x] `BibframeController` + `BiblioBfController` (two controllers)
- [x] `AhgBiblioBfServiceProvider` with web routes + view namespace
- [x] Routes: index, export, import, validate, agent pages
- [x] Views: index, export, import, validate, agent
- [x] `config/ahg-biblio-bf.php` config defaults
- [x] README

## What's missing (TODO)
- [ ] Full information-object (archival description) serialization via BibframeSerializer
- [ ] EasyRdf/integration for Turtle / N-Triples / JSON-LD output (currently stubs)
- [ ] OpenRiC proxy endpoints wired (stub in proxyToOpenric)
- [ ] PSIS sidebar/footer link registration
- [ ] Help article in `docs/help/`

## References
- [BIBFRAME 2.0 Vocabulary](https://www.loc.gov/standards/bibframe/)
- [BIBFRAME 2.0 Documentation](https://www.loc.gov/standards/bibframe/docs/)
- [OpenRiC service](../openric/) — RiC-O authoritative layer

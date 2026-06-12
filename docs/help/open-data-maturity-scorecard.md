> Heratio Help Center article. Category: Technical / Integration.

# Open Data Maturity Scorecard

## Overview

The platform publishes a public scorecard that grades its own open-data offering against Tim Berners-Lee's well-known **5-star Open Data deployment scheme** (https://5stardata.info/). For each star, the scorecard shows the concrete, live surfaces that prove it - the real URLs you can fetch right now - so the rating is self-verifying rather than asserted.

The scorecard is open data itself: no API key, read-only, and cross-origin (CORS) open so a browser app on any site can fetch it. It performs no database access, so it cannot fail over record data.

---

## The endpoint

**GET /open-data/maturity** (machine view at **GET /open-data/maturity.json**)

A browser visiting `/open-data/maturity` sees a readable HTML scorecard, with the star rating, the requirement for each star, and the evidence surfaces linked. A data client (or the explicit `.json` URL) gets a JSON document shaped as a `schema:Rating` with a `stars` array, each carrying `achieved` (true/false) and an `evidence` list of the surfaces that prove it.

```bash
# JSON scorecard
curl -s "https://your-site.example/open-data/maturity.json"
```

---

## The five stars and their evidence

| Star | Requirement | Evidence surfaces cited |
|---|---|---|
| 1 - Open licence | Data on the web under an open licence | The CC-BY-4.0 licence plus the public discovery / dataset / graph surfaces |
| 2 - Machine-readable | Structured, machine-readable data | The bulk CSV and JSON-LD dataset dumps and the schema.org Dataset descriptor |
| 3 - Open format | A non-proprietary open format | The JSON-LD / Turtle / RDF-XML graph and the combined CIDOC-CRM (ISO 21127) Turtle dump |
| 4 - URIs | URIs to denote things | The dereferenceable `/id/{record}`, `/id/actor/{slug}` and `/id/term/{slug}` entity URIs |
| 5 - Linked data | Linked to other data | The VoID discovery document, the Records-in-Contexts and CIDOC-CRM vocabularies, and the `sameAs` / `seeAlso` outbound links carried in the entity graphs |

A full install scores **5/5**. The evidence for every star is drawn from the same canonical surface list that drives the open-data protocol index, so the scorecard can never claim a surface the platform does not actually serve. On a slimmer install where a star's evidence surface is absent, that star is honestly reported as **not achieved** rather than asserted.

---

## Notes for integrators

- Read-only and requires no authentication.
- It is a capabilities scorecard, not a data export - it describes what the platform offers, not the records themselves.
- Responses set `Access-Control-Allow-Origin: *`, so browser-based apps can fetch them directly.
- Every URL is built relative to the platform's own host, never a hardcoded address.
- See also the open-data protocol index at **/open-data/protocol** (which now lists this scorecard as one of its surfaces) and the DCAT data catalogue at **/data/catalog**.

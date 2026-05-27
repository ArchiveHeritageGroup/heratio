# ahg-z3950 — Z39.50 Client and Server

Z39.50 client (search) and server (expose Heratio catalogue over Z39.50) for the Heratio library ecosystem.

## Protocol

Implements the Z39.50 information retrieval protocol using the `yaz` PHP extension (pecl install yaz). The client searches remote Z39.50 targets (SRU, OCLC, national libraries). The server exposes the Heratio `library_biblio_*` tables via the bib-1 attribute set.

## Features

- **Client** — connect to any Z39.50 target, run searchable queries using bib-1 attribute set, import results into Heratio.
- **Server** — serve Heratio catalogue records on a configurable port (default: 9999) as a Z39.50 server.
- **Admin UI** — manage target profiles, view connection logs, track query stats.
- **MARCMaker import** — parse Z39.55 returned records in USMARC/MARC21 format and persist into Heratio.

## Routes

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| GET | /z3950 | index | - |
| GET | /z3950/search | search form | auth |
| POST | /z3950/search | run query | auth |
| GET | /z3950/result/{resultSet} | browse result set | auth |
| GET | /z3950/import/{resultSet} | import one record | auth |
| POST | /z3950/import | batch import | auth |
| GET | /z3950/admin | admin: targets + stats | auth |
| GET | /z3950/target/create | add target | auth |
| POST | /z3950/target | save target | auth |
| DELETE | /z3950/target/{id} | delete target | auth |

## Configuration (config/ahg-z3950.php)

| Key | Default | Description |
|-----|---------|-------------|
| `server.enabled` | `false` | Enable Z39.50 server |
| `server.host` | `0.0.0.0` | Bind address |
| `server.port` | `9999` | Z39.50 server port |
| `client.timeout` | `30` | Connection timeout (seconds) |
| `client.max_records` | `100` | Maximum records per search |
| `client.syntax` | `USmarc` | Default record syntax (USmarc, SUTRS, XML) |

## Requires

- `yaz` PECL extension (`pecl install yaz`)
- `php-yaz` system package on Ubuntu/Debian

## Related packages

- `ahg-biblio-bf` — BIBFRAME 2.0 ingest/export
- `ahg-biblio-frbr` — FRBR conceptual model
- `ahg-library` — base library package (KBART, COUNTER/SUSHI)
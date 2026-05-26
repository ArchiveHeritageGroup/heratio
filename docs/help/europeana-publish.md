> Heratio Help Center article. Category: Federation.

# Europeana EDM Publish

**Version:** 1.0
**Date:** 2026-05-26
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

The Europeana EDM publish surface lets your repository deliver bulk
exports to the Europeana harvester in the Europeana Data Model (EDM)
RDF/XML format. Every published Information Object becomes one EDM
record file; the bundle ships with a sitemap and is packed into a
single zip ready for upload.

This is one of three federation-publish surfaces Heratio supports
alongside OAI-PMH (`/oai`) and the ResourceSync capability list
(`/.well-known/resourcesync`). Use Europeana publish when the receiving
aggregator prefers a periodic bulk drop rather than continuous OAI
harvest.

---

## 2. Where to find it

`Admin > Federation > Europeana EDM Publish` (or directly at
`/federation/europeana`).

The dashboard shows:

- Records in the last export
- Bundle size
- Last-finish timestamp (UTC)
- Run status (`success` / `error` / `running`)
- Run history (last 10 runs)
- "Generate now" button - kicks off a synchronous export
- "Download bundle" link - serves the most recent zip

---

## 3. What gets exported

Every Information Object whose publication status is `Published`
(status type 158, status_id 160). Draft records are excluded. The same
filter the OAI-PMH server uses, so an OAI subscriber and the Europeana
bundle see the same record set.

Each per-record file carries:

- `edm:ProvidedCHO` - the cultural-heritage object with dc:title,
  dc:description, dc:identifier, dc:creator (URI ref),
  dc:subject, dc:date, dc:language, dc:rights, dc:format,
  dc:contributor, edm:type, and dcterms:spatial.
- `ore:Aggregation` - the providing wrapper with edm:dataProvider,
  edm:provider, edm:isShownAt (your show page), edm:isShownBy
  (the primary access copy), edm:object (the thumbnail), and
  edm:rights.
- `edm:WebResource` - one per digital surrogate, with dc:format
  and edm:rights.
- `edm:Agent`, `edm:Place`, `edm:TimeSpan` - referenced by URI
  where applicable.

---

## 4. edm:type vocabulary

Europeana requires every record to declare exactly one of:

- `TEXT` - default; used for any record without a digital surrogate
- `IMAGE` - MIME `image/*`
- `SOUND` - MIME `audio/*`
- `VIDEO` - MIME `video/*`
- `3D` - MIME contains `model/`, `gltf`, or `obj`

The serializer picks based on the master digital object's MIME type.

---

## 5. Rights URI

`edm:rights` is mandatory and must resolve. Heratio looks up an
attached `rights_statement.uri` first (the RightsStatements.org or
Creative Commons URI you've linked to the record), then falls back
to `federation.europeana_default_rights` setting, and finally to
`http://rightsstatements.org/vocab/InC/1.0/` (In Copyright).

Make sure every published record carries a real rights statement
before publishing - the catch-all is intentionally pessimistic.

---

## 6. Configuration

Open the Settings module and look under scope `federation`:

| Setting | Default | Purpose |
| --- | --- | --- |
| `europeana_data_provider` | `The Archive and Heritage Group` | The `edm:dataProvider` literal Europeana shows alongside every record. |
| `europeana_country` | `South Africa` | Provider country. |
| `europeana_language` | record culture | Provider primary language. |
| `europeana_default_rights` | `http://rightsstatements.org/vocab/InC/1.0/` | Fallback rights URI. |

The federation_enabled global toggle also gates Europeana publish; turn
the dashboard off and the weekly schedule stops firing.

---

## 7. Schedule

The package registers a weekly run for Sunday 02:00 (server local time).
You can disable it by turning off federation_enabled.

For ad-hoc runs use the "Generate now" button or the CLI:

```
php artisan europeana:export
php artisan europeana:export --out=storage/europeana/
php artisan europeana:export --since=2026-01-01
```

`--since` does an incremental rebuild (only IOs touched after the
date). The bundle name still uses today's date.

---

## 8. Sending the bundle to Europeana

Once you have a confirmed Europeana provider agreement:

1. Click "Generate now" or wait for the weekly run.
2. Click "Download bundle".
3. Hand the `europeana-bundle-YYYY-MM-DD.zip` over to your data-ingest
   contact at Europeana (or upload via the provider portal they assign).
4. The sitemap inside is what their harvester crawls when they re-pull
   from your live URLs - so make sure the per-record show pages and
   primary digital-object access copies are publicly reachable.

---

## 9. Troubleshooting

- **No bundle is available yet** - run the export first. If the
  schedule hasn't fired yet and you haven't clicked "Generate now",
  the download is a no-op.
- **Status is `error`** - check the `error` column on the run-history
  table on the dashboard, then check Laravel's log. The most common
  cause is a missing `storage/europeana/` directory the worker can't
  create (perms drop-in needed for php-fpm).
- **edm:type comes out wrong** - the picker looks at the master
  digital object's MIME type. Fix the MIME on the record and re-run.

---

## See also

- OAI-PMH publishing (`/help/oai-pmh-server`)
- ResourceSync capability list (`/help/resourcesync`)
- Schema.org JSON-LD on show pages (`/help/jsonld-schema-org`)

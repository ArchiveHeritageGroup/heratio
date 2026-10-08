# Worked API examples

Real requests against the Heratio REST API v2 and OAI-PMH (heratio#1544). Replace `https://heratio.example.org` with your instance and `$KEY` with an API key (see the agent access policy for how to get one).

## Check the key

```bash
curl -s https://heratio.example.org/api/v2/ | jq .
```

The root lists the endpoints and needs no key. Any other v2 call without a key returns 401 with the message `API key required. Use X-API-Key header or Authorization: Bearer.`

## List and read descriptions

```bash
curl -s -H "X-API-Key: $KEY" "https://heratio.example.org/api/v2/descriptions?limit=10" | jq .
curl -s -H "X-API-Key: $KEY" "https://heratio.example.org/api/v2/descriptions/minutes-of-the-board-1922" | jq .
```

Records are addressed by slug, the last part of the record's web address.

## Create a description

```bash
curl -s -X POST https://heratio.example.org/api/v2/descriptions \
  -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{
        "title": "Minutes of the Board, 1922",
        "identifier": "BRD/1/1922",
        "parent_slug": "board-of-directors-fonds",
        "scope_and_content": "Bound minutes of the monthly board meetings.",
        "extent_and_medium": "1 volume"
      }' | jq .
```

The key needs the `write` scope. `parent_slug` files the new record under an existing one; leave it out for a top-level record. The response carries the new record's slug.

## Update a description

```bash
curl -s -X PATCH https://heratio.example.org/api/v2/descriptions/minutes-of-the-board-1922 \
  -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"arrangement": "Chronological."}' | jq .
```

`PATCH` changes only the fields you send. "Last modified" moves only if the content really changed, so harvesters are not sent records that are the same as before.

## Delete a description

```bash
curl -s -X DELETE -H "X-API-Key: $KEY" \
  https://heratio.example.org/api/v2/descriptions/minutes-of-the-board-1922
```

The key needs the `delete` scope. A record with records beneath it is not deleted: the API answers 409 Conflict, and the children have to go first.

## Safe retries

v2 accepts an `Idempotency-Key` header on writes. Send the same value when you retry a request that timed out, and Heratio returns the first result instead of creating a second record:

```bash
curl -s -X POST https://heratio.example.org/api/v2/descriptions \
  -H "X-API-Key: $KEY" -H "Idempotency-Key: 6f1c2d9e-board-1922" \
  -H "Content-Type: application/json" -d '{"title": "Minutes of the Board, 1922"}'
```

## Harvest with OAI-PMH

```bash
# What the repository offers
curl -s "https://heratio.example.org/oai?verb=Identify"
curl -s "https://heratio.example.org/oai?verb=ListMetadataFormats"

# Everything changed since 1 October 2026, as Dublin Core
curl -s "https://heratio.example.org/oai?verb=ListRecords&metadataPrefix=oai_dc&from=2026-10-01"

# Next page: pass the resumptionToken from the previous response
curl -s "https://heratio.example.org/oai?verb=ListRecords&resumptionToken=TOKEN"
```

A harvester only needs the full list once. After that, `from=` with the date of the last harvest returns only what changed.

## Errors and limits

| Status | Meaning |
| --- | --- |
| 401 | No key, or the key is unknown or revoked. |
| 403 | The key lacks the scope for this action. |
| 404 | No record with that slug (or it is not published and the key cannot see drafts). |
| 409 | Conflict, for example deleting a record that still has children. |
| 422 | Validation failed; the body lists the fields. |
| 429 | Rate limit reached; wait and retry. |

: API status codes

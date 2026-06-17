# Exhibitions REST API - User Guide

The Exhibitions API lets an external system create and manage **exhibition spaces** and their **object placements** programmatically, using a scoped API key. It is a thin, authenticated layer over the same service the 3D builder UI uses, so anything the builder can do to a space or a placement can also be driven over HTTP.

This is separate from the **public, read-only exports** an exhibition already publishes (IIIF `manifest.json`, `scene.json`, `exhibition.jsonld`). Those need no key and are unchanged. It is also separate from the **federated borrow** endpoints (`peer-scene` / `place-remote`). Use this API when an external system needs to create, read, update, or delete exhibition spaces and placements.

## Authentication and scopes

All endpoints require an API key (see the API Keys guide for issuing one). Scopes follow the same posture as the other resources:

| Scope | Allows |
|---|---|
| `read` | `GET` (list, show, list placements) |
| `write` | `POST`, `PUT` (create/update spaces and placements) |
| `delete` | `DELETE` (remove spaces and placements) |

Send the key as `Authorization: Bearer <key>` (or the other supported key headers). Writes honour idempotency, ETag, and CORS via the standard API middleware.

## Endpoints (v1)

```
GET    api/v1/exhibitions                          list spaces (paginated)            read
GET    api/v1/exhibitions/{slug}                   show a space + its placements      read
POST   api/v1/exhibitions                          create a space                     write
PUT    api/v1/exhibitions/{slug}                   update a space                     write
DELETE api/v1/exhibitions/{slug}                   delete a space                     delete

GET    api/v1/exhibitions/{slug}/placements        list placements (local + remote)   read
POST   api/v1/exhibitions/{slug}/placements        add or update a local placement    write
POST   api/v1/exhibitions/{slug}/placements/remote borrow a remote placement (#1277)  write
PUT    api/v1/exhibitions/{slug}/placements/{id}   update a local placement           write
DELETE api/v1/exhibitions/{slug}/placements/{id}   remove a placement                 delete
```

The same read endpoints are mirrored under the `api/v2` read group: `GET api/v2/exhibitions` and `GET api/v2/exhibitions/{slug}`.

## Examples

### Create an exhibition space

```
POST /api/v1/exhibitions
Authorization: Bearer <write-key>
Content-Type: application/json

{ "title": "Voices of the Coast", "description": "Oral histories, 1900-1960" }
```

Returns `201` with the created space (including its generated `slug`).

### Add a local object placement

```
POST /api/v1/exhibitions/voices-of-the-coast/placements
Authorization: Bearer <write-key>
Content-Type: application/json

{ "information_object_id": 10421, "size_units_used": 2.5,
  "starts_at": "2027-03-02", "ends_at": "2027-06-30" }
```

Returns `201` with `{ "id": <placement_id> }`. Posting the same body again with a `placement_id` updates that placement in place and returns `200`.

### Update a placement (dates only)

```
PUT /api/v1/exhibitions/voices-of-the-coast/placements/87
Authorization: Bearer <write-key>
Content-Type: application/json

{ "starts_at": "2027-04-01", "ends_at": "2027-07-31" }
```

`PUT` behaves as a merge: any field you omit keeps its current value, so you can adjust dates without resetting size or notes. Returns `200`.

### Borrow a remote object (federation, #1277)

```
POST /api/v1/exhibitions/voices-of-the-coast/placements/remote
Authorization: Bearer <write-key>
Content-Type: application/json

{ "remote_peer_id": 4, "remote_ref": "peer-obj-991",
  "remote_payload": { ...normalised peer object... } }
```

Remote (borrowed) placements are read-only mirrors and are managed only through this `/placements/remote` endpoint, not through `PUT .../placements/{id}`.

## Validation and errors

- All writes are validated. Missing or malformed fields return `422` with a `messages` map.
- A placement whose date range would exceed the space's capacity returns `422` with a clear "would exceed capacity by N units" message.
- `starts_at` after `ends_at` returns `422`.
- Deleting a space that still has placements returns `409` (remove the placements first).
- A placement that does not belong to the addressed exhibition returns `404` (placements are always scoped to their space).

## Notes

- No business logic is duplicated in the API layer; it calls `AhgExhibition\Services\ExhibitionSpaceService` directly, so capacity rules, slug generation, and date checks behave exactly as they do in the builder UI.
- The public exports and the federated borrow endpoints are unaffected by this API.

Refs: issue #1280; federation #1277; scene export #1151.

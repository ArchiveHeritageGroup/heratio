# Exhibition REST API (#1280)

Authenticated, scoped-key REST API for managing exhibition spaces and their object
placements from an external system. This sits on top of the existing **public, read-only**
interop surfaces (which are unchanged and need no key):

- `GET /exhibition-space/{slug}/manifest.json` - IIIF Presentation manifest
- `GET /exhibition-space/{slug}/scene.json` - `ahg-exhibition-scene` 1.0 (3D scene + objects)
- `GET /exhibition-space/{slug}/exhibition.jsonld` - JSON-LD / linked data

and the #1277 federated borrow endpoints. Everything below is a thin layer over
`AhgExhibition\Services\ExhibitionSpaceService` (the same service the web UI uses), so
validation, slug generation, capacity-overflow and date-order rules live in one place.

## Auth + scopes

API-key auth, same posture as the research-projects resource: reads need the `read` scope,
writes `write`, deletes `delete`. v1 and v2 both run the standard middleware (CORS, ETag,
idempotent POST). OpenAPI is auto-generated from the routes at `/api/openapi.json`.

## v1 - full CRUD

```
GET    /api/v1/exhibitions                          # list (q, space_type, page, limit, sort)      read
GET    /api/v1/exhibitions/{slug}                   # space + placements                            read
POST   /api/v1/exhibitions                          # create -> 201                                 write
PUT    /api/v1/exhibitions/{slug}                   # update                                        write
DELETE /api/v1/exhibitions/{slug}                   # delete (409 while placements still exist)     delete

GET    /api/v1/exhibitions/{slug}/placements        # list placements (local + remote)              read
POST   /api/v1/exhibitions/{slug}/placements        # place a LOCAL information object              write
POST   /api/v1/exhibitions/{slug}/placements/remote # borrow a peer object (#1277, read-only)       write
DELETE /api/v1/exhibitions/{slug}/placements/{id}   # remove a placement                            delete
```

### Create / update body
`name` (required on create), `space_type`, `building`, `floor`, `capacity_value`,
`capacity_unit`, `lighting_lux_target`, `notes`, `room_w`, `room_d`, `room_h`,
`building_id`, `building_seq`. The slug is generated from `name`.

### Place a local object
`information_object_id` (required), optional `size_units_used`, `starts_at`, `ends_at`,
`exhibition_id`, `notes`, and `placement_id` to update an existing placement. A capacity
overflow for the given date range returns 422.

### Borrow a remote object
`remote_payload` (the normalised peer object as an array or JSON string - the same shape
`scene.json` / the #1277 `peer-scene` endpoint returns), optional `remote_peer_id`,
`remote_ref`. Stored read-only with "Courtesy of" attribution; media stays on the peer.

## v2 - read mirror

Standard v2 envelope (`success` / `data` / `meta` / `links`):

```
GET /api/v2/exhibitions          # paginated list        read
GET /api/v2/exhibitions/{slug}   # space + placements     read
```

Writes are intentionally v1-only; v2 is the read surface.

## Errors

`422` validation / capacity / date-order, `404` unknown slug or placement, `409` delete
refused while placements still reference the space. v1 uses the plain v1 error envelope
(`{error, messages}`); v2 uses the v2 envelope (`{success:false, error, message}`).

# Cross-collection Connections (Unified G/L/A/M graph)

**Heratio issue #1197.** A "follow one record into everything related to it, across every
collection domain" page. Pick any catalogue record, then see every connected entity grouped
by GLAM domain: Records & descriptions, People & organisations, Repositories, Subjects/places
& terms, Accessions.

## Why it exists

Gallery, Library, Archive and Museum holdings share one underlying graph in Heratio (the
AtoM/Qubit `object` table with a `class_name` discriminator, joined through the generic
`relation` table). The RiC Graph Explorer renders that graph as an interactive node diagram;
this page is the plain-language companion - a grouped, linkable list that answers "who and
what is this record connected to?" without needing to read a force-directed graph.

## How it works

- **Page:** `/admin/ric/connections` (route `ric.connections`) - an entity search box
  (reuses the RiC autocomplete, `ric.public-autocomplete`) plus a grouped results grid.
- **Data:** `/ric-api/connections/{id}` (route `ric.public-connections`) returns
  `{ success, total, groups: [{ domain, count, items: [{ id, name, slug }] }] }`.
- **Service:** `AhgRic\Services\RelationshipService::crossCollectionNeighbours(int $entityId)`.
  Walks the `relation` table for any row touching the entity (subject_id OR object_id),
  collects the other end of each edge, then resolves each related entity's `class_name` and
  display name in one LEFT-JOIN query across `information_object_i18n`,
  `actor_i18n`, `term_i18n` and `slug` (`COALESCE(io.title, a.authorized_form_of_name,
  t.name)`). Results are grouped by GLAM domain and sorted by group size.

## Notes

- Edges are capped at 1000 per entity (first slice) to keep the page responsive on
  hub records that sit at the centre of large authority networks.
- RiC-native node classes (e.g. RicActivity) that have no AtoM i18n row appear under their
  own group labelled by class name, shown by id - they are real edges, not hidden.
- Items link to the public record via their `slug` when one exists.

First slice shipped under the ahg-ric package.

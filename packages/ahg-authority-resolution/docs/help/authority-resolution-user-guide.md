# AHG Authority Resolution - User Guide

The AHG Authority Resolution Engine turns NER-extracted name mentions into archivally-defensible authority links. It does not auto-link. Every authority link is the result of an explicit archivist decision, captured with the evidence the archivist saw at decision time, and written to an immutable audit trail.

This guide is for archivists working the review queue. For the CLI, see "Authority Resolution - CLI Commands". For the data model and SPARQL, see "Authority Resolution - Provenance Model".

## What the engine does

1. Reads NER mentions from `ahg_ner_entity` (PERSON, ORG, GPE, LOC, PLACE).
2. Promotes selected mentions into the workflow and computes a neighbourhood context packet (surrounding text, co-occurring entities, nearby dates, nearby places, role-language tokens).
3. Asks every registered candidate adapter (local actor / term tables plus the Fuseki agents / places graphs) for ranked candidates.
4. Runs ten evidence evaluators over every candidate and writes a per-dimension signal (`match`, `conflict`, `silent`, `absent`) plus a composite score.
5. Surfaces the mention, the evidence, and the ranked candidates on the review screen.
6. Records your decision and writes RDF-Star provenance to Fuseki.

## The five-outcome decision tree

Every mention ends in exactly one of five outcomes. Each click is irrevocable in the sense that the audit row is immutable - to "change" a decision you record a new one; both rows remain visible in the history.

| Outcome | When to use | Result |
|---|---|---|
| **Link** | The top-ranked candidate is correct | `ahg_mention.state = linked`; `ahg_ner_entity.linked_actor_id` updated; decision provenance written |
| **Link different** | A lower-ranked candidate is correct | Same as Link, with the picked candidate id and a snapshot showing what rank-1 was at the time |
| **Create new** | None of the candidates fit. A fresh authority record is created with pre-fill from VIAF / Wikidata / GeoNames / etc. | `ahg_mention.state = new_record_created`; new `actor` or `term` row + i18n + per-field provenance |
| **Park** | You cannot decide yet (waiting for an import, off-line research needed, candidate set unsafe) | `ahg_mention.state = parked`; row in `ahg_mention_park`; surfaces again when the candidate set changes |
| **Reject** | The mention is not actually an entity of the claimed type (NER false positive) | `ahg_mention.state = rejected`; row in `ahg_ner_feedback` (becomes NER training data) |

`parked` is the only non-terminal state. The other four are terminal.

## What the review screen shows you

The screen is split into three regions.

### Left: mention + context packet

- Mention value and entity-type badge (PERSON / ORG / GPE / PLACE)
- Link to the source information object
- The mention highlighted inside up to 150 characters of surrounding text on each side
- Character and paragraph offsets
- NER model version that produced the mention
- Co-occurring entities, nearby dates, nearby places, role-language tokens
- An amber ambiguity banner when the same surface form occurs more than once in the source document

### Middle: ranked candidates

One card per candidate, sorted by composite score (highest first). Each card shows:

- Display name and source badge (`Local actor`, `Local place`, `Fuseki agent`, `Fuseki place`)
- Composite score and name-similarity score
- A per-dimension evidence table with coloured badges (green for match, red for conflict, grey for silent, dashed-grey for absent)
- A "view full authority record" link to the existing `/actor/{id}` or `/taxonomy/term/{id}` show page (read-only)
- For PLACE candidates: a Leaflet map preview. Where coordinates are unavailable the preview degrades to a world-view map with a "no coordinates available" hint.

### Right: action buttons

A sticky sidebar with the five buttons in the order: Link to selected, Link different, Create new, Park for later, Reject as false positive.

## How to interpret the scores

The composite score is the value you act on. It is the candidate's name similarity (Jaro-Winkler) plus the sum of the evidence weights, clamped to `[0, 1]`:

```
composite = clamp(name_similarity + Sum(weight(signal_i)), 0, 1)
  weight(match)    = +0.10
  weight(conflict) = -0.30
  weight(silent)   =  0.00
  weight(absent)   =  0.00
```

A clean rank-1 hit with strong evidence sits at 1.000. A clean miss with a contradiction drops by 0.30. Multiple matches stack additively until the clamp bites.

## The four evidence signals

Every evaluator emits one of four signals per candidate, per dimension. The distinction between `silent` and `absent` is small but important.

| Signal | Meaning |
|---|---|
| **match** | The mention's context and the candidate's authority data overlap on this dimension. |
| **conflict** | A direct contradiction (for example: the mention's nearby year is after the candidate's death year). |
| **silent** | Data exists on both sides but does not overlap and does not contradict. The evaluator considered the evidence but found nothing decisive. |
| **absent** | The data is missing entirely on one or both sides. The evaluator had nothing to consider. |

Today `silent` and `absent` carry the same scoring weight (0). They are kept distinct on screen so you can tell "evidence existed and the evaluator found no overlap" from "no evidence to consider here". A future tuning pass may weight them differently.

## Evidence dimensions

Persons and organisations are scored on:

- **Temporal** - date-span overlap between the candidate's `dates_of_existence` and the mention's nearby dates.
- **Geographic** - place overlap between the candidate's known event-places and the mention's nearby places.
- **Relational** - co-occurring entity overlap via the `relation` table.
- **Role** - role-language consistency (substring scan of the candidate's history, functions, mandates, legal status).
- **Conflict** - hard contradictions (for example: a nearby year strictly after the candidate's end year).

Places are scored on:

- **Hierarchical** - admin-hierarchy parent / child overlap (via `term.parent_id`).
- **Prior** - document-level place prior (places this document already talks about, in aggregate).
- **Co-occurring person** - bound-to-person evidence (does the document mention people known to be associated with the place).
- **Place conflict** - hard contradictions specific to places.
- **Scale** - admin-level vs. context. For example, the surrounding text describes a city but the candidate is a continent.

## When most signals come back absent

On a freshly imported corpus most signals will be `absent` until the underlying authority records grow some shape. This is honest, not broken:

- A candidate actor with no `dates_of_existence` and no `history` text cannot show temporal or role evidence.
- Place candidates that all sit directly under the taxonomy root have no hierarchical evidence.
- A document where no mention has been linked yet has an empty place prior.

As the authority store fills out (date spans, biographical notes, place hierarchies, prior linked mentions), match and conflict signals start firing and the composite scores begin to materially separate the candidates.

## Walkthrough: a typical review

1. Open `/admin/authority-resolution/queue`. Filter by entity type or by source information object as needed.
2. Click a mention to open `/admin/authority-resolution/review/{id}`.
3. Read the surrounding text on the left. Confirm in your own head what this mention probably is.
4. Scan the candidate cards. If the top card is right, click **Link to selected**.
5. If a lower card is the right one, click its radio button, then **Link to different**. The audit captures the rank-1 score so the override is auditable later.
6. If nothing fits, click **Create new** and proceed through the new-authority form (see the "Creating a New Authority Record" article).
7. If you are not ready, click **Park for later** and enter a one-line reason. The mention will resurface on the Park queue when the candidate set changes.
8. If the NER model was wrong, click **Reject as false positive** and (optionally) note the reason. The mention is suppressed and the text becomes NER training data.
9. After any decision the screen advances to the next pending mention (lowest id first).

## Where to go next

- Authority Resolution - Review Screen Reference (region-by-region walkthrough)
- Authority Resolution - Park Queue
- Authority Resolution - Creating a New Authority Record
- Authority Resolution - Evidence Scoring (mechanics, formula, evaluator catalogue)
- Authority Resolution - Provenance Model (RDF-Star shape, SPARQL recipes)
- Authority Resolution - CLI Commands (11 artisan commands)

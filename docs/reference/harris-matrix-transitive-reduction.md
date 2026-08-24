# Harris Matrix implementation - the transitive-reduction gap

**Summary.** Both Heratio and the AtoM plugin build a Harris Matrix from stratigraphic context relationships, and both draw every recorded relationship as an edge. Neither performs transitive reduction, so relationships implied by other relationships are drawn anyway. The tiers come out correct because the layering takes the longest path, but a diagram that shows redundant relationships is not a Harris Matrix in the strict sense - removing them is one of Harris's rules and the reason the diagram stays legible. Filed as heratio#1482 and atom-ahg-plugins#192.

## What exists

Heratio: `packages/ahg-archaeology`, `ArchaeologyService::harrisMatrix()`, shipped v1.154.447 on 29 July 2026 under issue #1428. AtoM: `ahgArchaeologyPlugin`, `ArchaeologyService::harrisMatrix()`, reached through the archaeology actions and the contexts template.

Both implementations share the same shape, and it is a good one. Union-find merges `same_as` contexts into a single node. Kahn longest-path layering assigns tiers with level 0 - the latest material - at the top. Cycle detection flags a contradictory sequence rather than throwing. Heratio renders server-side as tiered boxes because CSP blocks the CDN Mermaid path, and offers the Mermaid source separately for export.

## The gap

Every `above`, `cuts` or `fills` row becomes a drawn edge. Nothing removes an edge that a longer path already implies.

This is not hypothetical. Single-context recording sheets routinely carry redundant relationships because the excavator genuinely observes them: where 1001 overlies 1002 and 1002 overlies 1003, a recorder will often also record "1001 above 1003", which is true and directly observed. CSV import loads all three correctly. The matrix then draws all three arrows, and the third carries no information the first two do not already express.

Severity, stated precisely. The **tiers are correct** - longest-path layering means the redundant edge does not lift 1003 to the wrong tier, so the sequence the diagram asserts is right. What is wrong is the diagram as a Harris Matrix, and it degrades badly with scale: on a site of a few hundred contexts the redundant edges multiply into exactly the unreadable web the form exists to prevent. Where the matrix is attached to a published description as a digital object, as in the AtoM demo site, the redundant edges enter the archive rather than staying on screen.

## The fix

Transitive reduction on the DAG: for each edge a to b, drop it if b is reachable from a by any path of length two or more. A depth-first reachability check per edge, excluding the edge under test, is sufficient at single-site scale and operates on data already in memory.

Order matters. It must run **after** the union-find `same_as` merge, because merging can create redundancy that did not exist between the unmerged contexts, and **before** the layering, so that tiers, rendering and export all consume the reduced set. Returning a count of removed edges is worth doing - an archaeologist may reasonably want to know their recording contained implied relationships.

## Two smaller observations in the same function

The edge key is the node pair alone, so where a pair carries more than one later-than relationship the last row read silently determines the arrow label. That should be a deliberate choice rather than an artefact of iteration order.

Cycle detection reports that a cycle exists without identifying it. Flagging rather than throwing is the right instinct, but "there is a cycle somewhere among your contexts" is not actionable for the person who must correct the recording. The nodes still carrying a non-zero in-degree after the queue drains are exactly the ones involved, and are already to hand.

## The wider field

Harris Matrix software is a small field and mostly either legacy Windows tools or thin wrappers over Graphviz. Actively maintained at August 2026: stratigraphr (R), PolyChron (Python), PHASER (Vue). Dormant but historically important: tsdye/harris-matrix (Common Lisp, Bayesian chronological models) and semerj/harris-matrix (d3). ArkMatrix, part of the Archaeological Recording Kit, is the one with a real excavation-recording system behind it. Stefano Costa's Harris Matrix Data Package is the sensible interoperability target for export, so a matrix is not trapped in one schema. The established desktop lineage is Harris Matrix Composer, Stratify, ArchEd and WinBASP, the last being the first environment to represent a Harris matrix correctly.

## Related

The stratigraphic context module plan carries the phasing and the rationale for server-side rendering. The AtoM side moved to phases 4a and 4b on 23 August 2026 - finds, CSV import, context sheet PDF, Elasticsearch and spatial indexing, and a panel injector giving a site description a route to its own matrix.

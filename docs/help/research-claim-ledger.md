# Research Claim Ledger

The Claim Ledger is the spine of a research project's argument. It promotes every
assertion you record into a first-class, tracked claim - from a first idea all the
way to a publishable statement - and refuses to let an unsupported claim pass
silently.

Open it from any project at **Research > Projects > [your project] > Claim Ledger**
(URL: `/research/project/{id}/claims`).

## What a claim holds

Each claim in the ledger carries:

- **Claim text** - the assertion stated in one or two sentences.
- **Status** - the lifecycle stage: Idea, Working claim, Supported, Contested,
  Weak, Rejected, Needs more evidence, Publishable.
- **Confidence** - High, Medium, Low or Tentative.
- **Originality** - whether the claim is Original, Derived, or Speculative.
- **Evidence type** - primary source, secondary source, archival record, oral
  testimony, material object, observation, or derived analysis.
- **Supporting sources** and **Opposing sources** - the citations on each side.
- **Quotations with page references** - the exact wording you are leaning on.
- **Method / theory link** - the methodological or theoretical anchor.
- **Researcher notes**, **Unresolved weaknesses**, and **Ethical concerns**.

## Attaching evidence

Evidence is attached from the project's own materials - its bibliography entries,
its annotations, and its collection items. On a claim's page, choose a source type,
pick an item, set the relationship (Supports, Opposes, or Contextualizes), and
attach. You can detach evidence at any time. Evidence links are shared with the
project's assertion graph, so a citation added here is visible everywhere the
assertion appears.

## The founding principle: no unsupported claim passes silently

The ledger index always surfaces two review lists:

- **Claims with NO citation** - every claim that has zero evidence attached. A
  claim cannot quietly graduate to Supported or Publishable without a citation.
- **Claims over-dependent on one source** - claims that have two or more evidence
  references but all of them point to a single source. Over-reliance on one source
  is flagged for a second look.

Both lists are empty-state friendly and confirm explicitly when every claim has a
citation and no claim leans on a single source.

## Lifecycle

Move a claim through its lifecycle from the claim's page using the **Lifecycle**
control, or set the status inline while editing. The badge colour reflects the
stage so a glance down the ledger shows which claims are still ideas, which are
contested, and which are ready to publish.

## Notes

- The ledger is per-project and auth-gated.
- It is additive: the underlying assertion records are never modified in a
  destructive way, and the extra ledger fields live in a sidecar table.
- Deleting a claim removes the claim and its evidence links.

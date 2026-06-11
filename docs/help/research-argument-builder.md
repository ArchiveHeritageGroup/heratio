# Argument Builder

The Argument Builder (Research OS Stage 12) lets you sequence the claims in a
project into a structured nine-step argument and warns you about weak spots
before you commit to a conclusion.

## What it does

You drag claims from the Claim Ledger into an ordered argument. Each step fills
one of nine canonical slots that together make a complete scholarly case:

1. **Problem** - the situation or puzzle the work addresses.
2. **Gap** - what is missing or unresolved in current knowledge.
3. **Frame** - the theoretical or conceptual lens applied.
4. **Method** - how the claim is established or tested.
5. **Evidence** - the core supported finding the case rests on.
6. **Analysis** - what the evidence means once interpreted.
7. **Counterargument** - the strongest objection and the response to it.
8. **Contribution** - what this adds that did not exist before.
9. **Implication** - why it matters: consequences and next steps.

## Claims are reused, not rebuilt

The builder never duplicates your claims. It reads them live from the Claim
Ledger (the same `research_assertion` records, with evidence from
`research_assertion_evidence`). Attach a claim to a slot from the picker on each
step; the step stores only a reference, so editing the claim in the ledger
updates it everywhere.

If a project has no claims yet, add them in the Claim Ledger first, then attach
them here.

## Weak-spot warnings

As you build, the system runs a heuristic check (no AI required) and flags:

- **Uncited step** - a step whose claim has no evidence attached at all.
- **Single-source over-reliance** - a step whose claim has two or more
  citations but they all come from one source.
- **Missing step** - any of the nine slots not yet placed in the chain.
- **Contested claim** - a step using a claim marked rejected, contested,
  disputed or weak, which signals a contradiction to resolve.
- **Conclusion stronger than the evidence** - a Contribution or Implication step
  resting on a low-confidence, weak-status or uncited claim.

Warnings appear inline on each step and collected in the side panel. They are
advisory: nothing blocks you from saving, but a clean panel means every step is
cited and the chain is complete.

## Using the canvas

- **Central thesis** - name the argument and state the single claim it exists to
  defend.
- **Add step** - choose a slot and (optionally) a claim, then add it.
- **Attach / change claim** - use the per-step picker to bind a claim, or pick
  "No claim attached" to clear it.
- **Reorder** - the up/down arrows move a step within the sequence.
- **Remove** - the trash button takes a step out of the argument (the underlying
  claim stays in the ledger).
- **Missing steps** - the side panel offers a one-click button to add any slot
  you have not placed yet.

There is one argument per project; it is created automatically the first time
you open the builder.

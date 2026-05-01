---
title: Architecture Decision Records
license: AGPL-3.0-or-later
---

# Architecture Decision Records (ADRs)

This directory holds the durable architectural decisions that bind future
implementation work in Heratio. Each ADR captures **why** a decision was made,
not just what was done - so that future contributors can tell whether the
constraint that produced the decision still applies, or has changed and the
ADR should be revisited.

## When to write a new ADR

Write one when you are about to make, or have just made, a decision that:

- Constrains future work across more than one package.
- Trades off something non-obvious (cost, staleness, parity, sovereignty).
- Reverses or qualifies a previous decision.
- Will be re-asked by someone six months from now ("why don't we just …?").

Bug fixes, refactors, and one-off implementation choices do **not** need an
ADR. The PR description carries that context.

## Format

- Filename: `NNNN-short-kebab-title.md` with a four-digit zero-padded number.
- Numbers are allocated in commit order; never re-use or renumber.
- Frontmatter: `title`, `status` (Proposed / Accepted / Superseded / Deprecated),
  `date`, `author`, `license`.
- Sections: Status, Context, Decision, Consequences, References. Add others
  (Worked example, Alternatives considered) when they earn their place.

## Index

| # | Title | Status |
| --- | --- | --- |
| 0001 | [AtoM base schema is read-only; performance work uses AHG sidecar tables](0001-atom-base-schema-readonly-sidecar-pattern.md) | Accepted |

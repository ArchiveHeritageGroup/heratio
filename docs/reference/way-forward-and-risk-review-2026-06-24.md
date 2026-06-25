# Heratio — Way Forward & Risk Review (2026-06-24)

> Blunt internal assessment + plan. Written to be reviewed, not to flatter.
> Verdicts are backed by reasoning; "done" means evidence (tests / live check /
> parity at real scale), not assertion.

## TL;DR

Three workstreams are half-finished and stacked unreleased on dev. The risk is
not "too little done" — it's **undetonated, unbisectable, half-migrated work**.
Stop starting; finish and de-risk in order: **release in chunks → resolve #1333
→ FS #1336 engine → only then new features.**

## Current state — the brutal read

### 1. Unreleased work is hoarded, not banked
FS Overlay (#1336) + #1333 (closure data layer, dual-write, read-swaps, ES
ancestor-delta) + the `fs-metadata-capture` browser-extension edits + the
FS-overlay sync fix are all staged/committed on dev and **not released**.
- **Risk:** a single mega-release to prod is unbisectable. If prod breaks after
  the pull, you cannot cheaply find which change did it.
- **Fix:** release in coherent, independently-revertable chunks (see Phase 0).

### 2. #1333 is a half-migration — cost paid, benefit uncashed
- Every tree mutation now dual-writes nested-set **and** closure → more code,
  more failure surface.
- lft/rgt is **still the source of truth**. Closure parity is proven only on
  **381 dev rows**. The entire justification was the **322k prod** nested-set
  wall.
- **Verdict:** in its current state #1333 is a *liability*, not a feature — added
  complexity, zero realised payoff. A half-migration is worse than none.
- **Fix:** finish it (prove at 322k → flip reads → retire lft/rgt + dual-write)
  or park it explicitly. No indefinite middle.

### 3. FS Overlay (#1336) is a UI with no engine
- The trained HTR model has `marriages: 0` training. The overlay, multi-record
  bands, Image Type rows, and Data Safe CSV are all built and verified
  *structurally* — but produce **garbage values** on real marriage hands.
- **Verdict:** not "done." It's a showroom car with no motor.
- **Fix:** train the `type_c` marriage model (model-side, operator-driven), or
  formally declare the blocker to FamilySearch. No more overlay features until
  the engine produces non-garbage.

### 4. The ES ancestor-delta is currently orphaned
- `ancestors` is now indexed and kept correct on subtree move via one async
  `_update_by_query` (verified). But **nothing queries it** — browse ancestor
  filtering is DB-backed (`scopeDescendants`).
- **Verdict:** foundation with no consumer = speculative until an ES-backed
  ancestor/subtree filter is wired to use it. Justify the consumer or treat it
  as dead weight.

## The plan — ordered, evidence-gated

### Phase 0 — Release the backlog in chunks (first)
Split the unreleased work into separately-revertable releases:
- (a) FS Overlay #1336
- (b) #1333 closure batch
- (c) extension + misc fixes

**Gate:** tests green + a live smoke check per chunk before the next. No mega-release.

### Phase 1 — Force a decision on #1333 (no middle)
- **Finish:** obtain a copy of the 322k atom/ANC dataset → run the recursive-CTE
  parity harness at scale → **only if it passes**, flip reads to closure and
  remove lft/rgt + dual-write.
  - **Go/no-go gate = 322k parity**, not dev's 381.
- **Or park:** stop touching it; document as stable-but-incomplete; stop
  counting it as value.
- **Recommendation:** finish, timeboxed.

### Phase 2 — FS #1336: engine or honesty
Train the marriage model, or declare the blocker. No further overlay work until
the model is real.

### Phase 3 — New features (#1331 sector templates / #1329 DSpace / #1330 Alma)
Only after Phases 0–2. Starting new work on top of three half-finished things
just multiplies half-finished things.

## Standing gates

- Nothing is "done" on assertion. Tests, live verification, or parity at real
  scale — or it ships with a written caveat.
- Lead with the flaw, not the win.

## On the locks (decision pending)

The locks exist because unprompted hot-path changes (esp. the IO show tree) were
reverted too many times. **Do not blanket-unlock** — that removes the only
guardrail against exactly that failure, right when work is most aggressive.
Unlock **per work-item**: each task names the specific paths it needs.

## Status of this session's work (all on dev, unreleased)

| Item | State | Evidence |
|---|---|---|
| FS Overlay FS-aware (templates, multi-record, Image Type, CSV) | built | headless verified; values garbage pending model |
| FS Overlay non-destructive crop + sync-to-server fix | built | headless verified |
| #1333 closure parity (IO/term/menu) | passed on dev | 0 missing / 0 extra vs parent_id CTE |
| #1333 read-swaps (8 sites) | built | each = same-or-more-correct vs lft/rgt |
| #1333 ES ancestor-delta | built | painless script verified on test docs |
| #1333 322k-scale parity + retire lft/rgt | NOT done | needs prod data copy |
| FS #1336 marriage model | NOT done | model-side blocker |

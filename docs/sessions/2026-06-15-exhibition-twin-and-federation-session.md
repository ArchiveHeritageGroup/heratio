# Session 2026-06-15 - exhibition digital twin: lazy lifecycle, federation, multilingual

Consolidated narrative across the day's releases (the per-release auto-logs are the
`v1.142.136`..`v1.142.140` files in this folder). One coherent arc: make the exhibition
walkthrough scale to large buildings, link it across institutions, and answer in-language -
each change gated and verified, blade work done under one-shot unlocks.

## Shipped

| Version | Issue | What |
|---|---|---|
| v1.142.136 | #1276 | Dispose-on-leave for **lazy** walkthrough buildings: per-room build/dispose with hysteresis (build 70 m < dispose 120 m). Gated on `LAZY` so non-lazy/production buildings are byte-identical. |
| v1.142.137 | #1277 | Federated-twin **curator UI**: "Borrow from a partner" in the builder, read-only + "Courtesy of" attribution + link-back in the walkthrough, remote placements in the show list. Backend `peerScene`/`placeRemote` over `RemoteSceneFetchService`. Plus the North Star article + LinkedIn post. |
| v1.142.138 | #1275 | Chatbot **reply in the input message language** (opt-in): local `InputLanguageDetector` (no network, no qwen) sets the MT target; English/ambiguous falls back to UI locale. |
| v1.142.139 | #1279 | Dispose-on-leave **material/texture safety**: tag shared singletons (`pedestal`/`path`/`bench` materials, `tree`/`tuft`/`person` textures) `userData.shared` and skip them in `disposeRoom`. Marble/grass use per-room `.clone()` so they were already safe. |
| v1.142.140 | #1278 | Detach **in-room Gaussian splats** on dispose: per-room scene tracking + serialized remove on the shared `DropInViewer` queue; re-add is automatic via the `_built` reset. |

## Key decisions / rationale

- **Hysteresis, not a single radius** (#1276): build at 70 m, dispose at 120 m, so a room's async
  loads finish well before it can be disposed and edge rooms do not thrash.
- **`userData.shared` flag over a hand-kept skip-list** (#1279): the root cause was shared
  singletons being disposed; a flag is order-independent and future-proof. The original 8
  `SHARED_MATS` stay; new shared resources just get the flag. Disposal only ever became *more*
  conservative, so non-lazy is unaffected.
- **Splat re-add is free** (#1278): `disposeRoom` already resets `STOPS._built`, so re-approach
  rebuilds the splats; only the *removal* path was new. Adds and removes share one queue because
  the GaussianSplats3D sorter dislikes concurrent scene ops.
- **Detection never touches an LLM** (#1275): compliance with `feedback_no_qwen_for_af`. The
  detected code is only ever an MT *target*; `AnswerLocalizer` fails soft to English, so a wrong
  detection cannot yield qwen output.
- **Federation is consume-oriented** (#1277): borrow is read-only, media stays on the peer, the
  record link opens the peer in a new tab (cross-origin records are not iframeable).

## Verification approach (no WebGL runtime available)

- PHP lint + Blade compile + `node --check` on the *rendered* ES module for every blade change.
- Service-layer end-to-end tests via tinker (the #1277 borrow chain fetched 110 real objects,
  borrowed one, confirmed it rendered remote across builder/show/walkthrough, then cleaned up).
- Offline logic unit-tests for the parts that are pure JS: the #1279 dispose skip-logic and the
  #1278 add/remove queue both pass standalone node harnesses.
- The #1275 detector has a 16-case suite plus an in-repo `ahg:chatbot-test-multilang --detect`
  self-check (9/9).
- Browser-only residuals (black-surface / GPU-memory checks for the lazy lifecycle) are left for
  an operator eyeball, by design.

## Scoped, not yet built

- **#1280** - REST API for exhibitions + placements in `ahg-api` (external systems create/manage
  exhibition spaces with a scoped key). Today exhibitions expose only outbound read-only interop
  (IIIF `manifest.json`, `scene.json`, `exhibition.jsonld`) and the #1277 federated borrow; there
  is no authenticated CRUD resource. Filed to mirror the existing `ResearchProjectApiController`
  pattern over `ExhibitionSpaceService`.

## Still open (residual / follow-up)

- #1280 (REST exhibition API), #1272 (CLIP image-search GPU service - operator side).
- The #1277 long-horizon items remain explicitly out of scope: cross-node ODRL, cross-node SSO,
  a real F3/SharePoint connector, media caching at scale, and a live partner node for full
  acceptance.

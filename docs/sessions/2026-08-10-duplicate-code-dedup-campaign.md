# Duplicate-code de-duplication campaign (2026-08-10)

**Releases:** v1.154.529 - v1.154.554 · **Deployed:** dev (heratio-dev) → heratio.org → sasaarchive · both live sites verified green throughout.

## Summary

A codebase-wide audit found substantial duplicated code across the ~112 `ahg-*` packages. This campaign consolidated it. A duplicate-method scanner (brace-matched method bodies, license/imports/comments stripped, hashed, grouped) drove the work:

**Audit went from 130 duplicate-method groups / ~2,576 excess LOC → 45 / 580** ( **−65% groups, −77% duplicated LOC** ). Plus ~20 dead views removed and the language/script + translation-target pick-lists single-sourced.

Every consolidation followed the same rule: **keep the original method signature/visibility as a trait method or delegate/alias so call sites are untouched**, and **verify at runtime (`class_uses`/`method_exists`/`instanceof`/live endpoint) before release**. Nothing that would change behaviour was force-merged.

## What was consolidated

- **Dead code (v1.154.529):** 20 orphaned Blade views in `ahg-core` (pre-Bootstrap-5-theme originals), verified zero-reference repo-wide.
- **Reference pick-lists (v1.154.530-531):** description language(28)/script(14) lists → `AhgCore\Support\LanguageOptions`; the machine-translation target picker (5 copies, drifting) → one 21-language `translationTargets()`.
- **Whole duplicated service stack (Cluster 1, v1.154.535):** 4 byte-identical record-keeping services duplicated in `ahg-integrity` + `ahg-records-manage` → thin backward-compat subclasses of the `ahg-records-manage` owners (net −890 LOC).
- **Shared helpers → traits/static helpers** across ~25 themes: `ahg_dropdown` option loaders, the AHG-gateway API-key resolver (`AiServicesSettings::gatewayKey()`), ahg-api RDF/format/pagination/term/publisher/subject helpers, sector CSV-import validation, description-standard property read/write, ahg-reports cockpit cards, c2pa provenance resolution, cross-package serializer helpers (`fetchRepository` ×4), clearance/ACL checks, and more.
- **Research package (biggest by count):** the workspace `sidebar()` was copy-pasted into **21 controllers** → `RendersResearchSidebar`; `findProject`/`loadProject` into **14** → `ResolvesResearchProject` (dual-name); plus project-context/DMP/date/AI-model/label helpers.
- **Structural (v1.154.552):** the Cookbook/Maturity/Protocol near-clone API-doc controllers → a shared `ServesApiDocument` trait (content negotiation / CORS / route resolution); each keeps only its own `document()` + `html()`. Verified live: `/open-data/{cookbook,maturity,protocol}` JSON+HTML 200.
- **Name-mismatch pairs (v1.154.553):** same body, different method names (`logAccess`/`logIcipAccess`, `loadProperty`/`loadFirstScalarProperty`, `subjects`/`subjectsList`, `autodetectBinary`/`detectC2paTool`) → dual-name traits (body once + a delegating alias), so every call site keeps its name. The c2pa static/instance mismatch was unified as static (`$this`-free body; `$this->staticMethod()` is valid PHP).

## Deliberately NOT merged (would change behaviour)

Genuine variants left in place: `normalizePath` (Warc vs WebArchive differ), the *cached* actor/provenance `dropdownOptions`, the string/`''` `publisher` variant, `AnalysisBridgeService::resolveAiModel`. Plus 6-8 line "floor" methods where a trait's overhead ≈ the duplication saved.

## Reusable lessons (for future dedup)

- Migration script: brace-match method removal + insert `use Trait`. The class regex **must** include `final `/`abstract ` **and** `trait` (e.g. `InformationObjectFetcher` is a trait; `PublicCheckController` is `final`). Keep the closing brace (`s[j:]`, not `s[j+1:]`).
- Traits may reference host `$this->x` / `self::CONST` — resolved per-using-class, so behaviour is preserved even when the constant differs between hosts.
- Deploy with an **explicit** tag (`git describe --tags --abbrev=0`), never a bare `git describe` mid-checkout (it resolves the *old* HEAD).
- The **dual-name delegate** pattern (one canonical body + an aliasing method) cleanly resolves same-body/different-name duplication without touching call sites.

Canonical shared helpers now live under `AhgCore\Support\` (+ `AhgCore\Support\Concerns\`) and each package's `Concerns/` namespace.

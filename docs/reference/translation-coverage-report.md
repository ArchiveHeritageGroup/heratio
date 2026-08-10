# UI translation coverage report

**Re-runnable:** `php artisan ahg:translation-coverage` (add `--heratio-only` to exclude AtoM-inherited XLIFF keys, `--locale=xx` to filter, `--fail-below=N` to gate). This static snapshot is a point-in-time record; the command is the source of truth and now runs in CI on every push/PR (`.github/workflows/translation-lint.yml`).

## Snapshot (2026-08-10)

- **Reference set:** 17,970 codebase `__()` keys.
- **Malformed locale files:** 0 (all 66 `lang/*.json` parse as JSON objects).
- **Buckets:** `>=90%: 0` · `40-89%: 7` · `<40%: 58`.

### Locales at >=40% coverage

| Locale | Coverage |
|---|---|
| zu (isiZulu) | 75% |
| xh (isiXhosa) | 75% |
| ss (siSwati) | 73% |
| ts (Xitsonga) | 73% |
| nso (Sepedi) | 73% |
| tn (Setswana) | 73% |
| st (Sesotho) | 72% |

All other locales are <40%. `en` reads 0% under this metric because English is the source (values == keys, so nothing counts as a distinct "translation"); `en.json` is the complete 18,819-key superset and is the source of truth for machine fills.

## Status against #1410

- [x] No malformed locale files (all parse as objects) - now **guarded in CI**.
- [x] `en.json` is the complete key superset (18,819 keys).
- [x] **Coverage CI check** - `ahg:translation-coverage` runs in `translation-lint.yml`; it fails the build on any malformed (non-object) locale file and prints the per-locale coverage table so drift is visible on every run.
- [ ] Priority locales machine-filled to >=80% - the SADC cluster is at ~73-75% (offline NLLB, #1416); international locales (fr/de/es/pt/it/nl/ar/zh) remain low. **Blocked on the gateway MT pipeline** (see #1419: `/ai/v1/translate` still exposes only af/en/bnt; #1444: ve/nr need a larger NLLB).
- [ ] Human review of priority locales via the translation-review UI.

The CI gate deliberately does **not** fail on low coverage % - that is aspirational machine-fill work, not a per-commit regression - it fails only on malformed files (a real, catchable regression).

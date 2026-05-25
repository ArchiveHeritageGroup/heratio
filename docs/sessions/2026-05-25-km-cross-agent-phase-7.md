# 2026-05-25 — KM cross-agent auto-publish (Phase 6 + 7 propagation)

## What shipped

The KM cross-agent auto-publish workflow (`.github/workflows/km-publish-on-close.yml`)
now lands a doc into the AHG KM corpus (`km_agent_docs`, project=<repo-short-name>)
on every issue close + PR merge across the AHG fleet. Implements the durable
"all repos / projects must constantly update KM" rule established earlier today
([feedback-always-update-km]).

| Repo | Version / commit | Status |
|---|---|---|
| **heratio** | `8377c058` (no version bump) | Phase 6 shipped earlier in day; remote already on SSH |
| **workbench** | (workflow file present; commit hash not separately tagged) | Phase 6 shipped earlier in day |
| **atom-framework** | **v2.11.20** | Phase 7 today; OAuth `workflow`-scope wall hit, Johan manually pushed |
| **atom-ahg-plugins** | **v3.44.1** (+ backlog v3.41.2 → v3.44.0 also pushed) | Phase 7 today; remote flipped to SSH first |

## How the workflow behaves

Triggers on `issues: [closed]` and `pull_request: [closed]` (PR only fires when
`merged == true`). Composes a JSON payload with title/body/url/labels and POSTs
to `${KM_BASE_URL}/api/ingest` with a bearer token. Fails open — if either
secret is missing, it logs a `::warning::` and exits 0 so a missing KM
deployment doesn't break the closing workflow.

## Required GitHub secrets (per repo)

- `KM_BASE_URL` — e.g. `https://km.theahg.co.za`
- `KM_API_KEY` — bearer admin key (per-repo key once scoping lands)

Confirm via repo Settings → Secrets and variables → Actions on each of the 4
repos above.

## OAuth workflow-scope gotcha (captured to memory)

`gh auth login` issues a `gho_*` OAuth-App token without `workflow` scope by
default. Any HTTPS push that creates/modifies `.github/workflows/*` is
rejected:

```
! [remote rejected] main -> main
  (refusing to allow an OAuth App to create or update workflow
   .github/workflows/<file> without `workflow` scope)
```

Fix: switch the repo's remote to SSH *before* the release. atom-ahg-plugins
was switched today (`git remote set-url origin git@github.com:...`).
atom-framework is still on HTTPS — Johan pushed v2.11.20 manually; flip it
before the next workflow-touching change. Full triage notes in
[[oauth-workflow-scope-https-push]] memory.

## Critical follow-ups

1. **atom-framework remote** — still HTTPS. Switch to SSH before any future
   workflow-touching release.
2. **Smoke-test** — close a throwaway issue on heratio or atom-framework
   and confirm the Actions run plus a fresh doc in `km_agent_docs`. The
   `gh run list --workflow km-publish-on-close.yml -L 1` view should show
   green; `km_ask` should return the new doc within an indexing cycle.
3. **Phase 8 — remaining AHG repos.** The "all repos update KM" rule
   applies to every repo in the fleet:
   - registry (ArchiveHeritageGroup/registry)
   - callhub
   - central (the AHG Central fleet collector — ahg-central-plugin v1.1.0
     just landed at /opt/ahgCentralPlugin per [[ahg-central-atom-plugin]])
   - km (km.theahg.co.za itself)
   - ahg-ai (the host-115 NER/MT/summarizer service)
   Apply the same workflow file + secrets per repo. Switch HTTPS remotes
   to SSH before staging.
4. **`km_ingest_doc` MCP tool** — until that ships, this Actions workflow
   is the canonical inbound channel for closed-issue and merged-PR
   surfacing. Memory [[feedback-always-update-km]] still depends on the
   in-repo `docs/sessions/` tree as the secondary surface (KM indexer
   crawls those on each pass).

## PSIS twins

Phase 7 applies to two repos that BOTH back PSIS (atom-framework =
Laravel-side framework, atom-ahg-plugins = Symfony AtoM plugins). The
"file a PSIS twin issue" rule ([feedback-always-file-psis-twin]) does not
apply here because this isn't a heratio-only feature — atom-framework
and atom-ahg-plugins are the PSIS side, and they got the workflow
directly. No separate twin needed.

## Cross-refs (memory)

- [[feedback-always-update-km]] — the durable rule this is implementing
- [[feedback-hand-execute-pushes]] — the new push-handoff rule today
- [[oauth-workflow-scope-https-push]] — the gotcha that surfaced today
- [[bin_release_git_add_all]] — release-script sweep behavior

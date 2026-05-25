# bin/gh-km-close — manual KM publish on close

A CLI fallback for the cross-agent KM auto-publish pipeline. Closes a GitHub
issue or PR via `gh` AND publishes the same close-event payload to
`km.theahg.co.za` that the GitHub Actions workflow would have posted.

**Use when:** GitHub Actions can't reach KM. Examples:

- Private repos behind enterprise GitHub with restricted runner egress.
- Self-hosted runners with outbound HTTPS to `km.theahg.co.za` blocked.
- Local-only repos that never push to GitHub.
- Manual closures from a workstation where you want KM surfaced anyway.

For the canonical case (public AHG repos with the workflow file in
`.github/workflows/km-publish-on-close.yml` and `KM_BASE_URL` + `KM_API_KEY`
configured as repo secrets), the workflow does this automatically on every
`issues: [closed]` and `pull_request: [closed]` (merged=true) event — no
need to run this script.

## Install

The script lives at `bin/gh-km-close` in this repo. Mirror it (or symlink)
into sibling AHG repos that want the same wrapper:

```
ln -s /usr/share/nginx/heratio/bin/gh-km-close /usr/share/nginx/atom-ahg-plugins/bin/gh-km-close
ln -s /usr/share/nginx/heratio/bin/gh-km-close /usr/share/nginx/workbench/bin/gh-km-close
```

(Or copy if you want each repo to own its version.)

## Required env

| Variable      | Notes                                                   |
|---------------|---------------------------------------------------------|
| `KM_BASE_URL` | e.g. `https://km.theahg.co.za`                          |
| `KM_API_KEY`  | Bearer admin key. Same secret the Actions workflow uses |

If either is unset, the script still does the GH close — it just skips the
KM publish step and prints a `warning:` line.

Optional:

| Variable     | Default                                  | Purpose                                                     |
|--------------|------------------------------------------|-------------------------------------------------------------|
| `KM_LEDGER`  | `~/.cache/gh-km-close/published.txt`     | Local idempotency ledger. Append-only, one line per publish |

## Usage

```bash
# Close issue #123 in the current repo with a close comment
bin/gh-km-close 123 --comment "resolved by reverting to v1.84.2"

# Close PR #456 (treats as PR — uses gh pr close instead of gh issue close)
bin/gh-km-close 456 --pr --comment "merged via release v1.88.0"

# Target a different repo without cd'ing
bin/gh-km-close 7 --repo ArchiveHeritageGroup/workbench --comment "..."

# Show the payload that would be sent (no GH writes, no KM POST)
bin/gh-km-close 720 --dry-run
```

Auto-detection: the script reads `gh repo view` for the current working
tree to figure out which repo to act on. Use `--repo OWNER/NAME` to override.

## Payload shape

Mirrors the GitHub Actions workflow exactly so KM sees the same document
shape regardless of source. JSON POSTed to `${KM_BASE_URL}/api/ingest`:

```json
{
  "title": "issue #123 closed: <original issue title>",
  "body": "**Closed:** issue #123 in <repo>\n**Title:** ...\n**URL:** ...\n**Closed by:** @<gh-user>\n**Labels:** ...\n**Closed at:** <ISO-8601 UTC>\n**Source:** bin/gh-km-close (Phase 7 CLI wrapper)\n**Close comment:** <optional>\n\n---\n\n<full original issue/PR body>",
  "project": "<repo-short-name>",
  "source_url": "https://github.com/<owner>/<repo>/issues/<n>",
  "author": "gh:<actual-closer-username>",
  "tags": ["issue|pr", "cli", "gh-km-close", "<label1>", "<label2>", ...]
}
```

Differences vs. the GH Actions payload:

- `author` is the live `gh api user` login (the actual person running the
  CLI) rather than the original author. Workflow uses the repo-org name.
- `tags` includes `cli` + `gh-km-close` instead of `github-actions` +
  `auto-publish`, so you can filter for "things published by hand" later if
  you need to.
- `body` includes a `**Source:** bin/gh-km-close (Phase 7 CLI wrapper)`
  marker line for the same reason.

## Idempotency

The script maintains an append-only local ledger at
`~/.cache/gh-km-close/published.txt`. Each successful KM POST adds one line:

```
ArchiveHeritageGroup/heratio#issue/720
ArchiveHeritageGroup/atom-ahg-plugins#pr/45
```

Before posting, the script greps the ledger for the same key. If matched,
the KM POST is skipped (the GH close still happens if the issue/PR is
still open — that step is gated by GitHub's own state, not the ledger).

**Failure mode:** if the KM POST returns non-2xx, the ledger is NOT updated
— a retry will attempt to post again. The GH close already happened, so
re-running the same command on an already-closed issue is safe: the script
sees `state == CLOSED` and skips the close step before retrying the POST.

**KM side-dedupe:** the `/api/ingest` endpoint does NOT dedupe on
`source_url` today. If two operators run the wrapper for the same issue
from different machines (different ledgers), KM will end up with two docs
with the same `source_url`. Acceptable trade-off for a Phase 7 fallback;
worth adding a `Idempotency-Key` header on the server when the wrapper
moves out of "fallback" territory.

## Exit codes

| Code | Meaning                                                                  |
|------|--------------------------------------------------------------------------|
| 0    | GH close OK + KM publish OK (or KM publish skipped per ledger)           |
| 1    | Invalid invocation / missing dependency / repo not detected              |
| 2    | GH close failed (KM publish not attempted)                               |
| 3    | GH close OK but KM publish failed (best-effort warning, retry-safe)      |

Code 3 is the interesting one: the GH close is durable, the ledger was
NOT updated, and a re-run will hit the "already closed" branch and only
retry the KM POST.

## Dependencies

- `gh` (GitHub CLI) — for issue/PR fetch + close.
- `jq` — payload composition.
- `curl` — KM POST.

All three are available on every AHG-managed host out of the box.

## Cross-references

- Parent issue: [heratio#716](https://github.com/ArchiveHeritageGroup/heratio/issues/716) (KM cross-agent endpoint, Phases 1–6).
- This issue: [heratio#720](https://github.com/ArchiveHeritageGroup/heratio/issues/720) (Phase 7 split).
- Workflow this wraps a fallback for: `.github/workflows/km-publish-on-close.yml`.
- KM HTTP API reference: `docs/km-public-api.md`.
- Operator memory (Claude): `feedback_always_update_km` rule + the global
  CLAUDE.md "KM auto-publish rule" section.

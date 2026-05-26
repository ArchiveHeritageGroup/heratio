# Audit-trail compliance reports

Heratio keeps a tamper-evident audit log of every meaningful action in the
system - record edits, logins, AI inferences, scheduled jobs, CLI runs.
Phase 6 of the audit-trail rollout (v1.100.0) adds an Artisan command that
exports filtered slices of that log in a format a regulator can audit.

## When to use this

- A regulator asks for "all access events to record #12345 between 1 March
  and 30 June 2026". Export with `--entity-type=information_object
  --entity-id=12345 --from=2026-03-01 --to=2026-06-30`.
- A tenant offboards and asks for their complete audit trail. Export with
  `--tenant=<tenant-id> --format=json`.
- An internal security review wants every failed login attempt last month.
  Export with `--action=login --from=2026-04-01 --to=2026-04-30
  --format=csv`.
- A staff member is suspended and you want everything they touched. Export
  with `--user=<user-id> --format=markdown`.

## Running the report

```bash
cd /usr/share/nginx/heratio
php artisan auditlog:report \
    --from=2026-03-01 --to=2026-03-31 \
    --tenant=42 \
    --format=csv \
    --out=/tmp/tenant-42-march.csv
```

All flags are optional. Run with no flags to get the full table dumped as
CSV (you almost certainly want `--limit=` or a date range when doing this).

| Flag | Meaning |
| --- | --- |
| `--from=DATE` | Only rows on or after DATE (00:00:00). Accepts `YYYY-MM-DD` or any `strtotime` shape. |
| `--to=DATE` | Only rows on or before DATE (23:59:59 for date-only values). |
| `--tenant=ID` | Only rows for tenant ID. |
| `--user=ID` | Only rows where the actor was user ID. |
| `--entity-type=TYPE` | E.g. `information_object`, `actor`, `repository`. |
| `--entity-id=ID` | Only rows for this specific entity (pair with `--entity-type`). |
| `--action=TYPE` | E.g. `create`, `update`, `delete`, `login`, `cli.command_end`. |
| `--format=csv|json|markdown` | Output format. CSV is RFC 4180. Default: csv. |
| `--out=PATH` | Write to file instead of stdout. |
| `--limit=N` | Cap row count (useful for previews). |

## Output formats

- **CSV** - All fields quoted, embedded quotes escaped per RFC 4180, CRLF
  line endings. Suitable for handing to spreadsheet tools and regulators.
- **JSON** - `{rows: [...], count: N}` shape, pretty-printed. Suitable for
  programmatic re-processing.
- **Markdown** - Human-readable table plus a `Hash of rows (sha256)` line
  in the header. Hand this to a regulator who wants to inspect the events
  by eye and confirm the report has not been tampered with after generation.

## Hash of rows (the integrity proof)

The Markdown output includes a sha256 fingerprint over the canonical JSON
view of the result set. The regulator can re-derive the same fingerprint by
re-running the same query against the JSON / CSV export. If the digest
matches, the report content matches what was in the database when the
report was generated.

The signed proof of "what was in the database has not been tampered with"
is the underlying hash chain - run `php artisan auditlog:verify-chain` to
walk every chained row and confirm the Ed25519 signatures hold.

## Deterministic ordering

Re-running the same filter produces byte-identical output. Rows come out in
`seq ASC, id ASC` order, which is the same order the hash chain verifier
walks them. This matters when comparing two exports taken at different
moments - new appended rows show up at the tail; nothing existing shuffles.

## See also

- `php artisan auditlog:verify-chain` - prove the hash chain is intact
- `php artisan audit:prune` - apply the retention policy (defaults to 365
  days, configurable via the Compliance settings tile)
- `docs/reference/audit-trail-tamper-evidence.md` - Phase 5 background on
  the Ed25519 / JCS / SHA-256 chain
- `docs/reference/audit-trail-phase-6.md` - Phase 6 implementation reference

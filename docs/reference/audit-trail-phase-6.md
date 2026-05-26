# Audit-trail Phase 6 - tenant scope, CLI/job hooks, compliance reports

Heratio v1.100.0 ships Phase 6 of issue #676 (Audit trail). Phase 5 added
hash-chain tamper evidence + Ed25519 signatures + the append-only MySQL
triggers + the `auditlog:verify-chain` CLI. Phase 6 makes the chain usable
for multi-tenant compliance work:

1. `tenant_id` column on `ahg_audit_log` so reports can be scoped per tenant
2. `AuditableCommand` + `AuditableJob` traits so Artisan commands and queued
   jobs join the same hash chain web requests already use
3. `auditlog:report` Artisan command that produces deterministic CSV / JSON /
   Markdown exports with a sha256-of-rows fingerprint suitable for regulator
   hand-off

Anomaly detection (statistical/rule-based) and off-host chain-head anchoring
remain Phase 7+.

## tenant_id propagation

A new column lands automatically on first boot after upgrade:

```sql
ALTER TABLE ahg_audit_log
    ADD COLUMN tenant_id INT UNSIGNED NULL AFTER user_id,
    ADD KEY idx_audit_tenant_action_created (tenant_id, action, created_at);
```

Legacy rows keep `tenant_id = NULL` and are treated as belonging to the
"single-tenant" / unknown case. New writes via `AuditLogger` /
`ChainedAuditWriter` resolve the tenant in this precedence:

1. explicit constructor argument or `withTenant(int)` override
2. `config('ahg.tenant_id')` (env-backed via `AHG_TENANT_ID`)
3. `Auth::user()->tenant_id` when authenticated
4. NULL

The composite index `(tenant_id, action, created_at)` keeps tenant-scoped
report queries off the table-scan path.

The `tenant_id` is included in the JCS canonical payload signed by the hash
chain, so a tenant can prove their slice of the audit log is intact without
having to verify rows that belong to other tenants. Per-tenant separate
chains (one head row per tenant) remain Phase 7.

## CLI command audit hooks

Drop the trait into any `Illuminate\Console\Command` subclass:

```php
use AhgAuditTrail\Concerns\AuditableCommand;

class MyCommand extends Command
{
    use AuditableCommand;

    protected $signature = 'app:do-thing {target}';

    public function handle(): int
    {
        $this->auditCommandStart();
        try {
            // ... real work ...
            return self::SUCCESS;
        } finally {
            $this->auditCommandEnd($this->lastExitCode ?? self::SUCCESS);
        }
    }
}
```

Both calls insert a row in `ahg_audit_log` via `AuditLogger->logAction()` so
they ride the same Ed25519 / JCS / SHA-256 chain as web rows.

- `cli.command_start` row captures: command name, arguments, options,
  host, pid, started_at (ISO-8601 UTC), `run_uuid` correlating the pair.
- `cli.command_end` row captures: command name, exit code, duration_ms,
  host, pid, ended_at, and the same `run_uuid` for join lookups.

Sensitive options (`--password`, etc) should be redacted by the consuming
command before delegating to `auditCommandStart()`. The trait does not
attempt to introspect option names.

## Queued-job audit hooks

```php
use AhgAuditTrail\Concerns\AuditableJob;

class MyJob implements ShouldQueue
{
    use AuditableJob;

    public function handle(): void
    {
        $this->auditJobStart();
        try {
            // ... real work ...
            $this->auditJobEnd('success');
        } catch (\Throwable $e) {
            $this->auditJobEnd('failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

The trait also provides `before()`, `after()`, and `failed()` methods so a
caller that already wires Laravel's queue middleware can get instrumentation
without touching `handle()`. If the consuming job already defines its own
`failed()`, the trait's version is shadowed - explicitly call
`$this->auditJobEnd('failed')` from inside the override.

Captured fields:

- job class name, queue name, connection name
- `attempts()` count (when invoked from the worker)
- payload sha256 hash (fingerprint of all serialised public properties)
- host, pid, started_at, ended_at, duration_ms, status

The payload hash deliberately does NOT include the full payload - we keep
sensitive job arguments out of the audit log and rely on the hash to prove
two end rows came from "the same job + same payload".

## auditlog:report

```
php artisan auditlog:report
    [--from=DATE] [--to=DATE]
    [--tenant=ID] [--user=ID]
    [--entity-type=TYPE] [--entity-id=ID]
    [--action=TYPE]
    [--format=csv|json|markdown]
    [--out=PATH]
    [--limit=N]
```

Common shapes:

```bash
# Tenant 42, all create/update/delete activity in March
php artisan auditlog:report --tenant=42 --from=2026-03-01 --to=2026-03-31 \
    --format=csv --out=/tmp/tenant-42-march.csv

# Everything touching a single information object (chain-of-custody export)
php artisan auditlog:report --entity-type=information_object --entity-id=12345 \
    --format=markdown --out=/tmp/io-12345-history.md

# All failed CLI commands in the last week
php artisan auditlog:report --action=cli.command_end --from=2026-05-19 \
    --format=json | jq '.rows[] | select(.metadata.exit_code != 0)'
```

Output rules:

- Deterministic ordering: `seq ASC, id ASC`. Same filter, same bytes.
- CSV is RFC 4180: all fields quoted, embedded `"` escaped as `""`, CRLF
  line endings.
- JSON wraps the rows in `{rows: [...], count: N}` and pretty-prints with
  `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
- Markdown puts a `Hash of rows (sha256)` line in the header. The hash is
  computed over a canonical JSON view (row keys sorted, JSON columns decoded)
  so a regulator can re-derive the same digest from the JSON or CSV export
  and confirm the report has not been tampered with after generation.
- `--out` writes to the file; otherwise everything goes to stdout (suitable
  for piping into `gpg --sign --armor` etc).

The report itself is NOT signed end-to-end - the signed proof of integrity
is the underlying chain (verified with `auditlog:verify-chain`). The
hash-of-rows line lets the regulator confirm "the CSV I'm holding matches
the database snapshot the chain proves" without re-running the full chain
verification.

## Files touched

- `packages/ahg-audit-trail/database/install-tenant.sql` - new
- `packages/ahg-audit-trail/src/Providers/AhgAuditTrailServiceProvider.php` -
  registers ReportCommand + auto-applies install-tenant.sql
- `packages/ahg-audit-trail/src/Services/AuditLogger.php` - resolves and
  injects `tenant_id` on every write
- `packages/ahg-audit-trail/src/Concerns/AuditableCommand.php` - new
- `packages/ahg-audit-trail/src/Concerns/AuditableJob.php` - new
- `packages/ahg-audit-trail/src/Console/Commands/ReportCommand.php` - new
- `docs/help/audit-trail-reports.md` - operator-facing help article
- `docs/reference/audit-trail-phase-6.md` - this file

## Out of scope

Phase 7 will tackle:

- Anomaly detection (statistical / rule-based flagging of audit rows)
- Off-host chain-head anchoring (RFC 3161 timestamping)
- Per-tenant separate chains (one head row per tenant, allows full
  tenant offboarding without breaking the global chain)

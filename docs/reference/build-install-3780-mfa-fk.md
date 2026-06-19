# build-install / E2E Parity FK 3780 - user_mfa_recovery_code signedness

## Symptom
`build-install-artifact` and `E2E Parity Tests` both fail at the core-schema
load with:

```
ERROR 3780 (HY000) at line NNNNN: Referencing column 'user_id' and referenced
column 'user_id' in foreign key constraint 'fk_mfa_recovery_user' are incompatible.
```

Both workflows had **never passed** (60+ red runs). The error is NOT
reproducible by loading `database/core/00_core_schema.sql` standalone - that
file is valid and loads cleanly on any MySQL 8.0.x.

## Root cause
Two sources defined the MFA tables with **conflicting integer signedness**:

- `packages/ahg-security-clearance/database/install.sql` created
  `user_totp_secret.user_id` and `user_mfa_recovery_code.user_id` as
  **`int unsigned`**.
- `database/core/00_core_schema.sql` (regenerated from prod) and the **live
  prod DB** have both as signed **`int`**.

During install the plugin's tables get created first (service-provider
auto-install on the first `php artisan` boot, before the core-schema stage),
so `user_mfa_recovery_code` exists as `int unsigned` with a deferred FK
(`FOREIGN_KEY_CHECKS=0`). Then the core schema does
`DROP TABLE user_totp_secret; CREATE TABLE user_totp_secret (... user_id int ...)`
- creating the parent as **signed**. MySQL validates the surviving unsigned
child's FK against the new signed parent -> **3780**, at the parent's CREATE
line. That is why the child exists (unsigned) but the parent is missing after
the failed load.

A signed parent + unsigned child FK is genuinely incompatible; MySQL enforces
this even though both are `int`.

## Why it didn't reproduce locally at first
Loading only the core schema (both signed) never triggers it. You must
recreate the collision: create the tables `int unsigned`, then re-create the
parent as signed `int`:

```sql
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE user_totp_secret (user_id int unsigned NOT NULL, PRIMARY KEY(user_id)) ENGINE=InnoDB;
CREATE TABLE user_mfa_recovery_code (id int unsigned NOT NULL AUTO_INCREMENT, user_id int unsigned NOT NULL,
  PRIMARY KEY(id), KEY(user_id),
  CONSTRAINT fk_mfa_recovery_user FOREIGN KEY (user_id) REFERENCES user_totp_secret(user_id)) ENGINE=InnoDB;
DROP TABLE IF EXISTS user_totp_secret;
CREATE TABLE user_totp_secret (user_id int NOT NULL, PRIMARY KEY(user_id)) ENGINE=InnoDB;  -- raises 3780
```

## Fix
Align the plugin to signed `int` (matching prod + the core schema):
`packages/ahg-security-clearance/database/install.sql` - both
`user_totp_secret.user_id` and `user_mfa_recovery_code.user_id` are now
`int` (signed). The source of truth for these column types is the core schema
+ production; keep all three in agreement.

The earlier `00_core_schema.sql` reorder (user_totp_secret ahead of
user_mfa_recovery_code, v1.154.16) was a harmless red herring - both core defs
were already signed; ordering was never the problem.

## Diagnostic that found it
`bin/install` stage 6 prints `@@version / @@sql_mode / @@foreign_key_checks`
and, on load failure, `SHOW CREATE TABLE` for the FK pair. The `SHOW CREATE`
revealed the child as `int unsigned` (vs the file's signed `int`), which
pointed at a second creator (the plugin). Keep that diagnostic.

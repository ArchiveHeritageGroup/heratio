# When a dev vhost served the production database - and how to check what was written

**Summary.** On 24 August 2026 the Heratio dev instance served production data for roughly six hours, because an opcached Laravel config naming the production database survived on the dev vhost under `opcache.validate_timestamps=0`. An automated crawler ran against dev inside that window, filling forms and clicking write-shaped controls. Nothing reached production content. This records how that was established, because the investigation surfaced three traps that would mislead anyone repeating it.

## What happened

The dev vhost on port 8090 answered requests for records that exist in the production database and not in the dev one. The first 404 for those records came only after php-fpm was restarted and flushed the cached config. Because that pool runs `opcache.validate_timestamps=0`, a compiled config naming the wrong database persists until the pool restarts - editing `.env` does not dislodge it, and no error is raised. The origin of that cached config was not established.

An authenticated crawler ran against the dev vhost inside the window, performing several thousand clicks and several hundred field fills. It skips destructive controls by keyword, so no deletions were possible, but it clicked contact-form sends, an "add new" on an actor form, and an "add and save" on a help article.

## Three traps in the investigation

**Timestamps are stored in UTC while nginx logs in local time.** Querying the database with log-derived bounds silently shifts the window by two hours. Doing that produced an apparent write inside the window that, corrected, fell 55 minutes outside it. Convert before querying, and confirm the direction: MySQL's `NOW()` on this host returns local time while PHP runs UTC, so the two disagree by design.

**The msmtp log does not see application mail.** The application's `.env` names the sendmail mailer, but `AppServiceProvider` calls a database-driven mail configuration at boot that overrides it from the `email_setting` table. At runtime `config('mail.default')` resolves to SMTP against the provider directly, so mail never passes through the host relay and `/var/log/msmtp.log` is blind to it. Concluding "no mail was sent" from that log is wrong. Verify with `config('mail.default')` and the resolved transport, not with `env()`.

**Write-shaped controls are often navigation.** The crawler's largest category of write-shaped clicks was an "Import" control appearing on every actor page. It is an anchor in the theme's main-menu partial - dropdown navigation to an import page. No import control exists in any actor view or route. Perform the grep before treating a click count as a write count.

## How to establish whether anything was written

Convert the window to UTC, then sweep rather than sample. The `object` table is the class-table-inheritance base, so zero rows created or updated there means no actors, users or descriptions were written. Then enumerate every table carrying a `created_at` from `information_schema` and count rows in the window, which separates content writes from background noise. Here that yielded around two thousand rows, all machine-generated: entity cache, cron runs, AI inference log, audit log, one visit event. No content writes at all.

The audit log is the cleanest discriminator for an authenticated agent. Every row in the window carried a null user id, and the crawler was authenticated, so it would have stamped one. Absence of a user id across the whole window is positive evidence the crawler wrote nothing, not merely absence of evidence.

## Two things that are not incidents

The public demo instance is open and refreshed daily. Registrations, user creation, edits and imports there are ordinary use and are discarded by the refresh. Do not raise them as incidents.

Low-volume automated login probing against the public instance is continuous background noise rather than an event. Compare against the previous day before treating a count as a spike; here it was lower than the day before.

## What remains open

Why an opcached config naming the production database existed on the dev vhost at all. That is the actual defect, and it is independent of anything the crawler did. A dev instance that can silently serve production data is a standing risk whatever happens to be pointed at it.

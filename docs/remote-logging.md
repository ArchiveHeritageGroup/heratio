# Remote logging (syslog / SIEM)

Heratio logs through Laravel's channels (`config/logging.php`). To ship logs to a central syslog server or SIEM (Splunk, Graylog, Wazuh, Elastic, rsyslog), add the `remote_syslog` channel to the log stack in `.env`:

```dotenv
LOG_STACK=daily,remote_syslog
LOG_REMOTE_SYSLOG_HOST=siem.example.org
LOG_REMOTE_SYSLOG_PORT=514
LOG_REMOTE_SYSLOG_LEVEL=info
LOG_REMOTE_SYSLOG_IDENT=heratio
```

Then clear the config cache: `sudo -u www-data php artisan config:clear`.

- **Transport.** UDP syslog (RFC 5424) via Monolog's `SyslogUdpHandler`. UDP drops messages silently if the collector is down; keep `daily` in the stack so a local copy always exists.
- **Level.** `LOG_REMOTE_SYSLOG_LEVEL` is independent of `LOG_LEVEL`, so the SIEM can receive `info` and above while the local file keeps `debug`.
- **Ident.** `LOG_REMOTE_SYSLOG_IDENT` is the program name in each message. Use one per instance (`heratio-prod`, `heratio-dev`) when several instances send to one collector.
- **Local syslog only.** The `syslog` channel writes to the host's own syslog daemon, which can forward over TLS (rsyslog `omfwd` with `StreamDriver="gtls"`). Use that when the SIEM requires TLS: `LOG_STACK=daily,syslog`.
- **What is sent.** Everything the application logs: errors, warnings, job progress and audit warnings. Security events are also in the audit log (`/admin/acl/audit-log`), which is not shipped by this channel.

Settings are read from `.env`, not from the database, so logging still works when the database is down. That is when you need it most.

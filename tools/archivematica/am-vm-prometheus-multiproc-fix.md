# AM VM fix: move `PROMETHEUS_MULTIPROC_DIR` off `/tmp` (heratio#1431)

**Where this runs:** the Archivematica server (VM at `192.168.0.150`), **not** Heratio.
This is an operator/VM-side change - there is no Heratio code involved.

## Problem

`MCPClient` writes Prometheus multiprocess counter files to `PROMETHEUS_MULTIPROC_DIR`,
which defaults under `/tmp` (`/tmp/prometheus-stats.../counter.db`). When
`systemd-tmpfiles` sweeps `/tmp` (periodic timer or reboot) those files vanish while
the workers still hold references, and the next transfer fails at **"Assign UUIDs to
directories"** with a `FileNotFoundError`. Restarting `archivematica-mcp-client`
clears it, but it recurs on the next `/tmp` wipe.

## Fix

Point the variable at a persistent, service-owned directory and recreate it on boot.

### 1. Persistent directory, recreated on every boot (tmpfiles.d)

Create `/etc/tmpfiles.d/archivematica-prometheus.conf`:

```
# type path                                     mode uid       gid       age argument
d      /var/lib/archivematica/prometheus-multiproc 0750 archivematica archivematica -   -
```

Adjust `archivematica:archivematica` to the user/group the MCPClient service runs as
(check with `systemctl show -p User archivematica-mcp-client`; on some installs it is
`root` or a container user). Apply now without waiting for a reboot:

```bash
sudo install -d -o archivematica -g archivematica -m 0750 /var/lib/archivematica/prometheus-multiproc
sudo systemd-tmpfiles --create /etc/tmpfiles.d/archivematica-prometheus.conf
```

### 2. Set the env var on the worker unit(s) (systemd drop-in)

For each worker that exports Prometheus metrics - at least `archivematica-mcp-client`,
and `archivematica-mcp-server` if it is also instrumented:

```bash
sudo systemctl edit archivematica-mcp-client
```

In the drop-in that opens, add:

```ini
[Service]
Environment=PROMETHEUS_MULTIPROC_DIR=/var/lib/archivematica/prometheus-multiproc
Environment=prometheus_multiproc_dir=/var/lib/archivematica/prometheus-multiproc
```

(Both the upper- and lower-case names are set because different `prometheus_client`
versions read different spellings.) Repeat `systemctl edit` for any sibling worker.

### 3. Reload + restart

```bash
sudo systemctl daemon-reload
sudo systemctl restart archivematica-mcp-client
# and any sibling worker you edited, e.g. archivematica-mcp-server
```

## Verify

```bash
# The env var is set on the running service:
systemctl show archivematica-mcp-client -p Environment | tr ' ' '\n' | grep -i multiproc
# The directory exists, service-owned, and is being written to during a transfer:
ls -la /var/lib/archivematica/prometheus-multiproc
```

Then run a transfer end to end and confirm it no longer stalls at "Assign UUIDs to
directories". Optionally simulate the original trigger to prove durability:

```bash
sudo systemd-tmpfiles --clean   # sweep /tmp as the timer would
# start another transfer -> it should still complete (nothing lived under /tmp)
```

## Why it matters

Low frequency, high blast radius: when it triggers, transfers stall silently
mid-pipeline until someone restarts the worker. Moving the directory off `/tmp` and
recreating it on boot removes the recurring manual-restart failure mode for good.

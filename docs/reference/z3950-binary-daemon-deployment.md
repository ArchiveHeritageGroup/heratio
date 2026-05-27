# Native Z39.50 Binary Daemon - Deployment Runbook

**Issue:** heratio#759
**Status:** Optional operator deployment. Heratio ships SRU 2.0 (`/sru`) as the primary federated-discovery surface; this runbook describes how to add a binary Z39.50 wire-protocol listener for vendors who specifically require it (eg legacy library systems that only speak raw Z39.50).

## Architecture

Heratio's PHP application stays decoupled from the binary daemon. The daemon is a YAZ-based sidecar that proxies requests to the Heratio SRU endpoint:

```
[Z39.50 client] --binary Z39.50/TCP--> [yaz-ztest / simpleserver]
                                           |
                                           v
                                    [SRU 2.0 HTTP /sru]
                                           |
                                           v
                                  [Heratio Laravel app]
```

This means:
- The PHP application requires zero new code
- The daemon process can be restarted independently of php-fpm
- The same CQL parser + record renderers are used for both surfaces
- The daemon runs as a separate systemd unit, off the main webserver port

## Prerequisites

- Linux host (Ubuntu 22.04 LTS or later recommended)
- YAZ toolkit installed: `apt install yaz`
- A reachable Heratio SRU endpoint (default: `https://heratio.theahg.co.za/sru`)

## Step 1: Install YAZ + the SRU-to-Z39.50 bridge daemon

```bash
sudo apt update
sudo apt install yaz libyaz-server-perl
```

`yaz-ztest` is the reference Z39.50 server shipped with YAZ. It can be configured to proxy `searchRetrieve` requests to an upstream SRU endpoint via the `srw-sru` config block.

## Step 2: Configure the daemon

Create `/etc/yaz/heratio-z3950.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<yazgfs>
  <listen id="public">tcp:@:210</listen>

  <server>
    <listenref>public</listenref>
    <config>/etc/yaz/heratio-z3950.cfg</config>
  </server>
</yazgfs>
```

Create `/etc/yaz/heratio-z3950.cfg`:

```
# Proxy bib-1 + USmarc Z39.50 queries to the Heratio SRU endpoint.
# Records returned in MARC21 syntax by default.

zoom:
  Heratio:
    name: heratio
    url: https://heratio.theahg.co.za/sru
    recordSyntax: usmarc
    transform: bib1.idx
    cclmap: bib1.ccl
```

Heratio's SRU `searchRetrieve` understands the CQL queries that YAZ's bib-1 -> CQL transform produces, so the bridge is straightforward.

## Step 3: systemd unit

`/etc/systemd/system/heratio-z3950.service`:

```ini
[Unit]
Description=Heratio Z39.50 binary daemon (YAZ -> SRU bridge)
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/yaz-ztest -f /etc/yaz/heratio-z3950.xml -l /var/log/yaz/heratio.log
Restart=on-failure
User=yaz
Group=yaz
ProtectSystem=full
ProtectHome=true

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo useradd -r -s /bin/false yaz
sudo mkdir -p /var/log/yaz && sudo chown yaz:yaz /var/log/yaz
sudo systemctl daemon-reload
sudo systemctl enable --now heratio-z3950
```

## Step 4: Firewall

Z39.50 default port is 210. Make sure your edge firewall opens it (TCP) only to the consortium peers you intend to expose to:

```bash
sudo ufw allow from <peer-IP> to any port 210 proto tcp
```

Avoid leaving port 210 open to the public internet without rate-limiting; Z39.50 has no native auth.

## Step 5: Verify

```bash
# From any host with yaz-client installed:
yaz-client tcp:<heratio-host>:210/heratio
Z> find @attr 1=4 africa
Z> show 1
```

The client should return MARC21 records pulled through Heratio's SRU endpoint.

## Step 6: Optional - auth-gating

Native Z39.50 supports username/password auth via the `init` request's `idAuthentication` element. If you need it, configure YAZ to require auth and store credentials in `/etc/yaz/heratio-z3950.passwd`. See `man yaz-ztest` for the directive set.

For consortium federations where mutual TLS makes more sense than username/password, use `stunnel` in front of the YAZ daemon and require client certificates.

## Operational notes

- YAZ logs to `/var/log/yaz/heratio.log` by default. Tail it during deployment to verify queries are reaching the SRU endpoint.
- The daemon caches no data; every search hits Heratio fresh. If you need lower latency, put a Varnish layer in front of `/sru` rather than caching in YAZ.
- The Heratio SRU endpoint already enforces a `maximumRecords` cap of 100; the YAZ bridge respects whatever the client requests up to that ceiling.

## When NOT to deploy this daemon

- If your consortium consumers can speak SRU directly (modern library systems, discovery services), use `/sru` directly. Faster, no extra hop, no extra surface to operate.
- If you only need to publish to Z39.50 clients occasionally, leave the daemon stopped and start it on demand.
- If your Heratio instance is internet-public and you do not have a peer-allowlisted firewall, do not expose port 210 globally. Z39.50 has no native rate-limiting.

## Cross-refs

- SRU endpoint reference: `docs/reference/z3950-implementation.md`
- User guide: `docs/help/z3950-user-guide.md`
- NLSA LMS Tender §7.1

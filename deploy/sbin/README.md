# deploy/sbin - host-level operational scripts

These run as **root from `/usr/local/sbin`**, outside the application. They are
tracked here because they carry logic that took real incidents to arrive at, and
a rebuilt host that lost them would rediscover those failures from scratch.

They are a **mirror, not the running copy**. Editing a file here changes nothing
until it is installed (below), and editing the live copy without updating this
one is what let the pair drift apart in the first place (#1465).

| Script | Purpose |
|---|---|
| `heratio-deploy.sh` | The sanctioned prod deploy. Pre-deploy DB dump → fast-forward-only pull from GitHub → composer + npm build → ownership restore → migrate → cache clear → php-fpm reload → demo-baseline rebase → health check. |
| `heratio-demo-snapshot.sh` | Captures the live DB as the demo *baseline* and stamps it with the deployed version. |
| `heratio-demo-reset.sh` | 02:00 cron. Restores the demo from that baseline, wiping visitor changes. Scheduled by `deploy/cron/heratio-demo-reset`. |

## Install

```bash
sudo install -o root -g root -m 0750 deploy/sbin/heratio-deploy.sh        /usr/local/sbin/
sudo install -o root -g root -m 0750 deploy/sbin/heratio-demo-snapshot.sh /usr/local/sbin/
sudo install -o root -g root -m 0750 deploy/sbin/heratio-demo-reset.sh    /usr/local/sbin/
sudo install -o root -g root -m 0644 deploy/cron/heratio-demo-reset       /etc/cron.d/
```

Demo-only: `heratio-demo-*` and their cron entry belong on the public demo host.
`heratio-deploy.sh` is useful on any instance deployed from GitHub.

## Two contracts worth understanding before editing

**1. The baseline carries a version stamp.** `heratio-demo-snapshot.sh` writes
`heratio-demo.version` beside the dump; `heratio-demo-reset.sh` refuses to
restore when that does not match the deployed `version.json`.

This exists because the baseline is a *full* dump, so restoring an old one rolls
the **schema** back, not just the data. Between 2026-07-10 and 2026-08-12 the
baseline went unrefreshed while releases kept shipping, so every night the reset
reverted prod's schema by a month; it only ever recovered because a deploy
happened to re-run migrations. Refusing is the safe failure - the demo keeps a
day of visitor changes, which is cosmetic, rather than serving current code
against a schema that predates it.

**2. `heratio-deploy.sh` restores ownership after running root tools.** composer,
npm and git all run as root there. On 2026-07-10 a root-run git left 399 files in
`.git` root-owned; `www-data` could then no longer update `refs/heads/main`, HEAD
detached, and this script's own `git checkout main` would have rolled the app
back 315 releases. Deploys therefore moved to manual pulls, which skip the
baseline rebase - which caused contract 1's failure. One ownership slip, five
symptoms.

The chown is scoped to `.git` plus tracked files and their directories.
`vendor/` and `node_modules/` stay root-owned deliberately: git does not manage
them and chowning ~3M files would add minutes to every deploy for nothing.

## Deploy ordering that matters

`heratio-deploy.sh` health-checks `https://heratio.org/` **before** rebasing the
demo baseline, and only rebases when it gets a 200.

The order used to be the other way round, which meant a broken deploy was
snapshotted as the "golden" state the 02:00 reset restores to - baking the
breakage in and re-applying it nightly. Checking first makes the failure safe:
unhealthy deploy → baseline not rebased → the reset's version guard sees a stale
baseline and refuses → the demo keeps serving current code instead of being
reset into a known-bad state. Fix the app, then run
`heratio-demo-snapshot.sh` once it is healthy.

The URL was `https://heratio.theahg.co.za/` until v1.154.590. That domain is a
301 redirect and never served this docroot (`/usr/share/nginx/heratio/public` is
served by `heratio.org.conf`), so the check had been reporting a redirect rather
than confirming the app was up.

## Keeping this directory honest

`bin/check-config-drift` compares every tracked host file against its installed
counterpart, and `deploy/cron/heratio-config-drift` runs it **weekly as root**
(Monday 07:00), raising a Workbench notification if anything has diverged. It
runs as root deliberately: these scripts are mode 0750, so an unprivileged run
reports them "unreadable" and skips them.

After changing either copy, check immediately rather than waiting for Monday:

```bash
sudo ./bin/check-config-drift --diff
```

Manual equivalent, if you prefer:

```bash
for f in heratio-deploy.sh heratio-demo-reset.sh heratio-demo-snapshot.sh; do
  diff -q "deploy/sbin/$f" "/usr/local/sbin/$f" || echo "DRIFT: $f"
done
```

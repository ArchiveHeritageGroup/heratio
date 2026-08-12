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

## Known issue

`heratio-deploy.sh` health-checks `https://heratio.theahg.co.za/`, but
`/usr/share/nginx/heratio/public` is served by `heratio.org.conf`
(`server_name heratio.org www.heratio.org`). That URL currently returns **301**,
so the check reports a redirect rather than confirming the app is up. It only
echoes the code and never fails the deploy, so it has gone unnoticed. Left as-is
here so this copy matches the running one exactly; fix both together.

## Keeping this directory honest

Nothing enforces that these match `/usr/local/sbin`. Until a drift check exists
(#1465), after changing either copy:

```bash
for f in heratio-deploy.sh heratio-demo-reset.sh heratio-demo-snapshot.sh; do
  diff -q "deploy/sbin/$f" "/usr/local/sbin/$f" || echo "DRIFT: $f"
done
```

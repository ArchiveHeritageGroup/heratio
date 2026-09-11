# heratio-dev push is self-service over SSH (2026-09-11)

heratio-dev now pushes to origin on its own. `sudo -u www-data ./bin/release`
commits, tags and pushes; prod then pulls. The old root-key push workaround is
retired.

How it is wired: dev `origin` is SSH (`git@github.com:ArchiveHeritageGroup/heratio.git`);
`www-data`'s key `/var/www/.ssh/id_ed25519` is a read-write deploy key on the
repo ("heratio-dev www-data"); the key is pinned repo-level via
`git config core.sshCommand`, because `sudo -u www-data` keeps the caller's HOME
and would otherwise miss the key. One consequence: remote git ops on heratio-dev
authenticate only as `www-data` - other users must use `sudo -u www-data git ...`.

# Heratio — Docker test stack

A self-contained way to bring up Heratio for testing, on any host with
Docker installed. Three services come up via Compose:

| service | image | role |
|---|---|---|
| `heratio` | built from `docker/Dockerfile` | PHP 8.3 + nginx + Heratio app, supervised |
| `mysql`   | `mysql:8.0` | persistent MySQL 8, utf8mb4, native auth |
| `elasticsearch` | `elasticsearch:8.13.4` | single-node, security off |

Cantaloupe (IIIF) and Ollama (AI) are intentionally **not** included — those
are remote-only on production, and not needed to test the app itself.

## Quick start

```bash
cd /usr/share/nginx/heratio
cp docker/.env.docker.example docker/.env.docker
# edit docker/.env.docker — set ADMIN_PASSWORD at minimum

docker compose -f docker/docker-compose.yml --env-file docker/.env.docker up -d --build
docker compose -f docker/docker-compose.yml logs -f heratio
```

First boot runs `docker/init.sh` which:
1. Generates `.env` from the compose env-vars and runs `artisan key:generate`
2. Loads `database/core/*.sql` (~995 tables)
3. Runs `heratio:install-bootstrap --pass=1` then `--pass=2`
4. Loads `database/seeds/*.sql`
5. Creates an admin user
6. Drops + recreates the `heratio_*` Elasticsearch indices

A marker at `storage/.heratio-installed` makes subsequent boots skip the
schema/seed work — they only re-run pass-2 (idempotent) and verify ES is up.

Once healthy, point your browser at `http://<host>:8088/` and log in with the
admin credentials from `.env.docker`.

## Reset

```bash
docker compose -f docker/docker-compose.yml down -v
```

`-v` drops the volumes — MySQL, ES, uploads, backups all wiped. Next `up`
starts from scratch.

## Inspect a running container

```bash
# shell in the app container
docker exec -it heratio-app bash

# tail the install log
docker exec -it heratio-app tail -f /var/www/heratio/storage/logs/laravel.log

# run a one-off artisan command
docker exec -it heratio-app php artisan tinker
```

## Running on a libvirt VM

```bash
# from the libvirt host (e.g. .112)
sudo bin/heratio-vm.sh   # provisions an Ubuntu 24.04 VM, installs Docker,
                         # clones this repo, runs the stack
```

See `bin/heratio-vm.sh` for the cloud-init / virt-install plumbing.

## What's NOT inside the container

These stay external on purpose:

- **Cantaloupe** (IIIF deep-zoom for TIFF/JP2). Heratio's `ahg-iiif-viewer.js`
  auto-detects TIFF/JP2 and routes to a Cantaloupe instance — point it via
  the `iiif_base_url` setting in the Dropdown Manager.
- **Ollama / vLLM** (AI services — HTR, NER, condition scan). Per project
  policy, AI is remote-only. Set `voice_local_llm_url` to your GPU host.
- **Cron / queue worker.** Add a `queue-worker` service to compose if you
  need background jobs. The supervisor config has a stub.
- **TLS termination.** The container speaks plain HTTP on port 80. Front
  it with the host's nginx (or Traefik) for HTTPS.

## Caveats

- Build is large (~1.5 GB image) because Heratio pulls ~94 packages via
  composer. Trade-off: a fully self-contained image with no dependency on
  the host's PHP / node setup.
- ES 8 requires `vm.max_map_count >= 262144` on the host kernel. If ES
  refuses to start, run: `sudo sysctl -w vm.max_map_count=262144` (and add
  to `/etc/sysctl.conf` for persistence).
- First boot takes a while — ~3 min for the schema + seed pass.
